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

namespace Berlioz\Http\Client\Stream;

use Berlioz\Http\Message\Stream;

class InflateStream extends Stream\AbstractStream
{
    /**
     * Stream constructor.
     *
     * @param Stream $stream
     */
    public function __construct(Stream $stream)
    {
        $this->fp = $stream->fp;
        stream_filter_append($stream->fp, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 15 + 32]);
    }
}