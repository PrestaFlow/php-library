<?php

namespace PrestaFlow\Library\Console;

use PrestaFlow\Library\Command\ExecuteSuite;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The `prestaflow` console application.
 *
 * Lives here rather than in bin/prestaflow so its exit codes can be tested.
 * An uncaught exception or error (Chrome that does not start, a suites path
 * that does not exist) is rendered as ERROR/TRACE lines and ends the run with
 * a non-zero exit code; a run whose assertions fail still exits with 1, and a
 * clean run with 0.
 */
class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('PrestaFlow', \PrestaFlow\Library\Traits\AppVersion::APP_VERSION);

        $this->add(new ExecuteSuite());

        // Most failures inside a run are \Error (resolveSuitePaths() throws one):
        // without this they would escape run() as a PHP fatal error.
        $this->setCatchErrors(true);
    }

    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void
    {
        $output->writeln(sprintf('<fg=red;options=bold>ERROR</> <fg=white>%s</>', $e->getMessage()));
        $output->writeln(sprintf('<fg=gray;options=bold>TRACE</> <fg=white>%s</>', $e->getFile() . ':' . $e->getLine()));

        foreach ($e->getTrace() as $trace) {
            $output->writeln(sprintf('<fg=gray;options=bold>TRACE</> <fg=white>%s</>', ($trace['file'] ?? '[internal]') . ':' . ($trace['line'] ?? '?')));
        }
    }
}
