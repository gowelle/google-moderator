<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Services;

use Gowelle\GoogleModerator\Blocklists\DatabaseBlocklistRepository;
use Gowelle\GoogleModerator\Blocklists\FileBlocklistRepository;
use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Gowelle\GoogleModerator\Contracts\ImageModerationEngine;
use Gowelle\GoogleModerator\Contracts\TextModerationEngine;
use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Engines\GeminiTextEngine;
use Gowelle\GoogleModerator\Engines\GeminiVisionEngine;
use Gowelle\GoogleModerator\Engines\NaturalLanguageEngine;
use Gowelle\GoogleModerator\Engines\VisionEngine;
use Gowelle\GoogleModerator\Exceptions\ModerationException;
use Illuminate\Support\Facades\Log;

/**
 * Central orchestrator for content moderation.
 *
 * Resolves engines based on configuration, runs moderation,
 * applies blocklist matching, and returns unified results.
 */
class ModerationManager
{
    private TextModerationEngine $textEngine;

    private ImageModerationEngine $imageEngine;

    private ?BlocklistRepository $blocklistRepository = null;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->textEngine = $this->resolveTextEngine();
        $this->imageEngine = $this->resolveImageEngine();

        if ($this->isBlocklistEnabled()) {
            $this->blocklistRepository = $this->resolveBlocklistRepository();
        }
    }

    /**
     * Moderate text content.
     *
     * @param  string  $text  The text to moderate
     * @param  string|null  $language  Optional ISO 639-1 language code
     */
    public function text(string $text, ?string $language = null): ModerationResult
    {
        // Run Google engine moderation
        $result = $this->textEngine->moderate($text, $language);

        // Apply blocklist matching if enabled
        if ($this->blocklistRepository !== null && $language !== null) {
            $blocklistResult = $this->matchBlocklist($text, $language);
            $result = $result->merge($blocklistResult);
        }

        // Log if configured
        $this->logResult('text', $result);

        return $result;
    }

    /**
     * Moderate image content.
     *
     * @param  string|resource  $image  Path, URL, or binary content
     */
    public function image(mixed $image): ModerationResult
    {
        $result = $this->imageEngine->moderate($image);

        // Log if configured
        $this->logResult('image', $result);

        return $result;
    }

    /**
     * Get the blocklist repository.
     */
    public function blocklist(): ?BlocklistRepository
    {
        return $this->blocklistRepository;
    }

    /**
     * Match text against blocklist terms.
     */
    private function matchBlocklist(string $text, string $language): ModerationResult
    {
        if ($this->blocklistRepository === null) {
            return ModerationResult::safe('google', 'blocklist');
        }

        $terms = $this->blocklistRepository->getTerms($language);
        $flags = [];

        foreach ($terms as $term) {
            if ($term->matches($text)) {
                $flags[] = new FlaggedTerm(
                    term: $term->value,
                    category: 'blocklist',
                    severity: $term->severity,
                    confidence: null,
                    source: 'blocklist',
                );
            }
        }

        return new ModerationResult(
            isSafe: count($flags) === 0,
            confidence: null,
            flags: $flags,
            provider: 'blocklist',
            engine: 'blocklist',
        );
    }

    /**
     * Resolve the text moderation engine based on config.
     */
    private function resolveTextEngine(): TextModerationEngine
    {
        $engineType = $this->config['engines']['text'] ?? 'natural_language';

        return match ($engineType) {
            'natural_language' => new NaturalLanguageEngine($this->config),
            'gemini' => $this->resolveGeminiTextEngine(),
            default => throw ModerationException::unsupportedEngine('text', $engineType),
        };
    }

    /**
     * Resolve Gemini text engine.
     */
    private function resolveGeminiTextEngine(): TextModerationEngine
    {
        $geminiEnabled = $this->config['gemini']['enabled'] ?? false;

        if (!$geminiEnabled) {
            throw ModerationException::configurationError(
                'Gemini engine is selected but gemini.enabled is false',
            );
        }

        return new GeminiTextEngine($this->config);
    }

    /**
     * Resolve the image moderation engine based on config.
     */
    private function resolveImageEngine(): ImageModerationEngine
    {
        $engineType = $this->config['engines']['image'] ?? 'vision';

        return match ($engineType) {
            'vision' => new VisionEngine($this->config),
            'gemini' => $this->resolveGeminiVisionEngine(),
            default => throw ModerationException::unsupportedEngine('image', $engineType),
        };
    }

    /**
     * Resolve Gemini vision engine.
     */
    private function resolveGeminiVisionEngine(): ImageModerationEngine
    {
        $geminiEnabled = $this->config['gemini']['enabled'] ?? false;

        if (!$geminiEnabled) {
            throw ModerationException::configurationError(
                'Gemini engine is selected but gemini.enabled is false',
            );
        }

        return new GeminiVisionEngine($this->config);
    }

    /**
     * Check if blocklist is enabled.
     */
    private function isBlocklistEnabled(): bool
    {
        return $this->config['blocklists']['enabled'] ?? true;
    }

    /**
     * Resolve the blocklist repository based on config.
     */
    private function resolveBlocklistRepository(): BlocklistRepository
    {
        $storage = $this->config['blocklists']['storage'] ?? 'database';

        return match ($storage) {
            'database' => new DatabaseBlocklistRepository($this->config),
            'file' => new FileBlocklistRepository($this->config),
            default => throw ModerationException::configurationError(
                "Unsupported blocklist storage: {$storage}",
            ),
        };
    }

    /**
     * Log moderation result if logging is enabled.
     */
    private function logResult(string $type, ModerationResult $result): void
    {
        $loggingEnabled = $this->config['logging']['enabled'] ?? false;

        if (!$loggingEnabled) {
            return;
        }

        // Only log unsafe content by default
        $logSafe = $this->config['logging']['log_safe_content'] ?? false;

        if ($result->isSafe() && !$logSafe) {
            return;
        }

        $channel = $this->config['logging']['channel'] ?? null;
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->info("Content moderation [{$type}]", [
            'is_safe' => $result->isSafe(),
            'provider' => $result->provider,
            'engine' => $result->engine,
            'flags' => array_map(fn ($f) => $f->toArray(), $result->flags()),
        ]);
    }
}
