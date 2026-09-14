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

use Berlioz\Http\Client\Components\CookieParserTrait;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\InvalidCookieDomainException;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use ElGigi\HarParser\Entities as Har;
use Exception;
use Psr\Http\Message\UriInterface;
use Stringable;

/**
 * Class Cookie.
 */
class Cookie implements Stringable
{
    use CookieParserTrait;

    protected string $name;
    protected ?string $value = null;
    protected ?DateTimeInterface $expires = null;
    protected ?string $path = null;
    protected ?string $domain = null;
    protected bool $hostOnly = true;
    protected ?string $version = null;
    protected bool $httpOnly = false;
    protected bool $secure = false;
    protected ?string $sameSite = null;

    /**
     * Parse raw cookie.
     *
     * When an URI is provided, the cookie domain must match its host.
     * Without an URI, an explicit Domain is required and its source cannot be validated.
     *
     * @param string $raw
     * @param UriInterface|null $uri
     *
     * @return Cookie
     * @throws HttpClientException
     */
    public static function parse(string $raw, ?UriInterface $uri = null): Cookie
    {
        try {
            $cookie = new Cookie();
            $cookieParsed = $cookie->parseCookie($raw);
            $cookie->name = $cookieParsed['name'];
            $cookie->value = $cookieParsed['value'];
            $cookie->expires = $cookieParsed['expires'];
            $cookie->path = $cookieParsed['path'] ?? null;
            if (null === $cookie->path || !str_starts_with($cookie->path, '/')) {
                $cookie->path = self::defaultPath($uri?->getPath() ?? '');
            }
            $domain = $cookieParsed['domain'];
            $cookie->hostOnly = null === $domain || '' === $domain;
            $host = null !== $uri ? CookieDomain::normalize($uri->getHost()) : null;
            $cookie->domain = $cookie->hostOnly
                ? ($host ?? throw new InvalidCookieDomainException('Missing cookie domain'))
                : CookieDomain::normalize($domain, attribute: true);
            if (null !== $host && !CookieDomain::matches(
                $host,
                $cookie->domain,
                $cookie->hostOnly,
            )) {
                throw new InvalidCookieDomainException('Cookie domain does not match the response host');
            }
            $cookie->version = $cookieParsed['version'] ?? null;
            $cookie->httpOnly = $cookieParsed['httponly'];
            $cookie->secure = $cookieParsed['secure'];
            $cookie->sameSite = $cookieParsed['samesite'];
        } catch (HttpClientException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new HttpClientException('Unable to parse cookie', previous: $exception);
        }

        return $cookie;
    }

    private static function defaultPath(string $path): string
    {
        if (!str_starts_with($path, '/') || 0 === ($lastSlash = strrpos($path, '/'))) {
            return '/';
        }

        return substr($path, 0, $lastSlash);
    }

    /**
     * Create cookie from HAR cookie.
     *
     * @param Har\Cookie $harCookie
     * @param UriInterface|null $uri Originating HAR entry URI
     *
     * @return static
     * @throws InvalidCookieDomainException
     */
    public static function createFromHar(Har\Cookie $harCookie, ?UriInterface $uri = null): static
    {
        $cookie = new Cookie();
        $cookie->name = $harCookie->getName();
        $cookie->value = $harCookie->getValue();
        $cookie->expires = $harCookie->getExpires();
        $cookie->path = $harCookie->getPath();
        if (null === $cookie->path || !str_starts_with($cookie->path, '/')) {
            $cookie->path = null !== $uri ? self::defaultPath($uri->getPath()) : null;
        }
        $domain = $harCookie->getDomain();
        // HAR has no standard hostOnly field. Undotted/absent domains are conservatively host-only.
        $cookie->hostOnly = null === $domain || !str_starts_with($domain, '.');
        $host = null !== $uri ? CookieDomain::normalize($uri->getHost()) : null;
        $cookie->domain = null === $domain || '' === $domain
            ? $host
            : CookieDomain::normalize($domain, attribute: true);
        if (null !== $host && null !== $cookie->domain && !CookieDomain::matches(
            $host,
            $cookie->domain,
            hostOnly: false,
        )) {
            throw new InvalidCookieDomainException('HAR cookie domain does not match the entry host');
        }
        if ($cookie->hostOnly && null !== $host) {
            $cookie->domain = $host;
        }
        $cookie->httpOnly = $harCookie->isHttpOnly() ?? false;
        $cookie->secure = $harCookie->isSecure() ?? false;
        $cookie->sameSite = $harCookie->getSameSite();

        return $cookie;
    }

    /**
     * Get array copy of cookie.
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return array_filter(
            [
                'name' => $this->name,
                'value' => $this->value,
                'expires' => $this->expires,
                'path' => $this->path,
                'domain' => $this->domain,
                'hostOnly' => $this->hostOnly,
                'version' => $this->version,
                'httpOnly' => $this->httpOnly,
                'secure' => $this->secure,
                'sameSite' => $this->sameSite,
            ],
            fn($value) => null !== $value
        );
    }

    /**
     * __toString() PHP magic method.
     */
    public function __toString(): string
    {
        return $this->getResponseHeader();
    }

    /**
     * Get request header string value.
     *
     * @return string
     */
    public function getRequestHeader(): string
    {
        $str = (string)$this;

        null !== $this->expires &&
        $str .= '; Expires=' . $this->expires->setTimezone(new DateTimeZone('GMT'))->format('r');
        null !== $this->path && $str .= '; Path=' . $this->path;
        !$this->hostOnly && null !== $this->domain && $str .= '; Domain=' . $this->domain;
        null !== $this->version && $str .= '; Version=' . $this->version;
        true === $this->httpOnly && $str .= '; HttpOnly';
        true === $this->secure && $str .= '; Secure';
        null !== $this->sameSite && $str .= '; SameSite=' . $this->sameSite;

        return $str;
    }

    /**
     * Get response header string value.
     *
     * @return string
     */
    public function getResponseHeader(): string
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
     * @return DateTimeInterface|null
     */
    public function getExpires(): ?DateTimeInterface
    {
        return $this->expires;
    }

    /**
     * Is expired?
     *
     * @param DateTime|null $now
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
            } catch (Exception) {
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
     * Is this cookie restricted to its exact host?
     *
     * @return bool
     */
    public function isHostOnly(): bool
    {
        return $this->hostOnly;
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
     * Get same site.
     *
     * @return string|null
     */
    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    /**
     * Is same cookie?
     *
     * @param Cookie $cookie
     *
     * @return bool
     */
    public function isSame(Cookie $cookie): bool
    {
        return
            $this->getName() === $cookie->getName() &&
            $this->getDomain() === $cookie->getDomain() &&
            $this->getPath() === $cookie->getPath();
    }

    /**
     * Update cookie.
     *
     * @param Cookie $cookie
     *
     * @return bool
     */
    public function update(Cookie $cookie): bool
    {
        // Not same cookie, return false!
        if (!$this->isSame($cookie)) {
            return false;
        }

        $this->value = $cookie->value;
        $this->expires = $cookie->expires;
        $this->version = $cookie->version;
        $this->httpOnly = $cookie->httpOnly;
        $this->secure = $cookie->secure;
        $this->hostOnly = $cookie->hostOnly;
        $this->sameSite = $cookie->sameSite;

        return true;
    }

    /**
     * Is valid for URI?
     *
     * @param UriInterface $uri
     *
     * @return bool
     */
    public function isValidForUri(UriInterface $uri): bool
    {
        // Check domain
        try {
            $host = CookieDomain::normalize($uri->getHost());
        } catch (InvalidCookieDomainException) {
            return false;
        }
        if (!CookieDomain::matches($host, $this->domain ?? '', $this->hostOnly)) {
            return false;
        }

        // Expired?
        if ($this->isExpired()) {
            return false;
        }

        // Not valid path?
        $path = $uri->getPath() ?: '/';
        $cookiePath = $this->path ?? '/';
        if ($path !== $cookiePath && !(
            str_starts_with($path, $cookiePath) &&
            (str_ends_with($cookiePath, '/') || '/' === ($path[strlen($cookiePath)] ?? ''))
        )) {
            return false;
        }

        // Secured?
        if (!($this->secure == false || $uri->getScheme() === 'https')) {
            return false;
        }

        return true;
    }
}
