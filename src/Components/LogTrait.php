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

use Berlioz\Http\Client\Client;
use Berlioz\Http\Client\Exception\HttpClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Trait LogTrait.
 *
 * @package Berlioz\Http\Client\Components
 */
trait LogTrait
{
    use LoggerAwareTrait;
    use LogFormatterTrait;
    /** @var resource|false File log pointer */
    private $logFp;

    /**
     * Log request and response.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @throws \Berlioz\Http\Client\Exception\HttpClientException if unable to write logs
     */
    protected function log(RequestInterface $request, ResponseInterface $response = null)
    {
        // Logger
        if (!empty($this->logger)) {
            $logLevel = 'info';
            if (!$response || intval(substr((string)$response->getStatusCode(), 0, 1)) != 2) {
                $logLevel = 'warning';
            }

            $this->logger->log(
                $logLevel,
                sprintf(
                    '%s / Request %s to %s, response %s',
                    __METHOD__,
                    $request->getMethod(),
                    $request->getUri(),
                    $response ?
                        sprintf(
                            '%d (%s)',
                            $response->getStatusCode(),
                            $response->getReasonPhrase()
                        ) :
                        'NONE'
                )
            );
        }

        // Log all detail to file ?
        if (!empty($this->options['logFile'])) {
            if (is_resource($this->logFp) || is_resource($this->logFp = @fopen($this->options['logFile'], 'a'))) {
                $str =
                    '###### ' . date('c') . ' ######' . PHP_EOL . PHP_EOL .
                    '>>>>>> Request' . PHP_EOL . PHP_EOL .
                    $this->formatRequestLog($request) . PHP_EOL . PHP_EOL .
                    '<<<<<< Response' . PHP_EOL . PHP_EOL .
                    $this->formatResponseLog($response) . PHP_EOL . PHP_EOL .
                    PHP_EOL . PHP_EOL;

                // Write into logs
                if (fwrite($this->logFp, $str) === false) {
                    throw new HttpClientException('Unable to write logs');
                }
            }
        }
    }

    /**
     * Close log resource.
     *
     * @return static
     * @throws \Berlioz\Http\Client\Exception\HttpClientException
     */
    public function closeLogResource(): Client
    {
        if (!is_resource($this->logFp)) {
            return $this;
        }

        // Close resource
        if (!fclose($this->logFp)) {
            throw new HttpClientException('Unable to close log file pointer');
        }

        $this->logFp = null;

        return $this;
    }
}