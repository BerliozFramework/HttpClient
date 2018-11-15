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

namespace Berlioz\Http\Client\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class HttpException.
 *
 * @package Berlioz\Http\Client\Exception
 */
class HttpException extends HttpClientException
{
    /** @var \Psr\Http\Message\RequestInterface */
    private $request;
    /** @var null|\Psr\Http\Message\ResponseInterface */
    private $response;

    /**
     * HttpException constructor.
     *
     * @param string                                   $message
     * @param \Psr\Http\Message\RequestInterface       $request
     * @param null|\Psr\Http\Message\ResponseInterface $response
     * @param null|\Throwable                          $previous
     */
    public function __construct(string $message, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Get request.
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}