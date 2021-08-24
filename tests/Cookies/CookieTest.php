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
}
