<?php

namespace PrestaFlow\Library\Reports;

use DOMDocument;
use DOMElement;
use PrestaFlow\Library\Utils\Screenshots;

final class JUnitReport
{
    /** @var array<int, array> */
    private array $suites = [];

    public function addSuite(array $results): void
    {
        $this->suites[] = $results;
    }

    public function render(): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('testsuites');
        $doc->appendChild($root);

        foreach ($this->suites as $results) {
            $root->appendChild($this->buildSuite($doc, $results));
        }

        return $doc->saveXML();
    }

    private function buildSuite(DOMDocument $doc, array $results): DOMElement
    {
        $stats = $results['stats'] ?? [];
        $tests = $results['tests'] ?? [];
        $classname = (string) ($results['suite'] ?? '');

        $suiteEl = $doc->createElement('testsuite');
        $suiteEl->setAttribute('name', (string) ($results['title'] ?: $classname));
        $suiteEl->setAttribute('tests', (string) count($tests));
        $suiteEl->setAttribute('failures', (string) ((int) ($stats['failures'] ?? 0)));
        $suiteEl->setAttribute('errors', '0');

        $skipped = (int) ($stats['skips'] ?? 0)
            + (int) ($stats['skippeds'] ?? 0)
            + (int) ($stats['todos'] ?? 0);
        $suiteEl->setAttribute('skipped', (string) $skipped);
        $suiteEl->setAttribute('time', $this->seconds($stats['time'] ?? 0));

        foreach ($tests as $test) {
            $suiteEl->appendChild($this->buildCase($doc, $test, $classname));
        }

        return $suiteEl;
    }

    private function buildCase(DOMDocument $doc, array $test, string $classname): DOMElement
    {
        $caseEl = $doc->createElement('testcase');
        $caseEl->setAttribute('name', (string) ($test['title'] ?? ''));
        $caseEl->setAttribute('classname', $classname);
        $caseEl->setAttribute('time', $this->seconds($test['time'] ?? 0));

        $state = $test['state'] ?? '';

        if ($state === 'fail') {
            $messages = $test['expect']['fail'] ?? [];
            $message = is_array($messages) ? implode("\n", $messages) : (string) $messages;

            $screen = $test['screen'] ?? null;
            $relative = (is_string($screen) && $screen !== '')
                ? Screenshots::relativeErrorPath($screen)
                : null;

            if ($relative !== null) {
                $message .= "\nScreenshot: " . $relative;
            }

            $failureEl = $doc->createElement('failure');
            $failureEl->setAttribute('message', $message);
            $caseEl->appendChild($failureEl);

            if ($relative !== null) {
                $sysOut = $doc->createElement('system-out');
                $sysOut->appendChild($doc->createTextNode('[[ATTACHMENT|' . $relative . ']]'));
                $caseEl->appendChild($sysOut);
            }

            $attachments = $test['attachments'] ?? [];
            if (is_array($attachments) && $attachments !== []) {
                $sysOut = $doc->createElement('system-out');
                $lines = array_map(static fn ($p) => '[[ATTACHMENT|' . $p . ']]', $attachments);
                $sysOut->appendChild($doc->createTextNode(implode("\n", $lines)));
                $caseEl->appendChild($sysOut);
            }
        } elseif (in_array($state, ['skip', 'skipped', 'todo'], true)) {
            $caseEl->appendChild($doc->createElement('skipped'));
        }

        return $caseEl;
    }

    private function seconds($milliseconds): string
    {
        return number_format(((float) $milliseconds) / 1000, 3, '.', '');
    }
}
