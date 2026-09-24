<?php

namespace PrestaFlow\Library\Support;

use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

/**
 * Wrapper mince autour de smalot/pdfparser pour les assertions sur PDF dans
 * les scénarios (factures, avoirs, bons de livraison, e-tickets…).
 *
 * Instanciation :
 *   Pdf::fromFile('/tmp/facture.pdf')
 *   Pdf::fromString(file_get_contents(…))
 *
 * Lecture :
 *   ->text()                        // tout le PDF concaténé (\n\n entre pages)
 *   ->pages()                       // array<string> — 1 entrée par page
 *   ->contains('TVA', ci: true)     // bool
 *   ->matches('/TVA\s+([\d,\.]+)/') // array de matches (offset 1)
 *
 * Le parser réel n'est instancié qu'à la première lecture (lazy).
 */
class Pdf
{
    private ?string $binary = null;
    private ?string $path = null;
    private ?Document $document = null;
    private ?string $textCache = null;
    /** @var string[]|null */
    private ?array $pagesCache = null;

    private function __construct() {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Pdf::fromFile : fichier introuvable ou illisible « {$path} »");
        }
        $pdf = new self();
        $pdf->path = $path;
        return $pdf;
    }

    public static function fromString(string $binary): self
    {
        if ($binary === '') {
            throw new \RuntimeException('Pdf::fromString : contenu vide');
        }
        $pdf = new self();
        $pdf->binary = $binary;
        return $pdf;
    }

    /** Texte concaténé de toutes les pages (séparateur `\n\n`). */
    public function text(): string
    {
        if ($this->textCache !== null) {
            return $this->textCache;
        }
        return $this->textCache = implode("\n\n", $this->pages());
    }

    /**
     * Texte de chaque page dans l'ordre. Utile quand on veut asserter qu'une
     * mention est bien sur la 1re page (ex. mention légale d'exemption TVA).
     *
     * @return string[]
     */
    public function pages(): array
    {
        if ($this->pagesCache !== null) {
            return $this->pagesCache;
        }
        $out = [];
        foreach ($this->document()->getPages() as $page) {
            $out[] = (string) $page->getText();
        }
        return $this->pagesCache = $out;
    }

    /**
     * true si $needle est dans le texte. Casse-insensible par défaut :
     * l'extraction PDF conserve la casse d'origine (souvent MAJUSCULES pour
     * les mentions légales) et on veut asserter du texte lisible côté humain.
     */
    public function contains(string $needle, bool $caseSensitive = false): bool
    {
        if ($needle === '') {
            return true;
        }
        $haystack = $this->text();
        return $caseSensitive
            ? str_contains($haystack, $needle)
            : stripos($haystack, $needle) !== false;
    }

    /**
     * Retourne les matches de $regex sur le texte concaténé. Renvoie un
     * tableau vide si aucun match. Multi-lignes et Unicode activés par
     * défaut ; passez votre propre delimiter+flags si vous voulez plus fin.
     *
     * @return string[]|array<int,array<int,string>>
     */
    public function matches(string $regex, int $group = 0): array
    {
        if (@preg_match($regex, '') === false) {
            throw new \RuntimeException("Pdf::matches : regex invalide « {$regex} »");
        }
        if (!preg_match_all($regex, $this->text(), $m)) {
            return [];
        }
        return $m[$group] ?? [];
    }

    /** Nombre de pages. */
    public function pageCount(): int
    {
        return count($this->pages());
    }

    private function document(): Document
    {
        if ($this->document !== null) {
            return $this->document;
        }
        $parser = new Parser();
        $this->document = $this->path !== null
            ? $parser->parseFile($this->path)
            : $parser->parseContent((string) $this->binary);
        return $this->document;
    }
}
