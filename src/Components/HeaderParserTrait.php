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

/**
 * Trait HeaderParserTrait.
 */
trait HeaderParserTrait
{
    /**
     * Parse headers.
     *
     * @param string $headers Raw headers
     * @param string|null $protocolVersion
     * @param mixed|null $statusCode
     * @param string|null $reasonPhrase Reason phrase returned by reference
     *
     * @return array
     */
    protected function parseHeaders(
        string $headers,
        ?string &$protocolVersion = null,
        ?int &$statusCode = null,
        ?string &$reasonPhrase = null
    ): array {
        $finalHeaders = [];

        // Explode raw headers
        $headers = explode("\r\n", $headers);
        // Get and remove first header line
        $firstHeader = array_shift($headers);
        // Explode headers
        $headers = array_map(
            function ($value) {
                $value = explode(":", $value, 2);
                $value = array_map('trim', $value);

                return array_filter($value);
            },
            $headers
        );
        $headers = array_filter($headers);

        foreach ($headers as $header) {
            $header[0] = ucwords($header[0], '-');
            $header[1] = $header[1] ?? null;

            if (null === $header[1]) {
                continue;
            }

            if (!isset($finalHeaders[$header[0]])) {
                $finalHeaders[$header[0]] = [$header[1]];
                continue;
            }

            $finalHeaders[$header[0]][] = $header[1];
        }

        // Treat first header
        $protocolVersion = null;
        $statusCode = null;
        $reasonPhrase = null;
        $matches = [];
        if (preg_match("#^HTTP/([0-9.]+) ([0-9]+) (.*)$#i", $firstHeader, $matches) === 1) {
            $protocolVersion = $matches[1];
            $statusCode = (int)$matches[2];
            $reasonPhrase = $matches[3];
            if (false === mb_check_encoding($reasonPhrase, 'utf-8')) {
                $reasonPhrase = utf8_encode($reasonPhrase);
            }
        }

        return $finalHeaders;
    }
}