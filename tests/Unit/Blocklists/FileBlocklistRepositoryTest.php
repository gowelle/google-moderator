<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\Blocklists\FileBlocklistRepository;
use Gowelle\GoogleModerator\DTOs\BlocklistTerm;

beforeEach(function () {
    // Create a temporary blocklist directory
    $this->tempDir = sys_get_temp_dir().'/google-moderator-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->config = [
        'blocklists' => [
            'file_path' => $this->tempDir,
        ],
        'cache' => [
            'enabled' => false,
            'ttl' => 3600,
            'prefix' => 'test_',
        ],
    ];
});

afterEach(function () {
    // Clean up temporary files
    $files = glob($this->tempDir.'/*');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($this->tempDir);
});

describe('FileBlocklistRepository', function () {
    it('returns empty array for non-existent language file', function () {
        $repo = new FileBlocklistRepository($this->config);

        $terms = $repo->getTerms('nonexistent');

        expect($terms)->toBeEmpty();
    });

    it('reads terms from JSON file', function () {
        $data = [
            'language' => 'en',
            'terms' => [
                ['value' => 'badword', 'severity' => 'high', 'is_regex' => false],
                ['value' => '*offensive*', 'severity' => 'medium', 'is_regex' => false],
            ],
        ];

        file_put_contents(
            $this->tempDir.'/en.json',
            json_encode($data),
        );

        $repo = new FileBlocklistRepository($this->config);
        $terms = $repo->getTerms('en');

        expect($terms)->toHaveCount(2);
        expect($terms[0])->toBeInstanceOf(BlocklistTerm::class);
        expect($terms[0]->value)->toBe('badword');
        expect($terms[0]->severity)->toBe('high');
    });

    it('adds a term to the blocklist', function () {
        $repo = new FileBlocklistRepository($this->config);

        $repo->addTerm('sw', 'neno_baya', 'high', false);

        $terms = $repo->getTerms('sw');
        expect($terms)->toHaveCount(1);
        expect($terms[0]->value)->toBe('neno_baya');
    });

    it('does not add duplicate terms', function () {
        $repo = new FileBlocklistRepository($this->config);

        $repo->addTerm('en', 'duplicate', 'high');
        $repo->addTerm('en', 'duplicate', 'medium');

        $terms = $repo->getTerms('en');
        expect($terms)->toHaveCount(1);
    });

    it('removes a term from the blocklist', function () {
        $data = [
            'language' => 'en',
            'terms' => [
                ['value' => 'keep', 'severity' => 'high'],
                ['value' => 'remove', 'severity' => 'medium'],
            ],
        ];

        file_put_contents(
            $this->tempDir.'/en.json',
            json_encode($data),
        );

        $repo = new FileBlocklistRepository($this->config);
        $repo->removeTerm('en', 'remove');

        $terms = $repo->getTerms('en');
        expect($terms)->toHaveCount(1);
        expect($terms[0]->value)->toBe('keep');
    });

    it('checks if term exists', function () {
        $data = [
            'language' => 'en',
            'terms' => [
                ['value' => 'exists', 'severity' => 'high'],
            ],
        ];

        file_put_contents(
            $this->tempDir.'/en.json',
            json_encode($data),
        );

        $repo = new FileBlocklistRepository($this->config);

        expect($repo->hasTerm('en', 'exists'))->toBeTrue();
        expect($repo->hasTerm('en', 'notexists'))->toBeFalse();
    });

    it('lists available languages', function () {
        file_put_contents($this->tempDir.'/en.json', json_encode(['language' => 'en', 'terms' => []]));
        file_put_contents($this->tempDir.'/sw.json', json_encode(['language' => 'sw', 'terms' => []]));

        $repo = new FileBlocklistRepository($this->config);
        $languages = $repo->getLanguages();

        expect($languages)->toContain('en');
        expect($languages)->toContain('sw');
    });
});
