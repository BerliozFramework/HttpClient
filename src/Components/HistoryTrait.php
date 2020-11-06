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

namespace Berlioz\Http\Client\Components;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HistoryTrait.
 *
 * @package Berlioz\Http\Client\Components
 */
trait HistoryTrait
{
    /** @var MessageInterface[][] History */
    protected $history = [];

    /**
     * Add history.
     *
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return static
     */
    protected function addHistory(RequestInterface $request, ?ResponseInterface $response)
    {
        $this->history[] = [
            'request' => $request,
            'response' => $response,
        ];

        return $this;
    }

    /**
     * Get history.
     *
     * @param int|null $index History index (null for all, -1 for last)
     *
     * @return false|MessageInterface[][]|MessageInterface[]
     */
    public function getHistory(?int $index = null)
    {
        if (null === $index) {
            return $this->history;
        }

        $history = array_slice($this->history, $index, 1);

        return reset($history);
    }

    /**
     * Clear history.
     *
     * @return static
     */
    public function clearHistory()
    {
        $this->history = [];

        return $this;
    }
}