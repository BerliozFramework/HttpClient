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

namespace Berlioz\Http\Client\Tests\Cookies;

use Berlioz\Http\Client\Cookies\Cookie;
use Berlioz\Http\Message\Uri;
use DateInterval;
use DateTime;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{
    public function testParseMaxAge()
    {
        $dateTime = new DateTime();
        $cookie = Cookie::parse('test=value; max-age=100; Domain=getberlioz.com');

        $this->assertEquals(
            $cookie->getExpires()->format('Y-m-d H:i:s'),
            $dateTime->add(new DateInterval('PT100S'))->format('Y-m-d H:i:s')
        );
    }

    public function testParseNegativeMaxAge()
    {
        $dateTime = new DateTime();
        $cookie = Cookie::parse('test=value; max-age=-100; Domain=getberlioz.com');

        $this->assertEquals(
            $cookie->getExpires()->format('Y-m-d H:i:s'),
            $dateTime->sub(new DateInterval('PT100S'))->format('Y-m-d H:i:s')
        );
    }

    public function testIsSame()
    {
        $cookie = Cookie::parse('foo=value; domain=getberlioz.com');

        $this->assertTrue($cookie->isSame($cookie));
        $this->assertTrue(
            $cookie->isSame(
                Cookie::parse(
                    'foo=value2',
                    Uri::createFromString('https://getberlioz.com')
                )
            )
        );
        $this->assertFalse($cookie->isSame(Cookie::parse('bar=value; domain=getberlioz.com')));
        $this->assertFalse($cookie->isSame(Cookie::parse('foo=value; domain=getberlioz.com; path=/qux/')));
        $this->assertFalse($cookie->isSame(Cookie::parse('foo=value; domain=gethectororm.com')));
        $this->assertTrue($cookie->isSame(Cookie::parse('foo=value; domain=getberlioz.com; version=qux')));
    }

    public function testUpdate()
    {
        $cookie = Cookie::parse('foo=value; domain=getberlioz.com; path=/qux/');

        $this->assertFalse($cookie->update(Cookie::parse('bar=value; domain=getberlioz.com')));
        $this->assertFalse($cookie->update(Cookie::parse('foo=value2; domain=getberlioz.com')));

        $this->assertTrue($cookie->update(Cookie::parse('foo=value2; domain=getberlioz.com; path=/qux/')));
        $this->assertEquals('value2', $cookie->getValue());
    }
}
