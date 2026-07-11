<?php

namespace PrestaFlow\Library\Reports;

final class VisualReport
{
    /**
     * @param \DateTimeImmutable|null $generatedAt Horodatage de génération (défaut :
     *        maintenant, UTC). Injectable pour les tests et pour figer un stamp
     *        commun entre HTML et JSON.
     */
    public function renderHtml(array $results, ?\DateTimeImmutable $generatedAt = null): string
    {
        $generatedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $counts = ['pass' => 0, 'fail' => 0, 'baseline' => 0];
        foreach ($results as $r) { $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1; }

        $rows = '';
        foreach ($results as $r) { $rows .= $this->row($r); }

        $head = '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Régression visuelle</title><style>'
            . 'body{font-family:system-ui,sans-serif;margin:2rem;color:#1a1a1a}'
            . 'h1{font-size:20px;margin:0 0 .25rem}.stamp{color:#666;font-size:13px;margin:0 0 1rem}'
            . '.sum{display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0}'
            . '.card{border:1px solid #e2e2e2;border-radius:10px;padding:1rem;margin:1rem 0}'
            . '.hd{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:space-between;align-items:center;margin-bottom:.75rem}'
            . '.badge{font-size:12px;padding:3px 10px;border-radius:6px}'
            . '.pass{background:#e1f5ee;color:#0f6e56}.fail{background:#fceceb;color:#a32d2d}'
            . '.baseline{background:#e6f1fb;color:#0c447c}'
            . '.imgs{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.75rem}'
            . '.imgs figure{margin:0;min-width:0}.imgs img{width:100%;height:auto;border:1px solid #e2e2e2;border-radius:6px;display:block}'
            . '.cap{font-size:12px;color:#666;margin-bottom:4px}'
            . '@media (max-width:640px){body{margin:1rem}.card{padding:.75rem}h1{font-size:18px}}</style></head><body>';

        // `T` = abbréviation du fuseau (CEST/UTC/…). ATOM = ISO 8601 avec offset.
        $stampAttr = htmlspecialchars($generatedAt->format(\DateTimeInterface::ATOM), ENT_QUOTES);
        $stampHuman = htmlspecialchars($generatedAt->format('Y-m-d H:i:s T'));

        $summary = '<h1>Régression visuelle</h1>'
            . '<p class="stamp">Généré le <time datetime="' . $stampAttr . '">' . $stampHuman . '</time></p>'
            . '<div class="sum">'
            . '<div>Conformes : <strong>' . $counts['pass'] . '</strong></div>'
            . '<div>Écarts : <strong>' . $counts['fail'] . '</strong></div>'
            . '<div>Baselines : <strong>' . $counts['baseline'] . '</strong></div></div>';

        return $head . $summary . $rows . '</body></html>';
    }

    public function renderJson(array $results, ?\DateTimeImmutable $generatedAt = null): string
    {
        $generatedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $out = [
            'generatedAt' => $generatedAt->format(\DateTimeInterface::ATOM),
            'checkpoints' => array_map(static fn ($r) => [
                'name' => $r['name'], 'status' => $r['status'], 'score' => $r['score'],
            ], $results),
        ];

        return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function row(array $r): string
    {
        $status = $r['status'];
        $scoreTxt = $r['score'] === null ? '—' : number_format($r['score'] * 100, 1) . ' %';
        $label = $status === 'baseline' ? 'baseline créée' : ($status === 'pass' ? 'conforme' : 'écart');

        $imgs = '';
        foreach (['reference' => 'Référence', 'actual' => 'Actuelle', 'diff' => 'Diff'] as $key => $cap) {
            if (!empty($r[$key]) && is_file($r[$key])) {
                $imgs .= '<figure><div class="cap">' . $cap . '</div><img alt="' . htmlspecialchars($cap)
                    . '" src="data:image/png;base64,' . base64_encode(file_get_contents($r[$key])) . '"></figure>';
            }
        }

        return '<div class="card"><div class="hd"><strong>' . htmlspecialchars($r['name']) . '</strong>'
            . '<span>score ' . $scoreTxt . ' <span class="badge ' . $status . '">' . $label . '</span></span></div>'
            . '<div class="imgs">' . $imgs . '</div></div>';
    }
}
