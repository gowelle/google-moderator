<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Contracts;

use Gowelle\GoogleModerator\DTOs\ModerationResult;

/**
 * Contract for image moderation engines.
 *
 * Implementations should analyze image content for adult, violent,
 * racy, or otherwise objectionable content and return a structured result.
 */
interface ImageModerationEngine
{
    /**
     * Moderate the given image.
     *
     * @param  string|resource  $image  Path to image file, URL, or binary content
     * @return ModerationResult The moderation analysis result
     */
    public function moderate(mixed $image): ModerationResult;
}
