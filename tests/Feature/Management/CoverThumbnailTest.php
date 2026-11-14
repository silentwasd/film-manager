<?php

namespace Tests\Feature\Management;

use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('imagewebp'))
            $this->markTestSkipped('GD без WebP');

        Storage::fake('public');
    }

    public function test_uploaded_cover_gets_webp_thumbnail(): void
    {
        $user = User::factory()->create();
        $film = Film::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
             ->put("/api/management/films/{$film->id}", [
                 'name'   => $film->name,
                 'format' => 'film',
                 'cover'  => UploadedFile::fake()->image('poster.jpg', 1000, 1500),
             ], ['Accept' => 'application/json'])
             ->assertOk();

        $film->refresh();

        $this->assertNotNull($film->cover_thumb);
        $this->assertStringStartsWith('films-thumb/', $film->cover_thumb);
        Storage::disk('public')->assertExists($film->cover_thumb);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($film->cover_thumb));
        $this->assertSame(360, $width);
        $this->assertSame(540, $height);

        $this->getJson("/api/management/films/{$film->id}")
             ->assertJsonPath('data.cover_thumb', $film->cover_thumb);
    }

    public function test_small_cover_is_not_upscaled(): void
    {
        Storage::disk('public')->put('films/small.jpg', UploadedFile::fake()->image('small.jpg', 200, 300)->getContent());

        $film = Film::factory()->create(['cover' => 'films/small.jpg']);

        [$width] = getimagesizefromstring(Storage::disk('public')->get($film->cover_thumb));
        $this->assertSame(200, $width);
    }

    public function test_thumbnail_passed_together_with_cover_is_kept(): void
    {
        // Так переносит карточки на прод tools/kinopoisk-transfer: превью уже готово.
        $film = Film::factory()->create(['cover' => 'films/missing.jpg', 'cover_thumb' => 'films-thumb/ready.webp']);

        $this->assertSame('films-thumb/ready.webp', $film->cover_thumb);
    }

    public function test_missing_cover_file_leaves_thumbnail_empty(): void
    {
        $film = Film::factory()->create(['cover' => 'films/missing.jpg']);

        $this->assertNull($film->cover_thumb);
    }

    public function test_command_backfills_missing_thumbnails(): void
    {
        Storage::disk('public')->put('films/old.jpg', UploadedFile::fake()->image('old.jpg', 800, 1200)->getContent());

        Film::factory()->create(['cover' => 'films/old.jpg']);
        Film::query()->update(['cover_thumb' => null]);

        $this->artisan('films:thumbs')->assertSuccessful();

        $thumb = Film::first()->cover_thumb;
        $this->assertSame('films-thumb/old.webp', $thumb);
        Storage::disk('public')->assertExists($thumb);
    }
}
