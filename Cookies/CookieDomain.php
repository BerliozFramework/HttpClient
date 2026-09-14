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

namespace Berlioz\Http\Client\Cookies;

use Berlioz\Http\Client\Exception\InvalidCookieDomainException;

/**
 * Class CookieDomain.
 *
 * @internal
 */
final class CookieDomain
{
    /**
     * Normalize a host or a cookie Domain attribute to ASCII.
     *
     * @param string $domain
     * @param bool $attribute Whether one leading dot may be removed
     *
     * @return string
     * @throws InvalidCookieDomainException
     */
    public static function normalize(string $domain, bool $attribute = false): string
    {
        if ($attribute && str_starts_with($domain, '.')) {
            $domain = substr($domain, 1);
        }

        if ('' === $domain) {
            throw new InvalidCookieDomainException('Missing cookie domain');
        }

        $ip = $domain;
        if (str_starts_with($domain, '[') && str_ends_with($domain, ']')) {
            $ip = substr($domain, 1, -1);
            if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new InvalidCookieDomainException('Invalid cookie IP address');
            }
        }
        if (false !== filter_var($ip, FILTER_VALIDATE_IP)) {
            return inet_ntop(inet_pton($ip));
        }

        if (!function_exists('idn_to_ascii')) {
            if (strlen($domain) > 253 || 1 !== preg_match(
                '/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/i',
                $domain,
            )) {
                throw new InvalidCookieDomainException('Invalid ASCII cookie domain or unavailable IDNA support');
            }

            return strtolower($domain);
        }

        $ascii = idn_to_ascii(
            $domain,
            IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_NONTRANSITIONAL_TO_ASCII,
            INTL_IDNA_VARIANT_UTS46,
        );
        if (false === $ascii || '' === $ascii || str_starts_with($ascii, '.') || str_ends_with($ascii, '.')) {
            throw new InvalidCookieDomainException('Invalid cookie domain');
        }

        return strtolower($ascii);
    }

    /**
     * Match normalized hosts, respecting DNS label boundaries and exact IP scope.
     *
     * @param string $host
     * @param string $domain
     * @param bool $hostOnly
     *
     * @return bool
     */
    public static function matches(string $host, string $domain, bool $hostOnly): bool
    {
        if ('' === $host || '' === $domain) {
            return false;
        }
        if ($host === $domain) {
            return true;
        }
        if ($hostOnly || false !== filter_var($host, FILTER_VALIDATE_IP) ||
            false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            return false;
        }

        return str_ends_with($host, '.' . $domain);
    }

}
