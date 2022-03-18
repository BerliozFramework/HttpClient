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

namespace Berlioz\Http\Client\Har;

use Berlioz\Http\Client\Components\CookieParserTrait;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\History\HistoryEntry;
use Berlioz\Http\Client\Session;
use Berlioz\Http\Message\Uri;
use DateTimeImmutable;
use ElGigi\HarParser\Builder as HarBuilder;
use ElGigi\HarParser\Entities as Har;
use ElGigi\HarParser\Exception\InvalidArgumentException;
use Exception;
use Generator;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HarGenerator
{
    use CookieParserTrait;

    private Session $session;

    public function __construct()
    {
    }

    /**
     * Handle history.
     *
     * @param Session $session
     *
     * @return void
     */
    public function handle(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * Get HAR object.
     *
     * @return Har\Log
     * @throws HttpClientException
     */
    public function getHar(): Har\Log
    {
        $this->session ?? throw new HttpClientException('No session handled');

        try {
            $builder = new HarBuilder\Builder();
            $builder->setCreator($this->getCreator());
            $builder->addEntry(...iterator_to_array($this->getEntries(), false));

            return $builder->build();
        } catch (Throwable $throwable) {
            throw new HttpClientException('Unable to generate HAR file', previous: $throwable);
        }
    }

    /**
     * Write HAR file.
     *
     * @param resource $fp
     *
     * @return void
     * @throws HttpClientException
     */
    public function writeHar($fp): void
    {
        $this->session ?? throw new HttpClientException('No session handled');

        try {
            $builder = new HarBuilder\BuilderStream($fp);
            $builder->setCreator($this->getCreator());

            foreach ($this->getEntries() as $entry) {
                $builder->addEntry($entry);
            }
        } catch (Throwable $throwable) {
            throw new HttpClientException('Unable to write HAR file', previous: $throwable);
        }
    }

    /**
     * Get creator.
     *
     * @return Har\Creator
     */
    protected function getCreator(): Har\Creator
    {
        return new Har\Creator(name: 'Berlioz HTTP Client', version: '2', comment: 'https://getberlioz.com');
    }

    /**
     * Get entries.
     *
     * @return Generator
     * @throws InvalidArgumentException
     */
    protected function getEntries(): Generator
    {
        foreach ($this->session->getHistory() as $historyEntry) {
            yield $this->getEntry($historyEntry);
        }
    }

    /**
     * Get entry from history entry.
     *
     * @param HistoryEntry $entry
     *
     * @return Har\Entry
     * @throws InvalidArgumentException
     */
    protected function getEntry(HistoryEntry $entry): Har\Entry
    {
        $request = $this->getRequest($entry->getRequest(), $entry->getCookies());
        $response = $entry->getResponse() ? $this->getResponse(
            $entry->getResponse(),
            $entry->getRequest()->getUri()
        ) : null;

        return new Har\Entry(
            pageref: null,
            startedDateTime: $entry->getTimings()?->getDateTime() ?? new DateTimeImmutable(),
            time: $entry->getTimings()?->getTotal() ?? -1,
            request: $request,
            response: $response ?? null,
            cache: [],
            timings: new Har\Timings(
                blocked: $entry->getTimings()?->getBlocked() ?? null,
                dns: $entry->getTimings()?->getDns() ?? null,
                connect: $entry->getTimings()?->getConnect() ?? null,
                send: $entry->getTimings()?->getSend() ?? 0,
                wait: $entry->getTimings()?->getWait() ?? 0,
                receive: $entry->getTimings()?->getReceive() ?? 0,
                ssl: $entry->getTimings()?->getSsl() ?? null,
            ),
            serverIPAddress: null,
        );
    }

    /**
     * Get HAR response from ResponseInterface.
     *
     * @param RequestInterface $request
     * @param array $cookies
     *
     * @return Har\Request
     * @throws InvalidArgumentException
     */
    protected function getRequest(RequestInterface $request, array $cookies): Har\Request
    {
        if ($request->getBody()->getSize() > 0) {
            $postData = new Har\PostData(
                mimeType: $request->getHeader('Content-Type')[0] ?? 'text/plain',
                params: [],
                text: $request->getBody()->getContents(),
            );
        }

        return new Har\Request(
            method: $request->getMethod(),
            url: (string)$request->getUri(),
            httpVersion: $request->getProtocolVersion(),
            cookies: array_map(
                function ($cookie) {
                    return Har\Cookie::load($cookie);
                },
                $cookies
            ),
            headers: $this->getHeaders($request),
            queryString: array_map(
                function ($value) {
                    $value = explode('=', $value, 2);

                    return new Har\QueryString($value[0], urldecode($value[1] ?? ''));
                },
                explode(ini_get('arg_separator.output'), $request->getUri()->getQuery())
            ),
            postData: $postData ?? null,
            headersSize: -1,
            bodySize: $request->getBody()->getSize(),
        );
    }

    /**
     * Get HAR response from ResponseInterface.
     *
     * @param ResponseInterface $response
     * @param Uri $uri
     *
     * @return Har\Response
     * @throws InvalidArgumentException
     * @throws Exception
     */
    protected function getResponse(ResponseInterface $response, Uri $uri): Har\Response
    {
        $cookies = array_map(
            function ($raw) use ($uri) {
                $cookieData = $this->parseCookie($raw);

                return new Har\Cookie(
                    name: $cookieData['name'],
                    value: $cookieData['value'],
                    path: $cookieData['path'],
                    domain: $cookieData['domain'] ?? $uri->getHost(),
                    expires: $cookieData['expires'],
                    httpOnly: $cookieData['httponly'],
                    secure: $cookieData['secure'],
                    sameSite: $cookieData['samesite'],
                );
            },
            $response->getHeader('Set-Cookie')
        );

        // Recopy stream content into memory
        $stream = $response->getBody();
        if ($stream->isReadable() && $stream->getSize() > 0) {
            $tmp = fopen('php://memory', 'w+');
            $stream->seek(0);
            while (!$stream->eof()) {
                fwrite($tmp, $stream->read(8192));
            }
        }

        $content = new Har\Content(
            size: $response->getBody()->getSize(),
            compression: null,
            mimeType: $response->getHeader('Content-Type')[0] ?? 'text/plain',
            text: $tmp ?? null,
        );

        return new Har\Response(
            status: $response->getStatusCode(),
            statusText: $response->getReasonPhrase(),
            httpVersion: $response->getProtocolVersion(),
            cookies: $cookies,
            headers: $this->getHeaders($response),
            content: $content,
            redirectURL: $response->getHeader('Location')[0] ?? '',
            headersSize: -1,
            bodySize: $response->getBody()->getSize(),
        );
    }

    /**
     * Get headers.
     *
     * @param MessageInterface $message
     *
     * @return array
     */
    protected function getHeaders(MessageInterface $message): array
    {
        $final = [];

        foreach ($message->getHeaders() as $name => $headers) {
            foreach ($headers as $value) {
                $final[] = new Har\Header(name: $name, value: $value);
            }
        }

        return $final;
    }
}