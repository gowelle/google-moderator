<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\DTOs\FlaggedTerm;
use Gowelle\GoogleModerator\DTOs\ModerationResult;
use Gowelle\GoogleModerator\Rules\ModeratedImage;
use Illuminate\Http\UploadedFile;

it('validates safe image path', function () {
    $path = '/tmp/safe.jpg';
    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('image')
        ->once()
        ->with($path)
        ->andReturn(ModerationResult::safe('google', 'vision'));

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedImage;
    $fail = function ($message) {
        $this->fail("Validation failed: $message");
    };

    $rule->validate('attribute', $path, $fail);
    expect(true)->toBeTrue();
});

it('validates safe uploaded file', function () {
    $file = UploadedFile::fake()->image('safe.jpg');

    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('image')
        ->once()
        ->withArgs(function ($arg) use ($file) {
            return $arg === $file->getPathname();
        })
        ->andReturn(ModerationResult::safe('google', 'vision'));

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedImage;
    $fail = function ($message) {
        $this->fail("Validation failed: $message");
    };

    $rule->validate('attribute', $file, $fail);
    expect(true)->toBeTrue();
});

it('fails unsafe image', function () {
    $path = '/tmp/unsafe.jpg';
    $flag = new FlaggedTerm('bad', 'adult', 'high', 1.0, 'google');
    $result = new ModerationResult(false, 1.0, [$flag], 'google', 'vision');

    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('image')
        ->once()
        ->with($path)
        ->andReturn($result);

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedImage;
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('The :attribute contains unsafe media (adult).');
    };

    $rule->validate('attribute', $path, $fail);
    expect($failed)->toBeTrue();
});

it('fails with custom message for image', function () {
    $path = '/tmp/unsafe.jpg';
    $flag = new FlaggedTerm('bad', 'adult', 'high', 1.0, 'google');
    $result = new ModerationResult(false, 1.0, [$flag], 'google', 'vision');

    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('image')
        ->once()
        ->with($path)
        ->andReturn($result);

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedImage('No bad images!');
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('No bad images!');
    };

    $rule->validate('attribute', $path, $fail);
    expect($failed)->toBeTrue();
});

it('fails if not a valid image source', function () {
    $rule = new ModeratedImage;
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('The :attribute must be a valid image.');
    };

    $rule->validate('attribute', 123, $fail);
    expect($failed)->toBeTrue();
});

it('fails if moderation throws exception', function () {
    $path = '/tmp/error.jpg';
    $mock = Mockery::mock(Gowelle\GoogleModerator\Services\ModerationManager::class);
    $mock->shouldReceive('image')
        ->once()
        ->with($path)
        ->andThrow(new Exception('API Error'));

    $this->instance(Gowelle\GoogleModerator\Services\ModerationManager::class, $mock);

    $rule = new ModeratedImage;
    $failed = false;
    $fail = function ($message) use (&$failed) {
        $failed = true;
        expect($message)->toBe('Unable to validate :attribute: API Error');
    };

    $rule->validate('attribute', $path, $fail);
    expect($failed)->toBeTrue();
});
