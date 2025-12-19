<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\DTOs;

use JsonSerializable;

/**
 * Immutable DTO representing the result of a moderation analysis.
 *
 * Contains the safety assessment, confidence score, flagged terms,
 * and metadata about which provider and engine were used.
 */
readonly class ModerationResult implements JsonSerializable
{
    /**
     * @param  bool  $isSafe  Whether the content is considered safe
     * @param  float|null  $confidence  Overall confidence score from 0.0 to 1.0
     * @param  array<FlaggedTerm>  $flags  Array of flagged terms/categories
     * @param  string  $provider  The provider name (e.g., 'google')
     * @param  string  $engine  The engine used (e.g., 'natural_language', 'vision', 'gemini')
     * @param  array<string, mixed>  $rawResponse  Optional raw API response for debugging
     */
    public function __construct(
        public bool $isSafe,
        public ?float $confidence,
        public array $flags,
        public string $provider,
        public string $engine,
        public array $rawResponse = [],
    ) {}

    /**
     * Check if the content is safe.
     */
    public function isSafe(): bool
    {
        return $this->isSafe;
    }

    /**
     * Check if the content is unsafe.
     */
    public function isUnsafe(): bool
    {
        return !$this->isSafe;
    }

    /**
     * Get all flagged terms.
     *
     * @return array<FlaggedTerm>
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * Get flags from API only.
     *
     * @return array<FlaggedTerm>
     */
    public function apiFlags(): array
    {
        return array_filter($this->flags, fn (FlaggedTerm $flag) => $flag->isFromApi());
    }

    /**
     * Get flags from blocklist only.
     *
     * @return array<FlaggedTerm>
     */
    public function blocklistFlags(): array
    {
        return array_filter($this->flags, fn (FlaggedTerm $flag) => $flag->isFromBlocklist());
    }

    /**
     * Get high-severity flags only.
     *
     * @return array<FlaggedTerm>
     */
    public function highSeverityFlags(): array
    {
        return array_filter($this->flags, fn (FlaggedTerm $flag) => $flag->isHighSeverity());
    }

    /**
     * Check if there are any high-severity flags.
     */
    public function hasHighSeverityFlags(): bool
    {
        return count($this->highSeverityFlags()) > 0;
    }

    /**
     * Get the overall confidence score.
     */
    public function confidence(): ?float
    {
        return $this->confidence;
    }

    /**
     * Get the provider name.
     */
    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * Get the engine name.
     */
    public function engine(): string
    {
        return $this->engine;
    }

    /**
     * Get flags grouped by category.
     *
     * @return array<string, array<FlaggedTerm>>
     */
    public function flagsByCategory(): array
    {
        $grouped = [];
        foreach ($this->flags as $flag) {
            $grouped[$flag->category][] = $flag;
        }

        return $grouped;
    }

    /**
     * Merge with another ModerationResult.
     *
     * Used to combine API results with blocklist results.
     */
    public function merge(ModerationResult $other): self
    {
        return new self(
            isSafe: $this->isSafe && $other->isSafe,
            confidence: $this->confidence,
            flags: array_merge($this->flags, $other->flags),
            provider: $this->provider,
            engine: $this->engine,
            rawResponse: $this->rawResponse,
        );
    }

    /**
     * Create a safe result with no flags.
     */
    public static function safe(string $provider, string $engine): self
    {
        return new self(
            isSafe: true,
            confidence: 1.0,
            flags: [],
            provider: $provider,
            engine: $engine,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_safe' => $this->isSafe,
            'confidence' => $this->confidence,
            'flags' => array_map(fn (FlaggedTerm $flag) => $flag->toArray(), $this->flags),
            'provider' => $this->provider,
            'engine' => $this->engine,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
