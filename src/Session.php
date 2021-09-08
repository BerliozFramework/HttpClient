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
use Berlioz\Http\Client\Har\HarParser;
use Berlioz\Http\Client\History\History;
use ElGigi\HarParser\Entities\Log;

/**
 * Class Session.
 */
class Session
{
    protected string $name;
    protected CookiesManager $cookies;
    protected History $history;

    public static function createFromHar(Log $har): static
    {
        $harParser = new HarParser();

        return $harParser->handle($har);
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
     * Get HAR.
     *
     * @return Log
     */
    public function getHar(): Log
    {
        $generator = new HarGenerator();

        return $generator->handle($this);
    }
}