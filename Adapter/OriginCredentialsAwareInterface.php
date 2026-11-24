<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Adapter;

/**
 * Interface OriginCredentialsAwareInterface.
 *
 * Adapter holding its own origin credentials (authentication, client certificate, cookies...).
 * The client uses the copy without origin credentials when a redirection leaves the initial origin
 * and the approved ones.
 */
interface OriginCredentialsAwareInterface extends AdapterInterface
{
    /**
     * Get a copy of the adapter without its origin credentials.
     *
     * Proxy credentials and transport settings must be kept. The current adapter must not be modified.
     *
     * @return static
     */
    public function withoutOriginCredentials(): static;
}
