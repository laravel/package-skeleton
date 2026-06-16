#!/usr/bin/env php
<?php

declare(strict_types=1);

use Laravel\AgentDetector\AgentDetector;
use Laravel\Chisel\Chisel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

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

trait FormatsStrings
{
    private function slug(string $value): string
    {
        return trim(
            strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value)),
            '-',
        );
    }

    private function studly(string $value): string
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace(['-', '_'], ' ', $value)),
        );
    }

    private function headline(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    private function snake(string $value): string
    {
        return str_replace('-', '_', $this->slug($value));
    }
}

trait InteractsWithGitHub
{
    protected array $existingCommands = [];

    protected ?string $ghUsername = null;

    protected ?bool $ghAuthenticated = null;

    private function commandExists(string $command): bool
    {
        if (isset($this->existingCommands[$command])) {
            return $this->existingCommands[$command];
        }

        [$check, $devnull] = match (PHP_OS_FAMILY) {
            'Windows' => ['where', 'NUL'],
            default => ['command -v', '/dev/null'],
        };

        $result = shell_exec(
            sprintf('%s %s 2> %s', $check, escapeshellarg($command), $devnull),
        );

        $this->existingCommands[$command] = trim((string) $result) !== '';

        return $this->existingCommands[$command];
    }

    private function ghIsAuthenticated(): bool
    {
        if ($this->ghAuthenticated !== null) {
            return $this->ghAuthenticated;
        }

        if (! $this->commandExists('gh')) {
            return $this->ghAuthenticated = false;
        }

        $result = $this->runCommand(
            ['gh', 'auth', 'status'],
            getcwd() ?: __DIR__,
        );

        return $this->ghAuthenticated = $result['success'];
    }

    private function ghUsername(): ?string
    {
        if ($this->ghUsername !== null) {
            return $this->ghUsername;
        }

        if (! $this->commandExists('gh') || ! $this->ghIsAuthenticated()) {
            return null;
        }

        $result = $this->runCommand(
            ['gh', 'api', 'user', '--jq', '.login'],
            getcwd() ?: __DIR__,
        );

        if (! $result['success']) {
            return null;
        }

        return $this->ghUsername = $result['output'];
    }

    /**
     * @param  list<string>  $command
     * @return array<string, mixed>
     */
    private function runGitHubCommand(array $command, mixed $runner): array
    {
        return is_callable($runner)
            ? $runner($command)
            : $this->runCommand($command);
    }

    /**
     * @param  list<string>  $command
     * @return array{success: bool, output: string}
     */
    private function runCommand(array $command, ?string $cwd = null): array
    {
        $cwd ??= $this->rootDir;

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

        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'success' => proc_close($process) === 0,
            'output' => trim((string) $output),
        ];
    }
}

class Metadata
{
    use FormatsStrings, InteractsWithGitHub;

    protected array $data = [];

    protected ArgvInput $input;

    public function __construct(protected string $rootDir)
    {
        //
    }

    public function collect(): void
    {
        foreach ($this->fields() as $key => $field) {
            $this->data[$key] = text(
                $field['label'],
                default: $field['default'](),
                required: true,
                hint: $field['hint'],
                validate: $field['validate'] ?? null,
            );
        }
    }

    public function setInput(ArgvInput $input): void
    {
        $this->input = $input;
    }

    public function packageName(): string
    {
        return $this->data['package_name'];
    }

    public function packageNameHuman(): string
    {
        return $this->data['package_name_human'];
    }

    public function packageDescription(): string
    {
        return $this->data['package_description'];
    }

    public function vendorSlug(): string
    {
        return explode('/', $this->data['package_name'])[0];
    }

    public function packageSlug(): string
    {
        return explode('/', $this->data['package_name'])[1];
    }

    public function authorName(): string
    {
        return $this->data['author_name'];
    }

    public function authorEmail(): string
    {
        return $this->data['author_email'];
    }

    public function vendorNamespace(): string
    {
        return $this->data['vendor_namespace'];
    }

    public function className(): string
    {
        return $this->data['class_name'] ?? '';
    }

    /** @return array<string, array{label: string, hint: string, default: callable, validate?: callable}> */
    public function fields(): array
    {
        $directoryName = basename($this->rootDir);

        return [
            'author_name' => [
                'label' => 'Author name',
                'hint' => 'Used in composer.json credits and README attribution.',
                'default' => fn () => $this->input->getOption('author-name') ?? trim((string) shell_exec('git config user.name')) ?: 'Vendor Name',
            ],
            'author_email' => [
                'label' => 'Author email',
                'hint' => 'Used in composer.json package author metadata.',
                'default' => fn () => $this->input->getOption('author-email') ?? trim((string) shell_exec('git config user.email')) ?: 'author@example.com',
                'validate' => function ($value) {
                    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        return 'Must be a valid email address.';
                    }

                    return null;
                },
            ],
            'package_name' => [
                'label' => 'Package name',
                'hint' => 'Used in composer.json and as the package name in Packagist.',
                'default' => fn () => $this->input->getOption('package-name') ?? implode('/', [
                    $this->ghUsername() ?: $this->slug($this->authorName()) ?: 'vendor-name',
                    $this->slug($directoryName === 'package-skeleton' ? 'my-package' : $directoryName),
                ]),
                'validate' => function ($value) {
                    if (! preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/', $value)) {
                        return 'Package name must be in the format vendor/package.';
                    }

                    return null;
                },
            ],
            'package_name_human' => [
                'label' => 'Package name (human readable)',
                'hint' => 'Used as the human-readable package name in README and docs.',
                'default' => function () {
                    if ($value = $this->input->getOption('package-name-human')) {
                        return $value;
                    }

                    return $this->headline(str_replace('-', ' ', $this->packageSlug()));
                },
            ],
            'package_description' => [
                'label' => 'Package description',
                'hint' => 'Used in composer.json, README, and documentation intro copy.',
                'default' => fn () => $this->input->getOption('package-description') ?? '',
            ],
            'vendor_namespace' => [
                'label' => 'Vendor namespace',
                'hint' => 'Used as the top-level PHP namespace, for example VendorName\\PackageName.',
                'default' => fn () => $this->input->getOption('vendor-namespace') ?? $this->studly($this->slug($this->packageNameHuman())),
                'validate' => function ($value) {
                    if (preg_match('/^[A-Z_a-z][A-Z_a-z0-9]*$/', $value) !== 1) {
                        return 'Vendor namespace must be a valid PHP namespace.';
                    }

                    return null;
                },
            ],
            'class_name' => [
                'label' => 'Main class name',
                'hint' => 'Used for the main class, service provider, facade, and command class names.',
                'default' => fn () => $this->input->getOption('class-name') ?? $this->studly($this->slug($this->packageNameHuman())),
                'validate' => function ($value) {
                    if (preg_match('/^[A-Z_a-z][A-Z_a-z0-9]*$/', $value) !== 1) {
                        return 'Class name must be a valid PHP class name.';
                    }

                    return null;
                },
            ],
        ];
    }

    public function useDefaults(): void
    {
        foreach ($this->fields() as $key => $field) {
            $this->data[$key] = $field['default']();
        }
    }

    public function toArray(): array
    {
        return $this->data;
    }
}

class LaravelPackageSkeletonConfigurator
{
    use FormatsStrings, InteractsWithGitHub;

    /**
     * @var array{'mode': string, 'visibility'?: string}
     */
    private array $githubConfig = [
        'mode' => 'skip',
    ];

    private ?Chisel $chisel = null;

    private ?InputDefinition $definition = null;

    private ?ArgvInput $input = null;

    /**
     * @var list<string>
     */
    private array $manualSteps = [];

    /**
     * @var array{'metadata': array<string, mixed>, 'selected_features': list<string>, 'selected_tools': list<string>, 'removed_paths': list<string>, 'modified_files': list<string>, 'manual_steps': list<string>}
     */
    private array $summary = [];

    private const SUCCESS = 0;

    private const FAILURE = 1;

    private Metadata $metadata;

    public function __construct(protected string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
        $this->metadata = new MetaData($this->rootDir);
    }

    public function run(): int
    {
        $autoload = $this->rootDir.'/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (! $this->dependenciesAreInstalled()) {
            $message = 'Composer dependencies are not installed. Run `composer install` before `php configure.php`.';

            if ($this->hasNonInteractiveFlags()) {
                $this->writeJson([
                    'message' => $message,
                    'success' => false,
                    'errors' => [$message],
                ]);
            } else {
                fwrite(STDERR, $message.PHP_EOL);
            }

            return self::FAILURE;
        }

        try {
            $input = $this->input();
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage().PHP_EOL.PHP_EOL);
            $this->printHelp();

            return self::FAILURE;
        }

        $this->metadata->setInput($input);

        if ($input->getOption('help')) {
            $this->printHelp();

            return self::SUCCESS;
        }

        if ($this->isNonInteractive()) {
            return $this->runNonInteractive();
        }

        return $this->runInteractive();
    }

    private function dependenciesAreInstalled(): bool
    {
        return function_exists('Laravel\Prompts\intro') &&
            class_exists(AgentDetector::class) &&
            class_exists(Chisel::class);
    }

    private function runInteractive(): int
    {
        intro('Configure your Laravel package');

        $this->metadata->collect();

        $defaultFeatures = $this->flaggedFeatures() ?: $this->featureKeys();

        $features = multiselect(
            'Package Features',
            array_map(fn ($feature) => $feature['label'], $this->features()),
            $defaultFeatures,
            info: fn (string $key) => $this->features()[$key]['description'] ?? '',
        );

        $tools = multiselect(
            'Package Tools',
            array_map(fn ($tool) => $tool['label'], $this->tools()),
            $this->toolKeys(),
            info: fn (string $key) => $this->tools()[$key]['description'] ?? '',
        );

        $this->setupGithubConfig();

        if ($this->isGithubMode('create')) {
            info('GitHub URL: https://github.com/'.$this->metadata->packageName());
        }

        $result = spin(
            fn (): array => $this->configure([
                'features' => $features,
                'tools' => $tools,
            ]),
            $this->isGithubMode('create') ? 'Creating GitHub repository and pushing the initial commit...' : 'Configuring the package...',
        );

        if (! $result['success']) {
            foreach ($result['errors'] as $message) {
                error((string) $message);
            }

            return self::FAILURE;
        }

        if (($result['summary']['manual_steps'] ?? []) !== []) {
            info('Next steps:');

            foreach ($result['summary']['manual_steps'] as $manualStep) {
                $lines = wordwrap($manualStep, width: 60);
                $lines = explode(PHP_EOL, $lines);
                $finalLines = [];

                foreach ($lines as $index => $line) {
                    if ($index === 0) {
                        $finalLines[] = '· '.$line;
                    } else {
                        $finalLines[] = '  '.$line;
                    }
                }

                info(implode(PHP_EOL, $finalLines));
            }
        }

        outro('Package configured successfully');

        return self::SUCCESS;
    }

    private function runNonInteractive(): int
    {
        $this->metadata->useDefaults();

        $result = $this->configure([
            'features' => $this->flaggedFeatures() ?: $this->featureKeys(),
            'tools' => $this->toolKeys(),
        ]);

        $this->writeJson($this->nonInteractivePayload($result));

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{success: bool, errors: list<string>, github?: array<string, mixed>, summary?: array<string, mixed>}  $result
     * @return array{message: string, success: bool, errors: list<string>, github: array<string, mixed>, summary: array<string, mixed>}
     */
    private function nonInteractivePayload(array $result): array
    {
        $success = $result['success'];

        return [
            'message' => $success ? 'Package configured successfully.' : 'Package configuration failed.',
            'success' => $success,
            'errors' => $result['errors'],
            'github' => $result['github'] ?? $this->defaultGithubResult(),
            'summary' => $result['summary'] ?? [],
        ];
    }

    /** @return list<string> */
    private function flaggedFeatures(): array
    {
        return array_values(
            array_filter(
                $this->featureKeys(),
                fn (string $feature): bool => (bool) $this->input()->getOption($this->keyToOption($feature)),
            ),
        );
    }

    private function keyToOption(string $key): string
    {
        return str_replace('_', '-', $key);
    }

    /** @param  array<string, mixed>  $payload */
    private function writeJson(array $payload): void
    {
        fwrite(
            STDOUT,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    private function isGithubMode(string $mode): bool
    {
        return ($this->githubConfig['mode'] ?? 'skip') === $mode;
    }

    private function setupGithubConfig(): void
    {
        if (! $this->commandExists('gh')) {
            warning('GitHub CLI was not found. Repository creation will be skipped.');

            return;
        }

        if (! $this->ghIsAuthenticated()) {
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

        $this->githubConfig = [
            'mode' => 'create',
            'visibility' => $visibility,
        ];
    }

    /** @return array{status: string, message: string, created_repositories: list<string>} */
    private function defaultGithubResult(): array
    {
        return [
            'status' => 'skipped',
            'message' => 'GitHub repository creation was skipped.',
            'created_repositories' => [],
        ];
    }

    /** @return array<string, array{label: string, description?: string, remove?: callable}> */
    private function features(): array
    {
        $provider = sprintf('%s/src/%sServiceProvider.php', $this->rootDir, $this->metadata->className() ?? '');
        $readme = $this->rootDir.'/README.md';
        $docsConfig = $this->rootDir.'/docs/.vitepress/config.ts';
        $docsIndex = $this->rootDir.'/docs/index.md';
        $docsInstallation = $this->rootDir.'/docs/getting-started/installation.md';

        return [
            'config' => [
                'label' => 'Config file',
                'remove' => fn () => [
                    $this->removePath('config'),
                    $this->removeChiselSection($provider, 'config'),
                    $this->removeMarkdownSection($readme, 'Publishing the Configuration File'),
                    $this->removePath('docs/getting-started/configuration.md'),
                    $this->removeLinesContaining($docsConfig, ['Configuration']),
                    $this->removeLinesContaining($docsIndex, ['Configuration']),
                    $this->removeLinesContaining($docsInstallation, ['-config']),
                    $this->removeLinesContaining($this->rootDir.'/phpstan.neon.dist', ['        - config']),
                ],
            ],
            'routes' => [
                'label' => 'Routes',
                'remove' => fn () => [
                    $this->removePath('routes'),
                    $this->removeChiselSection($provider, 'routes'),
                    $this->removeLinesContaining($readme, ['route', 'Route']),
                    $this->removeLinesContaining($this->rootDir.'/phpstan.neon.dist', ['        - routes']),
                ],
            ],
            'views' => [
                'label' => 'Views',
                'remove' => fn () => [
                    $this->removePath('resources/views'),
                    $this->removeChiselSection($provider, 'views'),
                    $this->removeMarkdownSection($readme, 'Publishing the Views'),
                    $this->removeLinesContaining($docsInstallation, ['-views']),
                ],
            ],
            'translations' => [
                'label' => 'Translations',
                'remove' => fn () => [
                    $this->removePath('lang'),
                    $this->removeChiselSection($provider, 'translations'),
                    $this->removeMarkdownSection($readme, 'Publishing the Translations'),
                    $this->removeLinesContaining($docsInstallation, ['-lang']),
                ],
            ],
            'migrations' => [
                'label' => 'Migrations',
                'remove' => fn () => [
                    $this->removePath('database/migrations'),
                    $this->removeChiselSection($provider, 'migrations'),
                    $this->removeMarkdownSection($readme, 'Publishing and Running the Migrations'),
                    $this->removeLinesContaining($docsInstallation, ['-migrations']),
                    $this->removeMarkdownSection($docsInstallation, 'Running Migrations'),
                    $this->removeLinesContaining($this->rootDir.'/phpstan.neon.dist', ['        - database']),
                ],
            ],
            'assets' => [
                'label' => 'Assets',
                'remove' => fn () => [
                    $this->removePath('public'),
                    $this->removeChiselSection($provider, 'assets'),
                    $this->removeMarkdownSection($readme, 'Publishing the Public Assets'),
                    $this->removeLinesContaining($docsInstallation, ['-assets']),
                ],
            ],
            'commands' => [
                'label' => 'Commands',
                'remove' => fn () => [
                    $this->removePath('src/Console/Commands'),
                    $this->removeChiselSection($provider, 'commands'),
                    $this->removeLinesContaining($readme, ['command', 'Command']),
                ],
            ],
            'facade' => [
                'label' => 'Facade',
                'remove' => fn () => [
                    $this->removePath('src/Facades'),
                    $this->removeLinesContaining($readme, ['facade', 'Facade']),
                ],
            ],
            'boost_skill' => [
                'label' => 'Boost Skill',
                'description' => 'Add a package skill for Laravel Boost',
                'remove' => fn () => [
                    $this->removePath('resources/boost/skills'),
                    $this->removePath('.agents/skills/package-generate-skill'),
                    $this->removePath('.claude/skills/package-generate-skill'),
                    $this->removeLinesContaining($readme, ['Boost', 'boost']),
                    $this->removeLinesContaining($this->rootDir.'/AGENTS.md', ['Boost', 'boost']),
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, description?: string, remove?: callable}
     */
    private function feature(string $key): array
    {
        return $this->features()[$key];
    }

    /**
     * @return array{label: string, description?: string, remove?: callable}
     */
    private function tool(string $key): array
    {
        return $this->tools()[$key];
    }

    /** @return list<string> */
    private function featureKeys(): array
    {
        return array_keys($this->features());
    }

    /** @return array<string, array{label: string, description?: string, remove?: callable}> */
    private function tools(): array
    {
        $readme = $this->rootDir.'/README.md';
        $docsConfig = $this->rootDir.'/docs/.vitepress/config.ts';
        $docsIndex = $this->rootDir.'/docs/index.md';

        return [
            'dependabot' => [
                'label' => 'Dependabot Pull Requests',
                'remove' => fn () => [
                    $this->removePath('.github/dependabot.yml'),
                    $this->removeLinesContaining($readme, ['Dependabot']),
                    $this->removeLinesContaining($this->rootDir.'/docs/index.md', ['Dependabot']),
                ],
                'add' => function () {
                    $this->manualSteps[] = 'Review Dependabot dependency update pull requests before merging them. This package intentionally does not include a Dependabot automatic merge workflow.';
                },
                'description' => 'Automated dependency updates',
            ],
            'issue_template' => [
                'label' => 'Issue Template',
                'remove' => fn () => $this->removePath(
                    '.github/ISSUE_TEMPLATE',
                ),
            ],
            'changelog' => [
                'label' => 'Changelog',
                'description' => 'Automated changelog generation',
                'remove' => fn () => [
                    $this->removePath('CHANGELOG.md'),
                    $this->removePath('.github/workflows/update-changelog.yml'),
                    $this->removePath('.github/release.yml'),
                    $this->removePath('docs/getting-started/changelog.md'),
                    $this->removeLinesContaining($docsConfig, ['Changelog']),
                    $this->removeLinesContaining($docsIndex, ['Changelog']),
                    $this->removeMarkdownSection($readme, 'Changelog'),
                    $this->removeLinesContaining($readme, ['changelog', 'CHANGELOG']),
                ],
                'add' => function () {
                    $manualStep = 'Create the release-note labels you plan to use, such as `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`, `skip-changelog`, and `duplicate`.';

                    if (! $this->ghIsAuthenticated()) {
                        $this->manualSteps[] = $manualStep;

                        return;
                    }

                    $labels = ['breaking', 'enhancement', 'bug', 'documentation', 'dependencies', 'maintenance', 'skip-changelog', 'duplicate'];
                    $failed = false;

                    foreach ($labels as $label) {
                        $result = $this->runCommand([
                            'gh',
                            'label',
                            'create',
                            $label,
                            '--repo',
                            $this->metadata->packageName(),
                            '--force',
                        ]);

                        if (! $result['success']) {
                            $failed = true;
                        }
                    }

                    if ($failed) {
                        $this->manualSteps[] = $manualStep;
                    }

                    $this->manualSteps[] = 'Review branch protection for `main`; changelog automation needs GitHub Actions to be allowed to commit `CHANGELOG.md` after a release is published.';
                },
            ],
            'funding' => [
                'label' => 'Funding',
                'remove' => fn () => $this->removePath('.github/FUNDING.yml'),
            ],
            'security_policy' => [
                'label' => 'Security Policy',
                'remove' => fn () => [
                    $this->removePath('.github/SECURITY.md'),
                    $this->removeMarkdownSection($readme, 'Security Vulnerabilities'),
                ],
            ],
            'documentation' => [
                'label' => 'Documentation',
                'description' => 'Docs via VitePress + GitHub Pages',
                'remove' => fn () => [
                    $this->removePath('docs'),
                    $this->removePath('package.json'),
                    $this->removePath('.agents/skills/package-docs'),
                    $this->removePath('.claude/skills/package-docs'),
                    $this->removePath('.github/workflows/docs.yml'),
                    $this->removeLinesContaining($readme, [
                        'documentation',
                        'Documentation',
                        'VitePress',
                        'GitHub Pages',
                    ]),
                    $this->removeLinesContaining($this->rootDir.'/AGENTS.md', ['VitePress', 'docs/']),
                    $this->removeLinesContaining($this->rootDir.'/.agents/skills/package-generate-skill/SKILL.md', ['docs/']),
                    $this->removeLinesContaining($this->rootDir.'/.claude/skills/package-generate-skill/SKILL.md', ['docs/']),
                    $this->removeLinesContaining($this->rootDir.'/.gitignore', [
                        'docs/.vitepress/dist',
                        'package-lock.json',
                        'pnpm-lock.yaml',
                        'bun.lock',
                    ]),
                    $this->removeLinesContaining($this->rootDir.'/.gitattributes', [
                        '/docs',
                        '/package.json',
                        'docs/.vitepress/dist',
                    ]),
                ],
                'add' => function () {
                    $manualStep = 'Enable GitHub Pages and set the source to GitHub Actions so `.github/workflows/docs.yml` can deploy the VitePress site.';

                    if (! $this->ghIsAuthenticated()) {
                        $this->manualSteps[] = $manualStep;

                        return;
                    }

                    $result = $this->runCommand([
                        'gh',
                        'api',
                        '-X',
                        'PUT',
                        "/repos/{$this->metadata->packageName()}/pages",
                        '-f',
                        'build_type=workflow',
                    ]);

                    if (! $result['success']) {
                        $this->manualSteps[] = $manualStep;
                    }
                },
            ],
        ];
    }

    /** @return list<string> */
    private function toolKeys(): array
    {
        return array_keys($this->tools());
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, errors: list<string>, github: array<string, mixed>, summary: array<string, mixed>}
     */
    private function configure(array $options): array
    {
        $selectedFeatures = array_values(
            $options['features'] ?? $this->featureKeys(),
        );
        $selectedTools = array_values($options['tools'] ?? $this->toolKeys());

        $github = $this->defaultGithubResult();

        $this->summary = [
            'metadata' => $this->metadata->toArray(),
            'selected_features' => $selectedFeatures,
            'selected_tools' => $selectedTools,
            'removed_paths' => [],
            'modified_files' => [],
            'manual_steps' => $this->manualSteps,
        ];

        $this->replacePackageReadme();
        $this->replacePackageAgentsMarkdown();
        $this->replacePlaceholders();
        $this->renamePackageFiles();
        $this->updateComposerJson($selectedFeatures);
        $this->removePath('.agents/skills/skeleton-development');
        $this->copyAgentSkillsToClaude();

        foreach (array_diff($this->featureKeys(), $selectedFeatures) as $featureToRemove) {
            if (isset($this->feature($featureToRemove)['remove'])) {
                $this->feature($featureToRemove)['remove']();
            }
        }

        foreach (array_diff($this->toolKeys(), $selectedTools) as $toolToRemove) {
            if (isset($this->tool($toolToRemove)['remove'])) {
                $this->tool($toolToRemove)['remove']();
            }
        }

        foreach ($selectedTools as $toolToAdd) {
            if (isset($this->feature($toolToAdd)['add'])) {
                $this->feature($toolToAdd)['add']();
            }
        }

        $this->removeChiselMarkers($selectedFeatures);
        $this->copyAgentsMarkdownToClaude();
        $this->cleanupEmptyDirectories();

        $formatResult = $this->runCommand([PHP_BINARY, 'vendor/bin/pint', '--quiet']);

        if (! $formatResult['success']) {
            return [
                'success' => false,
                'errors' => [
                    'Code formatting failed: '.$formatResult['output'],
                ],
                'github' => $github,
                'summary' => $this->summary,
            ];
        }

        if ($this->isGithubMode('create')) {
            $github = $this->createGitHubRepository($this->githubConfig);

            if (! $github['success']) {
                return [
                    'success' => false,
                    'errors' => [$github['message']],
                    'github' => $github,
                    'summary' => $this->summary,
                ];
            }
        }

        if (! $this->isGithubMode('create')) {
            $this->removePath('configure.php');
        }

        $dumpAutoloadResult = $this->runCommand(['composer', 'dump-autoload', '--quiet']);

        if (! $dumpAutoloadResult['success']) {
            return [
                'success' => false,
                'errors' => [
                    'Composer autoload generation failed: '.
                        $dumpAutoloadResult['output'],
                ],
                'github' => $github,
                'summary' => $this->summary,
            ];
        }

        sort($this->summary['modified_files']);
        sort($this->summary['removed_paths']);

        return [
            'success' => true,
            'errors' => [],
            'github' => $github,
            'summary' => $this->summary,
        ];
    }

    private function replacePlaceholders(): void
    {
        $replacements = $this->replacements();
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

        foreach ($this->textFiles($this->rootDir) as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $updated = preg_replace_callback(
                $placeholderPattern,
                fn (array $matches): string => $replacements[(string) $matches[0]],
                $contents,
            ) ?? $contents;

            $this->replaceFileContents($file, $contents, $updated);
        }
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $vendorNamespace = $this->metadata->vendorNamespace();
        $className = $this->metadata->className();
        $packageName = $this->metadata->packageNameHuman();
        $vendorSlug = $this->metadata->vendorSlug();
        $packageSlug = $this->metadata->packageSlug();

        return [
            ':author_name' => $this->metadata->authorName(),
            ':author_email' => $this->metadata->authorEmail(),
            ':author_username' => $vendorSlug,
            ':vendor_name' => $this->headline($vendorSlug),
            ':vendor_slug' => $vendorSlug,
            ':vendor_namespace' => $vendorNamespace,
            ':package_name' => $packageName,
            ':package_slug' => $packageSlug,
            ':package_description' => $this->metadata->packageDescription(),
            ':class_name' => $className,
            'vendor-name/skeleton' => $vendorSlug.'/'.$packageSlug,
            'vendor-name' => $vendorSlug,
            'Author Name' => $this->metadata->authorName(),
            'author@example.com' => $this->metadata->authorEmail(),
            'VendorName\\Skeleton' => $vendorNamespace.'\\'.$className,
            'VendorName' => $vendorNamespace,
            'SkeletonServiceProvider' => $className.'ServiceProvider',
            'SkeletonCommand' => $className.'Command',
            'Skeleton' => $className,
            'skeleton_placeholder' => $this->snake($packageSlug).'_placeholder',
            'skeleton' => $packageSlug,
        ];
    }

    /**
     * @return list<string>
     */
    private function textFiles(string $dir): array
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

            $relativePath = $this->relativePath($file->getPathname(), $dir);

            if (
                $this->isSkippedPath($relativePath) ||
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

    private function isSkippedPath(string $relativePath): bool
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
            if ($relativePath === $skip || str_starts_with($relativePath, $skip.'/')) {
                return true;
            }
        }

        return false;
    }

    private function renamePackageFiles(): void
    {
        $className = $this->metadata->className();
        $packageSlug = $this->metadata->packageSlug();
        $tableName = $this->snake($packageSlug).'_placeholder';

        $toRename = [
            'src/Skeleton.php' => "src/{$className}.php",
            'src/SkeletonServiceProvider.php' => "src/{$className}ServiceProvider.php",
            'src/Facades/Skeleton.php' => "src/Facades/{$className}.php",
            'src/Console/Commands/SkeletonCommand.php' => "src/Console/Commands/{$className}Command.php",
            'config/skeleton.php' => "config/{$packageSlug}.php",
            'routes/skeleton.php' => "routes/{$packageSlug}.php",
            'resources/boost/skills/skeleton' => "resources/boost/skills/{$packageSlug}-development",
        ];

        foreach ($toRename as $from => $to) {
            $this->renamePath($from, $to);
        }

        $migrationPaths = glob($this->rootDir.'/database/migrations/*create_skeleton_placeholder_table.php') ?: [];

        foreach ($migrationPaths as $migration) {
            $destination = implode('/', [
                dirname($migration),
                str_replace(
                    'create_skeleton_placeholder_table',
                    "create_{$tableName}_table",
                    basename($migration),
                ),
            ]);
            rename($migration, $destination);
            $this->trackRemoved($migration);
            $this->trackModified($destination);
        }
    }

    /**
     * @param  list<string>  $selectedFeatures
     */
    private function updateComposerJson(array $selectedFeatures): void
    {
        $path = $this->rootDir.'/composer.json';
        $composer = json_decode(
            (string) file_get_contents($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $namespace = implode('\\', [
            $this->metadata->vendorNamespace(),
            $this->metadata->className(),
        ]).'\\';

        $composer['name'] = $this->metadata->packageName();
        $composer['description'] = $this->metadata->packageDescription();
        $composer['keywords'] = array_values(
            array_unique([
                'laravel',
                ...explode('/', $this->metadata->packageName()),
            ]),
        );
        $composer['homepage'] = 'https://github.com/'.$this->metadata->packageName();
        $composer['authors'] = [
            [
                'name' => $this->metadata->authorName(),
                'email' => $this->metadata->authorEmail(),
                'role' => 'Developer',
            ],
        ];
        $composer['scripts']['clear'] = [
            '@php vendor/bin/testbench package:purge-skeleton --ansi',
        ];

        unset($composer['scripts']['post-install-cmd']);
        unset($composer['scripts']['post-update-cmd']);

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
                $this->metadata->className().
                'ServiceProvider',
        ];

        if (! in_array('facade', $selectedFeatures)) {
            unset($composer['extra']['laravel']['aliases']);
        } else {
            $composer['extra']['laravel']['aliases'] = [
                $this->metadata->className() => rtrim($namespace, '\\').
                    '\\Facades\\'.
                    $this->metadata->className(),
            ];
        }

        unset(
            $composer['require-dev']['laravel/agent-detector'],
            $composer['require-dev']['laravel/chisel'],
            $composer['require-dev']['laravel/prompts'],
        );

        if (($composer['extra']['laravel'] ?? []) === []) {
            unset($composer['extra']['laravel']);
        }

        file_put_contents(
            $path,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
                PHP_EOL,
        );

        $this->trackModified($path);
    }

    private function removeMarkdownSection(string $path, string $heading): void
    {
        $absolutePath = $this->absolutePath($path);

        if (! file_exists($absolutePath)) {
            return;
        }

        $contents = (string) file_get_contents($absolutePath);
        $pattern =
            '/\n##+ '.preg_quote($heading, '/').'\n.*?(?=\n##+ |\z)/s';
        $updated = preg_replace($pattern, '', $contents) ?? $contents;

        $this->replaceFileContents($absolutePath, $contents, $updated);
    }

    /**
     * @param  list<string>  $selectedFeatures
     */
    private function removeChiselMarkers(array $selectedFeatures): void
    {
        $provider = sprintf('%s/src/%sServiceProvider.php', $this->rootDir, $this->metadata->className());
        $providerSections = array_diff($this->featureKeys(), [
            'facade',
            'boost_skill',
        ]);

        foreach ($providerSections as $section) {
            if (in_array($section, $selectedFeatures, true)) {
                $this->removeChiselSectionMarkers($provider, $section);
            }
        }
    }

    private function removeChiselSection(string $path, string $tag): void
    {
        $this->rewriteWithChisel(
            $path,
            fn (string $relativePath) => $this->chisel()
                ->file($relativePath)
                ->removeSection($tag),
        );
    }

    private function removeChiselSectionMarkers(string $path, string $tag): void
    {
        $this->rewriteWithChisel(
            $path,
            fn (string $relativePath) => $this->chisel()
                ->file($relativePath)
                ->removeSectionMarkers($tag),
        );
    }

    /**
     * @param  list<string>  $needles
     */
    private function removeLinesContaining(string $path, array $needles): void
    {
        $absolutePath = $this->absolutePath($path);

        if (! file_exists($absolutePath)) {
            return;
        }

        $contents = (string) file_get_contents($absolutePath);
        $relativePath = $this->relativePath($absolutePath);

        foreach ($needles as $needle) {
            $this->chisel()->file($relativePath)->removeLinesContaining($needle);
        }

        $updated = (string) file_get_contents($absolutePath);

        if ($updated !== $contents) {
            $this->trackModified($absolutePath);
        }
    }

    private function replaceFileContents(string $path, string $contents, string $updated): void
    {
        if ($updated === $contents) {
            return;
        }

        $this->chisel()
            ->file($this->relativePath($this->absolutePath($path)))
            ->replace($contents, $updated);

        $this->trackModified($path);
    }

    private function rewriteWithChisel(string $path, callable $callback): void
    {
        $absolutePath = $this->absolutePath($path);

        if (! file_exists($absolutePath)) {
            return;
        }

        $contents = (string) file_get_contents($absolutePath);

        $callback($this->relativePath($absolutePath));

        if ((string) file_get_contents($absolutePath) !== $contents) {
            $this->trackModified($absolutePath);
        }
    }

    private function removePath(string $relativePath): void
    {
        $path = $this->rootDir.'/'.$relativePath;

        if (! file_exists($path)) {
            return;
        }

        if (! is_dir($path)) {
            $this->chisel()->file($relativePath)->delete();
            $this->trackRemoved($relativePath);

            return;
        }

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

        $this->trackRemoved($relativePath);
    }

    private function chisel(): Chisel
    {
        return $this->chisel ??= Chisel::in($this->rootDir);
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, $this->rootDir.'/')
            ? $path
            : $this->rootDir.'/'.$path;
    }

    private function renamePath(string $from, string $to): void
    {
        $source = $this->rootDir.'/'.$from;
        $destination = $this->rootDir.'/'.$to;

        if (! file_exists($source) || $source === $destination) {
            return;
        }

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        rename($source, $destination);
        $this->trackRemoved($from);
        $this->trackModified($to);
    }

    private function replacePackageReadme(): void
    {
        if (! file_exists($this->rootDir.'/README_PACKAGE.md')) {
            return;
        }

        $this->removePath('README.md');
        $this->renamePath('README_PACKAGE.md', 'README.md');
    }

    private function replacePackageAgentsMarkdown(): void
    {
        if (! file_exists($this->rootDir.'/AGENTS_PACKAGE.md')) {
            return;
        }

        $this->removePath('AGENTS.md');
        $this->renamePath('AGENTS_PACKAGE.md', 'AGENTS.md');
    }

    private function copyAgentSkillsToClaude(): void
    {
        $source = $this->rootDir.'/.agents/skills';
        $destination = $this->rootDir.'/.claude/skills';

        if (! is_dir($source)) {
            return;
        }

        $this->removePath('.claude/skills');

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
            $this->trackModified($target);
        }
    }

    private function copyAgentsMarkdownToClaude(): void
    {
        $source = $this->rootDir.'/AGENTS.md';
        $destination = $this->rootDir.'/CLAUDE.md';

        if (! file_exists($source)) {
            return;
        }

        copy($source, $destination);
        $this->trackModified($destination);
    }

    private function cleanupEmptyDirectories(): void
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
            $path = $this->rootDir.'/'.$relativePath;

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
                $this->trackRemoved($relativePath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $github
     * @return array<string, mixed>
     */
    private function createGitHubRepository(array $github): array
    {
        $visibility = match ($github['visibility'] ?? '') {
            'public' => 'public',
            default => 'private',
        };
        $repository = $this->metadata->packageName();
        $commands = [];
        $runner = $github['runner'] ?? null;
        $configurePath = $this->rootDir.'/configure.php';
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
        $insideGitResult = $this->runGitHubCommand($insideGitCommand, $runner);

        if (! ($insideGitResult['success'] ?? false)) {
            $initCommand = ['git', 'init'];
            $commands[] = $initCommand;
            $initResult = $this->runGitHubCommand($initCommand, $runner);

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
        $this->runGitHubCommand($removeOriginCommand, $runner);

        $commands[] = $createCommand;
        $result = $this->runGitHubCommand($createCommand, $runner);

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

        $this->removePath('configure.php');

        $gitCommands = [
            ['git', 'add', '--all'],
            [
                'git',
                '-c',
                'user.name='.$this->metadata->authorName(),
                '-c',
                'user.email='.$this->metadata->authorEmail(),
                'commit',
                '-m',
                'Initial commit',
            ],
            ['git', 'branch', '-M', 'main'],
            ['git', 'push', '-u', 'origin', 'main'],
        ];

        foreach ($gitCommands as $gitCommand) {
            $commands[] = $gitCommand;
            $gitResult = $this->runGitHubCommand($gitCommand, $runner);

            if (! ($gitResult['success'] ?? false)) {
                $this->restoreConfigureScript(
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

    private function restoreConfigureScript(string $path, string|false $contents): void
    {
        if ($contents === false || file_exists($path)) {
            return;
        }

        file_put_contents($path, $contents);
    }

    private function isNonInteractive(): bool
    {
        return $this->input()->getOption('no-interaction') ||
            getenv('COMPOSER_NO_INTERACTION') === '1' ||
            AgentDetector::detect()->isAgent;
    }

    private function hasNonInteractiveFlags(): bool
    {
        return getenv('COMPOSER_NO_INTERACTION') === '1' ||
            in_array('--no-interaction', $_SERVER['argv'] ?? []) ||
            in_array('-n', $_SERVER['argv'] ?? []);
    }

    private function definition(): InputDefinition
    {
        return $this->definition ??= $this->getInputDefinition();
    }

    private function getInputDefinition(): InputDefinition
    {
        $featureOptions = array_map(
            fn (string $key) => new InputOption(
                $this->keyToOption($key),
                null,
                InputOption::VALUE_NONE,
                sprintf('Include %s', $this->feature($key)['label']),
            ),
            $this->featureKeys(),
        );

        $metadataOptions = array_map(
            fn (string $key, array $field) => new InputOption(
                str_replace('_', '-', $key),
                null,
                InputOption::VALUE_OPTIONAL,
                $field['label'],
            ),
            array_keys($this->metadata->fields()),
            array_values($this->metadata->fields()),
        );

        return new InputDefinition([
            new InputOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Run non-interactively with all defaults'),
            ...$featureOptions,
            ...$metadataOptions,
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Show usage information'),
        ]);
    }

    private function input(): ArgvInput
    {
        return $this->input ??= new ArgvInput(null, $this->definition());
    }

    private function printHelp(): void
    {
        $lines = ['Usage: php configure.php [options]', '', 'Options:'];

        foreach ($this->definition()->getOptions() as $option) {
            $shortcut = $option->getShortcut() ? sprintf('-%s, ', $option->getShortcut()) : '    ';
            $lines[] = sprintf('  %s--%-24s%s', $shortcut, $option->getName(), $option->getDescription());
        }

        fwrite(STDOUT, implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function trackModified(string $path): void
    {
        $this->addToSummaryByKey('modified_files', $this->relativePath($path));
    }

    private function trackRemoved(string $path): void
    {
        $this->addToSummaryByKey('removed_paths', $this->relativePath($path));
    }

    private function addToSummaryByKey(string $key, string $value): void
    {
        $this->summary[$key][] = $value;
        $this->summary[$key] = array_values(
            array_unique($this->summary[$key]),
        );
    }

    private function relativePath(string $path, ?string $dir = null): string
    {
        $dir = str_replace('\\', '/', $dir ?? $this->rootDir);
        $path = str_replace('\\', '/', $path);

        if (! str_starts_with($path, $dir.'/')) {
            return ltrim(preg_replace('#^\./#', '', $path) ?? $path, '/');
        }

        $relativePath = ltrim(substr($path, strlen($dir)), '/');

        return preg_replace('#^\./#', '', $relativePath) ?? $relativePath;
    }
}

if (
    PHP_SAPI === 'cli' &&
    realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__
) {
    exit((new LaravelPackageSkeletonConfigurator(__DIR__))->run());
}
