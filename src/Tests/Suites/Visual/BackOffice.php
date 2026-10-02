<?php

namespace PrestaFlow\Library\Tests\Suites\Visual;

use PrestaFlow\Library\Tests\VisualTestsSuite;

/**
 * Back-office de PrestaShop (1.7.8, 8.2, 9.2), en anglais, sur desktop.
 *
 * Les URL d'admin portent un jeton : chaque page est atteinte par son entrée du
 * menu latéral. Le tableau de bord s'appelle `#tab-AdminDashboard` en 1.7 et
 * `#subtab-AdminDashboard` ensuite. Un masque absent d'une version est sans effet,
 * donc une même liste couvre les trois versions.
 */
class BackOffice extends VisualTestsSuite
{
    protected string $area = 'bo';

    protected array $devices = ['desktop'];

    protected array $locales = ['en'];

    protected array $checkpoints = [
        // Version affichée sous le logo en 1.7.
        ['name' => 'login', 'auth' => false, 'masks' => ['#login-header .text-center']],
        ['name' => 'dashboard', 'menu' => '#subtab-AdminDashboard, #tab-AdminDashboard',
            // Zones de widgets (chiffres, graphiques, actualités distantes) et période choisie.
            'masks' => ['#total_notif_number_wrapper', '#notifications-total', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                '#hookDashboardZoneOne', '#hookDashboardZoneTwo', '#hookDashboardZoneThree', '#calendar_form'],
            // Popup d'onboarding animée de la 1.7 et son fond ; indicateur d'appels ajax.
            'hide' => ['.onboarding-popup', '.modal-backdrop', '#ajax_running', '#header_infos .ajax-spinner']],
        ['name' => 'products', 'menu' => '#subtab-AdminProducts',
            'masks' => ['#total_notif_number_wrapper', '#notifications-total', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar'],
            // Indicateur d'appels ajax (coin haut gauche, ancien et nouveau thème), présent selon le timing.
            'hide' => ['#ajax_running', '#header_infos .ajax-spinner']],
        ['name' => 'orders', 'menu' => '#subtab-AdminOrders',
            'masks' => ['#total_notif_number_wrapper', '#notifications-total', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                'td.column-date_add'],
            // KPI chargés en ajax : hauteur variable selon le timing, retirés de la mise en page avec leur bouton d'actualisation.
            'hide' => ['#ajax_running', '#header_infos .ajax-spinner', '.kpi-container', '.kpi-refresh']],
        ['name' => 'customers', 'menu' => '#subtab-AdminCustomers',
            'masks' => ['#total_notif_number_wrapper', '#notifications-total', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                'td.column-date_add', 'td.column-connect', '#customersShowcaseCard'],
            'hide' => ['#ajax_running', '#header_infos .ajax-spinner', '.kpi-container', '.kpi-refresh']],
        ['name' => 'modules', 'menu' => '#subtab-AdminModulesSf',
            'masks' => ['#total_notif_number_wrapper', '#notifications-total', '#shop_version', '#ps-enterprise-ask-ai-anchor', '.onboarding-navbar',
                '.notification-counter'],
            'hide' => ['#ajax_running', '#header_infos .ajax-spinner']],
    ];
}
