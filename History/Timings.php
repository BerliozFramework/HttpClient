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

use DateTimeImmutable;

/**
 * Class Timings.
 */
class Timings
{
    /**
     * Timings constructor.
     *
     * Order of timings:
     * - blocked
     * - dns
     * - connect
     * - ssl
     * - send
     * - wait
     * - receive
     * All timings in milliseconds.
     *
     * @param DateTimeImmutable $dateTime
     * @param float $send
     * @param float $wait
     * @param float $receive
     * @param float $total
     * @param float|null $blocked
     * @param float|null $dns
     * @param float|null $connect
     * @param float|null $ssl
     */
    public function __construct(
        protected DateTimeImmutable $dateTime,
        protected float $send,
        protected float $wait,
        protected float $receive,
        protected float $total,
        protected ?float $blocked = null,
        protected ?float $dns = null,
        protected ?float $connect = null,
        protected ?float $ssl = null,
    ) {
    }

    /**
     * Get date time.
     *
     * @return DateTimeImmutable
     */
    public function getDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    /**
     * Get send time.
     *
     * @return float
     */
    public function getSend(): float
    {
        return $this->send;
    }

    /**
     * Get wait time.
     *
     * @return float
     */
    public function getWait(): float
    {
        return $this->wait;
    }

    /**
     * Get receive time.
     *
     * @return float
     */
    public function getReceive(): float
    {
        return $this->receive;
    }

    /**
     * Get total time.
     *
     * @return float
     */
    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * Get blocked time.
     *
     * @return float|null
     */
    public function getBlocked(): ?float
    {
        return $this->blocked;
    }

    /**
     * Get DNS resolution time.
     *
     * @return float|null
     */
    public function getDns(): ?float
    {
        return $this->dns;
    }

    /**
     * Get connect time.
     *
     * @return float|null
     */
    public function getConnect(): ?float
    {
        return $this->connect;
    }

    /**
     * Get SSL exchange time.
     *
     * @return float|null
     */
    public function getSsl(): ?float
    {
        return $this->ssl;
    }
}