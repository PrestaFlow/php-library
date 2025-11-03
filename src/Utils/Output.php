<?php

namespace PrestaFlow\Library\Utils;

trait Output
{
    public const FAIL = 'fail';

    public const SKIP = 'skip';

    public const SKIPPED = 'skipped';

    public const RUNS = 'pending';

    public const PASS = 'pass';

    public const TODO = 'todo';

    public const OUTPUT_FULL = 'full';
    public const OUTPUT_COMPACT = 'compact';
    public const OUTPUT_JSON = 'json';

    public $cli = false;

    /*
     * @var OutputInterface
     */
    protected $output;
    public $outputSections = [];
    protected $outputMode = 'full';

    protected function getOutputMode(): string
    {
        return $this->outputMode;
    }

    public function cli(string $baseLine = '  ', bool $bold = true, bool $newLine = false, string $titleColor = '', string $title = '', string $secondaryColor = 'white', string $message = '', string $section = 'default', bool $force = false)
    {
        if ($this->cli) {
            if ($this->getOutputMode() !== self::OUTPUT_JSON || $force === true) {
                if (!array_key_exists($section, $this->outputSections)) {
                    $this->outputSections[$section] = $this->output->section();
                }

                if ($newLine) {
                    $this->outputSections[$section]->writeln('');
                }

                if ($bold) {
                    $titleColor .= ';options=bold';
                }

                $message = sprintf($baseLine . '<fg=%s>%s</> <fg=%s>%s</>', $titleColor, $title, $secondaryColor, $this->getHumanString($message));
                $this->outputSections[$section]->writeln($message);
            } else {
                if (!array_key_exists($section, $this->outputSections)) {
                    $this->outputSections[$section] = [];
                }

                $this->outputSections[$section][] = [
                    'title' => $title,
                    'message' => $this->getHumanString($message),
                ];
            }
        }
    }

    public function clear(int $lines = 1, string $section = 'default')
    {
        if ($this->cli) {
            $this->outputSections[$section]?->clear($lines);
        }
    }

    public function expects($test, string $section = 'default', bool $force = false)
    {
        $baseLine = str_repeat('  ', 3);

        if (!empty($test['expect'])) {
            foreach ($test['expect'] as $state => $expectMessages) {
                foreach ($expectMessages as $expectMessage) {
                    if (is_string($expectMessage) && !str_contains($expectMessage, '[Debug] This page has moved')) {
                        match ($state) {
                            self::PASS => $this->outputText(baseLine: $baseLine, state: self::PASS, title: self::makeIcon(self::PASS), message: $expectMessage, secondaryColor: 'gray', section: $section, force: $force),
                            self::FAIL => $this->outputText(baseLine: $baseLine, state: self::FAIL, title: self::makeIcon(self::FAIL), message: $expectMessage, secondaryColor: 'gray', section: $section, force: $force),
                            self::SKIP => $this->outputText(baseLine: $baseLine, state: self::SKIP, title: self::makeIcon(self::SKIP), message: $expectMessage, secondaryColor: 'gray', section: $section, force: $force),
                            self::SKIPPED => $this->outputText(baseLine: $baseLine, state: self::SKIPPED, title: self::makeIcon(self::SKIPPED), message: $expectMessage, secondaryColor: 'gray', section: $section, force: $force),
                            self::TODO => $this->outputText(baseLine: $baseLine, state: self::TODO, title: self::makeIcon(self::TODO), message: $expectMessage, secondaryColor: 'gray', section: $section, force: $force),
                            default => $this->outputText(baseLine: $baseLine, state: self::TODO, title: self::makeIcon(self::TODO), message: $expectMessage, secondaryColor: 'gray', section: $section, force: $force)
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
            self::SKIP => 'yellow',
            self::SKIPPED => 'yellow',
            self::TODO => 'blue',
            default => 'gray',
        };
    }

    public function outputNewLine(string $section = 'default')
    {
        if ($this->outputMode !== self::OUTPUT_JSON) {
            $this->outputSections[$section]->writeln('');
        }
    }

    public function outputText(string $baseLine = '', string $state = 'default', string $title = '', string $message = '', string $secondaryColor = 'white', string $section = 'default', bool $force = false)
    {
        if (in_array($this->getOutputMode(), [self::OUTPUT_FULL, self::OUTPUT_JSON]) || $force) {
            $this->cli(
                bold: false,
                title: $title,
                titleColor: $this->getColor($state),
                secondaryColor: $secondaryColor,
                message: $this->getHumanString($message),
                baseLine: $baseLine,
                newLine: false,
                section: $section
            );
        } else if (self::OUTPUT_COMPACT === $this->getOutputMode()) {
            $this->cli(
                bold: false,
                title: $title,
                titleColor: $this->getColor($state),
                baseLine: $baseLine,
                section: $section
            );
        }
    }

    protected function debug(string|array $message, string $baseLine = '  ', bool $newLine = false, string $section = 'default')
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT);
        }

        $this->cli(
            bold: false,
            title: 'Debug:',
            titleColor: 'gray',
            secondaryColor: 'white',
            message: $this->getHumanString($message),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );
    }

    protected function info(string|array $message, string $baseLine = '  ', bool $newLine = false, string $section = 'default')
    {
        $this->cli(
            bold: true,
            title: 'INFO',
            titleColor: 'blue',
            secondaryColor: 'white',
            message: $this->getHumanString($message),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );
    }

    public function pass($test, string $baseLine = '    ', bool $newLine = false, string $section = 'default')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->cli(
            bold: true,
            title: 'PASS',
            titleColor: 'green',
            secondaryColor: 'white',
            message: $this->getHumanString($title),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );

        if ($this->isVerboseMode() && self::OUTPUT_COMPACT !== $this->getOutputMode()) {
            $this->expects($test, section: $section);
        }
    }

    public function warning($test, string $baseLine = '  ', bool $newLine = false, string $section = 'default')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->cli(
            bold: true,
            title: 'WARNING',
            titleColor: 'yellow',
            secondaryColor: 'white',
            message: $this->getHumanString($title),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );

        $this->expects($test, section: $section);
    }

    public function fail($test, string $baseLine = '    ', bool $newLine = false, string $section = 'default')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->cli(
            bold: true,
            title: 'FAIL',
            titleColor: 'red',
            secondaryColor: 'white',
            message: $this->getHumanString($title),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );

        $this->expects($test, section: $section, force: true);
    }

    public function skipped($test, string $baseLine = '    ', bool $newLine = false, string $section = 'default')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->cli(
            bold: true,
            title: 'SKIP',
            titleColor: 'yellow',
            secondaryColor: 'white',
            message: $this->getHumanString($title),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );

        if ($this->isVerboseMode() && self::OUTPUT_COMPACT !== $this->getOutputMode()) {
            $this->expects($test, section: $section);
        }
    }

    public function skippedCauseItsFail($test, string $baseLine = '    ', bool $newLine = false, string $section = 'default')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->cli(
            bold: true,
            title: 'SKIPPED',
            titleColor: 'gray',
            secondaryColor: 'white',
            message: $this->getHumanString($title),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );

        if ($this->isVerboseMode() && self::OUTPUT_COMPACT !== $this->getOutputMode()) {
            $this->expects($test, section: $section);
        }
    }

    public function toBeDone($test, string $baseLine = '    ', bool $newLine = false, string $section = 'default')
    {
        $title = $test;
        if (is_array($test)) {
            $title = $test['title'];
        }

        $this->cli(
            bold: true,
            title: 'TODO',
            titleColor: 'blue',
            secondaryColor: 'white',
            message: $this->getHumanString($title),
            baseLine: $baseLine,
            newLine: $newLine,
            section: $section
        );

        if ($this->isVerboseMode() && self::OUTPUT_COMPACT !== $this->getOutputMode()) {
            $this->expects($test, section: $section);
        }
    }

    protected function success(string $message, bool $newLine = false, string $section = 'default', bool $force = false)
    {
        $this->cli(
            bold: true,
            title: 'SUCCESS',
            titleColor: 'green',
            secondaryColor: 'white',
            message: $this->getHumanString($message),
            baseLine: '',
            newLine: $newLine,
            section: $section,
            force: $force
        );
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

    protected function formatSeconds($time)
    {
        return number_format($time / 1000, 2, '.', '');
    }
}
