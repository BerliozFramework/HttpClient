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

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\AdapterInterface;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\HttpContext;
use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class FakeAdapter implements AdapterInterface
{
    public function __construct(private Closure $closure)
    {
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function getTimings(): ?Timings
    {
        return null;
    }

    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface
    {
        return ($this->closure)($request, $context);
    }
}