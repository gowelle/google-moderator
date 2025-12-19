<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Contracts;

use Gowelle\GoogleModerator\DTOs\BlocklistTerm;

/**
 * Contract for blocklist storage and retrieval.
 *
 * Implementations can use file-based storage (JSON) or database storage.
 * Supports multiple languages with per-language term lists.
 */
interface BlocklistRepository
{
    /**
     * Get all blocklist terms for a specific language.
     *
     * @param  string  $language  ISO 639-1 language code (e.g., 'en', 'sw')
     * @return array<BlocklistTerm> Array of blocklist terms
     */
    public function getTerms(string $language): array;

    /**
     * Add a term to the blocklist.
     *
     * @param  string  $language  ISO 639-1 language code
     * @param  string  $value  The term or pattern to block
     * @param  string  $severity  Severity level: 'low', 'medium', 'high'
     * @param  bool  $isRegex  Whether the value is a regex pattern
     */
    public function addTerm(string $language, string $value, string $severity, bool $isRegex = false): void;

    /**
     * Remove a term from the blocklist.
     *
     * @param  string  $language  ISO 639-1 language code
     * @param  string  $value  The term to remove
     */
    public function removeTerm(string $language, string $value): void;

    /**
     * Check if a term exists in the blocklist.
     *
     * @param  string  $language  ISO 639-1 language code
     * @param  string  $value  The term to check
     */
    public function hasTerm(string $language, string $value): bool;

    /**
     * Get all supported languages.
     *
     * @return array<string> Array of language codes
     */
    public function getLanguages(): array;
}
