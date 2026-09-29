<?php

namespace PrestaFlow\Tests\Unit\Visual\Fixtures;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/** Suite nommée (pas anonyme) : le scope par défaut dérive du nom court de la classe. */
final class ProdVisual2 extends VisualTestsSuite
{
    protected array $checkpoints = [['name' => 'header', 'path' => '']];

    protected function importVisualPage(): void {}
}
