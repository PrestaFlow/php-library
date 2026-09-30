<?php

namespace PrestaFlow\Library\Visual;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Clip;
use HeadlessChromium\Page;

/**
 * Capture d'une page pour le sélecteur visuel : pleine page en JPEG qualité 80
 * (animations figées, comme au run ; bien plus léger qu'un PNG pleine page)
 * + carte des éléments visibles avec un sélecteur CSS proposé.
 *
 * Navigateur DÉDIÉ (jamais l'instance statique de TestsSuite) : une capture ne
 * doit pas perturber un run en cours, et inversement.
 */
class PageSnapshot
{
    /** Au-delà, Chrome ne capture plus de manière fiable (texture GPU). */
    public const MAX_HEIGHT = 15000;

    public const MAX_ELEMENTS = 4000;

    public const JPEG_QUALITY = 80;

    /** @var \Closure(array): object */
    private \Closure $browserFactory;

    /**
     * @param (\Closure(array): object)|null $browserFactory options chrome-php → navigateur ; null = BrowserFactory
     */
    public function __construct(
        ?\Closure $browserFactory = null,
        private int $stableTimeoutMs = 5000,
        private int $pollMs = 200,
    ) {
        $this->browserFactory = $browserFactory ?? static fn (array $options) => (new BrowserFactory())->createBrowser($options);
    }

    public function take(string $url, string $device = 'desktop', int $timeoutMs = 20000): SnapshotResult
    {
        $preset = VisualDevices::get($device);

        try {
            $browser = ($this->browserFactory)([
                'windowSize' => [$preset['width'], $preset['height']],
                'userAgent' => $preset['userAgent'],
                'headless' => true,
                'ignoreCertificateErrors' => true,
                'keepAlive' => false,
                'sendSyncDefaultTimeout' => $timeoutMs,
            ]);
        } catch (\Throwable $e) {
            throw new SnapshotException('Navigateur Chrome introuvable ou impossible à lancer : '.$e->getMessage(), 0, $e);
        }

        try {
            $page = $browser->createPage();
            try {
                // DOMContentLoaded plutôt que `load` (souvent plusieurs secondes de plus sur
                // une boutique) : la stabilité (readyState complete, polices, images
                // visibles) est ensuite sondée, bornée par stableTimeoutMs et rapportée.
                $page->navigate($url)->waitForNavigation(Page::DOM_CONTENT_LOADED, $timeoutMs);
            } catch (\Throwable $e) {
                throw new SnapshotException(sprintf("La page %s n'a pas répondu en %d s.", $url, intdiv($timeoutMs, 1000)), 0, $e);
            }

            $stable = $this->waitUntilStable($page);
            $page->evaluate(PageScripts::SETTLE_ANIMATIONS)->getReturnValue();

            $status = (int) $page->evaluate(
                "(function(){var n=performance.getEntriesByType('navigation')[0];return n&&n.responseStatus?n.responseStatus:0;})()"
            )->getReturnValue();
            // Une page en erreur (404, 500…) est capturée comme les autres : un point de
            // contrôle peut légitimement cibler une page d'erreur. Le statut est renvoyé.

            [$width, $height] = $page->evaluate(
                '[Math.ceil(document.documentElement.scrollWidth), Math.ceil(document.documentElement.scrollHeight)]'
            )->getReturnValue();
            $width = max(1, (int) $width);
            $height = max(1, min((int) $height, self::MAX_HEIGHT));

            $page->evaluate('window.scrollTo(0, 0)')->getReturnValue();
            $json = $page->evaluate(PageScripts::ELEMENT_MAP.'('.self::MAX_ELEMENTS.', '.$height.')')->getReturnValue($timeoutMs);
            $elements = is_string($json) ? json_decode($json, true) : null;

            $image = base64_decode((string) $page->screenshot([
                'format' => 'jpeg',
                'quality' => self::JPEG_QUALITY,
                'captureBeyondViewport' => true,
                'clip' => new Clip(0, 0, $width, $height),
            ])->getBase64($timeoutMs), true);

            return new SnapshotResult((string) $image, 'image/jpeg', $width, $height, is_array($elements) ? $elements : [], $stable, $status);
        } finally {
            try {
                $browser->close();
            } catch (\Throwable $e) {
                // le navigateur est peut-être déjà mort : rien à faire
            }
        }
    }

    private function waitUntilStable(object $page): bool
    {
        $deadline = microtime(true) + $this->stableTimeoutMs / 1000;
        do {
            try {
                if ($page->evaluate('(function(){try{return !!('.PageScripts::STABLE_CONDITION.');}catch(e){return false;}})()')->getReturnValue() === true) {
                    return true;
                }
            } catch (\Throwable $e) {
                // page occupée : on réessaie jusqu'à l'échéance
            }
            usleep($this->pollMs * 1000);
        } while (microtime(true) < $deadline);

        return false;
    }
}
