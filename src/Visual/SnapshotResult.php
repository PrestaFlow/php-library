<?php

namespace PrestaFlow\Library\Visual;

/** Capture pleine page (JPEG) + carte des éléments visibles (sélecteur visuel). */
final class SnapshotResult
{
    /**
     * @param string $image    capture pleine page (binaire, JPEG qualité 80)
     * @param string $mime     type MIME de $image ('image/jpeg')
     * @param int    $width    largeur capturée, en CSS px
     * @param int    $height   hauteur capturée, en CSS px (plafonnée)
     * @param array<int, array{i:int,p:int,tag:string,id:string,classes:string[],box:array{0:float,1:float,2:float,3:float},selector:string,matches:int}> $elements
     * @param bool   $stable   false si la page n'était pas stable à l'échéance
     * @param int    $status   statut HTTP du document principal (0 si inconnu, ex. file://)
     */
    public function __construct(
        public readonly string $image,
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
        public readonly array $elements,
        public readonly bool $stable,
        public readonly int $status = 0,
    ) {
    }
}
