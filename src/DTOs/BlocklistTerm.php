<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\DTOs;

/**
 * Represents a blocklist term entry.
 *
 * Used for both file-based and database-based blocklists.
 */
readonly class BlocklistTerm
{
    /**
     * @param  string  $language  ISO 639-1 language code
     * @param  string  $value  The term or pattern to block
     * @param  string  $severity  Severity level: 'low', 'medium', 'high'
     * @param  bool  $isRegex  Whether the value is a regex pattern
     */
    public function __construct(
        public string $language,
        public string $value,
        public string $severity = 'medium',
        public bool $isRegex = false,
    ) {}

    /**
     * Check if the given text matches this blocklist term.
     *
     * @param  string  $text  The text to check
     * @return bool True if the text matches this term
     */
    public function matches(string $text): bool
    {
        $normalizedText = mb_strtolower($text);
        $normalizedValue = mb_strtolower($this->value);

        if ($this->isRegex) {
            return (bool) preg_match($this->value, $text);
        }

        // Support wildcard patterns with asterisks
        if (str_contains($normalizedValue, '*')) {
            $pattern = '/'.str_replace('\*', '.*', preg_quote($normalizedValue, '/')).'/i';

            return (bool) preg_match($pattern, $normalizedText);
        }

        // Exact match (case-insensitive, word boundary)
        $pattern = '/\b'.preg_quote($normalizedValue, '/').'\b/iu';

        return (bool) preg_match($pattern, $normalizedText);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'value' => $this->value,
            'severity' => $this->severity,
            'is_regex' => $this->isRegex,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $language): self
    {
        return new self(
            language: $language,
            value: (string) ($data['value'] ?? ''),
            severity: (string) ($data['severity'] ?? 'medium'),
            isRegex: (bool) ($data['is_regex'] ?? $data['isRegex'] ?? false),
        );
    }
}
