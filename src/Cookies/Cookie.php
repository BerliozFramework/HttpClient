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
use DateTime;
use DateTimeInterface;
use ElGigi\HarParser\Entities\Cookie as HarCookie;
use Exception;
use Psr\Http\Message\UriInterface;

/**
 * Class Cookie.
 */
class Cookie
{
    use CookieParserTrait;

    protected string $name;
    protected ?string $value;
    protected ?DateTimeInterface $expires = null;
    protected ?string $path = null;
    protected ?string $domain = null;
    protected ?string $version = null;
    protected bool $httpOnly = false;
    protected bool $secure = false;
    protected ?string $sameSite = null;

    /**
     * Parse raw cookie.
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
            $cookie->domain = $cookieParsed['domain'] ?? ($uri ? $uri->getHost() : null);
            if (null === $cookie->domain) {
                throw new HttpClientException(sprintf('Missing domain for cookie "%s"', $cookie->name));
            }
            $cookie->domain = str_starts_with($cookie->domain, '.') ? $cookie->domain : '.' . $cookie->domain;
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

    /**
     * Create cookie from HAR cookie.
     *
     * @param HarCookie $harCookie
     *
     * @return static
     */
    public static function createFromHar(HarCookie $harCookie): static
    {
        $cookie = new Cookie();
        $cookie->name = $harCookie->getName();
        $cookie->value = $harCookie->getValue();
        $cookie->expires = $harCookie->getExpires();
        $cookie->path = $harCookie->getPath();
        $cookie->domain = $harCookie->getDomain();
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
            $this->getPath() === $cookie->getPath() &&
            $this->getVersion() === $cookie->getVersion() &&
            $this->isHttpOnly() === $cookie->isHttpOnly() &&
            $this->isSecure() === $cookie->isSecure();
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
        if (!(empty($this->domain) || empty($uri->getHost()) || $this->domain == $uri->getHost())) {
            if (substr($uri->getHost(), 0 - mb_strlen($this->domain)) != $this->domain) {
                if (substr($this->domain, 1) != $uri->getHost()) {
                    return false;
                }
            }
        }

        // Expired?
        if ($this->isExpired()) {
            return false;
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
     * @param Cookie $cookie
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