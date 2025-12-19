<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\DTOs\BlocklistTerm;

describe('BlocklistTerm DTO', function () {
    it('creates a blocklist term with all properties', function () {
        $term = new BlocklistTerm(
            language: 'sw',
            value: 'matusi',
            severity: 'high',
            isRegex: false,
        );

        expect($term->language)->toBe('sw');
        expect($term->value)->toBe('matusi');
        expect($term->severity)->toBe('high');
        expect($term->isRegex)->toBeFalse();
    });

    it('defaults severity to medium and isRegex to false', function () {
        $term = new BlocklistTerm(
            language: 'en',
            value: 'test',
        );

        expect($term->severity)->toBe('medium');
        expect($term->isRegex)->toBeFalse();
    });

    it('matches exact word with word boundary', function () {
        $term = new BlocklistTerm('en', 'bad');

        expect($term->matches('This is bad content'))->toBeTrue();
        expect($term->matches('This is badass content'))->toBeFalse(); // Should not match partial
        expect($term->matches('bad'))->toBeTrue();
        expect($term->matches('BAD'))->toBeTrue(); // Case insensitive
    });

    it('matches wildcard patterns', function () {
        $term = new BlocklistTerm('en', '*offensive*');

        expect($term->matches('This is very offensive content'))->toBeTrue();
        expect($term->matches('offensive'))->toBeTrue();
        expect($term->matches('offensiveness'))->toBeTrue();
    });

    it('matches regex patterns', function () {
        $term = new BlocklistTerm(
            language: 'en',
            value: '/\b(bad|terrible)\b/i',
            severity: 'high',
            isRegex: true,
        );

        expect($term->matches('This is bad'))->toBeTrue();
        expect($term->matches('This is terrible'))->toBeTrue();
        expect($term->matches('This is good'))->toBeFalse();
    });

    it('converts to array correctly', function () {
        $term = new BlocklistTerm('sw', 'haramu', 'high', false);

        expect($term->toArray())->toBe([
            'language' => 'sw',
            'value' => 'haramu',
            'severity' => 'high',
            'is_regex' => false,
        ]);
    });

    it('creates from array correctly', function () {
        $data = [
            'value' => 'matusi',
            'severity' => 'high',
            'is_regex' => false,
        ];

        $term = BlocklistTerm::fromArray($data, 'sw');

        expect($term->language)->toBe('sw');
        expect($term->value)->toBe('matusi');
        expect($term->severity)->toBe('high');
        expect($term->isRegex)->toBeFalse();
    });

    it('handles isRegex camelCase in fromArray', function () {
        $data = [
            'value' => 'pattern',
            'isRegex' => true,
        ];

        $term = BlocklistTerm::fromArray($data, 'en');

        expect($term->isRegex)->toBeTrue();
    });

    it('supports Swahili text matching', function () {
        $term = new BlocklistTerm('sw', 'vibaya');

        expect($term->matches('Hii ni vibaya sana'))->toBeTrue();
        expect($term->matches('Vibaya kabisa'))->toBeTrue();
    });
});
