<?php

namespace PrestaFlow\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Reports\JUnitReport;

final class JUnitReportTest extends TestCase
{
    private function sampleSuite(array $overrides = []): array
    {
        return array_merge([
            'suite' => 'PrestaFlow\\Library\\Tests\\Suites\\Demo',
            'title' => 'Demo suite',
            'stats' => [
                'passes' => 1, 'failures' => 1, 'skips' => 1,
                'skippeds' => 0, 'todos' => 0, 'assertions' => 2, 'time' => 1500,
            ],
            'tests' => [
                ['title' => 'passes', 'state' => 'pass', 'time' => 500, 'expect' => []],
                ['title' => 'fails', 'state' => 'fail', 'time' => 700, 'expect' => ['fail' => ['expected true']]],
                ['title' => 'skipme', 'state' => 'skip', 'time' => 0, 'expect' => []],
            ],
        ], $overrides);
    }

    public function testProducesWellFormedXml(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite());
        $this->assertNotFalse(simplexml_load_string($report->render()));
    }

    public function testSuiteAttributes(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite());
        $suite = simplexml_load_string($report->render())->testsuite[0];

        $this->assertSame('Demo suite', (string) $suite['name']);
        $this->assertSame('3', (string) $suite['tests']);
        $this->assertSame('1', (string) $suite['failures']);
        $this->assertSame('0', (string) $suite['errors']);
        $this->assertSame('1', (string) $suite['skipped']);
        $this->assertSame('1.500', (string) $suite['time']);
    }

    public function testCaseChildrenByState(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite());
        $cases = simplexml_load_string($report->render())->testsuite[0]->testcase;

        $this->assertCount(0, $cases[0]->children());           // pass: no child
        $this->assertSame('expected true', (string) $cases[1]->failure['message']);
        $this->assertTrue(isset($cases[2]->skipped));
    }

    public function testTodoMapsToSkipped(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite([
            'tests' => [['title' => 't', 'state' => 'todo', 'time' => 0, 'expect' => []]],
        ]));
        $xml = simplexml_load_string($report->render());
        $this->assertTrue(isset($xml->testsuite[0]->testcase[0]->skipped));
    }

    public function testEscapesSpecialCharacters(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite([
            'tests' => [['title' => 'a < b & "c"', 'state' => 'pass', 'time' => 0, 'expect' => []]],
        ]));
        $xml = simplexml_load_string($report->render());
        $this->assertNotFalse($xml);
        $this->assertSame('a < b & "c"', (string) $xml->testsuite[0]->testcase[0]['name']);
    }
}
