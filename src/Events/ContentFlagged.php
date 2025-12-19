<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Events;

use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when content is flagged as unsafe.
 *
 * Listen for this event to handle flagged content, such as:
 * - Logging the violation
 * - Notifying moderators
 * - Blocking the content submission
 * - Updating user reputation scores
 */
class ContentFlagged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  ModerationResult  $result  The moderation result containing flags
     * @param  string  $type  The content type: 'text' or 'image'
     * @param  string|null  $content  The original content (text or image path)
     * @param  string|null  $language  The language code (for text moderation)
     * @param  array<string, mixed>  $metadata  Additional context metadata
     */
    public function __construct(
        public readonly ModerationResult $result,
        public readonly string $type,
        public readonly ?string $content = null,
        public readonly ?string $language = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Check if this was text content.
     */
    public function isText(): bool
    {
        return $this->type === 'text';
    }

    /**
     * Check if this was image content.
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Get the flagged categories.
     *
     * @return array<string>
     */
    public function categories(): array
    {
        return array_unique(array_map(
            fn ($flag) => $flag->category,
            $this->result->flags(),
        ));
    }

    /**
     * Check if content was flagged with high severity.
     */
    public function isHighSeverity(): bool
    {
        return $this->result->hasHighSeverityFlags();
    }
}
