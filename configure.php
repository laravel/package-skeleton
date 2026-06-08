#!/usr/bin/env php
<?php

declare(strict_types=1);

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class LaravelPackageSkeletonConfigurator
{
    /**
     * @var array{'mode': string, 'visibility'?: string}
     */
    private static array $githubConfig = [
        'mode' => 'skip',
    ];

    private static ?string $rootDir = null;

    /**
     * @var array{'metadata': array<string, mixed>, 'selected_features': list<string>, 'selected_tools': list<string>, 'removed_paths': list<string>, 'modified_files': list<string>, 'github': array<string, mixed>, 'manual_steps': list<string>}
     */
    private static array $summary = [];

    private const SUCCESS = 0;

    private const FAILURE = 1;

    public static function run(string $rootDir): int
    {
        self::$rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);

        $autoload = self::$rootDir.'/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }

        $defaults = self::defaults();

        if (self::isNonInteractive()) {
            return self::runNonInteractive($defaults);
        }

        if (! function_exists("Laravel\Prompts\intro")) {
            fwrite(
                STDERR,
                'Laravel Prompts is not installed. Run `composer install` before `php ./configure.php`.'.
                    PHP_EOL,
            );

            return self::FAILURE;
        }

        return self::runInteractive($defaults);
    }

    private static function runInteractive(array $defaults): int
    {
        intro('Configure your Laravel package');

        $metadata = [];

        foreach (self::metadataFields() as $key => $field) {
            $default = self::metadataDefault($key, $defaults, $metadata);

            $metadata[$key] = text(
                $field['label'],
                default: $default,
                required: true,
                hint: $field['hint'],
            );
        }

        $features = multiselect('Package Features', self::features(), self::featureKeys());
        $tools = multiselect('Package Tools', self::tools(), self::toolKeys());

        self::setupGithubConfig();

        info('Summary');
        info("Package: {$metadata['vendor_slug']}/{$metadata['package_slug']}");
        info('Features: '.self::toList($features, fn (string $key): string => self::feature($key)));
        info('Tools: '.self::toList($tools, fn (string $key): string => self::tool($key)));

        if (self::isGithubMode('create')) {
            info("GitHub URL: https://github.com/{$metadata['vendor_slug']}/{$metadata['package_slug']}");
        }

        if (! confirm('Apply these changes?', true)) {
            warning('Configuration cancelled. No files were changed.');

            return self::FAILURE;
        }

        $result = spin(
            fn (): array => self::configure([
                'metadata' => $metadata,
                'features' => $features,
                'tools' => $tools,
                'github' => self::$githubConfig,
            ]),
            self::isGithubMode('create') ? 'Creating GitHub repository and pushing the initial commit...' : 'Configuring the package...',
        );

        if (! $result['success']) {
            foreach ($result['errors'] as $message) {
                error((string) $message);
            }

            return self::FAILURE;
        }

        if (($result['summary']['manual_steps'] ?? []) !== []) {
            info('Manual follow-up steps:');

            foreach ($result['summary']['manual_steps'] as $manualStep) {
                info("· {$manualStep}");
            }
        }

        outro('Package configured successfully.');

        return self::SUCCESS;
    }

    private static function runNonInteractive(array $defaults): int
    {
        $result = self::configure([
            'metadata' => $defaults,
            'features' => self::featureKeys(),
            'tools' => self::toolKeys(),
            'github' => self::$githubConfig,
        ]);

        if (! $result['success']) {
            foreach ($result['errors'] as $message) {
                fwrite(STDERR, (string) $message.PHP_EOL);
            }

            return self::FAILURE;
        }

        fwrite(STDOUT, 'Package configured successfully.'.PHP_EOL);

        return self::SUCCESS;
    }

    private static function isGithubMode(string $mode): bool
    {
        return (self::$githubConfig['mode'] ?? 'skip') === $mode;
    }

    private static function setupGithubConfig(): void
    {
        if (! self::commandExists('gh')) {
            warning('GitHub CLI was not found. Repository creation will be skipped.');

            return;
        }

        if (! self::ghIsAuthenticated()) {
            warning('GitHub CLI is not authenticated. Repository creation will be skipped.');

            return;
        }

        if (! confirm('Create a GitHub repository now?', false)) {
            return;
        }

        $visibility = (string) select(
            'Repository visibility',
            ['private' => 'Private', 'public' => 'Public'],
            'private',
        );

        self::$githubConfig = [
            'mode' => 'create',
            'visibility' => $visibility,
        ];
    }

    /** @return array<string, string> */
    private static function features(): array
    {
        return [
            'config' => 'Config file',
            'routes' => 'Routes',
            'views' => 'Views',
            'translations' => 'Translations',
            'migrations' => 'Migrations',
            'assets' => 'Assets',
            'commands' => 'Commands',
            'facade' => 'Facade',
            'boost_skill' => 'Boost Skill',
        ];
    }

    private static function toList(array $keys, callable $labelCallback): string
    {
        return implode(', ', array_map($labelCallback, $keys));
    }

    private static function feature(string $key): string
    {
        return self::features()[$key] ?? $key;
    }

    private static function tool(string $key): string
    {
        return self::tools()[$key] ?? $key;
    }

    /** @return list<string> */
    private static function featureKeys(): array
    {
        return array_keys(self::features());
    }

    /** @return array<string, string> */
    private static function tools(): array
    {
        return [
            'dependabot' => 'Dependabot Pull Requests',
            'issue_template' => 'Issue Template',
            'changelog' => 'Changelog',
            'funding' => 'Funding',
            'security_policy' => 'Security Policy',
            'documentation' => 'Documentation',
        ];
    }

    /** @return list<string> */
    private static function toolKeys(): array
    {
        return array_keys(self::tools());
    }

    /** @return array<string, array{label: string, hint: string}> */
    private static function metadataFields(): array
    {
        return [
            'author_name' => [
                'label' => 'Author name',
                'hint' => 'Used in composer.json credits and README attribution.',
            ],
            'author_email' => [
                'label' => 'Author email',
                'hint' => 'Used in composer.json package author metadata.',
            ],
            'author_username' => [
                'label' => 'Author GitHub username',
                'hint' => 'Used for the README credits link.',
            ],
            'vendor_slug' => [
                'label' => 'GitHub / Packagist user',
                'hint' => 'Use the GitHub user or organization that will own the repository and Packagist package.',
            ],
            'vendor_namespace' => [
                'label' => 'Vendor namespace',
                'hint' => 'Used as the top-level PHP namespace, for example VendorName\\PackageName.',
            ],
            'package_name' => [
                'label' => 'Package name',
                'hint' => 'Used as the human-readable package name in README and docs.',
            ],
            'package_slug' => [
                'label' => 'Package slug',
                'hint' => 'Used in composer package names, publish tags, config files, routes, and URLs.',
            ],
            'class_name' => [
                'label' => 'Main class name',
                'hint' => 'Used for the main class, service provider, facade, and command class names.',
            ],
            'package_description' => [
                'label' => 'Package description',
                'hint' => 'Used in composer.json, README, and documentation intro copy.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, errors: list<string>, github: array<string, mixed>, summary: array<string, mixed>}
     */
    private static function configure(array $options): array
    {
        $metadata = $options['metadata'] ?? [];
        $selectedFeatures = array_values(
            $options['features'] ?? self::featureKeys(),
        );
        $selectedTools = array_values($options['tools'] ?? self::toolKeys());

        $errors = self::validate($metadata, $selectedFeatures, $selectedTools);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'summary' => [],
            ];
        }

        $metadata['vendor_slug'] = self::slug((string) $metadata['vendor_slug']);

        self::$summary = [
            'metadata' => $metadata,
            'selected_features' => $selectedFeatures,
            'selected_tools' => $selectedTools,
            'removed_paths' => [],
            'modified_files' => [],
            'github' => [
                'status' => 'skipped',
                'message' => 'GitHub repository creation was skipped.',
                'created_repositories' => [],
            ],
            'manual_steps' => self::manualSteps($selectedTools),
        ];

        self::replacePackageReadme();
        self::replacePlaceholders($metadata);
        self::renamePackageFiles($metadata);
        self::updateComposerJson($metadata, $selectedFeatures);
        self::copyAgentSkillsToClaude();

        foreach (array_diff(self::featureKeys(), $selectedFeatures) as $feature) {
            self::removeFeature($feature, $metadata);
        }

        foreach (array_diff(self::toolKeys(), $selectedTools) as $tool) {
            self::removeTool($tool);
        }

        self::copyAgentsMarkdownToClaude();
        self::cleanupEmptyDirectories();

        $formatResult = self::runCommand([PHP_BINARY, 'vendor/bin/pint', '--quiet']);

        if (! $formatResult['success']) {
            return [
                'success' => false,
                'errors' => [
                    'Code formatting failed: '.$formatResult['output'],
                ],
                'github' => self::$summary['github'],
                'summary' => self::$summary,
            ];
        }

        if (self::isGithubMode('create')) {
            $githubResult = self::createGitHubRepository($metadata, self::$githubConfig);

            self::$summary['github'] = $githubResult;

            if (! $githubResult['success']) {
                return [
                    'success' => false,
                    'errors' => [$githubResult['message']],
                    'github' => $githubResult,
                    'summary' => self::$summary,
                ];
            }
        }

        self::removePath('configure.php');

        $dumpAutoloadResult = self::runCommand(['composer', 'dump-autoload', '--quiet']);

        if (! $dumpAutoloadResult['success']) {
            return [
                'success' => false,
                'errors' => [
                    'Composer autoload generation failed: '.
                        $dumpAutoloadResult['output'],
                ],
                'github' => self::$summary['github'],
                'summary' => self::$summary,
            ];
        }

        sort(self::$summary['modified_files']);
        sort(self::$summary['removed_paths']);

        return [
            'success' => true,
            'errors' => [],
            'github' => self::$summary['github'],
            'summary' => self::$summary,
        ];
    }

    /**
     * @param  array<string, string>  $defaults
     * @param  array<string, string>  $metadata
     */
    private static function metadataDefault(string $key, array $defaults, array $metadata): string
    {
        return match (true) {
            $key === 'vendor_slug' && isset($metadata['author_username']) => self::slug($metadata['author_username']),
            $key === 'package_slug' && isset($metadata['package_name']) => self::slug($metadata['package_name']),
            $key === 'class_name' && isset($metadata['package_name']) => self::studly(self::slug($metadata['package_name'])),
            default => $defaults[$key],
        };
    }

    /**
     * @param  list<string>  $selectedTools
     * @return list<string>
     */
    private static function manualSteps(array $selectedTools): array
    {
        $steps = [];

        $toolSteps = [
            'documentation' => [
                'Enable GitHub Pages and set the source to GitHub Actions so `.github/workflows/docs.yml` can deploy the VitePress site.',
            ],
            'dependabot' => [
                'Review Dependabot dependency update pull requests before merging them. This package intentionally does not include a Dependabot automatic merge workflow.',
            ],
            'changelog' => [
                'Create the release-note labels you plan to use, such as `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`, `skip-changelog`, and `duplicate`.',
                'Review branch protection for `main`; changelog automation needs GitHub Actions to be allowed to commit `CHANGELOG.md` after a release is published.',
            ],
        ];

        foreach ($toolSteps as $tool => $toolStep) {
            if (in_array($tool, $selectedTools)) {
                $steps = array_merge($steps, $toolStep);
            }
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $features
     * @param  list<string>  $tools
     * @return list<string>
     */
    private static function validate(array $metadata, array $features, array $tools): array
    {
        $errors = [];
        $paths = [
            'composer.json',
            'src/SkeletonServiceProvider.php',
            'README.md',
            'README_PACKAGE.md',
        ];

        foreach ($paths as $path) {
            if (! file_exists(self::$rootDir.'/'.$path)) {
                $errors[] = "Expected skeleton file [{$path}] was not found.";
            }
        }

        foreach (array_keys(self::metadataFields()) as $key) {
            if (
                ! isset($metadata[$key]) ||
                trim((string) $metadata[$key]) === ''
            ) {
                $errors[] = self::fieldLabel($key).' is required.';
            }
        }

        if (
            isset($metadata['author_email']) &&
            filter_var($metadata['author_email'], FILTER_VALIDATE_EMAIL) ===
            false
        ) {
            $errors[] =
                self::fieldLabel('author_email').
                ' must be a valid email address.';
        }

        if (
            isset($metadata['vendor_slug']) &&
            ! preg_match(
                '/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/',
                (string) $metadata['vendor_slug'],
            )
        ) {
            $errors[] =
                self::fieldLabel('vendor_slug').
                ' may only contain letters, numbers, and hyphens.';
        }

        if (
            isset($metadata['package_slug']) &&
            ! preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                (string) $metadata['package_slug'],
            )
        ) {
            $errors[] =
                self::fieldLabel('package_slug').' must be a lowercase slug.';
        }

        foreach (['vendor_namespace', 'class_name'] as $key) {
            if (
                isset($metadata[$key]) &&
                ! self::isPhpIdentifier((string) $metadata[$key])
            ) {
                $errors[] =
                    self::fieldLabel($key).' must be a valid PHP identifier.';
            }
        }

        $unknownFeatures = array_diff($features, self::featureKeys());
        $unknownTools = array_diff($tools, self::toolKeys());

        foreach ($unknownFeatures as $feature) {
            $errors[] = "Unknown package feature [{$feature}].";
        }

        foreach ($unknownTools as $tool) {
            $errors[] = "Unknown package tool [{$tool}].";
        }

        return $errors;
    }

    private static function isPhpIdentifier(string $value): bool
    {
        return preg_match('/^[A-Z_a-z][A-Z_a-z0-9]*$/', $value) === 1;
    }

    private static function fieldLabel(string $key): string
    {
        return self::metadataFields()[$key]['label'] ?? self::headline($key);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function replacePlaceholders(array $metadata): void
    {
        $replacements = self::replacements($metadata);
        $placeholderPattern =
            '/'.
            implode(
                '|',
                array_map(
                    static fn (string $placeholder): string => preg_quote(
                        $placeholder,
                        '/',
                    ),
                    array_keys($replacements),
                ),
            ).
            '/';

        foreach (self::textFiles(self::$rootDir) as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $updated = preg_replace_callback(
                $placeholderPattern,
                fn (array $matches): string => $replacements[(string) $matches[0]],
                $contents,
            ) ?? $contents;

            if ($updated === $contents) {
                continue;
            }

            file_put_contents($file, $updated);
            self::trackModified($file);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    private static function replacements(array $metadata): array
    {
        $vendorNamespace = (string) $metadata['vendor_namespace'];
        $className = (string) $metadata['class_name'];
        $packageSlug = (string) $metadata['package_slug'];
        $packageName = (string) $metadata['package_name'];
        $vendorSlug = (string) $metadata['vendor_slug'];

        return [
            ':author_name' => (string) $metadata['author_name'],
            ':author_email' => (string) $metadata['author_email'],
            ':author_username' => (string) $metadata['author_username'],
            ':vendor_name' => self::headline($vendorSlug),
            ':vendor_slug' => $vendorSlug,
            ':vendor_namespace' => $vendorNamespace,
            ':package_name' => $packageName,
            ':package_slug' => $packageSlug,
            ':package_description' => (string) $metadata['package_description'],
            ':class_name' => $className,
            'vendor-name/skeleton' => $vendorSlug.'/'.$packageSlug,
            'vendor-name' => $vendorSlug,
            'Author Name' => (string) $metadata['author_name'],
            'author@example.com' => (string) $metadata['author_email'],
            'VendorName\\Skeleton' => $vendorNamespace.'\\'.$className,
            'VendorName' => $vendorNamespace,
            'SkeletonServiceProvider' => $className.'ServiceProvider',
            'SkeletonCommand' => $className.'Command',
            'Skeleton' => $className,
            'skeleton_placeholder' => self::snake($packageSlug).'_placeholder',
            'skeleton' => $packageSlug,
        ];
    }

    /**
     * @return list<string>
     */
    private static function textFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = self::relativePath($file->getPathname(), $dir);

            if (
                self::isSkippedPath($relativePath) ||
                $relativePath === 'configure.php'
            ) {
                continue;
            }

            $handle = fopen($file->getPathname(), 'rb');

            if ($handle === false) {
                continue;
            }

            $contents = fread($handle, 1024);
            fclose($handle);

            if ($contents === false || str_contains($contents, "\0")) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    private static function isSkippedPath(string $relativePath): bool
    {
        $toSkip = [
            '.git',
            'vendor',
            'node_modules',
            'workbench',
            '.phpunit.cache',
            'bootstrap/cache',
        ];

        foreach ($toSkip as $skip) {
            if (
                $relativePath === $skip ||
                str_starts_with($relativePath, $skip.'/')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function renamePackageFiles(array $metadata): void
    {
        $className = (string) $metadata['class_name'];
        $packageSlug = (string) $metadata['package_slug'];
        $tableName = self::snake($packageSlug).'_placeholder';

        self::renamePath(
            'src/Skeleton.php',
            'src/'.$className.'.php',
        );
        self::renamePath(
            'src/SkeletonServiceProvider.php',
            'src/'.$className.'ServiceProvider.php',
        );
        self::renamePath(
            'src/Facades/Skeleton.php',
            'src/Facades/'.$className.'.php',
        );
        self::renamePath(
            'src/Console/Commands/SkeletonCommand.php',
            'src/Console/Commands/'.$className.'Command.php',
        );
        self::renamePath(
            'config/skeleton.php',
            'config/'.$packageSlug.'.php',
        );
        self::renamePath(
            'routes/skeleton.php',
            'routes/'.$packageSlug.'.php',
        );
        self::renamePath(
            'resources/boost/skills/skeleton',
            'resources/boost/skills/'.$packageSlug.'-development',
        );

        $migrationPaths = glob(self::$rootDir.'/database/migrations/*create_skeleton_placeholder_table.php') ?: [];

        foreach ($migrationPaths as $migration) {
            $destination =
                dirname($migration).
                '/'.
                str_replace(
                    'create_skeleton_placeholder_table',
                    'create_'.$tableName.'_table',
                    basename($migration),
                );
            rename($migration, $destination);
            self::trackRemoved($migration);
            self::trackModified($destination);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $selectedFeatures
     */
    private static function updateComposerJson(array $metadata, array $selectedFeatures): void
    {
        $path = self::$rootDir.'/composer.json';
        $composer = json_decode(
            (string) file_get_contents($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $namespace = implode('\\', [
            $metadata['vendor_namespace'],
            $metadata['class_name'],
        ]).'\\';

        $composer['name'] = implode('/', [
            $metadata['vendor_slug'],
            $metadata['package_slug'],
        ]);
        $composer['description'] = (string) $metadata['package_description'];
        $composer['keywords'] = array_values(
            array_unique([
                (string) $metadata['vendor_slug'],
                'laravel',
                (string) $metadata['package_slug'],
            ]),
        );
        $composer['homepage'] =
            'https://github.com/'.
            (string) $metadata['vendor_slug'].
            '/'.
            (string) $metadata['package_slug'];
        $composer['authors'][0]['name'] = (string) $metadata['author_name'];
        $composer['authors'][0]['email'] = (string) $metadata['author_email'];
        $composer['scripts']['clear'] = [
            '@php vendor/bin/testbench package:purge-skeleton --ansi',
        ];
        unset($composer['scripts']['setup']);
        $composer['autoload']['psr-4'] = [$namespace => 'src/'];
        $composer['autoload-dev']['psr-4'] =
            [$namespace.'Tests\\' => 'tests/'] +
            array_filter(
                $composer['autoload-dev']['psr-4'] ?? [],
                fn (string $key): bool => ! str_starts_with(
                    $key,
                    'VendorName\\Skeleton\\',
                ),
                ARRAY_FILTER_USE_KEY,
            );
        $composer['extra']['laravel']['providers'] = [
            rtrim($namespace, '\\').
                '\\'.
                (string) $metadata['class_name'].
                'ServiceProvider',
        ];

        if (! in_array('facade', $selectedFeatures)) {
            unset($composer['extra']['laravel']['aliases']);
        } else {
            $composer['extra']['laravel']['aliases'] = [
                (string) $metadata['class_name'] => rtrim($namespace, '\\').
                    '\\Facades\\'.
                    (string) $metadata['class_name'],
            ];
        }

        unset($composer['require-dev']['laravel/prompts']);

        if (($composer['extra']['laravel'] ?? []) === []) {
            unset($composer['extra']['laravel']);
        }

        file_put_contents(
            $path,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
                PHP_EOL,
        );
        self::trackModified($path);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function removeFeature(
        string $feature,
        array $metadata,
    ): void {
        $provider =
            self::$rootDir.
            '/src/'.
            (string) $metadata['class_name'].
            'ServiceProvider.php';
        $readme = self::$rootDir.'/README.md';
        $docsConfig = self::$rootDir.'/docs/.vitepress/config.ts';
        $docsIndex = self::$rootDir.'/docs/index.md';
        $docsInstallation = self::$rootDir.'/docs/getting-started/installation.md';

        $map = [
            'config' => fn () => [
                self::removePath('config'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootConfig',
                ),
                self::removeProviderLine(
                    $provider,
                    'mergeConfigFrom',
                ),
                self::removeMarkdownSection(
                    $readme,
                    'Publishing the Configuration File',
                ),
                self::removePath(
                    'docs/getting-started/configuration.md',
                ),
                self::removeLinesContaining(
                    $docsConfig,
                    ['Configuration'],
                ),
                self::removeLinesContaining(
                    $docsIndex,
                    ['Configuration'],
                ),
                self::removeLinesContaining(
                    $docsInstallation,
                    ['-config'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/phpstan.neon.dist',
                    ['        - config'],
                ),
            ],
            'routes' => fn () => [
                self::removePath('routes'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootRoutes',
                ),
                self::removeLinesContaining(
                    $readme,
                    ['route', 'Route'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/phpstan.neon.dist',
                    ['        - routes'],
                ),
            ],
            'views' => fn () => [
                self::removePath('resources/views'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootViews',
                ),
                self::removeMarkdownSection(
                    $readme,
                    'Publishing the Views',
                ),
                self::removeLinesContaining(
                    $docsInstallation,
                    ['-views'],
                ),
            ],
            'translations' => fn () => [
                self::removePath('lang'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootTranslations',
                ),
                self::removeMarkdownSection(
                    $readme,
                    'Publishing the Translations',
                ),
                self::removeLinesContaining(
                    $docsInstallation,
                    ['-lang'],
                ),
            ],
            'migrations' => fn () => [
                self::removePath('database/migrations'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootMigrations',
                ),
                self::removeMarkdownSection(
                    $readme,
                    'Publishing and Running the Migrations',
                ),
                self::removeLinesContaining(
                    $docsInstallation,
                    ['-migrations'],
                ),
                self::removeMarkdownSection(
                    $docsInstallation,
                    'Running Migrations',
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/phpstan.neon.dist',
                    ['        - database'],
                ),
            ],
            'assets' => fn () => [
                self::removePath('public'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootAssets',
                ),
                self::removeMarkdownSection(
                    $readme,
                    'Publishing the Public Assets',
                ),
                self::removeLinesContaining(
                    $docsInstallation,
                    ['-assets'],
                ),
            ],
            'commands' => fn () => [
                self::removePath('src/Console/Commands'),
                self::removeProviderCallAndMethod(
                    $provider,
                    'bootCommands',
                ),
                self::removeProviderLine(
                    $provider,
                    'Command;',
                ),
                self::removeLinesContaining(
                    $readme,
                    ['command', 'Command'],
                ),
            ],
            'facade' => fn () => [
                self::removePath('src/Facades'),
                self::removeLinesContaining(
                    $readme,
                    ['facade', 'Facade'],
                ),
            ],
            'boost_skill' => fn () => [
                self::removePath('resources/boost/skills'),
                self::removePath(
                    '.agents/skills/package-generate-skill',
                ),
                self::removePath(
                    '.claude/skills/package-generate-skill',
                ),
                self::removeLinesContaining(
                    $readme,
                    ['Boost', 'boost'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/AGENTS.md',
                    ['Boost', 'boost'],
                ),
            ],
        ];

        if (isset($map[$feature])) {
            $map[$feature]();
        }
    }

    private static function removeTool(string $tool): void
    {
        $readme = self::$rootDir.'/README.md';
        $docsConfig = self::$rootDir.'/docs/.vitepress/config.ts';
        $docsIndex = self::$rootDir.'/docs/index.md';

        $map = [
            'dependabot' => fn () => [
                self::removePath('.github/dependabot.yml'),
                self::removeLinesContaining(
                    $readme,
                    ['Dependabot'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/docs/index.md',
                    ['Dependabot'],
                ),
            ],
            'issue_template' => fn () => self::removePath(
                '.github/ISSUE_TEMPLATE',
            ),
            'changelog' => fn () => [
                self::removePath('CHANGELOG.md'),
                self::removePath(
                    '.github/workflows/update-changelog.yml',
                ),
                self::removePath('.github/release.yml'),
                self::removePath(
                    'docs/getting-started/changelog.md',
                ),
                self::removeLinesContaining(
                    $docsConfig,
                    ['Changelog'],
                ),
                self::removeLinesContaining(
                    $docsIndex,
                    ['Changelog'],
                ),
                self::removeMarkdownSection(
                    $readme,
                    'Changelog',
                ),
                self::removeLinesContaining(
                    $readme,
                    ['changelog', 'CHANGELOG'],
                ),
            ],
            'funding' => fn () => self::removePath(
                '.github/FUNDING.yml',
            ),
            'security_policy' => fn () => [
                self::removePath('.github/SECURITY.md'),
                self::removeMarkdownSection(
                    $readme,
                    'Security Vulnerabilities',
                ),
            ],
            'documentation' => fn () => [
                self::removePath('docs'),
                self::removePath('package.json'),
                self::removePath(
                    '.agents/skills/package-docs',
                ),
                self::removePath(
                    '.claude/skills/package-docs',
                ),
                self::removePath('.github/workflows/docs.yml'),
                self::removeLinesContaining(
                    $readme,
                    [
                        'documentation',
                        'Documentation',
                        'VitePress',
                        'GitHub Pages',
                    ],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/AGENTS.md',
                    ['VitePress', 'docs/'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/.agents/skills/package-generate-skill/SKILL.md',
                    ['docs/'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/.claude/skills/package-generate-skill/SKILL.md',
                    ['docs/'],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/.gitignore',
                    [
                        'docs/.vitepress/dist',
                        'package-lock.json',
                        'pnpm-lock.yaml',
                        'bun.lock',
                    ],
                ),
                self::removeLinesContaining(
                    self::$rootDir.'/.gitattributes',
                    ['/docs', '/package.json', 'docs/.vitepress/dist'],
                ),
            ],
        ];

        if (isset($map[$tool])) {
            $map[$tool]();
        }
    }

    private static function removeProviderCallAndMethod(string $path, string $method): void
    {
        if (! file_exists($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $updated =
            preg_replace(
                '/^\s*\$this->'.$method."\(\);\R/m",
                '',
                $contents,
            ) ?? $contents;
        $updated =
            preg_replace(
                '/\n\s*private function '.
                    $method.
                    '\(\): void\n\s*\{(?:[^{}]*|\{[^{}]*\})*\}\n/s',
                "\n",
                $updated,
            ) ?? $updated;
        $updated = preg_replace("/\n{3,}/", "\n\n", $updated) ?? $updated;

        if ($updated !== $contents) {
            file_put_contents($path, $updated);
            self::trackModified($path);
        }
    }

    private static function removeProviderLine(string $path, string $needle): void
    {
        if (! file_exists($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $lines = explode("\n", $contents);
        $filtered = array_values(
            array_filter(
                $lines,
                fn (string $line): bool => ! str_contains($line, $needle),
            ),
        );
        $updated = implode("\n", $filtered);

        if ($updated !== $contents) {
            file_put_contents($path, $updated);
            self::trackModified($path);
        }
    }

    private static function removeMarkdownSection(string $path, string $heading): void
    {
        if (! file_exists($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $pattern =
            '/\n##+ '.preg_quote($heading, '/').'\n.*?(?=\n##+ |\z)/s';
        $updated = preg_replace($pattern, '', $contents) ?? $contents;

        if ($updated !== $contents) {
            file_put_contents($path, $updated);
            self::trackModified($path);
        }
    }

    /**
     * @param  list<string>  $needles
     */
    private static function removeLinesContaining(string $path, array $needles): void
    {
        if (! file_exists($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $lines = explode("\n", $contents);
        $filtered = array_values(
            array_filter($lines, function (string $line) use ($needles): bool {
                foreach ($needles as $needle) {
                    if (str_contains($line, $needle)) {
                        return false;
                    }
                }

                return true;
            }),
        );
        $updated = implode("\n", $filtered);

        if ($updated !== $contents) {
            file_put_contents($path, $updated);
            self::trackModified($path);
        }
    }

    private static function removePath(string $relativePath): void
    {
        $path = self::$rootDir.'/'.$relativePath;

        if (! file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $path,
                    RecursiveDirectoryIterator::SKIP_DOTS,
                ),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                $item->isDir()
                    ? rmdir($item->getPathname())
                    : unlink($item->getPathname());
            }

            rmdir($path);
        } else {
            unlink($path);
        }

        self::$summary['removed_paths'][] = $relativePath;
    }

    private static function renamePath(string $from, string $to): void
    {
        $source = self::$rootDir.'/'.$from;
        $destination = self::$rootDir.'/'.$to;

        if (! file_exists($source) || $source === $destination) {
            return;
        }

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        rename($source, $destination);
        self::$summary['removed_paths'][] = $from;
        self::$summary['modified_files'][] = $to;
    }

    private static function replacePackageReadme(): void
    {
        if (! file_exists(self::$rootDir.'/README_PACKAGE.md')) {
            return;
        }

        self::removePath('README.md');
        self::renamePath('README_PACKAGE.md', 'README.md');
    }

    private static function copyAgentSkillsToClaude(): void
    {
        $source = self::$rootDir.'/.agents/skills';
        $destination = self::$rootDir.'/.claude/skills';

        if (! is_dir($source)) {
            return;
        }

        self::removePath('.claude/skills');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target =
                $destination.
                '/'.
                substr($item->getPathname(), strlen($source) + 1);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            copy($item->getPathname(), $target);
            self::trackModified($target);
        }
    }

    private static function copyAgentsMarkdownToClaude(): void
    {
        $source = self::$rootDir.'/AGENTS.md';
        $destination = self::$rootDir.'/CLAUDE.md';

        if (! file_exists($source)) {
            return;
        }

        copy($source, $destination);
        self::trackModified($destination);
    }

    private static function cleanupEmptyDirectories(): void
    {
        foreach (
            [
                'resources/boost',
                'resources',
                'database',
                'src/Console',
                '.github/workflows',
                '.github',
            ] as $relativePath
        ) {
            $path = self::$rootDir.'/'.$relativePath;

            if (
                is_dir($path) &&
                iterator_count(
                    new FilesystemIterator(
                        $path,
                        FilesystemIterator::SKIP_DOTS,
                    ),
                ) === 0
            ) {
                rmdir($path);
                self::$summary['removed_paths'][] = $relativePath;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $github
     * @return array<string, mixed>
     */
    private static function createGitHubRepository(
        array $metadata,
        array $github,
    ): array {
        $visibility = match ($github['visibility'] ?? '') {
            'public' => 'public',
            default => 'private',
        };
        $repository = "{$metadata['vendor_slug']}/{$metadata['package_slug']}";
        $commands = [];
        $runner = $github['runner'] ?? null;
        $configurePath = self::$rootDir.'/configure.php';
        $configureContents = file_exists($configurePath)
            ? file_get_contents($configurePath)
            : false;
        $createCommand = [
            'gh',
            'repo',
            'create',
            $repository,
            '--'.$visibility,
            '--source=.',
            '--remote=origin',
        ];
        $insideGitCommand = ['git', 'rev-parse', '--is-inside-work-tree'];
        $commands[] = $insideGitCommand;
        $insideGitResult = self::runGitHubCommand(
            $insideGitCommand,
            $runner,
        );

        if (! ($insideGitResult['success'] ?? false)) {
            $initCommand = ['git', 'init'];
            $commands[] = $initCommand;
            $initResult = self::runGitHubCommand($initCommand, $runner);

            if (! ($initResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Git repository initialization failed: '.
                        (string) ($initResult['output'] ?? ''),
                    'command' => $initCommand,
                    'commands' => $commands,
                    'created_repositories' => [],
                ];
            }
        }

        $removeOriginCommand = ['git', 'remote', 'remove', 'origin'];
        $commands[] = $removeOriginCommand;
        self::runGitHubCommand($removeOriginCommand, $runner);

        $commands[] = $createCommand;
        $result = self::runGitHubCommand($createCommand, $runner);

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'GitHub repository creation failed: '.
                    (string) ($result['output'] ?? ''),
                'command' => $createCommand,
                'commands' => $commands,
                'created_repositories' => [],
            ];
        }

        $gitCommands = [
            ['git', 'add', '--all'],
            [
                'git',
                '-c',
                'user.name='.(string) $metadata['author_name'],
                '-c',
                'user.email='.(string) $metadata['author_email'],
                'commit',
                '-m',
                'Initial commit',
            ],
            ['git', 'branch', '-M', 'main'],
            ['git', 'push', '-u', 'origin', 'main'],
        ];

        foreach ($gitCommands as $gitCommand) {
            $commands[] = $gitCommand;
            $gitResult = self::runGitHubCommand($gitCommand, $runner);

            if (! ($gitResult['success'] ?? false)) {
                self::restoreConfigureScript(
                    $configurePath,
                    $configureContents,
                );

                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Initial commit push failed: '.
                        (string) ($gitResult['output'] ?? ''),
                    'command' => $gitCommand,
                    'commands' => $commands,
                    'created_repositories' => [
                        'https://github.com/'.$repository,
                    ],
                ];
            }
        }

        return [
            'success' => true,
            'status' => 'created',
            'message' => 'GitHub repository was created and the configured package was pushed.',
            'command' => $createCommand,
            'commands' => $commands,
            'created_repositories' => ['https://github.com/'.$repository],
        ];
    }

    /**
     * @param  list<string>  $command
     * @return array<string, mixed>
     */
    private static function runGitHubCommand(array $command, mixed $runner): array
    {
        return is_callable($runner)
            ? $runner($command)
            : self::runCommand($command);
    }

    private static function restoreConfigureScript(string $path, string|false $contents): void
    {
        if ($contents === false || file_exists($path)) {
            return;
        }

        file_put_contents($path, $contents);
    }

    /**
     * @param  list<string>  $command
     * @return array{success: bool, output: string}
     */
    private static function runCommand(array $command, ?string $cwd = null): array
    {
        $cwd ??= self::$rootDir;

        $process = proc_open(
            implode(' ', array_map('escapeshellarg', $command)),
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
        );

        if (! is_resource($process)) {
            return ['success' => false, 'output' => 'Unable to start process.'];
        }

        $output =
            stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'success' => proc_close($process) === 0,
            'output' => trim((string) $output),
        ];
    }

    private static function commandExists(string $command): bool
    {
        $check = PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v';

        return trim(
            (string) shell_exec(
                $check.
                    ' '.
                    escapeshellarg($command).
                    ' 2> '.
                    (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'),
            ),
        ) !== '';
    }

    private static function ghIsAuthenticated(): bool
    {
        $result = self::runCommand(
            ['gh', 'auth', 'status'],
            getcwd() ?: __DIR__,
        );

        return $result['success'];
    }

    private static function isNonInteractive(): bool
    {
        return getenv('COMPOSER_NO_INTERACTION') === '1' ||
            in_array('--no-interaction', $_SERVER['argv'] ?? []) ||
            in_array('-n', $_SERVER['argv'] ?? []);
    }

    /** @return array<string, string> */
    private static function defaults(): array
    {
        $directoryName = basename(self::$rootDir);
        $packageSlug = self::slug(
            $directoryName === 'package-skeleton'
                ? 'my-package'
                : $directoryName,
        );
        $className = self::studly($packageSlug);
        $vendorName =
            trim((string) shell_exec('git config user.name')) ?: 'Vendor Name';
        $vendorSlug = self::slug($vendorName) ?: 'vendor-name';

        return [
            'author_name' => $vendorName,
            'author_email' => trim((string) shell_exec('git config user.email')) ?:
                'author@example.com',
            'author_username' => $vendorSlug,
            'vendor_slug' => $vendorSlug,
            'vendor_namespace' => self::studly($vendorSlug),
            'package_name' => self::headline($packageSlug),
            'package_slug' => $packageSlug,
            'class_name' => $className,
            'package_description' => 'A Laravel package.',
        ];
    }

    private static function slug(string $value): string
    {
        return trim(
            strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value)),
            '-',
        );
    }

    private static function studly(string $value): string
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace(['-', '_'], ' ', $value)),
        );
    }

    private static function headline(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    private static function snake(string $value): string
    {
        return str_replace('-', '_', self::slug($value));
    }

    private static function trackModified(string $path): void
    {
        self::$summary['modified_files'][] = self::relativePath($path);
        self::$summary['modified_files'] = array_values(
            array_unique(self::$summary['modified_files']),
        );
    }

    private static function trackRemoved(string $path): void
    {
        self::$summary['removed_paths'][] = self::relativePath($path);
        self::$summary['removed_paths'] = array_values(
            array_unique(self::$summary['removed_paths']),
        );
    }

    private static function relativePath(string $path, ?string $dir = null): string
    {
        $dir ??= self::$rootDir;

        return str_replace(
            '\\',
            '/',
            ltrim(
                substr($path, strlen($dir)),
                DIRECTORY_SEPARATOR,
            ),
        );
    }
}

if (
    PHP_SAPI === 'cli' &&
    realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__
) {
    exit(LaravelPackageSkeletonConfigurator::run(__DIR__));
}
