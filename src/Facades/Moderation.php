<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Facades;

use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Services\ModerationManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ModerationResult text(string $text, ?string $language = null)
 * @method static ModerationResult image(mixed $image)
 * @method static BlocklistRepository|null blocklist()
 *
 * @see \Gowelle\GoogleModerator\Services\ModerationManager
 */
class Moderation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModerationManager::class;
    }
}
