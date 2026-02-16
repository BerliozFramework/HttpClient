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

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Message\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class HistoryEntry.
 */
class HistoryEntry
{
    private array $cookies;

    public function __construct(
        CookiesManager $cookies,
        protected RequestInterface $request,
        protected ?ResponseInterface $response = null,
        protected ?Timings $timings = null,
    ) {
        $this->cookies = $cookies->getArrayCopy($this->request->getUri());
    }

    public function __serialize(): array
    {
        return [
            'cookies' => $this->cookies,
            'request' => $this->request->withBody(new Stream()),
            'request_body' => $this->request->getBody()->getContents(),
            'response' => $this->response?->withBody(new Stream()),
            'response_body' => $this->response?->getBody()->getContents(),
            'timings' => $this->timings,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->cookies = $data['cookies'];
        $this->request = $data['request']?->withBody(new Stream\MemoryStream($data['request_body']));
        $this->response = $data['response']?->withBody(new Stream\MemoryStream($data['response_body']));
        $this->timings = $data['timings'];
    }

    /**
     * Get cookies at request moment.
     *
     * @return array
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get timings.
     *
     * @return Timings|null
     */
    public function getTimings(): ?Timings
    {
        return $this->timings;
    }

    /**
     * Get request.
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get response.
     *
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}