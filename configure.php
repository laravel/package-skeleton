#!/usr/bin/env php
<?php

declare(strict_types=1);

use Laravel\AgentDetector\AgentDetector;
use Laravel\Chisel\Chisel;
use Laravel\Prompts\Elements\Element;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\callout;
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

    private function ghRepoExists(string $repo): bool
    {
        if (! $this->ghIsAuthenticated()) {
            return false;
        }

        $result = $this->runCommand(
            ['gh', 'repo', 'view', $repo, '--json', 'name'],
            getcwd() ?: __DIR__,
        );

        return $result['success'];
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
                'default' => fn () => $this->input->getOption('author-name') ?? $this->runCommand(['git', 'config', 'user.name'])['output'] ?: 'Vendor Name',
            ],
            'author_email' => [
                'label' => 'Author email',
                'hint' => 'Used in composer.json package author metadata.',
                'default' => fn () => $this->input->getOption('author-email') ?? $this->runCommand(['git', 'config', 'user.email'])['output'] ?: 'author@example.com',
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
                'label' => 'Package display name',
                'hint' => 'Used as the readable package name in README.',
                'default' => function () {
                    if ($value = $this->input->getOption('package-name-human')) {
                        return $value;
                    }

                    return $this->headline(str_replace('-', ' ', $this->packageSlug()));
                },
            ],
            'package_description' => [
                'label' => 'Package description',
                'hint' => 'Used in composer.json and README intro copy.',
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

class Feature
{
    private mixed $removeCallback = null;

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $description = null,
    ) {
        //
    }

    public static function from(string $key, string $label, ?string $description = null): self
    {
        return new self(
            key: $key,
            label: $label,
            description: $description,
        );
    }

    public function onRemove(callable $callback): self
    {
        $this->removeCallback = $callback;

        return $this;
    }

    public function remove(): void
    {
        if (isset($this->removeCallback)) {
            ($this->removeCallback)($this);
        }
    }
}

class Features
{
    private array $features = [];

    public function __construct()
    {
        //
    }

    public function add(Feature $feature): void
    {
        $this->features[$feature->key] = $feature;
    }

    public function keys(): array
    {
        return array_keys($this->features);
    }

    public function labels(): array
    {
        return array_map(
            fn (Feature $feature) => $feature->label,
            $this->features,
        );
    }

    public function get(string $key): ?Feature
    {
        return $this->features[$key] ?? null;
    }
}

class Tool
{
    private mixed $removeCallback = null;

    private mixed $addCallback = null;

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $description = null,
        protected array $manualSteps = [],
    ) {
        //
    }

    public static function from(
        string $key,
        string $label,
        ?string $description = null,
        array $manualSteps = [],
    ): self {
        return new self(
            key: $key,
            label: $label,
            description: $description,
            manualSteps: $manualSteps,
        );
    }

    public function clearManualSteps(): void
    {
        $this->manualSteps = [];
    }

    public function removeManualStep(int $index): void
    {
        array_splice($this->manualSteps, $index, 1);
    }

    public function manualSteps(): array
    {
        return $this->manualSteps;
    }

    public function onRemove(callable $callback): self
    {
        $this->removeCallback = $callback;

        return $this;
    }

    public function onAdd(callable $callback): self
    {
        $this->addCallback = $callback;

        return $this;
    }

    public function remove(): void
    {
        $this->clearManualSteps();

        if (isset($this->removeCallback)) {
            ($this->removeCallback)($this);
        }
    }

    public function add(): void
    {
        if (isset($this->addCallback)) {
            ($this->addCallback)($this);
        }
    }
}

class Tools
{
    private array $tools = [];

    public function __construct()
    {
        //
    }

    public function add(Tool $tool): void
    {
        $this->tools[$tool->key] = $tool;
    }

    public function keys(): array
    {
        return array_keys($this->tools);
    }

    public function labels(): array
    {
        return array_map(
            fn (Tool $tool) => $tool->label,
            $this->tools,
        );
    }

    public function get(string $key): ?Tool
    {
        return $this->tools[$key] ?? null;
    }

    public function manualSteps(): array
    {
        $steps = [];

        foreach ($this->tools as $tool) {
            $steps = array_merge($steps, $tool->manualSteps());
        }

        return $steps;
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

    private Features $features;

    private Tools $tools;

    public function __construct(protected string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
        $this->metadata = new MetaData($this->rootDir);
        $this->features = new Features;
        $this->tools = new Tools;
        $this->registerFeatures();
        $this->registerTools();
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
                $this->writeJson($this->failed($message));
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

    /**
     * @return array{'success': bool, 'errors': list<string>, 'summary': array<string, mixed>} + array<string, mixed>
     */
    private function failed(string|array $errors, array $extra = []): array
    {
        return $this->response(false, $errors, $extra);
    }

    /**
     * @return array{'success': bool, 'errors': list<string>, 'summary': array<string, mixed>} + array<string, mixed>
     */
    private function success(array $extra = []): array
    {
        return $this->response(true, [], $extra);
    }

    /**
     * @return array{'success': bool, 'errors': list<string>, 'summary': array<string, mixed>} + array<string, mixed>
     */
    private function response(bool $success, string|array $errors = [], array $extra = []): array
    {
        return array_merge([
            'success' => $success,
            'errors' => is_array($errors) ? $errors : [$errors],
            'summary' => $this->summary,
        ], $extra);
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

        $defaultFeatures = $this->flaggedFeatures() ?: $this->features->keys();

        $features = multiselect(
            'Package Features',
            $this->features->labels(),
            $defaultFeatures,
            info: fn (string $key) => $this->features->get($key)->description ?? '',
        );

        $tools = multiselect(
            'Package Tools',
            $this->tools->labels(),
            $this->tools->keys(),
            info: fn (string $key) => $this->tools->get($key)->description ?? '',
        );

        $this->setupGithubConfig();

        if ($this->isGithubMode('create')) {
            info('<bg=green;options=bold;fg=black> GitHub URL </>');
            info('https://github.com/'.$this->metadata->packageName());
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

        $installerSteps = [];

        if ($this->input->getOption('installer-dir')) {
            $installerSteps = [
                'You can start your local development using:',
                "`cd {$this->input->getOption('installer-dir')}`",
                "\e[1mNew to Laravel?\e[22m".' Check out our '.Element::link(
                    'https://laravel.com/docs/packages',
                    'package documentation',
                ).'.',
                Element::heading('Build something amazing!'),
            ];
        }

        if (($result['summary']['manual_steps'] ?? []) !== []) {
            callout(
                label: 'Next Steps',
                content: [
                    Element::bulletedList($result['summary']['manual_steps'], spaced: true),
                    ...$installerSteps,
                ],
            );
        }

        outro('Package configured successfully');

        return self::SUCCESS;
    }

    private function runNonInteractive(): int
    {
        $this->metadata->useDefaults();

        $result = $this->configure([
            'features' => $this->flaggedFeatures() ?: $this->features->keys(),
            'tools' => $this->tools->keys(),
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

        return $this->response($success, $result['errors'], [
            'summary' => $result['summary'] ?? [],
        ]);
    }

    /** @return list<string> */
    private function flaggedFeatures(): array
    {
        return array_values(
            array_filter(
                $this->features->keys(),
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
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL);
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

    private function providerPath(): string
    {
        return sprintf('%s/src/%sServiceProvider.php', $this->rootDir, $this->metadata->className());
    }

    private function registerFeatures(): void
    {
        $featureTest = $this->rootDir.'/tests/Feature/ExampleTest.php';
        $readme = $this->rootDir.'/README.md';

        $this->features->add(
            Feature::from(
                key: 'config',
                label: 'Config file',
            )->onRemove(fn () => [
                $this->removePath('config'),
                $this->removeChiselSection($this->providerPath(), 'config'),
                $this->removeChiselSection($featureTest, 'config'),
                $this->removeMarkdownSection($readme, 'Publishing the Configuration File'),
                $this->removeLinesContaining($this->rootDir.'/phpstan.neon.dist', ['        - config']),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'routes',
                label: 'Routes',
            )->onRemove(fn () => [
                $this->removePath('routes'),
                $this->removeChiselSection($this->providerPath(), 'routes'),
                $this->removeLinesContaining($readme, ['route', 'Route']),
                $this->removeLinesContaining($this->rootDir.'/phpstan.neon.dist', ['        - routes']),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'views',
                label: 'Views',
            )->onRemove(fn () => [
                $this->removePath('resources/views'),
                $this->removeChiselSection($this->providerPath(), 'views'),
                $this->removeChiselSection($featureTest, 'views'),
                $this->removeMarkdownSection($readme, 'Publishing the Views'),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'translations',
                label: 'Translations',
            )->onRemove(fn () => [
                $this->removePath('lang'),
                $this->removeChiselSection($this->providerPath(), 'translations'),
                $this->removeChiselSection($featureTest, 'translations'),
                $this->removeMarkdownSection($readme, 'Publishing the Translations'),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'migrations',
                label: 'Migrations',
            )->onRemove(fn () => [
                $this->removePath('database/migrations'),
                $this->removeChiselSection($this->providerPath(), 'migrations'),
                $this->removeMarkdownSection($readme, 'Publishing and Running the Migrations'),
                $this->removeLinesContaining($this->rootDir.'/phpstan.neon.dist', ['        - database']),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'assets',
                label: 'Assets',
            )->onRemove(fn () => [
                $this->removePath('public'),
                $this->removeChiselSection($this->providerPath(), 'assets'),
                $this->removeMarkdownSection($readme, 'Publishing the Public Assets'),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'commands',
                label: 'Commands',
            )->onRemove(fn () => [
                $this->removePath('src/Console/Commands'),
                $this->removeChiselSection($this->providerPath(), 'commands'),
                $this->removeChiselSection($featureTest, 'commands'),
                $this->removeLinesContaining($readme, ['command', 'Command']),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'facade',
                label: 'Facade',
            )->onRemove(fn () => [
                $this->removePath('src/Facades'),
                $this->removeLinesContaining($readme, ['facade', 'Facade']),
            ]),
        );

        $this->features->add(
            Feature::from(
                key: 'boost_skill',
                label: 'Boost Skill',
                description: 'Bundled AI skill for Laravel Boost',
            )->onRemove(fn () => [
                $this->removePath('resources/boost/skills'),
                $this->removePath('.agents/skills/package-generate-skill'),
                $this->removeLinesContaining($readme, ['Boost', 'boost']),
                $this->removeLinesContaining($this->rootDir.'/AGENTS.md', ['Boost', 'boost']),
            ]),
        );
    }

    private function registerTools(): void
    {
        $readme = $this->rootDir.'/README.md';

        $this->tools->add(
            Tool::from(
                key: 'dependabot',
                label: 'Dependabot',
                description: 'Automated dependency update pull requests',
                manualSteps: [
                    'Review Dependabot pull requests before merging. This package does not include an automatic merge workflow.',
                ],
            )
                ->onRemove(fn () => [
                    $this->removePath('.github/dependabot.yml'),
                    $this->removeLinesContaining($readme, ['Dependabot']),
                ]),
        );

        $this->tools->add(
            Tool::from(
                key: 'issue_template',
                label: 'Issue Template',
            )
                ->onRemove(fn () => $this->removePath(
                    '.github/ISSUE_TEMPLATE',
                )),
        );

        $this->tools->add(
            Tool::from(
                key: 'changelog',
                label: 'Changelog',
                description: 'Automated changelog generation via GitHub releases',
                manualSteps: [
                    'Create the release-note labels: `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`, `skip-changelog`, and `duplicate`.',
                    'Review branch protection for `main` — changelog automation requires GitHub Actions to commit `CHANGELOG.md` after a release.',
                ],
            )->onRemove(
                fn () => [
                    $this->removePath('CHANGELOG.md'),
                    $this->removePath('.github/workflows/update-changelog.yml'),
                    $this->removePath('.github/release.yml'),
                    $this->removeMarkdownSection($readme, 'Changelog'),
                    $this->removeLinesContaining($readme, ['changelog', 'CHANGELOG']),
                ],
            )
                ->onAdd(function (Tool $tool) {
                    if (! $this->ghRepoExists($this->metadata->packageName())) {
                        return;
                    }

                    $labels = [
                        'breaking',
                        'enhancement',
                        'bug',
                        'documentation',
                        'dependencies',
                        'maintenance',
                        'skip-changelog',
                        'duplicate',
                    ];
                    $allSucceeded = true;

                    foreach ($labels as $label) {
                        $result = $this->runCommand([
                            'gh',
                            'label',
                            'create',
                            $label,
                            '--force',
                            '--repo',
                            $this->metadata->packageName(),
                        ]);

                        if (! $result['success']) {
                            $allSucceeded = false;
                        }
                    }

                    if ($allSucceeded) {
                        $tool->removeManualStep(0);
                    }
                }),
        );

        $this->tools->add(
            Tool::from(
                key: 'funding',
                label: 'Funding',
            )->onRemove(fn () => $this->removePath('.github/FUNDING.yml')),
        );

        $this->tools->add(
            Tool::from(
                key: 'security_policy',
                label: 'Security Policy',
            )->onRemove(fn () => [
                $this->removePath('.github/SECURITY.md'),
                $this->removeMarkdownSection($readme, 'Security Vulnerabilities'),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, errors: list<string>, github: array<string, mixed>, summary: array<string, mixed>}
     */
    private function configure(array $options): array
    {
        $selectedFeatures = array_values(
            $options['features'] ?? $this->features->keys(),
        );
        $selectedTools = array_values($options['tools'] ?? $this->tools->keys());

        $github = $this->defaultGithubResult();

        $this->summary = [
            'metadata' => $this->metadata->toArray(),
            'selected_features' => $selectedFeatures,
            'selected_tools' => $selectedTools,
            'removed_paths' => [],
            'modified_files' => [],
            'manual_steps' => [],
        ];

        $this->replacePackageReadme();
        $this->replacePackageAgentsMarkdown();
        $this->replacePlaceholders();
        $this->renamePackageFiles();
        $this->updateComposerJson($selectedFeatures);
        $this->removePath('.agents/skills/skeleton-development');

        foreach (array_diff($this->features->keys(), $selectedFeatures) as $featureToRemove) {
            $this->features->get($featureToRemove)->remove();
        }

        foreach (array_diff($this->tools->keys(), $selectedTools) as $toolToRemove) {
            $this->tools->get($toolToRemove)->remove();
        }

        $this->removeChiselMarkers($selectedFeatures);
        $this->linkClaudeGuidance();
        $this->cleanupEmptyDirectories();

        $formatResult = $this->runCommand([PHP_BINARY, 'vendor/bin/pint', '--quiet']);

        if (! $formatResult['success']) {
            return $this->failed('Code formatting failed: '.$formatResult['output']);
        }

        if ($this->isGithubMode('create')) {
            $github = $this->createGitHubRepository($this->githubConfig);

            if (! $github['success']) {
                return $this->failed($github['message']);
            }
        }

        foreach ($selectedTools as $toolToAdd) {
            $this->tools->get($toolToAdd)->add();
        }

        $this->summary['manual_steps'] = $this->tools->manualSteps();

        if (! $this->isGithubMode('create')) {
            $this->removePath('configure.php');
        }

        $composerBinary = getenv('COMPOSER_BINARY');
        $composerCommand = $composerBinary !== false
            ? [PHP_BINARY, $composerBinary]
            : ['composer'];

        $this->runCommand([...$composerCommand, 'dump-autoload', '--quiet']);

        sort($this->summary['modified_files']);
        sort($this->summary['removed_paths']);

        return $this->success(['summary' => $this->summary]);
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
        $featureTest = $this->rootDir.'/tests/Feature/ExampleTest.php';

        if (empty($selectedFeatures)) {
            $this->removeChiselSection($this->providerPath(), 'any-features');
        } else {
            $this->removeChiselSectionMarkers($this->providerPath(), 'any-features');
        }

        foreach ($selectedFeatures as $section) {
            $this->removeChiselSectionMarkers($this->providerPath(), $section);
            $this->removeChiselSectionMarkers($featureTest, $section);
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

        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        if (is_link($path)) {
            unlink($path);
            $this->trackRemoved($relativePath);

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
            $item->isDir() && ! $item->isLink()
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
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = str_replace('\\', '/', $this->rootDir);

        return str_starts_with($normalizedPath, $normalizedRoot.'/')
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

    private function linkClaudeGuidance(): void
    {
        $this->linkOrCopyPath('AGENTS.md', 'CLAUDE.md');
        $this->linkOrCopyPath('.agents', '.claude');
    }

    private function linkOrCopyPath(string $from, string $to): void
    {
        $source = $this->rootDir.'/'.$from;
        $destination = $this->rootDir.'/'.$to;

        if (! file_exists($source)) {
            return;
        }

        $this->removePath($to);

        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        if (@symlink($from, $destination)) {
            $this->trackModified($to);

            return;
        }

        $this->copyPath($source, $destination);
        $this->trackModified($to);
    }

    private function copyPath(string $source, string $destination): void
    {
        if (! is_dir($source)) {
            copy($source, $destination);

            return;
        }

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination.'/'.substr($item->getPathname(), strlen($source) + 1);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            copy($item->getPathname(), $target);
        }
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
                return $this->failed(
                    'Git repository initialization failed: '.(string) ($initResult['output'] ?? ''),
                    [
                        'command' => $initCommand,
                        'commands' => $commands,
                    ],
                );
            }
        }

        $removeOriginCommand = ['git', 'remote', 'remove', 'origin'];
        $commands[] = $removeOriginCommand;
        $this->runGitHubCommand($removeOriginCommand, $runner);

        $commands[] = $createCommand;
        $result = $this->runGitHubCommand($createCommand, $runner);

        if (! ($result['success'] ?? false)) {
            return $this->failed(
                'GitHub repository creation failed: '.(string) ($result['output'] ?? ''),
                [
                    'command' => $createCommand,
                    'commands' => $commands,
                ],
            );
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

                return $this->failed(
                    'Initial commit push failed: '.(string) ($gitResult['output'] ?? ''),
                    [
                        'command' => $gitCommand,
                        'commands' => $commands,
                        'created_repositories' => [
                            'https://github.com/'.$repository,
                        ],
                    ],
                );
            }
        }

        return $this->success(
            [
                'command' => $createCommand,
                'commands' => $commands,
                'created_repositories' => ['https://github.com/'.$repository],
            ],
        );
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
                sprintf('Include %s', $this->features->get($key)->label),
            ),
            $this->features->keys(),
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
            new InputOption('installer-dir', null, InputOption::VALUE_REQUIRED, 'Directory specified by the Laravel installer'),
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
