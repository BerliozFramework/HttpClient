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
use Berlioz\Http\Client\Har\HarFactory;
use Berlioz\Http\Client\History\History;
use ElGigi\HarParser\Entities\Log;
use ElGigi\HarParser\Exception\InvalidArgumentException;
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

    public function __construct(
        ?string $name = null,
        int|float $historySize = INF,
        ?CookiesManager $cookies = null,
    ) {
        $this->name = $name ?? uniqid();
        $this->cookies = $cookies ?? new CookiesManager();
        $this->history = new History($historySize);
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'cookies' => iterator_to_array($this->cookies),
            'history' => $this->history,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->name = $data['name'] ?? uniqid();
        // Older sessions stored a manager with cookies whose original scope cannot be recovered.
        $this->cookies = new CookiesManager(cookies: is_array($data['cookies'] ?? null) ? $data['cookies'] : []);
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
     * Create from HAR.
     *
     * @param Log $har
     *
     * @return static
     * @throws Exception\HttpClientException
     * @deprecated 2.4.0 No longer used by internal code and removed in next releases.
     * @see HarFactory::createSession()
     */
    public static function createFromHar(Log $har): static
    {
        return HarFactory::createSession($har);
    }

    /**
     * Create from HAR file.
     *
     * @param string $filename
     *
     * @return static
     * @throws Exception\HttpClientException
     * @throws InvalidArgumentException
     * @deprecated 2.4.0 No longer used by internal code and removed in next releases.
     * @see HarFactory::createSessionFromFile()
     */
    public static function createFromHarFile(string $filename): static
    {
        return HarFactory::createSessionFromFile($filename);
    }

    /**
     * Get HAR.
     *
     * @return Log
     * @throws Exception\HttpClientException
     * @deprecated 2.4.0 No longer used by internal code and removed in next releases.
     * @see HarFactory::createHarFromSession()
     */
    public function getHar(): Log
    {
        return HarFactory::createHarFromSession($this);
    }

    /**
     * Write HAR file.
     *
     * @param resource $fp
     *
     * @return void
     * @throws Exception\HttpClientException
     * @deprecated 2.4.0 No longer used by internal code and removed in next releases.
     * @see HarFactory::writeHarFromSession()
     */
    public function writeHar($fp): void
    {
        HarFactory::writeHarFromSession($this, $fp);
    }
}
