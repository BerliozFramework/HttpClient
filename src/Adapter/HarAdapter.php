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

use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Har\HarHandler;
use Berlioz\Http\Client\History\Timings;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Message\Uri;
use ElGigi\HarParser\Entities\Entry;
use ElGigi\HarParser\Entities\Log;
use ElGigi\HarParser\Parser;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HarAdapter implements AdapterInterface
{
    protected Log $har;
    protected int $stack = -1;
    protected array $usedEntries = [];
    protected HarHandler $handler;
    protected ?Timings $timings = null;

    public function __construct(
        Log|string $har,
        protected bool $strict = false
    ) {
        is_string($har) && $har = (new Parser())->parse($har, contentIsFile: true);
        $this->har = $har;
        $this->handler = new HarHandler();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'har';
    }

    /**
     * @inheritDoc
     */
    public function getTimings(): ?Timings
    {
        return $this->timings;
    }

    /**
     * Get next entry.
     *
     * @param RequestInterface $request
     *
     * @return Entry
     * @throws HttpClientException
     */
    private function getNextEntry(RequestInterface $request): Entry
    {
        $requestUri = $request->getUri()->withFragment('');

        // Strict, get the next entry into stack
        if ($this->strict) {
            if (null === ($entry = $this->har->getEntry(++$this->stack))) {
                throw new HttpClientException('HAR stack empty');
            }

            $historyUri = Uri::create($entry->getRequest()->getUrl())->withFragment('');

            // Different HTTP method or URI
            if (strtolower($entry->getRequest()->getMethod()) != strtolower($request->getMethod()) ||
                (string)$historyUri != (string)$requestUri) {
                throw new HttpClientException(
                    sprintf(
                        'Url not similar to the HAR stack (expected: "%s" ; actual: "%s").',
                        $requestUri,
                        $historyUri
                    )
                );
            }

            return $entry;
        }

        // Not strict, get the first entry whose correspond to the request
        foreach ($this->har->getEntries() as $key => $entry) {
            if (in_array($key, $this->usedEntries)) {
                continue;
            }

            $historyUri = Uri::create($entry->getRequest()->getUrl())->withFragment('');

            // Same HTTP method
            if (strtolower($entry->getRequest()->getMethod()) == strtolower($request->getMethod())) {
                // Same URI
                if ((string)$historyUri == (string)$requestUri) {
                    $this->usedEntries[] = $key;

                    return $entry;
                }
            }
        }

        throw new HttpClientException(sprintf('Url not found in the HAR stack (expected: "%s").', $requestUri));
    }

    /**
     * @inheritDoc
     * @throws HttpClientException
     */
    public function sendRequest(RequestInterface $request, ?HttpContext $context = null): ResponseInterface
    {
        $entry = $this->getNextEntry($request);
        $this->timings = $this->handler->getTimings($entry);

        return $this->handler->getHttpResponse($entry->getResponse());
    }
}