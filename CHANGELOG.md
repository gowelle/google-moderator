# Changelog

All notable changes to `google-moderator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-12-19

### Added
- Initial release
- Text moderation using Google Natural Language API (`moderateText`)
- Image moderation using Google Vision API (SafeSearch)
- Optional Gemini API integration for text and image moderation
- Opt-in blocklists with file and database storage
- Multi-language support (any language, Swahili-first)
- Pattern matching: exact, wildcard, and regex
- Configurable thresholds per category
- Caching for blocklist terms
- Artisan commands for blocklist import/export
- ModerationResult DTO with rich filtering API
- FlaggedTerm DTO with source tracking
- `ContentFlagged` event dispatched when content is flagged as unsafe
- Events configuration option to enable/disable event dispatching
- GitHub Actions CI workflow (PHP 8.2-8.4, Laravel 10-12 matrix)
- PHPStan and Pint checks in CI
- Dependabot configuration for automated dependency updates
- Comprehensive unit and integration tests
