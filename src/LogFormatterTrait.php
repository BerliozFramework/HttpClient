<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait LogFormatterTrait.
 *
 * @package Berlioz\Http\Client
 */
trait LogFormatterTrait
{
    /**
     * Format request log.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     *
     * @return string
     */
    protected function formatRequestLog(RequestInterface $request): string
    {
        // Main header
        $str =
            sprintf(
                '%s %s HTTP/%s' . PHP_EOL,
                $request->getMethod(),
                $request->getUri()->getPath() . (!empty(
                $request->getUri()->getQuery()
                ) ? '?' . $request->getUri()->getQuery() : ''),
                $request->getProtocolVersion()
            );

        // Host
        $str .= sprintf('Host: %s', $request->getUri()->getHost());
        if ($request->getUri()->getPort()) {
            $str .= sprintf(':%d', $request->getUri()->getPort());
        }
        $str .= PHP_EOL;

        // Message
        $str .= $this->formatMessageLog($request);

        return $str;
    }

    /**
     * Format response log.
     *
     * @param \Psr\Http\Message\ResponseInterface|null $response
     *
     * @return string
     */
    protected function formatResponseLog(?ResponseInterface $response): string
    {
        if (null === $response) {
            return 'No response';
        }

        // Main header
        $str =
            sprintf(
                'HTTP/%s %s %s' . PHP_EOL,
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            );

        // Message
        $str .= $this->formatMessageLog($response);

        return $str;
    }

    /**
     * Format message log.
     *
     * @param \Psr\Http\Message\MessageInterface $message
     *
     * @return string
     */
    protected function formatMessageLog(MessageInterface $message): string
    {
        $str = '';

        // Headers
        foreach ($message->getHeaders() as $key => $values) {
            foreach ($values as $value) {
                $str .= sprintf('%s: %s' . PHP_EOL, $key, $value);
            }
        }

        // Body
        $str .=
            PHP_EOL .
            ($message->getBody()->getSize() > 0 ? $message->getBody() : 'Empty body');

        return $str;
    }
}