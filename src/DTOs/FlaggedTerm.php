<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\DTOs;

/**
 * Represents a flagged term from moderation analysis.
 *
 * Terms can originate from either the Google API analysis
 * or from custom blocklist matching.
 */
readonly class FlaggedTerm
{
    /**
     * @param  string  $term  The flagged term or category
     * @param  string  $category  The category of the flag (e.g., 'toxic', 'adult', 'violence')
     * @param  string  $severity  Severity level: 'low', 'medium', 'high'
     * @param  float|null  $confidence  Confidence score from 0.0 to 1.0 (null for blocklist matches)
     * @param  string  $source  Source of the flag: 'api' or 'blocklist'
     */
    public function __construct(
        public string $term,
        public string $category,
        public string $severity,
        public ?float $confidence = null,
        public string $source = 'api',
    ) {}

    /**
     * Check if this flag originated from the API.
     */
    public function isFromApi(): bool
    {
        return $this->source === 'api';
    }

    /**
     * Check if this flag originated from a blocklist.
     */
    public function isFromBlocklist(): bool
    {
        return $this->source === 'blocklist';
    }

    /**
     * Check if this is a high-severity flag.
     */
    public function isHighSeverity(): bool
    {
        return $this->severity === 'high';
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'category' => $this->category,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'source' => $this->source,
        ];
    }
}
