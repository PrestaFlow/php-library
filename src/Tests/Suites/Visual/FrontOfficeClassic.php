<?php

namespace PrestaFlow\Library\Tests\Suites\Visual;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * Régression visuelle du front-office, thème Classic.
 *
 * Mêmes checkpoints que le modèle « Front-office essentiel » de l'app, en
 * anglais (les boutiques Flashlight de la CI n'ont que l'anglais). Les chemins
 * sont sans préfixe de langue : avec une seule langue PrestaShop n'en met pas,
 * et PRESTAFLOW_PREFIX_LOCALE vaut false par défaut.
 *
 * Une exécution = un device (PRESTAFLOW_DEVICE) × une locale (PRESTAFLOW_LOCALE).
 */
class FrontOfficeClassic extends VisualTestsSuite
{
    protected array $devices = ['desktop', 'mobile'];

    protected array $locales = ['en'];

    protected array $checkpoints = [
        ['name' => 'header', 'path' => null, 'zone' => 'element', 'selector' => '#header', 'masks' => ['.cart-products-count']],
        ['name' => 'footer', 'path' => null, 'zone' => 'element', 'selector' => '#footer'],
        ['name' => 'home', 'path' => null, 'scrollBelow' => '#header', 'masks' => ['#carousel']],
        ['name' => 'login', 'path' => 'login', 'scrollBelow' => '#header'],
        ['name' => 'cart-empty', 'path' => 'cart', 'scrollBelow' => '#header'],
        ['name' => 'contact', 'path' => 'contact-us', 'scrollBelow' => '#header'],
        ['name' => 'search', 'path' => 'search?s=test', 'scrollBelow' => '#header'],
        ['name' => 'not-found', 'path' => 'page-not-found-prestaflow', 'scrollBelow' => '#header'],
    ];
}
