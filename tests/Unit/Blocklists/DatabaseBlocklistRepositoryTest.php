<?php

declare(strict_types=1);

use Gowelle\GoogleModerator\Blocklists\DatabaseBlocklistRepository;
use Gowelle\GoogleModerator\DTOs\BlocklistTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Create the blocklist_terms table
    if (!Schema::hasTable('blocklist_terms')) {
        Schema::create('blocklist_terms', function ($table) {
            $table->id();
            $table->string('language', 10)->index();
            $table->string('value');
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_regex')->default(false);
            $table->timestamps();
            $table->unique(['language', 'value']);
        });
    }

    // Clear the table before each test
    DB::table('blocklist_terms')->truncate();

    $this->config = [
        'blocklists' => [
            'table' => 'blocklist_terms',
        ],
        'cache' => [
            'enabled' => false,
            'ttl' => 3600,
            'prefix' => 'test_',
        ],
    ];
});

describe('DatabaseBlocklistRepository', function () {
    it('returns empty array when no terms exist', function () {
        $repo = new DatabaseBlocklistRepository($this->config);

        $terms = $repo->getTerms('en');

        expect($terms)->toBeEmpty();
    });

    it('retrieves terms from database', function () {
        DB::table('blocklist_terms')->insert([
            ['language' => 'en', 'value' => 'badword', 'severity' => 'high', 'is_regex' => false, 'created_at' => now(), 'updated_at' => now()],
            ['language' => 'en', 'value' => 'offensive', 'severity' => 'medium', 'is_regex' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $repo = new DatabaseBlocklistRepository($this->config);
        $terms = $repo->getTerms('en');

        expect($terms)->toHaveCount(2);
        expect($terms[0])->toBeInstanceOf(BlocklistTerm::class);
    });

    it('adds a term to the database', function () {
        $repo = new DatabaseBlocklistRepository($this->config);

        $repo->addTerm('sw', 'haramu', 'high', false);

        $this->assertDatabaseHas('blocklist_terms', [
            'language' => 'sw',
            'value' => 'haramu',
            'severity' => 'high',
        ]);
    });

    it('updates existing term on duplicate add', function () {
        $repo = new DatabaseBlocklistRepository($this->config);

        $repo->addTerm('en', 'test', 'low');
        $repo->addTerm('en', 'test', 'high');

        $count = DB::table('blocklist_terms')
            ->where('language', 'en')
            ->where('value', 'test')
            ->count();

        expect($count)->toBe(1);

        $term = DB::table('blocklist_terms')
            ->where('language', 'en')
            ->where('value', 'test')
            ->first();

        expect($term->severity)->toBe('high');
    });

    it('removes a term from the database', function () {
        DB::table('blocklist_terms')->insert([
            'language' => 'en',
            'value' => 'toremove',
            'severity' => 'medium',
            'is_regex' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repo = new DatabaseBlocklistRepository($this->config);
        $repo->removeTerm('en', 'toremove');

        $this->assertDatabaseMissing('blocklist_terms', [
            'language' => 'en',
            'value' => 'toremove',
        ]);
    });

    it('checks if term exists', function () {
        DB::table('blocklist_terms')->insert([
            'language' => 'en',
            'value' => 'exists',
            'severity' => 'high',
            'is_regex' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repo = new DatabaseBlocklistRepository($this->config);

        expect($repo->hasTerm('en', 'exists'))->toBeTrue();
        expect($repo->hasTerm('en', 'notexists'))->toBeFalse();
    });

    it('lists available languages', function () {
        DB::table('blocklist_terms')->insert([
            ['language' => 'en', 'value' => 'test1', 'severity' => 'low', 'is_regex' => false, 'created_at' => now(), 'updated_at' => now()],
            ['language' => 'sw', 'value' => 'test2', 'severity' => 'low', 'is_regex' => false, 'created_at' => now(), 'updated_at' => now()],
            ['language' => 'fr', 'value' => 'test3', 'severity' => 'low', 'is_regex' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $repo = new DatabaseBlocklistRepository($this->config);
        $languages = $repo->getLanguages();

        expect($languages)->toContain('en');
        expect($languages)->toContain('sw');
        expect($languages)->toContain('fr');
    });
});
