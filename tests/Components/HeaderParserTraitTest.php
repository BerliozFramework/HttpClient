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

namespace Berlioz\Http\Client\Tests\Components;

use Berlioz\Http\Client\Components\HeaderParserTrait;
use PHPUnit\Framework\TestCase;

class HeaderParserTraitTest extends TestCase
{
    public function headerProvider(): array
    {
        return [
            [
                'headers' => "HTTP/1.1 200 OK\r\nContent-Encoding: utf-8\r\nx-foo-bar: qux",
                'expected' => [
                    'protocolVersion' => '1.1',
                    'statusCode' => '200',
                    'reasonPhrase' => 'OK',
                    'headers' => [
                        'Content-Encoding' => ['utf-8'],
                        'X-Foo-Bar' => ['qux'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider headerProvider
     */
    public function testParseHeaders(string $headers, array $expected)
    {
        $cookieParser = new class {
            use HeaderParserTrait {
                parseHeaders as public;
            }
        };

        $result = $cookieParser->parseHeaders($headers, $protocolVersion, $statusCode, $reasonPhrase);

        $this->assertEquals($expected['protocolVersion'], $protocolVersion);
        $this->assertEquals($expected['statusCode'], $statusCode);
        $this->assertEquals($expected['reasonPhrase'], $reasonPhrase);
        $this->assertSame($expected['headers'], $result);
    }
}
