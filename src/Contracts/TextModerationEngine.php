<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Contracts;

use Gowelle\GoogleModerator\DTOs\ModerationResult;

/**
 * Contract for text moderation engines.
 *
 * Implementations should analyze text content for harmful, toxic,
 * or otherwise objectionable content and return a structured result.
 */
interface TextModerationEngine
{
    /**
     * Moderate the given text content.
     *
     * @param  string  $text  The text content to moderate
     * @param  string|null  $language  Optional ISO 639-1 language code (e.g., 'en', 'sw')
     * @return ModerationResult The moderation analysis result
     */
    public function moderate(string $text, ?string $language = null): ModerationResult;
}
