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
use Berlioz\Http\Client\Proxy;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Stream\MemoryStream;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Constants
defined('CURL_HTTP_VERSION_2_0') || define('CURL_HTTP_VERSION_2_0', 3);
defined('CURLOPT_KEYPASSWD') || define('CURLOPT_KEYPASSWD', 10026);

/**
 * Class CurlAdapter.
 */
class CurlAdapter extends AbstractAdapter implements OriginCredentialsAwareInterface
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
     * Warning: you can't specify some CURL options:
     *     - CURLINFO_HEADER_OUT
     *     - CURLOPT_HTTP_VERSION
     *     - CURLOPT_CUSTOMREQUEST
     *     - CURLOPT_FOLLOWLOCATION
     *     - CURLOPT_HEADER
     *     - CURLOPT_HEADERFUNCTION
     *     - CURLOPT_HTTPHEADER
     *     - CURLOPT_NOSIGNAL
     *     - CURLOPT_RETURNTRANSFER
     *     - CURLOPT_POST
     *     - CURLOPT_POSTFIELDS
     *     - CURLOPT_URL
     *     - CURLOPT_WRITEFUNCTION
     * They are reserved for good work of service.
     */
    protected function clearOptions(): void
    {
        // Remove reserved CURL options
        $reservedOptions = [
            CURLINFO_HEADER_OUT,
            CURLOPT_HTTP_VERSION,
            CURLOPT_CUSTOMREQUEST,
            CURLOPT_FOLLOWLOCATION,
            CURLOPT_HEADER,
            CURLOPT_HEADERFUNCTION,
            CURLOPT_HTTPHEADER,
            CURLOPT_NOSIGNAL,
            CURLOPT_RETURNTRANSFER,
            CURLOPT_POST,
            CURLOPT_POSTFIELDS,
            CURLOPT_URL,
            CURLOPT_WRITEFUNCTION,
        ];

        $this->options = array_diff_key($this->options, array_fill_keys($reservedOptions, null));
    }

    /**
     * @inheritDoc
     */
    public function withoutOriginCredentials(): static
    {
        $clone = clone $this;
        $clone->options = array_diff_key($this->options, array_flip($this->getOriginCredentialsOptions()));

        return $clone;
    }

    /**
     * Get CURL options carrying origin credentials, removed by {@see CurlAdapter::withoutOriginCredentials()}.
     *
     * Proxy credentials are not part of them: they are sent to the proxy, not to the origin.
     *
     * @return int[]
     */
    protected function getOriginCredentialsOptions(): array
    {
        $names = [
            'CURLOPT_USERPWD',
            'CURLOPT_USERNAME',
            'CURLOPT_PASSWORD',
            'CURLOPT_LOGIN_OPTIONS',
            'CURLOPT_SASL_AUTHZID',
            'CURLOPT_XOAUTH2_BEARER',
            'CURLOPT_TLSAUTH_USERNAME',
            'CURLOPT_TLSAUTH_PASSWORD',
            'CURLOPT_SSLCERT',
            'CURLOPT_SSLCERT_BLOB',
            'CURLOPT_SSLCERTPASSWD',
            'CURLOPT_SSLKEY',
            'CURLOPT_SSLKEY_BLOB',
            'CURLOPT_SSLKEYPASSWD',
            'CURLOPT_KEYPASSWD',
            'CURLOPT_NETRC',
            'CURLOPT_NETRC_FILE',
            'CURLOPT_COOKIE',
            'CURLOPT_COOKIEFILE',
            'CURLOPT_COOKIELIST',
        ];

        return array_values(array_unique(array_map(
            constant(...),
            array_filter($names, defined(...)),
        )));
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface
    {
        $headersStream = new MemoryStream();
        $bodyStream = new MemoryStream();

        // Init CURL
        $curlOptions = $this->prepareCurlOptions(
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
        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

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
                    - curl_getinfo($ch, CURLINFO_APPCONNECT_TIME_T)) / 1000),
            wait: (float)((curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T)
                    - curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME_T)) / 1000),
            receive: (float)((curl_getinfo($ch, CURLINFO_TOTAL_TIME_T)
                    - curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T)) / 1000),
            total: (float)(curl_getinfo($ch, CURLINFO_TOTAL_TIME_T) / 1000),
            blocked: -1,
            dns: (float)(curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME_T) / 1000),
            connect: (float)((curl_getinfo($ch, CURLINFO_CONNECT_TIME_T)
                    - curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME_T)) / 1000),
            ssl: (float)((curl_getinfo($ch, CURLINFO_APPCONNECT_TIME_T)
                    - curl_getinfo($ch, CURLINFO_CONNECT_TIME_T)) / 1000),
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
     * Prepare CURL options.
     *
     * @param RequestInterface $request
     * @param array $options
     * @param HttpContext|null $context
     *
     * @return array
     */
    protected function prepareCurlOptions(
        RequestInterface $request,
        array $options = [],
        ?HttpContext $context = null,
    ): array {
        $options = array_replace($this->options, $options);

        // HTTP Version
        $options[CURLOPT_HTTP_VERSION] = match ($request->getProtocolVersion()) {
            1.0 => CURL_HTTP_VERSION_1_0,
            2.0 => CURL_HTTP_VERSION_2_0,
            default => CURL_HTTP_VERSION_1_1,
        };

        // URL of request
        $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        $options[CURLOPT_URL] = $request->getUri();

        // Timeouts
        $options[CURLOPT_NOSIGNAL] = true;

        // Headers
        {
            $options[CURLOPT_HEADER] = false;
            $options[CURLINFO_HEADER_OUT] = false;
            $options[CURLOPT_HTTPHEADER] = $this->getHeadersLines($request);
        }

        $options[CURLOPT_FOLLOWLOCATION] = false;

        if ($request->getBody()->getSize() > 0) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = (string)$request->getBody();
        }

        // Proxy, resolved here: libcurl must not read the environment by itself.
        // A raw cURL proxy option takes precedence over the environment, not over a configured or disabled proxy.
        $proxy = null;
        if (null !== $context?->proxy || !array_key_exists(CURLOPT_PROXY, $options)) {
            $options[CURLOPT_PROXY] = '';
            $options[CURLOPT_HTTPPROXYTUNNEL] = false;

            // SOCKS proxy of environment, not supported by Proxy: handed over to libcurl ("no_proxy" already applied)
            $socks = null === $context?->proxy ? Proxy::socksFromEnvironment($request) : null;
            if (null !== $socks) {
                $options[CURLOPT_PROXY] = $socks;
                $options[CURLOPT_NOPROXY] = '';
            } else {
                $proxy = Proxy::resolve($context, $request);
            }
        }
        if (null !== $proxy) {
            if ($proxy->isSecure() && !$this->supportsHttpsProxy()) {
                throw new RequestException('HTTPS proxy is not supported by the installed libcurl', $request);
            }

            $proxyOptions = [
                // Tunnel for HTTPS origins only, HTTP requests are sent to the proxy in absolute-form
                CURLOPT_HTTPPROXYTUNNEL => 'https' === strtolower($request->getUri()->getScheme()),
                CURLOPT_PROXY => $proxy->getUrl(),
                CURLOPT_PROXYTYPE => $proxy->isSecure() ? CURLPROXY_HTTPS : CURLPROXY_HTTP,
                // "no_proxy" already applied
                CURLOPT_NOPROXY => '',
                CURLOPT_PROXYUSERNAME => $proxy->user,
                CURLOPT_PROXYPASSWORD => null !== $proxy->user ? ($proxy->password ?? '') : null,
                CURLOPT_PROXY_CAINFO => $context->proxy_ssl_cafile ?? $context?->ssl_cafile,
                CURLOPT_PROXY_CAPATH => $context->proxy_ssl_capath ?? $context?->ssl_capath,
            ];

            $options = array_replace($options, array_filter($proxyOptions, fn($value) => null !== $value));
        }

        // HTTP Context
        if (null !== $context) {
            $contextOptions = [];

            $contextOptions[CURLOPT_SSL_VERIFYPEER] = $context->ssl_verify_peer;
            $contextOptions[CURLOPT_SSL_VERIFYHOST] = $context->ssl_verify_host ? 2 : 0;
            $contextOptions[CURLOPT_PROXY_SSL_VERIFYPEER] = $context->proxy_ssl_verify_peer;
            $contextOptions[CURLOPT_PROXY_SSL_VERIFYHOST] = $context->proxy_ssl_verify_host ? 2 : 0;
            $contextOptions[CURLOPT_CAINFO] = $context->ssl_cafile;
            $contextOptions[CURLOPT_CAPATH] = $context->ssl_capath;
            $contextOptions[CURLOPT_SSL_CIPHER_LIST] = $context->ssl_ciphers;
            $contextOptions[CURLOPT_SSLCERT] = $context->ssl_local_cert;
            $contextOptions[CURLOPT_SSLCERTPASSWD] = $context->ssl_local_cert_passphrase;
            $contextOptions[CURLOPT_SSLKEY] = $context->ssl_local_pk;
            $contextOptions[CURLOPT_KEYPASSWD] = $context->ssl_local_passphrase;

            $options = array_replace($options, array_filter($contextOptions, fn($value) => null !== $value));
        }

        return $options;
    }

    /**
     * Is HTTPS proxy supported by libcurl?
     *
     * @return bool
     */
    protected function supportsHttpsProxy(): bool
    {
        return (bool)(curl_version()['features'] & CURL_VERSION_HTTPS_PROXY);
    }
}
