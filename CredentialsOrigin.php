<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client;

use Berlioz\Http\Client\Exception\InvalidOriginException;
use Psr\Http\Message\UriInterface;

/**
 * Class CredentialsOrigin.
 *
 * Origin approved to receive origin-scoped transport credentials, given as "scheme://host[:port]".
 * A leading "*." label matches exactly one subdomain level, never the parent domain itself.
 */
final readonly class CredentialsOrigin
{
    private function __construct(
        public string $scheme,
        public string $host,
        public int $port,
        public bool $wildcard,
    ) {
    }

    /**
     * Create from string.
     *
     * @param string $origin
     *
     * @return self
     * @throws InvalidOriginException
     */
    public static function fromString(string $origin): self
    {
        $parsed = parse_url($origin);

        if (false === $parsed || !isset($parsed['scheme'], $parsed['host'])) {
            throw InvalidOriginException::invalid($origin, 'expected "scheme://host[:port]"');
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw InvalidOriginException::invalid($origin, 'only "http" and "https" schemes are supported');
        }

        if (
            isset($parsed['user'])
            || isset($parsed['pass'])
            || isset($parsed['query'])
            || isset($parsed['fragment'])
            || !in_array($parsed['path'] ?? '', ['', '/'], true)
        ) {
            throw InvalidOriginException::invalid($origin, 'an origin has no user info, path, query or fragment');
        }

        $host = self::normalizeHost($parsed['host']);
        $wildcard = str_starts_with($host, '*.');
        if ($wildcard) {
            $host = substr($host, 2);
        }

        if (str_contains($host, '*')) {
            throw InvalidOriginException::invalid($origin, 'wildcard is only allowed as a full leftmost label');
        }

        $labels = explode('.', $host);
        if (in_array('', $labels, true)) {
            throw InvalidOriginException::invalid($origin, 'empty host label');
        }

        if ($wildcard && (count($labels) < 2 || self::isIpHost($host))) {
            throw InvalidOriginException::invalid($origin, 'wildcard requires a domain with at least two labels');
        }

        return new self(
            scheme: $scheme,
            host: $host,
            port: $parsed['port'] ?? self::defaultPort($scheme),
            wildcard: $wildcard,
        );
    }

    /**
     * Is URI covered by this origin?
     *
     * @param UriInterface $uri
     *
     * @return bool
     */
    public function matches(UriInterface $uri): bool
    {
        $scheme = strtolower($uri->getScheme());
        if ($scheme !== $this->scheme || ($uri->getPort() ?? self::defaultPort($scheme)) !== $this->port) {
            return false;
        }

        $host = self::normalizeHost($uri->getHost());
        if ('' === $host) {
            return false;
        }

        if (false === $this->wildcard) {
            return $host === $this->host;
        }

        $suffix = '.' . $this->host;
        if (self::isIpHost($host) || !str_ends_with($host, $suffix)) {
            return false;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        return '' !== $subdomain && !str_contains($subdomain, '.');
    }

    private static function normalizeHost(string $host): string
    {
        return rtrim(strtolower($host), '.');
    }

    private static function isIpHost(string $host): bool
    {
        return str_starts_with($host, '[') || false !== filter_var($host, FILTER_VALIDATE_IP);
    }

    private static function defaultPort(string $scheme): int
    {
        return 'https' === $scheme ? 443 : 80;
    }
}
