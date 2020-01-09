<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client;

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\HttpException;
use Berlioz\Http\Client\Exception\NetworkException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Message\Message;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Stream;
use Berlioz\Http\Message\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Serializable;

use function mb_convert_case;

// Constants
defined('CURL_HTTP_VERSION_2_0') || define('CURL_HTTP_VERSION_2_0', 3);

class Client implements ClientInterface, LoggerAwareInterface, Serializable
{
    use LoggerAwareTrait;
    /** @var array Options */
    private $options;
    /** @var array CURL options */
    private $curlOptions;
    /** @var array Default headers */
    private $defaultHeaders;
    /** @var \Psr\Http\Message\MessageInterface[][] History */
    private $history;
    /** @var \Berlioz\Http\Client\Cookies\CookiesManager CookiesManager */
    private $cookies;
    /** @var resource|false File log pointer */
    private $fp;

    /**
     * Client constructor.
     *
     * @param array $options
     *
     * @option int    "followLocationLimit" Limit location to follow
     * @option string "logFile"             Log file name (only file name, not path)
     */
    public function __construct($options = [])
    {
        // Default options
        $this->options = [
            'followLocationLimit' => 5,
            'logFile' => null,
            'exceptions' => true,
        ];
        $this->options = array_replace($this->options, $options);

        // Init CURL options
        $this->curlOptions = [];

        // Default headers
        $this->defaultHeaders = [
            'Accept' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
            'User-Agent' => ['BerliozBot/1.0'],
            'Accept-Language' => ['fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3'],
            'Accept-Encoding' => ['gzip, deflate'],
            'Accept-Charset' => ['ISO-8859-1,utf-8;q=0.7,*;q=0.7'],
            'Connection' => ['close'],
        ];

        // Init history
        $this->history = [];

        // Init cookies
        $this->cookies = new CookiesManager;
    }

    /**
     * Client destructor.
     *
     * @throws \Berlioz\Http\Client\Exception\HttpClientException if unable to close log file pointer
     */
    public function __destruct()
    {
        $this->closeLogResource();
    }

    /**
     * @inheritdoc
     */
    public function serialize(): string
    {
        // Make history
        $bodies = [];
        foreach ($this->history as $iEntry => $entry) {
            foreach ($entry as $type => $message) {
                if ($message instanceof Message) {
                    $bodies[$iEntry][$type] = $message->getBody()->getContents();
                }
            }
        }

        return serialize(
            [
                'options' => $this->options,
                'curlOptions' => $this->curlOptions,
                'defaultHeaders' => $this->defaultHeaders,
                'history' => $this->history,
                'cookies' => $this->cookies,
                'bodies' => $bodies,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        $tmpUnserialized = unserialize($serialized);

        $this->options = $tmpUnserialized['options'];
        $this->curlOptions = $tmpUnserialized['curlOptions'];
        $this->defaultHeaders = $tmpUnserialized['defaultHeaders'];
        $this->history = $tmpUnserialized['history'];
        $this->cookies = $tmpUnserialized['cookies'];

        // Construct history
        foreach ($tmpUnserialized['bodies'] as $iEntry => $entry) {
            foreach ($entry as $type => $body) {
                $stream = new Stream();
                $stream->write($body);

                $this->history[$iEntry][$type] = $this->history[$iEntry][$type]->withBody($stream);
            }
        }
    }

    /**
     * Get CURL options.
     *
     * @return array
     */
    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }

    /**
     * Set CURL options.
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
     *
     * @param array $curlOptions
     * @param bool $erase Erase all existent options (default: false)
     *
     * @return static
     */
    public function setCurlOptions(array $curlOptions, bool $erase = false): Client
    {
        if (!$erase) {
            $curlOptions = array_replace($this->curlOptions, $curlOptions);
        }

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
        ];
        if (defined('CURLOPT_FOLLOWLOCATION')) {
            $reservedOptions[] = CURLOPT_FOLLOWLOCATION;
        }

        $this->curlOptions = array_diff($curlOptions, $reservedOptions);

        return $this;
    }

    /**
     * Get default headers.
     *
     * @return array
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Set default headers.
     *
     * @param array $headers
     * @param bool $erase Erase if exists (default: true)
     *
     * @return static
     */
    public function setDefaultHeaders(array $headers, bool $erase = true): Client
    {
        if ($erase) {
            $this->defaultHeaders = $headers;

            return $this;
        }

        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $this;
    }

    /**
     * Get default header.
     *
     * @param string $name
     *
     * @return array
     */
    public function getDefaultHeader(string $name): array
    {
        return $this->defaultHeaders[$name] ?? [];
    }

    /**
     * Set default header.
     *
     * @param string $name Name
     * @param string $value Value
     * @param bool $erase Erase if exists (default: true)
     *
     * @return static
     */
    public function setDefaultHeader(string $name, string $value, bool $erase = true): Client
    {
        if ($erase || !isset($this->defaultHeaders[$name])) {
            unset($this->defaultHeaders[$name]);
        }

        $this->defaultHeaders[$name] = array_merge((array)($this->defaultHeaders[$name] ?? []), (array)$value);

        return $this;
    }

    /**
     * Log request and response.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @throws \Berlioz\Http\Client\Exception\HttpClientException if unable to write logs
     */
    protected function log(RequestInterface $request, ResponseInterface $response = null)
    {
        $this->history[] = [
            'request' => $request,
            'response' => $response,
        ];

        // Logger
        if (!empty($this->logger)) {
            $logLevel = 'info';
            if (!$response || intval(substr((string)$response->getStatusCode(), 0, 1)) != 2) {
                $logLevel = 'warning';
            }

            $this->logger->log(
                $logLevel,
                sprintf(
                    '%s / Request %s to %s, response %s',
                    __METHOD__,
                    $request->getMethod(),
                    $request->getUri(),
                    $response ?
                        sprintf(
                            '%d (%s)',
                            $response->getStatusCode(),
                            $response->getReasonPhrase()
                        ) :
                        'NONE'
                )
            );
        }

        // Log all detail to file ?
        if (!empty($this->options['logFile'])) {
            if (is_resource($this->fp) || is_resource($this->fp = @fopen($this->options['logFile'], 'a'))) {
                $str = '###### ' . date('c') . ' ######' . PHP_EOL . PHP_EOL .
                    '>>>>>> Request' . PHP_EOL . PHP_EOL;

                // Request
                {
                    // Main header
                    $str .= sprintf(
                        '%s %s HTTP/%s' . PHP_EOL,
                        $request->getMethod(),
                        $request->getUri()->getPath() . (!empty(
                        $request->getUri()->getQuery()
                        ) ? '?' . $request->getUri()->getQuery() : ''),
                        $request->getProtocolVersion()
                    );

                    // Host
                    $str .= sprintf('Host: %s', $request->getUri()->getHost());
                    if ($request->getUri()->getPort()) {
                        $str .= sprintf(':%d', $request->getUri()->getPort());
                    }
                    $str .= PHP_EOL;

                    // Headers
                    foreach ($request->getHeaders() as $key => $values) {
                        foreach ($values as $value) {
                            $str .= sprintf('%s: %s' . PHP_EOL, $key, $value);
                        }
                    }

                    // Body
                    $str .= PHP_EOL .
                        ($request->getBody()->getSize() > 0 ? $request->getBody() : 'Empty body') .
                        PHP_EOL .
                        PHP_EOL;
                }

                $str .= '<<<<<< Response' . PHP_EOL . PHP_EOL;

                // Response
                if (!is_null($response)) {
                    // Main header
                    $str .= sprintf(
                        'HTTP/%s %s %s' . PHP_EOL,
                        $response->getProtocolVersion(),
                        $response->getStatusCode(),
                        $response->getReasonPhrase()
                    );

                    // Headers
                    foreach ($response->getHeaders() as $key => $values) {
                        foreach ($values as $value) {
                            $str .= sprintf('%s: %s' . PHP_EOL, $key, $value);
                        }
                    }

                    // Body
                    $str .= PHP_EOL .
                        ($response->getBody()->getSize() > 0 ? $response->getBody() : 'Empty body') .
                        PHP_EOL .
                        PHP_EOL;
                } else {
                    $str .= 'No response' .
                        PHP_EOL .
                        PHP_EOL;
                }

                $str .= PHP_EOL . PHP_EOL;

                // Write into logs
                if (fwrite($this->fp, $str) === false) {
                    throw new HttpClientException('Unable to write logs');
                }
            }
        }
    }

    /**
     * Close log resource.
     *
     * @return static
     * @throws \Berlioz\Http\Client\Exception\HttpClientException
     */
    public function closeLogResource(): Client
    {
        if (!is_resource($this->fp)) {
            return $this;
        }

        // Close resource
        if (!fclose($this->fp)) {
            throw new HttpClientException('Unable to close log file pointer');
        }

        $this->fp = null;

        return $this;
    }

    /**
     * Get history.
     *
     * @param int|null $index History index (null for all, -1 for last)
     *
     * @return false|\Psr\Http\Message\MessageInterface[]
     */
    public function getHistory(?int $index = null)
    {
        if (null === $index) {
            return $this->history;
        }

        $history = array_slice($this->history, $index, 1);

        return reset($history);
    }

    /**
     * Clear history.
     *
     * @return \Berlioz\Http\Client\Client
     */
    public function clearHistory(): Client
    {
        $this->history = [];

        return $this;
    }

    /**
     * Get cookies manager.
     *
     * @return \Berlioz\Http\Client\Cookies\CookiesManager
     */
    public function getCookies(): CookiesManager
    {
        return $this->cookies;
    }

    /**
     * Parse headers.
     *
     * @param string $headers Raw headers
     * @param mixed $reasonPhrase Reason phrase returned by reference
     *
     * @return array
     */
    protected function parseHeaders(string $headers, &$reasonPhrase = null): array
    {
        $finalHeaders = [];

        // Explode raw headers
        $headers = explode("\r\n", $headers);
        // Get and remove first header line
        $firstHeader = array_shift($headers);
        // Explode headers
        $headers = array_map(
            function ($value) {
                $value = explode(":", $value, 2);
                $value = array_map('trim', $value);
                $value = array_filter($value);

                return $value;
            },
            $headers
        );
        $headers = array_filter($headers);

        foreach ($headers as $header) {
            $header[0] = mb_convert_case($header[0], MB_CASE_TITLE);
            $header[1] = $header[1] ?? null;

            if (!isset($finalHeaders[$header[0]])) {
                $finalHeaders[$header[0]] = [$header[1]];
                continue;
            }

            $finalHeaders[$header[0]][] = $header[1];
        }

        // Treat first header
        $reasonPhrase = null;
        $matches = [];
        if (preg_match("#^HTTP/([0-9.]+) ([0-9]+) (.*)$#i", $firstHeader, $matches) === 1) {
            $reasonPhrase = $matches[3];
        }

        return $finalHeaders;
    }

    /**
     * Init CURL options.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     *
     * @return resource
     */
    protected function initCurl(RequestInterface $request)
    {
        // CURL init
        $ch = curl_init();

        // HTTP Version
        switch ($request->getProtocolVersion()) {
            case 1.0:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
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

        if (defined('CURLOPT_FOLLOWLOCATION')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($request->getBody()->getSize() > 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$request->getBody());
        }

        // Set user options
        curl_setopt_array($ch, $this->getCurlOptions());

        return $ch;
    }

    /**
     * @inheritdoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!function_exists('curl_version')) {
            throw new HttpClientException('CURL module required for HTTP Client');
        }

        $followLocationCounter = 0;
        $originalRequest = $request;

        // Add default headers
        foreach ($this->defaultHeaders as $headerName => $headerValue) {
            if ($request->hasHeader($headerName)) {
                continue;
            }

            $request = $request->withHeader($headerName, $headerValue);
        }

        do {
            // Init CURL
            $request = $this->getCookies()->addCookiesToRequest($request);
            $ch = $this->initCurl($request);

            // Execute CURL request
            $content = curl_exec($ch);

            // CURL errors ?
            switch (curl_errno($ch)) {
                case CURLE_OK:
                    break;
                case CURLE_URL_MALFORMAT:
                case CURLE_URL_MALFORMAT_USER:
                case CURLE_MALFORMAT_USER:
                case CURLE_BAD_PASSWORD_ENTERED:
                    // Log request
                    $this->log($request);

                    throw new RequestException(
                        sprintf('CURL error : %s (%s)', curl_error($ch), $request->getUri()), $request
                    );
                default:
                    // Log request
                    $this->log($request);

                    throw new NetworkException(
                        sprintf('CURL error : %s (%s)', curl_error($ch), $request->getUri()), $request
                    );
            }

            // Response
            {
                // Headers
                $reasonPhrase = null;
                $headers = $this->parseHeaders(
                    (string)substr($content, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)),
                    $reasonPhrase
                );

                // Body
                $stream = new Stream();
                $streamData = substr($content, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
                if (isset($headers['Content-Encoding'])) {
                    // Gzip
                    if (in_array('gzip', $headers['Content-Encoding'])) {
                        $streamData = gzdecode($streamData);
                    }

                    // Deflate
                    if (in_array('deflate', $headers['Content-Encoding'])) {
                        $streamData = gzinflate(trim($streamData));
                    }
                }
                $stream->write((string)$streamData);

                // Construct object
                $response = new Response(
                    $stream,
                    curl_getinfo($ch, CURLINFO_HTTP_CODE),
                    $headers,
                    $reasonPhrase ?? ''
                );

                // Parse response cookies
                $this->getCookies()->addCookiesFromResponse($request->getUri(), $response);
            }

            // Log request & response
            $this->log($request, $response);

            $followLocation = false;
            if (empty($newLocation = $response->getHeader('Location'))) {
                continue;
            }

            // Follow location ?
            $followLocation = ($followLocationCounter++ <= ($this->options['followLocationLimit'] ?? 5));
            if (!$followLocation) {
                throw new RequestException('Too many redirection from host', $originalRequest);
            }

            // Get redirect
            if (empty($redirectUrl = curl_getinfo($ch)['redirect_url'])) {
                $redirectUrl = $newLocation[0];
            }
            $redirectUri = Uri::createFromString($redirectUrl);

            if (empty($redirectUri->getHost())) {
                $url = parse_url((string)$request->getUri());
                $redirectUri = $redirectUri->withHost($url['host']);

                if (!empty($url['scheme'])) {
                    $redirectUri = $redirectUri->withScheme($url['scheme']);
                }
            }

            // Reset request for redirection, but keeps headers
            $request =
                $request
                    ->withMethod(Request::HTTP_METHOD_GET)
                    ->withHeader('Referer', (string)$request->getUri())
                    ->withUri($redirectUri)
                    ->withBody(new Stream);

            // Add cookies to the new request
            $request = $this->getCookies()->addCookiesToRequest($request);
        } while ($followLocation);

        // Exceptions if error?
        if ($this->options['exceptions']) {
            if (!$response || intval(substr((string)$response->getStatusCode(), 0, 1)) != 2) {
                throw new HttpException(
                    sprintf('%d - %s', $response->getStatusCode(), $response->getReasonPhrase()),
                    $originalRequest,
                    $response
                );
            }
        }

        return $response;
    }

    /**
     * Construct request.
     *
     * @param string $method Http method
     * @param string|\Psr\Http\Message\UriInterface $uri Uri
     * @param array $parameters Get parameters
     * @param string|\Psr\Http\Message\StreamInterface $body Body
     * @param array $options Options
     *
     * @option array "headers" Headers of request
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function constructRequest(
        string $method,
        $uri,
        array $parameters = null,
        $body = null,
        array $options = []
    ): RequestInterface {
        // URI
        if (!$uri instanceof UriInterface) {
            $uri = Uri::createFromString($uri);
        }

        // Parameters
        if (!is_null($parameters)) {
            $uri = $uri->withQuery(http_build_query($parameters));
        }

        // Create request
        $request = new Request($method, $uri);

        // Body
        if (null !== $body) {
            $stream = $body;

            if (!($body instanceof StreamInterface)) {
                $stream = new Stream;
                $stream->write($body);
            }

            $request = $request->withBody($stream);
        }

        // Headers
        if (!empty($options['headers'])) {
            $request = $request->withHeaders((array)$options['headers']);
        }

        return $request;
    }

    /**
     * Request.
     *
     * @param string $method Http method
     * @param string|\Psr\Http\Message\UriInterface $uri Uri
     * @param array $parameters Get parameters
     * @param string|\Psr\Http\Message\StreamInterface $body Body
     * @param array $options Options
     *
     * @option array "headers" Headers of request
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function request(
        string $method,
        $uri,
        array $parameters = null,
        $body = null,
        array $options = []
    ): ResponseInterface {
        $request = $this->constructRequest($method, $uri, $parameters, $body, $options);

        return $this->sendRequest($request);
    }

    /**
     * Get request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function get($uri, array $parameters = null, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $parameters, null, $options);
    }

    /**
     * Post request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param string|StreamInterface $body Body of request
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function post($uri, $body = null, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, null, $body, $options);
    }

    /**
     * Patch request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param string|StreamInterface $body Body of request
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function patch($uri, $body = null, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, null, $body, $options);
    }

    /**
     * Put request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param string|StreamInterface $body Body of request
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function put($uri, $body = null, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, null, $body, $options);
    }

    /**
     * Delete request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function delete($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $parameters, null, $options);
    }

    /**
     * Options request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function options($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('OPTIONS', $uri, $parameters, null, $options);
    }

    /**
     * Head request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function head($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, $parameters, null, $options);
    }

    /**
     * Connect request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param string|StreamInterface $body Body of request
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function connect($uri, $body, array $options = []): ResponseInterface
    {
        return $this->request('CONNECT', $uri, null, $body, $options);
    }

    /**
     * Trace request.
     *
     * @param string|\Psr\Http\Message\UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens during processing the request.
     */
    public function trace($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('TRACE', $uri, $parameters, null, $options);
    }
}
