<?php

/**
 * Reads the JUnit reports of the scenario suites run against a demo shop and
 * fails on anything but green or an EXPECTED skip.
 *
 * A skip is not a failure, but on PrestaShop's demo data most of them would
 * hide one: "no product can be added to the cart", "no offline payment
 * method" mean the discovery broke, not that the shop lacks something. Only
 * the skips listed for this cell in the expected-skips file pass.
 *
 * Usage: php check-scenario-results.php <expected-skips.json> <cell> <junit.xml>...
 *   <cell>  e.g. "1.7.8.11-classic", "9.2.0-hummingbird"
 *
 * expected-skips.json: {"<cell or *>": [{"suite": "...", "test": "...", "reason": "..."}]}
 * Each key of an entry is a substring to find (case-sensitive) in the suite
 * name, the test name and the skip reason; an absent key matches anything.
 */

[$script, $expectedFile, $cell] = array_pad(array_slice($argv, 0, 3), 3, null);
$reports = array_slice($argv, 3);

if ($expectedFile === null || $cell === null || $reports === []) {
    fwrite(STDERR, "Usage: php check-scenario-results.php <expected-skips.json> <cell> <junit.xml>...\n");
    exit(2);
}

$expected = json_decode((string) file_get_contents($expectedFile), true);
if (!is_array($expected)) {
    fwrite(STDERR, "Invalid expected skips file: $expectedFile\n");
    exit(2);
}
$allowed = [...($expected['*'] ?? []), ...($expected[$cell] ?? [])];

$matches = static function (array $rule, string $suite, string $test, string $reason): bool {
    foreach (['suite' => $suite, 'test' => $test, 'reason' => $reason] as $key => $value) {
        if (isset($rule[$key]) && !str_contains($value, (string) $rule[$key])) {
            return false;
        }
    }

    return true;
};

$problems = [];
$counts = ['pass' => 0, 'fail' => 0, 'skip' => 0];

foreach ($reports as $report) {
    $xml = @simplexml_load_file($report);
    if ($xml === false) {
        $problems[] = "$report: no JUnit report (the run did not finish)";
        continue;
    }

    foreach ($xml->testsuite as $suiteEl) {
        $suite = (string) $suiteEl['name'];
        foreach ($suiteEl->testcase as $case) {
            $test = (string) $case['name'];
            if (isset($case->failure)) {
                $counts['fail']++;
                $problems[] = "FAIL  $suite | $test | " . strtok((string) $case->failure['message'], "\n");
            } elseif (isset($case->skipped)) {
                $counts['skip']++;
                $reason = (string) $case->skipped['message'];
                $ok = false;
                foreach ($allowed as $rule) {
                    if (is_array($rule) && $matches($rule, $suite, $test, $reason)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    $problems[] = "SKIP  $suite | $test | " . ($reason !== '' ? $reason : '(no reason)') . "  <- not expected on $cell";
                }
            } else {
                $counts['pass']++;
            }
        }
    }
}

printf("Scenarios on %s: %d passed, %d failed, %d skipped\n", $cell, $counts['pass'], $counts['fail'], $counts['skip']);
foreach ($problems as $problem) {
    echo $problem, "\n";
}

exit($problems === [] ? 0 : 1);
