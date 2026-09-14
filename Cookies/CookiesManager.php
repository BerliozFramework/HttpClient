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

namespace Berlioz\Http\Client\Cookies;

use ArrayIterator;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\InvalidCookieDomainException;
use Countable;
use IteratorAggregate;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use TypeError;

/**
 * Class CookiesManager.
 */
class CookiesManager implements IteratorAggregate, Countable
{
    /** @var Cookie[] CookiesManager */
    protected array $cookies = [];

    /**
     * CookiesManager constructor.
     *
     * Restores an already deduplicated snapshot in its original order, without updating its cookies.
     * Use addCookie() when replacement by name, domain and path is required.
     *
     * @param Cookie[] $cookies Cookies unique by name, domain and path
     *
     * @throws TypeError If an element is not a Cookie
     */
    public function __construct(array $cookies = [])
    {
        foreach ($cookies as $cookie) {
            if (!$cookie instanceof Cookie) {
                throw new TypeError(sprintf('Expected a Cookie instance, %s given', get_debug_type($cookie)));
            }
        }

        $this->cookies = array_values($cookies);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->cookies);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->cookies);
    }

    /**
     * Get array copy of cookies.
     *
     * @param UriInterface|null $uri
     *
     * @return array
     */
    public function getArrayCopy(?UriInterface $uri = null): array
    {
        $cookies = $uri ? $this->getCookiesForUri($uri) : $this->cookies;

        return array_map(fn(Cookie $cookie) => $cookie->getArrayCopy(), $cookies);
    }

    /**
     * Get cookie.
     *
     * @param string $name
     * @param UriInterface $uri
     *
     * @return Cookie|null
     */
    public function getCookie(string $name, UriInterface $uri): ?Cookie
    {
        foreach ($this->getCookiesForUri($uri) as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * Get cookies line for specific uri
     *
     * @param UriInterface $uri
     *
     * @return Cookie[]
     */
    public function getCookiesForUri(UriInterface $uri): array
    {
        $uriCookies = [];

        foreach ($this->cookies as $cookie) {
            if (!$cookie->isValidForUri($uri)) {
                continue;
            }

            $uriCookies[] = $cookie;
        }

        return $uriCookies;
    }

    /**
     * Add cookie.
     *
     * Explicit application insertion; the caller is responsible for validating the cookie's source.
     *
     * @param Cookie $cookie
     *
     * @return static
     */
    public function addCookie(Cookie $cookie): static
    {
        foreach ($this->cookies as $aCookie) {
            if (true === $aCookie->update($cookie)) {
                return $this;
            }
        }

        $this->cookies[] = $cookie;

        return $this;
    }

    /**
     * Add raw cookie.
     *
     * @param string $raw
     * @param UriInterface|null $uri
     *
     * @return static
     * @throws HttpClientException
     */
    public function addRawCookie(string $raw, ?UriInterface $uri = null): static
    {
        $this->addCookie(Cookie::parse($raw, $uri));

        return $this;
    }

    /**
     * Add cookies from response
     *
     * Cookies with a domain not authorized by the response URI are ignored individually.
     *
     * @param UriInterface $uri
     * @param ResponseInterface $response
     *
     * @return static
     * @throws HttpClientException
     */
    public function addCookiesFromResponse(UriInterface $uri, ResponseInterface $response): static
    {
        $cookies = $response->getHeader('Set-Cookie');

        foreach ($cookies as $raw) {
            try {
                $this->addRawCookie($raw, $uri);
            } catch (InvalidCookieDomainException) {
                continue;
            }
        }

        return $this;
    }

    /**
     * Add header line for cookies
     *
     * @param RequestInterface $request
     * @param bool $erase Erase Cookie line ? (default: true)
     *
     * @return RequestInterface
     */
    public function addCookiesToRequest(RequestInterface $request, bool $erase = true): RequestInterface
    {
        if ($erase) {
            $request = $request->withoutHeader('Cookie');
        }

        $uriCookies = $this->getCookiesForUri($request->getUri());

        if (count($uriCookies) > 0) {
            return $request->withAddedHeader('Cookie', implode('; ', $uriCookies));
        }

        return $request;
    }

    /**
     * Remove cookie.
     *
     * @param Cookie $cookie
     *
     * @return static
     */
    public function removeCookie(Cookie $cookie): static
    {
        while (false !== ($key = array_search($cookie, $this->cookies, true))) {
            unset($this->cookies[$key]);
        }

        return $this;
    }
}
