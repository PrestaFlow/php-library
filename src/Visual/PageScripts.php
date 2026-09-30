<?php

namespace PrestaFlow\Library\Visual;

/**
 * Scripts évalués dans la page par les captures visuelles (runs et sélecteur
 * visuel) : une seule source pour ne jamais diverger.
 */
final class PageScripts
{
    /**
     * Page stable : chargement complet + polices + images visibles. Ignore les
     * images sans boîte de rendu (masquées par CSS, elles ne se chargent jamais)
     * et les images lazy hors du viewport. Expression booléenne (sans `return`).
     */
    public const STABLE_CONDITION =
        "document.readyState === 'complete' && (!document.fonts || document.fonts.status === 'loaded') "
        . "&& Array.from(document.images).every(function(i){"
        . "if (i.complete) { return true; }"
        . "if (i.getClientRects().length === 0) { return true; }"
        . "if (!i.offsetParent && getComputedStyle(i).position !== 'fixed') { return true; }"
        . "var r = i.getBoundingClientRect();"
        . "var outside = r.bottom <= 0 || r.top >= window.innerHeight || r.right <= 0 || r.left >= window.innerWidth;"
        . "return i.loading === 'lazy' && outside;"
        . "})";

    /**
     * Fige les animations comme Playwright (`animations: 'disabled'`) : finie →
     * état final, infinie → annulée (état de départ). IIFE sans valeur de retour.
     */
    public const SETTLE_ANIMATIONS =
        "(function(){if(!document.getAnimations){return;}document.getAnimations().forEach(function(a){"
        . "try{var t=a.effect&&a.effect.getComputedTiming();if(t&&t.endTime===Infinity){a.cancel();}else{a.finish();}}catch(e){}"
        . "});})()";
}
