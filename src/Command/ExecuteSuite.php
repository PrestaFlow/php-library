<?php

namespace PrestaFlow\Library\Command;

use Error;
use PrestaFlow\Library\Utils\Output;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'run',
    description: 'Execute test suite',
    hidden: false,
    aliases: ['run-test']
)]
class ExecuteSuite extends Command
{
    protected $io;
    protected $output;
    protected $sections = [
        'title' => null,
        'progressIndicator' => null,
        'progressBar' => null,
    ];
    protected $json = [];

    protected $debugModeDetected = null;

    protected $draftMode = null;
    protected $groups = ['all'];
    protected $debugMode = false;

    protected $file = 'prestaflow/results.json';

    use Output;

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output format (full, compact, json)', self::OUTPUT_FULL)
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show stats')
            ->addOption('file', 'f', InputOption::VALUE_NONE, 'Output to file')
            ->addOption('draft', 'd', InputOption::VALUE_NEGATABLE, 'Draft mode')
            ->addArgument('folder', InputArgument::OPTIONAL, 'The folder name', 'tests')
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Execute tests on specific group ?',
                ['all']
            )
        ;
    }

    protected function defineOutputMode(InputInterface $input)
    {
        $optionValue = strtolower($input->getOption('output'));
        if (false === $optionValue) {
            // in this case, the option was not passed when running the command
            $this->outputMode = self::OUTPUT_FULL;
        } elseif (null === $optionValue) {
            // in this case, the option was passed when running the command
            // but no value was given to it
            $this->outputMode = self::OUTPUT_FULL;
        } else {
            // in this case, the option was passed when running the command and
            // some specific value was given to it
            $this->outputMode = self::OUTPUT_FULL;
            if (self::OUTPUT_COMPACT === $optionValue) {
                $this->outputMode = self::OUTPUT_COMPACT;
            } elseif (self::OUTPUT_JSON === $optionValue) {
                $this->outputMode = self::OUTPUT_JSON;
            }
        }
    }

    protected function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function initSections($output)
    {
        if (self::OUTPUT_JSON !== $this->getOutputMode()) {
            $this->sections['title'] = $output->section();
            $this->sections['title']->setMaxHeight(2);

            $this->outputTitle();
        }

        $this->sections['progressBar'] = $output->section();
        $this->sections['progressBar']->setMaxHeight(1);

        $this->sections['progressIndicator'] = new ProgressIndicator($this->sections['progressBar'], 'verbose', 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
        $this->sections['progressIndicator']->start('Processing...');
    }

    protected function outputTitle()
    {
        $this->sections['title']->write('');
        $this->sections['title']->write(sprintf('<fg=bright-cyan>%s</>' . PHP_EOL, '𝗣𝗿𝗲𝘀𝘁𝗮𝗙𝗹𝗼𝘄 | v' . \PrestaFlow\Library\Traits\AppVersion::APP_VERSION));
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->handleDir(dirname($this->file));
        $this->cli = true;
        $this->output = $output;

        $this->defineOutputMode($input);

        $this->initSections($output);

        $start_time = hrtime(true);

        $this->draftMode = $input->getOption('draft') ?? null;

        $this->groups = $input->getOption('group') ?? ['all'];

        $folderPath = ucfirst($input->getArgument('folder'));

        if (!is_dir($folderPath) || !is_dir($folderPath)) {
            $this->sections['progressIndicator']->finish('Finished');
            $this->sections['progressBar']->clear();
            throw new Error(sprintf('The suites folder [%s] doesn\'t seem to exist', $folderPath));
        }

        $testSuites = $this->getTestsSuites($folderPath);

        if (!count($testSuites)) {
            $this->sections['progressIndicator']->finish('Finished');
            $this->sections['progressBar']->clear();

            $this->success('Tests folder is empty', newLine: true);
            return Command::SUCCESS;
        };

        $nbSuites = 0;
        foreach ($testSuites as $suitePath) {
            $this->sections['progressIndicator']->advance();

            if (!str_ends_with($suitePath, '.php')) {
                continue;
            }

            $testFile = file_get_contents($suitePath);
            $namespace = str_replace('namespace ', '', str_replace(';', '', preg_grep('/namespace\s+(.+?);$/sm', explode("\n", $testFile))));
            if (is_array($namespace)) {
                $namespace = array_values($namespace)[0];
            } else {
                $namespace = '';
            }

            $pathSplits = explode('/', $suitePath);

            $className = $namespace . '\\' . str_replace('.php', '', $pathSplits[count($pathSplits) - 1]);

            $continue = true;
            try {
                $suite = new $className();
            } catch (Error $e) {
                $continue = false;
            }

            if (!$continue) {
                continue;
            }

            try {
                if ($this->isExecutable($suite)) {
                    $nbSuites++;
                    // Create a new section
                    $sectionId = ($this->cli ? 'cli-' : '') . sha1(str_replace('\\', '-', $className));
                    if (!array_key_exists($sectionId, $this->outputSections)) {
                        if (self::OUTPUT_JSON !== $this->getOutputMode()) {
                            $this->outputSections[$sectionId] = $this->output->section();
                        } else {
                            $this->outputSections[$sectionId] = [];
                        }
                    }
                    // -End

                    $this->outputNewLine(section: $sectionId);

                    $this->debugMode = $suite->isDebugMode();

                    if ($this->isDebugMode()) {
                        $this->debug('Locale: ' . $suite->getLocale(), section: $sectionId);
                    }

                    $this->outputSections[$sectionId] = $suite->run(
                        cli: true,
                        output: $this->output,
                        mode: $this->outputMode,
                        section: $sectionId,
                        sectionOutput: $this->outputSections[$sectionId]
                    );

                    foreach ($suite->warnings as $warning) {
                        if (!empty($warning)) {
                            match ($warning) {
                                'debug-mode' => $this->debugModeDetected = true,
                                default => $this->warning($warning, newLine: true, section: $sectionId)
                            };
                        }
                    }

                    if (self::OUTPUT_JSON === $this->getOutputMode()) {
                        if ($input->getOption('file')) {
                            $this->filePutContents($this->file, json_encode($suite->results(false), JSON_PRETTY_PRINT));
                            $this->success('Results saved to ' . $this->file, newLine: true, force: true);
                        } else {
                            $this->output->writeLn(json_encode($suite->results(false), JSON_PRETTY_PRINT));
                        }
                    }
                } else {
                    if ($this->isDebugMode()) {
                        // $this->debug('Not executable', section: $sectionId);
                    }
                }
            } catch (Error $e) {
                throw $e;
            }
        }

        $this->sections['progressIndicator']->finish('Finished');
        $this->sections['progressBar']->clear();

        if (!$nbSuites) {
            $this->success('Tests folder is empty', newLine: true);
            return Command::SUCCESS;
        };

        $end_time = hrtime(true);
        $time = round(($end_time - $start_time) / 1e+6);

        if ($this->debugModeDetected) {
            $this->cli(baseLine: '', bold: true, titleColor: 'yellow', title: 'WARNING', secondaryColor: 'white', message: 'debug-mode', newLine: true, section: 'warnings');
        }

        $message = sprintf('%ss', $this->formatSeconds($time));
        $this->cli(baseLine: '', bold: false, titleColor: 'gray', title: 'Duration:', secondaryColor: 'white', message: $message, newLine: true, section: 'duration');

        return Command::SUCCESS;
    }

    protected function handleDir($path)
    {
        if (is_dir($path)) {
            $this->emptyDir($path);
        }

        mkdir($path, 0777, true);
    }

    protected function emptyDir($path)
    {
        $dir = new \DirectoryIterator($path);

        // Iterate through the subdirectories / files of the provided directory
        foreach ($dir as $dir_info) {

            // Exclude the . (current directory) and .. (parent directory) paths
            // from the directory iteration
            if (! $dir_info->isDot()) {

                // Get the full currently iterated path
                $iterated_path = $dir_info->getPathname();

                // If the currently iterated path is a directory
                if ($dir_info->isDir()) {

                    // which is not empty (in which case scandir returns an array of not 2 (. and ..) elements)
                    if (count(scandir($iterated_path)) !== 2) {

                        // Call the function recursively
                        $this->emptyDir($iterated_path);
                    } else {

                        // if the currently iterated path is an empty directory, remove it
                        rmdir($iterated_path);
                    }
                } elseif ($dir_info->isFile()) {

                    // If the currently iterated path describes a file, we need to
                    // delete that file
                    unlink($iterated_path);
                }
            } // loop which opens if the currently iterated directory is neither . nor ..

        } // end of iteration through directories / files of provided path

        // After iterating through the subpaths of the provided path, remove the
        // concerned path
        rmdir($path);
    }

    protected function filePutContents($fullPath, $contents, $flags = 0)
    {
        $parts = explode('/', $fullPath);
        array_pop($parts);
        $dir = implode('/', $parts);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $contents, $flags);
    }

    protected function isExecutable($suite)
    {
        // Check if the suite is an instance of TestsSuite
        if (
            !is_subclass_of($suite, 'PrestaFlow\Library\Tests\TestsSuite')
            || get_class($suite) === 'PrestaFlow\Library\Tests\TestsSuite'
        ) {
            return false;
        }

        // Drafts
        $matchDraft = false;
        if ($this->draftMode !== null) {
            if ($this->draftMode && $suite->isDraft()) {
                $matchDraft = true;
            } elseif (!$this->draftMode && !$suite->isDraft()) {
                $matchDraft = true;
            }
        } else {
            $matchDraft = true;
        }

        // Groups
        $matchGroups = true;
        if (!in_array('all', $this->groups)) {
            if (count($this->groups)) {
                $matchGroups = false;
                if (is_array($suite->getGroups())) {
                    foreach ($suite->getGroups() as $group) {
                        if (in_array($group, $this->groups)) {
                            $matchGroups = true;
                        }
                    }
                } else {
                    if (in_array($suite->getGroups(), $this->groups)) {
                        $matchGroups = true;
                    }
                }
            }
        }

        return $matchDraft && $matchGroups;
    }

    public function getTestsSuites($folderPath)
    {
        $testSuites = [];
        $folderFiles = scandir($folderPath);
        if (is_array($folderFiles)) {
            foreach ($folderFiles as $folderFile) {
                if ($folderFile != '.' && $folderFile != '..') {
                    if (is_dir($folderPath . '/' . $folderFile)) {
                        foreach ($this->getTestsSuites($folderPath . '/' . $folderFile) as $childFolderFile) {
                            $testSuites[] = $childFolderFile;
                        }
                    } else {
                        $testSuites[] = $folderPath . '/' . $folderFile;
                    }
                }
            }
        }
        return $testSuites;
    }
}
