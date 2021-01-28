<?php

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
