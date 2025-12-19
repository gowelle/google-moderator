<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure how to authenticate with Google Cloud APIs.
    | Supports multiple methods for flexibility across environments.
    |
    | Priority order:
    | 1. credentials_json (inline JSON - for serverless like Vapor)
    | 2. credentials_path (path to service account JSON file)
    | 3. project_id only (uses Application Default Credentials)
    | 4. Falls back to ADC if nothing is configured
    |
    */

    'auth' => [
        // Path to service account JSON file
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),

        // Inline JSON credentials (base64 encoded or raw JSON string)
        'credentials_json' => env('GOOGLE_CREDENTIALS_JSON'),

        // Google Cloud Project ID (required for some operations)
        'project_id' => env('GOOGLE_CLOUD_PROJECT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation Engines
    |--------------------------------------------------------------------------
    |
    | Configure which engines to use for text and image moderation.
    |
    | Text options: 'natural_language', 'gemini'
    | Image options: 'vision', 'gemini'
    |
    */

    'engines' => [
        'text' => env('GOOGLE_MODERATOR_TEXT_ENGINE', 'natural_language'),
        'image' => env('GOOGLE_MODERATOR_IMAGE_ENGINE', 'vision'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation Thresholds
    |--------------------------------------------------------------------------
    |
    | Confidence thresholds for flagging content as unsafe.
    | Values from 0.0 (flag everything) to 1.0 (flag nothing).
    | Lower values = more strict moderation.
    |
    */

    'thresholds' => [
        // Text moderation categories (Natural Language API)
        'toxic' => 0.7,
        'severe_toxic' => 0.5,
        'insult' => 0.7,
        'profanity' => 0.7,
        'derogatory' => 0.7,
        'sexual' => 0.7,
        'death_harm_tragedy' => 0.7,
        'violent' => 0.7,
        'firearms_weapons' => 0.7,
        'public_safety' => 0.7,
        'health' => 0.8,
        'religion_belief' => 0.8,
        'illicit_drugs' => 0.6,
        'war_conflict' => 0.7,
        'politics' => 0.8,
        'finance' => 0.9,
        'legal' => 0.9,

        // Image moderation categories (Vision API SafeSearch)
        'adult' => 'LIKELY',       // UNKNOWN, VERY_UNLIKELY, UNLIKELY, POSSIBLE, LIKELY, VERY_LIKELY
        'violence' => 'LIKELY',
        'racy' => 'LIKELY',
        'spoof' => 'POSSIBLE',
        'medical' => 'POSSIBLE',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocklists
    |--------------------------------------------------------------------------
    |
    | Opt-in custom blocklists for terms not caught by Google APIs.
    | Supports multiple languages with per-language term lists.
    |
    */

    'blocklists' => [
        // Enable/disable blocklist checking
        'enabled' => env('GOOGLE_MODERATOR_BLOCKLISTS_ENABLED', true),

        // Storage driver: 'database' or 'file'
        'storage' => env('GOOGLE_MODERATOR_BLOCKLIST_STORAGE', 'database'),

        // Supported languages (ISO 639-1 codes)
        // The package supports any language, not limited to this list
        'languages' => ['en', 'sw'],

        // Path for file-based storage (relative to storage_path)
        'file_path' => env('GOOGLE_MODERATOR_BLOCKLIST_PATH', 'blocklists'),

        // Database table name
        'table' => 'blocklist_terms',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini Configuration
    |--------------------------------------------------------------------------
    |
    | Optional Gemini API integration for advanced classification.
    | Disabled by default. Enable only if you have Gemini API access.
    |
    */

    'gemini' => [
        'enabled' => env('GOOGLE_MODERATOR_GEMINI_ENABLED', false),
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-pro'),
        'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-pro-vision'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache settings for blocklist terms to reduce database/file reads.
    |
    */

    'cache' => [
        'enabled' => env('GOOGLE_MODERATOR_CACHE_ENABLED', true),
        'ttl' => env('GOOGLE_MODERATOR_CACHE_TTL', 3600), // seconds
        'prefix' => 'google_moderator_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for moderation events.
    |
    */

    'logging' => [
        'enabled' => env('GOOGLE_MODERATOR_LOGGING_ENABLED', false),
        'channel' => env('GOOGLE_MODERATOR_LOG_CHANNEL'),
        'log_safe_content' => false, // Only log unsafe content by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure event dispatching for content moderation.
    | When enabled, ContentFlagged events are dispatched for unsafe content.
    |
    */

    'events' => [
        'enabled' => env('GOOGLE_MODERATOR_EVENTS_ENABLED', true),
    ],
];
