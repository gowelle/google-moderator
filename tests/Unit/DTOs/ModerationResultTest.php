<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;

describe('ModerationResult DTO', function () {
    it('creates a safe result', function () {
        $result = ModerationResult::safe('google', 'natural_language');

        expect($result->isSafe())->toBeTrue();
        expect($result->isUnsafe())->toBeFalse();
        expect($result->confidence())->toBe(1.0);
        expect($result->flags())->toBeEmpty();
        expect($result->provider())->toBe('google');
        expect($result->engine())->toBe('natural_language');
    });

    it('creates an unsafe result with flags', function () {
        $flags = [
            new FlaggedTerm('toxic', 'toxic', 'high', 0.9, 'api'),
            new FlaggedTerm('profanity', 'profanity', 'medium', 0.7, 'api'),
        ];

        $result = new ModerationResult(
            isSafe: false,
            confidence: 0.9,
            flags: $flags,
            provider: 'google',
            engine: 'natural_language',
        );

        expect($result->isSafe())->toBeFalse();
        expect($result->isUnsafe())->toBeTrue();
        expect($result->flags())->toHaveCount(2);
        expect($result->confidence())->toBe(0.9);
    });

    it('filters api flags correctly', function () {
        $flags = [
            new FlaggedTerm('toxic', 'toxic', 'high', 0.9, 'api'),
            new FlaggedTerm('blocked', 'blocklist', 'high', null, 'blocklist'),
        ];

        $result = new ModerationResult(false, 0.9, $flags, 'google', 'natural_language');

        expect($result->apiFlags())->toHaveCount(1);
        expect(array_values($result->apiFlags())[0]->term)->toBe('toxic');
    });

    it('filters blocklist flags correctly', function () {
        $flags = [
            new FlaggedTerm('toxic', 'toxic', 'high', 0.9, 'api'),
            new FlaggedTerm('blocked', 'blocklist', 'high', null, 'blocklist'),
        ];

        $result = new ModerationResult(false, 0.9, $flags, 'google', 'natural_language');

        expect($result->blocklistFlags())->toHaveCount(1);
        expect(array_values($result->blocklistFlags())[0]->term)->toBe('blocked');
    });

    it('filters high severity flags correctly', function () {
        $flags = [
            new FlaggedTerm('severe', 'toxic', 'high', 0.95, 'api'),
            new FlaggedTerm('mild', 'profanity', 'low', 0.5, 'api'),
        ];

        $result = new ModerationResult(false, 0.95, $flags, 'google', 'natural_language');

        expect($result->highSeverityFlags())->toHaveCount(1);
        expect($result->hasHighSeverityFlags())->toBeTrue();
    });

    it('groups flags by category', function () {
        $flags = [
            new FlaggedTerm('term1', 'toxic', 'high', 0.9, 'api'),
            new FlaggedTerm('term2', 'toxic', 'medium', 0.7, 'api'),
            new FlaggedTerm('term3', 'violence', 'high', 0.8, 'api'),
        ];

        $result = new ModerationResult(false, 0.9, $flags, 'google', 'natural_language');
        $grouped = $result->flagsByCategory();

        expect($grouped)->toHaveKey('toxic');
        expect($grouped)->toHaveKey('violence');
        expect($grouped['toxic'])->toHaveCount(2);
        expect($grouped['violence'])->toHaveCount(1);
    });

    it('merges two results correctly', function () {
        $result1 = new ModerationResult(
            isSafe: true,
            confidence: 0.9,
            flags: [],
            provider: 'google',
            engine: 'natural_language',
        );

        $result2 = new ModerationResult(
            isSafe: false,
            confidence: null,
            flags: [new FlaggedTerm('blocked', 'blocklist', 'high', null, 'blocklist')],
            provider: 'blocklist',
            engine: 'blocklist',
        );

        $merged = $result1->merge($result2);

        expect($merged->isSafe())->toBeFalse();
        expect($merged->flags())->toHaveCount(1);
        expect($merged->provider())->toBe('google'); // Keeps original provider
    });

    it('converts to array correctly', function () {
        $flags = [new FlaggedTerm('toxic', 'toxic', 'high', 0.9, 'api')];
        $result = new ModerationResult(false, 0.9, $flags, 'google', 'natural_language');

        $array = $result->toArray();

        expect($array)->toHaveKey('is_safe');
        expect($array)->toHaveKey('confidence');
        expect($array)->toHaveKey('flags');
        expect($array)->toHaveKey('provider');
        expect($array)->toHaveKey('engine');
        expect($array['is_safe'])->toBeFalse();
        expect($array['flags'])->toHaveCount(1);
    });

    it('serializes to json correctly', function () {
        $result = ModerationResult::safe('google', 'vision');
        $json = json_encode($result);

        expect($json)->toBeString();
        expect($json)->toContain('"is_safe":true');
        expect($json)->toContain('"provider":"google"');
    });
});
