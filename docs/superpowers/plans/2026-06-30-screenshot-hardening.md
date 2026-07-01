# Screenshot Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing screenshot-on-failure reliable (it is silently lost in standalone mode) and CI-consumable, by centralising path/dir logic in one helper and wiring it into capture, the terminal link, and the JUnit report.

**Architecture:** A new pure `Screenshots` util owns the errors-dir path, directory creation, the portable relative path, and the capture delay. `Expect` (capture), `Output` (terminal link) and `JUnitReport` (CI artifact reference) all consume it instead of duplicating path logic. The failed-capture reason is surfaced as a debug line instead of being swallowed.

**Tech Stack:** PHP 8.1+, PHPUnit 10, `DOMDocument`. No browser needed to test the new code.

Spec: `docs/superpowers/specs/2026-06-30-screenshot-hardening-design.md`

---

### Task 0: `Screenshots` helper (path + dir + delay)

**Goal:** A pure, browser-free util that resolves the errors dir (creating it on demand), the screenshot path, the portable relative path, and the capture delay — the single source of truth all consumers use.

**Files:**
- Create: `src/Utils/Screenshots.php`
- Test: `tests/Unit/Utils/ScreenshotsTest.php`

**Acceptance Criteria:**
- [ ] `errorsDir(create: true)` creates the dir; `errorsDir()` does not.
- [ ] `relativeErrorPath('x.png')` === `'prestaflow/screens/errors/x.png'`.
- [ ] `captureDelay()` reads `PRESTAFLOW_SCREENSHOT_DELAY`, defaults to 3, never negative.

**Verify:** `vendor/bin/phpunit tests/Unit/Utils/ScreenshotsTest.php` → green

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Utils/ScreenshotsTest.php`:
```php
<?php

namespace PrestaFlow\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Utils\Screenshots;

final class ScreenshotsTest extends TestCase
{
    private string $cwd;
    private string $tmp;

    protected function setUp(): void
    {
        $this->cwd = getcwd();
        $this->tmp = sys_get_temp_dir() . '/pf_shots_' . uniqid();
        mkdir($this->tmp, 0777, true);
        chdir($this->tmp);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        @unlink($this->tmp . '/prestaflow/screens/errors');
        // best-effort recursive cleanup
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
        unset($_ENV['PRESTAFLOW_SCREENSHOT_DELAY']);
    }

    public function testErrorsDirDoesNotCreateByDefault(): void
    {
        $dir = Screenshots::errorsDir();
        $this->assertStringEndsWith('prestaflow/screens/errors', $dir);
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testErrorsDirCreatesWhenRequested(): void
    {
        $dir = Screenshots::errorsDir(create: true);
        $this->assertDirectoryExists($dir);
        $this->assertStringEndsWith('prestaflow/screens/errors', $dir);
    }

    public function testErrorPathConcatenates(): void
    {
        $this->assertSame(Screenshots::errorsDir() . '/x.png', Screenshots::errorPath('x.png'));
    }

    public function testRelativeErrorPathIsStable(): void
    {
        $this->assertSame('prestaflow/screens/errors/x.png', Screenshots::relativeErrorPath('x.png'));
    }

    public function testCaptureDelayDefaultsToThree(): void
    {
        unset($_ENV['PRESTAFLOW_SCREENSHOT_DELAY']);
        $this->assertSame(3, Screenshots::captureDelay());
    }

    public function testCaptureDelayReadsEnv(): void
    {
        $_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] = '0';
        $this->assertSame(0, Screenshots::captureDelay());
        $_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] = '5';
        $this->assertSame(5, Screenshots::captureDelay());
    }

    public function testCaptureDelayNeverNegative(): void
    {
        $_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] = '-2';
        $this->assertSame(0, Screenshots::captureDelay());
    }
}
```

- [ ] **Step 2: Run it, confirm it FAILS**

Run: `vendor/bin/phpunit tests/Unit/Utils/ScreenshotsTest.php`
Expected: FAIL — `Class "PrestaFlow\Library\Utils\Screenshots" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Utils/Screenshots.php`:
```php
<?php

namespace PrestaFlow\Library\Utils;

final class Screenshots
{
    public const ERRORS_SUBPATH = 'screens/errors';
    public const RELATIVE_DIR = 'prestaflow/screens/errors';

    public static function errorsDir(bool $create = false): string
    {
        if (function_exists('storage_path')) {
            $dir = rtrim(storage_path(), '/') . '/' . self::ERRORS_SUBPATH;
        } else {
            $dir = getcwd() . '/' . self::RELATIVE_DIR;
        }

        if ($create && !is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public static function errorPath(string $filename, bool $create = false): string
    {
        return self::errorsDir($create) . '/' . $filename;
    }

    public static function relativeErrorPath(string $filename): string
    {
        return self::RELATIVE_DIR . '/' . $filename;
    }

    public static function captureDelay(): int
    {
        $delay = (int) ($_ENV['PRESTAFLOW_SCREENSHOT_DELAY'] ?? 3);

        return $delay < 0 ? 0 : $delay;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Utils/ScreenshotsTest.php`
Expected: PASS — 7 tests, OK.

- [ ] **Step 5: Commit** — see "Commits" note (await user go-ahead; do NOT `git commit`).

---

### Task 1: Wire the helper into capture, link, and diagnostics

**Goal:** Make `Expect` capture reliable (dir created, delay configurable, failures recorded), fix the `Output` link, and surface capture failures in `TestsSuite`.

**Files:**
- Modify: `src/Expects/Expect.php`
- Modify: `src/Utils/Output.php`
- Modify: `src/Tests/TestsSuite.php`

**Acceptance Criteria:**
- [ ] Capture saves via `Screenshots::errorPath($fileName, create: true)` (dir created first).
- [ ] `sleep` uses `Screenshots::captureDelay()`.
- [ ] On capture failure, `Expect::$latestScreenshotError` holds the reason (not silently null).
- [ ] `Output` computes the link path via `Screenshots::errorPath($test['screen'])` (no `realpath`).
- [ ] `TestsSuite::attachScreen` adds a `debug` line when capture failed.
- [ ] `php -l` clean on the three files; `composer test-unit` still green.

**Verify:** `php -l src/Expects/Expect.php && php -l src/Utils/Output.php && php -l src/Tests/TestsSuite.php && composer test-unit`

**Steps:**

- [ ] **Step 1: `Expect.php` — import + new static prop**

Add the import after the existing `use` block (near the top of the file, alongside other `use` statements):
```php
use PrestaFlow\Library\Utils\Screenshots;
```
Add a static property next to the existing `public static $latestError = '';` (around line 23):
```php
    public static $latestScreenshotError = null;
```

- [ ] **Step 2: `Expect.php` — harden `getExceptionConstructor`**

The method currently begins:
```php
    protected function getExceptionConstructor($explanation, $arguments = array())
    {
        try {
            $page = TestsSuite::getPage();
            if ($page instanceof HeadlessChromiumPage) {
                sleep(3);
                $fileName = 'error_' . $page->getSession()->getTargetId() . '-' . time() . '.png';
                self::$latestError = $fileName;
                $screenshot = $page->screenshot([
                    'captureBeyondViewport' => true,
                    'clip' => $page->getFullPageClip(),
                    'format' => 'png',
                ]);
                if (function_exists('storage_path')) {
                    $screenshot->saveToFile(storage_path() . '/screens/errors/' . $fileName);
                } else {
                    $screenshot->saveToFile('./prestaflow/screens/errors/' . $fileName);
                }
            }
        } catch (OperationTimedOut $e) {
            self::$latestError = null;
        } catch (FilesystemException $e) {
            self::$latestError = null;
        } catch (Exception $e) {
            self::$latestError = null;
        }
```
Replace that span with:
```php
    protected function getExceptionConstructor($explanation, $arguments = array())
    {
        self::$latestScreenshotError = null;
        try {
            $page = TestsSuite::getPage();
            if ($page instanceof HeadlessChromiumPage) {
                sleep(Screenshots::captureDelay());
                $fileName = 'error_' . $page->getSession()->getTargetId() . '-' . time() . '.png';
                self::$latestError = $fileName;
                $screenshot = $page->screenshot([
                    'captureBeyondViewport' => true,
                    'clip' => $page->getFullPageClip(),
                    'format' => 'png',
                ]);
                $screenshot->saveToFile(Screenshots::errorPath($fileName, create: true));
            }
        } catch (OperationTimedOut $e) {
            self::$latestError = null;
            self::$latestScreenshotError = $e->getMessage();
        } catch (FilesystemException $e) {
            self::$latestError = null;
            self::$latestScreenshotError = $e->getMessage();
        } catch (Exception $e) {
            self::$latestError = null;
            self::$latestScreenshotError = $e->getMessage();
        }
```
(Leave everything after the catch blocks unchanged.)

- [ ] **Step 3: `Output.php` — fix the link path**

`Output.php` is a trait in namespace `PrestaFlow\Library\Utils` (same namespace as `Screenshots`). In `expects()`, the current block:
```php
                $screenPath = function_exists('storage_path')
                    ? storage_path() . '/screens/errors/' . $test['screen']
                    : realpath('./prestaflow/screens/errors') . '/' . $test['screen'];
```
Replace with:
```php
                $screenPath = Screenshots::errorPath($test['screen']);
```
(The surrounding debug output and the `<href=file://…>` link line stay unchanged.)

- [ ] **Step 4: `TestsSuite.php` — surface capture failures**

The current method:
```php
    public function attachScreen(&$test)
    {
        $test['screen'] = Expect::$latestError;
        $this->screens[] = $test['screen'];
    }
```
Replace with:
```php
    public function attachScreen(&$test)
    {
        $test['screen'] = Expect::$latestError;
        $this->screens[] = $test['screen'];

        if (!empty(Expect::$latestScreenshotError)) {
            $this->log('Screenshot capture failed: ' . Expect::$latestScreenshotError);
            Expect::$latestScreenshotError = null;
        }
    }
```
NOTE: route the message through `$this->log()` (which pushes to
`self::$pendingDebugMessages`), NOT directly into `$test['debug']`. In `run()`'s
`finally`, `attachScreen()` runs BEFORE `attachDebugMessages()`, and the latter
does `$test['debug'] = self::$pendingDebugMessages;` (an unconditional
assignment) — so anything written straight to `$test['debug']` here would be
overwritten. `log()` is the channel `attachDebugMessages` consumes, so the line
survives and is rendered.

- [ ] **Step 5: Verify**

Run: `php -l src/Expects/Expect.php && php -l src/Utils/Output.php && php -l src/Tests/TestsSuite.php`
Expected: "No syntax errors detected" for each.
Run: `composer test-unit`
Expected: green (Screenshots tests from Task 0 + all existing). This task changes browser-driven code paths that the unit suite does not exercise; correctness of the capture itself is confirmed by code review + the helper's unit tests + a manual browser run (see plan "Integration check").

- [ ] **Step 6: Commit** — see "Commits" note (await user go-ahead).

---

### Task 2: Reference the screenshot in the JUnit report

**Goal:** A failed `<testcase>` with a screenshot carries the reference in both the `<failure>` message (GitHub Actions reporters) and a `<system-out>` `[[ATTACHMENT|…]]` (GitLab/Jenkins).

**Files:**
- Modify: `src/Reports/JUnitReport.php`
- Test: `tests/Unit/Reports/JUnitReportTest.php`

**Acceptance Criteria:**
- [ ] A `fail` testcase with `screen` adds `\nScreenshot: prestaflow/screens/errors/<file>` to the failure message and a `<system-out>` `[[ATTACHMENT|prestaflow/screens/errors/<file>]]`.
- [ ] A `fail` testcase without `screen` adds neither.
- [ ] XML stays well-formed.

**Verify:** `vendor/bin/phpunit tests/Unit/Reports/JUnitReportTest.php` → green

**Steps:**

- [ ] **Step 1: Write the failing tests**

Append these two methods to `tests/Unit/Reports/JUnitReportTest.php` (inside the class). They reuse the existing `sampleSuite()` helper:
```php
    public function testFailureWithScreenshotEmitsAttachment(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite([
            'tests' => [['title' => 'boom', 'state' => 'fail', 'time' => 100, 'expect' => ['fail' => ['expected true']], 'screen' => 'error_x.png']],
        ]));
        $xml = simplexml_load_string($report->render());
        $this->assertNotFalse($xml);
        $case = $xml->testsuite[0]->testcase[0];
        $this->assertStringContainsString('Screenshot: prestaflow/screens/errors/error_x.png', (string) $case->failure['message']);
        $this->assertSame('[[ATTACHMENT|prestaflow/screens/errors/error_x.png]]', trim((string) $case->{'system-out'}));
    }

    public function testFailureWithoutScreenshotHasNoAttachment(): void
    {
        $report = new JUnitReport();
        $report->addSuite($this->sampleSuite([
            'tests' => [['title' => 'boom', 'state' => 'fail', 'time' => 100, 'expect' => ['fail' => ['expected true']]]],
        ]));
        $xml = simplexml_load_string($report->render());
        $case = $xml->testsuite[0]->testcase[0];
        $this->assertFalse(isset($case->{'system-out'}));
        $this->assertStringNotContainsString('Screenshot:', (string) $case->failure['message']);
    }
```

- [ ] **Step 2: Run to confirm FAIL**

Run: `vendor/bin/phpunit tests/Unit/Reports/JUnitReportTest.php`
Expected: FAIL — no `system-out`, message lacks the screenshot line.

- [ ] **Step 3: Implement in `JUnitReport.php`**

Add the import near the top (with the existing `use DOMDocument; use DOMElement;`):
```php
use PrestaFlow\Library\Utils\Screenshots;
```
Replace the `if ($state === 'fail') { … }` block in `buildCase()` (currently lines ~67-72) with:
```php
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
        } elseif (in_array($state, ['skip', 'skipped', 'todo'], true)) {
            $caseEl->appendChild($doc->createElement('skipped'));
        }
```

- [ ] **Step 4: Run to confirm PASS**

Run: `vendor/bin/phpunit tests/Unit/Reports/JUnitReportTest.php`
Expected: PASS (7 tests now — 5 existing + 2 new).
Run: `composer test-unit`
Expected: all green.

- [ ] **Step 5: Commit** — see "Commits" note (await user go-ahead).

---

## Integration check (manual, needs a reachable PrestaShop)

With a shop reachable and a deliberately failing test:
```bash
bin/prestaflow run --junit=/tmp/junit.xml src/Tests/Suites/Fails
```
Confirm: `prestaflow/screens/errors/` is created and contains the `.png`; the terminal "Open screenshot" link opens it; `/tmp/junit.xml` contains `<system-out>[[ATTACHMENT|prestaflow/screens/errors/…]]` and the `Screenshot:` line in `<failure>`. If no shop is reachable, rely on the unit-tested helper + JUnit changes and code review of the Expect/Output wiring.

## Commits

The repository owner requires explicit approval before any `git commit`. Implementer subagents must NOT run `git commit`/`git add`; leave changes in the working tree. The coordinator batches commits once the user approves.
