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

namespace Berlioz\Http\Client;

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Har\HarGenerator;
use Berlioz\Http\Client\Har\HarHandler;
use Berlioz\Http\Client\History\History;
use ElGigi\HarParser\Entities\Log;
use ElGigi\HarParser\Exception\InvalidArgumentException;
use ElGigi\HarParser\Parser;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Session.
 */
class Session
{
    protected string $name;
    protected CookiesManager $cookies;
    protected History $history;

    /**
     * Create from HAR.
     *
     * @param Log $har
     *
     * @return static
     * @throws Exception\HttpClientException
     */
    public static function createFromHar(Log $har): static
    {
        $harParser = new HarHandler();

        return $harParser->handle($har);
    }

    /**
     * Create from HAR file.
     *
     * @param string $filename
     *
     * @return static
     * @throws Exception\HttpClientException
     * @throws InvalidArgumentException
     */
    public static function createFromHarFile(string $filename): static
    {
        $harParser = new Parser();

        return static::createFromHar($harParser->parse($filename, true));
    }

    public function __construct(?string $name = null)
    {
        $this->name = $name ?? uniqid();
        $this->cookies = new CookiesManager();
        $this->history = new History();
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'cookies' => $this->cookies,
            'history' => $this->history,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->name = $data['name'] ?? uniqid();
        $this->cookies = $data['cookies'] ?? new CookiesManager();
        $this->history = $data['history'] ?? new History();
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get cookies.
     *
     * @return CookiesManager
     */
    public function getCookies(): CookiesManager
    {
        return $this->cookies;
    }

    /**
     * Get history.
     *
     * @return History
     */
    public function getHistory(): History
    {
        return $this->history;
    }

    /**
     * Get last request.
     *
     * @return RequestInterface|null
     */
    public function getLastRequest(): ?RequestInterface
    {
        return $this->history->getLast()?->getRequest();
    }

    /**
     * Get last response.
     *
     * @return ResponseInterface|null
     */
    public function getLastResponse(): ?ResponseInterface
    {
        return $this->history->getLast()?->getResponse();
    }

    /**
     * Get HAR.
     *
     * @return Log
     * @throws Exception\HttpClientException
     */
    public function getHar(): Log
    {
        $generator = new HarGenerator();
        $generator->handle($this);

        return $generator->getHar();
    }

    /**
     * Write HAR file.
     *
     * @param resource $fp
     *
     * @return void
     * @throws Exception\HttpClientException
     */
    public function writeHar($fp): void
    {
        $generator = new HarGenerator();
        $generator->handle($this);
        $generator->writeHar($fp);
    }
}