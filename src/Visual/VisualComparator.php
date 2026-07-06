<?php

namespace PrestaFlow\Library\Visual;

use SapientPro\ImageComparator\ImageComparator;

class VisualComparator
{
    private ImageComparator $comparator;

    public function __construct()
    {
        $this->comparator = new ImageComparator();
    }

    /** Score de similarité 0–1 (1 = identiques). Accepte des chemins de fichiers. */
    public function compare(string $referencePath, string $actualPath): float
    {
        $raw = (float) $this->comparator->compare($referencePath, $actualPath);
        // Normaliser en 0–1 quel que soit le domaine de sortie de la lib (0–1 ou 0–100).
        return $raw > 1.0 ? $raw / 100.0 : $raw;
    }

    /** PNG de diff : actuelle grisée, pixels différant de la référence peints en rouge. */
    public function generateDiff(string $referencePath, string $actualPath, string $diffPath, int $tolerance = 40): void
    {
        $ref = $this->load($referencePath);
        $act = $this->load($actualPath);
        $w = imagesx($ref);
        $h = imagesy($ref);
        if (imagesx($act) !== $w || imagesy($act) !== $h) {
            $resized = imagecreatetruecolor($w, $h);
            imagecopyresampled($resized, $act, 0, 0, 0, 0, $w, $h, imagesx($act), imagesy($act));
            imagedestroy($act);
            $act = $resized;
        }
        $out = imagecreatetruecolor($w, $h);
        $red = imagecolorallocate($out, 220, 40, 40);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $ra = imagecolorat($ref, $x, $y);
                $aa = imagecolorat($act, $x, $y);
                $d = abs((($ra >> 16) & 0xFF) - (($aa >> 16) & 0xFF)) + abs((($ra >> 8) & 0xFF) - (($aa >> 8) & 0xFF)) + abs(($ra & 0xFF) - ($aa & 0xFF));
                if ($d > $tolerance) {
                    imagesetpixel($out, $x, $y, $red);
                } else {
                    $g = (int) (((((($aa >> 16) & 0xFF) + (($aa >> 8) & 0xFF) + ($aa & 0xFF)) / 3) * 0.35) + 165);
                    imagesetpixel($out, $x, $y, imagecolorallocate($out, $g, $g, $g));
                }
            }
        }
        if (!is_dir(dirname($diffPath))) {
            mkdir(dirname($diffPath), 0777, true);
        }
        imagepng($out, $diffPath);
        imagedestroy($ref);
        imagedestroy($act);
        imagedestroy($out);
    }

    private function load(string $path)
    {
        $img = @imagecreatefrompng($path);
        if ($img === false) {
            throw new \RuntimeException('Image PNG illisible : ' . $path);
        }
        return $img;
    }
}
