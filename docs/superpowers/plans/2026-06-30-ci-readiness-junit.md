# CI Readiness (exit code + JUnit) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `bin/prestaflow run` CI-usable — exit non-zero on test failure and emit a JUnit XML report via a separate `--junit` flag.

**Architecture:** Two new pure, browser-free classes under `src/Reports/` — `TestRunSummary` (decides the process exit code by accumulating per-suite failure counts) and `JUnitReport` (serialises suite results to JUnit XML with `DOMDocument`). `ExecuteSuite` wires them in: feeds each executed suite's `getStats()`/`results(false)` into them, writes the XML file when `--junit` is set, and returns `Command::FAILURE` when any test failed. The ~28 existing pages and suites are untouched.

**Tech Stack:** PHP 8.1+, Symfony Console 6, `DOMDocument` (ext-dom), PHPUnit 10 (new dev dependency).

Spec: `docs/superpowers/specs/2026-06-30-ci-readiness-junit-design.md`

---

### Task 0: PHPUnit dev harness

**Goal:** Add a browser-free PHPUnit setup so the new pure classes can be unit-tested.

**Files:**
- Modify: `composer.json` (add `require-dev`, `autoload-dev`, `test-unit` script)
- Create: `phpunit.xml.dist`
- Create: `tests/Unit/.gitkeep` (placeholder so the dir exists before the first test)

**Acceptance Criteria:**
- [ ] `composer install` resolves PHPUnit ^10 as a dev dependency.
- [ ] `composer test-unit` runs PHPUnit and reports "No tests executed" (no tests yet) without error.
- [ ] The existing `tests` composer script (PrestaFlow suite runner) is unchanged.

**Verify:** `composer test-unit` → PHPUnit banner, exits 0 with "No tests executed!"

**Steps:**

- [ ] **Step 1: Edit `composer.json`**

Add these top-level keys (keep the existing `require`, `autoload`, `authors`, and the existing `scripts.tests` entry intact):

```json
    "autoload-dev": {
        "psr-4": {
            "PrestaFlow\\Tests\\": "tests/"
        }
    },
    "require-dev": {
        "phpunit/phpunit": "^10"
    }
```

And extend `scripts` so it reads:

```json
    "scripts": {
        "tests": "./bin/prestaflow run src/Tests",
        "test-unit": "phpunit"
    }
```

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create the test directory placeholder**

Create an empty file `tests/Unit/.gitkeep`.

- [ ] **Step 4: Install and regenerate autoload**

Run: `composer update phpunit/phpunit && composer dump-autoload`
Expected: PHPUnit ^10 installed, no errors.

- [ ] **Step 5: Verify the harness runs**

Run: `composer test-unit`
Expected: PHPUnit banner, "No tests executed!", exit 0.

- [ ] **Step 6: Add `.phpunit.cache` to `.gitignore`**

Append `.phpunit.cache/` to `.gitignore` (create the file if absent).

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/Unit/.gitkeep .gitignore
git commit -m "build: add PHPUnit dev harness for unit tests"
```

---

### Task 1: `TestRunSummary` (exit-code decision)

**Goal:** A pure accumulator that answers "did any test fail across all suites?".

**Files:**
- Create: `src/Reports/TestRunSummary.php`
- Test: `tests/Unit/Reports/TestRunSummaryTest.php`

**Acceptance Criteria:**
- [ ] `add()` accumulates `stats['failures']` across calls.
- [ ] `hasFailures()` is `true` iff total failures > 0.
- [ ] A `stats` array missing the `failures` key is treated as 0 (no crash).

**Verify:** `vendor/bin/phpunit tests/Unit/Reports/TestRunSummaryTest.php` → all green

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Reports/TestRunSummaryTest.php`:

```php
<?php

namespace PrestaFlow\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Reports\TestRunSummary;

final class TestRunSummaryTest extends TestCase
{
    public function testNoFailuresByDefault(): void
    {
        $summary = new TestRunSummary();
        $this->assertFalse($summary->hasFailures());
        $this->assertSame(0, $summary->totalFailures());
    }

    public function testAccumulatesFailuresAcrossSuites(): void
    {
        $summary = new TestRunSummary();
        $summary->add(['failures' => 2]);
        $summary->add(['failures' => 1]);
        $this->assertSame(3, $summary->totalFailures());
        $this->assertTrue($summary->hasFailures());
    }

    public function testPassingSuitesDoNotFail(): void
    {
        $summary = new TestRunSummary();
        $summary->add(['failures' => 0, 'passes' => 5]);
        $this->assertFalse($summary->hasFailures());
    }

    public function testMissingFailuresKeyIsTreatedAsZero(): void
    {
        $summary = new TestRunSummary();
        $summary->add(['passes' => 3]);
        $this->assertSame(0, $summary->totalFailures());
        $this->assertFalse($summary->hasFailures());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Reports/TestRunSummaryTest.php`
Expected: FAIL — `Class "PrestaFlow\Library\Reports\TestRunSummary" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `src/Reports/TestRunSummary.php`:

```php
<?php

namespace PrestaFlow\Library\Reports;

final class TestRunSummary
{
    private int $totalFailures = 0;

    public function add(array $stats): void
    {
        $this->totalFailures += (int) ($stats['failures'] ?? 0);
    }

    public function totalFailures(): int
    {
        return $this->totalFailures;
    }

    public function hasFailures(): bool
    {
        return $this->totalFailures > 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Reports/TestRunSummaryTest.php`
Expected: PASS — 4 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add src/Reports/TestRunSummary.php tests/Unit/Reports/TestRunSummaryTest.php
git commit -m "feat: add TestRunSummary for CI exit-code decision"
```

---

### Task 2: `JUnitReport` (XML serialiser)

**Goal:** Convert accumulated suite results into a well-formed JUnit XML string.

**Files:**
- Create: `src/Reports/JUnitReport.php`
- Test: `tests/Unit/Reports/JUnitReportTest.php`

**Acceptance Criteria:**
- [ ] Output is well-formed XML: `<testsuites>` > `<testsuite>` > `<testcase>`.
- [ ] `<testsuite>` carries `name`, `tests`, `failures`, `errors`, `skipped`, `time` (seconds, 3 decimals).
- [ ] `fail` test → `<failure message="…">`; `skip`/`skipped`/`todo` → `<skipped/>`; `pass` → no child.
- [ ] Special characters in titles/messages are escaped (valid XML preserved).

**Verify:** `vendor/bin/phpunit tests/Unit/Reports/JUnitReportTest.php` → all green

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Reports/JUnitReportTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Reports/JUnitReportTest.php`
Expected: FAIL — `Class "PrestaFlow\Library\Reports\JUnitReport" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `src/Reports/JUnitReport.php`:

```php
<?php

namespace PrestaFlow\Library\Reports;

use DOMDocument;
use DOMElement;

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
            $failureEl = $doc->createElement('failure');
            $failureEl->setAttribute('message', $message);
            $caseEl->appendChild($failureEl);
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Reports/JUnitReportTest.php`
Expected: PASS — 5 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add src/Reports/JUnitReport.php tests/Unit/Reports/JUnitReportTest.php
git commit -m "feat: add JUnitReport XML serialiser"
```

---

### Task 3: Wire `--junit` and exit code into `ExecuteSuite`

**Goal:** Feed each executed suite into both new classes, write the JUnit file when requested, and return a failing exit code when any test failed.

**Files:**
- Modify: `src/Command/ExecuteSuite.php`

**Acceptance Criteria:**
- [ ] `run` returns `1` when ≥1 test fails, `0` otherwise (empty folder → `0`).
- [ ] `--junit` (no value) writes `prestaflow/junit.xml`; `--junit=path` writes to `path`; option absent writes nothing.
- [ ] `--junit` works alongside any `--output` value (console rendering unaffected).

**Verify (integration, needs a reachable PrestaShop):**
`bin/prestaflow run --junit=/tmp/junit.xml src/Tests/Suites/Fails ; echo "exit=$?"`
→ `exit=1` and `/tmp/junit.xml` contains `<failure`. On an all-green folder → `exit=0`, no `<failure>`. Validate XML: `xmllint --noout /tmp/junit.xml`.

**Steps:**

- [ ] **Step 1: Add the imports**

In `src/Command/ExecuteSuite.php`, after the existing `use PrestaFlow\Library\Utils\Output;` line, add:

```php
use PrestaFlow\Library\Reports\JUnitReport;
use PrestaFlow\Library\Reports\TestRunSummary;
```

- [ ] **Step 2: Register the `--junit` option**

In `configure()`, inside the `$this` option chain (e.g. directly after the `->addOption('file', ...)` line), add:

```php
            ->addOption('junit', null, InputOption::VALUE_OPTIONAL, 'Write a JUnit XML report (default path: prestaflow/junit.xml)', false)
```

- [ ] **Step 3: Instantiate the collectors and resolve the path**

In `execute()`, immediately after `$this->groups = $input->getOption('group') ?? ['all'];`, add:

```php
        $summary = new TestRunSummary();
        $report = new JUnitReport();

        $junitOption = $input->getOption('junit');
        $junitPath = ($junitOption === false) ? null : ($junitOption ?: 'prestaflow/junit.xml');
```

- [ ] **Step 4: Feed each executed suite into the collectors**

Inside the `foreach ($testSuites as $suitePath)` loop, within the `if ($this->isExecutable($suite))` block, directly after the `foreach ($suite->warnings as $warning) { ... }` loop and before the `if (self::OUTPUT_JSON === $this->getOutputMode())` block, add:

```php
                    $summary->add($suite->getStats());
                    $report->addSuite($suite->results(false));
```

- [ ] **Step 5: Write the JUnit file and set the exit code**

At the end of `execute()`, the method currently ends with:

```php
        $message = sprintf('%ss', $this->formatSeconds($time));
        $this->cli(baseLine: '', bold: false, titleColor: 'gray', title: 'Duration:', secondaryColor: 'white', message: $message, newLine: true, section: 'duration');

        return Command::SUCCESS;
    }
```

Replace those final lines with:

```php
        $message = sprintf('%ss', $this->formatSeconds($time));
        $this->cli(baseLine: '', bold: false, titleColor: 'gray', title: 'Duration:', secondaryColor: 'white', message: $message, newLine: true, section: 'duration');

        if ($junitPath !== null) {
            $this->filePutContents($junitPath, $report->render());
            $this->success('JUnit report saved to ' . $junitPath, newLine: true, force: true);
        }

        return $summary->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }
```

Leave the two earlier `return Command::SUCCESS;` early-returns (empty folder / no executable suites) as-is — no failures occurred there, so `0` is correct.

- [ ] **Step 6: Confirm no unit regressions**

Run: `composer test-unit`
Expected: PASS — Task 1 + Task 2 suites green (9 tests).

- [ ] **Step 7: Integration check (if a PrestaShop instance is reachable)**

Run: `bin/prestaflow run --junit=/tmp/junit.xml src/Tests/Suites/Fails ; echo "exit=$?"`
Expected: `exit=1`; `xmllint --noout /tmp/junit.xml` succeeds; the file contains `<failure`.

If no instance is reachable, note that this step is deferred to a manual run and rely on the unit-tested `JUnitReport`/`TestRunSummary` plus code review of the wiring.

- [ ] **Step 8: Commit**

```bash
git add src/Command/ExecuteSuite.php
git commit -m "feat: emit JUnit report and CI exit code from run command"
```

---

## Notes for the implementer

- PrestaShop "1.7" maps to the `src/Pages/v7` tree; this plan does not touch it (it's spec 1B). 1A is purely the runner/reporting layer.
- `TestsSuite::results(false)` already returns `['suite', 'title', 'stats', 'tests', ...]`; each test entry carries `title`, `state`, `time` (ms), `expect`. `JUnitReport` consumes exactly that shape — do not change `results()`.
- `--junit`'s three-state behaviour relies on the `false` default (absent) vs `null` (present, no value) distinction, the same trick the existing `--draft` option uses.
- Keep `Command::FAILURE`/`Command::SUCCESS` — they are Symfony Console's `1`/`0`.
