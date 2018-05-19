<?php

declare(strict_types=1);

namespace ChangelogGenerator\Command;

use ChangelogGenerator\ChangelogConfig;
use ChangelogGenerator\ChangelogGenerator;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use function count;
use function current;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function getcwd;
use function is_array;
use function sprintf;

class GenerateChangelogCommand extends Command
{
    public const WRITE_STRATEGY_REPLACE = 'replace';
    public const WRITE_STRATEGY_APPEND  = 'append';
    public const WRITE_STRATEGY_PREPEND = 'prepend';

    /** @var ChangelogGenerator */
    private $changelogGenerator;

    public function __construct(ChangelogGenerator $changelogGenerator)
    {
        $this->changelogGenerator = $changelogGenerator;

        parent::__construct();
    }

    protected function configure() : void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate a changelog markdown document from a GitHub milestone.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a changelog markdown document from a GitHub milestone:

    <info>%command.full_name% --user=doctrine --repository=migrations --milestone=2.0</info>

You can filter the changelog by label names using the --label option:

    <info>%command.full_name% --user=doctrine --repository=migrations --milestone=2.0 --label=Enhancement --label=Bug</info>
EOT
            )
            ->addOption(
                'user',
                null,
                InputOption::VALUE_REQUIRED,
                'User that owns the repository.'
            )
            ->addOption(
                'repository',
                null,
                InputOption::VALUE_REQUIRED,
                'The repository owned by the user.'
            )
            ->addOption(
                'milestone',
                null,
                InputOption::VALUE_REQUIRED,
                'The milestone to build the changelog for.'
            )
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Write the changelog to a file.',
                false
            )
            ->addOption(
                'append',
                null,
                InputOption::VALUE_NONE,
                'Append the changelog to the file.'
            )
            ->addOption(
                'prepend',
                null,
                InputOption::VALUE_NONE,
                'Prepend the changelog to the file.'
            )
            ->addOption(
                'label',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The labels to generate a changelog for.'
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'The path to a configuration file.'
            )
            ->addOption(
                'project',
                'p',
                InputOption::VALUE_REQUIRED,
                'The project from the configuration to generate a changelog for.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $changelogOutput = $this->getChangelogOutput($input, $output);

        $this->changelogGenerator->generate(
            $this->getChangelogConfig($input),
            $changelogOutput
        );

        if ($this->getFileWriteStrategy($input) !== self::WRITE_STRATEGY_PREPEND) {
            return;
        }

        $file = $this->getChangelogFilePath($input->getOption('file'));

        if (! ($changelogOutput instanceof BufferedOutput)) {
            return;
        }

        file_put_contents($file, $changelogOutput->fetch() . file_get_contents($file));
    }

    private function getChangelogConfig(InputInterface $input) : ChangelogConfig
    {
        $user       = $input->getOption('user');
        $repository = $input->getOption('repository');
        $milestone  = $input->getOption('milestone');
        $labels     = $input->getOption('label');

        $changelogConfig = $this->loadConfigFile($input);

        if ($changelogConfig !== null) {
            return $changelogConfig;
        }

        return new ChangelogConfig(
            $user,
            $repository,
            $milestone,
            $labels
        );
    }

    /**
     * @return false|resource
     */
    protected function fopen(string $file, string $mode)
    {
        return fopen($file, $mode);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function createOutput(string $file, string $fileWriteStrategy) : OutputInterface
    {
        if ($fileWriteStrategy === self::WRITE_STRATEGY_PREPEND) {
            return new BufferedOutput();
        }

        $handle = $this->fopen($file, $this->getFileHandleMode($fileWriteStrategy));

        if ($handle === false) {
            throw new InvalidArgumentException(sprintf('Could not open handle for %s', $file));
        }

        return new StreamOutput($handle);
    }

    private function getFileHandleMode(string $fileWriteStrategy) : string
    {
        if ($fileWriteStrategy === self::WRITE_STRATEGY_APPEND) {
            return 'a+';
        }

        return 'w+';
    }

    private function getChangelogOutput(InputInterface $input, OutputInterface $output) : OutputInterface
    {
        $file              = $input->getOption('file');
        $fileWriteStrategy = $this->getFileWriteStrategy($input);

        $changelogOutput = $output;

        if ($file !== false) {
            $changelogOutput = $this->createOutput($this->getChangelogFilePath($file), $fileWriteStrategy);
        }

        return $changelogOutput;
    }

    private function getFileWriteStrategy(InputInterface $input) : string
    {
        $append  = (bool) $input->getOption('append');
        $prepend = (bool) $input->getOption('prepend');

        if ($append) {
            return self::WRITE_STRATEGY_APPEND;
        }

        if ($prepend) {
            return self::WRITE_STRATEGY_PREPEND;
        }

        return self::WRITE_STRATEGY_REPLACE;
    }

    private function getChangelogFilePath(?string $file) : string
    {
        return $file === null ? sprintf('%s/CHANGELOG.md', getcwd()) : $file;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function loadConfigFile(InputInterface $input) : ?ChangelogConfig
    {
        $config = $input->getOption('config');

        if ($config === null) {
            $config = 'changelog-generator-config.php';

            if (! file_exists($config)) {
                return null;
            }
        }

        if (! file_exists($config)) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" does not exist.', $config));
        }

        $changelogConfigs = include $config;

        if (! is_array($changelogConfigs) || count($changelogConfigs) === 0) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" did not return anything.', $config));
        }

        $changelogConfig = $this->findChangelogConfig($input, $changelogConfigs);

        $this->overrideChangelogConfig($input, $changelogConfig);

        return $changelogConfig;
    }

    /**
     * @param ChangelogConfig[] $changelogConfigs
     */
    private function findChangelogConfig(InputInterface $input, array $changelogConfigs) : ChangelogConfig
    {
        $project = $input->getOption('project');

        $changelogConfig = current($changelogConfigs);

        if ($project !== null) {
            if (! isset($changelogConfigs[$project])) {
                throw new InvalidArgumentException(sprintf('Could not find project named "%s" configured', $project));
            }

            $changelogConfig = $changelogConfigs[$project];
        }

        return $changelogConfig;
    }

    private function overrideChangelogConfig(InputInterface $input, ChangelogConfig $changelogConfig) : void
    {
        $user       = $input->getOption('user');
        $repository = $input->getOption('repository');
        $milestone  = $input->getOption('milestone');
        $labels     = $input->getOption('label');

        if ($user !== null) {
            $changelogConfig->setUser($user);
        }

        if ($repository !== null) {
            $changelogConfig->setRepository($repository);
        }

        if ($milestone !== null) {
            $changelogConfig->setMilestone($milestone);
        }

        if ($labels === []) {
            return;
        }

        $changelogConfig->setLabels($labels);
    }
}
