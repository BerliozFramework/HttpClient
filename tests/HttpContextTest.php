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

use Berlioz\Http\Client\HttpContext;
use PHPUnit\Framework\TestCase;

class HttpContextTest extends TestCase
{
    public function testMake_HttpContext()
    {
        $options = new HttpContext();

        $this->assertSame($options, HttpContext::make($options));
        $this->assertSame($options, HttpContext::make($options, new HttpContext(proxy: 'https://getberlioz.com:8080')));
    }

    public function testMake_array()
    {
        $options = HttpContext::make(['proxy' => $expected = 'https://getberlioz.com:8080']);

        $this->assertSame($expected, $options->proxy);

        $options2 = HttpContext::make(['ssl_ciphers' => $expected2 = 'foo'], $options);

        $this->assertSame($expected, $options2->proxy);
        $this->assertSame($expected2, $options2->ssl_ciphers);
    }

    public function testMake_null()
    {
        $options = HttpContext::make(['proxy' => 'https://getberlioz.com:8080']);
        $options2 = HttpContext::make(null, $options);

        $this->assertEquals($options, $options2);
        $this->assertNotSame($options, $options2);
    }
}
