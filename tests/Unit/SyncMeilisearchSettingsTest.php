<?php

declare(strict_types=1);

use App\Console\Commands\SyncMeilisearchSettings;

it('buildSynonyms returns bidirectional mappings for each word in a group', function (): void {
    $method = new ReflectionMethod(SyncMeilisearchSettings::class, 'buildSynonyms');
    $synonyms = $method->invoke(new SyncMeilisearchSettings);

    expect($synonyms)->toBeArray();

    // studio ↔ garçonnière ↔ chambre garçonnière
    expect($synonyms)->toHaveKey('studio')
        ->and($synonyms['studio'])->toContain('garçonnière')
        ->and($synonyms['studio'])->toContain('chambre garçonnière');

    expect($synonyms)->toHaveKey('garçonnière')
        ->and($synonyms['garçonnière'])->toContain('studio');

    // villa ↔ maison
    expect($synonyms)->toHaveKey('villa')
        ->and($synonyms['villa'])->toContain('maison');

    expect($synonyms)->toHaveKey('maison')
        ->and($synonyms['maison'])->toContain('villa');

    // parking ↔ garage
    expect($synonyms)->toHaveKey('parking')
        ->and($synonyms['parking'])->toContain('garage');
});

it('buildSynonyms never maps a word to itself', function (): void {
    $method = new ReflectionMethod(SyncMeilisearchSettings::class, 'buildSynonyms');
    $synonyms = $method->invoke(new SyncMeilisearchSettings);

    foreach ($synonyms as $word => $targets) {
        expect($targets)->not->toContain($word);
    }
});

it('buildSynonyms returns non-empty arrays for all entries', function (): void {
    $method = new ReflectionMethod(SyncMeilisearchSettings::class, 'buildSynonyms');
    $synonyms = $method->invoke(new SyncMeilisearchSettings);

    foreach ($synonyms as $word => $targets) {
        expect($targets)
            ->toBeArray()
            ->not->toBeEmpty("Synonyms for '{$word}' should not be empty");
    }
});
