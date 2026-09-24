# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
