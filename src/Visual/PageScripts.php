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

    /**
     * Carte des éléments visibles + sélecteur CSS proposé (sélecteur visuel).
     * Fonction à appeler avec (MAX éléments, MAX_Y px) ; renvoie une chaîne JSON :
     * [{i, p, tag, id, classes, box:[x,y,w,h] (coordonnées DOCUMENT), selector, matches}]
     * Parcours en profondeur : un parent a toujours un index inférieur à ses enfants.
     */
    public const ELEMENT_MAP = <<<'JS'
(function (MAX, MAX_Y) {
  var SKIP = { SCRIPT: 1, STYLE: 1, META: 1, LINK: 1, NOSCRIPT: 1, TEMPLATE: 1, HEAD: 1, TITLE: 1, BASE: 1 };
  var STATE = /^(active|open|show|hover|focus|selected|current|disabled|collapsed|in|fade|visible)$/;
  var PREFIX = /^(is-|has-|js-|owl-|swiper-|slick-|splide__)/;
  var sx = window.scrollX, sy = window.scrollY;
  var esc = function (s) { return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/([^\w-])/g, '\\$1'); };
  var count = function (sel) { try { return document.querySelectorAll(sel).length; } catch (e) { return 0; } };
  var generatedId = function (id) { return /\d{4,}/.test(id) || /[0-9a-f]{8,}/i.test(id) || /^(ember|react-|__)/.test(id); };
  var stable = function (el) {
    return Array.prototype.filter.call(el.classList, function (c) { return !STATE.test(c) && !PREFIX.test(c) && !/\d{4,}/.test(c); }).slice(0, 5);
  };
  var combos = function (list, n) {
    if (n === 1) { return list.map(function (c) { return [c]; }); }
    var out = [];
    list.forEach(function (c, k) { combos(list.slice(k + 1), n - 1).forEach(function (rest) { out.push([c].concat(rest)); }); });
    return out;
  };
  var ownMemo = new Map();
  var own = function (el) {
    if (ownMemo.has(el)) { return ownMemo.get(el); }
    var found = null;
    if (el.id && !generatedId(el.id) && count('#' + esc(el.id)) === 1) { found = '#' + esc(el.id); }
    if (!found) {
      var tag = el.tagName.toLowerCase(), cls = stable(el);
      for (var n = 1; n <= Math.min(3, cls.length) && !found; n++) {
        var list = combos(cls, n);
        for (var k = 0; k < list.length; k++) {
          var s = tag + list[k].map(function (c) { return '.' + esc(c); }).join('');
          if (count(s) === 1) { found = s; break; }
        }
      }
    }
    ownMemo.set(el, found);
    return found;
  };
  var selectorFor = function (el) {
    var mine = own(el);
    if (mine) { return mine; }
    var path = [], cur = el;
    while (cur && cur.parentElement) {
      var idx = 1;
      for (var sib = cur.previousElementSibling; sib; sib = sib.previousElementSibling) { if (sib.tagName === cur.tagName) { idx++; } }
      path.unshift(cur.tagName.toLowerCase() + ':nth-of-type(' + idx + ')');
      var parent = cur.parentElement;
      var anchor = parent === document.body ? 'body' : (parent === document.documentElement ? 'html' : own(parent));
      if (anchor) {
        var sel = anchor + ' > ' + path.join(' > ');
        if (count(sel) === 1) { return sel; }
      }
      cur = parent;
    }
    return 'html > ' + path.join(' > ');
  };
  var kept = [];
  var walk = function (el, p) {
    if (kept.length >= MAX || SKIP[el.tagName.toUpperCase()]) { return; }
    var cs = getComputedStyle(el);
    if (cs.display === 'none' || parseFloat(cs.opacity) === 0) { return; }
    var r = el.getBoundingClientRect();
    var top = r.top + sy;
    var me = p;
    if (cs.visibility !== 'hidden' && r.width * r.height > 16 && el.getClientRects().length > 0 && top < MAX_Y) {
      me = kept.length;
      kept.push({ el: el, p: p, box: [Math.round(r.left + sx), Math.round(top), Math.round(r.width), Math.round(r.height)] });
    }
    if (el.tagName.toLowerCase() === 'svg') { return; }
    for (var c = el.firstElementChild; c; c = c.nextElementSibling) { walk(c, me); }
  };
  walk(document.body, -1);
  return JSON.stringify(kept.map(function (k, i) {
    var sel = selectorFor(k.el);
    return {
      i: i, p: k.p, tag: k.el.tagName.toLowerCase(), id: k.el.id || '',
      classes: Array.prototype.slice.call(k.el.classList, 0, 8), box: k.box,
      selector: sel, matches: count(sel)
    };
  }));
})
JS;
}
