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

namespace Berlioz\Http\Client\Components;

use Berlioz\Http\Client\Exception\HttpClientException;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Throwable;

/**
 * Trait CookieParserTrait.
 */
trait CookieParserTrait
{
    /**
     * Get now.
     *
     * @return DateTimeInterface
     */
    private function now(): DateTimeInterface
    {
        return new DateTimeImmutable('now');
    }

    /**
     * Parse cookie.
     *
     * @param string $cookieRaw
     *
     * @return array
     * @throws Exception
     */
    protected function parseCookie(string $cookieRaw): array
    {
        try {
            // Parse
            $cookieTmp = explode(";", $cookieRaw);
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

            $cookie = [];
            $cookie['name'] = $cookieTmp['name'];
            $cookie['value'] = isset($cookieTmp['value']) ? str_replace(' ', '+', $cookieTmp['value']) : null;
            $cookie['expires'] = null;

            if (array_key_exists('max-age', $cookieTmp)) {
                $cookieTmp['max-age'] = intval($cookieTmp['max-age']);

                $cookie['expires'] = $this->now();
                $dateInterval = new DateInterval(sprintf('PT%dS', abs($cookieTmp['max-age'])));

                if ($cookieTmp['max-age'] > 0) {
                    $cookie['expires'] = $cookie['expires']->add($dateInterval);
                }
                if ($cookieTmp['max-age'] < 0) {
                    $cookie['expires'] = $cookie['expires']->sub($dateInterval);
                }
            }
            if (array_key_exists('expires', $cookieTmp)) {
                $cookie['expires'] = new DateTime($cookieTmp['expires']);
            }

            $cookie['path'] = $cookieTmp['path'] ?? null;
            $cookie['domain'] = $cookieTmp['domain'] ?? null;
            if (null !== $cookie['domain']) {
                $cookie['domain'] = str_starts_with(
                    $cookie['domain'],
                    '.'
                ) ? $cookie['domain'] : '.' . $cookie['domain'];
            }
            $cookie['version'] = $cookieTmp['version'] ?? null;
            $cookie['httponly'] = array_key_exists('httponly', $cookieTmp);
            $cookie['secure'] = array_key_exists('secure', $cookieTmp);
            $cookie['samesite'] = $cookieTmp['samesite'] ?? null;
        } catch (Throwable $exception) {
            throw new HttpClientException(
                sprintf('Unable to parse cookie raw: "%s"', $cookieRaw),
                previous: $exception
            );
        }

        return $cookie;
    }
}