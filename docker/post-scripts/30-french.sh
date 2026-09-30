set -eu

echo "* Installing French..."

cat > /tmp/add-french.php <<'PHP'
<?php
require_once '/var/www/html/config/config.inc.php';

// Every shop, not only the one the CLI context lands on: Language::add()
// associates the new language with the shops of the current context, and the
// second shop (10-second-shop.sh) was left without French otherwise.
Shop::setContext(Shop::CONTEXT_ALL);
Context::getContext()->employee = new Employee(1);

// Translating the multilang tables goes through SymfonyContainer, which reads
// the global $kernel: config.inc.php alone does not boot one in CLI. PS 9
// splits the back office into AdminKernel; 1.7 and 8 only have AppKernel.
$kernelClass = file_exists('/var/www/html/app/AdminKernel.php') ? 'AdminKernel' : 'AppKernel';
require_once '/var/www/html/app/' . $kernelClass . '.php';
global $kernel;
$kernel = new $kernelClass('prod', false);
$kernel->boot();

// Language::checkAndAddLanguage() only creates the language row: without the
// pack, French is a copy of English (/fr/search instead of /fr/recherche, no
// translated strings). downloadAndInstallLanguagePack() downloads the pack,
// creates the language when missing, installs the catalogue and translates the
// multilang tables (meta url_rewrite included). It also repairs a French that
// was added earlier without its pack, so it runs even when 'fr' exists.
$result = Language::downloadAndInstallLanguagePack('fr', _PS_VERSION_);
if ($result !== true) {
    fwrite(STDERR, implode(PHP_EOL, (array) $result) . PHP_EOL);
    exit(1);
}

$idLang = (int) Language::getIdByIso('fr');
foreach (Shop::getCompleteListOfShopsID() as $idShop) {
    Db::getInstance()->execute(
        'INSERT IGNORE INTO ' . _DB_PREFIX_ . 'lang_shop (id_lang, id_shop) VALUES (' . $idLang . ', ' . (int) $idShop . ')'
    );
}

// PS 9 drops the /en/ prefix for the default language and redirects /en/...
// to /... (losing the query: /en/search?s=test lands on /search). Keep the
// prefix on every language, as 1.7 and 8 do, so /{iso}/{path} is valid
// everywhere. The key is unknown before 9 and harmless there.
Configuration::updateGlobalValue('PS_DEFAULT_LANGUAGE_URL_PREFIX', 1);

Tools::clearAllCache();

echo 'french id_lang=' . $idLang . PHP_EOL;
PHP

if php /tmp/add-french.php; then
    echo "✅ French installed"
else
    echo "⚠️ French could not be installed (see the error above; no network?)"
fi
rm -f /tmp/add-french.php
