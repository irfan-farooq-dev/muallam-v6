<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Builds the zip for hosting without a terminal (cPanel): the committed code plus production-only
 * Composer packages, split into the app folder (outside the web root) and public_html.
 * With --keep-public, public/ stays inside the app folder instead, for a (sub)domain whose
 * document root is <app-folder>/public, and the zip never touches public_html.
 */
#[Signature('site:package
    {--ref=HEAD : Git commit, branch or tag to package}
    {--app-folder=muallam : Name of the app folder next to public_html on the server}
    {--keep-public : Keep public/ inside the app folder (document root is <app-folder>/public)}
    {--output= : Folder for the zip (default: a "muallam-deploy" folder next to this project)}')]
#[Description('Build the upload zip for cPanel hosting (no SSH needed)')]
class PackageSite extends Command
{
    /**
     * Development-only files left out of the package.
     *
     * @var list<string>
     */
    private const LEFT_OUT = [
        'tests', 'docs', '.ai', '.claude', '.github', '.mcp.json', 'AGENTS.md', 'CLAUDE.md', 'boost.json',
        'phpunit.xml', '.editorconfig', '.gitattributes', 'README.md', 'database/database.sqlite',
    ];

    public function handle(): int
    {
        $ref = (string) $this->option('ref');
        $appFolder = trim((string) $this->option('app-folder'), '/');
        $output = (string) ($this->option('output') ?: dirname(base_path()).'/muallam-deploy');

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $appFolder)) {
            $this->error('The app folder must be a plain folder name, such as "muallam".');

            return self::FAILURE;
        }

        if (trim($this->git(['status', '--porcelain'])) !== '' && $ref === 'HEAD') {
            $this->warn('You have uncommitted changes. They are NOT in the package (only committed code is).');
        }

        $commit = trim($this->git(['rev-parse', '--short', $ref]));
        $release = $commit.'-'.now()->format('Ymd-His');
        $work = storage_path('app/private/package-build');
        $app = "{$work}/{$appFolder}";
        $publicHtml = "{$work}/public_html";

        File::deleteDirectory($work);
        File::ensureDirectoryExists($app);

        $this->line("Exporting {$ref} ({$commit})...");
        $this->runProcess(['sh', '-c', 'git archive --format=tar "$0" | tar -x -C "$1"', $ref, $app], base_path());

        $this->line('Installing production Composer packages...');
        $this->runProcess(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress', '--prefer-dist'], $app, 600);

        foreach (self::LEFT_OUT as $path) {
            File::isDirectory("{$app}/{$path}") ? File::deleteDirectory("{$app}/{$path}") : File::delete("{$app}/{$path}");
        }
        File::delete(File::glob("{$app}/bootstrap/cache/*.php"));

        $keepPublic = (bool) $this->option('keep-public');

        if (! $keepPublic) {
            File::moveDirectory("{$app}/public", $publicHtml);
            $this->pointIndexAtAppFolder("{$publicHtml}/index.php", $appFolder);
        }
        File::put("{$app}/RELEASE", $release.PHP_EOL);

        File::ensureDirectoryExists($output);
        $zip = "{$output}/{$appFolder}-{$release}.zip";
        $this->line('Zipping...');
        $this->runProcess(['zip', '-rqy', $zip, $appFolder, ...($keepPublic ? [] : ['public_html'])], $work, 300);
        File::deleteDirectory($work);

        $this->info("Package ready: {$zip}");
        $this->line($keepPublic
            ? "Upload it to your hosting home folder, extract it (it fills {$appFolder}/ only), and the scheduler finishes the deploy within a minute."
            : 'Upload it to your hosting home folder (next to public_html), extract it, and the scheduler finishes the deploy within a minute.');

        return self::SUCCESS;
    }

    /**
     * public_html/index.php loads the app from the folder beside it instead of the parent folder.
     */
    private function pointIndexAtAppFolder(string $index, string $appFolder): void
    {
        $contents = File::get($index);
        $updated = str_replace("__DIR__.'/../", "__DIR__.'/../{$appFolder}/", $contents);

        if (substr_count($updated, "/../{$appFolder}/") !== 3) {
            throw new RuntimeException('public/index.php has changed; update PackageSite::pointIndexAtAppFolder().');
        }

        File::put($index, $updated);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments): string
    {
        return $this->runProcess(['git', ...$arguments], base_path());
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, string $path, int $timeout = 120): string
    {
        $result = Process::path($path)->timeout($timeout)->run($command);

        if ($result->failed()) {
            throw new RuntimeException('Command failed: '.implode(' ', $command).PHP_EOL.$result->errorOutput());
        }

        return $result->output();
    }
}
