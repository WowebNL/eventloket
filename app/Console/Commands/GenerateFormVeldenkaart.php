<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OpenForms\Veldenkaart\Loaders\ApiLoader;
use App\Services\OpenForms\Veldenkaart\Loaders\LoaderInterface;
use App\Services\OpenForms\Veldenkaart\Loaders\LocalLoader;
use App\Services\OpenForms\Veldenkaart\Renderers\JsonRenderer;
use App\Services\OpenForms\Veldenkaart\Renderers\MarkdownRenderer;
use App\Services\OpenForms\Veldenkaart\VeldenkaartBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateFormVeldenkaart extends Command
{
    protected $signature = 'forms:veldenkaart
        {--source=api : api|local — data source}
        {--form= : Form slug or UUID (defaults to services.open_forms.main_form_slug)}
        {--output=docs/formulier-veldenkaart.generated.md : Output markdown path (sidecar .json is derived)}
        {--local-path= : Path to the local dump directory (defaults to docker/local-data/open-formulier)}';

    protected $description = 'Generate a complete, machine- and human-readable veldenkaart from Open Forms';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $formIdentifier = (string) ($this->option('form') ?: config('services.open_forms.main_form_slug') ?: '');
        $outputMd = (string) $this->option('output');

        if ($outputMd === '') {
            $this->error('Missing --output path');

            return self::INVALID;
        }
        if ($formIdentifier === '') {
            $this->error('Missing --form argument and no services.open_forms.main_form_slug configured');

            return self::INVALID;
        }

        $loader = $this->buildLoader($source);
        if ($loader === null) {
            return self::INVALID;
        }

        $this->info("Loading form '{$formIdentifier}' from {$loader->sourceLabel()}...");
        $raw = $loader->load($formIdentifier);
        $this->line(sprintf(
            '  • %d steps, %d logic rules, %d form variables',
            count($raw->formSteps),
            count($raw->logicRules),
            count($raw->variables),
        ));

        $builder = new VeldenkaartBuilder;
        $data = $builder->build($raw);

        $this->info(sprintf(
            '  • %d fields (excl. content), %d logic actions across %d rules',
            $data->totalFieldCount(),
            $data->totalActionCount(),
            count($data->logicRules),
        ));

        $markdown = (new MarkdownRenderer)->render($data);
        $json = (new JsonRenderer)->render($data);

        $outputMdAbs = $this->absolute($outputMd);
        $outputJsonAbs = $this->replaceSuffix($outputMdAbs, '.md', '.json');

        File::ensureDirectoryExists(dirname($outputMdAbs));
        File::put($outputMdAbs, $markdown);
        File::put($outputJsonAbs, $json);

        $this->info('Veldenkaart geschreven:');
        $this->line('  md:   '.$this->relative($outputMdAbs));
        $this->line('  json: '.$this->relative($outputJsonAbs));

        return self::SUCCESS;
    }

    private function buildLoader(string $source): ?LoaderInterface
    {
        return match ($source) {
            'api' => $this->buildApiLoader(),
            'local' => $this->buildLocalLoader(),
            default => tap(null, fn () => $this->error("Unknown --source value: {$source} (expected: api|local)")),
        };
    }

    private function buildApiLoader(): ?ApiLoader
    {
        $baseUrl = (string) config('services.open_forms.base_url');
        $token = (string) config('services.open_forms.admin_token');

        if ($baseUrl === '') {
            $this->error('services.open_forms.base_url is not configured (set OPEN_FORMS_BASE_URL)');

            return null;
        }
        if ($token === '') {
            $this->error('services.open_forms.admin_token is not configured (set OPEN_FORMS_ADMIN_TOKEN). Generate via:');
            $this->line('  docker compose exec open-forms python manage.py drf_create_token <username>');

            return null;
        }

        return new ApiLoader($baseUrl, $token);
    }

    private function buildLocalLoader(): LocalLoader
    {
        $path = (string) ($this->option('local-path') ?: base_path('docker/local-data/open-formulier'));

        return new LocalLoader($path);
    }

    private function absolute(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private function replaceSuffix(string $path, string $oldSuffix, string $newSuffix): string
    {
        if (str_ends_with($path, $oldSuffix)) {
            return substr($path, 0, -strlen($oldSuffix)).$newSuffix;
        }

        return $path.$newSuffix;
    }

    private function relative(string $abs): string
    {
        $base = base_path().'/';
        if (str_starts_with($abs, $base)) {
            return substr($abs, strlen($base));
        }

        return $abs;
    }
}
