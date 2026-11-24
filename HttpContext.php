<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client;

class HttpContext
{
    public function __construct(
        /** Proxy URL, false for no proxy, or null for the proxy of environment ("http_proxy", "https_proxy"...) */
        public string|false|null $proxy = null,
        public bool $ssl_verify_peer = true,
        public bool $ssl_verify_host = true,
        public ?string $ssl_ciphers = null,
        public ?string $ssl_cafile = null,
        public ?string $ssl_capath = null,
        public ?string $ssl_local_cert = null,
        public ?string $ssl_local_pk = null,
        /** @deprecated CURL < 7.17 */
        public ?string $ssl_local_cert_passphrase = null,
        public ?string $ssl_local_passphrase = null,
        /**
         * Additional origins allowed to receive origin credentials on redirects
         * ("https://*.example.com" for one subdomain level).
         *
         * @var string[]|null
         */
        public ?array $credentials_origins = null,
        public bool $proxy_ssl_verify_peer = true,
        public bool $proxy_ssl_verify_host = true,
        /** Default to $ssl_cafile */
        public ?string $proxy_ssl_cafile = null,
        /** Default to $ssl_capath */
        public ?string $proxy_ssl_capath = null,
    ) {
    }

    /**
     * Get a copy without origin credentials (TLS client certificate), keeping proxy and TLS verification settings.
     *
     * @return static
     */
    public function withoutOriginCredentials(): static
    {
        $clone = clone $this;
        $clone->ssl_local_cert = null;
        $clone->ssl_local_pk = null;
        $clone->ssl_local_cert_passphrase = null;
        $clone->ssl_local_passphrase = null;

        return $clone;
    }

    public static function make(array|self|null $context = null, ?self $initial = null): self
    {
        // None defined
        if (null === $context) {
            if (null !== $initial) {
                return clone $initial;
            }

            return new self();
        }

        // Array of options
        if (is_array($context)) {
            $new = null !== $initial ? clone $initial : new self();

            foreach ($context as $key => $value) {
                $new->$key = $value;
            }

            return $new;
        }

        return $context;
    }
}
