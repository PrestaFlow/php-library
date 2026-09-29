<?php

namespace PrestaFlow\Library\Visual;

/**
 * Comparaison pixel à pixel de deux captures PNG.
 *
 * Score = 1 - (pixels changés / pixels totaux). Un pixel est « changé » quand
 * la somme des écarts absolus de ses canaux R, G, B dépasse $tolerance (marge
 * d'anticrénelage). Le score est donc directement lisible : 0.999 = 0,1 % de
 * pixels changés.
 *
 * Auparavant le score venait d'un hash perceptuel (~8×8) aveugle aux petits
 * changements (une ligne de texte modifiée donnait 1.0) alors que le diff,
 * lui, était calculé pixel à pixel : les deux sont désormais issus du même
 * passage.
 */
class VisualComparator
{
    /** Hauteur des bandes comparées en bloc (octets bruts) avant le détail par pixel. */
    private const STRIP_HEIGHT = 32;

    /** Score de similarité 0–1 (1 = identiques), pixel à pixel. Accepte des chemins de fichiers. */
    public function compare(string $referencePath, string $actualPath, int $tolerance = 40): float
    {
        return $this->compareAndDiff($referencePath, $actualPath, null, $tolerance);
    }

    /** PNG de diff : actuelle grisée, pixels différant de la référence peints en rouge. BC : délègue à compareAndDiff(). */
    public function generateDiff(string $referencePath, string $actualPath, string $diffPath, int $tolerance = 40): void
    {
        $this->compareAndDiff($referencePath, $actualPath, $diffPath, $tolerance);
    }

    /**
     * Un seul passage : calcule le score et, si $diffPath est fourni, écrit le
     * PNG de diff (actuelle grisée/éclaircie, pixels changés en rouge).
     *
     * Tailles différentes : la comparaison porte sur l'union des deux tailles,
     * la zone hors recouvrement compte comme changée (pas de ré-échantillonnage,
     * qui masquerait les écarts) ; le diff a la taille de l'union.
     */
    public function compareAndDiff(string $referencePath, string $actualPath, ?string $diffPath, int $tolerance = 40): float
    {
        $ref = $this->load($referencePath);
        $act = $this->load($actualPath);

        $rw = imagesx($ref);
        $rh = imagesy($ref);
        $aw = imagesx($act);
        $ah = imagesy($act);
        $w = max($rw, $aw);
        $h = max($rh, $ah);
        $ow = min($rw, $aw);
        $oh = min($rh, $ah);

        $total = $w * $h;
        $changed = $total - $ow * $oh;

        $out = null;
        $red = 0;
        if ($diffPath !== null) {
            $out = $this->greyBackground($act, $w, $h);
            $red = imagecolorallocate($out, 220, 40, 40);
            if ($w > $ow) {
                imagefilledrectangle($out, $ow, 0, $w - 1, $h - 1, $red);
            }
            if ($h > $oh) {
                imagefilledrectangle($out, 0, $oh, $ow - 1, $h - 1, $red);
            }
        }

        for ($y0 = 0; $y0 < $oh; $y0 += self::STRIP_HEIGHT) {
            $sh = min(self::STRIP_HEIGHT, $oh - $y0);
            // Chemin rapide : bande identique à l'octet près => aucun pixel changé.
            if ($this->stripBytes($ref, $y0, $ow, $sh) === $this->stripBytes($act, $y0, $ow, $sh)) {
                continue;
            }
            $yEnd = $y0 + $sh;
            for ($y = $y0; $y < $yEnd; $y++) {
                for ($x = 0; $x < $ow; $x++) {
                    $ra = imagecolorat($ref, $x, $y);
                    $aa = imagecolorat($act, $x, $y);
                    if ($ra === $aa) {
                        continue;
                    }
                    $d = abs((($ra >> 16) & 0xFF) - (($aa >> 16) & 0xFF))
                        + abs((($ra >> 8) & 0xFF) - (($aa >> 8) & 0xFF))
                        + abs(($ra & 0xFF) - ($aa & 0xFF));
                    if ($d > $tolerance) {
                        $changed++;
                        if ($out !== null) {
                            imagesetpixel($out, $x, $y, $red);
                        }
                    }
                }
            }
        }

        imagedestroy($ref);
        imagedestroy($act);

        if ($out !== null) {
            if (!is_dir(dirname($diffPath))) {
                mkdir(dirname($diffPath), 0777, true);
            }
            imagepng($out, $diffPath);
            imagedestroy($out);
        }

        return $total > 0 ? 1.0 - $changed / $total : 1.0;
    }

    /**
     * Fond du diff : l'image actuelle en niveaux de gris « lavés »
     * (g * 0.35 + 165), calculé par GD en natif plutôt que pixel par pixel.
     */
    private function greyBackground($act, int $w, int $h)
    {
        $out = imagecreatetruecolor($w, $h);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $act, 0, 0, 0, 0, imagesx($act), imagesy($act));
        imagefilter($out, IMG_FILTER_GRAYSCALE);
        // out = out * 0.35 + 254 * 0.65 ≈ out * 0.35 + 165
        $wash = imagecreatetruecolor($w, $h);
        imagefill($wash, 0, 0, imagecolorallocate($wash, 254, 254, 254));
        imagecopymerge($out, $wash, 0, 0, 0, 0, $w, $h, 65);
        imagedestroy($wash);

        return $out;
    }

    /** Octets bruts (BMP non compressé) d'une bande horizontale, pour une comparaison en bloc. */
    private function stripBytes($img, int $y, int $w, int $h): string
    {
        $strip = imagecreatetruecolor($w, $h);
        imagealphablending($strip, false);
        imagecopy($strip, $img, 0, 0, 0, $y, $w, $h);
        // BMP non compressé = pixels RGB bruts (imagegd() peut être désactivé
        // selon la build de GD). Écrit dans un flux mémoire, pas sur stdout.
        $fp = fopen('php://memory', 'w+b');
        imagebmp($strip, $fp, false);
        rewind($fp);
        $bytes = (string) stream_get_contents($fp);
        fclose($fp);
        imagedestroy($strip);

        return $bytes;
    }

    private function load(string $path)
    {
        $img = @imagecreatefrompng($path);
        if ($img === false) {
            throw new \RuntimeException('Image PNG illisible : ' . $path);
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        return $img;
    }
}
