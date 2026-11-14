<?php

namespace App\Console\Commands;

use App\Models\Film;
use App\Services\Images\CoverThumbnail;
use Illuminate\Console\Command;

class MakeCoverThumbnails extends Command
{
    protected $signature = 'films:thumbs {--force : Пересоздать и те превью, что уже есть}';

    protected $description = 'Нарезать превью постеров (WebP) для карточек и сеток';

    public function handle(CoverThumbnail $thumbnails): int
    {
        $query = Film::query()->whereNotNull('cover')->where('cover', '!=', '');

        if (!$this->option('force'))
            $query->whereNull('cover_thumb');

        $total = $query->count();
        $made  = 0;
        $bar   = $this->output->createProgressBar($total);

        $query->orderBy('id')->chunkById(100, function ($films) use ($thumbnails, &$made, $bar) {
            foreach ($films as $film) {
                if ($path = $thumbnails->make($film->cover)) {
                    // Мимо наблюдателя и без смены updated_at — это служебное поле.
                    Film::whereKey($film->id)->update(['cover_thumb' => $path]);
                    $made++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Превью: сделано {$made} из {$total}.");

        if ($made < $total)
            $this->warn('Пропущенные — без файла постера или с битой картинкой (подробности в laravel.log).');

        return self::SUCCESS;
    }
}
