<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\PlatformService;
use App\Support\Modules;
use App\Support\Platform;
use Illuminate\Console\Command;

/**
 * Same as Platform Settings in the admin panel, for servers and first setup:
 *   php artisan worktrack:platform:mode single --main=worktrack-demo
 *   php artisan worktrack:platform:mode multi
 */
class SetPlatformMode extends Command
{
    protected $signature = 'worktrack:platform:mode
                            {mode : single or multi}
                            {--main= : slug of the main organisation (default: the current one)}';

    protected $description = 'Switch between single company and multiple companies mode';

    public function handle(PlatformService $platform): int
    {
        $mode = strtolower((string) $this->argument('mode'));

        if (!in_array($mode, [Platform::SINGLE, Platform::MULTI], true)) {
            $this->error('Mode must be "single" or "multi".');

            return self::FAILURE;
        }

        $slug = $this->option('main');
        $main = $slug ? Organisation::query()->where('slug', $slug)->first() : Platform::mainOrganisation();

        if (!$main) {
            $this->error($slug ? "No organisation with the slug \"{$slug}\"." : 'There is no organisation yet.');

            return self::FAILURE;
        }

        $others = $mode === Platform::SINGLE ? $platform->othersActive($main) : collect();

        if ($others->isNotEmpty() && !$this->confirm('These organisations will be made inactive (not deleted): ' . $others->pluck('name')->join(', ') . '. Continue?', true)) {
            return self::FAILURE;
        }

        $platform->apply($mode, $main);

        $this->info(($mode === Platform::SINGLE ? 'Single company mode' : 'Multiple companies mode') . ". Main organisation: {$main->name}.");
        $this->line('Its modules: ' . (collect(Modules::enabledFor($main))->map(fn ($m) => Modules::label($m))->join(', ') ?: 'core only'));

        return self::SUCCESS;
    }
}
