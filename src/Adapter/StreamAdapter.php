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
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class StreamAdapter.
 */
class StreamAdapter extends AbstractAdapter
{
    use HeaderParserTrait;

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'stream';
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface
    {
        $dateTime = new DateTimeImmutable();
        $initTime = microtime(true);

        // Create socket
        $fp = $this->createSocketClient($request, $context);

        $connectTime = microtime(true) - $initTime;

        // Write request
        $this->writeRequest($fp, $request);

        $requestTime = microtime(true) - $connectTime;

        // Read response
        $response = $this->readResponse($fp, $headersTime);

        $waitTime = $headersTime - $requestTime;
        $totalTime = microtime(true);

        // Close socket
        fclose($fp);

        $this->timings = new Timings(
            dateTime: $dateTime,
            send: $requestTime / 10,
            wait: $waitTime / 10,
            receive: ($waitTime - $totalTime) / 10,
            total: $totalTime
        );

        return $response;
    }

    /**
     * Create stream context.
     *
     * @param HttpContext|null $context
     *
     * @return resource
     */
    protected function createContext(?HttpContext $context = null)
    {
        $contextOptions = [];
        $contextOptions['http']['follow_location'] = false;

        // HTTP Context
        if (null !== $context) {
            $contextOptions['http']['proxy'] = null;

            if (false !== $context->proxy) {
                $proxyUri = Uri::createFromString($context->proxy);

                $contextOptions['http']['proxy'] = sprintf(
                    '%s:%d',
                    $proxyUri->getHost(),
                    $proxyUri->getPort() ?? ($proxyUri->getScheme() == 'http' ? 80 : 443)
                );
                $contextOptions['http']['request_fulluri'] = true;

                if ($proxyUri->getUserInfo()) {
                    $contextOptions['http']['header'] = sprintf(
                        'Authorization: Basic %s',
                        base64_encode($proxyUri->getUserInfo())
                    );
                }
            }

            $contextOptions['ssl']['verify_peer'] = $context->ssl_verify_peer;
            $contextOptions['ssl']['verify_peer_name'] = $context->ssl_verify_host;
            $contextOptions['ssl']['allow_self_signed'] = !$context->ssl_verify_peer;
            $contextOptions['ssl']['cafile'] = $context->ssl_cafile;
            $contextOptions['ssl']['capath'] = $context->ssl_capath;
            $contextOptions['ssl']['local_cert'] = $context->ssl_local_cert;
            $contextOptions['ssl']['local_cert_passphrase'] = $context->ssl_local_cert_passphrase;
            $contextOptions['ssl']['local_pk'] = $context->ssl_local_pk;
            $contextOptions['ssl']['ciphers'] = $context->ssl_ciphers;
            $contextOptions['ssl'] = array_filter($contextOptions['ssl'], fn($value) => null !== $value);
        }

        return stream_context_create($contextOptions);
    }

    /**
     * Create socket client.
     *
     * @param RequestInterface $request
     * @param HttpContext|null $context
     *
     * @return resource
     * @throws NetworkException
     */
    protected function createSocketClient(RequestInterface $request, ?HttpContext $context = null)
    {
        $wrapper = match ($request->getUri()->getScheme()) {
            'http' => 'tcp',
            'https' => 'ssl',
        };
        $port = $request->getUri()->getPort() ?? match ($request->getUri()->getScheme()) {
                'http' => 80,
                'https' => 443,
            };

        $fp = stream_socket_client(
            address: $address = sprintf('%s://%s:%d', $wrapper, $request->getUri()->getHost(), $port),
            error_code: $errno,
            error_message: $errstr,
            context: $this->createContext($context),
        );

        if (false === $fp) {
            throw new NetworkException(
                sprintf('Unable to connect to %s : %s (%d)', $address, $errstr, $errno),
                $request
            );
        }

        return $fp;
    }

    /**
     * Write request.
     *
     * @param $fp
     * @param RequestInterface $request
     *
     * @throws NetworkException
     */
    protected function writeRequest($fp, RequestInterface $request): void
    {
        // Write headers
        fwrite(
            $fp,
            sprintf(
                "%s %s HTTP/%s\r\n",
                $request->getMethod(),
                $request->getUri()->getPath() .
                (!empty($request->getUri()->getQuery()) ? '?' . $request->getUri()->getQuery() : ''),
                $request->getProtocolVersion()
            )
        ) ?: throw new NetworkException('Unable to write request headers', $request);

        // Headers
        foreach ($this->getHeadersLines($request) as $headerLine) {
            fwrite($fp, $headerLine . "\r\n") ?:
                throw new NetworkException('Unable to write request headers', $request);
        }

        // Separator for body
        fwrite($fp, "\r\n") ?? throw new NetworkException('Unable to write request separator', $request);

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
     * @param float|null $headersTime
     *
     * @return ResponseInterface
     */
    private function readResponse($fp, ?float &$headersTime): ResponseInterface
    {
        // Headers
        $protocolVersion = $statusCode = $reasonPhrase = null;
        $headers = $this->parseHeaders(
            $this->readHeaders($fp),
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

        $encodingHeader = array_merge(
            $response->getHeader('Content-Encoding'),
            $response->getHeader('Transfer-Encoding')
        );
        $encodingHeader = implode(', ', $encodingHeader);
        $encodingHeader = explode(',', $encodingHeader);
        $encodingHeader = array_map('trim', $encodingHeader);

        // Content length defined
        if (count($contentLength = $response->getHeader('Content-Length')) > 0) {
            // Empty content
            if (0 == ($contentLength = $contentLength[0])) {
                return $response;
            }

            return $response->withBody(
                $this->createStream(
                    fread($fp, (int)$contentLength),
                    $encodingHeader
                )
            );
        }

        $content = '';

        // Chunked
        if (true === in_array('chunked', $encodingHeader)) {
            while (false !== ($hex = fgets($fp))) {
                $length = (int)hexdec($hex);

                if (0 === $length) {
                    continue;
                }

                $content .= fread($fp, $length);
            }

            return $response->withBody(
                $this->createStream(
                    $content,
                    $encodingHeader
                )
            );
        }

        // Get all content
        while (false === feof($fp)) {
            $content .= fread($fp, 1024);
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
     *
     * @return string
     */
    protected function readHeaders($fp): string
    {
        $headers = '';

        while (false !== ($buffer = fgets($fp))) {
            $headers .= $buffer;

            // First new line separator of headers and content
            if ($buffer == "\r\n") {
                return $headers;
            }
        }

        return $headers;
    }
}