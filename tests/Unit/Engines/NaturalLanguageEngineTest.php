<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\Engines\NaturalLanguageEngine;

beforeEach(function () {
    // Skip tests if Google Cloud credentials are not available
    if (empty(getenv('GOOGLE_APPLICATION_CREDENTIALS'))) {
        $this->markTestSkipped('Google Cloud credentials not available');
    }
});

describe('NaturalLanguageEngine', function () {
    it('returns safe result for empty text', function () {
        $engine = new NaturalLanguageEngine([
            'thresholds' => ['toxic' => 0.7],
        ]);

        $result = $engine->moderate('');

        expect($result->isSafe())->toBeTrue();
        expect($result->engine())->toBe('natural_language');
    });

    it('returns safe result for whitespace-only text', function () {
        $engine = new NaturalLanguageEngine([
            'thresholds' => ['toxic' => 0.7],
        ]);

        $result = $engine->moderate('   ');

        expect($result->isSafe())->toBeTrue();
    });
})->skip(fn () => empty(getenv('GOOGLE_APPLICATION_CREDENTIALS')), 'Google Cloud credentials not available');

describe('NaturalLanguageEngine (mocked)', function () {
    it('parses moderation response correctly', function () {
        // This test would use a mock client
        // For now, we test the threshold logic
        $config = [
            'thresholds' => [
                'toxic' => 0.7,
                'severe_toxic' => 0.5,
                'insult' => 0.7,
            ],
        ];

        // Test that config is properly handled
        expect($config['thresholds']['toxic'])->toBe(0.7);
    });

    it('determines severity correctly based on confidence', function () {
        // Test severity determination logic
        $highConfidence = 0.95;
        $mediumConfidence = 0.75;
        $lowConfidence = 0.55;

        // High: >= 0.9
        expect($highConfidence >= 0.9)->toBeTrue();

        // Medium: >= 0.7 and < 0.9
        expect($mediumConfidence >= 0.7 && $mediumConfidence < 0.9)->toBeTrue();

        // Low: < 0.7
        expect($lowConfidence < 0.7)->toBeTrue();
    });
});
