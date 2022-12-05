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

declare(strict_types=1);

namespace Berlioz\Http\Client;

use Berlioz\Http\Client\Cookies\CookiesManager;
use Closure;

class Options
{
    private const DEFAULT_HEADERS = [
        'Accept' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
        'User-Agent' => ['Berlioz Client/2.0'],
        'Accept-Language' => ['fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3'],
        'Accept-Encoding' => ['gzip, deflate'],
        'Connection' => ['close'],
    ];
    private array $userDefined;

    public function __construct(
        // Default adapter
        public ?string $adapter = null,
        // Base of URI for URI requests
        public ?string $baseUri = null,
        // If client follow locations
        public int|false $followLocation = 5,
        // Sleep time between each request
        public int $sleepTime = 0,
        // File where log requests/responses
        public ?string $logFile = null,
        // Throw exceptions on HTTP error cases
        public bool $exceptions = true,
        // NULL: to use default cookie manager, FALSE: to not use cookies, a CookieManager object to use
        public CookiesManager|false|null $cookies = null,
        // Callback on each request
        public ?Closure $callback = null,
        // Callback on exception
        public ?Closure $callbackException = null,
        // Default headers
        public array $headers = self::DEFAULT_HEADERS,
        public ?HttpContext $context = null,
        ...$userDefined,
    ) {
        $this->userDefined = $userDefined;
    }

    public static function make(array|self|null $options = null, ?self $initial = null): self
    {
        // None defined
        if (null === $options) {
            if (null !== $initial) {
                return clone $initial;
            }

            return new self();
        }

        // Array of options
        if (is_array($options)) {
            $new = new self(
                adapter: $initial?->adapter,
                baseUri: $initial?->baseUri,
                followLocation: $initial?->followLocation ?? 5,
                sleepTime: $initial?->sleepTime ?? 0,
                logFile: $initial?->logFile,
                exceptions: $initial?->exceptions ?? true,
                cookies: $initial?->cookies,
                callback: $initial?->callback,
                callbackException: $initial?->callbackException,
                headers: $initial?->headers ?? self::DEFAULT_HEADERS,
                context: $initial?->context ? clone $initial->context : null,
            );

            foreach ($options as $key => $value) {
                $new->$key = match ($key) {
                    'headers' => array_replace(
                        self::normalizeHeaders($new->headers),
                        self::normalizeHeaders((array)$value),
                    ),
                    'context' => HttpContext::make($value, $new->context),
                    default => $value
                };
            }

            return $new;
        }

        return $options;
    }

    /**
     * Get user defined options.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->userDefined[$name] ?? null;
    }

    /**
     * Set user defined option.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->userDefined[$name] = $value;
    }

    /**
     * Normalize headers.
     *
     * @param array $headers
     *
     * @return array
     */
    public static function normalizeHeaders(array $headers): array
    {
        $final = [];

        foreach ($headers as $name => $value) {
            $name = static::normalizeHeaderName($name);
            $final[$name] = (array)$value;
        }

        array_walk_recursive($final, fn(&$value) => $value = (string)$value);
        unset($value);

        return $final;
    }

    /**
     * Normalize headers.
     *
     * @param string $name
     *
     * @return string
     */
    public static function normalizeHeaderName(string $name): string
    {
        return ucwords(strtolower($name), ' -_');
    }
}