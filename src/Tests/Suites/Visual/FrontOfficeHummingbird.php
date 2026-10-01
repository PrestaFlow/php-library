<?php

namespace PrestaFlow\Library\Tests\Suites\Visual;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * Régression visuelle du front-office, thème Hummingbird.
 *
 * Mêmes checkpoints que le modèle « Front-office essentiel » de l'app, en
 * anglais (les boutiques Flashlight de la CI n'ont que l'anglais). Les chemins
 * sont sans préfixe de langue : avec une seule langue PrestaShop n'en met pas,
 * et PRESTAFLOW_PREFIX_LOCALE vaut false par défaut.
 *
 * Pas de scrollBelow : le header Hummingbird est sticky, il resterait dans la
 * capture. Le badge du panier est masqué partout où le header apparaît.
 *
 * Une exécution = un device (PRESTAFLOW_DEVICE) × une locale (PRESTAFLOW_LOCALE).
 */
class FrontOfficeHummingbird extends VisualTestsSuite
{
    protected array $devices = ['desktop', 'mobile'];

    protected array $locales = ['en'];

    protected array $checkpoints = [
        ['name' => 'header', 'path' => null, 'zone' => 'element', 'selector' => '#header', 'masks' => ['.header-block__badge']],
        ['name' => 'footer', 'path' => null, 'zone' => 'element', 'selector' => '#footer'],
        ['name' => 'home', 'path' => null, 'masks' => ['.header-block__badge', '#home-slider', '.ps-imageslider']],
        ['name' => 'login', 'path' => 'login', 'masks' => ['.header-block__badge']],
        ['name' => 'cart-empty', 'path' => 'cart', 'masks' => ['.header-block__badge']],
        ['name' => 'contact', 'path' => 'contact-us', 'masks' => ['.header-block__badge']],
        ['name' => 'search', 'path' => 'search?s=test', 'masks' => ['.header-block__badge']],
        ['name' => 'not-found', 'path' => 'page-not-found-prestaflow', 'masks' => ['.header-block__badge']],
    ];
}
