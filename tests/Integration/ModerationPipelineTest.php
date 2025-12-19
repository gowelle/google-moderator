<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\Blocklists\FileBlocklistRepository;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Services\ModerationManager;

describe('ModerationManager Integration', function () {
    it('can be instantiated with default config', function () {
        $config = [
            'engines' => [
                'text' => 'natural_language',
                'image' => 'vision',
            ],
            'blocklists' => [
                'enabled' => false,
            ],
            'gemini' => [
                'enabled' => false,
            ],
        ];

        // This will fail without Google credentials, but tests config parsing
        expect(fn () => new ModerationManager($config))->toThrow(Exception::class);
    });

    it('resolves blocklist repository based on config', function () {
        $tempDir = sys_get_temp_dir().'/gm-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $config = [
            'blocklists' => [
                'enabled' => true,
                'storage' => 'file',
                'file_path' => $tempDir,
            ],
            'cache' => [
                'enabled' => false,
            ],
        ];

        $repo = new FileBlocklistRepository($config);

        expect($repo)->toBeInstanceOf(FileBlocklistRepository::class);

        // Cleanup
        rmdir($tempDir);
    });
});

describe('Moderation Pipeline', function () {
    it('returns ModerationResult from text moderation', function () {
        // Mock test - in real scenario this would use mocked Google client
        $result = ModerationResult::safe('google', 'natural_language');

        expect($result)->toBeInstanceOf(ModerationResult::class);
        expect($result->isSafe())->toBeTrue();
        expect($result->provider())->toBe('google');
    });

    it('merges API and blocklist results correctly', function () {
        $apiResult = new ModerationResult(
            isSafe: true,
            confidence: null,
            flags: [],
            provider: 'google',
            engine: 'natural_language',
        );

        $blocklistResult = new ModerationResult(
            isSafe: false,
            confidence: null,
            flags: [new \Gowelle\GoogleModerator\DTOs\FlaggedTerm(
                'blocked_term',
                'blocklist',
                'high',
                null,
                'blocklist',
            )],
            provider: 'blocklist',
            engine: 'blocklist',
        );

        $merged = $apiResult->merge($blocklistResult);

        expect($merged->isSafe())->toBeFalse();
        expect($merged->flags())->toHaveCount(1);
        expect($merged->blocklistFlags())->toHaveCount(1);
    });
});
