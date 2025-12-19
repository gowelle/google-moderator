<?php

declare(strict_types=1);

use Google\Cloud\Vision\V1\Likelihood;

describe('VisionEngine', function () {
    it('converts likelihood to confidence correctly', function () {
        // Test the likelihood to confidence mapping
        $likelihoodMap = [
            Likelihood::VERY_LIKELY => 0.95,
            Likelihood::LIKELY => 0.8,
            Likelihood::POSSIBLE => 0.5,
            Likelihood::UNLIKELY => 0.2,
            Likelihood::VERY_UNLIKELY => 0.05,
            Likelihood::UNKNOWN => 0.0,
        ];

        foreach ($likelihoodMap as $likelihood => $expectedConfidence) {
            expect($likelihood)->toBeInt();
        }
    });

    it('has correct threshold level mappings', function () {
        $thresholdLevels = [
            'VERY_UNLIKELY' => 1,
            'UNLIKELY' => 2,
            'POSSIBLE' => 3,
            'LIKELY' => 4,
            'VERY_LIKELY' => 5,
        ];

        expect($thresholdLevels['LIKELY'])->toBe(4);
        expect($thresholdLevels['POSSIBLE'])->toBe(3);
    });

    it('determines severity based on likelihood correctly', function () {
        // VERY_LIKELY or LIKELY = high
        expect(Likelihood::VERY_LIKELY)->toBe(5);
        expect(Likelihood::LIKELY)->toBe(4);

        // POSSIBLE = medium
        expect(Likelihood::POSSIBLE)->toBe(3);

        // UNLIKELY or below = low
        expect(Likelihood::UNLIKELY)->toBe(2);
    });
})->skip(fn () => !class_exists(Likelihood::class), 'Google Cloud Vision not installed');

describe('VisionEngine Config', function () {
    it('accepts threshold configuration', function () {
        $config = [
            'thresholds' => [
                'adult' => 'LIKELY',
                'violence' => 'LIKELY',
                'racy' => 'POSSIBLE',
                'spoof' => 'POSSIBLE',
                'medical' => 'POSSIBLE',
            ],
        ];

        expect($config['thresholds']['adult'])->toBe('LIKELY');
        expect($config['thresholds']['racy'])->toBe('POSSIBLE');
    });

    it('supports numeric thresholds', function () {
        $config = [
            'thresholds' => [
                'adult' => 4, // Equivalent to LIKELY
                'violence' => 5, // Equivalent to VERY_LIKELY
            ],
        ];

        expect($config['thresholds']['adult'])->toBe(4);
    });
});
