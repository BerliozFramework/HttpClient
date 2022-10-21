<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testGet_userDefined()
    {
        $options = new Options();
        $options->value = 'foo';

        $this->assertEquals('foo', $options->value);
    }

    public function testGet_undefined()
    {
        $options = new Options();

        $this->assertNull($options->undefined);
    }

    public function testMake_Options()
    {
        $options = new Options();

        $this->assertSame($options, Options::make($options));
        $this->assertSame($options, Options::make($options, new Options(baseUri: 'https://getberlioz.com:8080')));
    }

    public function testMake_array()
    {
        $options = Options::make(['baseUri' => $expected = 'https://getberlioz.com:8080']);

        $this->assertSame($expected, $options->baseUri);

        $options2 = Options::make(['adapter' => $expected2 = 'foo'], $options);

        $this->assertSame($expected, $options2->baseUri);
        $this->assertSame($expected2, $options2->adapter);
    }

    public function testMake_null()
    {
        $options = Options::make(['baseUri' => 'https://getberlioz.com:8080']);
        $options2 = Options::make(null, $options);

        $this->assertEquals($options, $options2);
        $this->assertNotSame($options, $options2);
    }

    public function testMake_headers()
    {
        $options = Options::make(
            [
                'headers' => [
                    'Foo' => ['FooValue1', 'FooValue2'],
                    'Bar' => ['BarValue1', 'BarValue2'],
                    'Qux' => ['QuxValue1'],
                ]
            ]
        );
        $options2 = Options::make(['headers' => ['Bar' => ['NewBarValue']]], $options);

        $this->assertEquals(
            array_replace(
                $options->headers,
                [
                    'Bar' => ['NewBarValue'],
                ]
            ),
            $options2->headers
        );
    }
}
