<?php

namespace PrestaFlow\Library\Command;

use Error;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'run',
    description: 'Execute test suite',
    hidden: false,
    aliases: ['run-test']
)]
class ExecuteSuite extends Command
{
    public const FAIL = 'fail';

    public const SKIPPED = 'skip';

    public const RUNS = 'pending';

    public const PASS = 'pass';

    public const TODO = 'todo';

    public const OUTPUT_FULL = 'full';
    public const OUTPUT_COMPACT = 'compact';
    public const OUTPUT_JSON = 'json';

    protected $io;
    protected $output;
    protected $json = [];

    protected $debugModeDetected = null;

    protected $outputMode = 'full';
    protected $draftMode = null;
    protected $verboseMode = true;
    protected $debugMode = false;

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output format (full, compact, json)', self::OUTPUT_FULL)
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show stats')
            ->addOption('draft', 'd', InputOption::VALUE_NEGATABLE, 'Draft mode')
            ->addArgument('folder', InputArgument::OPTIONAL, 'The folder name', 'tests');
    }

    protected function defineOutputMode(InputInterface $input)
    {
        $optionValue = $input->getOption('output');
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

    protected function getOutputMode(): string
    {
        return $this->outputMode;
    }

    protected function isVerboseMode(): bool
    {
        return $this->verboseMode;
    }

    protected function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    protected function error(string $message)
    {
        if (self::OUTPUT_FULL === $this->getOutputMode()) {
            $this->output->writeln('<fg=red;options=bold>ERROR</> <fg=white>' . $message . '</>');
        } else if (self::OUTPUT_COMPACT === $this->getOutputMode()) {
            $this->output->writeln('<fg=red;options=bold>ERROR</>');
        } else if (self::OUTPUT_JSON === $this->getOutputMode()) {
            $this->output->writeln(
                json_encode(
                    [
                        'hasError' => true,
                        'error' => $message,
                    ]
                )
            );
        }
    }

    protected function success(string $message)
    {
        if (self::OUTPUT_FULL === $this->getOutputMode()) {
            $this->output->writeln('<fg=green;options=bold>SUCCESS</> <fg=white>' . $message . '</>');
        } else if (self::OUTPUT_COMPACT === $this->getOutputMode()) {
            $this->output->writeln('<fg=green;options=bold>SUCCESS</>');
        } else if (self::OUTPUT_JSON === $this->getOutputMode()) {
            $this->output->writeln(
                json_encode(
                    [
                        'hasError' => false,
                        'message' => $message,
                    ]
                )
            );
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;

        $start_time = hrtime(true);

        $this->defineOutputMode($input);

        $this->draftMode = $input->getOption('draft') ?? null;

        $folderPath = $input->getArgument('folder');

        if (!is_dir($folderPath) || !is_dir(ucfirst($folderPath))) {
            $this->outputNewLine();
            $this->debug($folderPath);
            $this->error('The suites folder doesn\'t seem to exist');
            return Command::FAILURE;
        }

        $testSuites = $this->getTestsSuites($folderPath);

        if (!count($testSuites)) {
            $this->outputNewLine();
            $this->success('Tests folder is empty');
            return Command::SUCCESS;
        };

        foreach ($testSuites as $suitePath) {
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

            $className = $namespace . '\\' . str_replace('.php', '', $pathSplits[count($pathSplits)-1]);

            try {
                $suite = new $className();
                if ($this->isExecutable($suite)) {

                    $this->outputNewLine();

                    $this->verboseMode = $suite->isVerboseMode();
                    $this->debugMode = $suite->isDebugMode();

                    if ($this->isDebugMode()) {
                        //$this->debug($globals, newLine: true);
                    }

                    if ($this->isDebugMode()) {
                        $this->debug($className, newLine: true);
                    }

                    if ($this->isDebugMode()) {
                        $this->debug('Locale: ' . $suite->getLocale());
                    }

                    $suite->run(cli: true);

                    $results = $suite->results(false);
                    $this->info($suite->getDescribe());

                    foreach ($results['tests'] as $test) {
                        if (isset($test['warning']) && !empty($test['warning'])) {
                            match ($test['warning']) {
                                'debug-mode' => $this->debugModeDetected = true,
                                default => $this->warning($test['warning'], newLine: true)
                            };
                        } else {
                            $output->writeln('');
                        }
                        match ($test['state']) {
                            self::PASS => $this->pass($test),
                            self::FAIL => $this->fail($test),
                            self::SKIPPED => $this->skip($test),
                            self::TODO => $this->todo($test),
                        };

                        if ($this->isDebugMode()) {
                            $output->writeln(sprintf('  <fg=gray>Duration:</> <fg=white>%ss</>', $this->formatSeconds($test['time'])));
                        }
                    }

                    $this->outputNewLine();

                    $tests = [];
                    if ($results['stats']['failures']) {
                        $tests[] = sprintf('<fg=red;options=bold>%d failures</>', $results['stats']['failures']);
                    }
                    if ($results['stats']['passes']) {
                        $tests[] = sprintf('<fg=green;options=bold>%d passed</>', $results['stats']['passes']);
                    }
                    if ($results['stats']['skips']) {
                        $tests[] = sprintf('<fg=bright-yellow;options=bold>%d skips</>', $results['stats']['skips']);
                    }
                    if ($results['stats']['todos']) {
                        $tests[] = sprintf('<fg=blue;options=bold>%d todos</>', $results['stats']['todos']);
                    }

                    $output->writeln([
                        sprintf(
                            '    <fg=gray>Tests:</>    <fg=default>%s</><fg=gray> (%s assertions)</>',
                            implode('<fg=gray>,</> ', $tests),
                            (int) $results['stats']['assertions']
                        ),
                    ]);

                    if ($this->debugModeDetected) {
                        $this->output->writeln('');
                        $this->warning('debug-mode', newLine: false);
                        $this->output->writeln('');
                    }

                    //
                    $output->writeln(sprintf('    <fg=gray>Duration:</> <fg=white>%ss</>', $this->formatSeconds($results['stats']['time'])));
                }
            } catch (Error $e) {
                $this->error($e->getMessage());

                //return Command::FAILURE;
            }
        }

        $this->outputNewLine();

        $end_time = hrtime(true);
        $time = round(($end_time - $start_time) / 1e+6);

        $output->writeln(sprintf('  <fg=gray>Duration:</> <fg=white>%ss</>', $this->formatSeconds($time)));

        return Command::SUCCESS;
    }

    protected function isExecutable($suite)
    {
        // Check if the suite is an instance of TestsSuite
        if (!is_subclass_of($suite, 'PrestaFlow\Library\Tests\TestsSuite')
            && get_class($suite) === 'PrestaFlow\Library\Tests\TestsSuite') {
                return false;
        }

        // Draft
        if ($this->draftMode !== null) {
            if ($this->draftMode && !$suite->isDraft()) {
                if ($this->isDebugMode()) {
                    $this->debug(get_class($suite), baseLine: '  ');
                }
                return false;
            } elseif (!$this->draftMode && $suite->isDraft()) {
                if ($this->isDebugMode()) {
                    $this->debug(get_class($suite), baseLine: '  ');
                }
                return false;
            }
        }

        return true;
    }

    protected function formatSeconds($time)
    {
        return number_format($time / 1000, 2, '.', '');
    }

    public function getTestsSuites($folderPath)
    {
        $testSuites = [];
        $folderFiles = scandir($folderPath);
        if (is_array($folderFiles)) {
            foreach ($folderFiles as $folderFile) {
                if ($folderFile != '.' && $folderFile != '..') {
                    if (is_dir($folderPath.'/'.$folderFile)) {
                        foreach ($this->getTestsSuites($folderPath.'/'.$folderFile) as $childFolderFile) {
                            $testSuites[] = $childFolderFile;
                        }
                    } else {
                        $testSuites[] = $folderPath.'/'.$folderFile;
                    }
                }
            }
        }
        return $testSuites;
    }

    public function expects($test)
    {
        $baseLine = str_repeat('  ', 3);

        if (!empty($test['expect'])) {
            foreach ($test['expect'] as $state => $expectMessages) {
                foreach ($expectMessages as $expectMessage) {
                    if (is_string($expectMessage) && !str_contains($expectMessage, '[Debug] This page has moved')) {
                        match ($state) {
                            self::PASS => $this->outputText(baseLine: $baseLine, state: self::PASS, title: self::makeIcon(self::PASS), message: $expectMessage, secondaryColor: 'gray'),
                            self::FAIL => $this->outputText(baseLine: $baseLine, state: self::FAIL, title: self::makeIcon(self::FAIL), message: $expectMessage, secondaryColor: 'gray'),
                            self::SKIPPED => $this->outputText(baseLine: $baseLine, state: self::SKIPPED, title: self::makeIcon(self::SKIPPED), message: $expectMessage, secondaryColor: 'gray'),
                            self::TODO => $this->outputText(baseLine: $baseLine, state: self::TODO, title: self::makeIcon(self::TODO), message: $expectMessage, secondaryColor: 'gray'),
                            default => $this->outputText(baseLine: $baseLine, state: self::TODO, title: self::makeIcon(self::TODO), message: $expectMessage, secondaryColor: 'gray')
                        };
                    }
                }
            }
        }
    }

    public function getColor(string $state): string
    {
        return match ($state) {
            self::PASS => 'green',
            self::FAIL => 'red',
            self::SKIPPED => 'yellow',
            self::TODO => 'blue',
            default => 'gray',
        };
    }

    public function outputNewLine()
    {
        if ($this->outputMode !== self::OUTPUT_JSON) {
            $this->io->newLine();
        }
    }

    public function outputText(string $baseLine = '', string $state = 'default', string $title = '', string $message = '', string $secondaryColor = 'white')
    {
        if (self::OUTPUT_FULL === $this->getOutputMode()) {
            $output = sprintf($baseLine . '<fg=%s>%s</> <fg=%s>%s</>', $this->getColor($state), $title, $secondaryColor, $message);
            $this->output->writeln($output);
        } else if (self::OUTPUT_COMPACT === $this->getOutputMode()) {
            $output = sprintf($baseLine . '<fg=%s>%s</>', $this->getColor($state), $title);
            $this->output->writeln($output);
        } else if (self::OUTPUT_JSON === $this->getOutputMode()) {
            $this->output->writeln(
                json_encode(
                    [
                        'title' => $title,
                        'message' => $message,
                    ]
                )
            );
        }
    }

    protected function debug(string|array $message, string $baseLine = '  ', bool $newLine = false)
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT);
        }

        if ($newLine) {
            $this->output->writeln('');
            $baseLine = '';
        }

        $this->output->writeln(sprintf($baseLine . '<fg=blue;options=bold>INFO</> <fg=white>%s</>', $this->getHumanString($message)));

        if ($newLine) {
            $this->output->writeln('');
        }
    }

    protected function info(string|array $message, string $baseLine = '  ', bool $newLine = false)
    {
        return $this->debug($message, $baseLine, $newLine);
    }

    public function pass($test, string $baseLine = '    ')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }


        $this->output->writeln(sprintf($baseLine . '<fg=green;options=bold>PASS</> <fg=white>%s</>', $this->getHumanString($title)));

        if ($this->isVerboseMode()) {
            $this->expects($test);
        }
    }

    public function warning($test, string $baseLine = '  ', $newLine = false)
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        if ($newLine) {
            $this->output->writeln('');
            $baseLine = '';
        }

        $this->output->writeln(sprintf($baseLine . '<fg=yellow;options=bold>WARNING</> <fg=white>%s</>', $this->getHumanString($title)));

        if ($newLine) {
            $this->output->writeln('');
        }

        $this->expects($test);
    }

    public function fail($test, string $baseLine = '    ')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->output->writeln(sprintf($baseLine . '<fg=red;options=bold>FAIL</> <fg=white>%s</>', $this->getHumanString($title)));

        $this->expects($test);
    }

    public function skip($test, string $baseLine = '    ')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->output->writeln(sprintf($baseLine . '<fg=yellow;options=bold>SKIP</> <fg=white>%s</>', $this->getHumanString($title)));

        if ($this->isVerboseMode()) {
            $this->expects($test);
        }
    }

    public function todo($test, string $baseLine = '    ')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->output->writeln(sprintf($baseLine . '<fg=blue;options=bold>TODO</> <fg=white>%s</>', $this->getHumanString($title)));

        if ($this->isVerboseMode()) {
            $this->expects($test);
        }
    }

    protected function getHumanString(string $message)
    {
        return match ($message) {
            'debug-mode' => 'Your shop is running in debug mode',
            default => $message
        };
    }

    /**
     * Get the test case icon.
     */
    public static function makeIcon(string $type): string
    {
        switch ($type) {
            case self::FAIL:
                return '⨯';
            case self::SKIPPED:
                return '-';
            case self::RUNS:
                return '•';
            case self::TODO:
                return '↓';
            default:
                return '✓';
        }
    }
}
