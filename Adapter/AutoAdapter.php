<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Adapter;

use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\HttpContext;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AutoAdapter.
 *
 * Selects the best available transport adapter automatically: cURL is preferred
 * and the stream adapter is used as a fallback when the cURL extension is not
 * loaded. The resolved adapter is then used for every request.
 */
class AutoAdapter implements AdapterInterface
{
    private ?AdapterInterface $resolved = null;

    /**
     * AutoAdapter constructor.
     *
     * @param AdapterInterface|null $curl Adapter used when the cURL extension is loaded
     * @param AdapterInterface|null $stream Adapter used as fallback
     */
    public function __construct(
        private ?AdapterInterface $curl = null,
        private ?AdapterInterface $stream = null,
    ) {
    }

    public function __serialize(): array
    {
        return [
            'curl' => $this->curl,
            'stream' => $this->stream,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->curl = $data['curl'] ?? null;
        $this->stream = $data['stream'] ?? null;
        $this->resolved = null;
    }

    /**
     * Resolve the underlying adapter.
     *
     * cURL is preferred when the extension is loaded, otherwise the stream
     * adapter is used. The result is memoized to stay consistent between
     * {@see AutoAdapter::sendRequest()} and {@see AutoAdapter::getTimings()}.
     *
     * @return AdapterInterface
     */
    public function resolveAdapter(): AdapterInterface
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        if (extension_loaded('curl')) {
            return $this->resolved = $this->curl ??= new CurlAdapter();
        }

        return $this->resolved = $this->stream ??= new StreamAdapter();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->resolveAdapter()->getName();
    }

    /**
     * @inheritDoc
     */
    public function getTimings(): ?Timings
    {
        return $this->resolveAdapter()->getTimings();
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface
    {
        return $this->resolveAdapter()->sendRequest($request, $context);
    }
}
