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
     * Create proxy from HTTP context.
     *
     * Only "http" and "https" proxies are supported, a proxy without scheme is an "http" proxy.
     * Any other configuration is rejected before any I/O.
     *
     * @param HttpContext|null $context
     * @param RequestInterface $request
     *
     * @return self|null
     * @throws RequestException
     */
    public static function fromContext(?HttpContext $context, RequestInterface $request): ?self
    {
        if (null === $context || false === $context->proxy) {
            return null;
        }

        $value = trim($context->proxy);
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
