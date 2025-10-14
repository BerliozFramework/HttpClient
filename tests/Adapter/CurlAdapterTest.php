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
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class CurlAdapterTest extends TestCase
{
    public function testOptions()
    {
        $adapter = new class([
            CURLOPT_TIMEOUT => 20,
            CURLOPT_URL => 'https://gethectororm.com/',
        ]) extends CurlAdapter {
            public function prepareCurlOptions(
                RequestInterface $request,
                array $options = [],
                ?HttpContext $context = null,
            ): array {
                return parent::prepareCurlOptions($request, $options, $context);
            }
        };

        $preparedOptions = $adapter->prepareCurlOptions(
            new ServerRequest(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            [
                CURLOPT_CONNECTTIMEOUT => 10,
            ],
            new HttpContext(ssl_verify_host: false),
        );

        $expected = [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        $this->assertEquals(
            $expected,
            array_intersect_key($preparedOptions, $expected),
        );
    }
}
