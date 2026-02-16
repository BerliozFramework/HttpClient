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
use Berlioz\Http\Client\HttpContext;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface AdapterInterface.
 */
interface AdapterInterface extends ClientInterface
{
    /**
     * Get adapter name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get timings of last request.
     *
     * @return Timings|null
     */
    public function getTimings(): ?Timings;

    /**
     * @inheritDoc
     *
     * @param HttpContext|null $context
     */
    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface;
}