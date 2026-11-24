// Techtyl Designer, Empfaenger in der Live-Vorschau.
// Aktiv nur, wenn diese Seite im Designer (gleiche Herkunft, Pfad des Designers) als iframe laeuft.
// Nimmt nur Nachrichten des Eltern-Fensters derselben Herkunft an, nur bekannte Schluessel mit
// geprueften Werten, und setzt daraus CSS-Variablen und data-Attribute auf <html>. Ankuendigung
// und Links gibt es als gepruefte Daten an das Dashboard weiter (Ereignis "techtyl:designer-preview").
// Es wird nie HTML erzeugt.
(function () {
  'use strict';

  var DESIGNER_PATH = '/extensions/techtyl/designer/editor';
  var parentWindow = window.parent;
  if (!parentWindow || parentWindow === window) return;
  try {
    if (parentWindow.location.origin !== window.location.origin) return;
    if (parentWindow.location.pathname.replace(/\/+$/, '') !== DESIGNER_PATH) return;
  } catch (error) {
    return; // fremde Herkunft: Zugriff verweigert
  }
  if (window.__techtylReceiver) return;
  window.__techtylReceiver = true;

  var root = document.documentElement;
  root.setAttribute('data-techtyl-preview', 'an');

  var VARIANTS = ['werft', 'papier', 'hafen', 'kiefer'];
  var NAVIGATION = ['sidebar', 'icons', 'top'];
  var LAYOUTS = ['raster', 'liste', 'kompakt'];
  var MOTION = ['aus', 'dezent', 'voll'];
  var SCENES = ['contours', 'aurora', 'stars', 'forest', 'harbor', 'grid'];
  var SCENE_FOR = { werft: 'contours', papier: 'grid', hafen: 'harbor', kiefer: 'forest' };
  var KINDS = ['info', 'neu', 'erfolg', 'warnung', 'wartung'];
  var ICONS = ['megafon', 'info', 'stern', 'rakete', 'werkzeug', 'warnung', 'herz', 'kalender', 'glocke', 'geschenk'];
  var ANNOUNCE_POSITIONS = ['karte', 'leiste', 'schwebend'];
  var WEBSITE = ['support', 'status', 'billing', 'docs', 'website'];
  var SOCIAL = ['discord', 'github', 'youtube', 'twitch', 'x', 'instagram', 'tiktok', 'mastodon', 'reddit'];
  var LINK_POSITIONS = ['oben', 'schwebend'];
  var ALIGN = ['links', 'mitte', 'rechts'];

  function oneOf(value, list) {
    return typeof value === 'string' && list.indexOf(value) !== -1 ? value : null;
  }

  function int(value, min, max) {
    return typeof value === 'number' && isFinite(value) ? Math.max(min, Math.min(max, Math.round(value))) : null;
  }

  function hex(value) {
    return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value) ? value.toLowerCase() : null;
  }

  // Wie Design::backgroundUrl: https-Adresse oder Pfad auf diesem Panel, ohne Ausbruch-Zeichen.
  function imageUrl(value) {
    if (typeof value !== 'string') return null;
    value = value.trim();
    if (value === '') return '';
    if (value.length > 500 || /["'()\\<>\s]/.test(value)) return null;
    if (value.charAt(0) === '/' && value.charAt(1) !== '/') return value;
    if (/^https:\/\//.test(value)) {
      try {
        new URL(value);
        return value;
      } catch (error) {
        return null;
      }
    }
    return null;
  }

  // Control, format and line/paragraph separator characters become spaces.
  function isControl(code) {
    return (
      code < 32 ||
      (code >= 0x7f && code <= 0x9f) ||
      (code >= 0x200b && code <= 0x200f) ||
      (code >= 0x2028 && code <= 0x202e) ||
      (code >= 0x2060 && code <= 0x206f) ||
      code === 0xfeff
    );
  }

  function text(value, max, multiline) {
    if (typeof value !== 'string') return '';
    var CR = String.fromCharCode(13);
    var LF = String.fromCharCode(10);
    var normalized = value.split(CR + LF).join(LF).split(CR).join(LF);
    var clean = '';
    for (var i = 0; i < normalized.length; i++) {
      var code = normalized.charCodeAt(i);
      clean += isControl(code) && !(multiline && code === 10) ? ' ' : normalized.charAt(i);
    }
    if (!multiline) clean = clean.replace(/ {2,}/g, ' ').trim();
    return clean.slice(0, max);
  }

  function httpUrl(value) {
    if (typeof value !== 'string' || value.length > 300 || /[\s"'<>\\`]/.test(value)) return '';
    try {
      var url = new URL(value);
      if ((url.protocol !== 'http:' && url.protocol !== 'https:') || url.username || url.password || !url.hostname) return '';
      return value;
    } catch (error) {
      return '';
    }
  }

  // Wie Clean::cssString: alles ausser A-Z, a-z, 0-9 und Leerzeichen als Hex-Folge.
  function cssString(value) {
    var out = '"';
    for (var i = 0; i < value.length; i++) {
      var code = value.codePointAt(i);
      if (code > 0xffff) i++;
      var ch = String.fromCodePoint(code);
      // the trailing space ends the escape (CSS swallows it), so a following real space survives
      out += /^[A-Za-z0-9 ]$/.test(ch) ? ch : '\\' + ('000000' + code.toString(16)).slice(-6) + ' ';
    }
    return out + '"';
  }

  function inkFor(accent) {
    var channels = [1, 3, 5].map(function (at) {
      var c = parseInt(accent.substr(at, 2), 16) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2] > 0.36;
  }

  function setVar(name, value) {
    if (value === null || value === undefined) root.style.removeProperty(name);
    else root.style.setProperty(name, value);
  }

  function applyDesign(design) {
    if (!design || typeof design !== 'object') return;
    var variant = oneOf(design.variant, VARIANTS);
    var nav = oneOf(design.navigation, NAVIGATION);
    var layout = oneOf(design.server_layout, LAYOUTS);
    var motion = oneOf(design.motion, MOTION);
    if (variant) root.setAttribute('data-techtyl-variant', variant);
    if (nav) root.setAttribute('data-techtyl-nav', nav);
    if (layout) root.setAttribute('data-techtyl-servers', layout);
    if (motion) root.setAttribute('data-techtyl-motion', motion);
    if (typeof design.banners === 'boolean') root.setAttribute('data-techtyl-banners', design.banners ? 'an' : 'aus');

    var accent = hex(design.accent);
    if (accent) {
      setVar('--techtyl-accent', accent);
      var light = inkFor(accent);
      setVar('--techtyl-accent-ink', light ? '#17130b' : '#ffffff');
      root.setAttribute('data-techtyl-accent', light ? 'hell' : 'dunkel');
    }
    var radius = int(design.radius, 0, 22);
    if (radius !== null) setVar('--techtyl-radius', radius + 'px');
    var bannerHeight = int(design.banner_height, 48, 160);
    if (bannerHeight !== null) setVar('--techtyl-banner-height', bannerHeight + 'px');
    var dim = int(design.dim, 0, 90);
    if (dim !== null) setVar('--techtyl-dim', String(dim / 100));
    var opacity = int(design.opacity, 35, 100);
    if (opacity !== null) setVar('--techtyl-opacity', opacity + '%');
    var blur = int(design.blur, 0, 24);
    if (blur !== null) setVar('--techtyl-blur', blur + 'px');

    var background = imageUrl(design.background);
    if (background !== null) setVar('--techtyl-background', background === '' ? 'none' : 'url("' + background + '")');
    var loginImage = imageUrl(design.login_image);
    if (loginImage !== null) {
      setVar('--techtyl-login-image', loginImage === '' ? 'none' : 'url("' + loginImage + '")');
      root.setAttribute('data-techtyl-loginbild', loginImage === '' ? 'aus' : 'an');
    }
    var title = text(design.login_title, 60, false);
    var subtitle = text(design.login_subtitle, 160, false);
    setVar('--techtyl-login-title', title === '' ? null : cssString(title));
    setVar('--techtyl-login-subtitle', subtitle === '' ? null : cssString(subtitle));
    root.setAttribute('data-techtyl-logintext', title !== '' || subtitle !== '' ? 'an' : 'aus');

    var scene = resolvedScene(design);
    if (scene) root.setAttribute('data-techtyl-scene', scene);
    refreshScene();
  }

  // auto wird hier aufgeloest. Ausgegeben wird nur none oder eine bekannte Szene.
  // Ein Bild ausserhalb der mitgelieferten Motive (Upload oder fremde https-Adresse) ergibt none.
  function resolvedScene(design) {
    if (!design || typeof design !== 'object' || !('scene' in design)) return null;
    var chosen = oneOf(design.scene, ['none'].concat(SCENES));
    if (chosen) return chosen;
    if (design.scene !== 'auto') return null;
    var background = imageUrl(design.background);
    var bundled = background === '' || (typeof background === 'string' && background.indexOf('/extensions/techtyl/defaults/backgrounds/') === 0);
    if (typeof background === 'string' && background !== '' && !bundled) return 'none';
    var variant = oneOf(design.variant, VARIANTS);
    return (variant && SCENE_FOR[variant]) || 'contours';
  }

  function refreshScene() {
    var engine = window.TechtylScene;
    if (!engine || typeof engine.refresh !== 'function') return;
    try {
      engine.refresh();
    } catch (error) {
      /* die Engine meldet eigene Fehler selbst */
    }
  }

  function cleanAnnouncement(value) {
    if (!value || typeof value !== 'object') return null;
    var body = text(value.text, 600, true);
    if (value.enabled !== true || body.trim() === '') return null;
    return {
      text: body,
      kind: oneOf(value.kind, KINDS) || 'info',
      icon: oneOf(value.icon, ICONS) || 'megafon',
      position: oneOf(value.position, ANNOUNCE_POSITIONS) || 'karte',
      dismissible: value.dismissible !== false,
      revision: 'vorschau',
    };
  }

  function cleanLinks(value) {
    if (!value || typeof value !== 'object') return null;
    var website = [];
    var social = [];
    (Array.isArray(value.website) ? value.website : []).slice(0, 6).forEach(function (entry) {
      if (!entry || typeof entry !== 'object') return;
      var kind = oneOf(entry.kind, WEBSITE);
      var url = httpUrl(entry.url);
      if (kind && url) website.push({ kind: kind, label: text(entry.label, 32, false), url: url });
    });
    (Array.isArray(value.social) ? value.social : []).slice(0, 8).forEach(function (entry) {
      if (!entry || typeof entry !== 'object') return;
      var network = oneOf(entry.network, SOCIAL);
      var url = httpUrl(entry.url);
      if (network && url) social.push({ network: network, url: url });
    });
    if (website.length === 0 && social.length === 0) return null;
    return {
      website: website,
      social: social,
      position: oneOf(value.position, LINK_POSITIONS) || 'oben',
      align: oneOf(value.align, ALIGN) || 'rechts',
    };
  }

  function applyContent(message) {
    var detail = Object.freeze({
      announcement: cleanAnnouncement(message.announcement),
      links: cleanLinks(message.links),
    });
    window.__techtylDesignerPreview = detail;
    try {
      window.dispatchEvent(new CustomEvent('techtyl:designer-preview', { detail: detail }));
    } catch (error) {
      /* alte Browser: das Dashboard liest beim naechsten Aufbau __techtylDesignerPreview */
    }
  }

  function tell(event, extra) {
    var message = { type: 'techtyl-preview', v: 1, event: event, path: location.pathname };
    if (extra) message.detail = String(extra).slice(0, 120);
    parentWindow.postMessage(message, window.location.origin);
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || event.source !== parentWindow) return;
    var message = event.data;
    if (!message || typeof message !== 'object' || message.type !== 'techtyl-designer' || message.v !== 1) return;
    if (message.design) applyDesign(message.design);
    if ('announcement' in message || 'links' in message) applyContent(message);
  });

  // In der Vorschau keine Wege, die die Sitzung beenden oder den Rahmen verlassen.
  var adminPage = /^\/admin(\/|$)/.test(location.pathname);
  document.addEventListener(
    'click',
    function (event) {
      var target = event.target instanceof Element ? event.target : null;
      if (!target) return;
      var logout = target.closest('button, a');
      if (logout && logout.querySelector && logout.querySelector('svg[data-icon="sign-out-alt"], svg[data-icon="right-from-bracket"]')) {
        event.preventDefault();
        event.stopPropagation();
        tell('blocked', 'logout');
        return;
      }
      var link = target.closest('a[href]');
      if (!link) return;
      var href;
      try {
        href = new URL(link.getAttribute('href'), location.href);
      } catch (error) {
        return;
      }
      var leaves =
        adminPage ||
        href.origin !== location.origin ||
        /^\/(admin|auth\/logout|extensions|api)(\/|$)/.test(href.pathname);
      if (leaves && link.getAttribute('target') !== '_blank') {
        event.preventDefault();
        event.stopPropagation();
        tell('blocked', 'link');
      }
    },
    true
  );
  if (adminPage) {
    document.addEventListener(
      'submit',
      function (event) {
        event.preventDefault();
        event.stopPropagation();
        tell('blocked', 'form');
      },
      true
    );
  }

  // Seitenwechsel innerhalb der App an den Designer melden.
  ['pushState', 'replaceState'].forEach(function (method) {
    var native = history[method];
    history[method] = function () {
      var result = native.apply(this, arguments);
      tell('path');
      return result;
    };
  });
  window.addEventListener('popstate', function () {
    tell('path');
  });

  tell('ready');
})();
