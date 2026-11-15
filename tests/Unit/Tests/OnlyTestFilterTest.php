<?php

namespace PrestaFlow\Tests\Unit\Tests;

use PHPUnit\Framework\TestCase;
use PrestaFlow\Library\Tests\TestsSuite;

/** PRESTAFLOW_ONLY_TEST : rejeu d'un seul test d'une suite. */
final class OnlyTestFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PRESTAFLOW_ONLY_TEST');
        unset($_ENV['PRESTAFLOW_ONLY_TEST']);
    }

    private function tests(): array
    {
        return [
            ['title' => 'ouvre la fiche', 'steps' => fn () => null],
            ['title' => 'ajoute au panier', 'steps' => fn () => null],
        ];
    }

    public function test_without_the_variable_every_test_runs(): void
    {
        $this->assertCount(2, TestsSuite::onlyTest($this->tests()));
    }

    public function test_only_the_named_test_is_kept(): void
    {
        $_ENV['PRESTAFLOW_ONLY_TEST'] = 'ajoute au panier';

        $kept = TestsSuite::onlyTest($this->tests());

        $this->assertSame(['ajoute au panier'], array_column($kept, 'title'));
    }

    public function test_an_unknown_title_gives_a_failing_test_not_an_empty_suite(): void
    {
        $_ENV['PRESTAFLOW_ONLY_TEST'] = 'disparu';

        $kept = TestsSuite::onlyTest($this->tests());

        $this->assertCount(1, $kept);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disparu');
        ($kept[0]['steps'])();
    }
}
