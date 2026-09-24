#!/bin/sh
set -eu

# Works around PrestaShop#42964: ps_module_carrier is not in
# Shop::getAssoTables(), so a duplicated shop inherits no carrier restrictions
# and Hook::getHookModuleExecList() then offers it no payment method at all.
# Without this, checkout on shop 2 stops at "no payment method available".
echo "* Copying carrier restrictions to the second shop..."

cat > /tmp/carrier-restrictions.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

$rows = Db::getInstance()->executeS('SELECT id_shop FROM ' . _DB_PREFIX_ . 'shop WHERE id_shop <> 1');
foreach ($rows ?: [] as $row) {
    Db::getInstance()->execute(
        'INSERT IGNORE INTO ' . _DB_PREFIX_ . 'module_carrier (id_module, id_shop, id_reference) '
        . 'SELECT id_module, ' . (int) $row['id_shop'] . ', id_reference '
        . 'FROM ' . _DB_PREFIX_ . 'module_carrier WHERE id_shop = 1'
    );
}
PHP

php /tmp/carrier-restrictions.php
rm -f /tmp/carrier-restrictions.php

echo "✅ Carrier restrictions copied"
