#!/bin/sh
set -eu

# The test containers have no mail relay (sendmail is busybox's, with no SMTP
# server behind it), so Mail::Send() fails and every flow that checks it
# answers an error: the forgotten-password page says "An error occurred while
# sending the email.". PS_MAIL_METHOD=3 (Mail::METHOD_DISABLE, "Never send
# emails") makes Mail::Send() return true without sending, in 1.7, 8 and 9.
# Set for every shop: shop- and group-level values are dropped so none of
# them overrides the global one.
echo "* Disabling e-mails on every shop..."

cat > /tmp/no-mail.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

Db::getInstance()->execute(
    'DELETE FROM ' . _DB_PREFIX_ . 'configuration WHERE name = \'PS_MAIL_METHOD\''
    . ' AND (id_shop IS NOT NULL OR id_shop_group IS NOT NULL)'
);
Configuration::updateGlobalValue('PS_MAIL_METHOD', 3);
PHP

php /tmp/no-mail.php
rm -f /tmp/no-mail.php

echo "✅ E-mails disabled"
