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

namespace Berlioz\HttpClient;

use Berlioz\Core\App\AppAwareInterface;
use Berlioz\Core\App\AppAwareTrait;
use Berlioz\Core\Http\Request;
use Berlioz\Core\Http\Response;
use Berlioz\Core\Http\Stream;
use Berlioz\Core\Http\Uri;
use Berlioz\Core\OptionList;
use Http\Client\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client implements HttpClient, AppAwareInterface
{
    use AppAwareTrait;
    /** @var \Berlioz\Core\OptionList Options */
    private $options;
    /** @var array CURL options */
    private $curlOptions;
    /** @var array Default headers */
    private $defaultHeaders;
    /** @var array History */
    private $history;
    /** @var \Berlioz\HttpClient\Cookies Cookies */
    private $cookies;

    /**
     * Client constructor.
     *
     * @param \Berlioz\Core\OptionList|null $options
     */
    public function __construct(OptionList $options = null)
    {
        // Default options
        $this->options = new OptionList(['followLocationLimit' => 5]);
        if (!is_null($options)) {
            $this->options->mergeWith($options);
        }

        // Init CURL options
        $this->curlOptions = [];

        // Default headers
        $this->defaultHeaders =
            ['Accept'          => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
             'User-Agent'      => ['BerliozBot/1.0'],
             'Accept-Language' => ['fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3'],
             'Accept-Encoding' => ['gzip, deflate'],
             'Accept-Charset'  => ['ISO-8859-1,utf-8;q=0.7,*;q=0.7'],
             'Connection'      => ['close']];

        // Init history
        $this->history = [];

        // Init cookies
        $this->cookies = new Cookies;
    }

    /**
     * Get options
     *
     * @return \Berlioz\Core\OptionList
     */
    public function getOptions(): OptionList
    {
        return $this->options;
    }

    /**
     * Get CURL options
     *
     * @return array
     */
    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }

    /**
     * Set CURL options
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
     * @param bool  $erase Erase all existent options (default: false)
     */
    public function setCurlOptions(array $curlOptions, bool $erase = false)
    {
        if (!$erase) {
            $curlOptions = array_merge($this->curlOptions, $curlOptions);
        }

        // Remove reserved CURL options
        $reservedOptions = [CURLOPT_HTTP_VERSION,
                            CURLOPT_CUSTOMREQUEST,
                            CURLOPT_URL,
                            CURLOPT_HEADER,
                            CURLINFO_HEADER_OUT,
                            CURLOPT_HTTPHEADER,
                            CURLOPT_FOLLOWLOCATION,
                            CURLOPT_RETURNTRANSFER,
                            CURLOPT_POST,
                            CURLOPT_POSTFIELDS];
        foreach ($reservedOptions as $reservedOption) {
            unset($curlOptions[$reservedOption]);
        }

        $this->curlOptions = $curlOptions;
    }

    /**
     * Set default headers
     *
     * @param array $headers
     * @param bool  $erase Erase if exists (default: true)
     */
    public function setDefaultHeaders(array $headers, bool $erase = true)
    {
        if ($erase) {
            $this->defaultHeaders = $headers;
        } else {
            $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        }
    }

    /**
     * Set default header
     *
     * @param string $name  Name
     * @param string $value Value
     * @param bool   $erase Erase if exists (default: true)
     */
    public function setDefaultHeader(string $name, string $value, bool $erase = true)
    {
        if ($erase || !isset($this->defaultHeaders[$name])) {
            $this->defaultHeaders[$name] = (array) $value;
        } else {
            $this->defaultHeaders[$name] = array_merge($this->defaultHeaders[$name] ?? [], (array) $value);
        }
    }

    /**
     * Log request and response
     *
     * @param \Psr\Http\Message\RequestInterface  $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    protected function log(RequestInterface $request, ResponseInterface $response = null)
    {
        $this->history[] = ['request'  => $request,
                            'response' => $response];
    }

    /**
     * Get history
     *
     * @param int|null $index History index (null for all, -1 for last)
     *
     * @return false|array
     */
    public function getHistory(int $index = null)
    {
        if (is_null($index)) {
            return $this->history;
        } else {
            if ($index == -1) {
                return end($this->history);
            } else {
                return $this->history[$index] ?? false;
            }
        }
    }

    /**
     * Get cookies manager
     *
     * @return \Berlioz\HttpClient\Cookies
     */
    public function getCookies(): Cookies
    {
        return $this->cookies;
    }

    /**
     * Parse headers
     *
     * @param string $headers      Raw headers
     * @param mixed  $reasonPhrase Reason phrase returned by reference
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
                $value = array_map(
                    function ($value) {
                        return trim($value);
                    },
                    $value);
                $value = array_filter($value);

                return $value;
            },
            $headers);
        $headers = array_filter($headers);

        foreach ($headers as $header) {
            $header[0] = \mb_convert_case($header[0], MB_CASE_TITLE);
            $header[1] = $header[1] ?? null;

            if (!isset($finalHeaders[$header[0]])) {
                $finalHeaders[$header[0]] = [$header[1]];
            } else {
                $finalHeaders[$header[0]][] = $header[1];
            }
        }

        // Treat first header
        $matches = [];
        if (preg_match("#^HTTP/([0-9\.]+) ([0-9]+) (.*)$#ie", $firstHeader, $matches) === 1) {
            $reasonPhrase = $matches[3];
        } else {
            $reasonPhrase = null;
        }

        return $finalHeaders;
    }

    /**
     * Init CURL options
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

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($request->getBody()->getSize() > 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $request->getBody());
        }

        // Set user options
        curl_setopt_array($ch, $this->getCurlOptions());

        return $ch;
    }

    /**
     * @inheritdoc
     */
    public function sendRequest(RequestInterface $request)
    {
        if (function_exists('curl_version')) {
            $followLocationCounter = 0;

            do {
                // Init CURL
                $request = $this->getCookies()->addCookiesToRequest($request);
                $ch = $this->initCurl($request);

                // Execute CURL request
                $content = curl_exec($ch);

                // CURL errors ?
                if (curl_errno($ch)) {
                    // Log request
                    $this->log($request);

                    throw new \Exception(sprintf('CURL error : %s (%s)', curl_error($ch), $request->getUri()), curl_errno($ch));
                }

                // Response
                {
                    // Headers
                    $reasonPhrase = null;
                    $headers = $this->parseHeaders((string) substr($content, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)), $reasonPhrase);

                    // Body
                    $stream = new Stream();
                    $stream->write((string) substr($content, curl_getinfo($ch, CURLINFO_HEADER_SIZE)));

                    // Construct object
                    $response = new Response(curl_getinfo($ch, CURLINFO_HTTP_CODE),
                                             $headers,
                                             $stream,
                                             $reasonPhrase);

                    // Parse response cookies
                    $this->getCookies()->addCookiesFromResponse($request->getUri(), $response);
                }

                // Log request & response
                $this->log($request, $response);

                // Follow location ?
                $followLocation = false;
                if (!empty($newLocation = curl_getinfo($ch, CURLOPT_FOLLOWLOCATION))) {
                    if ($followLocationCounter++ <= $this->getOptions()->get('followLocationLimit')) {
                        $followLocation = true;
                        /** @var \Psr\Http\Message\RequestInterface $request */
                        $request = $request->withUri(Uri::createFromString($newLocation));
                        $request = $request->withoutHeader('Referer');
                        $request = $request->withAddedHeader('Referer', (string) $request->getUri());
                    } else {
                        throw new \HttpRuntimeException('Too many redirection from host');
                    }
                }
            } while ($followLocation);

            return $response;
        } else {
            throw new \Exception('CURL module required for HTTP Client');
        }
    }

    /**
     * Request
     *
     * @param string $method
     * @param string $uri
     * @param array  $parameters Get parameters
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request(string $method, string $uri, array $parameters = [])
    {
        $uri = Uri::createFromString($uri);
        $uri->withQuery(http_build_query($parameters));

        return $this->sendRequest(new Request($method, $uri));
    }
}