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

namespace Berlioz\Http\Client\Cookies;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class CookiesManager.
 *
 * @package Berlioz\Http\Client\Cookies
 */
class CookiesManager implements IteratorAggregate, Countable
{
    /** @var \Berlioz\Http\Client\Cookies\Cookie[] CookiesManager */
    protected $cookies = [];

    /**
     * CookiesManager constructor.
     */
    public function __construct()
    {
        $this->cookies = [];
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
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
     * Get cookie.
     *
     * @param string $name
     * @param \Psr\Http\Message\UriInterface $uri
     *
     * @return \Berlioz\Http\Client\Cookies\Cookie|null
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
     * @param \Psr\Http\Message\UriInterface $uri
     *
     * @return \Berlioz\Http\Client\Cookies\Cookie[]
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
     * @param \Berlioz\Http\Client\Cookies\Cookie $cookie
     *
     * @return CookiesManager
     */
    public function addCookie(Cookie $cookie): CookiesManager
    {
        foreach ($this->cookies as $aCookie) {
            if ($aCookie->isSame($cookie)) {
                $aCookie->update($cookie);
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
     * @param \Psr\Http\Message\UriInterface|null $uri
     *
     * @return static
     * @throws \Berlioz\Http\Client\Exception\HttpClientException
     */
    public function addRawCookie(string $raw, ?UriInterface $uri = null): CookiesManager
    {
        $this->addCookie(Cookie::parse($raw, $uri));

        return $this;
    }

    /**
     * Add cookies from response
     *
     * @param \Psr\Http\Message\UriInterface $uri
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return static
     * @throws \Berlioz\Http\Client\Exception\HttpClientException
     */
    public function addCookiesFromResponse(UriInterface $uri, ResponseInterface $response): CookiesManager
    {
        $cookies = $response->getHeader('Set-Cookie');

        foreach ($cookies as $raw) {
            $this->addRawCookie($raw, $uri);
        }

        return $this;
    }

    /**
     * Add header line for cookies
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param bool $erase Erase Cookie line ? (default: true)
     *
     * @return \Psr\Http\Message\RequestInterface
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
     * @param \Berlioz\Http\Client\Cookies\Cookie $cookie
     *
     * @return static
     */
    public function removeCookie(Cookie $cookie): CookiesManager
    {
        while (false !== ($key = array_search($cookie, $this->cookies, true))) {
            unset($this->cookies[$key]);
        }

        return $this;
    }
}