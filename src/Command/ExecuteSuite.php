<?php

namespace PrestaFlow\Library\Command;

use Exception;
use PrestaFlow\Library\Tests\TestsSuite;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
    protected $outputMode = 'full';
    protected $json = [];

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output format (full, compact, json)', self::OUTPUT_FULL)
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show stats')
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

    protected function debug(string|array $message, bool $newLine = false)
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT);
        }
        $this->output->writeln('<fg=blue;options=bold>INFO</> <fg=white>' . $message . '</>');

        if ($newLine) {
            $this->output->writeln('');
        }
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

        $this->defineOutputMode($input);

        $folderPath = $input->getArgument('folder');

        if (!is_dir($folderPath) || !is_dir(ucfirst($folderPath))) {
            $this->debug($folderPath);
            $this->error('The suites folder doesn\'t seem to exist');
            return Command::FAILURE;
        }

        $testSuites = $this->getTestsSuites($folderPath);

        if (!count($testSuites)) {
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
                if (is_subclass_of($suite, 'PrestaFlow\Library\Tests\TestsSuite')
                    && get_class($suite) !== 'PrestaFlow\Library\Tests\TestsSuite') {
                    $globals = $suite->getGlobals();
                    if ($globals['DEBUG']) {
                        $this->debug($globals, newLine: true);
                    }

                    $this->debug($className);

                    $suite->run(cli: true);

                    $results = $suite->results(false);

                    foreach ($results['tests'] as $test) {
                        $output->writeln('');
                        match ($test['state']) {
                            self::PASS => $this->pass($test),
                            self::FAIL => $this->fail($test),
                            self::SKIPPED => $this->skip($test),
                            self::TODO => $this->todo($test),
                        };
                    }

                    $this->io->newLine();

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
                            '  <fg=gray>Tests:</>    <fg=default>%s</><fg=gray> (%s assertions)</>',
                            implode('<fg=gray>,</> ', $tests),
                            (int) $results['stats']['assertions']
                        ),
                    ]);

                    //
                    $seconds = number_format($results['stats']['time'] / 1000, 2, '.', '');
                    $output->writeln(sprintf('  <fg=gray>Duration:</> <fg=white>%ss</>', $seconds));
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());

                //return Command::FAILURE;
            }
            $this->io->newLine();
        }

        return Command::SUCCESS;
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
        if (!empty($test['expect'])) {
            foreach ($test['expect'] as $state => $expectMessages) {
                foreach ($expectMessages as $expectMessage) {
                    if (is_string($expectMessage) && !str_contains($expectMessage, '[Debug] This page has moved')) {
                        match ($state) {
                            self::PASS => $this->output->writeln(sprintf('  <fg=green>%s</> <fg=gray>%s</>', self::makeIcon(self::PASS), $expectMessage)),
                            self::FAIL => $this->output->writeln(sprintf('  <fg=red>%s</> <fg=gray>%s</>', self::makeIcon(self::FAIL), $expectMessage)),
                            self::SKIPPED => $this->output->writeln(sprintf('  <fg=yellow>%s</> <fg=gray>%s</>', self::makeIcon(self::SKIPPED), $expectMessage)),
                            self::TODO => $this->output->writeln(sprintf('  <fg=blue>%s</> <fg=gray>%s</>', self::makeIcon(self::TODO), $expectMessage)),
                            default => $this->output->writeln(sprintf('  <fg=gray>%s</> <fg=gray>%s</>', self::makeIcon(self::TODO), $expectMessage))
                        };
                    }
                }
            }
        }
    }

    public function pass($test)
    {
        $this->output->writeln(sprintf('  <fg=green;options=bold>PASS</> <fg=white>%s</>', $test['title']));

        $this->expects($test);
    }

    public function fail($test)
    {
        $this->output->writeln(sprintf('  <fg=red;options=bold>FAIL</> <fg=white>%s</>', $test['title']));

        $this->expects($test);
    }

    public function skip($test)
    {
        $this->output->writeln(sprintf('  <fg=yellow;options=bold>SKIP</> <fg=white>%s</>', $test['title']));

        $this->expects($test);
    }

    public function todo($test)
    {
        $this->output->writeln(sprintf('  <fg=blue;options=bold>TODO</> <fg=white>%s</>', $test['title']));

        $this->expects($test);
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
