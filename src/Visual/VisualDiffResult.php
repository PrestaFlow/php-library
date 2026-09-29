<?php

namespace PrestaFlow\Library\Visual;

/** Résultat d'une comparaison pixel à pixel (VisualComparator::compareAndDiff). */
final class VisualDiffResult
{
    public function __construct(
        /** 1 - changedPixels / totalPixels (1 = identiques). */
        public readonly float $score,
        /** Pixels dont l'écart RGB cumulé dépasse la tolérance (+ zone hors recouvrement si tailles différentes). */
        public readonly int $changedPixels,
        /** Pixels comparés (union des deux tailles). */
        public readonly int $totalPixels,
    ) {
    }
}
