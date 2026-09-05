<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProtectPaperFiles extends Command
{
    protected $signature = 'icoman:protect-papers';
    protected $description = 'Move existing full papers to private storage, verifying bytes before removing public copies.';

    public function handle(): int
    {
        foreach (Media::where('model_type', (new \App\Models\Submission)->getMorphClass())->where('collection_name', 'camera_ready')->get() as $media) {
            $path = $media->getPathRelativeToRoot();
            if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, ':')) {
                throw new \RuntimeException('Unsafe media path.');
            }
            $destination = Storage::disk('papers');
            if ($media->disk !== 'papers') {
                $source = Storage::disk($media->disk);
                $bytes = $source->get($path);
                if (! is_string($bytes) || ! $destination->put($path, $bytes)
                    || hash('sha256', $bytes) !== hash('sha256', $destination->get($path))) {
                    throw new \RuntimeException('Could not verify private copy for media '.$media->id);
                }
                $media->update(['disk' => 'papers', 'conversions_disk' => 'papers']);
                if (! $source->delete($path)) {
                    throw new \RuntimeException('Could not remove original file for media '.$media->id);
                }
            }
            // Finish cleanup after an interrupted previous run, only when bytes match.
            $public = Storage::disk('public');
            if ($public->exists($path)) {
                if (! $destination->exists($path) || hash('sha256', $public->get($path)) !== hash('sha256', $destination->get($path))) {
                    throw new \RuntimeException('Public/private copy mismatch for media '.$media->id);
                }
                if (! $public->delete($path)) {
                    throw new \RuntimeException('Public copy cleanup failed for media '.$media->id);
                }
            }
        }
        $this->info('Full papers verified in private storage; matching public originals removed.');

        return self::SUCCESS;
    }
}
