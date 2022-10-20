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
        public string|false $proxy = false,
        public bool $ssl_verify_peer = true,
        public bool $ssl_verify_host = true,
        public ?string $ssl_ciphers = null,
        public ?string $ssl_cafile = null,
        public ?string $ssl_capath = null,
        public ?string $ssl_local_cert = null,
        public ?string $ssl_local_pk = null,
        public ?string $ssl_local_cert_passphrase = null,
    ) {
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