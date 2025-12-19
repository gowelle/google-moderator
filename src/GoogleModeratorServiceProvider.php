<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator;

use Gowelle\GoogleModerator\Blocklists\DatabaseBlocklistRepository;
use Gowelle\GoogleModerator\Blocklists\FileBlocklistRepository;
use Gowelle\GoogleModerator\Commands\ExportBlocklistCommand;
use Gowelle\GoogleModerator\Commands\ImportBlocklistCommand;
use Gowelle\GoogleModerator\Contracts\BlocklistRepository;
use Gowelle\GoogleModerator\Services\ModerationManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GoogleModeratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('google-moderator')
            ->hasConfigFile()
            ->hasMigration('create_blocklist_terms_table')
            ->hasCommands([
                ImportBlocklistCommand::class,
                ExportBlocklistCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register ModerationManager as singleton
        $this->app->singleton(ModerationManager::class, function ($app) {
            return new ModerationManager(
                config('google-moderator', []),
            );
        });

        // Register BlocklistRepository based on config
        $this->app->singleton(BlocklistRepository::class, function ($app) {
            $config = config('google-moderator', []);
            $storage = $config['blocklists']['storage'] ?? 'database';

            return match ($storage) {
                'file' => new FileBlocklistRepository($config),
                default => new DatabaseBlocklistRepository($config),
            };
        });
    }

    public function packageBooted(): void
    {
        // Publish sample blocklist files
        $this->publishes([
            __DIR__.'/../storage/blocklists' => storage_path('blocklists'),
        ], 'google-moderator-blocklists');
    }
}
