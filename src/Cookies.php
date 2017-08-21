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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Cookies
{
    /** @var array Cookies */
    private $cookies;

    /**
     * Cookies constructor.
     */
    public function __construct()
    {
        $this->cookies = [];
    }

    /**
     * Get cookies line for specific uri
     *
     * @param \Psr\Http\Message\UriInterface $uri
     *
     * @return string
     */
    public function getCookiesForUri(UriInterface $uri)
    {
        $rawCookies = [];

        foreach ($this->cookies as $cookie) {
            // Good domain ?
            if (empty($cookie['domain']) || empty($uri->getHost()) || $cookie['domain'] == $uri->getHost()) {
                $domainValid = true;
            } else {
                if (substr($uri->getHost(), 0 - mb_strlen($cookie['domain'])) == $cookie['domain']) {
                    $domainValid = true;
                } else {
                    if (substr($cookie['domain'], 1) == $uri->getHost()) {
                        $domainValid = true;
                    } else {
                        $domainValid = false;
                    }
                }
            }

            // Domain valid ?
            if ($domainValid) {
                // Valid expiration ?
                if ($cookie['expires'] == 0 && $cookie['expires'] >= time()) {
                    // Valid path ?
                    if (empty($cookie['path']) || substr($uri->getPath(), 0 - mb_strlen($cookie['path'])) == $cookie['path']) {
                        $rawCookies[] = $cookie['name'] . "=" . str_replace("\0", "%00", $cookie['value']);
                    }
                }
            }
        }

        return implode('; ', $rawCookies);
    }

    /**
     * Add header line for cookies
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param bool                               $erase Erase Cookie line ? (default: true)
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function addCookiesToRequest(RequestInterface $request, bool $erase = true): RequestInterface
    {
        if ($erase) {
            $request = $request->withoutHeader('Cookie');
        }

        return $request->withAddedHeader('Cookie', $this->getCookiesForUri($request->getUri()));
    }

    /**
     * Add cookies from response
     *
     * @param \Psr\Http\Message\UriInterface      $uri
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function addCookiesFromResponse(UriInterface $uri, ResponseInterface $response)
    {
        $cookies = $response->getHeader('Set-Cookie');

        foreach ($cookies as $raw) {
            $this->addCookieRaw($raw, $uri);
        }
    }

    /**
     * Add raw cookie line
     *
     * @param string                              $raw
     * @param \Psr\Http\Message\UriInterface|null $uri
     */
    public function addCookieRaw(string $raw, UriInterface $uri = null)
    {
        // Cookie
        $cookie = ['name'     => null,
                   'value'    => null,
                   'expires'  => null,
                   'path'     => null,
                   'domain'   => null,
                   'version'  => null,
                   'httponly' => false,
                   'secure'   => false];

        // Parse
        $cookieTmp = explode(";", $raw);
        array_walk(
            $cookieTmp,
            function (&$value) {
                $value = explode('=', $value, 2);
                $value = array_map('trim', $value);
                $value[0] = mb_strtolower($value[0]);
                $value[1] = $value[1] ?? null;
            }
        );
        $cookieTmp[] = ['name', $cookieTmp[0][0]];
        $cookieTmp[] = ['value', $cookieTmp[0][1]];
        unset($cookieTmp[0]);
        $cookieTmp = array_column($cookieTmp, 1, 0);

        // Make cookie
        $cookie['name'] = $cookieTmp['name'];
        $cookie['value'] = $cookieTmp['value'] ? str_replace(' ', '+', $cookieTmp['value']) : null;
        $cookie['expires'] = $cookieTmp['max-age'] ? time() + $cookieTmp['max-age'] : ($cookieTmp['expires'] ? strtotime($cookieTmp['expires']) : null);
        $cookie['path'] = $cookieTmp['path'] ?? ($uri ? $uri->getPath() : null);
        $cookie['domain'] = $cookieTmp['domain'] ?? ($uri ? $uri->getHost() : null);
        $cookie['domain'] = substr($cookie['domain'], 0, 1) == '.' ? $cookie['domain'] : '.' . $cookie['domain'];
        $cookie['version'] = $cookieTmp['version'] ?? null;
        $cookie['httponly'] = isset($cookieTmp['httponly']);
        $cookie['secure'] = isset($cookieTmp['secure']);

        // Add cookie
        $this->cookies[$cookie['domain']][$cookie['name']] = $cookie;
    }
}