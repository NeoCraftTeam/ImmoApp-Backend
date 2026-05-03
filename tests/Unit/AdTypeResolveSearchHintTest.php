<?php

declare(strict_types=1);

use App\Models\AdType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves appartement meublé when the query mentions meublé and the hint is generic', function (): void {
    $simple = AdType::factory()->create(['name' => 'appartement simple']);
    $meuble = AdType::factory()->create(['name' => 'appartement meublé']);

    $picked = AdType::resolveFromNaturalSearchHint('appartement', 'appartement meublé a douala 150000', null);

    expect($picked)->not->toBeNull()
        ->and($picked->id)->toBe($meuble->id)
        ->and($picked->id)->not->toBe($simple->id);
});

it('resolves appartement simple when the query mentions simple', function (): void {
    $simple = AdType::factory()->create(['name' => 'appartement simple']);
    $meuble = AdType::factory()->create(['name' => 'appartement meublé']);

    $picked = AdType::resolveFromNaturalSearchHint('appartement', 'appartement simple a douala', null);

    expect($picked)->not->toBeNull()
        ->and($picked->id)->toBe($simple->id)
        ->and($picked->id)->not->toBe($meuble->id);
});

it('prefers non meublé rows for bare appartement when both exist', function (): void {
    $simple = AdType::factory()->create(['name' => 'appartement simple']);
    $meuble = AdType::factory()->create(['name' => 'appartement meublé']);

    $picked = AdType::resolveFromNaturalSearchHint('appartement', 'appartement a douala', null);

    expect($picked)->not->toBeNull()
        ->and($picked->id)->toBe($simple->id)
        ->and($picked->id)->not->toBe($meuble->id);
});
