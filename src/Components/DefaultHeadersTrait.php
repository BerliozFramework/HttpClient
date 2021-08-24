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

declare(strict_types=1);

namespace Berlioz\Http\Client\Components;

/**
 * Trait DefaultHeadersTrait.
 */
trait DefaultHeadersTrait
{
    /** @var array Default headers */
    protected array $defaultHeaders = [];

    /**
     * Get default headers.
     *
     * @return array
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Set default headers.
     *
     * @param array $headers
     * @param bool $erase Erase if exists (default: true)
     *
     * @return static
     */
    public function setDefaultHeaders(array $headers, bool $erase = true): static
    {
        if ($erase) {
            $this->defaultHeaders = $headers;

            return $this;
        }

        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $this;
    }

    /**
     * Get default header.
     *
     * @param string $name
     *
     * @return array
     */
    public function getDefaultHeader(string $name): array
    {
        return $this->defaultHeaders[$name] ?? [];
    }

    /**
     * Set default header.
     *
     * @param string $name Name
     * @param string $value Value
     * @param bool $erase Erase if exists (default: true)
     *
     * @return static
     */
    public function setDefaultHeader(string $name, string $value, bool $erase = true): static
    {
        if ($erase || !isset($this->defaultHeaders[$name])) {
            unset($this->defaultHeaders[$name]);
        }

        $this->defaultHeaders[$name] = array_merge((array)($this->defaultHeaders[$name] ?? []), (array)$value);

        return $this;
    }
}