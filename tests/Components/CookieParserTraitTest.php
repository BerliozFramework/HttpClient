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

use Berlioz\Http\Client\Components\CookieParserTrait;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class CookieParserTraitTest extends TestCase
{
    public function cookieProvider(): array
    {
        return [
            [
                'raw' => 'foo=bar',
                'expected' => [
                    'name' => 'foo',
                    'value' => 'bar',
                    'expires' => null,
                    'path' => null,
                    'domain' => null,
                    'version' => null,
                    'httponly' => false,
                    'secure' => false,
                    'samesite' => null,
                ],
            ],
            [
                'raw' => 'foo=bar; max-age=100',
                'expected' => [
                    'name' => 'foo',
                    'value' => 'bar',
                    'expires' => (new DateTime('2022-03-18 07:15:30'))->add(new DateInterval('PT100S')),
                    'path' => null,
                    'domain' => null,
                    'version' => null,
                    'httponly' => false,
                    'secure' => false,
                    'samesite' => null,
                ],
            ]
        ];
    }

    /**
     * @dataProvider cookieProvider
     */
    public function testParseCookie(string $raw, array $expected)
    {
        $cookieParser = new class {
            use CookieParserTrait {
                parseCookie as public;
            }

            private function now(): DateTimeInterface
            {
                return new DateTimeImmutable('2022-03-18 07:15:30');
            }
        };

        $result = $cookieParser->parseCookie($raw);
        foreach ($result as $key => $value) {
            $this->assertArrayHasKey($key, $expected);

            $this->assertEquals(
                $expected[$key],
                $value
            );
        }
    }
}
