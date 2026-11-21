<?php

namespace PrestaFlow\Library\Pages\v9\FrontOffice\Search;

use PrestaFlow\Library\Exceptions\SkipTestException;
use PrestaFlow\Library\Pages\v9\FrontOffice\Listing\Page as BasePage;

/**
 * The search results page (controller=search) and the header search field
 * of the ps_searchbar module.
 *
 * The page is reached through the URL the shop publishes
 * (prestashop.urls.pages.search) with the s= parameter, the one both the
 * module's form and the core search controller use. Results are product
 * miniatures in #js-product-list, compared by their data-id-product
 * (Classic 1.7 to 9 and hummingbird), never by their wording.
 */
class Page extends BasePage
{
    public string $url = 'search';
    public $shopPage = 'search';

    /** Skip reason of pickSearchWord(). */
    public const NO_SEARCH_WORD = 'The product name has no word of at least 4 letters to search for: set the searchWord param.';

    /** Skip reason of requireHeaderSearch(). */
    public const NO_HEADER_SEARCH = 'This shop has no search field in its header (the ps_searchbar module is missing or not shown).';

    /** Shortest word pickSearchWord() searches for (PS_SEARCH_MINWORDLEN is 3 by default). */
    public const MIN_WORD_LENGTH = 4;

    public function defineSelectors()
    {
        return [
            ...parent::defineSelectors(),
            // Classic (1.7 to 9): #search_widget; hummingbird: #ps_searchbar.
            // Both carry the js-search-widget class the core JS relies on, on
            // a form posting the s field to the search controller.
            'headerSearchInput' => '#search_widget input[name="s"], #ps_searchbar input[name="s"], .js-search-widget input[name="s"]',
            'resultMiniature' => '#js-product-list [data-id-product]',
        ];
    }

    /**
     * Longest word of $text with at least $minLength letters (any script),
     * the first one on a tie; null when there is none. Digits and
     * punctuation split words: "T-shirt 2000" gives "shirt".
     */
    public static function longestWord(string $text, int $minLength = self::MIN_WORD_LENGTH): ?string
    {
        if (!preg_match_all('/[\p{L}\p{M}]+/u', $text, $matches)) {
            return null;
        }

        $best = null;
        $bestLength = $minLength - 1;
        foreach ($matches[0] as $word) {
            $length = mb_strlen($word);
            if ($length > $bestLength) {
                $best = $word;
                $bestLength = $length;
            }
        }

        return $best;
    }

    /**
     * The word to search for: $given when a suite sets one, otherwise the
     * longest word of $productName (longestWord()). Skips the test and the
     * rest of the suite when the name has no such word.
     */
    public function pickSearchWord(mixed $productName, mixed $given = null): string
    {
        if (is_scalar($given) && trim((string) $given) !== '') {
            return trim((string) $given);
        }

        $word = self::longestWord(is_scalar($productName) ? (string) $productName : '');
        if ($word === null) {
            throw new SkipTestException(self::NO_SEARCH_WORD, skipRemaining: true);
        }

        return $word;
    }

    /** URL of the search results for $word, from the shop's own search URL. */
    public function getSearchUrl(string $word): string
    {
        $this->pfPrimeShopUrls($this);
        $url = $this->getPageURL($this);

        return $url . (str_contains($url, '?') ? '&' : '?') . 's=' . rawurlencode($word);
    }

    /** Open the search results for $word through the search page's URL. */
    public function searchFor(mixed $word): void
    {
        $this->goToUrl($this->getSearchUrl(is_scalar($word) ? (string) $word : ''));
        $this->waitForPageLoaded();
    }

    /** Whether the page open now shows the header search field (ps_searchbar). */
    public function hasHeaderSearch(): bool
    {
        return $this->elementIsVisible($this->getSelector('headerSearchInput'), 3000);
    }

    /** Skip this test (only) when the page open now has no header search field. */
    public function requireHeaderSearch(): void
    {
        if (!$this->hasHeaderSearch()) {
            throw new SkipTestException(self::NO_HEADER_SEARCH, skipRemaining: false);
        }
    }

    /**
     * Type $word in the header search field of the page open now and submit
     * its form, as Enter does; waits for the results page. The visible field
     * is used when the theme renders several (desktop and mobile).
     */
    public function searchFromHeader(mixed $word): void
    {
        $this->getPage()->evaluate(sprintf(
            '(function(sel,word){var all=[].slice.call(document.querySelectorAll(sel));'
            . 'var i=all.find(function(e){return e.offsetParent!==null;})||all[0];if(!i)return;'
            . 'i.focus();i.value=word;i.dispatchEvent(new Event("input",{bubbles:true}));'
            . 'var f=i.form;if(!f)return;'
            . 'setTimeout(function(){if(f.requestSubmit){f.requestSubmit();}else{f.submit();}},0);})(%s,%s)',
            json_encode($this->getSelector('headerSearchInput')),
            json_encode(is_scalar($word) ? (string) $word : '')
        ));

        // The page the field was on is not the results page: wait for the
        // results page to be there and loaded, without racing the navigation.
        $this->waitForJsCondition(
            '!!document.body && document.body.id === "search" && document.readyState === "complete"',
            15000
        );
    }

    /** Whether the page open now is the search results page (body#search). */
    public function isSearchPage(): bool
    {
        return $this->waitForJsCondition('!!document.body && document.body.id === "search"', 5000);
    }

    /** Distinct product ids of the results, in page order (data-id-product). */
    public function getResultProductIds(): array
    {
        try {
            $json = $this->getPage()->evaluate(sprintf(
                '(function(sel){var out=[];[].slice.call(document.querySelectorAll(sel)).forEach(function(e){'
                . 'var id=parseInt(e.getAttribute("data-id-product"),10);if(id>0&&out.indexOf(id)<0){out.push(id);}});'
                . 'return JSON.stringify(out);})(%s)',
                json_encode($this->getSelector('resultMiniature'))
            ))->getReturnValue();
        } catch (\Throwable) {
            return [];
        }

        $ids = is_string($json) ? json_decode($json, true) : null;

        return is_array($ids) ? array_values(array_filter($ids, 'is_int')) : [];
    }

    /** Number of distinct products among the results. */
    public function countResults(): int
    {
        return count($this->getResultProductIds());
    }

    /** Whether product $productId (as a suite passes it) is among the results. */
    public function hasResultProduct(mixed $productId): bool
    {
        return is_numeric($productId) && in_array((int) $productId, $this->getResultProductIds(), true);
    }
}
