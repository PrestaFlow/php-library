#!/bin/sh
set -eu

# A shop duplicated by 10-second-shop.sh gets its products marked as indexed
# (product_shop.indexed is copied from shop 1) but no search words of its own
# (ps_search_word is per shop): every search on it answers no product.
# Rebuild the search index of each shop, in that shop's context.
echo "* Rebuilding the search index of every shop..."

cat > /tmp/search-index.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

$rows = Db::getInstance()->executeS('SELECT id_shop FROM ' . _DB_PREFIX_ . 'shop WHERE active = 1 ORDER BY id_shop');
foreach ($rows ?: [] as $row) {
    $idShop = (int) $row['id_shop'];
    Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
    Context::getContext()->shop = new Shop($idShop);

    // Full rebuild, limited to this shop's products and words in a shop context.
    Search::indexation(true);

    $words = (int) Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'search_word WHERE id_shop = ' . $idShop
    );
    echo '  shop ' . $idShop . ': ' . $words . " search words\n";
}
PHP

php /tmp/search-index.php
rm -f /tmp/search-index.php

echo "✅ Search index rebuilt"
