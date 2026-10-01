<?php

namespace PrestaFlow\Library\Tests\Suites\Visual;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * Régression visuelle du front-office, thème Classic.
 *
 * Mêmes checkpoints que le modèle « Front-office essentiel » de l'app, en
 * anglais et en français. `path` reste le chemin anglais (comportement en
 * inchangé) ; `paths['fr']` donne la réécriture française installée par le
 * pack de langue (connexion, panier, nous-contacter, recherche). La CI installe
 * le français (.github/visual/install-french.sh) et lance avec
 * PRESTAFLOW_PREFIX_LOCALE=true : deux langues actives → URLs en /en/ et /fr/.
 *
 * Une exécution = un device (PRESTAFLOW_DEVICE) × une locale (PRESTAFLOW_LOCALE).
 */
class FrontOfficeClassic extends VisualTestsSuite
{
    protected array $devices = ['desktop', 'mobile'];

    protected array $locales = ['en', 'fr'];

    protected array $checkpoints = [
        ['name' => 'header', 'path' => null, 'zone' => 'element', 'selector' => '#header', 'masks' => ['.cart-products-count']],
        ['name' => 'footer', 'path' => null, 'zone' => 'element', 'selector' => '#footer'],
        ['name' => 'home', 'path' => null, 'scrollBelow' => '#header', 'masks' => ['#carousel']],
        ['name' => 'login', 'path' => 'login', 'paths' => ['fr' => 'connexion'], 'scrollBelow' => '#header'],
        ['name' => 'cart-empty', 'path' => 'cart', 'paths' => ['fr' => 'panier'], 'scrollBelow' => '#header'],
        ['name' => 'contact', 'path' => 'contact-us', 'paths' => ['fr' => 'nous-contacter'], 'scrollBelow' => '#header'],
        ['name' => 'search', 'path' => 'search?s=test', 'paths' => ['fr' => 'recherche?s=test'], 'scrollBelow' => '#header'],
        ['name' => 'not-found', 'path' => 'page-not-found-prestaflow', 'paths' => ['fr' => 'page-introuvable-prestaflow'], 'scrollBelow' => '#header'],
    ];
}
