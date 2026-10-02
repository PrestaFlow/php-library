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
     * Sélecteur, par ordre de préférence : id stable ou combinaison de classes
     * stables unique → descendant d'un ancêtre unique (`#main div.card`) →
     * descendant d'un ancêtre unique via un repère intermédiaire
     * (`#_desktop_blocks-7 #block-7-1 h5`) → chemin :nth-of-type depuis
     * l'ancêtre unique le plus proche. Les classes d'état (active, hidden, d-none, sr-only…) et de librairies sont ignorées.
     * Les clones de carrousels (Owl `.owl-item.cloned`, Slick `.slick-cloned`,
     * Swiper `.swiper-slide-duplicate`) et tout leur contenu sont exclus de la
     * carte et du décompte : ce sont des doublons visuels. Si un sélecteur ne
     * reste unique qu'en ignorant les clones, il est suffixé d'un filtre
     * `:not(.owl-item.cloned *)` pour rester unique dans le vrai document.
     * Fonction à appeler avec (MAX éléments, MAX_Y px) ; renvoie une chaîne JSON :
     * [{i, p, tag, id, classes, box:[x,y,w,h] (coordonnées DOCUMENT), selector, matches}]
     * Parcours en profondeur : un parent a toujours un index inférieur à ses enfants.
     */
    public const ELEMENT_MAP = <<<'JS'
(function (MAX, MAX_Y) {
  var SKIP = { SCRIPT: 1, STYLE: 1, META: 1, LINK: 1, NOSCRIPT: 1, TEMPLATE: 1, HEAD: 1, TITLE: 1, BASE: 1 };
  var STATE = /^(active|open|show|hover|focus|selected|current|disabled|collapsed|in|fade|visible|hidden|d-none|sr-only|invisible)$/;
  var PREFIX = /^(is-|has-|js-|owl-|swiper-|slick-|splide__)/;
  var sx = window.scrollX, sy = window.scrollY;
  // clones de carrousels présents dans la page (doublons visuels à ignorer)
  var CLONES = ['.owl-item.cloned', '.slick-cloned', '.swiper-slide-duplicate'].filter(function (c) { return document.querySelector(c); });
  var CLONE = CLONES.join(', ');
  var NOT_CLONE = CLONES.map(function (c) { return ':not(' + c + ' *)'; }).join('');
  var esc = function (s) { return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/([^\w-])/g, '\\$1'); };
  // le DOM ne bouge pas pendant la carte : un même sélecteur n'est compté qu'une fois
  // count : correspondances hors clones (choix du sélecteur) ; rawCount : dans tout le document
  var countMemo = new Map(), rawMemo = new Map();
  var rawCount = function (sel) {
    if (rawMemo.has(sel)) { return rawMemo.get(sel); }
    var n; try { n = document.querySelectorAll(sel).length; } catch (e) { n = 0; }
    rawMemo.set(sel, n);
    return n;
  };
  var count = function (sel) {
    if (!CLONE) { return rawCount(sel); }
    if (countMemo.has(sel)) { return countMemo.get(sel); }
    var n = 0;
    try { document.querySelectorAll(sel).forEach(function (e) { if (!e.closest(CLONE)) { n++; } }); } catch (e) { n = 0; }
    countMemo.set(sel, n);
    return n;
  };
  // Id ou classe « générés » (changent d'un rendu à l'autre), donc rejetés :
  //  - hash : suite hexadécimale de 8+ caractères mêlant au moins une lettre a-f
  //    et un chiffre (`a1b2c3d4e5`, `css-9f8e7d6c`) ;
  //  - nombre pur (`12345`) ou très longue suite de chiffres, 9+ (timestamps
  //    `1690000000123`) ;
  //  - préfixes de frameworks : `ember`, `react-`, `__`, `:r` (useId React).
  // Restent STABLES les identifiants d'entité `mot-123`, `mot_123`,
  // `block-195226-6`, `prettyblocks-carousel-195226`, `product-12`, et les
  // `_desktop_…` / `_mobile_…` de PrestaShop.
  var generated = function (s) {
    if (/^\d+$/.test(s) || /\d{9,}/.test(s) || /^(ember|react-|__|:r)/.test(s)) { return true; }
    return (s.match(/[0-9a-f]{8,}/ig) || []).some(function (h) { return /[a-f]/i.test(h) && /\d/.test(h); });
  };
  var stable = function (el) {
    return Array.prototype.filter.call(el.classList, function (c) { return !STATE.test(c) && !PREFIX.test(c) && !generated(c); }).slice(0, 5);
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
    if (el.id && !generated(el.id) && count('#' + esc(el.id)) === 1) { found = '#' + esc(el.id); }
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
  // ancre = sélecteur propre unique d'un ancêtre (ou 'body') ; null sinon
  var anchorOf = function (a) { return a === document.body ? 'body' : (a === document.documentElement ? null : own(a)); };
  // « descendant d'un ancêtre unique » : `ancre tag.classes` puis `ancre tag`,
  // ancêtres les plus proches d'abord, 6 niveaux au plus
  var candidates = function (el) {
    var tag = el.tagName.toLowerCase(), cls = stable(el), cands = [];
    for (var n = 1; n <= Math.min(3, cls.length); n++) {
      combos(cls, n).forEach(function (l) { cands.push(tag + l.map(function (c) { return '.' + esc(c); }).join('')); });
    }
    cands.push(tag);
    return cands;
  };
  var descendant = function (el) {
    var cands = candidates(el);
    var a = el.parentElement;
    for (var depth = 0; a && a !== document.documentElement && depth < 6; depth++, a = a.parentElement) {
      var anchor = anchorOf(a);
      if (!anchor) { continue; }
      for (var k = 0; k < cands.length; k++) {
        var sel = anchor + ' ' + cands[k];
        if (count(sel) === 1) { return sel; }
      }
    }
    return null;
  };
  // « via un repère » : `ancre repère cible`, où l'ancre est l'ancêtre unique le
  // plus proche (12 niveaux au plus) et le repère un ancêtre intermédiaire non
  // unique seul (id stable ou tag.classe), le plus proche d'abord. Ex. un bloc
  // rendu en _desktop_ et _mobile_ : `#_desktop_blocks-7 #block-7-1 h5.card-title`.
  var viaLandmark = function (el) {
    var chain = [], anchor = null, a = el.parentElement;
    for (var depth = 0; a && a !== document.documentElement && depth < 12; depth++, a = a.parentElement) {
      anchor = anchorOf(a);
      if (anchor) { break; }
      chain.push(a);
    }
    if (!anchor) { return null; }
    var cands = candidates(el);
    for (var m = 0; m < chain.length; m++) {
      var mark = chain[m], tokens = [];
      if (mark.id && !generated(mark.id)) { tokens.push('#' + esc(mark.id)); }
      stable(mark).slice(0, 3).forEach(function (c) { tokens.push(mark.tagName.toLowerCase() + '.' + esc(c)); });
      for (var t = 0; t < tokens.length; t++) {
        for (var k = 0; k < cands.length; k++) {
          var sel = anchor + ' ' + tokens[t] + ' ' + cands[k];
          if (count(sel) === 1) { return sel; }
        }
      }
    }
    return null;
  };
  // unique hors clones mais pas dans le document : on écarte explicitement les clones
  var finalize = function (sel) {
    if (!NOT_CLONE || rawCount(sel) === 1) { return sel; }
    var filtered = sel + NOT_CLONE;
    return rawCount(filtered) === 1 ? filtered : sel;
  };
  var selectorFor = function (el) {
    var mine = own(el);
    if (mine) { return mine; }
    var desc = descendant(el);
    if (desc) { return desc; }
    var via = viaLandmark(el);
    if (via) { return via; }
    var path = [], cur = el;
    while (cur && cur.parentElement) {
      var idx = 1;
      for (var sib = cur.previousElementSibling; sib; sib = sib.previousElementSibling) { if (sib.tagName === cur.tagName) { idx++; } }
      path.unshift(cur.tagName.toLowerCase() + ':nth-of-type(' + idx + ')');
      var parent = cur.parentElement;
      var anchor = parent === document.documentElement ? 'html' : anchorOf(parent);
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
    // clone de carrousel : ni lui ni ses descendants (les index parents restent cohérents)
    if (CLONE && el.matches(CLONE)) { return; }
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
    var sel = finalize(selectorFor(k.el));
    return {
      i: i, p: k.p, tag: k.el.tagName.toLowerCase(), id: k.el.id || '',
      classes: Array.prototype.slice.call(k.el.classList, 0, 8), box: k.box,
      selector: sel, matches: rawCount(sel)
    };
  }));
})
JS;

    /**
     * Applique `PROP: VALUE !important` en style inline sur les éléments des
     * sélecteurs (et leurs descendants si DEEP). Nécessaire en plus du <style>
     * injecté : pour les déclarations !important, une règle en @layer
     * (utilitaires Bootstrap de Hummingbird, ex. `.d-md-block`) l'emporte sur
     * une règle hors couche ; le style inline !important, lui, gagne toujours.
     * Valeur et priorité d'origine mémorisées dans window.__pfVisualInline[KIND]
     * pour RESTORE_INLINE. Un sélecteur invalide n'empêche pas les autres.
     * Appel : sprintf('(%s)(%s, %s, %s, %s, %s)', APPLY_INLINE, kind, sélecteurs, prop, valeur, deep) en JSON.
     */
    public const APPLY_INLINE = <<<'JS'
function (KIND, SELECTORS, PROP, VALUE, DEEP) {
  var reg = window.__pfVisualInline = window.__pfVisualInline || {};
  var saved = reg[KIND] = reg[KIND] || [];
  var apply = function (el) {
    saved.push([el, PROP, el.style.getPropertyValue(PROP), el.style.getPropertyPriority(PROP)]);
    el.style.setProperty(PROP, VALUE, 'important');
  };
  SELECTORS.forEach(function (sel) {
    try {
      document.querySelectorAll(sel).forEach(function (el) {
        apply(el);
        if (DEEP) { el.querySelectorAll('*').forEach(apply); }
      });
    } catch (e) {}
  });
  return saved.length;
}
JS;

    /**
     * Restaure exactement (valeur + priorité, ou retrait) les styles inline
     * posés par APPLY_INLINE pour KIND, dans l'ordre inverse d'application.
     * Appel : sprintf('(%s)(%s)', RESTORE_INLINE, kind) en JSON.
     */
    public const RESTORE_INLINE = <<<'JS'
function (KIND) {
  var reg = window.__pfVisualInline;
  if (!reg || !reg[KIND]) { return 0; }
  var saved = reg[KIND];
  delete reg[KIND];
  for (var i = saved.length - 1; i >= 0; i--) {
    var el = saved[i][0], prop = saved[i][1], old = saved[i][2], prio = saved[i][3];
    try {
      if (old === '') { el.style.removeProperty(prop); } else { el.style.setProperty(prop, old, prio); }
    } catch (e) {}
  }
  return saved.length;
}
JS;
}
