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
use Throwable;

/**
 * Class HttpException.
 *
 * @package Berlioz\Http\Client\Exception
 */
class HttpException extends HttpClientException
{
    /** @var RequestInterface */
    private $request;
    /** @var null|ResponseInterface */
    private $response;

    /**
     * HttpException constructor.
     *
     * @param string $message
     * @param RequestInterface $request
     * @param null|ResponseInterface $response
     * @param null|Throwable $previous
     */
    public function __construct(
        string $message,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?Throwable $previous = null
    ) {
        $code = 0;
        if ($response) {
            $code = $response->getStatusCode();
        }

        parent::__construct($message, $code, $previous);
        $this->request = $request;
        $this->response = $response;
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