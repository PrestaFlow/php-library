<?php

namespace PrestaFlow\Library\Command;

use Exception;
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

    protected $output;

    protected function configure(): void
    {
        $this
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show stats')
            ->addArgument('suite', InputArgument::REQUIRED, 'The suite name');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $suitePath = $input->getArgument('suite');

        $className = '\\PrestaFlow\\Library\\Tests\\Suites\\' . str_replace('/', '\\', $suitePath);

        if (!class_exists($className)) {
            $output->writeln('<fg=red;options=bold>Suite seems not exists</>');

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
                    '?'
                ),
            ]);

            //
            $seconds = number_format($results['stats']['time'] / 1000, 2, '.', '');
            $output->writeln(sprintf('  <fg=gray>Duration:</> <fg=white>%ss</>', $seconds));
        } catch (Exception $e) {
            $output->writeln('<fg=red;options=bold>' . $e->getMessage() . '</>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    public function pass($test)
    {
        $this->output->writeln(sprintf('  <fg=green;options=bold>PASS</> <fg=white>%s</>', $test['title']));
        $this->output->writeln(sprintf('  <fg=green>%s</> <fg=gray>that true is true</>', self::makeIcon(self::PASS)));
    }

    public function fail($test)
    {
        $this->output->writeln(sprintf('  <fg=red;options=bold>FAIL</> <fg=white>%s</>', $test['title']));
        $this->output->writeln(sprintf('  <fg=red>%s</> <fg=gray>that true is true</>', self::makeIcon(self::FAIL)));
    }

    public function skip($test)
    {
        $this->output->writeln(sprintf('  <fg=yellow;options=bold>SKIP</> <fg=white>%s</>', $test['title']));
        $this->output->writeln(sprintf('  <fg=yellow>%s</> <fg=gray>that true is true</>', self::makeIcon(self::SKIPPED)));
    }

    public function todo($test)
    {
        $this->output->writeln(sprintf('  <fg=blue;options=bold>TODO</> <fg=white>%s</>', $test['title']));
        $this->output->writeln(sprintf('  <fg=blue>%s</> <fg=gray>that true is true</>', self::makeIcon(self::TODO)));
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
