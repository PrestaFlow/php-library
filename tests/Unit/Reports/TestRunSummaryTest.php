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
