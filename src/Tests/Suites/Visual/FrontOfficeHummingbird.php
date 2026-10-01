<?php

namespace PrestaFlow\Library\Tests\Suites\Visual;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * Régression visuelle du front-office, thème Hummingbird.
 *
 * Mêmes checkpoints que le modèle « Front-office essentiel » de l'app, en
 * anglais et en français. `path` reste le chemin anglais (comportement en
 * inchangé) ; `paths['fr']` donne la réécriture française installée par le
 * pack de langue (connexion, panier, nous-contacter, recherche). La CI installe
 * le français (.github/visual/install-french.sh) et lance avec
 * PRESTAFLOW_PREFIX_LOCALE=true : deux langues actives → URLs en /en/ et /fr/.
 *
 * Pas de scrollBelow : le header Hummingbird est sticky, il resterait dans la
 * capture. Le badge du panier est masqué partout où le header apparaît.
 *
 * Une exécution = un device (PRESTAFLOW_DEVICE) × une locale (PRESTAFLOW_LOCALE).
 */
class FrontOfficeHummingbird extends VisualTestsSuite
{
    protected array $devices = ['desktop', 'mobile'];

    protected array $locales = ['en', 'fr'];

    protected array $checkpoints = [
        ['name' => 'header', 'path' => null, 'zone' => 'element', 'selector' => '#header', 'masks' => ['.header-block__badge']],
        ['name' => 'footer', 'path' => null, 'zone' => 'element', 'selector' => '#footer'],
        ['name' => 'home', 'path' => null, 'masks' => ['.header-block__badge', '#home-slider', '.ps-imageslider']],
        ['name' => 'login', 'path' => 'login', 'paths' => ['fr' => 'connexion'], 'masks' => ['.header-block__badge']],
        ['name' => 'cart-empty', 'path' => 'cart', 'paths' => ['fr' => 'panier'], 'masks' => ['.header-block__badge']],
        ['name' => 'contact', 'path' => 'contact-us', 'paths' => ['fr' => 'nous-contacter'], 'masks' => ['.header-block__badge']],
        ['name' => 'search', 'path' => 'search?s=test', 'paths' => ['fr' => 'recherche?s=test'], 'masks' => ['.header-block__badge']],
        ['name' => 'not-found', 'path' => 'page-not-found-prestaflow', 'paths' => ['fr' => 'page-introuvable-prestaflow'], 'masks' => ['.header-block__badge']],
    ];
}
