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
