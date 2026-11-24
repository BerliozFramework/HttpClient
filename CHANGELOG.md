# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Client::query()` shortcut for the HTTP `QUERY` method (RFC 10008)
- `HttpContext::$credentials_origins` to approve additional origins receiving origin transport credentials on
  redirects (`https://auth.example.com`, or `https://*.example.com` for one subdomain level)
- `OriginCredentialsAwareInterface` for adapters holding their own origin credentials, implemented by `CurlAdapter`
  and `AutoAdapter`
- `HttpContext` options `proxy_ssl_verify_peer`, `proxy_ssl_verify_host` (enabled by default, independent of the origin
  `ssl_verify_*` options), `proxy_ssl_cafile` and `proxy_ssl_capath` (default to `ssl_cafile` and `ssl_capath`)
- Proxy environment variables are honored identically by the cURL and stream adapters: `https_proxy`/`HTTPS_PROXY`
  for HTTPS requests, `http_proxy` for HTTP requests (never the uppercase `HTTP_PROXY`, httpoxy),
  `all_proxy`/`ALL_PROXY` as fallback, and `no_proxy`/`NO_PROXY` exclusions (hosts, domains, IP addresses, CIDR
  ranges, optional port)

### Changed

- `HttpContext::$proxy` defaults to `null`, which follows the proxy environment variables in every adapter; `false` now
  means no proxy at all, including for the cURL adapter, which previously still honored the environment variables
- The stream adapter now goes through the proxy of the environment variables, it previously always connected directly
- The cURL adapter resolves the proxy itself: libcurl no longer reads the proxy environment variables, except when a
  raw `CURLOPT_PROXY` option is given with the default `null` proxy; a configured proxy or `false` takes precedence
  over a raw `CURLOPT_PROXY` option
- A proxy of the environment variables with an unsupported scheme (e.g. `ftp://`) is rejected with a
  `RequestException` naming the variable; SOCKS proxies (`socks4`, `socks4a`, `socks5`, `socks5h`) are still handed
  over to libcurl by the cURL adapter, and rejected by the stream adapter
- A proxy of the environment variables without port uses port 80 (`http`) or 443 (`https`), libcurl used 1080
- The cURL adapter only opens a `CONNECT` tunnel for HTTPS origins; HTTP requests are sent to the proxy in
  absolute-form, as the stream adapter does
- `QUERY` requests keep their method and body when following a 301 or 302 redirection (RFC 10008, section 2.5);
  only a 303 switches to `GET`
- Disabling origin TLS verification (`ssl_verify_peer`, `ssl_verify_host`) no longer disables proxy TLS verification;
  use `proxy_ssl_verify_peer` and `proxy_ssl_verify_host`
- An unsupported proxy configuration (scheme other than `http`/`https`, missing host, path, query or fragment) is
  rejected with a `RequestException` before any connection; a proxy without scheme is an `http` proxy

### Security

- Configured proxy is enforced across adapters (GHSA-3jc3-3r75-xrx6): the cURL adapter now keeps the proxy scheme, so
  an `https://` proxy is reached over TLS instead of plain HTTP; the stream adapter now connects through the proxy
  (absolute-form requests, `CONNECT` tunnel for HTTPS origins, data sent by the proxy before the origin TLS handshake
  rejected) instead of connecting directly to the origin, sends proxy credentials in `Proxy-Authorization` instead of
  `Authorization`, and rejects HTTPS origins through an HTTPS proxy, which PHP streams cannot handle
- Origin transport credentials (client certificate, key and passphrases of `HttpContext`, credential options of
  `CurlAdapter` such as `CURLOPT_USERPWD`, `CURLOPT_XOAUTH2_BEARER` or `CURLOPT_COOKIE`) are no longer sent when
  a redirection leaves the initial origin or the approved ones; proxy settings are kept
- Custom adapters holding their own origin credentials must implement `OriginCredentialsAwareInterface`: the client
  uses their `withoutOriginCredentials()` copy on those redirections, other adapters only receive the `HttpContext`
  without TLS client options

## [3.3.2] - 2026-10-25

_No changes in this release._

## [3.3.1] - 2026-10-16

_No changes in this release._

## [3.3.0] - 2026-09-24

### Added

- Additive `redirectSensitiveHeaders` option to configure application-specific credential headers

### Fixed

- Restore cookie manager snapshots in linear time without replaying cookie replacement checks
- Reject empty cookie hosts before IDNA conversion to avoid ValueError on PHP 8.4 and later
- Ignore invalid cookie domains individually during HAR import and replay
- Serialize session cookies as an array and discard cookies from legacy session formats
- Preserve host-only and SameSite attributes when updating cookies
- Honor `cookies: false` when sending requests and collecting response cookies
- Preserve content type for 307/308 request bodies and recalculate redirect content length

### Security

- Preserve host-only scope across HAR exports/imports and validate imported cookies against their entry host
- Normalize internationalized cookie domains when IDNA support is available; reject Unicode domains otherwise
- Reject response cookies for unrelated domains before storage, replacement or deletion
- Preserve host-only cookie scope and enforce cookie path boundaries and default paths
- Strip credential headers and URI credentials persistently after cross-origin redirects without modifying caller options
- Sanitize redirect referers and omit them on HTTPS-to-HTTP redirects, preventing reapplication from default headers

## [3.2.1] - 2026-09-11

_No changes in this release._

## [3.2.0] - 2026-07-02

### Added

- `AutoAdapter` selecting cURL by default and falling back to the stream transport automatically

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

_No changes in this release._

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
