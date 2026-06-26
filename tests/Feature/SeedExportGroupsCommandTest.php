<?php

use App\Models\Audio;
use App\Models\Export;
use App\Models\File;
use App\Models\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('groups already-exported audios into legacy and file_id=8 exports', function () {
    // Create 8 files so a file with id 8 exists for the FK.
    $files = collect(range(1, 8))->map(fn () => File::factory()->create());
    $legacyText = Text::factory()->create(['file_id' => $files[2]->id]);  // file_id 3
    $currentText = Text::factory()->create(['file_id' => $files[7]->id]); // file_id 8

    $legacyAudio = Audio::factory()->create([
        'text_id' => $legacyText->id,
        'edit_converted_audio_duration' => 100,
        'exported_at' => now(),
    ]);

    $currentAudio = Audio::factory()->create([
        'text_id' => $currentText->id,
        'edit_converted_audio_duration' => 100,
        'exported_at' => now(),
    ]);

    $notExportedYet = Audio::factory()->create([
        'text_id' => $currentText->id,
        'edit_converted_audio_duration' => null,
        'exported_at' => null,
    ]);

    Artisan::call('export:seed-groups');

    $legacy = Export::where('name', 'Legacy (file_id < 8)')->sole();
    $current = Export::where('name', 'file_id = 8')->sole();

    expect($legacyAudio->fresh()->export_id)->toBe($legacy->id)
        ->and($currentAudio->fresh()->export_id)->toBe($current->id)
        ->and($notExportedYet->fresh()->export_id)->toBeNull()
        ->and($legacy->fresh()->exported_count)->toBe(1)
        ->and($current->fresh()->exported_count)->toBe(1);
});

it('is idempotent and does not duplicate exports or reassign on a second run', function () {
    $files = collect(range(1, 8))->map(fn () => File::factory()->create());
    $text = Text::factory()->create(['file_id' => $files[7]->id]);

    $audio = Audio::factory()->create([
        'text_id' => $text->id,
        'edit_converted_audio_duration' => 100,
        'exported_at' => now(),
    ]);

    Artisan::call('export:seed-groups');
    $firstExportId = $audio->fresh()->export_id;

    Artisan::call('export:seed-groups');

    expect(Export::count())->toBe(2)
        ->and($audio->fresh()->export_id)->toBe($firstExportId);
});
