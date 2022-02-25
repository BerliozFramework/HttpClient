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

namespace Berlioz\Http\Client\Adapter;

use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Stream;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class AbstractAdapter.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    protected ?Timings $timings;

    /**
     * Get timings.
     *
     * @return Timings|null
     */
    public function getTimings(): ?Timings
    {
        return $this->timings ?? null;
    }

    /**
     * Get headers lines.
     *
     * @param Request $request
     *
     * @return array
     */
    protected function getHeadersLines(RequestInterface $request): array
    {
        $headers = [];

        // Host header
        $headers[] = sprintf('Host: %s', ($request->getHeader('host') ?: [$request->getUri()->getHost()])[0]);
        foreach ($request->getHeaders() as $name => $values) {
            if ('host' == strtolower($name)) {
                continue;
            }

            foreach ($values as $value) {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }

        return $headers;
    }

    /**
     * Create stream from content.
     *
     * @param string|null $content
     * @param array|null $contentEncodingHeader
     *
     * @return StreamInterface
     */
    protected function createStream(?string $content, ?array $contentEncodingHeader): StreamInterface
    {
        if (!is_resource($content) && !is_string($content) && !is_null($content)) {
            throw new InvalidArgumentException('Parameter must be a resource type, string or null value.');
        }

        $stream = new Stream();

        if (null === $content) {
            return $stream;
        }

        if (!empty($contentEncodingHeader)) {
            // Gzip
            if (in_array('gzip', $contentEncodingHeader)) {
                $content = gzdecode($content);
            }

            // Deflate
            if (in_array('deflate', $contentEncodingHeader)) {
                $content = gzinflate(trim($content));
            }
        }

        $stream->write($content);

        return $stream;
    }
}