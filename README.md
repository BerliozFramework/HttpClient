# Berlioz HTTP Client

[![Latest Version](https://img.shields.io/packagist/v/berlioz/http-client.svg?style=flat-square)](https://github.com/BerliozFramework/HttpClient/releases)
[![Software license](https://img.shields.io/github/license/BerliozFramework/HttpClient.svg?style=flat-square)](https://github.com/BerliozFramework/HttpClient/blob/2.x/LICENSE)
[![Build Status](https://img.shields.io/github/workflow/status/BerliozFramework/HttpClient/Tests/2.x.svg?style=flat-square)](https://github.com/BerliozFramework/HttpClient/actions/workflows/tests.yml?query=branch%3A2.x)
[![Quality Grade](https://img.shields.io/codacy/grade/3e9df26a706d4ac285e1a49176665751/2.x.svg?style=flat-square)](https://app.codacy.com/gh/BerliozFramework/HttpClient)
[![Total Downloads](https://img.shields.io/packagist/dt/berlioz/http-client.svg?style=flat-square)](https://packagist.org/packages/berlioz/http-client)

**Berlioz HTTP Client** is a PHP library to request HTTP server with continuous navigation, including cookies, sessions...
Implements PSR-18 (HTTP Client), PSR-7 (HTTP message interfaces) and PSR-17 (HTTP Factories) standards.

## Installation

### Composer

You can install **Berlioz HTTP Client** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require berlioz/http-client
```

### Dependencies

- **PHP** ^8.0
- PHP libraries:
  - **curl**
  - **mbstring**
  - **zlib**
- Packages:
  - **berlioz/http-message**
  - **psr/http-client**
  - **psr/log**

## Usage

### Requests

#### With RequestInterface

You can construct your own request object whose implements `RequestInterface` interface (PSR-7).

```php
use Berlioz\Http\Client\Client;
use Berlioz\Http\Message\Request;

/** @var \Psr\Http\Message\RequestInterface $request */
$request = new Request(...);

$client = new Client();
$response = $client->sendRequest($request);

print $response->getBody();
```

#### Get/Post/Patch/Put/Delete/Options/Head/Connect/Trace

Methods are available to do request with defined HTTP method:

- `Client::get(...)`
- `Client::post(...)`
- `Client::patch(...)`
- `Client::put(...)`
- `Client::delete(...)`
- `Client::options(...)`
- `Client::head(...)`
- `Client::connect(...)`
- `Client::trace(...)`

Example with `Client::get()`:

```php
use Berlioz\Http\Client\Client;

$client = new Client();
$response = $client->get('https://getberlioz.com');

print $response->getBody();
```

You also can pass HTTP method in argument to `Client::request(...)` method:

```php
use Berlioz\Http\Client\Client;

$client = new Client();
$response = $client->request('get', 'https://getberlioz.com');

print $response->getBody();
```

### History

The browsing history is saved in `Client` object.
If you serialize the object `Client`, the history is preserve.

Without argument, the method `Client::getHistory()` returns an array of complete history:

```php
use Berlioz\Http\Client\Client;

$client = new Client();
$history = $client->getHistory();
```

With argument, the method return a specific request in history.

### Cookies

A cookie manager is available to manage cookies of session and between requests.
The manager is available with `Client::getCookies()` method.

If you serialize the object `Client`, the cookies are preserves.