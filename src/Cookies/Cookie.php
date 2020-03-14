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

use Berlioz\Http\Client\Exception\HttpClientException;
use DateInterval;
use DateTime;
use Exception;
use Psr\Http\Message\UriInterface;

/**
 * Class Cookie.
 *
 * @package Berlioz\Http\Client\Cookies
 */
class Cookie
{
    /** @var string Name */
    private $name;
    /** @var string|null Value */
    private $value;
    /** @var \DateTime|null Expires */
    private $expires;
    /** @var string|null Path */
    private $path;
    /** @var string Domain */
    private $domain;
    /** @var string Version */
    private $version;
    /** @var bool HTTP only? */
    private $httpOnly;
    /** @var bool Secure? */
    private $secure;

    /**
     * Parse raw cookie.
     *
     * @param string $raw
     * @param \Psr\Http\Message\UriInterface|null $uri
     *
     * @return \Berlioz\Http\Client\Cookies\Cookie
     * @throws \Berlioz\Http\Client\Exception\HttpClientException
     */
    public static function parse(string $raw, ?UriInterface $uri = null): Cookie
    {
        $cookie = new Cookie;

        // Parse
        $cookieTmp = explode(";", $raw);
        array_walk(
            $cookieTmp,
            function (&$value) {
                $value = explode('=', $value, 2);
                $value = array_map('trim', $value);
                $value[1] = $value[1] ?? null;
            }
        );
        $cookieTmp[] = ['name', $cookieTmp[0][0]];
        $cookieTmp[] = ['value', $cookieTmp[0][1]];
        unset($cookieTmp[0]);
        $cookieTmp = array_column($cookieTmp, 1, 0);
        $cookieTmp = array_change_key_case($cookieTmp, CASE_LOWER);

        // Make cookie
        $cookie->name = $cookieTmp['name'];
        $cookie->value = isset($cookieTmp['value']) ? str_replace(' ', '+', $cookieTmp['value']) : null;
        try {
            if (isset($cookieTmp['max-age'])) {
                $cookie->expires = new DateTime();
                $cookie->expires->add(new DateInterval(sprintf('PT%dS', $cookieTmp['max-age'])));
            }
            if (isset($cookieTmp['expires'])) {
                $cookie->expires = new DateTime($cookieTmp['expires']);
            }
        } catch (Exception $exception) {
            throw new HttpClientException(
                sprintf(
                    'Unable to parse expiration date "%s" of cookie "%s"',
                    $cookieTmp['max-age'] ?? $cookieTmp['expires'],
                    $cookie->name
                )
            );
        }
        $cookie->path = $cookieTmp['path'] ?? null;
        $cookie->domain = $cookieTmp['domain'] ?? ($uri ? $uri->getHost() : null);
        if (null === $cookie->domain) {
            throw new HttpClientException(sprintf('Missing domain for cookie "%s"', $cookie->name));
        }
        $cookie->domain = substr($cookie->domain, 0, 1) == '.' ? $cookie->domain : '.' . $cookie->domain;
        $cookie->version = $cookieTmp['version'] ?? null;
        $cookie->httpOnly = array_key_exists('httponly', $cookieTmp);
        $cookie->secure = array_key_exists('secure', $cookieTmp);

        return $cookie;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->name . "=" . str_replace("\0", "%00", $this->value);
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get value.
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Get expiration.
     *
     * @return \DateTime|null
     */
    public function getExpires(): ?DateTime
    {
        return $this->expires;
    }

    /**
     * Is expired?
     *
     * @param \DateTime|null $now
     *
     * @return bool
     */
    public function isExpired(?DateTime $now = null): bool
    {
        if (null === $this->expires) {
            return false;
        }

        if (null === $now) {
            try {
                $now = new DateTime();
            } catch (Exception $e) {
                return false;
            }
        }

        return $this->expires < $now;
    }

    /**
     * Get path.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Get domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get version.
     *
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Is HTTP only?
     *
     * @return mixed
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * Is secure?
     *
     * @return mixed
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Is same cookie?
     *
     * @param \Berlioz\Http\Client\Cookies\Cookie $cookie
     *
     * @return bool
     */
    public function isSame(Cookie $cookie): bool
    {
        return
            $this->getName() === $cookie->getName() &&
            $this->getDomain() === $cookie->getDomain() &&
            $this->getPath() === $cookie->getPath() &&
            $this->getVersion() === $cookie->getVersion() &&
            $this->isHttpOnly() === $cookie->isHttpOnly() &&
            $this->isSecure() === $cookie->isSecure();
    }

    /**
     * Is valid for URI?
     *
     * @param \Psr\Http\Message\UriInterface $uri
     *
     * @return bool
     */
    public function isValidForUri(UriInterface $uri): bool
    {
        // Check domain
        if (!(empty($this->domain) || empty($uri->getHost()) || $this->domain == $uri->getHost())) {
            if (substr($uri->getHost(), 0 - mb_strlen($this->domain)) != $this->domain) {
                if (substr($this->domain, 1) != $uri->getHost()) {
                    return false;
                }
            }
        }

        // Expired?
        if (null !== $this->expires) {
            try {
                if ($this->expires < (new DateTime())) {
                    return false;
                }
            } catch (Exception $exception) {
                return false;
            }
        }

        // Not valid path?
        if (!(empty($this->path) ||
            substr(
                $uri->getPath(),
                0,
                mb_strlen($this->path)
            ) == $this->path)) {
            return false;
        }

        // Secured?
        if (!($this->secure == false || $uri->getScheme() === 'https')) {
            return false;
        }

        return true;
    }

    /**
     * Update cookie.
     *
     * @param \Berlioz\Http\Client\Cookies\Cookie $cookie
     *
     * @return static
     */
    public function update(Cookie $cookie): Cookie
    {
        $this->name = $cookie->name;
        $this->value = $cookie->value;
        $this->expires = $cookie->expires;
        $this->path = $cookie->path;
        $this->domain = $cookie->domain;
        $this->version = $cookie->version;
        $this->httpOnly = $cookie->httpOnly;
        $this->secure = $cookie->secure;

        return $this;
    }
}