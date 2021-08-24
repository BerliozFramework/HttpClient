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

namespace Berlioz\Http\Client\History;

use ArrayIterator;
use Berlioz\Http\Client\Cookies\CookiesManager;
use Countable;
use Generator;
use IteratorAggregate;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class History.
 */
class History implements Countable, IteratorAggregate
{
    /** @var HistoryEntry[] */
    protected array $history = [];

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->history);
    }

    /**
     * Clear history.
     */
    public function clear(): void
    {
        $this->history = [];
    }

    /**
     * Add request and response to history.
     *
     * @param CookiesManager $cookies
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     * @param Timings|null $timings
     */
    public function add(
        CookiesManager $cookies,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?Timings $timings = null
    ): void {
        $this->addEntry(new HistoryEntry($cookies, $request, $response, $timings));
    }

    /**
     * Add entry to history.
     *
     * @param HistoryEntry $entry
     */
    public function addEntry(HistoryEntry $entry): void
    {
        $this->history[] = $entry;
    }

    /**
     * Get complete history.
     *
     * @return Generator
     */
    public function getAll(): Generator
    {
        yield from $this->history;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->history);
    }

    /**
     * Get entry.
     *
     * @param int $index
     *
     * @return HistoryEntry|null
     */
    public function get(int $index = -1): ?HistoryEntry
    {
        $history = array_slice($this->history, $index, 1);

        return reset($history) ?: null;
    }

    /**
     * Get first entry.
     *
     * @return HistoryEntry|null
     */
    public function getFirst(): ?HistoryEntry
    {
        return reset($this->history) ?: null;
    }

    /**
     * Get last entry.
     *
     * @return HistoryEntry|null
     */
    public function getLast(): ?HistoryEntry
    {
        return end($this->history) ?: null;
    }
}