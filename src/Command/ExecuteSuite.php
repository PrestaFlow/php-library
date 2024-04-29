<?php

namespace PrestaFlow\Library\Command;

use Exception;
use stdClass;
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

    protected $output;
    protected $outputMode = 'full';
    protected $json = [];

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output format (full, compact, json)', self::OUTPUT_FULL)
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show stats')
            ->addArgument('suite', InputArgument::REQUIRED, 'The suite name');
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

    protected function getOutputMode() : string
    {
        return $this->outputMode;
    }

    protected function debug(string $message)
    {
        $this->output->writeln('<fg=blue;options=bold>INFO</> <fg=white>' . $message . '</>');
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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $this->defineOutputMode($input);

        $suitePath = $input->getArgument('suite');

        $className = '\\PrestaFlow\\Library\\Tests\\Suites\\' . str_replace('/', '\\', $suitePath);

        if (!class_exists($className)) {
            $this->debug($className);
            $this->error('The test suite doesn\'t seem to exist');
            return Command::FAILURE;
        }

        try {
            $suite = new $className();
            $suite->run();

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

            $output->writeln(['']);

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
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    public function pass($test)
    {
        $this->output->writeln(sprintf('  <fg=green;options=bold>PASS</> <fg=white>%s</>', $test['title']));
        if (!empty($test['expect'])) {
            $this->output->writeln(sprintf('  <fg=green>%s</> <fg=gray>%s</>', self::makeIcon(self::PASS), $test['expect']));
        }
    }

    public function fail($test)
    {
        $this->output->writeln(sprintf('  <fg=red;options=bold>FAIL</> <fg=white>%s</>', $test['title']));
        if (!empty($test['expect'])) {
            $this->output->writeln(sprintf('  <fg=red>%s</> <fg=gray>%s</>', self::makeIcon(self::FAIL), $test['expect']));
        }
    }

    public function skip($test)
    {
        $this->output->writeln(sprintf('  <fg=yellow;options=bold>SKIP</> <fg=white>%s</>', $test['title']));
        if (!empty($test['expect'])) {
            $this->output->writeln(sprintf('  <fg=yellow>%s</> <fg=gray>%s</>', self::makeIcon(self::SKIPPED), $test['expect']));
        }
    }

    public function todo($test)
    {
        $this->output->writeln(sprintf('  <fg=blue;options=bold>TODO</> <fg=white>%s</>', $test['title']));
        if (!empty($test['expect'])) {
            $this->output->writeln(sprintf('  <fg=blue>%s</> <fg=gray>%s</>', self::makeIcon(self::TODO), $test['expect']));
        }
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
