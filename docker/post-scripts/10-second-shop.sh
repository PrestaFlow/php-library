#!/bin/sh
set -eu

echo "* Provisioning a second shop..."

cat > /tmp/second-shop.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

$src = 1;

// A second shop, its group, and its URL on a dedicated port.
$group = new ShopGroup();
$group->name = 'Group2';
$group->active = true;
$group->add();

$shop = new Shop();
$shop->name = 'Shop2';
$shop->id_shop_group = (int) $group->id;
$shop->id_category = (int) Configuration::get('PS_HOME_CATEGORY');
$shop->theme_name = 'classic';
$shop->active = true;
$shop->add();

$url = new ShopUrl();
$url->id_shop = (int) $shop->id;
$secondPort = getenv('SECOND_SHOP_PORT') ?: '8093';
$host = preg_replace('/:\d+$/', '', (string) Configuration::get('PS_SHOP_DOMAIN'));
$url->domain = $url->domain_ssl = $host . ':' . $secondPort;
// A DEDICATED PORT, not a virtual URI. PrestaShop discriminates shops by
// domain including the port, so localhost:8093 is a distinct shop — and its
// assets resolve at the root, needing no rewrite at all.
//
// The virtual-URI approach this replaced cannot work here: it relies on
// Tools::generateHtaccess(), and every Flashlight tag we use is -nginx, which
// never reads .htaccess. Verified 2026-09-24: /shop2/themes/...css returned
// 404 while the same file at the root returned 200. There is no -apache tag
// for 1.7.8.11, 8.2.8 or 9.2.0 (only 9.0.3 has one), so switching flavour is
// not an option either.
$url->physical_uri = '/';
$url->virtual_uri = '';
$url->main = true;
$url->active = true;
$url->add();

// copyShopData() reads Tools::getValue('categoryBox'): outside an HTTP request
// that returns false and count(false) is fatal on PHP 8. Prime it with the
// source shop's categories, which is what the Multistore form would post.
$rows = Db::getInstance()->executeS(
    'SELECT id_category FROM ' . _DB_PREFIX_ . 'category_shop WHERE id_shop = ' . (int) $src
);
$categories = array_map('intval', array_column($rows ?: [], 'id_category'));
$_POST['categoryBox'] = $_REQUEST['categoryBox'] = $categories;

// The same 26 keys the Multistore form offers.
$keys = [
    'carrier', 'cms', 'contact', 'country', 'currency', 'discount', 'employee',
    'image', 'lang', 'manufacturer', 'module', 'hook_module', 'meta_lang',
    'product', 'product_attribute', 'stock_available', 'store',
    'webservice_account', 'attribute_group', 'feature', 'group',
    'tax_rules_group', 'supplier', 'zone', 'cart_rule',
];
$shop->copyShopData($src, array_fill_keys($keys, 'on'));
$shop->associateSuperAdmins();

array_unshift($categories, (int) Configuration::get('PS_ROOT_CATEGORY'));
Category::updateFromShop(array_values(array_unique($categories)), (int) $shop->id);

// Module-owned data travels through a hook, not through copyShopData().
foreach ((array) Hook::getHookModuleExecList('actionShopDataDuplication') as $m) {
    Hook::exec('actionShopDataDuplication', [
        'old_id_shop' => $src,
        'new_id_shop' => (int) $shop->id,
    ], (int) $m['id_module']);
}

// Multistore is enabled, and its back-office screens must exist with it:
// setting the flag without activating the tabs gives a shop that cannot be
// managed, which is exactly how the previous hand-made container ended up.
Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
Db::getInstance()->execute(
    'UPDATE ' . _DB_PREFIX_ . "tab SET active = 1 WHERE class_name IN ('AdminShopGroup','AdminShopUrl')"
);

// Nothing to rewrite: the second shop lives at the root of its own port.

echo 'second shop id=' . (int) $shop->id . PHP_EOL;
PHP

php /tmp/second-shop.php
rm -f /tmp/second-shop.php

echo "✅ Second shop provisioned"
