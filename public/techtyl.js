// Techtyl, Helfer fuer die Client-Oberflaeche. Vier kleine Aufgaben:
//  1. Design-Auswahl des Servers als data-Attribute auf <html> setzen.
//  2. Die fest eingebaute Grau-/Blau-Palette des Panels in den Stylesheets auf
//     --techtyl-* Variablen umbiegen (wirkt fuer alle Komponenten, unabhaengig von
//     den gehashten Klassennamen eines Builds).
//  3. Stabile Anker fuer das Stylesheet setzen: Bereich/Seite/Server auf <html>,
//     Zustand und Auslastung in Prozent an Serverkarten und Statistik-Kacheln.
//  4. Strg+K oeffnet die Suche, die Navigation bekommt einen gleitenden Marker.
// Es wird nie HTML aus fremden Daten erzeugt, nur Attribute, Text und CSS-Variablen.
(function () {
  var root = document.documentElement;

  /* ---------- 1 Attribute ---------- */
  var source = document.getElementById('techtyl-attributes');
  if (source) {
    try {
      var attributes = JSON.parse(source.textContent || '');
      if (attributes && typeof attributes === 'object') {
        Object.keys(attributes).forEach(function (key) {
          if (/^data-techtyl-[a-z]+$/.test(key) && /^[a-z]+$/.test(String(attributes[key]))) {
            root.setAttribute(key, String(attributes[key]));
          }
        });
      }
    } catch (error) {
      /* ohne Attribute gelten die Standardwerte des Stylesheets */
    }
  }

  // Alles Weitere gilt nur der Client-Oberflaeche (React-App), nicht dem Admin-Bereich
  if (!document.getElementById('app')) return;

  /* ---------- Schriften (liegen unter public/fonts, nichts kommt von fremden Servern) ---------- */
  (function () {
    var base = '/extensions/techtyl/fonts/';
    var faces = [
      ['Bricolage Grotesque', 'bricolage-grotesque-var', '200 800', 'font-stretch:75% 100%;'],
      ['IBM Plex Sans', 'plex-sans-400', '400', ''],
      ['IBM Plex Sans', 'plex-sans-500', '500', ''],
      ['IBM Plex Sans', 'plex-sans-600', '600 700', ''],
      ['IBM Plex Mono', 'plex-mono-400', '400', ''],
      ['IBM Plex Mono', 'plex-mono-500', '500 700', ''],
    ];
    var style = document.createElement('style');
    style.id = 'techtyl-fonts';
    style.textContent = faces
      .map(function (face) {
        return (
          "@font-face{font-family:'" + face[0] + "';src:url('" + base + face[1] + ".woff2') format('woff2');font-weight:" +
          face[2] + ';' + face[3] + 'font-style:normal;font-display:swap}'
        );
      })
      .join('');
    document.head.appendChild(style);
  })();

  /* ---------- 2 Palette ---------- */
  // "r,g,b" -> [Variable als Flaeche, Variable als Schrift/Linie]
  var palette = {};
  function tone(list, surface, ink) {
    list.split(' ').forEach(function (rgb) {
      palette[rgb] = [surface, ink || surface];
    });
  }
  // twin.macro schreibt die Grautoene des Panels als hsla(), Tailwind als rgb(): beide Schreibweisen
  tone('31,41,51 210,24%,16%', 'n900', 'line');
  tone('51,64,77 209,20%,25%', 'n800', 'line');
  tone('63,77,90 209,18%,30%', 'n700', 'line');
  tone('81,95,108 209,14%,37%', 'n600', 'line');
  tone('96,109,123 211,12%,43%', 'n500', 'edge');
  tone('123,135,147 211,10%,53%', 'n400', 'faint');
  tone('154,165,177 211,13%,65%', 'n300', 'muted');
  tone('202,209,216 210,16%,82%', 'soft');
  tone('229,232,235 245,247,250 214,15%,91% 216,33%,97%', 'text');
  tone('19,26,32', 'console');
  tone('59,130,246 37,99,235 29,78,216 9,103,210 6,182,212 8,145,178 14,116,144 21,94,117 60,231,225', 'accent', 'accent-text');
  tone('96,165,250 147,197,253 34,211,238', 'accent-hover', 'accent-text');
  tone('239,246,255 219,234,254 191,219,254 207,250,254 165,243,252', 'accent-ink');
  tone('239,68,68 220,38,38 185,28,28 255,37,58', 'bad-fill', 'bad');
  tone('248,113,113', 'bad-hover', 'bad');
  tone('254,226,226 254,202,202 254,242,242', 'bad-soft', 'bad-ink');
  tone('16,185,129 34,197,94 22,163,74', 'ok-fill', 'ok');
  tone('220,252,231 240,253,244', 'ok-ink');
  tone('234,179,8 202,138,4 245,158,11 250,204,21', 'warn-fill', 'warn');
  tone('254,252,232 113,63,18 133,77,14', 'warn-ink');

  var colorPattern = /(?:rgb|hsl)a?\(\s*([\d.]+)(?:deg)?[,\s]+([\d.]+%?)[,\s]+([\d.]+%?)\s*(?:[,/]\s*((?:[^()]|\([^()]*\))+?))?\s*\)/g;

  function remapText(text) {
    return text.replace(colorPattern, function (match, r, g, b, alpha, offset) {
      var entry = palette[r + ',' + g + ',' + b];
      if (!entry) return match;
      var before = text.slice(Math.max(0, offset - 90), offset);
      var property = /([a-z-]+)\s*:[^:;]*$/.exec(before);
      var isSurface = property && /^background/.test(property[1]);
      // Ringe und Fokus-Rahmen sollen die Akzentfarbe selbst tragen, nicht die Textvariante
      var isRing = property && /ring|shadow|border|outline/.test(property[1]);
      var name = isSurface || (isRing && /^accent/.test(entry[0])) ? entry[0] : entry[1];
      var value = 'var(--techtyl-' + name + ')';
      if (alpha === undefined || alpha === '1') return value;
      alpha = alpha.trim();
      var share = /%$/.test(alpha) ? alpha : 'calc(' + alpha + ' * 100%)';
      return 'color-mix(in srgb, ' + value + ' ' + share + ', transparent)';
    });
  }

  var nativeInsert = CSSStyleSheet.prototype.insertRule;
  var nativeDelete = CSSStyleSheet.prototype.deleteRule;

  function remapRule(sheet, index) {
    var rule = sheet.cssRules[index];
    if (!rule) return;
    var text = rule.cssText;
    if ((text.indexOf('rgb') === -1 && text.indexOf('hsl') === -1) || text.indexOf('--techtyl-') !== -1) return;
    var next = remapText(text);
    if (next === text) return;
    try {
      nativeDelete.call(sheet, index);
      nativeInsert.call(sheet, next, index);
    } catch (error) {
      try {
        nativeInsert.call(sheet, text, index);
      } catch (ignored) {
        /* Regel bleibt weg, wenn der Browser sie nicht zuruecknimmt */
      }
    }
  }

  function remapSheet(sheet) {
    if (sheet.ownerNode && sheet.ownerNode.id === 'techtyl-vars') return;
    var rules;
    try {
      rules = sheet.cssRules;
    } catch (error) {
      return;
    }
    for (var i = 0; i < rules.length; i++) remapRule(sheet, i);
  }

  function remapAll() {
    for (var i = 0; i < document.styleSheets.length; i++) remapSheet(document.styleSheets[i]);
  }

  CSSStyleSheet.prototype.insertRule = function (rule, index) {
    var at = nativeInsert.call(this, rule, index);
    try {
      remapRule(this, at);
    } catch (error) {
      /* Original-Regel bleibt bestehen */
    }
    return at;
  };

  remapAll();
  // Nachgeladene Seitenteile bringen eigene <style>-Elemente mit
  new MutationObserver(function (records) {
    for (var i = 0; i < records.length; i++) {
      for (var j = 0; j < records[i].addedNodes.length; j++) {
        var node = records[i].addedNodes[j];
        if (node.nodeName === 'STYLE' && node.sheet) remapSheet(node.sheet);
      }
    }
  }).observe(document.head, { childList: true });

  /* Schriftfarbe auf der Akzentfarbe: dunkel oder hell, je nach Helligkeit des Akzents */
  function accentInk() {
    var probe = document.createElement('i');
    probe.style.cssText = 'position:absolute;visibility:hidden;color:var(--techtyl-accent)';
    document.body.appendChild(probe);
    var parts = getComputedStyle(probe).color.match(/[\d.]+/g) || [240, 163, 59];
    probe.remove();
    var linear = parts.slice(0, 3).map(function (channel) {
      var c = Number(channel) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    var luminance = 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
    root.style.setProperty('--techtyl-accent-ink', luminance > 0.36 ? '#17130b' : '#ffffff');
    root.setAttribute('data-techtyl-accent', luminance > 0.36 ? 'hell' : 'dunkel');
    // Diagramme der Konsole (fest tuerkis, Farbton 188 Grad) auf den Farbton des Akzents drehen
    var r = Number(parts[0]) / 255;
    var g = Number(parts[1]) / 255;
    var b = Number(parts[2]) / 255;
    var max = Math.max(r, g, b);
    var spread = max - Math.min(r, g, b);
    var hue = 188;
    if (spread > 0.04) {
      if (max === r) hue = ((g - b) / spread) % 6;
      else if (max === g) hue = (b - r) / spread + 2;
      else hue = (r - g) / spread + 4;
      hue = (hue * 60 + 360) % 360;
    }
    root.style.setProperty('--techtyl-chart-turn', Math.round(hue - 188) + 'deg');
  }

  /* ---------- 3 Anker ---------- */
  var lastPath = null;
  function describeRoute() {
    var path = location.pathname.replace(/\/+$/, '') || '/';
    var server = /^\/server\/([a-z0-9]+)(?:\/([a-z0-9-]+))?/i.exec(path);
    var area = 'dashboard';
    var page = 'servers';
    if (server) {
      area = 'server';
      page = server[2] || 'console';
    } else if (/^\/account/.test(path)) {
      area = 'account';
      page = path.split('/')[2] || 'overview';
    } else if (/^\/auth/.test(path)) {
      area = 'auth';
      page = path.split('/')[2] || 'login';
    }
    root.setAttribute('data-techtyl-area', area);
    root.setAttribute('data-techtyl-page', page);
    if (server) root.setAttribute('data-techtyl-server', server[1]);
    else root.removeAttribute('data-techtyl-server');
    if (path !== lastPath) {
      lastPath = path;
      schedule();
      // aktiven Eintrag einer langen Seitenleiste in den sichtbaren Bereich holen
      setTimeout(function () {
        var active = document.querySelector('.techtyl-subnav__body a.active');
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
        schedule();
      }, 60);
    }
  }

  function describeTitle() {
    var parts = document.title.split(' | ');
    var page = parts.length > 1 ? parts[parts.length - 1] : parts[0];
    root.style.setProperty('--techtyl-title', JSON.stringify(page || ''));
    root.style.setProperty('--techtyl-server-name', JSON.stringify(parts.length > 1 ? parts.slice(0, -1).join(' | ') : ''));
  }

  ['pushState', 'replaceState'].forEach(function (method) {
    var native = history[method];
    history[method] = function () {
      var result = native.apply(this, arguments);
      describeRoute();
      return result;
    };
  });
  window.addEventListener('popstate', describeRoute);
  window.addEventListener('hashchange', describeRoute);

  var units = { bytes: 1, byte: 1, kib: 1024, mib: 1048576, gib: 1073741824, tib: 1099511627776, kb: 1000, mb: 1e6, gb: 1e9, tb: 1e12 };
  function amount(text) {
    var found = /([\d.,]+)\s*(%|[a-z]+)?/i.exec(String(text || '').replace(/^[^\d]*/, ''));
    if (!found) return null;
    var value = parseFloat(found[1].replace(',', '.'));
    if (isNaN(value)) return null;
    var unit = (found[2] || '').toLowerCase();
    if (unit === '%' || unit === '') return value;
    return units[unit] ? value * units[unit] : null;
  }

  function setMeter(block, usedText, limitText) {
    var used = amount(usedText);
    var limit = amount(limitText);
    if (used === null || limit === null || limit <= 0) {
      if (block.hasAttribute('data-techtyl-pct')) {
        block.removeAttribute('data-techtyl-pct');
        block.removeAttribute('data-techtyl-load');
        block.style.removeProperty('--techtyl-fill');
        block.style.removeProperty('--techtyl-pct-text');
      }
      return;
    }
    var share = Math.max(0, Math.min(100, (used / limit) * 100));
    var label = share >= 10 || share === 0 ? String(Math.round(share)) : share.toFixed(1);
    if (block.getAttribute('data-techtyl-pct') === label) return;
    block.setAttribute('data-techtyl-pct', label);
    block.setAttribute('data-techtyl-load', share >= 90 ? 'hoch' : share >= 75 ? 'mittel' : 'normal');
    block.style.setProperty('--techtyl-fill', (share / 100).toFixed(3));
    block.style.setProperty('--techtyl-pct-text', '"' + label + ' %"');
    block.title = usedText.trim() + ' / ' + limitText.replace(/^[^\d∞]*/, '').trim();
  }

  function enhanceStats() {
    if (root.getAttribute('data-techtyl-area') !== 'server') return;
    var blocks = document.querySelectorAll('#app .grid.grid-cols-6 > div');
    for (var i = 0; i < blocks.length; i++) {
      var holder = blocks[i].querySelector('div.truncate');
      var limit = holder && holder.querySelector('span');
      if (!holder || !limit) continue;
      var used = '';
      for (var j = 0; j < holder.childNodes.length; j++) {
        if (holder.childNodes[j].nodeType === 3) used += holder.childNodes[j].textContent;
      }
      setMeter(blocks[i], used, limit.textContent);
    }
  }

  /* ---------- 4 Marker und Suche ---------- */
  function placeMarker() {
    var tracks = document.querySelectorAll('[data-techtyl-track]');
    for (var i = 0; i < tracks.length; i++) {
      var track = tracks[i];
      var active = track.querySelector('a.active');
      if (!active || !active.offsetWidth) {
        track.removeAttribute('data-techtyl-marker');
        continue;
      }
      var outer = track.getBoundingClientRect();
      var inner = active.getBoundingClientRect();
      var x = Math.round(inner.left - outer.left + track.scrollLeft);
      var y = Math.round(inner.top - outer.top + track.scrollTop);
      var key = [x, y, Math.round(inner.width), Math.round(inner.height)].join(',');
      if (track.getAttribute('data-techtyl-marker') === key) continue;
      track.style.setProperty('--techtyl-marker-x', x + 'px');
      track.style.setProperty('--techtyl-marker-y', y + 'px');
      track.style.setProperty('--techtyl-marker-w', Math.round(inner.width) + 'px');
      track.style.setProperty('--techtyl-marker-h', Math.round(inner.height) + 'px');
      track.setAttribute('data-techtyl-marker', key);
    }
  }

  var queued = false;
  var settle = 0;
  function enhance() {
    queued = false;
    enhanceStats();
    placeMarker();
  }
  function schedule() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(enhance);
    // zweiter Durchlauf, wenn Farbuebergaenge des Panels (150 ms) abgeschlossen sind
    clearTimeout(settle);
    settle = setTimeout(enhance, 320);
  }

  function start() {
    accentInk();
    describeRoute();
    describeTitle();
    var app = document.getElementById('app');
    if (app) new MutationObserver(schedule).observe(app, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class'] });
    var title = document.querySelector('title');
    if (title) new MutationObserver(describeTitle).observe(title, { childList: true, characterData: true, subtree: true });
    // Der Designer aendert Variante und Akzent ohne Neuladen
    new MutationObserver(function () {
      accentInk();
      schedule();
    }).observe(root, { attributes: true, attributeFilter: ['data-techtyl-variant', 'data-techtyl-nav'] });
    var vars = document.getElementById('techtyl-vars');
    if (vars) new MutationObserver(accentInk).observe(vars, { childList: true, characterData: true, subtree: true });
    window.addEventListener('resize', schedule);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
    schedule();
  }

  if (document.body) start();
  else document.addEventListener('DOMContentLoaded', start);
})();
