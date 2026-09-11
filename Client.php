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
use Berlioz\Http\Client\Adapter\AutoAdapter;
use Berlioz\Http\Client\Components\DefaultHeadersTrait;
use Berlioz\Http\Client\Components\HeaderParserTrait;
use Berlioz\Http\Client\Components\LogTrait;
use Berlioz\Http\Client\Components\RequestFactoryTrait;
use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\HttpException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * Class Client.
 */
class Client implements ClientInterface, LoggerAwareInterface
{
    use DefaultHeadersTrait;
    use HeaderParserTrait;
    use LogTrait;
    use RequestFactoryTrait;

    private ?float $lastRequestTime = null;
    private Options $options;
    private array $adapters;
    private Session $session;

    /**
     * Client constructor.
     *
     * @param Options|array|null $options
     * @param AdapterInterface ...$adapter
     *
     * @option string "baseUri" Base of URI if not given in requests
     * @option false|int "followLocation" Follow redirections (default: 5)
     * @option int "sleepTime" Sleep time between requests (ms) (default: 0)
     * @option string "logFile" Log file name (only file name, not path)
     * @option bool "exceptions" Throw exceptions on error (default: true)
     * @option null|false|CookiesManager "cookies" NULL: to use default cookie manager; FALSE: to not use cookies; a CookieManager object to use
     * @option Closure "callback"  Callback after each request
     * @option Closure "callbackException"  Callback on exception
     * @option array "headers" Default headers
     */
    public function __construct(Options|array|null $options = null, AdapterInterface ...$adapter)
    {
        $this->options = Options::make($options);
        $this->defaultHeaders = &$this->options->headers;
        $this->adapters = $adapter ?: [new AutoAdapter()];
        $this->session = new Session(historySize: $this->options->history);
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
        $this->defaultHeaders = &$this->options->headers;
    }

    /**
     * Get options.
     *
     * @return Options
     */
    public function getOptions(): Options
    {
        return $this->options;
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
     * @param Options $options
     *
     * @return RequestInterface
     */
    protected function prepareRequest(
        RequestInterface $request,
        CookiesManager|false|null $cookies,
        Options $options
    ): RequestInterface {
        $request = $request->withUri(
            Uri::create(
                $request->getUri(),
                $options->baseUri ?? $this->getSession()->getHistory()->getLast()?->getRequest()->getUri()
            )
        );

        // Add default headers to request
        $request = $this->addDefaultHeaders($request, $options->headers);

        // Add content length
        if (false === $request->hasHeader('Content-Length')) {
            $length = $request->getBody()->getSize();

            if (null !== $length) {
                $request = $request->withHeader('Content-Length', $request->getBody()->getSize());
            }
        }

        // Add cookies to request
        if (false !== $cookies) {
            $cookies ??= new CookiesManager();
            $request = $cookies->addCookiesToRequest($request);
        }

        return $request;
    }

    /**
     * Compare absolute HTTP origins, including effective default ports.
     */
    protected function isSameOrigin(UriInterface $source, UriInterface $target): bool
    {
        $scheme = strtolower($source->getScheme());
        if (
            !in_array($scheme, ['http', 'https'], true)
            || $scheme !== strtolower($target->getScheme())
            || $source->getHost() === ''
            || strcasecmp($source->getHost(), $target->getHost()) !== 0
        ) {
            return false;
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;

        return ($source->getPort() ?? $defaultPort) === ($target->getPort() ?? $defaultPort);
    }

    /**
     * Remove mandatory and application credentials from cross-origin redirect headers.
     *
     * @param array<string, string|string[]> $headers
     * @return array<string, string|string[]>
     */
    protected function filterRedirectHeaders(array $headers, Options $options): array
    {
        $sensitiveHeaders = array_map(
            strtolower(...),
            array_merge(['Authorization', 'Proxy-Authorization', 'Cookie'], $options->redirectSensitiveHeaders),
        );

        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower($name), $sensitiveHeaders, true)) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    /**
     * Resolve Location before comparing origins; never accept credentials supplied by Location.
     */
    protected function prepareRedirectUri(string $location, UriInterface $source): UriInterface
    {
        $locationUri = Uri::createFromString($location);
        $target = Uri::create($locationUri, $source)->withFragment('');

        if ($locationUri->getUserInfo() !== '' || !$this->isSameOrigin($source, $target)) {
            $target = $target->withUserInfo('');
        }

        return $target;
    }

    /**
     * Generate a Referer using strict-origin-when-cross-origin semantics.
     */
    protected function createRedirectReferer(UriInterface $source, UriInterface $target): ?string
    {
        $sourceScheme = strtolower($source->getScheme());
        $targetScheme = strtolower($target->getScheme());
        if (
            !in_array($sourceScheme, ['http', 'https'], true)
            || !in_array($targetScheme, ['http', 'https'], true)
            || $source->getHost() === ''
            || $target->getHost() === ''
            || ($sourceScheme === 'https' && $targetScheme === 'http')
        ) {
            return null;
        }

        $referer = $source->withUserInfo('')->withFragment('');
        if (!$this->isSameOrigin($source, $target)) {
            $referer = $referer->withPath('/')->withQuery('');
        }

        return (string)$referer;
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request, Options|array $options = []): ResponseInterface
    {
        $followLocationCounter = 0;
        $stripSensitiveHeaders = false;
        $originalRequest = $request;

        // Keep redirect state local, including headers referenced by the client's default-header trait.
        $options = clone Options::make($options, $this->options);
        $headers = $options->headers;
        $options->headers = &$headers;

        // Keep an empty manager for history when automatic cookie handling is disabled.
        $cookies = $options->cookies ?? $this->getSession()->getCookies() ?: new CookiesManager();

        do {
            $this->sleep($options);
            $request = $this->prepareRequest($request, $options->cookies === false ? false : $cookies, $options);
            $adapter = $this->getAdapter($options->adapter);

            try {
                for ($iRetry = 0; $iRetry < max($options->retry ?: 1, 1); $iRetry++) {
                    try {
                        $this->lastRequestTime = microtime(true);
                        $response = $adapter->sendRequest($request, $options->context);
                        break;
                    } catch (NetworkExceptionInterface $exception) {
                        if (($iRetry + 1) >= $options->retry) {
                            throw $exception;
                        }

                        $this->getSession()->getHistory()->add($cookies, $request, timings: $adapter->getTimings());
                        usleep($options->retryTime * 1000);
                    }
                }

                // Add request to history
                $this->getSession()->getHistory()->add($cookies, $request, $response ?? null, $adapter->getTimings());
            } catch (ClientExceptionInterface $exception) {
                $this->getSession()->getHistory()->add($cookies, $request, timings: $adapter->getTimings());
                $this->log($request);

                throw $exception;
            }

            $this->log($request, $response);
            if ($options->cookies !== false) {
                $cookies->addCookiesFromResponse($request->getUri(), $response);
            }

            // Callback
            if (null !== $options->callback) {
                ($options->callback)($request, $response);
            }

            $followLocation = false;
            if (!in_array(
                $response->getStatusCode(),
                [
                    Response::HTTP_STATUS_CREATED,
                    Response::HTTP_STATUS_MOVED_PERMANENTLY,
                    Response::HTTP_STATUS_MOVED_TEMPORARILY,
                    Response::HTTP_STATUS_SEE_OTHER,
                    Response::HTTP_STATUS_TEMPORARY_REDIRECT,
                    Response::HTTP_STATUS_PERMANENT_REDIRECT,
                ]
            )) {
                continue;
            }
            if (empty($newLocation = $response->getHeader('Location'))) {
                continue;
            }

            // Follow location ?
            if (false === $options->followLocation) {
                continue;
            }
            $followLocation = ($followLocationCounter++ <= $options->followLocation);
            if (!$followLocation) {
                throw new RequestException('Too many redirection from host', $originalRequest);
            }

            $sourceUri = $request->getUri();
            $redirectUri = $this->prepareRedirectUri($newLocation[0], $sourceUri);
            $stripSensitiveHeaders = $stripSensitiveHeaders || !$this->isSameOrigin($sourceUri, $redirectUri);

            // Recompute redirect metadata rather than reapplying caller-supplied values.
            foreach (array_keys($options->headers) as $name) {
                if (in_array(strtolower((string)$name), ['referer', 'content-type', 'content-length'], true)) {
                    unset($options->headers[$name]);
                }
            }

            if (null !== ($referer = $this->createRedirectReferer($sourceUri, $redirectUri))) {
                $options->headers['Referer'] = [$referer];
            }

            $redirectMethod = Request::HTTP_METHOD_GET;
            $redirectBody = null;
            // For 307 and 308 redirect cases, method and body not changed.
            if (in_array(
                $response->getStatusCode(),
                [
                    Response::HTTP_STATUS_TEMPORARY_REDIRECT,
                    Response::HTTP_STATUS_PERMANENT_REDIRECT,
                ]
            )) {
                $redirectMethod = $request->getMethod();
                $redirectBody = $request->getBody();
                if ($request->hasHeader('Content-Type')) {
                    $options->headers['Content-Type'] = $request->getHeader('Content-Type');
                }
            }

            if ($stripSensitiveHeaders) {
                $options->headers = $this->filterRedirectHeaders($options->headers, $options);
                $redirectUri = $redirectUri->withUserInfo('');
            }

            // Prepare exactly once at the next iteration, using the persistently filtered options.
            $request = new Request($redirectMethod, $redirectUri, $redirectBody);
        } while ($followLocation);

        // Exceptions if error?
        if (true === $options->exceptions) {
            if (intval(substr((string)$response->getStatusCode(), 0, 1)) > 3) {
                $exception = new HttpException(
                    sprintf('%d - %s', $response->getStatusCode(), $response->getReasonPhrase()),
                    $originalRequest,
                    $response
                );

                if (null === $options->callbackException) {
                    throw $exception;
                }

                return ($options->callbackException)($exception, $options);
            }
        }

        return $response;
    }

    /**
     * Sleep between two request.
     */
    protected function sleep(Options $options): void
    {
        // Initial request
        if (null === $this->lastRequestTime) {
            return;
        }

        // No sleep time
        if (($sleepMicroTime = $options->sleepTime * 1000) <= 0) {
            return;
        }

        // Sleep
        $diffTime = (microtime(true) - $this->lastRequestTime);
        if ($diffTime < $sleepMicroTime) {
            usleep((int)ceil($sleepMicroTime - $diffTime));
        }
    }
}
