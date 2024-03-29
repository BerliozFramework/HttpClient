# Change Log

All notable changes to this project will be documented in this file. This project adheres
to [Semantic Versioning] (http://semver.org/). For change log format,
use [Keep a Changelog] (http://keepachangelog.com/).

## [2.0.0] - 2024-03-22

### Added

- `Options` class to manage options of client
- `HttpContext` class to pass context to adapters
- 'context' option to personalize Proxy/SSL/... options of adapters
- Retry option for `NetworkException`

### Changed

- Signature of `AdapterInterface::sendRequest()` method
- Adapters accept context to personalize Proxy/SSL/... options
- Undefined option returns NULL instead of exception
- Force string cast in `HarGenerator` headers
- Change method to get content and headers from CURL
- Do not get content from HEAD requests
- Bump version of `elgigi/har-parser` library to ^1.0

### Removed

- Remove deprecated default header "Accept-Charset"

### Fixed

- Normalize headers in options
- Normalize default headers
- Non-utf8 header value for HAR generation
- Non-utf8 querystring value for HAR generation
- Bug with clone method of PHP
- Fix redirect for 307 and 308 http status

## [2.0.0-beta5] - 2022-09-07

### Fixed

- HTTP reason not encoded in UTF8 from response

## [2.0.0-beta4] - 2022-07-13

### Added

- Write HAR file to a stream

### Changed

- `Cookie::isSame()` now compare only name, domain and path
- `HarParser` renamed to `HarHandler`
- `HarAdapter` accept a path of HAR file in constructor argument

### Removed

- Dependency with mbstring

### Fixed

- Try to decode content if empty
- Trimmed content before inflate failed
- Creation of request/response from `HarAdapter` use now cookies objects of har file instead of headers

## [2.0.0-beta3] - 2022-02-25

### Added

- New method `DefaultHeadersTrait::addDefaultHeaders`
- New `HarAdapter` to simulate connection with HAR files

### Changed

- Base64 encoding for HAR response content

### Fixed

- Redirection keep old headers like `Content-Length`
- Duplicate host header

## [2.0.0-beta2] - 2022-01-13

### Added

- New method `HarParser::addEntryToSession(Session $session, Entry $entry): void`
- New method `HarParser::getTimings(Entry $entry): Timings`
- New method `Session::getLastRequest(): ?RequestInterface`
- New method `Session::getLastResponse(): ?ResponseInterface`
- New method `Session::createFromHarFile(): Session`
- New `callback` option for requests
- New `followLocation` option for requests, return 3xx Response object if it's a redirection
- Add missing 'Content-Length' header during preparation of request

### Changed

- Visibility of method `HarParser::getHttpRequest(Request $request): RequestInterface` to public
- Visibility of method `HarParser::getHttpResponse(Response $response): ResponseInterface` to public
- Merge options of request with global options
- Use previous request URI if "baseUri" option is not defined

### Fixed

- Default "sleepTime" option to 0
- Conversion of "sleepTime" in ms to microseconds

## [2.0.0-beta1] - 2021-08-30

### Added

- Adapters
- Option `cookies` for requests, to ignore cookies or force a specified manager
- HAR file

### Changed

- Refactoring

## [1.0.2] - 2021-02-15

### Changed

- Separate headers' parser into an independent trait to be reused

## [1.0.1] - 2021-01-28

### Fixed

- Fix cookie negative max-age

## [1.0.0] - 2020-11-06

Initial version