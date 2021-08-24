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

namespace Berlioz\Http\Client\Tests;

use Symfony\Component\Process\Process;

trait PhpServerTrait
{
    /** @var Process */
    protected static Process $process;

    public static function setUpBeforeClass(): void
    {
        self::$process = new Process(['php', '-S', 'localhost:8080', '-t', realpath(__DIR__ . '/server')]);
        self::$process->start();
        usleep(100000);
    }

    public static function tearDownAfterClass(): void
    {
        self::$process->stop();
    }
}