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

namespace Berlioz\Http\Client;

use Berlioz\Http\Client\Adapter\AdapterInterface;
use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Client\Adapter\StreamAdapter;
use Berlioz\Http\Client\Components;
use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\HttpException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Stream;
use Berlioz\Http\Message\Uri;
use Closure;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * Class Client.
 */
class Client implements ClientInterface, LoggerAwareInterface
{
    use Components\DefaultHeadersTrait;
    use Components\HeaderParserTrait;
    use Components\LogTrait;
    use Components\RequestFactoryTrait;

    private ?float $lastRequestTime = null;
    private array $adapters;
    private Session $session;

    /**
     * Client constructor.
     *
     * @param array $options
     * @param AdapterInterface ...$adapter
     *
     * @option string "baseUri"             Base of URI if not given in requests
     * @option int    "followLocationLimit" Limit location to follow
     * @option int    "sleepTime"           Sleep time between requests (ms) (default: 0)
     * @option string "logFile"             Log file name (only file name, not path)
     * @option bool   "exceptions"          Throw exceptions on error (default: true)
     * @option array  "headers"             Default headers
     */
    public function __construct(protected array $options = [], AdapterInterface ...$adapter)
    {
        // Merge with default options
        $this->options = array_replace_recursive(
            [
                'baseUri' => null,
                'followLocation' => true,
                'followLocationLimit' => 5,
                'sleepTime' => 0,
                'logFile' => null,
                'exceptions' => true,
                'headers' => [
                    'Accept' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
                    'User-Agent' => ['BerliozBot/1.0'],
                    'Accept-Language' => ['fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3'],
                    'Accept-Encoding' => ['gzip, deflate'],
                    'Accept-Charset' => ['ISO-8859-1,utf-8;q=0.7,*;q=0.7'],
                    'Connection' => ['close'],
                ],
            ],
            $this->options
        );
        $this->defaultHeaders = &$this->options['headers'];

        $this->adapters = $adapter ?: [extension_loaded('curl') ? new CurlAdapter() : new StreamAdapter()];
        $this->session = new Session();
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
        return [
            'adapters' => $this->adapters,
            'options' => $this->options,
            'session' => $this->session,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->adapters = $data['adapters'];
        $this->options = $data['options'];
        $this->session = $data['session'];
        $this->defaultHeaders = &$this->options['headers'];
    }

    /**
     * Get adapters.
     *
     * @return AdapterInterface[]
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * Get adapter.
     *
     * @param string|null $name
     *
     * @return AdapterInterface
     * @throws HttpClientException
     */
    public function getAdapter(?string $name = null): AdapterInterface
    {
        if (null === $name) {
            return reset($this->adapters);
        }

        foreach ($this->adapters as $adapter) {
            if ($name === $adapter->getName()) {
                return $adapter;
            }
        }

        throw new HttpClientException(sprintf('Unknown adapter "%s"', $name));
    }

    /**
     * Get session.
     *
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Set session.
     *
     * @param Session $session
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * Prepare request.
     *
     * @param RequestInterface $request
     * @param CookiesManager|false|null $cookies
     * @param array $options
     *
     * @return RequestInterface
     * @throws HttpClientException
     */
    protected function prepareRequest(
        RequestInterface $request,
        CookiesManager|false|null $cookies = null,
        array $options = []
    ): RequestInterface {
        $uriReconstituted = $this->reconstituteUri($request->getUri(), $options['baseUri'] ?? null);
        if ($uriReconstituted !== $request->getUri()) {
            $request = $request->withUri($uriReconstituted);
        }

        // Add default headers to request
        foreach ($options['headers'] ?? [] as $name => $value) {
            if ($request->hasHeader($name)) {
                continue;
            }

            $request = $request->withHeader($name, $value);
        }

        // Add cookies to request
        if (false !== $cookies) {
            $cookies = $cookies ?? new CookiesManager();
            $request = $cookies->addCookiesToRequest($request);
        }

        return $request;
    }

    /**
     * Reconstitute URI.
     *
     * @param UriInterface $uri
     * @param string|null $baseUri
     *
     * @return Uri
     * @throws HttpClientException
     */
    protected function reconstituteUri(UriInterface $uri, ?string $baseUri): Uri
    {
        // Already host
        if (!empty($uri->getHost())) {
            return $uri;
        }

        if (null === $baseUri) {
            // Get prev request to get base uri
            $baseUri = $this->getSession()->getHistory()->getLast()?->getRequest()->getUri();

            if (null === $baseUri) {
                throw new HttpClientException(
                    'Missing host on request, unable to reconstitute without "baseUri" option or previous request'
                );
            }

            $baseUri = $baseUri->withPath('')->withQuery('')->withFragment('');
        }

        return Uri::createFromString(
            rtrim((string)$baseUri, '/') .
            '/' .
            ltrim((string)$uri, '/')
        );
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request, array $options = []): ResponseInterface
    {
        $followLocationCounter = 0;
        $originalRequest = $request;

        // Merge options with global options
        $options = array_replace_recursive($this->options, $options);

        // Cookies manager
        // If option "cookies" is defined:
        // - false: no cookies sent in request
        // - CookieManager: no cookies sent in request
        $cookies = $this->getSession()->getCookies();
        if (isset($options['cookies'])) {
            $cookies = $options['cookies'] ?: new CookiesManager();

            if (!is_object($cookies) || !$cookies instanceof CookiesManager) {
                throw new HttpClientException(
                    sprintf(
                        'Option "cookies" must be an instance of "%s" class, or null or false value',
                        CookiesManager::class
                    )
                );
            }
        }

        do {
            $this->sleep();
            $request = $this->prepareRequest($request, $cookies, $options);
            $adapter = $this->getAdapter($options['adapter'] ?? null);

            try {
                $this->lastRequestTime = microtime(true);
                $response = $adapter->sendRequest($request);

                // Add request to history
                $this->getSession()->getHistory()->add($cookies, $request, $response, $adapter->getTimings());
            } catch (ClientExceptionInterface $exception) {
                $this->getSession()->getHistory()->add($cookies, $request, timings: $adapter->getTimings());
                $this->log($request);

                throw $exception;
            }

            $this->log($request, $response);
            $cookies->addCookiesFromResponse($request->getUri(), $response);

            // Callback
            $callback = $options['callback'] ?? null;
            if ($callback instanceof Closure) {
                $callback($request, $response);
            }

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
            if (false === $options['followLocation']) {
                continue;
            }
            $followLocation = ($followLocationCounter++ <= ($options['followLocationLimit'] ?? 5));
            if (!$followLocation) {
                throw new RequestException('Too many redirection from host', $originalRequest);
            }

            // Get redirect
            $redirectUri = Uri::createFromString($newLocation[0]);

            if (empty($redirectUri->getHost())) {
                $redirectUri =
                    $redirectUri
                        ->withScheme($request->getUri()->getScheme())
                        ->withHost($request->getUri()->getHost())
                        ->withPort($request->getUri()->getPort());

                if (!empty($userInfo = $request->getUri()->getUserInfo())) {
                    $redirectUri = $redirectUri->withUserInfo(...explode(':', $userInfo, 2));
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
            $request = $cookies->addCookiesToRequest($request);
        } while ($followLocation);

        // Exceptions if error?
        if ($options['exceptions']) {
            if (intval(substr((string)$response->getStatusCode(), 0, 1)) > 3) {
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
     * Sleep between two request.
     */
    protected function sleep(): void
    {
        // Initial request
        if (null === $this->lastRequestTime) {
            return;
        }

        // No sleep time
        if (($sleepMicroTime = $this->options['sleepTime'] * 1000) <= 0) {
            return;
        }

        // Sleep
        $diffTime = (microtime(true) - $this->lastRequestTime);
        if ($diffTime < $sleepMicroTime) {
            usleep((int)ceil($sleepMicroTime - $diffTime));
        }
    }
}
