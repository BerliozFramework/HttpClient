<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Adapter;

use Berlioz\Http\Client\Components\HeaderParserTrait;
use Berlioz\Http\Client\Exception\NetworkException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Client\Proxy;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class StreamAdapter.
 */
class StreamAdapter extends AbstractAdapter
{
    use HeaderParserTrait;

    public function __construct(
        protected ?int $timeout = 2,
        protected ?float $connectTimeout = null,
    ) {
    }

    public function getName(): string
    {
        return 'stream';
    }

    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface
    {
        $dateTime = new DateTimeImmutable();
        $initTime = microtime(true);

        $proxy = Proxy::fromContext($context, $request);
        $fp = $this->createSocketClient($request, $context);

        try {
            $connectTime = microtime(true) - $initTime;

            // Write request (absolute-form through a proxy, except inside a tunnel)
            $this->writeRequest($fp, $request, 'https' !== $request->getUri()->getScheme() ? $proxy : null);

            $requestTime = microtime(true) - $connectTime;

            $response = $this->readResponse($fp, $request->getMethod(), $headersTime, $request);

            $waitTime = $headersTime - $requestTime;
            $totalTime = $initTime - microtime(true);

            $this->timings = new Timings(
                dateTime: $dateTime,
                send: $requestTime / 1000,
                wait: $waitTime / 1000,
                receive: ($totalTime - ($headersTime + $waitTime)) / 1000,
                total: $totalTime / 1000
            );

            return $response;
        } finally {
            // Close socket
            is_resource($fp) && fclose($fp);
        }
    }

    /**
     * Create stream context.
     *
     * Without proxy, the TLS options are those of the origin. With an HTTPS proxy, they are those of the proxy;
     * the origin TLS options are applied later, inside the tunnel (see {@see StreamAdapter::openTunnel()}).
     *
     * @param HttpContext|null $context
     * @param Proxy|null $proxy
     *
     * @return resource
     */
    protected function createContext(?HttpContext $context = null, ?Proxy $proxy = null)
    {
        $contextOptions = [];

        if (null !== $context) {
            if (null === $proxy) {
                $contextOptions['ssl'] = $this->getSslOptions($context);
            }

            if (true === $proxy?->isSecure()) {
                $contextOptions['ssl'] = $this->getProxySslOptions($context);
            }
        }

        return stream_context_create($contextOptions);
    }

    /**
     * Get origin TLS options.
     *
     * @param HttpContext $context
     *
     * @return array
     */
    protected function getSslOptions(HttpContext $context): array
    {
        $options = [
            'verify_peer' => $context->ssl_verify_peer,
            'verify_peer_name' => $context->ssl_verify_host,
            'allow_self_signed' => !$context->ssl_verify_peer,
            'cafile' => $context->ssl_cafile,
            'capath' => $context->ssl_capath,
            'local_cert' => $context->ssl_local_cert,
            'passphrase' => $context->ssl_local_passphrase ?? $context->ssl_local_cert_passphrase,
            'local_pk' => $context->ssl_local_pk,
            'ciphers' => $context->ssl_ciphers,
        ];

        return array_filter($options, fn($value) => null !== $value);
    }

    /**
     * Get proxy TLS options.
     *
     * @param HttpContext $context
     *
     * @return array
     */
    protected function getProxySslOptions(HttpContext $context): array
    {
        $options = [
            'verify_peer' => $context->proxy_ssl_verify_peer,
            'verify_peer_name' => $context->proxy_ssl_verify_host,
            'allow_self_signed' => !$context->proxy_ssl_verify_peer,
            'cafile' => $context->proxy_ssl_cafile ?? $context->ssl_cafile,
            'capath' => $context->proxy_ssl_capath ?? $context->ssl_capath,
        ];

        return array_filter($options, fn($value) => null !== $value);
    }

    /**
     * Create socket client.
     *
     * @param RequestInterface $request
     * @param HttpContext|null $context
     *
     * @return resource
     * @throws NetworkException
     * @throws RequestException
     */
    protected function createSocketClient(RequestInterface $request, ?HttpContext $context = null)
    {
        $proxy = Proxy::fromContext($context, $request);
        $secure = match ($request->getUri()->getScheme()) {
            'http' => false,
            'https' => true,
        };
        $host = $request->getUri()->getHost();
        $port = $request->getUri()->getPort() ?? ($secure ? 443 : 80);

        // PHP streams cannot run TLS inside TLS
        if (true === $proxy?->isSecure() && $secure) {
            throw new RequestException(
                'HTTPS requests through an HTTPS proxy are not supported by the stream adapter',
                $request
            );
        }

        $address = match (true) {
            null === $proxy => sprintf('%s://%s:%d', $secure ? 'ssl' : 'tcp', $host, $port),
            default => sprintf('%s://%s', $proxy->isSecure() ? 'ssl' : 'tcp', $proxy->getAuthority()),
        };

        error_clear_last();
        $fp = @stream_socket_client(
            address: $address,
            error_code: $errno,
            error_message: $errstr,
            timeout: $this->connectTimeout,
            context: $this->createContext($context, $proxy),
        );

        if (false === $fp) {
            throw new NetworkException(
                sprintf(
                    'Unable to connect to %s : %s (%d)',
                    $address,
                    $errstr ?: (error_get_last()['message'] ?? 'unknown error'),
                    $errno
                ),
                $request
            );
        }

        stream_set_blocking($fp, true);
        if ($this->timeout !== null) {
            stream_set_timeout($fp, $this->timeout);
        }

        if (null !== $proxy && $secure) {
            try {
                $this->openTunnel($fp, $request, $proxy, $context);
            } catch (Throwable $exception) {
                fclose($fp);
                throw $exception;
            }
        }

        return $fp;
    }

    /**
     * Open a tunnel to the origin through the proxy, and enable origin TLS.
     *
     * @param resource $fp
     * @param RequestInterface $request
     * @param Proxy $proxy
     * @param HttpContext|null $context
     *
     * @throws NetworkException
     */
    protected function openTunnel($fp, RequestInterface $request, Proxy $proxy, ?HttpContext $context): void
    {
        $target = sprintf('%s:%d', $request->getUri()->getHost(), $request->getUri()->getPort() ?? 443);

        $connect = sprintf("CONNECT %s HTTP/1.1\r\nHost: %s\r\n", $target, $target);
        if (null !== ($authorization = $proxy->getAuthorization())) {
            $connect .= sprintf("Proxy-Authorization: %s\r\n", $authorization);
        }
        $connect .= "\r\n";

        if (false === fwrite($fp, $connect)) {
            throw new NetworkException('Unable to write proxy CONNECT request', $request);
        }

        $headers = $this->readHeaders($fp, $request);
        if (1 !== preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $headers, $matches)) {
            throw new NetworkException(sprintf('Invalid proxy response to CONNECT %s', $target), $request);
        }
        if (($status = (int)$matches[1]) < 200 || $status >= 300) {
            throw new NetworkException(
                sprintf('Proxy refused CONNECT %s with status %d', $target, $status),
                $request
            );
        }

        // Bytes received before the TLS handshake would be read as if they came from the authenticated origin
        if (stream_get_meta_data($fp)['unread_bytes'] > 0) {
            throw new NetworkException(
                sprintf('Unexpected data from proxy after CONNECT %s response', $target),
                $request
            );
        }

        $sslOptions = null !== $context ? $this->getSslOptions($context) : [];
        $sslOptions['peer_name'] = trim($request->getUri()->getHost(), '[]');
        foreach ($sslOptions as $name => $value) {
            stream_context_set_option($fp, 'ssl', $name, $value);
        }

        error_clear_last();
        if (true !== @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new NetworkException(
                sprintf(
                    'Unable to establish TLS connection with %s through proxy: %s',
                    $target,
                    error_get_last()['message'] ?? 'unknown error'
                ),
                $request
            );
        }
    }

    /**
     * Write request.
     *
     * @param $fp
     * @param RequestInterface $request
     * @param Proxy|null $proxy Proxy to which the request is sent in absolute-form
     *
     * @throws NetworkException
     */
    protected function writeRequest($fp, RequestInterface $request, ?Proxy $proxy = null): void
    {
        $uri = $request->getUri();
        $target = ($uri->getPath() ?: '/') . (!empty($uri->getQuery()) ? '?' . $uri->getQuery() : '');
        $headersLines = $this->getHeadersLines($request);

        if (null !== $proxy) {
            $target = sprintf(
                '%s://%s%s%s',
                $uri->getScheme(),
                $uri->getHost(),
                null !== $uri->getPort() ? ':' . $uri->getPort() : '',
                $target,
            );

            if (null !== ($authorization = $proxy->getAuthorization())) {
                $headersLines[] = sprintf('Proxy-Authorization: %s', $authorization);
            }
        }

        // Write headers
        fwrite(
            $fp,
            sprintf(
                "%s %s HTTP/%s\r\n",
                $request->getMethod(),
                $target,
                $request->getProtocolVersion()
            )
        ) ?: $this->throwIfTimedOut($fp, new NetworkException('Unable to write request headers', $request));

        // Headers
        foreach ($headersLines as $headerLine) {
            fwrite($fp, $headerLine . "\r\n") ?:
                $this->throwIfTimedOut($fp, new NetworkException('Unable to write request headers', $request));
        }

        // Separator for body
        fwrite($fp, "\r\n") ?:
            $this->throwIfTimedOut($fp, new NetworkException('Unable to write request separator', $request));

        // Write body per packets 8K by 8K
        $stream = $request->getBody();
        if ($stream->isReadable()) {
            $stream->seek(0);

            while (!$stream->eof()) {
                $result = fwrite($fp, $stream->read(8192));

                if (false === $result) {
                    throw new NetworkException('Unable to write request body', $request);
                }
            }
        }
    }

    /**
     * Read response.
     *
     * @param $fp
     * @param string $method
     * @param float|null $headersTime
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     * @throws NetworkException
     */
    private function readResponse(
        $fp,
        string $method,
        ?float &$headersTime,
        RequestInterface $request
    ): ResponseInterface {
        // Headers
        $protocolVersion = $statusCode = $reasonPhrase = null;
        $headers = $this->parseHeaders(
            $this->readHeaders($fp, $request),
            $protocolVersion,
            $statusCode,
            $reasonPhrase
        );

        $headersTime = microtime(true);

        $response = new Response(
            statusCode: $statusCode ?? 0,
            headers: $headers,
            reasonPhrase: $reasonPhrase
        );
        $response = $response->withProtocolVersion($protocolVersion);

        if (strtoupper($method) == Request::HTTP_METHOD_HEAD) {
            return $response;
        }

        $encodingHeader = array_merge(
            $response->getHeader('Content-Encoding'),
            $response->getHeader('Transfer-Encoding')
        );
        $encodingHeader = implode(', ', $encodingHeader);
        $encodingHeader = explode(',', $encodingHeader);
        $encodingHeader = array_map(trim(...), $encodingHeader);

        // Content length defined
        if (count($contentLength = $response->getHeader('Content-Length')) > 0) {
            // Empty content
            if (0 == ($contentLength = $contentLength[0])) {
                return $response;
            }

            $read = 0;
            $buf = '';
            while ($read < $contentLength) {
                $chunk = fread($fp, $contentLength - $read);

                if ($chunk === false || $chunk === '') {
                    $this->throwIfTimedOut($fp, new NetworkException('Read failed (fixed length)', $request));
                    if (feof($fp)) {
                        break;
                    }
                    continue;
                }
                $buf .= $chunk;
                $read += strlen($chunk);
            }

            return $response->withBody(
                $this->createStream($buf, $encodingHeader)
            );
        }

        $content = '';

        // Chunked
        if (true === in_array('chunked', $encodingHeader)) {
            while (false !== ($hex = fgets($fp))) {
                if ($hex === '') {
                    $this->throwIfTimedOut($fp, new NetworkException('Read timed out (chunk size)', $request));
                }
                $length = (int)hexdec($hex);
                if (0 === $length) {
                    continue;
                }

                $part = '';
                $remain = $length;
                while ($remain > 0) {
                    $chunk = fread($fp, $remain);
                    if ($chunk === false || $chunk === '') {
                        $this->throwIfTimedOut($fp, new NetworkException('Read timed out (chunk data)', $request));
                        if (feof($fp)) {
                            break 2;
                        }
                        continue;
                    }
                    $part .= $chunk;
                    $remain -= strlen($chunk);
                }

                $content .= $part;
            }

            return $response->withBody(
                $this->createStream(
                    $content,
                    $encodingHeader
                )
            );
        }

        while (false === feof($fp)) {
            $chunk = fread($fp, 1024);
            if ($chunk === false || $chunk === '') {
                $this->throwIfTimedOut($fp, new NetworkException('Read timed out (body)', $request));
                continue;
            }

            $content .= $chunk;
        }

        return $response->withBody(
            $this->createStream(
                $content,
                $encodingHeader
            )
        );
    }

    /**
     * Read headers.
     *
     * @param resource $fp
     * @param RequestInterface $request
     *
     * @return string
     * @throws NetworkException
     */
    protected function readHeaders($fp, RequestInterface $request): string
    {
        $headers = '';

        while (false !== ($buffer = fgets($fp))) {
            if ($buffer === '') {
                $this->throwIfTimedOut($fp, new NetworkException('Read timed out (headers)', $request));
            }
            $headers .= $buffer;

            // First new line separator of headers and content
            if ($buffer == "\r\n") {
                return $headers;
            }
        }

        $this->throwIfTimedOut($fp, new NetworkException('Failed to read headers', $request));

        return $headers;
    }

    /**
     * Throw if timeout.
     *
     * @param resource $fp
     * @param NetworkException $throwable
     *
     * @return void
     * @throws NetworkException
     */
    private function throwIfTimedOut($fp, NetworkException $throwable): void
    {
        $meta = stream_get_meta_data($fp);
        if (!empty($meta['timed_out'])) {
            throw $throwable;
        }
    }
}
