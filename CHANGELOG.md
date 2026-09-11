# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Additive `redirectSensitiveHeaders` option to configure application-specific credential headers

### Security

- Strip credential headers and URI credentials persistently after cross-origin redirects without modifying caller options
- Sanitize redirect referers and omit them on HTTPS-to-HTTP redirects, preventing reapplication from default headers

### Fixed

- Honor `cookies: false` when sending requests and collecting response cookies
- Preserve content type for 307/308 request bodies and recalculate redirect content length

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
