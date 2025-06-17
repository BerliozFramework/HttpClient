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

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CurlAdapterTest extends TestCase
{
    public function testOptions()
    {
        $adapter = new CurlAdapter([
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_URL => 'https://getberlioz.com/',
        ]);
        $reflection = new ReflectionClass($adapter);
        $reflectionProperty = $reflection->getProperty('options');
        $reflectionProperty->setAccessible(true);
        $adapterOptions = $reflectionProperty->getValue($adapter);

        $this->assertSame(
            [
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
            ],
            $adapterOptions
        );
    }
}
