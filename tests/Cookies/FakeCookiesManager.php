<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests\Cookies;

use Berlioz\Http\Client\Cookies\Cookie;
use Berlioz\Http\Client\Cookies\CookiesManager;

class FakeCookiesManager extends CookiesManager
{
    public function addCookie(Cookie $cookie)
    {
        $this->cookies[] = $cookie;
    }
}