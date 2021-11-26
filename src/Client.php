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

use Berlioz\Http\Client\Components;
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
use Psr\Log\LoggerAwareInterface;
use Serializable;

// Constants
defined('CURL_HTTP_VERSION_2_0') || define('CURL_HTTP_VERSION_2_0', 3);

class Client implements ClientInterface, LoggerAwareInterface, Serializable
{
    use Components\DefaultHeadersTrait;
    use Components\HeaderParserTrait;
    use Components\HistoryTrait;
    use Components\LogTrait;
    use Components\RequestFactoryTrait;

    /** @var array Options */
    private $options;
    /** @var array CURL options */
    private $curlOptions;
    /** @var CookiesManager CookiesManager */
    private $cookies;

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

        // Init cookies
        $this->cookies = new CookiesManager();
    }

    /**
     * Client destructor.
     *
     * @throws HttpClientException if unable to close log file pointer
     */
    public function __destruct()
    {
        $this->closeLogResource();
    }

    public function __serialize(): array
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

        return [
            'options' => $this->options,
            'curlOptions' => $this->curlOptions,
            'defaultHeaders' => $this->defaultHeaders,
            'history' => $this->history,
            'cookies' => $this->cookies,
            'bodies' => $bodies,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->options = $data['options'];
        $this->curlOptions = $data['curlOptions'];
        $this->defaultHeaders = $data['defaultHeaders'];
        $this->history = $data['history'];
        $this->cookies = $data['cookies'];

        // Construct history
        foreach ($data['bodies'] as $iEntry => $entry) {
            foreach ($entry as $type => $body) {
                $stream = new Stream();
                $stream->write($body);

                $this->history[$iEntry][$type] = $this->history[$iEntry][$type]->withBody($stream);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        $this->__unserialize(unserialize($serialized));
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
     * Get cookies manager.
     *
     * @return CookiesManager
     */
    public function getCookies(): CookiesManager
    {
        return $this->cookies;
    }

    /**
     * Init CURL options.
     *
     * @param RequestInterface $request
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
            try {
                switch (curl_errno($ch)) {
                    case CURLE_OK:
                        break;
                    case CURLE_URL_MALFORMAT:
                    case CURLE_URL_MALFORMAT_USER:
                    case CURLE_MALFORMAT_USER:
                    case CURLE_BAD_PASSWORD_ENTERED:
                        throw new RequestException(
                            sprintf('CURL error : %s (%s)', curl_error($ch), $request->getUri()), $request
                        );
                    default:
                        throw new NetworkException(
                            sprintf('CURL error : %s (%s)', curl_error($ch), $request->getUri()), $request
                        );
                }
            } catch (HttpException $e) {
                $this->addHistory($request, null);
                $this->log($request);

                throw $e;
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
            $this->addHistory($request, $response);
            $this->log($request, $response);

            $followLocation = false;
            if (!in_array(
                $response->getStatusCode(),
                [
                    Response::HTTP_STATUS_CREATED,
                    Response::HTTP_STATUS_MOVED_PERMANENTLY,
                    Response::HTTP_STATUS_MOVED_TEMPORARILY,
                    Response::HTTP_STATUS_SEE_OTHER,
                    307,
                    308
                ]
            )) {
                continue;
            }
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
                    ->withBody(new Stream());

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
}
