<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Rules\ModeratedText;

it('validates safe text', function () {
    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('text')
        ->once()
        ->with('safe text')
        ->andReturn(ModerationResult::safe('google', 'text'));

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedText;
    $fail = function ($message) {
        $this->fail("Validation failed: $message");
    };

    $rule->validate('attribute', 'safe text', $fail);
    expect(true)->toBeTrue(); // Assertion to ensure no exception
});

it('fails unsafe text', function () {
    $flag = new FlaggedTerm('bad', 'profanity', 'high', 1.0, 'google');
    $result = new ModerationResult(false, 1.0, [$flag], 'google', 'text');

    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('text')
        ->once()
        ->with('unsafe text')
        ->andReturn($result);

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedText;
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('The :attribute contains unsafe content (profanity).');
    };

    $rule->validate('attribute', 'unsafe text', $fail);
    expect($failed)->toBeTrue();
});

it('fails unsafe text with multiple categories', function () {
    $flag1 = new FlaggedTerm('bad', 'profanity', 'high', 1.0, 'google');
    $flag2 = new FlaggedTerm('worse', 'violence', 'high', 1.0, 'google');
    $result = new ModerationResult(false, 1.0, [$flag1, $flag2], 'google', 'text');

    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('text')
        ->once()
        ->with('unsafe text')
        ->andReturn($result);

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedText;
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('The :attribute contains unsafe content (profanity, violence).');
    };

    $rule->validate('attribute', 'unsafe text', $fail);
    expect($failed)->toBeTrue();
});

it('fails with custom message', function () {
    $flag = new FlaggedTerm('bad', 'profanity', 'high', 1.0, 'google');
    $result = new ModerationResult(false, 1.0, [$flag], 'google', 'text');

    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('text')
        ->once()
        ->with('unsafe text')
        ->andReturn($result);

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedText('Custom validation message.');
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('Custom validation message.');
    };

    $rule->validate('attribute', 'unsafe text', $fail);
    expect($failed)->toBeTrue();
});

it('fails if value is not string', function () {
    $rule = new ModeratedText;
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('The :attribute must be a string.');
    };

    $rule->validate('attribute', 123, $fail);
    expect($failed)->toBeTrue();
});
