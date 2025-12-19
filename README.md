# Google Moderator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gowelle/google-moderator.svg?style=flat-square)](https://packagist.org/packages/gowelle/google-moderator)
[![Total Downloads](https://img.shields.io/packagist/dt/gowelle/google-moderator.svg?style=flat-square)](https://packagist.org/packages/gowelle/google-moderator)
[![PHP Version](https://img.shields.io/packagist/php-v/gowelle/google-moderator.svg?style=flat-square)](https://packagist.org/packages/gowelle/google-moderator)
[![License](https://img.shields.io/packagist/l/gowelle/google-moderator.svg?style=flat-square)](LICENSE.md)

A Laravel package for **text and image moderation** using Google AI APIs, with opt-in blocklists, multi-language support, and internal engine switching.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [ModerationResult API](#moderationresult-api)
- [Blocklists](#blocklists)
- [Engine Comparison](#engine-comparison)
- [Thresholds](#thresholds)
- [Events](#events)
- [Testing](#testing)
- [Changelog](#changelog)

## Features

- 🔤 **Text Moderation** - Analyze text for toxic, harmful, or inappropriate content
- 🖼️ **Image Moderation** - Detect adult, violent, or racy content in images
- 🌍 **Multi-Language Support** - Works with any language (Swahili-first, but not limited)
- 📋 **Custom Blocklists** - File or database-backed blocklists with regex support
- 🔄 **Engine Switching** - Switch between Natural Language API, Vision API, or Gemini
- ⚡ **Caching** - Built-in caching for blocklist terms
- 🧪 **Testable** - Fully testable with mocked Google clients

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- Google Cloud account with enabled APIs

## Installation

```bash
composer require gowelle/google-moderator
```

Publish the configuration:

```bash
php artisan vendor:publish --tag="google-moderator-config"
```

Publish the migrations:

```bash
php artisan vendor:publish --tag="google-moderator-migrations"
php artisan migrate
```

## Configuration

### Authentication

The package supports multiple authentication methods:

```php
// config/google-moderator.php

'auth' => [
    // Option 1: Path to service account JSON file
    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    
    // Option 2: Inline JSON (for serverless environments like Vapor)
    'credentials_json' => env('GOOGLE_CREDENTIALS_JSON'),
    
    // Option 3: Project ID for Application Default Credentials
    'project_id' => env('GOOGLE_CLOUD_PROJECT'),
],
```

### Engine Selection

```php
'engines' => [
    'text' => 'natural_language', // or 'gemini'
    'image' => 'vision',          // or 'gemini'
],
```

## Quick Start

### Text Moderation

```php
use Gowelle\GoogleModerator\Facades\Moderation;

$result = Moderation::text(
    text: 'This is some content to moderate',
    language: 'en'
);

if ($result->isSafe()) {
    // Content is safe
} else {
    // Content was flagged
    foreach ($result->flags() as $flag) {
        echo "{$flag->category}: {$flag->severity}";
    }
}
```

### Image Moderation

```php
use Gowelle\GoogleModerator\Facades\Moderation;

// From file path
$result = Moderation::image('/path/to/image.jpg');

// From URL
$result = Moderation::image('https://example.com/image.jpg');

if ($result->isUnsafe()) {
    // Handle unsafe image
}
```

## ModerationResult API

```php
$result = Moderation::text($content, 'sw');

// Safety checks
$result->isSafe();              // bool
$result->isUnsafe();            // bool
$result->confidence();          // float|null

// Flag access
$result->flags();               // array<FlaggedTerm>
$result->apiFlags();            // Flags from Google API only
$result->blocklistFlags();      // Flags from blocklist only
$result->highSeverityFlags();   // High severity flags only
$result->hasHighSeverityFlags(); // bool

// Metadata
$result->provider();            // 'google' or 'blocklist'
$result->engine();              // 'natural_language', 'vision', 'gemini'

// Grouping
$result->flagsByCategory();     // array<string, array<FlaggedTerm>>

// Serialization
$result->toArray();
json_encode($result);
```

## Blocklists

> **💡 Bonus Feature**: Google APIs don't provide customizable term blocking. This package includes a complete blocklist system so you can catch domain-specific terms, slang, or phrases that the AI might miss.

### Why Blocklists?

- **Domain-specific terms** - Block product names, competitor mentions, or industry jargon
- **Regional slang** - Catch offensive terms in local dialects (especially useful for Swahili and other languages)
- **Zero-tolerance words** - Instantly flag specific terms regardless of AI confidence
- **Runs after AI analysis** - Combines AI intelligence with your custom rules

### Enabling Blocklists

```php
'blocklists' => [
    'enabled' => true,
    'storage' => 'database', // or 'file'
    'languages' => ['en', 'sw', 'fr'], // any languages
],
```

### File-Based Blocklists

Create JSON files in `storage/blocklists/`:

```json
// storage/blocklists/sw.json
{
    "language": "sw",
    "terms": [
        { "value": "offensive_word", "severity": "high" },
        { "value": "*partial_match*", "severity": "medium" }
    ]
}
```

Publish sample files:

```bash
php artisan vendor:publish --tag="google-moderator-blocklists"
```

### Database Blocklists

Store terms in the database for easy management via admin panels:

```php
// Add terms programmatically
Moderation::blocklist()->addTerm('sw', 'neno_baya', 'high');
Moderation::blocklist()->addTerm('en', '*spam*', 'medium');
```

Import/export via Artisan:

```bash
php artisan moderator:blocklist:import storage/blocklists/sw.json --language=sw
php artisan moderator:blocklist:export --language=sw --output=exported.json
```

### Pattern Matching

Blocklist terms support three matching modes:

| Pattern | Example | Matches |
|---------|---------|---------|
| Exact | `badword` | "This is badword here" ✅ "badwordy" ❌ |
| Wildcard | `*offensive*` | "very offensive content" ✅ |
| Regex | `/\b(bad\|terrible)\b/i` | "This is bad" ✅ |

## Engine Comparison

| Feature | Natural Language | Vision | Gemini |
|---------|-----------------|--------|--------|
| Text Moderation | ✅ | ❌ | ✅ |
| Image Moderation | ❌ | ✅ | ✅ |
| Toxicity Detection | ✅ (16 categories) | ❌ | ✅ |
| SafeSearch | ❌ | ✅ | ✅ |
| Multi-language | ✅ | N/A | ✅ |
| Cost | Per request | Per image | Per request |
| Default | ✅ Text | ✅ Image | ❌ Optional |

## Thresholds

Configure sensitivity per category:

```php
'thresholds' => [
    // Text (0.0 - 1.0, lower = more strict)
    'toxic' => 0.7,
    'severe_toxic' => 0.5,
    'profanity' => 0.7,
    
    // Image (VERY_UNLIKELY to VERY_LIKELY)
    'adult' => 'LIKELY',
    'violence' => 'LIKELY',
    'racy' => 'POSSIBLE',
],
```

## Events

The package dispatches a `ContentFlagged` event whenever content is flagged as unsafe:

```php
use Gowelle\GoogleModerator\Events\ContentFlagged;
use Illuminate\Support\Facades\Event;

// In your EventServiceProvider or listener
Event::listen(ContentFlagged::class, function (ContentFlagged $event) {
    Log::warning('Unsafe content detected', [
        'type' => $event->type,           // 'text' or 'image'
        'categories' => $event->categories(),
        'is_high_severity' => $event->isHighSeverity(),
        'flags' => $event->result->flags(),
    ]);
    
    // Take action: notify moderators, block submission, etc.
});
```

### ContentFlagged Event Properties

```php
$event->result;      // ModerationResult DTO
$event->type;        // 'text' or 'image'
$event->content;     // Original text or image path
$event->language;    // Language code (for text)
$event->metadata;    // Additional context

// Helper methods
$event->isText();           // bool
$event->isImage();          // bool
$event->categories();       // array of flagged categories
$event->isHighSeverity();   // bool
```

### Disabling Events

```php
// config/google-moderator.php
'events' => [
    'enabled' => false,
],
```

## Testing

```bash
# Unit tests
composer test

# With coverage
composer test-coverage

# Static analysis
composer analyse

# Code style
composer format-test
```

### Mocking in Tests

```php
use Gowelle\GoogleModerator\Facades\Moderation;
use Gowelle\GoogleModerator\DTOs\ModerationResult;

Moderation::shouldReceive('text')
    ->with('test content', 'en')
    ->andReturn(ModerationResult::safe('google', 'natural_language'));
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please send an email to gowelle.john@icloud.com.

## Credits

- [Gowelle](https://github.com/gowelle)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
