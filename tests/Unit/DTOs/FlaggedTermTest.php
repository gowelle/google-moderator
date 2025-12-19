<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\DTOs\FlaggedTerm;

describe('FlaggedTerm DTO', function () {
    it('creates a flagged term with all properties', function () {
        $term = new FlaggedTerm(
            term: 'test_term',
            category: 'toxic',
            severity: 'high',
            confidence: 0.95,
            source: 'api',
        );

        expect($term->term)->toBe('test_term');
        expect($term->category)->toBe('toxic');
        expect($term->severity)->toBe('high');
        expect($term->confidence)->toBe(0.95);
        expect($term->source)->toBe('api');
    });

    it('defaults source to api', function () {
        $term = new FlaggedTerm(
            term: 'test',
            category: 'profanity',
            severity: 'medium',
        );

        expect($term->source)->toBe('api');
        expect($term->confidence)->toBeNull();
    });

    it('identifies api source correctly', function () {
        $term = new FlaggedTerm(
            term: 'test',
            category: 'toxic',
            severity: 'high',
            source: 'api',
        );

        expect($term->isFromApi())->toBeTrue();
        expect($term->isFromBlocklist())->toBeFalse();
    });

    it('identifies blocklist source correctly', function () {
        $term = new FlaggedTerm(
            term: 'blocked_word',
            category: 'blocklist',
            severity: 'high',
            source: 'blocklist',
        );

        expect($term->isFromBlocklist())->toBeTrue();
        expect($term->isFromApi())->toBeFalse();
    });

    it('identifies high severity correctly', function () {
        $highTerm = new FlaggedTerm('test', 'toxic', 'high');
        $mediumTerm = new FlaggedTerm('test', 'toxic', 'medium');
        $lowTerm = new FlaggedTerm('test', 'toxic', 'low');

        expect($highTerm->isHighSeverity())->toBeTrue();
        expect($mediumTerm->isHighSeverity())->toBeFalse();
        expect($lowTerm->isHighSeverity())->toBeFalse();
    });

    it('converts to array correctly', function () {
        $term = new FlaggedTerm(
            term: 'test_term',
            category: 'violence',
            severity: 'medium',
            confidence: 0.8,
            source: 'api',
        );

        $array = $term->toArray();

        expect($array)->toBe([
            'term' => 'test_term',
            'category' => 'violence',
            'severity' => 'medium',
            'confidence' => 0.8,
            'source' => 'api',
        ]);
    });
});
