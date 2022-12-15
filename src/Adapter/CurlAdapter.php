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
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\NetworkException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Stream\MemoryStream;
use Berlioz\Http\Message\Uri;
use CurlHandle;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Constants
defined('CURL_HTTP_VERSION_2_0') || define('CURL_HTTP_VERSION_2_0', 3);

/**
 * Class CurlAdapter.
 */
class CurlAdapter extends AbstractAdapter
{
    use HeaderParserTrait;

    /**
     * CurlAdapter constructor.
     *
     * @param array $options
     *
     * @throws HttpClientException
     */
    public function __construct(protected array $options = [])
    {
        if (!extension_loaded('curl')) {
            throw new HttpClientException('CURL module required for HTTP Client');
        }

        $this->clearOptions();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'curl';
    }

    /**
     * Clear CURL options.
     *
     * Warning: you can't specify some CURL options :
     *     - CURLOPT_HTTP_VERSION
     *     - CURLOPT_CUSTOMREQUEST
     *     - CURLOPT_URL
     *     - CURLOPT_HEADER
     *     - CURLINFO_HEADER_OUT
     *     - CURLOPT_HTTPHEADER
     *     - CURLOPT_FOLLOWLOCATION
     *     - CURLOPT_RETURNTRANSFER
     *     - CURLOPT_POST
     *     - CURLOPT_POSTFIELDS
     * They are reserved for good work of service.
     */
    protected function clearOptions(): void
    {
        // Remove reserved CURL options
        $reservedOptions = [
            CURLOPT_HTTP_VERSION,
            CURLOPT_CUSTOMREQUEST,
            CURLOPT_URL,
            CURLOPT_HEADER,
            CURLOPT_HEADERFUNCTION,
            CURLINFO_HEADER_OUT,
            CURLOPT_HTTPHEADER,
            CURLOPT_RETURNTRANSFER,
            CURLOPT_POST,
            CURLOPT_POSTFIELDS,
            CURLOPT_FOLLOWLOCATION,
            CURLOPT_WRITEFUNCTION,
        ];

        $this->options = array_diff($this->options, $reservedOptions);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request, HttpContext $context = null): ResponseInterface
    {
        $headersStream = new MemoryStream();
        $bodyStream = new MemoryStream();

        // Init CURL
        $ch = $this->initCurl(
            $request,
            [
                CURLOPT_HEADERFUNCTION => fn($ch, $data) => $headersStream->write($data),
                CURLOPT_WRITEFUNCTION => match (strtoupper($request->getMethod())) {
                    Request::HTTP_METHOD_HEAD => null,
                    default => fn($ch, $data) => $bodyStream->write($data),
                },
                CURLOPT_NOBODY => $request->getMethod() == Request::HTTP_METHOD_HEAD,
            ],
            $context,
        );

        // Execute CURL request
        $dateTime = new DateTimeImmutable();
        curl_exec($ch);

        // CURL error?
        switch (curl_errno($ch)) {
            case CURLE_OK:
                break;
            case CURLE_URL_MALFORMAT:
            case CURLE_URL_MALFORMAT_USER:
            case CURLE_MALFORMAT_USER:
            case CURLE_BAD_PASSWORD_ENTERED:
                throw new RequestException(
                    sprintf(
                        'CURL error: %s (%s)',
                        curl_error($ch),
                        $request->getUri()
                    ),
                    $request
                );
            default:
                throw new NetworkException(
                    sprintf(
                        'CURL error: %s (%s)',
                        curl_error($ch),
                        $request->getUri()
                    ),
                    $request
                );
        }

        // Timings
        $this->timings = new Timings(
            dateTime: $dateTime,
            send: (float)((curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME_T)
                    - curl_getinfo($ch, CURLINFO_APPCONNECT_TIME_T)) / 10),
            wait: (float)((curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T)
                    - curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME_T)) / 10),
            receive: (float)((curl_getinfo($ch, CURLINFO_TOTAL_TIME_T)
                    - curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T)) / 10),
            total: (float)(curl_getinfo($ch, CURLINFO_TOTAL_TIME_T) / 10),
            blocked: -1,
            dns: (float)(curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME_T) / 10),
            connect: (float)((curl_getinfo($ch, CURLINFO_CONNECT_TIME_T)
                    - curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME_T)) / 10),
            ssl: (float)((curl_getinfo($ch, CURLINFO_APPCONNECT_TIME_T)
                    - curl_getinfo($ch, CURLINFO_CONNECT_TIME_T)) / 10),
        );

        // Response
        $protocolVersion = $reasonPhrase = null;
        $bodyStream->seek(0);
        $headers = $this->parseHeaders(
            $headersStream->getContents(),
            protocolVersion: $protocolVersion,
            reasonPhrase: $reasonPhrase
        );

        // Replace location header with redirect_url parameter
        if (!empty($redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL))) {
            $headers['Location'] = [$redirectUrl];
        }

        // Create response
        $response = new Response(
            $this->createStream($bodyStream, $headers['Content-Encoding'] ?? null),
            curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            $headers,
            $reasonPhrase ?? ''
        );

        return $response->withProtocolVersion($protocolVersion);
    }

    /**
     * Init CURL options.
     *
     * @param RequestInterface $request
     * @param HttpContext|null $context
     *
     * @return CurlHandle
     */
    protected function initCurl(
        RequestInterface $request,
        array $options = [],
        ?HttpContext $context = null,
    ): CurlHandle {
        // CURL init
        $ch = curl_init();
        curl_setopt_array($ch, $options);

        // HTTP Version
        switch ($request->getProtocolVersion()) {
            case 1.0:
                $curlOpts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
                break;
            case 2.0:
                $curlOpts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
                break;
            case 1.1:
            default:
                $curlOpts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }

        // URL of request
        $curlOpts[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        $curlOpts[CURLOPT_URL] = $request->getUri();

        // Headers
        {
            $curlOpts[CURLOPT_HEADER] = false;
            $curlOpts[CURLINFO_HEADER_OUT] = false;
            $curlOpts[CURLOPT_HTTPHEADER] = $this->getHeadersLines($request);
        }

        $curlOpts[CURLOPT_FOLLOWLOCATION] = false;

        if ($request->getBody()->getSize() > 0) {
            $curlOpts[CURLOPT_POST] = true;
            $curlOpts[CURLOPT_POSTFIELDS] = (string)$request->getBody();
        }

        // HTTP Context
        if (null !== $context) {
            $contextOptions = [];

            $contextOptions[CURLOPT_HTTPPROXYTUNNEL] = $context->proxy !== false;
            if (false !== $context->proxy) {
                $proxyUri = Uri::createFromString($context->proxy);
                $contextOptions[CURLOPT_PROXY] = sprintf(
                    '%s:%d',
                    $proxyUri->getHost(),
                    $proxyUri->getPort() ?? ($proxyUri->getScheme() == 'http' ? 80 : 443)
                );
                $contextOptions[CURLOPT_PROXYUSERPWD] = $proxyUri->getUserInfo() ?: null;
            }

            $contextOptions[CURLOPT_SSL_VERIFYPEER] = $context->ssl_verify_peer;
            $contextOptions[CURLOPT_SSL_VERIFYHOST] = $context->ssl_verify_host ? 2 : 0;
            $contextOptions[CURLOPT_PROXY_SSL_VERIFYPEER] = $context->ssl_verify_peer;
            $contextOptions[CURLOPT_PROXY_SSL_VERIFYHOST] = $context->ssl_verify_host ? 2 : 0;
            $contextOptions[CURLOPT_CAINFO] = $context->ssl_cafile;
            $contextOptions[CURLOPT_CAPATH] = $context->ssl_capath;
            $contextOptions[CURLOPT_SSL_CIPHER_LIST] = $context->ssl_ciphers;
            $contextOptions[CURLOPT_SSLCERT] = $context->ssl_local_cert;
            $contextOptions[CURLOPT_SSLCERTPASSWD] = $context->ssl_local_cert_passphrase;
            $contextOptions[CURLOPT_SSLKEY] = $context->ssl_local_pk;

            $curlOpts = array_replace($curlOpts, array_filter($contextOptions, fn($value) => null !== $value));
        }

        curl_setopt_array($ch, $curlOpts);

        return $ch;
    }
}
