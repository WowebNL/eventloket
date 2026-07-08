<?php

declare(strict_types=1);

namespace App\Console\Commands\Register;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Writes the deploy-time version file that the /__version route reads.
 *
 * Run this during deployment (see deploy-to-staging.yml in the sla-vrzl repo).
 * The deploy knows the tag it is releasing, so pass it with --tag; the sha and
 * branch are read from git when not given. Only deploy-time facts live here; the
 * route computes php, framework, app_env, composer.lock hash and OS at request
 * time. Keeping git out of the request path means no exec() on a web request.
 *
 * The file is written to config('register.version_file'), which sits outside
 * public/ so it is never web-served.
 */
class BuildVersionFile extends Command
{
    protected $signature = 'register:build-version
        {--tag= : The release tag (defaults to `git describe`)}
        {--sha= : The commit sha (defaults to `git rev-parse HEAD`)}
        {--branch= : The branch (defaults to git; a tag deploy is detached, so null)}
        {--node= : The Node.js version (defaults to `node -v`; omitted if unknown)}';

    protected $description = 'Write the deploy-time version.json for the /__version endpoint';

    public function handle(): int
    {
        $tag = $this->stringOption('tag') ?? $this->git(['describe', '--tags', '--always']);
        $sha = $this->stringOption('sha') ?? $this->git(['rev-parse', 'HEAD']);
        $branch = $this->stringOption('branch') ?? $this->currentBranch();
        $node = $this->stringOption('node') ?? $this->nodeVersion();

        $data = [
            'git_tag' => $tag,
            'git_sha' => $sha,
            'branch' => $branch,
            'deployed_at' => now()->toIso8601String(),
        ];

        // Only include nodejs when we actually know it (see decision: omitted by
        // default on the server, but honoured when passed or detectable).
        if ($node !== null) {
            $data['nodejs'] = $node;
        }

        $path = (string) config('register.version_file');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info("Wrote {$path}");
        $this->line('  '.json_encode($data, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** A non-empty trimmed option value, or null. */
    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Run a git subcommand in the app root; null on any failure or empty output. */
    private function git(array $args): ?string
    {
        $result = Process::path(base_path())->run(array_merge(['git'], $args));

        if (! $result->successful()) {
            return null;
        }

        $output = trim($result->output());

        return $output !== '' ? $output : null;
    }

    /** The checked out branch, or null when detached (a tag deploy reports HEAD). */
    private function currentBranch(): ?string
    {
        $branch = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);

        return ($branch === null || $branch === 'HEAD') ? null : $branch;
    }

    /** The Node.js version (major.minor.patch), or null if node is not available. */
    private function nodeVersion(): ?string
    {
        $result = Process::run(['node', '-v']);

        if ($result->successful() && preg_match('/(\d+\.\d+\.\d+)/', $result->output(), $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
