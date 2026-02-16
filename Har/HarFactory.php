<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Har;

use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Session;
use ElGigi\HarParser\Entities\Log;
use ElGigi\HarParser\Exception\InvalidArgumentException;
use ElGigi\HarParser\Parser;

class HarFactory
{
    /**
     * Create HAR Log class from session.
     *
     * @param Session $session
     *
     * @return Log
     * @throws HttpClientException
     */
    public static function createHarFromSession(Session $session): Log
    {
        $generator = new HarGenerator();
        $generator->handle($session);

        return $generator->getHar();
    }

    /**
     * Write HAR file.
     *
     * @param Session $session
     * @param resource $dst
     *
     * @return void
     * @throws HttpClientException
     */
    public static function writeHarFromSession(Session $session, $dst): void
    {
        $generator = new HarGenerator();
        $generator->handle($session);
        $generator->writeHar($dst);
    }

    /**
     * Create session from HAR Log class.
     *
     * @param Log $har
     *
     * @return Session
     * @throws HttpClientException
     */
    public static function createSession(Log $har): Session
    {
        $harParser = new HarHandler();

        return $harParser->handle($har);
    }

    /**
     * Create session from HAR file.
     *
     * @param string $filename
     *
     * @return Session
     * @throws HttpClientException
     * @throws InvalidArgumentException
     */
    public static function createSessionFromFile(string $filename): Session
    {
        $harParser = new Parser();

        return static::createSession($harParser->parse($filename, true));
    }
}
