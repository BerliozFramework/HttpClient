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

use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Stream;
use Berlioz\Http\Message\Uri;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Trait RequestFactoryTrait.
 *
 * @package Berlioz\Http\Client\Components
 */
trait RequestFactoryTrait
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens while processing the request.
     */
    abstract public function sendRequest(RequestInterface $request): ResponseInterface;

    /**
     * Construct request.
     *
     * @param string $method Http method
     * @param string|UriInterface $uri Uri
     * @param array|null $parameters Get parameters
     * @param string|StreamInterface|null $body Body
     * @param array $options Options
     *
     * @option array "headers" Headers of request
     *
     * @return RequestInterface
     */
    public function constructRequest(
        string $method,
        $uri,
        ?array $parameters = null,
        $body = null,
        array $options = []
    ): RequestInterface {
        // URI
        if (!$uri instanceof UriInterface) {
            $uri = Uri::createFromString($uri);
        }

        // Parameters
        if (null !== $parameters) {
            $uri = $uri->withQuery(http_build_query($parameters));
        }

        // Create request
        $request = new Request($method, $uri);

        // Body
        if (null !== $body) {
            $stream = $body;

            if (!($body instanceof StreamInterface)) {
                $stream = new Stream();
                $stream->write($body);
            }

            $request = $request->withBody($stream);
        }

        // Headers
        if (!empty($options['headers'])) {
            $request = $request->withHeaders((array)$options['headers']);
        }

        return $request;
    }

    /**
     * Request.
     *
     * @param string $method Http method
     * @param string|UriInterface $uri Uri
     * @param array|null $parameters Get parameters
     * @param string|StreamInterface|null $body Body
     * @param array $options Options
     *
     * @option array "headers" Headers of request
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function request(
        string $method,
        $uri,
        array $parameters = null,
        $body = null,
        array $options = []
    ): ResponseInterface {
        $request = $this->constructRequest($method, $uri, $parameters, $body, $options);

        return $this->sendRequest($request);
    }

    /**
     * Get request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param array|null $parameters Get parameters
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function get($uri, array $parameters = null, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $parameters, null, $options);
    }

    /**
     * Post request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param string|StreamInterface|null $body Body of request
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function post($uri, $body = null, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, null, $body, $options);
    }

    /**
     * Patch request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param string|StreamInterface|null $body Body of request
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function patch($uri, $body = null, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, null, $body, $options);
    }

    /**
     * Put request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param string|StreamInterface|null $body Body of request
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function put($uri, $body = null, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, null, $body, $options);
    }

    /**
     * Delete request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function delete($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $parameters, null, $options);
    }

    /**
     * Options request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function options($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('OPTIONS', $uri, $parameters, null, $options);
    }

    /**
     * Head request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function head($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, $parameters, null, $options);
    }

    /**
     * Connect request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param string|StreamInterface $body Body of request
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function connect($uri, $body, array $options = []): ResponseInterface
    {
        return $this->request('CONNECT', $uri, null, $body, $options);
    }

    /**
     * Trace request.
     *
     * @param string|UriInterface $uri Uri of request
     * @param array $parameters Get parameters
     * @param array $options Options
     *
     * @return ResponseInterface
     * @throws ClientExceptionInterface If an error happens during processing the request.
     */
    public function trace($uri, array $parameters = [], array $options = []): ResponseInterface
    {
        return $this->request('TRACE', $uri, $parameters, null, $options);
    }
}