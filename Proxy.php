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

use Berlioz\Http\Client\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use SensitiveParameter;

/**
 * Class Proxy.
 *
 * Validated proxy configuration, shared by the transport adapters.
 *
 * @internal
 */
final readonly class Proxy
{
    public const SCHEME_HTTP = 'http';
    public const SCHEME_HTTPS = 'https';
    private const SOCKS_SCHEMES = ['socks4', 'socks4a', 'socks5', 'socks5h'];

    public function __construct(
        public string $scheme,
        public string $host,
        public int $port,
        public ?string $user = null,
        #[SensitiveParameter] public ?string $password = null,
    ) {
    }

    /**
     * __debugInfo() magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'scheme' => $this->scheme,
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'password' => null !== $this->password ? '*** HIDDEN ***' : null,
        ];
    }

    /**
     * Resolve proxy of request.
     *
     * The proxy of the HTTP context is used if configured, no proxy if disabled (false),
     * else the proxy of environment.
     *
     * @param HttpContext|null $context
     * @param RequestInterface $request
     *
     * @return self|null
     * @throws RequestException
     */
    public static function resolve(?HttpContext $context, RequestInterface $request): ?self
    {
        return match ($context?->proxy) {
            false => null,
            null => self::fromEnvironment($request),
            default => self::fromContext($context, $request),
        };
    }

    /**
     * Create proxy from HTTP context.
     *
     * Only "http" and "https" proxies are supported, a proxy without scheme is an "http" proxy.
     * Any other configuration is rejected before any I/O.
     *
     * @param HttpContext|null $context
     * @param RequestInterface $request
     *
     * @return self|null Null if no proxy is configured
     * @throws RequestException
     */
    public static function fromContext(?HttpContext $context, RequestInterface $request): ?self
    {
        if (!is_string($context?->proxy)) {
            return null;
        }

        return self::fromString($context->proxy, $request);
    }

    /**
     * Create proxy from environment variables.
     *
     * Variables "https_proxy" (or "HTTPS_PROXY") for HTTPS requests, "http_proxy" for HTTP requests,
     * and "all_proxy" (or "ALL_PROXY") for both; hosts of "no_proxy" (or "NO_PROXY") are excluded.
     * The uppercase "HTTP_PROXY" is never used: it can be set by the "Proxy" request header in CGI context (httpoxy).
     *
     * @param RequestInterface $request
     *
     * @return self|null
     * @throws RequestException
     */
    public static function fromEnvironment(RequestInterface $request): ?self
    {
        if (null === ($env = self::getEnvironmentProxy($request))) {
            return null;
        }

        try {
            return self::fromString($env[1], $request);
        } catch (RequestException $exception) {
            throw new RequestException(
                sprintf('Environment variable "%s": %s', $env[0], $exception->getMessage()),
                $request,
                previous: $exception,
            );
        }
    }

    /**
     * Get the SOCKS proxy URL of environment variables.
     *
     * SOCKS proxies are not supported by this class, but the cURL adapter hands them over to libcurl,
     * which used them before the proxy was resolved by the adapters.
     *
     * @param RequestInterface $request
     *
     * @return string|null Null if the proxy of environment is not a SOCKS proxy, or if there is none
     */
    public static function socksFromEnvironment(RequestInterface $request): ?string
    {
        if (null === ($env = self::getEnvironmentProxy($request))) {
            return null;
        }

        $scheme = strtolower(strstr($env[1], '://', true) ?: '');

        return in_array($scheme, self::SOCKS_SCHEMES, true) ? $env[1] : null;
    }

    /**
     * Get the proxy environment variable of request, unless the host is excluded by "no_proxy".
     *
     * @param RequestInterface $request
     *
     * @return array{string, string}|null Name and value of variable
     */
    private static function getEnvironmentProxy(RequestInterface $request): ?array
    {
        $uri = $request->getUri();
        $secure = 'https' === strtolower($uri->getScheme());

        $env = match ($secure) {
            true => self::getEnv('https_proxy', 'HTTPS_PROXY'),
            false => self::getHttpProxyEnv(),
        };
        $env ??= self::getEnv('all_proxy', 'ALL_PROXY');

        if (null === $env) {
            return null;
        }

        $noProxy = self::getEnv('no_proxy', 'NO_PROXY')[1] ?? null;
        if (null !== $noProxy && self::isExcluded($uri->getHost(), $uri->getPort() ?? ($secure ? 443 : 80), $noProxy)) {
            return null;
        }

        return $env;
    }

    /**
     * Get the first non-empty environment variable.
     *
     * @param string ...$names
     *
     * @return array{string, string}|null Name and value of variable
     */
    private static function getEnv(string ...$names): ?array
    {
        foreach ($names as $name) {
            $value = getenv($name);

            if (is_string($value) && '' !== trim($value)) {
                return [$name, trim($value)];
            }
        }

        return null;
    }

    /**
     * Get "http_proxy" environment variable.
     *
     * Environment variables are case-insensitive on Windows: "http_proxy" may be the "HTTP_PROXY" one,
     * set by the "Proxy" request header outside of CLI.
     *
     * @return array{string, string}|null Name and value of variable
     */
    private static function getHttpProxyEnv(): ?array
    {
        if ('Windows' === PHP_OS_FAMILY && 'cli' !== PHP_SAPI) {
            return null;
        }

        return self::getEnv('http_proxy');
    }

    /**
     * Is host excluded by a "no_proxy" list?
     *
     * Entries are separated by commas: "*" for all hosts, host names (also matching their sub-domains,
     * with or without leading dot), IP addresses or CIDR ranges, with an optional port.
     * An IP address host only matches IP address and CIDR range entries.
     *
     * @param string $host
     * @param int $port
     * @param string $noProxy
     *
     * @return bool
     */
    public static function isExcluded(string $host, int $port, string $noProxy): bool
    {
        $host = rtrim(strtolower(trim($host, '[]')), '.');
        $hostIsIp = false !== @inet_pton($host);

        foreach (explode(',', $noProxy) as $entry) {
            $entry = strtolower(trim($entry));

            if ('' === $entry) {
                continue;
            }
            if ('*' === $entry) {
                return true;
            }

            // Optional port: "host:port", "[ipv6]:port"
            $entryPort = null;
            if (1 === preg_match('/^(\[[^]]+]|[^:]+):(\d+)$/', $entry, $matches)) {
                [, $entry, $entryPort] = $matches;
            }
            if (null !== $entryPort && (int)$entryPort !== $port) {
                continue;
            }

            $entry = trim($entry, '[]');

            if ($hostIsIp) {
                if (self::matchIp($host, $entry)) {
                    return true;
                }

                continue;
            }

            $entry = rtrim(ltrim($entry, '*.'), '.');
            if ('' !== $entry && ($host === $entry || str_ends_with($host, '.' . $entry))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match IP address with an IP address or CIDR range.
     *
     * @param string $host
     * @param string $entry
     *
     * @return bool
     */
    private static function matchIp(string $host, string $entry): bool
    {
        if (false === ($hostIp = @inet_pton($host))) {
            return false;
        }

        $bits = null;
        if (str_contains($entry, '/')) {
            [$entry, $bits] = explode('/', $entry, 2);
            if (!ctype_digit($bits)) {
                return false;
            }
            $bits = (int)$bits;
        }

        if (false === ($entryIp = @inet_pton($entry)) || strlen($entryIp) !== strlen($hostIp)) {
            return false;
        }

        $bits ??= strlen($hostIp) * 8;
        if ($bits > strlen($hostIp) * 8) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        if (substr($hostIp, 0, $bytes) !== substr($entryIp, 0, $bytes)) {
            return false;
        }
        if (0 === ($remaining = $bits % 8)) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($hostIp[$bytes]) & $mask) === (ord($entryIp[$bytes]) & $mask);
    }

    /**
     * Create proxy from string.
     *
     * @param string $value
     * @param RequestInterface $request
     *
     * @return self
     * @throws RequestException
     */
    private static function fromString(string $value, RequestInterface $request): self
    {
        $value = trim($value);
        if (!str_contains($value, '://')) {
            $value = self::SCHEME_HTTP . '://' . $value;
        }

        $parsed = parse_url($value);

        if (false === $parsed) {
            throw new RequestException('Invalid proxy configuration', $request);
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, [self::SCHEME_HTTP, self::SCHEME_HTTPS], true)) {
            throw new RequestException(
                sprintf('Unsupported proxy scheme "%s", only "http" and "https" are supported', $scheme),
                $request
            );
        }

        if (empty($parsed['host'])) {
            throw new RequestException('Invalid proxy configuration: missing host', $request);
        }

        if (!in_array($parsed['path'] ?? '', ['', '/'], true) ||
            isset($parsed['query']) ||
            isset($parsed['fragment'])) {
            throw new RequestException(
                'Invalid proxy configuration: path, query and fragment are not allowed',
                $request
            );
        }

        return new self(
            scheme: $scheme,
            host: $parsed['host'],
            port: $parsed['port'] ?? ($scheme === self::SCHEME_HTTPS ? 443 : 80),
            user: isset($parsed['user']) ? rawurldecode($parsed['user']) : null,
            password: isset($parsed['pass']) ? rawurldecode($parsed['pass']) : null,
        );
    }

    /**
     * Is a TLS connection to the proxy?
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return self::SCHEME_HTTPS === $this->scheme;
    }

    /**
     * Get authority (host and port), without credentials.
     *
     * @return string
     */
    public function getAuthority(): string
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    /**
     * Get URL, without credentials.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return sprintf('%s://%s', $this->scheme, $this->getAuthority());
    }

    /**
     * Get value of the "Proxy-Authorization" header.
     *
     * @return string|null
     */
    public function getAuthorization(): ?string
    {
        if (null === $this->user) {
            return null;
        }

        return 'Basic ' . base64_encode($this->user . ':' . ($this->password ?? ''));
    }
}
