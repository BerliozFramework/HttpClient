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
use Berlioz\Http\Message\Response;
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
        if (!function_exists('curl_version')) {
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
            CURLINFO_HEADER_OUT,
            CURLOPT_HTTPHEADER,
            CURLOPT_RETURNTRANSFER,
            CURLOPT_POST,
            CURLOPT_POSTFIELDS,
            CURLOPT_FOLLOWLOCATION,
        ];

        $this->options = array_diff($this->options, $reservedOptions);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // Init CURL
        $ch = $this->initCurl($request);

        // Execute CURL request
        $dateTime = new DateTimeImmutable();
        $content = curl_exec($ch);

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
        $headersSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = $this->parseHeaders(
            mb_substr($content, 0, $headersSize),
            protocolVersion: $protocolVersion,
            reasonPhrase: $reasonPhrase
        );

        // Replace location header with redirect_url parameter
        if (!empty($redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL))) {
            $headers['Location'] = [$redirectUrl];
        }

        $response = new Response(
            $this->createStream(mb_substr($content, $headersSize), $headers['Content-Encoding'] ?? []),
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
     *
     * @return CurlHandle
     */
    protected function initCurl(RequestInterface $request): CurlHandle
    {
        // CURL init
        $ch = curl_init();

        // HTTP Version
        switch ($request->getProtocolVersion()) {
            case 1.0:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
            case 2.0:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
                break;
            case 1.1:
            default:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }

        // URL of request
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($ch, CURLOPT_URL, $request->getUri());

        // Headers
        {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            $headers = [];
            foreach ($request->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $headers[] = sprintf('%s: %s', $name, $value);
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($request->getBody()->getSize() > 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$request->getBody());
        }

        // Set user options
        curl_setopt_array($ch, $this->options);

        return $ch;
    }
}