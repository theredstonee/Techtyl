// Techtyl Designer: Vollbild-Seite fuer Root-Admins.
// Baut die Oberflaeche aus den Daten in #dz-data, haelt den Entwurf, zeigt ihn live in der Vorschau
// (postMessage an receiver.js im iframe, nur an dieselbe Herkunft) und speichert per PUT.
// DOM entsteht nur ueber createElement/textContent, nie ueber HTML-Zeichenketten.
(function () {
  'use strict';

  var dataNode = document.getElementById('dz-data');
  var app = document.getElementById('dz');
  if (!dataNode || !app) return;

  var DATA;
  try {
    DATA = JSON.parse(dataNode.textContent || '{}');
  } catch (error) {
    return;
  }

  var ORIGIN = window.location.origin;
  var BASE = '/extensions/techtyl/designer';
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var OPT = DATA.options || {};
  var root = document.documentElement;
  var TEXTS = DATA.texts || {};
  var NUMBER_LOCALE = DATA.locale === 'de' ? 'de-DE' : 'en-GB';

  // Text of the active language (the server already merged English in as fallback).
  function T(key, replace) {
    var text = typeof TEXTS[key] === 'string' ? TEXTS[key] : key;
    Object.keys(replace || {}).forEach(function (name) {
      text = text.split(':' + name).join(String(replace[name]));
    });
    return text;
  }

  /* ================= Helfer ================= */

  function h(tag, props, children) {
    var el = document.createElement(tag);
    props = props || {};
    Object.keys(props).forEach(function (key) {
      var value = props[key];
      if (value === undefined || value === null || value === false) return;
      if (key === 'class') el.className = value;
      else if (key === 'text') el.textContent = value;
      else if (key === 'on') {
        Object.keys(value).forEach(function (name) {
          el.addEventListener(name, value[name]);
        });
      } else if (key === 'vars') {
        Object.keys(value).forEach(function (name) {
          el.style.setProperty(name, value[name]);
        });
      } else if (key in el && typeof value !== 'string' && key !== 'list') el[key] = value;
      else el.setAttribute(key, value === true ? '' : String(value));
    });
    append(el, children);
    return el;
  }

  function append(el, children) {
    if (children === undefined || children === null || children === false) return el;
    if (!Array.isArray(children)) children = [children];
    children.forEach(function (child) {
      if (child === undefined || child === null || child === false) return;
      if (Array.isArray(child)) append(el, child);
      else el.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    });
    return el;
  }

  var SVG_NS = 'http://www.w3.org/2000/svg';
  function s(tag, attrs, children) {
    var el = document.createElementNS(SVG_NS, tag);
    Object.keys(attrs || {}).forEach(function (key) {
      el.setAttribute(key, String(attrs[key]));
    });
    (children || []).forEach(function (child) {
      if (child) el.appendChild(child);
    });
    return el;
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function same(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function getPath(object, path) {
    return path.split('.').reduce(function (current, key) {
      return current === undefined || current === null ? undefined : current[key];
    }, object);
  }

  function setPath(object, path, value) {
    var keys = path.split('.');
    var last = keys.pop();
    var target = keys.reduce(function (current, key) {
      return current[key];
    }, object);
    target[last] = value;
  }

  /* ================= Icons (eigene Zeichnungen, 24er Raster) ================= */

  var ICONS = {
    palette: 'M12 3a9 9 0 0 0 0 18c1.1 0 1.6-.8 1.6-1.6 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.7-1.6 1.6-1.6H16a5 5 0 0 0 5-5c0-4.1-4-7.4-9-7.4z M7.5 11.5h.01 M10 7.5h.01 M14.5 7.5h.01 M17 11h.01',
    image: 'M4 5h16v14H4z M4 16l5-5 4 4 3-3 4 4 M15 9.5h.01',
    layout: 'M4 4h16v16H4z M9 4v16 M9 10h11',
    motion: 'M2 12c2-4.5 4-4.5 6 0s4 4.5 6 0 4-4.5 6 0 M5 18h14',
    login: 'M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4 M10 16l4-4-4-4 M14 12H4',
    link: 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1 M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    shield: 'M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z M9 12l2 2 4-4',
    undo: 'M9 14L4 9l5-5 M4 9h10a6 6 0 0 1 0 12h-3',
    redo: 'M15 14l5-5-5-5 M20 9H10a6 6 0 0 0 0 12h3',
    reload: 'M20 11a8 8 0 1 0-2.3 5.7 M20 4v7h-7',
    desktop: 'M3 4h18v12H3z M8 20h8 M12 16v4',
    tablet: 'M6 3h12v18H6z M11 18h2',
    phone: 'M8 3h8v18H8z M11 18h2',
    close: 'M6 6l12 12 M18 6L6 18',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    plus: 'M12 5v14 M5 12h14',
    trash: 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13',
    up: 'M6 15l6-6 6 6',
    down: 'M6 9l6 6 6-6',
    upload: 'M12 16V4 M7 9l5-5 5 5 M4 16v4h16v-4',
    keyboard: 'M3 6h18v12H3z M7 10h.01 M11 10h.01 M15 10h.01 M7 14h10',
    bold: 'M7 5h6a3.5 3.5 0 0 1 0 7H7z M7 12h7a3.5 3.5 0 0 1 0 7H7z',
    italic: 'M10 5h8 M6 19h8 M14 5l-4 14',
    eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z M12 9a3 3 0 1 0 0 6a3 3 0 0 0 0-6z',
    dashboard: 'M4 4h7v7H4z M13 4h7v4h-7z M13 10h7v10h-7z M4 13h7v7H4z',
    console: 'M4 5h16v14H4z M7 9l3 3-3 3 M12 15h5',
    admin: 'M12 3l2.2 2.2 3-.4.4 3L20 10l-1.6 2.6L20 15l-2.4 1.2-.4 3-3-.4L12 21l-2.2-2.2-3 .4-.4-3L4 15l1.6-2.4L4 10l2.4-1.2.4-3 3 .4z M12 9a3 3 0 1 0 0 6a3 3 0 0 0 0-6z',
    alert: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M12 8v5 M12 16h.01',
    ok: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M8 12.5l2.8 2.8L16.5 9.5',
    globe: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M3 12h18 M12 3c3 3 3 15 0 18 M12 3c-3 3-3 15 0 18',
    // Ankuendigung
    megafon: 'M3 10v4a1 1 0 0 0 1 1h2l5 4V5L6 9H4a1 1 0 0 0-1 1z M15 9a4 4 0 0 1 0 6 M18 6a8 8 0 0 1 0 12',
    info: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M12 11v5 M12 7.5h.01',
    stern: 'M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.6l1-5.8L3.5 9.7l5.9-.9z',
    rakete: 'M9 12c1-5 5-8 11-8 0 6-3 10-8 11z M9 12H5l3-4h4 M12 15v4l4-3v-4 M6 18c-1 1-1 3-1 3s2 0 3-1 M14.5 9.5h.01',
    werkzeug: 'M15 4a5 5 0 0 0-4.6 6.9L4 17.3V20h2.7l6.4-6.4A5 5 0 0 0 20 9l-3 3-3-3 3-3a5 5 0 0 0-2-2z',
    warnung: 'M12 4l9 16H3z M12 10v4 M12 17h.01',
    herz: 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z',
    kalender: 'M4 6h16v14H4z M4 10h16 M8 3v4 M16 3v4',
    glocke: 'M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z M10 20a2 2 0 0 0 4 0',
    geschenk: 'M4 10h16v10H4z M3 7h18v3H3z M12 7v13 M12 7c-2-4-6-3-5 0 M12 7c2-4 6-3 5 0',
    // Website-Links
    support: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M12 8a4 4 0 1 0 0 8a4 4 0 0 0 0-8z M5.6 5.6l3.6 3.6 M14.8 14.8l3.6 3.6 M18.4 5.6l-3.6 3.6 M9.2 14.8l-3.6 3.6',
    status: 'M3 12h4l2-5 4 10 2-5h6',
    billing: 'M3 6h18v12H3z M3 10h18 M7 15h4',
    docs: 'M5 4h9a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z M5 17a3 3 0 0 1 3-3h9',
    website: 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M3 12h18 M12 3c3 3 3 15 0 18 M12 3c-3 3-3 15 0 18',
    // Social
    discord: 'M8 6.5c2.6-1 5.4-1 8 0l2 9.5c-1.6 1.4-3.3 2-4.6 2l-.9-1.6 M8 6.5L6 16c1.6 1.4 3.3 2 4.6 2l.9-1.6 M9.6 12.2h.01 M14.4 12.2h.01 M8.5 15.2c2.3.9 4.7.9 7 0',
    github: 'M8 8l-4 4 4 4 M16 8l4 4-4 4 M13.5 6l-3 12',
    youtube: 'M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z M10 9l5 3-5 3z',
    twitch: 'M5 4h15v10l-4 4h-4l-3 3v-3H5z M11 8v4 M15 8v4',
    x: 'M5 5l14 14 M19 5L5 19',
    instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z M12 9a3 3 0 1 0 0 6a3 3 0 0 0 0-6z M17 7h.01',
    tiktok: 'M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5 M14 4c.5 2.5 2.5 4 5 4',
    mastodon: 'M16 12a4 4 0 1 1-1.2-2.8 M16 9v4.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1',
    reddit: 'M12 8c4.4 0 8 2.5 8 5.5S16.4 19 12 19s-8-2.5-8-5.5S7.6 8 12 8z M12 8l1.5-4.5 4 1 M9 13h.01 M15 13h.01 M9.5 16c1.5 1 3.5 1 5 0',
  };

  function svgIcon(name) {
    var svg = s('svg', { viewBox: '0 0 24 24', 'aria-hidden': 'true', class: 'dz-svg' });
    (ICONS[name] || ICONS.info).split(' M').forEach(function (part, index) {
      svg.appendChild(s('path', { d: (index === 0 ? '' : 'M') + part }));
    });
    return svg;
  }

  function icon(name, extraClass) {
    return h('span', { class: 'dz-ico' + (extraClass ? ' ' + extraClass : ''), 'aria-hidden': 'true' }, svgIcon(name));
  }

  // Platzhalter im Blade-Markup fuellen
  Array.prototype.forEach.call(document.querySelectorAll('.dz-ico[data-icon]'), function (el) {
    el.appendChild(svgIcon(el.getAttribute('data-icon')));
    el.setAttribute('aria-hidden', 'true');
  });

  /* ================= Pruefungen (wie auf dem Server) ================= */

  function httpUrl(value) {
    if (typeof value !== 'string') return false;
    value = value.trim();
    if (!value || value.length > 300 || /[\s"'<>\\`]/.test(value)) return false;
    try {
      var url = new URL(value);
      return (url.protocol === 'http:' || url.protocol === 'https:') && !url.username && !url.password && !!url.hostname;
    } catch (error) {
      return false;
    }
  }

  function imageUrlOk(value) {
    if (value === '') return true;
    if (typeof value !== 'string' || value.length > 500 || /["'()\\<>\s]/.test(value)) return false;
    if (value.charAt(0) === '/' && value.charAt(1) !== '/') return true;
    if (!/^https:\/\//.test(value)) return false;
    try {
      new URL(value);
      return true;
    } catch (error) {
      return false;
    }
  }

  function accentIsLight(hex) {
    if (!/^#[0-9a-f]{6}$/i.test(hex)) return true;
    var lin = [1, 3, 5].map(function (at) {
      var c = parseInt(hex.substr(at, 2), 16) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2] > 0.36;
  }

  /* Mini-Markdown: **fett**, *kursiv* oder _kursiv_, [Text](https://...). Alles andere bleibt Text. */
  var MD_PATTERN = /\*\*([^*\n]+?)\*\*|\*([^*\n]+?)\*|_([^_\n]+?)_|\[([^\]\n]{1,120})\]\(([^)\s]{1,300})\)/g;

  function renderInline(text, depth) {
    var nodes = [];
    var last = 0;
    var match;
    var pattern = new RegExp(MD_PATTERN.source, 'g');
    while ((match = pattern.exec(text)) !== null) {
      if (match.index > last) nodes.push(document.createTextNode(text.slice(last, match.index)));
      if (match[1] !== undefined) nodes.push(h('strong', {}, depth < 2 ? renderInline(match[1], depth + 1) : match[1]));
      else if (match[2] !== undefined || match[3] !== undefined) {
        var inner = match[2] !== undefined ? match[2] : match[3];
        nodes.push(h('em', {}, depth < 2 ? renderInline(inner, depth + 1) : inner));
      } else if (httpUrl(match[5])) {
        nodes.push(h('a', { href: match[5], target: '_blank', rel: 'noopener noreferrer' }, match[4]));
      } else {
        nodes.push(document.createTextNode(match[0]));
      }
      last = pattern.lastIndex;
    }
    if (last < text.length) nodes.push(document.createTextNode(text.slice(last)));
    return nodes;
  }

  function renderMarkdown(text) {
    var out = [];
    String(text || '')
      .split('\n')
      .forEach(function (line, index) {
        if (index > 0) out.push(h('br'));
        out = out.concat(renderInline(line, 0));
      });
    return out;
  }

  /* ================= Zustand ================= */

  var defaults = DATA.defaults;
  var motifs = Array.isArray(DATA.motifs) ? DATA.motifs : [];
  var saved = pickState(DATA.saved);
  var uploadedUrl = DATA.saved.uploaded || '';
  var SCENE_FOR = { werft: 'contours', papier: 'grid', hafen: 'harbor', kiefer: 'forest' };
  var SCENE_KEYS = ['auto', 'none', 'contours', 'aurora', 'stars', 'forest', 'harbor', 'grid'];
  // Designs ohne Szene gelten als "auto", auch wenn der gespeicherte Stand den Schluessel noch nicht hat.
  function ensureScene(design) {
    if (!design || SCENE_KEYS.indexOf(design.scene) === -1) {
      if (design) design.scene = 'auto';
    }
  }
  ensureScene(defaults && defaults.design);
  ensureScene(saved.design);
  var state = clone(saved);
  var undoStack = [];
  var redoStack = [];
  var coalesce = { key: null, time: 0 };
  var binds = [];

  function pickState(source) {
    return {
      design: clone(source.design),
      limits: clone(source.limits),
      announcement: clone(source.announcement),
      links: clone(source.links),
    };
  }

  function get(path) {
    return getPath(state, path);
  }

  // Aenderung mit Rueckgaengig-Schritt. Regler-Bewegungen auf demselben Schluessel werden
  // innerhalb kurzer Zeit zu einem Schritt zusammengefasst.
  function set(path, value, merge) {
    if (same(get(path), value)) return;
    var now = Date.now();
    if (!(merge && coalesce.key === path && now - coalesce.time < 900)) {
      undoStack.push(JSON.stringify(state));
      if (undoStack.length > 120) undoStack.shift();
      redoStack = [];
    }
    coalesce = { key: merge ? path : null, time: now };
    setPath(state, path, clone(value));
    changed();
  }

  function setMany(changes) {
    undoStack.push(JSON.stringify(state));
    redoStack = [];
    coalesce = { key: null, time: 0 };
    changes.forEach(function (change) {
      setPath(state, change[0], clone(change[1]));
    });
    changed();
  }

  function undo() {
    if (!undoStack.length) return;
    redoStack.push(JSON.stringify(state));
    state = JSON.parse(undoStack.pop());
    coalesce = { key: null, time: 0 };
    changed(true);
  }

  function redo() {
    if (!redoStack.length) return;
    undoStack.push(JSON.stringify(state));
    state = JSON.parse(redoStack.pop());
    coalesce = { key: null, time: 0 };
    changed(true);
  }

  function bind(fn) {
    binds.push(fn);
    fn();
  }

  var syncQueued = false;
  function changed(full) {
    if (full) renderCategory(false);
    if (syncQueued) return;
    syncQueued = true;
    requestAnimationFrame(function () {
      syncQueued = false;
      binds.forEach(function (fn) {
        fn();
      });
      applyChrome();
      updateSavebar();
      updateCategoryMarks();
      sendToFrame();
    });
  }

  function dirtyPaths() {
    var paths = [];
    ['design', 'limits', 'announcement', 'links'].forEach(function (section) {
      Object.keys(Object.assign({}, saved[section], state[section])).forEach(function (key) {
        if (!same(saved[section][key], state[section][key])) paths.push(section + '.' + key);
      });
    });
    return paths;
  }

  function isDirty() {
    return dirtyPaths().length > 0;
  }

  /* ================= Bereiche ================= */

  var CATEGORIES = [
    {
      key: 'stil',
      label: T('cat_style'),
      icon: 'palette',
      lead: T('lead_style'),
      paths: ['design.variant', 'design.accent', 'design.radius'],
      render: renderStyle,
    },
    {
      key: 'hintergrund',
      label: T('cat_background'),
      title: T('title_background'),
      icon: 'image',
      lead: T('lead_background'),
      paths: ['design.background', 'design.dim', 'design.opacity', 'design.blur', 'design.scene'],
      render: renderBackground,
    },
    {
      key: 'layout',
      label: T('cat_layout'),
      icon: 'layout',
      lead: T('lead_layout'),
      paths: ['design.navigation', 'design.server_layout', 'design.banners', 'design.banner_height'],
      render: renderLayout,
    },
    {
      key: 'bewegung',
      label: T('cat_motion'),
      icon: 'motion',
      lead: T('lead_motion'),
      paths: ['design.motion'],
      render: renderMotion,
    },
    {
      key: 'login',
      label: T('cat_login'),
      title: T('title_login'),
      icon: 'login',
      lead: T('lead_login'),
      paths: ['design.login_image', 'design.login_title', 'design.login_subtitle'],
      render: renderLogin,
      preview: 'login',
    },
    {
      key: 'links',
      label: T('cat_links'),
      icon: 'link',
      lead: T('lead_links'),
      paths: ['links.website', 'links.social', 'links.position', 'links.align'],
      render: renderLinks,
      preview: 'dashboard',
    },
    {
      key: 'ankuendigung',
      label: T('cat_announcement'),
      title: T('title_announcement'),
      icon: 'megafon',
      lead: T('lead_announcement'),
      paths: ['announcement.enabled', 'announcement.text', 'announcement.kind', 'announcement.icon', 'announcement.position', 'announcement.dismissible'],
      render: renderAnnouncement,
      preview: 'dashboard',
    },
    {
      key: 'rechte',
      label: T('cat_rights'),
      title: T('title_rights'),
      icon: 'shield',
      lead: T('lead_rights'),
      paths: ['limits.user_appearance', 'limits.user_layout', 'limits.user_background', 'limits.user_banners', 'limits.max_upload_kb'],
      render: renderRights,
    },
  ];

  var current = CATEGORIES[0];
  var catButtons = {};
  var catsNode = document.getElementById('dz-cats');
  var catMarker = h('span', { class: 'dz-cat-marker', 'aria-hidden': 'true' });
  catsNode.appendChild(catMarker);

  CATEGORIES.forEach(function (category, index) {
    var button = h(
      'button',
      {
        type: 'button',
        class: 'dz-cat',
        role: 'tab',
        id: 'dz-cat-' + category.key,
        'aria-selected': 'false',
        'aria-controls': 'dz-content',
        tabindex: '-1',
        title: (category.title || category.label.replace(/­/g, '')) + ' (Alt+' + (index + 1) + ')',
        on: {
          click: function () {
            selectCategory(category, true);
          },
          keydown: function (event) {
            var step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
            if (event.key === 'Home') step = -index;
            if (event.key === 'End') step = CATEGORIES.length - 1 - index;
            if (step === undefined) return;
            event.preventDefault();
            var next = CATEGORIES[(index + step + CATEGORIES.length) % CATEGORIES.length];
            selectCategory(next, true);
            catButtons[next.key].focus();
          },
        },
      },
      [icon(category.icon), h('span', { text: category.label }), h('span', { class: 'dz-cat-dot', 'aria-hidden': 'true' })]
    );
    catButtons[category.key] = button;
    catsNode.appendChild(button);
  });

  function placeCatMarker() {
    var button = catButtons[current.key];
    if (!button) return;
    catsNode.style.setProperty('--dz-marker-y', button.offsetTop + button.offsetHeight / 2 - 15 + 'px');
    catsNode.style.setProperty('--dz-marker-x', button.offsetLeft + 'px');
    catsNode.style.setProperty('--dz-marker-w', button.offsetWidth + 'px');
  }

  function updateCategoryMarks() {
    var dirty = dirtyPaths();
    CATEGORIES.forEach(function (category) {
      var isDirtyCat = category.paths.some(function (path) {
        return dirty.indexOf(path) !== -1;
      });
      catButtons[category.key].setAttribute('data-dirty', isDirtyCat ? 'ja' : 'nein');
    });
  }

  var titleNode = document.getElementById('dz-title');
  var contentNode = document.getElementById('dz-content');
  var scrollNode = document.getElementById('dz-scroll');

  function selectCategory(category, animate) {
    var switched = current !== category;
    current = category;
    CATEGORIES.forEach(function (item) {
      var active = item === category;
      catButtons[item.key].setAttribute('aria-selected', active ? 'true' : 'false');
      catButtons[item.key].setAttribute('tabindex', active ? '0' : '-1');
    });
    contentNode.setAttribute('aria-labelledby', 'dz-cat-' + category.key);
    titleNode.textContent = category.title || category.label;
    placeCatMarker();
    if (switched) scrollNode.scrollTop = 0;
    renderCategory(animate);
    if (category.preview && switched && previewPage !== category.preview && !(category.preview === 'dashboard' && previewPage === 'console')) {
      setPreviewPage(category.preview);
    }
    try {
      sessionStorage.setItem('techtyl-designer-cat', category.key);
    } catch (error) {
      /* ohne Speicher startet der Designer beim ersten Bereich */
    }
  }

  function renderCategory(animate) {
    binds = [];
    contentNode.textContent = '';
    contentNode.removeAttribute('data-enter');
    var blocks = [h('p', { class: 'dz-lead', text: current.lead })].concat(current.render());
    blocks.push(
      h('div', { class: 'dz-reset-area' }, [
        h(
          'button',
          {
            type: 'button',
            class: 'dz-btn dz-btn-ghost dz-btn-small',
            on: {
              click: function () {
                resetCategory(current);
              },
            },
          },
          [icon('undo'), T('reset_area')]
        ),
      ])
    );
    blocks.forEach(function (block, index) {
      block.style.setProperty('--i', String(index));
      contentNode.appendChild(block);
    });
    if (animate) {
      void contentNode.offsetWidth;
      contentNode.setAttribute('data-enter', 'ja');
    }
  }

  function resetCategory(category) {
    var changes = category.paths
      .map(function (path) {
        return [path, getPath(defaults, path)];
      })
      .filter(function (change) {
        return change[1] !== undefined && !same(get(change[0]), change[1]);
      });
    if (!changes.length) {
      toast('info', T('already_default_title'), T('already_default_text'));
      return;
    }
    setMany(changes);
    renderCategory(false);
    toast('info', T('reset_title'), T('reset_text'));
  }

  /* ================= Bausteine ================= */

  function group(label, children, extra) {
    return h('section', { class: 'dz-group' }, [
      h('div', { class: 'dz-group-head' }, [h('h2', { class: 'dz-label', text: label }), extra || null]),
      children,
    ]);
  }

  function hint(text) {
    return h('p', { class: 'dz-hint', text: text });
  }

  // Auswahl-Kacheln. Standard ist eine Radiogruppe. Mit pressed: Umschaltflaechen (aria-pressed),
  // ebenfalls nur eine gewaehlt, Pfeiltasten wechseln die Auswahl.
  function tiles(config) {
    var pressed = !!config.pressed;
    var stateAttr = pressed ? 'aria-pressed' : 'aria-checked';
    var wrap = h('div', {
      class: 'dz-tiles',
      role: pressed ? 'group' : 'radiogroup',
      'aria-label': config.label,
      vars: { '--cols': String(config.cols || 2) },
    });
    var buttons = config.options.map(function (option, index) {
      var button = h(
        'button',
        {
          type: 'button',
          class: 'dz-tile' + (config.iconOnly ? ' dz-tile-icon' : config.compact ? ' dz-tile-compact' : ''),
          title: config.iconOnly ? option.title : null,
          role: pressed ? null : 'radio',
          'aria-checked': pressed ? null : 'false',
          'aria-pressed': pressed ? 'false' : null,
          disabled: option.disabled || false,
          on: {
            click: function () {
              if (!option.disabled) config.pick(option.value, option);
            },
            keydown: function (event) {
              var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
              if (step === undefined) return;
              event.preventDefault();
              var enabled = config.options.filter(function (item) {
                return !item.disabled;
              });
              var at = enabled.indexOf(option);
              var next = enabled[(at + step + enabled.length) % enabled.length];
              config.pick(next.value, next);
              buttons[config.options.indexOf(next)].focus();
            },
          },
        },
        [
          option.art ? h('span', { class: 'dz-tile-art' }, option.art()) : option.iconName ? icon(option.iconName) : null,
          h('span', { class: 'dz-tile-text' + (config.iconOnly ? ' dz-sr' : '') }, [
            h('span', { class: 'dz-tile-title', text: option.title }),
            option.desc ? h('span', { class: 'dz-tile-desc', text: option.desc }) : null,
          ]),
          h('span', { class: 'dz-tile-check', 'aria-hidden': 'true' }, icon('check')),
          option.badge ? h('span', { class: 'dz-tile-badge', text: option.badge }) : null,
        ]
      );
      if (option.setup) option.setup(button);
      wrap.appendChild(button);
      return button;
    });
    bind(function () {
      var value = config.value();
      var found = false;
      config.options.forEach(function (option, index) {
        var on = same(option.value, value) && !found;
        if (on) found = true;
        buttons[index].setAttribute(stateAttr, on ? 'true' : 'false');
      });
      // Fokus-Reihenfolge: nur die gewaehlte (sonst erste freie) Kachel ist per Tab erreichbar
      var focusIndex = config.options.findIndex(function (option, index) {
        return buttons[index].getAttribute(stateAttr) === 'true';
      });
      if (focusIndex === -1) {
        focusIndex = config.options.findIndex(function (option) {
          return !option.disabled;
        });
      }
      buttons.forEach(function (button, index) {
        button.setAttribute('tabindex', index === focusIndex ? '0' : '-1');
      });
    });
    return wrap;
  }

  function slider(config) {
    var input = h('input', {
      type: 'range',
      class: 'dz-range',
      min: String(config.min),
      max: String(config.max),
      step: String(config.step || 1),
      'aria-label': config.label,
      on: {
        input: function () {
          set(config.path, Number(input.value), true);
        },
      },
    });
    var chip = h('output', { class: 'dz-chip-value', 'aria-hidden': 'true' });
    var row = h('div', { class: 'dz-slider' }, [
      h('div', { class: 'dz-slider-label' }, [h('span', { text: config.label }), config.note ? h('small', { text: config.note }) : null]),
      input,
      chip,
    ]);
    bind(function () {
      var value = Number(get(config.path));
      if (Number(input.value) !== value) input.value = String(value);
      var disabled = config.disabled ? config.disabled() : false;
      input.disabled = disabled;
      input.style.setProperty('--fill', ((value - config.min) / (config.max - config.min)) * 100 + '%');
      var text = config.format ? config.format(value) : String(value);
      chip.textContent = text;
      input.setAttribute('aria-valuetext', text);
    });
    return row;
  }

  function switchRow(config) {
    var toggle = h('button', {
      type: 'button',
      class: 'dz-switch',
      role: 'switch',
      'aria-checked': 'false',
      'aria-label': config.title,
    });
    var row = h(
      'div',
      {
        class: 'dz-switch-row',
        on: {
          click: function () {
            set(config.path, !get(config.path));
          },
        },
      },
      [
        h('div', { class: 'dz-switch-text' }, [h('strong', { text: config.title }), config.desc ? h('span', { text: config.desc }) : null]),
        toggle,
      ]
    );
    bind(function () {
      var on = !!get(config.path);
      toggle.setAttribute('aria-checked', on ? 'true' : 'false');
      row.setAttribute('data-on', on ? 'ja' : 'nein');
    });
    return row;
  }

  function textField(config) {
    var id = 'dz-f-' + config.path.replace(/\./g, '-');
    var count = h('span', { class: 'dz-count', 'aria-live': 'off' });
    var input = h(config.multiline ? 'textarea' : 'input', {
      class: config.multiline ? 'dz-textarea' : 'dz-input',
      id: id,
      type: config.multiline ? null : 'text',
      maxlength: String(config.max),
      placeholder: config.placeholder || '',
      autocomplete: 'off',
      spellcheck: config.multiline ? 'true' : 'false',
      rows: config.multiline ? String(config.rows || 4) : null,
      on: {
        input: function () {
          set(config.path, input.value, true);
        },
      },
    });
    bind(function () {
      var value = String(get(config.path) || '');
      if (document.activeElement !== input && input.value !== value) input.value = value;
      count.textContent = value.length + ' / ' + config.max;
      count.setAttribute('data-near', value.length > config.max * 0.9 ? 'ja' : 'nein');
    });
    var field = h('div', { class: 'dz-field' }, [
      h('label', { class: 'dz-field-label', for: id }, [h('span', { text: config.label }), count]),
      config.before || null,
      input,
      config.hint ? hint(config.hint) : null,
    ]);
    field.input = input;
    return field;
  }

  /* ================= Skizzen ================= */


  function sketch(variant, shapes, extraClass, attrs) {
    var svg = s('svg', Object.assign({ viewBox: '0 0 160 100', 'aria-hidden': 'true', class: 'dz-sk' + (extraClass ? ' ' + extraClass : '') }, attrs || {}));
    if (variant) svg.setAttribute('data-variant', variant);
    svg.appendChild(s('rect', { x: 0, y: 0, width: 160, height: 100, class: 's-bg' }));
    shapes.forEach(function (shape) {
      svg.appendChild(s(shape[0], shape[1]));
    });
    return svg;
  }

  function r(x, y, w, hh, cls, rx) {
    return ['rect', { x: x, y: y, width: w, height: hh, rx: rx === undefined ? 2 : rx, class: cls }];
  }

  function card(x, y, w, hh, banner) {
    var out = [r(x, y, w, hh, 's-surface', 3)];
    if (banner) out.push(r(x, y, w, Math.round(hh * 0.38), 's-accent-soft', 3));
    out.push(r(x + 4, y + hh * (banner ? 0.5 : 0.22), w * 0.55, 3, 's-text', 1.5));
    out.push(r(x + 4, y + hh * (banner ? 0.68 : 0.45), w * 0.35, 2.5, 's-muted', 1.2));
    out.push(r(x + 4, y + hh - 7, w - 8, 2, 's-line', 1));
    return out;
  }

  function variantArt(variant) {
    return function () {
      var shapes = [r(0, 0, 36, 100, 's-surface', 0), r(6, 8, 18, 5, 's-text', 1.5), r(6, 24, 24, 5, 's-accent', 1.5), r(6, 33, 20, 4, 's-muted', 1.5), r(6, 41, 22, 4, 's-muted', 1.5), r(6, 49, 16, 4, 's-muted', 1.5)];
      shapes = shapes.concat([r(44, 9, 46, 6, 's-text', 2), r(132, 8, 20, 8, 's-accent', 3)]);
      shapes = shapes.concat(card(44, 24, 52, 34, true), card(102, 24, 52, 34, true), card(44, 64, 52, 30, false), card(102, 64, 52, 30, false));
      return sketch(variant, shapes);
    };
  }

  function navArt(kind) {
    return function () {
      var shapes;
      if (kind === 'sidebar') {
        shapes = [r(0, 0, 40, 100, 's-surface', 0), r(6, 8, 22, 5, 's-text', 1.5), r(6, 22, 28, 5, 's-accent', 1.5), r(6, 31, 24, 4, 's-muted', 1.5), r(6, 39, 26, 4, 's-muted', 1.5), r(6, 52, 18, 3, 's-line', 1.5), r(6, 59, 24, 4, 's-muted', 1.5)];
        shapes = shapes.concat(card(48, 10, 50, 38, true), card(104, 10, 50, 38, true), card(48, 54, 50, 38, true), card(104, 54, 50, 38, true));
      } else if (kind === 'icons') {
        shapes = [r(0, 0, 16, 100, 's-surface', 0), r(4, 8, 8, 8, 's-text', 2), r(4, 26, 8, 8, 's-accent', 2), r(4, 40, 8, 8, 's-muted', 2), r(4, 54, 8, 8, 's-muted', 2)];
        shapes = shapes.concat(card(24, 10, 62, 38, true), card(92, 10, 62, 38, true), card(24, 54, 62, 38, true), card(92, 54, 62, 38, true));
      } else {
        shapes = [r(0, 0, 160, 16, 's-surface', 0), r(6, 5, 16, 6, 's-text', 1.5), r(32, 6, 18, 4, 's-accent', 1.5), r(56, 6, 18, 4, 's-muted', 1.5), r(80, 6, 18, 4, 's-muted', 1.5), r(140, 5, 14, 6, 's-muted', 3)];
        shapes = shapes.concat(card(8, 24, 70, 34, true), card(84, 24, 68, 34, true), card(8, 63, 70, 32, true), card(84, 63, 68, 32, true));
      }
      return sketch(null, shapes);
    };
  }

  function serversArt(kind) {
    return function () {
      var shapes = [];
      if (kind === 'raster') {
        shapes = shapes.concat(card(10, 10, 66, 38, true), card(84, 10, 66, 38, true), card(10, 54, 66, 38, true), card(84, 54, 66, 38, true));
      } else if (kind === 'liste') {
        [10, 32, 54, 76].forEach(function (y) {
          shapes.push(r(10, y, 140, 16, 's-surface', 3), r(10, y, 34, 16, 's-accent-soft', 3), r(50, y + 4, 34, 3, 's-text', 1.5), r(50, y + 10, 22, 2.5, 's-muted', 1.2), r(104, y + 7, 40, 2.5, 's-line', 1.2));
        });
      } else {
        for (var row = 0; row < 3; row++) {
          for (var col = 0; col < 3; col++) {
            shapes.push(r(10 + col * 48, 10 + row * 28, 44, 24, 's-surface', 3), r(10 + col * 48, 10 + row * 28, 44, 7, 's-accent-soft', 3), r(14 + col * 48, 21 + row * 28, 24, 3, 's-text', 1.5));
          }
        }
      }
      return sketch(null, shapes);
    };
  }

  function bannerArt(on) {
    return function () {
      var shapes = [r(20, 14, 120, 72, 's-surface', 5)];
      if (on) shapes.push(r(20, 14, 120, 30, 's-accent-soft', 5), ['path', { d: 'M20 44 L52 30 L74 38 L100 24 L140 40 L140 44 Z', class: 's-accent' }]);
      var top = on ? 52 : 26;
      shapes.push(r(28, top, 58, 5, 's-text', 2), r(28, top + 10, 36, 3.5, 's-muted', 1.5), r(28, 74, 104, 3, 's-line', 1.5));
      return sketch(null, shapes);
    };
  }

  function motionArt(level) {
    return function () {
      var svg = sketch(null, [], 'dz-motion-demo', { 'data-level': level });
      [18, 62, 106].forEach(function (x, index) {
        var g = s('g', { class: 'm-card m-' + (index + 1) });
        g.appendChild(s('rect', { x: x, y: 22, width: 38, height: 44, rx: 4, class: 's-surface' }));
        g.appendChild(s('rect', { x: x, y: 22, width: 38, height: 14, rx: 4, class: 's-accent-soft' }));
        g.appendChild(s('rect', { x: x + 4, y: 42, width: 22, height: 3.5, rx: 1.5, class: 's-text' }));
        g.appendChild(s('rect', { x: x + 4, y: 50, width: 14, height: 3, rx: 1.5, class: 's-muted' }));
        svg.appendChild(g);
      });
      svg.appendChild(s('path', { d: 'M18 82 H54 L60 74 L68 90 L74 82 H142', class: 'm-pulse' }));
      return svg;
    };
  }

  function announceArt(position) {
    return function () {
      var shapes = [r(0, 0, 30, 100, 's-surface', 0), r(5, 8, 18, 4, 's-text', 1.5), r(5, 20, 20, 4, 's-muted', 1.5), r(5, 28, 16, 4, 's-muted', 1.5)];
      var contentTop = 10;
      if (position === 'leiste') {
        shapes.push(r(30, 0, 130, 10, 's-accent', 0), r(70, 3.5, 50, 3, 's-bg', 1.5));
        contentTop = 18;
      } else if (position === 'karte') {
        shapes.push(r(38, 10, 114, 16, 's-raise', 3), r(38, 10, 3, 16, 's-accent', 1.5), r(46, 14, 8, 8, 's-accent-soft', 2), r(58, 15.5, 60, 3, 's-text', 1.5), r(58, 20.5, 40, 2.5, 's-muted', 1.2));
        contentTop = 32;
      }
      shapes = shapes.concat(card(38, contentTop, 54, 30, true), card(98, contentTop, 54, 30, true));
      if (position === 'schwebend') {
        shapes.push(r(96, 70, 58, 24, 's-raise', 4), r(96, 70, 3, 24, 's-accent', 1.5), r(104, 76, 8, 8, 's-accent-soft', 2), r(116, 77, 32, 3, 's-text', 1.5), r(116, 83, 22, 2.5, 's-muted', 1.2));
      }
      return sketch(null, shapes);
    };
  }

  function linksArt(position, align) {
    return function () {
      var shapes = [r(0, 0, 30, 100, 's-surface', 0), r(5, 8, 18, 4, 's-text', 1.5), r(5, 20, 20, 4, 's-muted', 1.5)];
      var widths = [20, 16, 8, 8];
      var total = widths.reduce(function (a, b) {
        return a + b + 4;
      }, -4);
      var area = position === 'oben' ? [38, 152] : [38, 152];
      var start = align === 'links' ? area[0] : align === 'mitte' ? (area[0] + area[1] - total) / 2 : area[1] - total;
      var y = position === 'oben' ? 10 : 82;
      if (position === 'schwebend') {
        shapes = shapes.concat(card(38, 10, 54, 30, true), card(98, 10, 54, 30, true), card(38, 46, 54, 28, false));
        shapes.push(r(start - 4, y - 4, total + 8, 14, 's-raise', 7));
      } else {
        shapes = shapes.concat(card(38, 26, 54, 32, true), card(98, 26, 54, 32, true), card(38, 64, 54, 30, false));
      }
      widths.forEach(function (w, index) {
        shapes.push(r(start, y, w, 6, index < 2 ? 's-line' : 's-accent', 3));
        start += w + 4;
      });
      return sketch(null, shapes);
    };
  }

  function solidArt() {
    return sketch(null, [r(14, 14, 132, 72, 's-surface', 6), r(24, 26, 60, 6, 's-text', 2), r(24, 38, 40, 4, 's-muted', 2)]);
  }

  function patternArt() {
    var shapes = [];
    for (var i = 1; i < 7; i++) {
      shapes.push(['circle', { cx: 128, cy: 14, r: i * 14, class: 's-stroke' }]);
    }
    shapes.push(r(14, 62, 18, 6, 's-accent', 2), r(14, 74, 70, 9, 's-text', 2));
    return sketch(null, shapes);
  }

  function imageArt(url) {
    return function () {
      return h('img', { src: url, alt: '', loading: 'lazy', decoding: 'async', draggable: 'false' });
    };
  }

  function sceneShapes(kind) {
    if (kind === 'contours') {
      var rings = [];
      for (var i = 1; i <= 5; i++) {
        rings.push(['ellipse', { cx: 80, cy: 118, rx: 18 * i, ry: 9 * i, class: i % 2 === 0 ? 's-accent-stroke' : 's-stroke' }]);
      }
      return rings;
    }
    if (kind === 'aurora') {
      return [
        ['path', { d: 'M0 34 C40 8 78 58 118 24 C140 10 150 20 160 16 L160 0 L0 0 Z', class: 's-accent-soft' }],
        ['path', { d: 'M0 52 C46 28 90 72 160 40 L160 0 L0 0 Z', class: 's-muted' }],
        ['path', { d: 'M0 74 C50 48 100 86 160 60 L160 100 L0 100 Z', class: 's-surface' }],
      ];
    }
    if (kind === 'stars') {
      var dots = [[18, 18, 1.1], [36, 12, 0.7], [58, 22, 1.4], [82, 10, 0.8], [108, 20, 1.1], [136, 14, 1.5], [24, 40, 0.7], [48, 36, 1], [74, 44, 0.8], [98, 34, 1.3], [124, 46, 0.8], [148, 32, 0.7], [30, 64, 1.1], [62, 70, 0.8], [90, 60, 1.4], [118, 72, 0.9], [146, 58, 1]];
      return dots.map(function (dot, index) {
        return ['circle', { cx: dot[0], cy: dot[1], r: dot[2], class: index % 5 === 0 ? 's-accent' : 's-text' }];
      });
    }
    if (kind === 'forest') {
      var shapes = [['rect', { x: 0, y: 78, width: 160, height: 22, class: 's-surface' }]];
      [[22, 80, 22], [46, 86, 30], [72, 76, 20], [98, 88, 34], [126, 78, 24], [148, 84, 18]].forEach(function (tree) {
        var x = tree[0];
        var y = tree[1];
        var height = tree[2];
        var half = Math.round(height * 0.42);
        shapes.push(['polygon', { points: x - half + ',' + y + ' ' + (x + half) + ',' + y + ' ' + x + ',' + (y - height), class: 's-surface' }]);
        shapes.push(['polygon', { points: x - Math.round(half * 0.62) + ',' + (y - Math.round(height * 0.38)) + ' ' + (x + Math.round(half * 0.62)) + ',' + (y - Math.round(height * 0.38)) + ' ' + x + ',' + (y - height - 8), class: 's-accent-soft' }]);
      });
      return shapes;
    }
    if (kind === 'harbor') {
      return [
        ['rect', { x: 0, y: 58, width: 160, height: 42, class: 's-surface' }],
        ['path', { d: 'M0 66 C30 58 54 74 84 64 C114 54 136 70 160 62', class: 's-stroke' }],
        ['path', { d: 'M0 82 C40 74 72 88 112 78 C132 74 146 80 160 76', class: 's-stroke' }],
        ['rect', { x: 64, y: 40, width: 28, height: 20, rx: 1, class: 's-raise' }],
        ['rect', { x: 68, y: 44, width: 5, height: 4, class: 's-accent' }],
        ['rect', { x: 76, y: 44, width: 5, height: 4, class: 's-accent-soft' }],
        ['rect', { x: 84, y: 44, width: 5, height: 4, class: 's-accent' }],
        ['circle', { cx: 24, cy: 52, r: 1.6, class: 's-accent' }],
        ['circle', { cx: 128, cy: 22, r: 5, class: 's-muted' }],
      ];
    }
    if (kind === 'grid') {
      var grid = [];
      var step;
      for (step = 0; step <= 160; step += 20) grid.push(['line', { x1: step, y1: 0, x2: step, y2: 100, class: 's-stroke' }]);
      for (step = 0; step <= 100; step += 20) grid.push(['line', { x1: 0, y1: step, x2: 160, y2: step, class: 's-stroke' }]);
      grid.push(['circle', { cx: 80, cy: 50, r: 2.6, class: 's-accent' }]);
      return grid;
    }
    return [];
  }

  function sceneArt(kind) {
    return function () {
      var variant = get('design.variant');
      if (!SCENE_FOR[variant]) variant = 'werft';
      var drawn = kind === 'auto' ? SCENE_FOR[variant] : kind;
      return h('span', { class: 'dz-scene-art' }, sketch(variant, sceneShapes(drawn)));
    };
  }

  /* Background: '' = included image of the active variant, 'none' = no image, otherwise the address. */
  function variantDefaultUrl(variant) {
    var found = motifs.filter(function (motif) {
      return motif.variant === variant;
    })[0];
    return found ? found.url : '';
  }

  function resolvedBackground() {
    var value = state.design.background;
    if (value === 'none') return '';
    if (value === '') return variantDefaultUrl(state.design.variant);
    return value;
  }

  // Wie Design::resolveScene: auto folgt der Variante, ein Bild ausserhalb der Motive wird none.
  function previewScene() {
    var scene = state.design.scene;
    if (scene === 'none' || SCENE_KEYS.indexOf(scene) > 1) return scene;
    var background = resolvedBackground();
    var bundled = background === '' || motifs.some(function (motif) {
      return motif.url === background;
    });
    if (!bundled) return 'none';
    return SCENE_FOR[state.design.variant] || 'contours';
  }

  /* ================= Bereich: Stil ================= */

  var SWATCHES = ['#f0a33b', '#ef6f5e', '#e86a9a', '#a885f2', '#5aa9f0', '#2ec4b6', '#7cc48a', '#b8d44a', '#e8c35a', '#c8794a', '#9fb2c4', '#e9f0ee'].map(function (hex, index) {
    return [hex, T('swatch_' + index)];
  });

  function renderStyle() {
    var variantTiles = tiles({
      label: T('group_variant'),
      cols: 2,
      options: (OPT.variants || []).map(function (variant) {
        return { value: variant, title: T('variant_' + variant), desc: T('variant_' + variant + '_desc'), art: variantArt(variant) };
      }),
      value: function () {
        return get('design.variant');
      },
      pick: function (value) {
        set('design.variant', value);
      },
    });

    var swatchWrap = h('div', { class: 'dz-swatches', role: 'radiogroup', 'aria-label': T('accent_suggestions') });
    var swatchButtons = SWATCHES.map(function (swatch, index) {
      var button = h('button', {
        type: 'button',
        class: 'dz-swatch',
        role: 'radio',
        'aria-checked': 'false',
        'aria-label': swatch[1],
        title: swatch[1] + ' ' + swatch[0],
        vars: { '--sw': swatch[0] },
        on: {
          click: function () {
            set('design.accent', swatch[0]);
          },
          keydown: function (event) {
            var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
            if (step === undefined) return;
            event.preventDefault();
            var next = (index + step + SWATCHES.length) % SWATCHES.length;
            set('design.accent', SWATCHES[next][0]);
            swatchButtons[next].focus();
          },
        },
      });
      swatchWrap.appendChild(button);
      return button;
    });

    var native = h('input', {
      type: 'color',
      'aria-label': T('accent_free'),
      on: {
        input: function () {
          set('design.accent', native.value.toLowerCase(), true);
        },
      },
    });
    var hexInput = h('input', {
      class: 'dz-input dz-input-mono',
      type: 'text',
      maxlength: '7',
      spellcheck: 'false',
      autocomplete: 'off',
      'aria-label': T('accent_hex'),
      on: {
        input: function () {
          var value = hexInput.value.trim();
          if (value.charAt(0) !== '#') value = '#' + value;
          var valid = /^#[0-9a-f]{6}$/i.test(value);
          hexInput.setAttribute('aria-invalid', valid ? 'false' : 'true');
          if (valid) set('design.accent', value.toLowerCase(), true);
        },
        blur: function () {
          hexInput.value = get('design.accent');
          hexInput.setAttribute('aria-invalid', 'false');
        },
      },
    });
    var contrast = h('span', { class: 'dz-contrast' });
    bind(function () {
      var accent = get('design.accent');
      var matched = false;
      swatchButtons.forEach(function (button, index) {
        var on = SWATCHES[index][0] === accent;
        if (on) matched = true;
        button.setAttribute('aria-checked', on ? 'true' : 'false');
        button.setAttribute('tabindex', on || (!matched && index === 0 && !SWATCHES.some(function (sw) { return sw[0] === accent; })) ? '0' : '-1');
      });
      if (native.value !== accent) native.value = accent;
      if (document.activeElement !== hexInput) hexInput.value = accent;
      contrast.textContent = accentIsLight(accent) ? T('ink_dark') : T('ink_light');
    });

    var demo = h('div', { class: 'dz-radius-demo', 'aria-hidden': 'true' }, [h('i'), h('i'), h('i')]);
    bind(function () {
      demo.style.setProperty('--demo-r', get('design.radius') + 'px');
    });

    return [
      group(T('group_variant'), variantTiles),
      group(T('group_accent'), [
        swatchWrap,
        h('div', { class: 'dz-color-row' }, [h('label', { class: 'dz-color-native', title: T('accent_free') }, native), hexInput, contrast]),
        hint(T('accent_hint')),
      ]),
      group(T('group_corners'), [
        demo,
        slider({
          path: 'design.radius',
          label: T('rounding'),
          min: 0,
          max: 22,
          format: function (value) {
            return value + ' px';
          },
        }),
      ]),
    ];
  }

  /* ================= Bereich: Hintergrund ================= */

  function uploadTile(onUploaded) {
    var file = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', class: 'dz-sr', tabindex: '-1', 'aria-hidden': 'true' });
    var option = {
      value: '__upload__',
      title: T('upload_title'),
      desc: T('upload_desc'),
      art: function () {
        if (uploadedUrl) return h('img', { src: uploadedUrl, alt: '', draggable: 'false' });
        return h('span', { class: 'dz-drop' }, [icon('upload'), T('upload_drop')]);
      },
      setup: function (button) {
        button.appendChild(file);
        button.addEventListener('dragover', function (event) {
          event.preventDefault();
          button.setAttribute('data-drag', 'ja');
        });
        button.addEventListener('dragleave', function () {
          button.removeAttribute('data-drag');
        });
        button.addEventListener('drop', function (event) {
          event.preventDefault();
          button.removeAttribute('data-drag');
          if (event.dataTransfer && event.dataTransfer.files[0]) upload(event.dataTransfer.files[0], button, onUploaded);
        });
        file.addEventListener('change', function () {
          if (file.files && file.files[0]) upload(file.files[0], button, onUploaded);
          file.value = '';
        });
        option.button = button;
      },
    };
    option.choose = function () {
      if (uploadedUrl && get('design.background') !== uploadedUrl) onUploaded(uploadedUrl);
      else file.click();
    };
    return option;
  }

  function upload(fileObject, button, done) {
    var maxBytes = ((DATA.upload && DATA.upload.max_kb) || 10240) * 1024;
    if (['image/png', 'image/jpeg', 'image/webp'].indexOf(fileObject.type) === -1) {
      toast('bad', T('upload_bad_type_title'), T('upload_bad_type'));
      return;
    }
    if (fileObject.size > maxBytes) {
      toast('bad', T('upload_too_big_title'), T('upload_too_big', { size: Math.round(maxBytes / 1048576) }));
      return;
    }
    var body = new FormData();
    body.append('image', fileObject);
    button.setAttribute('data-busy', 'ja');
    request('POST', BASE + '/upload', body)
      .then(function (response) {
        uploadedUrl = response.attributes.url;
        done(uploadedUrl);
        renderCategory(false);
        toast('ok', T('upload_done_title'), T('upload_done'));
      })
      .catch(function (error) {
        toast('bad', T('upload_failed'), error.message);
      })
      .then(function () {
        button.removeAttribute('data-busy');
      });
  }

  function renderBackground() {
    var custom = uploadTile(function (url) {
      set('design.background', url);
    });
    var urlOption = {
      value: '__url__',
      title: T('bg_url_title'),
      desc: T('bg_url_desc'),
      art: function () {
        return h('span', { class: 'dz-drop' }, [icon('link'), 'https://…']);
      },
    };
    var variantDefault = variantDefaultUrl(get('design.variant'));
    var options = [
      { value: '', title: T('bg_variant'), desc: T('bg_variant_desc'), art: variantDefault ? imageArt(variantDefault) : solidArt },
      { value: 'none', title: T('bg_none'), desc: T('bg_none_desc'), art: solidArt },
    ]
      .concat(
        motifs.map(function (motif) {
          return { value: motif.url, title: motif.name, desc: T('bundled'), art: imageArt(motif.url) };
        })
      )
      .concat([custom, urlOption]);

    var urlField = h('div', { class: 'dz-field' });
    var urlInput = h('input', {
      class: 'dz-input dz-input-mono',
      type: 'url',
      maxlength: '500',
      placeholder: 'https://example.org/bild.webp',
      'aria-label': T('bg_url_label'),
      spellcheck: 'false',
      on: {
        input: function () {
          var value = urlInput.value.trim();
          var valid = value === '' || (/^https:\/\//.test(value) && imageUrlOk(value));
          urlInput.setAttribute('aria-invalid', valid ? 'false' : 'true');
          urlError.hidden = valid;
          if (valid && value !== '') set('design.background', value, true);
        },
      },
    });
    var urlError = h('p', { class: 'dz-error', text: T('bg_url_error'), hidden: true });
    append(urlField, [h('label', { class: 'dz-field-label' }, [h('span', { text: T('bg_url_label') })]), urlInput, urlError]);

    var known = function (value) {
      return value === '' || value === 'none' || value === uploadedUrl || motifs.some(function (motif) {
        return motif.url === value;
      });
    };
    var urlMode = !known(get('design.background'));

    var grid = tiles({
      label: T('background_image'),
      cols: 2,
      options: options,
      value: function () {
        var value = get('design.background');
        if (urlMode) return '__url__';
        if (uploadedUrl && value === uploadedUrl) return '__upload__';
        return value;
      },
      pick: function (value, option) {
        if (value === '__upload__') {
          urlMode = false;
          urlField.hidden = true;
          option.choose();
          return;
        }
        if (value === '__url__') {
          urlMode = true;
          urlField.hidden = false;
          urlInput.value = known(get('design.background')) ? '' : get('design.background');
          changed();
          urlInput.focus();
          return;
        }
        urlMode = false;
        urlField.hidden = true;
        set('design.background', value);
      },
    });
    urlField.hidden = !urlMode;
    if (urlMode) urlInput.value = get('design.background');

    var noImage = function () {
      return resolvedBackground() === '';
    };

    var sceneGrid = tiles({
      label: T('group_scene'),
      cols: 2,
      pressed: true,
      options: SCENE_KEYS.map(function (key) {
        var option = { value: key, title: T('scene_' + key), art: sceneArt(key) };
        if (key === 'auto' || key === 'none') option.desc = T('scene_' + key + '_desc');
        return option;
      }),
      value: function () {
        var value = get('design.scene');
        return SCENE_KEYS.indexOf(value) === -1 ? 'auto' : value;
      },
      pick: function (value) {
        set('design.scene', value);
      },
    });

    return [
      group(T('group_image'), [
        grid,
        urlField,
        motifs.length ? null : hint(T('motifs_missing')),
      ]),
      group(T('group_scene'), [sceneGrid, hint(T('scene_hint'))]),
      group(T('group_effect'), [
        slider({
          path: 'design.dim',
          label: T('dim'),
          note: T('dim_note'),
          min: 0,
          max: 90,
          disabled: noImage,
          format: function (value) {
            return value + ' %';
          },
        }),
        slider({
          path: 'design.opacity',
          label: T('opacity'),
          note: T('opacity_note'),
          min: 35,
          max: 100,
          format: function (value) {
            return value + ' %';
          },
        }),
        slider({
          path: 'design.blur',
          label: T('blur'),
          note: T('blur_note'),
          min: 0,
          max: 24,
          format: function (value) {
            return value + ' px';
          },
        }),
        hint(T('blur_hint')),
      ]),
    ];
  }

  /* ================= Bereich: Layout ================= */

  function renderLayout() {
    return [
      group(
        T('group_navigation'),
        tiles({
          label: T('group_navigation'),
          cols: 3,
          options: ['sidebar', 'icons', 'top'].map(function (kind) {
            return { value: kind, title: T('nav_' + kind), desc: T('nav_' + kind + '_desc'), art: navArt(kind) };
          }),
          value: function () {
            return get('design.navigation');
          },
          pick: function (value) {
            set('design.navigation', value);
          },
        })
      ),
      group(
        T('group_servers'),
        tiles({
          label: T('group_servers'),
          cols: 3,
          options: ['raster', 'liste', 'kompakt'].map(function (kind) {
            return { value: kind, title: T('servers_' + kind), desc: T('servers_' + kind + '_desc'), art: serversArt(kind) };
          }),
          value: function () {
            return get('design.server_layout');
          },
          pick: function (value) {
            set('design.server_layout', value);
          },
        })
      ),
      group(T('group_banners'), [
        tiles({
          label: T('group_banners'),
          cols: 2,
          options: [
            { value: true, title: T('banners_on'), desc: T('banners_on_desc'), art: bannerArt(true) },
            { value: false, title: T('banners_off'), desc: T('banners_off_desc'), art: bannerArt(false) },
          ],
          value: function () {
            return get('design.banners');
          },
          pick: function (value) {
            set('design.banners', value);
          },
        }),
        slider({
          path: 'design.banner_height',
          label: T('banner_height'),
          min: 48,
          max: 160,
          step: 4,
          disabled: function () {
            return !get('design.banners');
          },
          format: function (value) {
            return value + ' px';
          },
        }),
      ]),
    ];
  }

  /* ================= Bereich: Bewegung ================= */

  function renderMotion() {
    return [
      group(
        T('group_level'),
        tiles({
          label: T('cat_motion'),
          cols: 1,
          options: ['aus', 'dezent', 'voll'].map(function (level) {
            return { value: level, title: T('motion_' + level), desc: T('motion_' + level + '_desc'), art: motionArt(level) };
          }),
          value: function () {
            return get('design.motion');
          },
          pick: function (value) {
            set('design.motion', value);
          },
        })
      ),
    ];
  }

  /* ================= Bereich: Login ================= */

  function renderLogin() {
    var options = [{ value: '', title: T('login_pattern'), desc: T('login_pattern_desc'), art: patternArt }];
    var background = resolvedBackground();
    options.push({
      value: '__background__',
      title: T('login_panel_bg'),
      desc: background ? T('login_panel_bg_desc') : T('login_panel_bg_off'),
      art: background ? imageArt(background) : solidArt,
      disabled: !background,
    });
    motifs.forEach(function (motif) {
      options.push({ value: motif.url, title: motif.name, desc: T('bundled'), art: imageArt(motif.url) });
    });

    var imageTiles = tiles({
      label: T('login_image_label'),
      cols: 2,
      options: options,
      value: function () {
        var value = get('design.login_image');
        if (value !== '' && value === resolvedBackground() && !motifs.some(function (motif) { return motif.url === value; })) return '__background__';
        return value;
      },
      pick: function (value) {
        set('design.login_image', value === '__background__' ? resolvedBackground() : value);
      },
    });

    return [
      group(T('group_image'), imageTiles),
      group(T('group_texts'), [
        textField({
          path: 'design.login_title',
          label: T('login_title'),
          max: OPT.login_title_max || 60,
          placeholder: T('login_title_ph'),
        }),
        textField({
          path: 'design.login_subtitle',
          label: T('login_subtitle'),
          max: OPT.login_subtitle_max || 160,
          multiline: true,
          rows: 2,
          placeholder: T('login_subtitle_ph'),
          hint: T('login_plain'),
        }),
      ]),
      h('div', { class: 'dz-group' }, [
        h(
          'button',
          {
            type: 'button',
            class: 'dz-btn',
            on: {
              click: function () {
                setPreviewPage('login');
              },
            },
          },
          [icon('eye'), T('login_preview')]
        ),
      ]),
    ];
  }

  /* ================= Bereich: Links ================= */

  function linkRows(config) {
    var list = h('div', { class: 'dz-list' });
    var addWrap = h('div', { class: 'dz-add' });
    var labels = config.labels;
    var addButtons = Object.keys(labels).map(function (key) {
      var button = h(
        'button',
        {
          type: 'button',
          class: 'dz-add-chip',
          on: {
            click: function () {
              var entries = clone(get(config.path));
              if (entries.length >= config.max) return;
              entries.push(config.create(key));
              set(config.path, entries);
              drawRows(true);
            },
          },
        },
        [icon('plus'), icon(key), labels[key]]
      );
      addWrap.appendChild(button);
      return button;
    });

    function drawRows(focusLast) {
      list.textContent = '';
      var entries = get(config.path);
      if (!entries.length) list.appendChild(h('p', { class: 'dz-empty', text: config.empty }));
      entries.forEach(function (entry, index) {
        var kind = entry[config.kindKey];
        var urlInput = h('input', {
          class: 'dz-input dz-input-mono',
          type: 'url',
          value: entry.url || '',
          placeholder: 'https://',
          maxlength: String((OPT.links && OPT.links.url_max) || 300),
          'aria-label': T('link_address', { name: labels[kind] }),
          spellcheck: 'false',
          on: {
            input: function () {
              var entries2 = clone(get(config.path));
              entries2[index].url = urlInput.value.trim();
              urlInput.setAttribute('aria-invalid', urlInput.value.trim() === '' || httpUrl(urlInput.value) ? 'false' : 'true');
              set(config.path, entries2, true);
            },
          },
        });
        urlInput.setAttribute('aria-invalid', !entry.url || httpUrl(entry.url) ? 'false' : 'true');
        var fields = [];
        if (config.withLabel) {
          var labelInput = h('input', {
            class: 'dz-input',
            type: 'text',
            value: entry.label || '',
            placeholder: labels[kind],
            maxlength: String((OPT.links && OPT.links.label_max) || 32),
            'aria-label': T('link_label', { name: labels[kind] }),
            on: {
              input: function () {
                var entries2 = clone(get(config.path));
                entries2[index].label = labelInput.value;
                set(config.path, entries2, true);
              },
            },
          });
          fields.push(labelInput);
        } else {
          fields.push(h('span', { class: 'dz-row-name', text: labels[kind] }));
        }
        fields.push(urlInput);
        var move = function (step) {
          return function () {
            var entries2 = clone(get(config.path));
            var target = index + step;
            if (target < 0 || target >= entries2.length) return;
            var item = entries2.splice(index, 1)[0];
            entries2.splice(target, 0, item);
            set(config.path, entries2);
            drawRows(false);
            var buttons = list.querySelectorAll('.dz-row')[target];
            if (buttons) buttons.querySelector(step < 0 ? '[data-move="up"]' : '[data-move="down"]').focus();
          };
        };
        list.appendChild(
          h('div', { class: 'dz-row' }, [
            h('span', { class: 'dz-row-icon' }, icon(kind)),
            h('div', { class: 'dz-row-fields' }, fields),
            h('div', { class: 'dz-row-tools' }, [
              h('button', { type: 'button', class: 'dz-icon-btn', 'data-move': 'up', title: T('move_up'), disabled: index === 0, on: { click: move(-1) } }, [icon('up'), h('span', { class: 'dz-sr', text: T('move_up') })]),
              h('button', { type: 'button', class: 'dz-icon-btn', 'data-move': 'down', title: T('move_down'), disabled: index === entries.length - 1, on: { click: move(1) } }, [icon('down'), h('span', { class: 'dz-sr', text: T('move_down') })]),
              h(
                'button',
                {
                  type: 'button',
                  class: 'dz-icon-btn',
                  title: T('remove'),
                  on: {
                    click: function () {
                      var entries2 = clone(get(config.path));
                      entries2.splice(index, 1);
                      set(config.path, entries2);
                      drawRows(false);
                    },
                  },
                },
                [icon('trash'), h('span', { class: 'dz-sr', text: T('remove') })]
              ),
            ]),
          ])
        );
      });
      addButtons.forEach(function (button) {
        button.disabled = entries.length >= config.max;
      });
      if (focusLast) {
        var inputs = list.querySelectorAll('.dz-row:last-child input');
        if (inputs.length) inputs[inputs.length - 1].focus();
      }
    }

    drawRows(false);
    // Rueckgaengig/Wiederholen: Liste neu zeichnen, wenn sich die Anzahl/Reihenfolge geaendert hat
    var lastShape = JSON.stringify(get(config.path).map(function (entry) {
      return entry[config.kindKey];
    }));
    bind(function () {
      var shape = JSON.stringify(get(config.path).map(function (entry) {
        return entry[config.kindKey];
      }));
      if (shape !== lastShape) {
        lastShape = shape;
        drawRows(false);
      }
    });
    return [list, addWrap];
  }

  function renderLinks() {
    var L = OPT.links || {};
    return [
      group(
        T('group_website'),
        linkRows({
          path: 'links.website',
          kindKey: 'kind',
          labels: L.website || {},
          max: L.max_website || 6,
          withLabel: true,
          empty: T('website_empty'),
          create: function (kind) {
            return { kind: kind, label: '', url: '' };
          },
        }),
        h('span', { class: 'dz-value', text: T('up_to', { count: L.max_website || 6 }) })
      ),
      group(
        T('group_social'),
        linkRows({
          path: 'links.social',
          kindKey: 'network',
          labels: L.social || {},
          max: L.max_social || 8,
          withLabel: false,
          empty: T('social_empty'),
          create: function (network) {
            return { network: network, url: '' };
          },
        }),
        h('span', { class: 'dz-value', text: T('up_to', { count: L.max_social || 8 }) })
      ),
      group(
        T('group_place'),
        tiles({
          label: T('links_place'),
          cols: 2,
          options: ['oben', 'schwebend'].map(function (position) {
            return { value: position, title: T('links_' + position), desc: T('links_' + position + '_desc'), art: linksArt(position, 'rechts') };
          }),
          value: function () {
            return get('links.position');
          },
          pick: function (value) {
            set('links.position', value);
          },
        })
      ),
      group(
        T('group_align'),
        tiles({
          label: T('align_label'),
          cols: 3,
          options: ['links', 'mitte', 'rechts'].map(function (align) {
            return {
              value: align,
              title: T('align_' + align),
              art: function () {
                return linksArt(get('links.position'), align)();
              },
            };
          }),
          value: function () {
            return get('links.align');
          },
          pick: function (value) {
            set('links.align', value);
          },
        })
      ),
      hint(T('links_hint')),
    ];
  }

  /* ================= Bereich: Ankuendigung ================= */


  function renderAnnouncement() {
    var A = OPT.announcement || {};
    var preview = h('div', { class: 'dz-announce', 'aria-live': 'polite' });
    var previewIcon = h('span', { class: 'dz-announce-icon' });
    var previewBody = h('div', { class: 'dz-announce-body' });
    append(preview, [previewIcon, previewBody]);

    var field = textField({
      path: 'announcement.text',
      label: T('text'),
      max: A.text_max || 600,
      multiline: true,
      rows: 4,
      placeholder: T('announce_ph'),
    });
    var textarea = field.input;

    function wrapSelection(before, after, fallback) {
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var value = textarea.value;
      var selected = value.slice(start, end) || fallback;
      var next = value.slice(0, start) + before + selected + after + value.slice(end);
      if (next.length > (A.text_max || 600)) return;
      textarea.value = next;
      textarea.focus();
      textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
      set('announcement.text', next);
    }

    var tools = h('div', { class: 'dz-md-tools', role: 'toolbar', 'aria-label': T('formatting') }, [
      h('button', { type: 'button', class: 'dz-icon-btn', title: T('bold_hint'), on: { click: function () { wrapSelection('**', '**', T('bold_sample')); } } }, [icon('bold'), h('span', { class: 'dz-sr', text: T('bold') })]),
      h('button', { type: 'button', class: 'dz-icon-btn', title: T('italic_hint'), on: { click: function () { wrapSelection('*', '*', T('italic_sample')); } } }, [icon('italic'), h('span', { class: 'dz-sr', text: T('italic') })]),
      h('button', { type: 'button', class: 'dz-icon-btn', title: T('link'), on: { click: function () { wrapSelection('[', '](https://)', T('link_sample')); } } }, [icon('link'), h('span', { class: 'dz-sr', text: T('link') })]),
      h('span', { class: 'dz-md-help', text: T('md_help') }),
    ]);
    field.insertBefore(tools, textarea);
    textarea.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && !event.shiftKey && (event.key === 'b' || event.key === 'i')) {
        event.preventDefault();
        if (event.key === 'b') wrapSelection('**', '**', T('bold_sample'));
        else wrapSelection('*', '*', T('italic_sample'));
      }
    });

    bind(function () {
      var a = state.announcement;
      preview.setAttribute('data-kind', a.kind);
      preview.setAttribute('data-off', a.enabled ? 'nein' : 'ja');
      previewIcon.textContent = '';
      previewIcon.appendChild(icon(a.icon));
      previewBody.textContent = '';
      append(previewBody, a.text.trim() ? renderMarkdown(a.text) : [h('span', { class: 'dz-hint', text: T('announce_empty') })]);
    });

    return [
      group(T('group_show'), [
        switchRow({ path: 'announcement.enabled', title: T('show_announcement'), desc: T('show_announcement_desc') }),
      ]),
      group(T('group_message'), [field, preview]),
      group(
        T('group_kind'),
        tiles({
          label: T('kind_label'),
          cols: 2,
          compact: true,
          options: (A.kinds || []).map(function (kind) {
            return {
              value: kind,
              title: T('kind_' + kind),
              desc: T('kind_' + kind + '_desc'),
              art: function () {
                return h('span', { class: 'dz-kind-chip', 'data-kind': kind }, icon(kind === 'erfolg' ? 'check' : kind === 'wartung' ? 'werkzeug' : kind === 'warnung' ? 'warnung' : kind === 'neu' ? 'stern' : 'info'));
              },
            };
          }),
          value: function () {
            return get('announcement.kind');
          },
          pick: function (value) {
            set('announcement.kind', value);
          },
        })
      ),
      group(
        T('group_icon'),
        tiles({
          label: T('group_icon'),
          cols: 5,
          iconOnly: true,
          options: (A.icons || []).map(function (name) {
            return { value: name, title: T('icon_' + name), iconName: name };
          }),
          value: function () {
            return get('announcement.icon');
          },
          pick: function (value) {
            set('announcement.icon', value);
          },
        })
      ),
      group(
        T('group_place'),
        tiles({
          label: T('announce_place'),
          cols: 3,
          options: ['karte', 'leiste', 'schwebend'].map(function (position) {
            return { value: position, title: T('pos_' + position), desc: T('pos_' + position + '_desc'), art: announceArt(position) };
          }),
          value: function () {
            return get('announcement.position');
          },
          pick: function (value) {
            set('announcement.position', value);
          },
        })
      ),
      group(T('group_behaviour'), [
        switchRow({ path: 'announcement.dismissible', title: T('dismissible'), desc: T('dismissible_desc') }),
      ]),
    ];
  }

  /* ================= Bereich: Rechte ================= */

  var UPLOAD_PRESETS = [512, 1024, 2048, 5120, 10240];

  function sizeLabel(kb) {
    return kb >= 1024 ? (kb / 1024).toLocaleString(NUMBER_LOCALE, { maximumFractionDigits: 1 }) + ' MB' : kb + ' KB';
  }

  function renderRights() {
    var presets = h('div', { class: 'dz-presets', role: 'radiogroup', 'aria-label': T('group_upload') });
    var presetButtons = UPLOAD_PRESETS.map(function (kb) {
      var button = h('button', {
        type: 'button',
        class: 'dz-preset',
        role: 'radio',
        'aria-checked': 'false',
        text: sizeLabel(kb),
        on: {
          click: function () {
            set('limits.max_upload_kb', kb);
          },
        },
      });
      presets.appendChild(button);
      return button;
    });
    var min = OPT.upload_min_kb || 64;
    var max = OPT.upload_max_kb || 10240;
    var number = h('input', {
      class: 'dz-input dz-input-mono',
      type: 'number',
      min: String(min),
      max: String(max),
      step: '64',
      id: 'dz-upload-kb',
      on: {
        change: function () {
          var value = clamp(Math.round(Number(number.value) || min), min, max);
          number.value = String(value);
          set('limits.max_upload_kb', value);
        },
      },
    });
    bind(function () {
      var value = get('limits.max_upload_kb');
      presetButtons.forEach(function (button, index) {
        button.setAttribute('aria-checked', UPLOAD_PRESETS[index] === value ? 'true' : 'false');
      });
      if (document.activeElement !== number) number.value = String(value);
    });

    var php = DATA.upload && DATA.upload.php_limit ? T('php_limit', { size: DATA.upload.php_limit }) : '';
    return [
      group(T('group_grants'), ['appearance', 'layout', 'background', 'banners'].map(function (key) {
        return switchRow({ path: 'limits.user_' + key, title: T('right_' + key), desc: T('right_' + key + '_desc') });
      })),
      group(T('group_upload'), [
        presets,
        h('div', { class: 'dz-field' }, [h('label', { class: 'dz-field-label', for: 'dz-upload-kb' }, [h('span', { text: T('upload_custom') }), h('span', { class: 'dz-count', text: T('range', { min: min, max: max }) })]), number]),
        hint(T('upload_hint') + ' ' + php),
      ]),
    ];
  }

  /* ================= Vorschau ================= */

  var frame = document.getElementById('dz-frame');
  var frameState = document.getElementById('dz-frame-state');
  var viewport = document.getElementById('dz-viewport');
  var device = document.getElementById('dz-device');
  var urlLabel = document.getElementById('dz-url');
  var zoomLabel = document.getElementById('dz-zoom');
  var serverWrap = document.getElementById('dz-server-wrap');
  var serverSelect = document.getElementById('dz-server');
  var servers = Array.isArray(DATA.servers) ? DATA.servers : [];
  var previewPage = 'dashboard';
  var deviceKind = 'desktop';
  var frameReady = false;
  var frameTimer = null;
  var DEVICE_WIDTH = { desktop: 1366, tablet: 834, handy: 390 };

  servers.forEach(function (server) {
    serverSelect.appendChild(h('option', { value: server.id, text: server.name + ' (' + server.id + ')' }));
  });
  serverSelect.addEventListener('change', function () {
    loadFrame();
  });

  function segmented(container, options, onPick) {
    var pill = h('span', { class: 'dz-seg-pill', 'aria-hidden': 'true' });
    container.appendChild(pill);
    var buttons = options.map(function (option, index) {
      var button = h(
        'button',
        {
          type: 'button',
          role: 'radio',
          'aria-checked': 'false',
          title: option.title,
          on: {
            click: function () {
              onPick(option.value);
            },
            keydown: function (event) {
              var step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
              if (step === undefined) return;
              event.preventDefault();
              var next = options[(index + step + options.length) % options.length];
              onPick(next.value);
              buttons[options.indexOf(next)].focus();
            },
          },
        },
        [icon(option.icon), option.label ? h('span', { text: option.label }) : h('span', { class: 'dz-sr', text: option.title })]
      );
      container.appendChild(button);
      return button;
    });
    return function update(value) {
      options.forEach(function (option, index) {
        var on = option.value === value;
        buttons[index].setAttribute('aria-checked', on ? 'true' : 'false');
        buttons[index].setAttribute('tabindex', on ? '0' : '-1');
        if (on) {
          container.style.setProperty('--pill-x', buttons[index].offsetLeft - 3 + 'px');
          container.style.setProperty('--pill-w', buttons[index].offsetWidth + 'px');
        }
      });
    };
  }

  var pageOptions = [
    { value: 'dashboard', label: T('page_dashboard'), title: T('page_dashboard'), icon: 'dashboard' },
    { value: 'console', label: T('page_console'), title: T('page_console_title'), icon: 'console' },
    { value: 'login', label: T('page_login'), title: T('page_login_title'), icon: 'login' },
    { value: 'admin', label: T('page_admin'), title: T('page_admin_title'), icon: 'admin' },
  ];
  if (!servers.length) pageOptions.splice(1, 1);
  var updatePages = segmented(document.getElementById('dz-pages'), pageOptions, setPreviewPage);
  var updateDevices = segmented(
    document.getElementById('dz-devices'),
    [
      { value: 'desktop', title: T('device_desktop'), icon: 'desktop' },
      { value: 'tablet', title: T('device_tablet'), icon: 'tablet' },
      { value: 'handy', title: T('device_handy'), icon: 'phone' },
    ],
    function (value) {
      deviceKind = value;
      device.setAttribute('data-device', value);
      updateDevices(value);
      fit();
    }
  );

  function setPreviewPage(page) {
    previewPage = page;
    updatePages(page);
    serverWrap.hidden = page !== 'console' || servers.length < 2;
    loadFrame();
  }

  function frameSrc() {
    var src = BASE + '/preview/' + previewPage;
    if (previewPage === 'console' && serverSelect.value) src += '?server=' + encodeURIComponent(serverSelect.value);
    return src;
  }

  function loadFrame() {
    frameReady = false;
    frameState.setAttribute('data-state', 'laedt');
    urlLabel.textContent = { dashboard: '/', console: '/server/…', login: '/auth/login', admin: '/admin' }[previewPage];
    clearTimeout(frameTimer);
    frameTimer = setTimeout(function () {
      if (!frameReady) frameState.setAttribute('data-state', 'fehler');
    }, 15000);
    frame.src = frameSrc();
  }

  frame.addEventListener('load', function () {
    // Kein Empfaenger nach dem Laden: Fehlerseite, abgelaufene Sitzung oder umgeleitet.
    setTimeout(function () {
      var ok = false;
      try {
        ok = !!(frame.contentWindow && frame.contentWindow.__techtylReceiver);
      } catch (error) {
        ok = false;
      }
      if (!ok && frame.src.indexOf('about:blank') === -1) frameState.setAttribute('data-state', 'fehler');
    }, 1500);
  });

  document.getElementById('dz-reload').addEventListener('click', loadFrame);
  document.getElementById('dz-frame-retry').addEventListener('click', loadFrame);

  window.addEventListener('message', function (event) {
    if (event.origin !== ORIGIN || event.source !== frame.contentWindow) return;
    var message = event.data;
    if (!message || typeof message !== 'object' || message.type !== 'techtyl-preview' || message.v !== 1) return;
    if (typeof message.path === 'string') urlLabel.textContent = message.path.slice(0, 120);
    if (message.event === 'ready') {
      frameReady = true;
      clearTimeout(frameTimer);
      sendToFrame();
      // kurz warten, bis die App gezeichnet hat
      setTimeout(function () {
        frameState.setAttribute('data-state', 'bereit');
      }, previewPage === 'admin' ? 80 : 450);
      // The panel app did not start (e.g. its script is missing during an update): say so.
      if (previewPage !== 'admin') {
        setTimeout(function () {
          try {
            var appRoot = frame.contentDocument && frame.contentDocument.getElementById('app');
            if (appRoot && appRoot.children.length === 0) frameState.setAttribute('data-state', 'fehler');
          } catch (error) {
            /* other origin: nothing to check */
          }
        }, 6000);
      }
    } else if (message.event === 'blocked') {
      blockedToast(message.detail);
    }
  });

  var lastBlocked = 0;
  function blockedToast(kind) {
    if (Date.now() - lastBlocked < 4000) return;
    lastBlocked = Date.now();
    toast('info', T('blocked_title'), T(kind === 'logout' ? 'blocked_logout' : kind === 'form' ? 'blocked_form' : 'blocked_link'));
  }

  function sendToFrame() {
    if (!frameReady || !frame.contentWindow) return;
    frame.contentWindow.postMessage(
      {
        type: 'techtyl-designer',
        v: 1,
        design: Object.assign({}, state.design, { background: resolvedBackground(), scene: previewScene() }),
        announcement: state.announcement,
        links: state.links,
      },
      ORIGIN
    );
  }

  function fit() {
    var width = viewport.clientWidth;
    var height = viewport.clientHeight;
    if (!width || !height) return;
    var wanted = deviceKind === 'desktop' ? Math.max(width, Math.min(DEVICE_WIDTH.desktop, 1366)) : DEVICE_WIDTH[deviceKind];
    if (deviceKind === 'desktop' && width >= 1180) wanted = width;
    var scale = Math.min(1, width / wanted);
    viewport.style.setProperty('--frame-w', Math.round(wanted) + 'px');
    viewport.style.setProperty('--frame-h', Math.round(height / scale) + 'px');
    viewport.style.setProperty('--frame-scale', scale.toFixed(4));
    zoomLabel.textContent = Math.round(scale * 100) + ' %';
  }

  if (window.ResizeObserver) new ResizeObserver(fit).observe(viewport);
  window.addEventListener('resize', function () {
    fit();
    placeCatMarker();
    updatePages(previewPage);
    updateDevices(deviceKind);
  });

  // Schmale Bildschirme: Vorschau als eigene Ebene
  var stageOpen = document.getElementById('dz-preview-open');
  stageOpen.addEventListener('click', function () {
    app.setAttribute('data-stage', 'offen');
    // On a phone the phone frame is the useful default.
    if (deviceKind === 'desktop' && window.innerWidth < 600) {
      deviceKind = 'handy';
      device.setAttribute('data-device', 'handy');
    }
    setTimeout(function () {
      fit();
      updatePages(previewPage);
      updateDevices(deviceKind);
      document.getElementById('dz-stage-close').focus();
    }, 50);
  });
  document.getElementById('dz-stage-close').addEventListener('click', function () {
    app.removeAttribute('data-stage');
    stageOpen.focus();
  });

  /* ================= Oberflaeche, Speichern ================= */

  function applyChrome() {
    var accent = state.design.accent;
    if (/^#[0-9a-f]{6}$/i.test(accent)) {
      root.style.setProperty('--dz-accent', accent);
      root.style.setProperty('--dz-accent-ink', accentIsLight(accent) ? '#17130b' : '#ffffff');
    }
    root.setAttribute('data-motion', state.design.motion);
  }

  var savebar = document.getElementById('dz-savebar');
  var savebarText = document.getElementById('dz-savebar-text');
  var saveButton = document.getElementById('dz-save');
  var discardButton = document.getElementById('dz-discard');
  var undoButton = document.getElementById('dz-undo');
  var redoButton = document.getElementById('dz-redo');
  var saving = false;

  function updateSavebar() {
    var count = dirtyPaths().length;
    var open = count > 0 || saving;
    savebar.setAttribute('data-open', open ? 'ja' : 'nein');
    savebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    app.setAttribute('data-dirty', count > 0 ? 'ja' : 'nein');
    saveButton.tabIndex = open ? 0 : -1;
    discardButton.tabIndex = open ? 0 : -1;
    if (!saving) {
      savebarText.textContent = '';
      append(savebarText, [T('unsaved') + ' ', h('b', { text: '· ' + T(count === 1 ? 'values_one' : 'values_many', { count: count }) })]);
    }
    undoButton.disabled = undoStack.length === 0;
    redoButton.disabled = redoStack.length === 0;
    document.title = (count > 0 ? '• ' : '') + T('page_title');
  }

  function request(method, url, body, retried) {
    var original = body;
    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    // Current token from the XSRF cookie (stays valid if the session token changed since the page
    // loaded); the token of the page is the fallback.
    var cookie = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);
    if (cookie) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie[1]);
    else headers['X-CSRF-TOKEN'] = CSRF;
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    return fetch(url, { method: method, headers: headers, body: body, credentials: 'same-origin', redirect: 'error' })
      .catch(function () {
        throw new Error(T('err_network'));
      })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return null;
          })
          .then(function (json) {
            if (response.ok && json) return json;
            // Session token changed meanwhile (e.g. signed in again in another tab): the response
            // brought a fresh XSRF cookie, so one more attempt.
            if (response.status === 419 && !retried) return request(method, url, original, true);
            var error = new Error(describeError(response.status, json));
            error.status = response.status;
            // Field of the first error: Laravel style {errors: {field: [...]}} or the panel's
            // {errors: [{detail, source: {field}}]}
            error.field = null;
            if (json && json.errors && !Array.isArray(json.errors)) error.field = Object.keys(json.errors)[0] || null;
            else if (json && Array.isArray(json.errors) && json.errors[0]) {
              var first = json.errors[0];
              var field = (first.meta && first.meta.source_field) || (first.source && first.source.field) || '';
              error.field = String(field) || null;
            }
            throw error;
          });
      });
  }

  function describeError(status, json) {
    if (status === 419) return T('err_419');
    if (status === 404 || status === 401 || status === 403) return T('err_auth');
    if (status === 413) return T('err_413');
    if (status === 429) return T('err_429');
    if (json && json.errors && !Array.isArray(json.errors)) {
      var first = Object.keys(json.errors)[0];
      if (first && json.errors[first][0]) return json.errors[first][0];
    }
    if (json && Array.isArray(json.errors) && json.errors[0] && json.errors[0].detail) return json.errors[0].detail;
    return T('err_generic', { status: status });
  }

  function categoryForField(field) {
    var path = field.split('.').slice(0, 2).join('.');
    return (
      CATEGORIES.find(function (category) {
        return category.paths.indexOf(path) !== -1;
      }) || null
    );
  }

  function save() {
    if (saving || !isDirty()) return Promise.resolve(true);
    saving = true;
    savebar.setAttribute('data-state', 'speichert');
    saveButton.disabled = true;
    discardButton.disabled = true;
    savebarText.textContent = T('saving');
    return request('PUT', BASE + '/editor', state)
      .then(function (response) {
        var fresh = response.attributes;
        saved = pickState(fresh);
        uploadedUrl = fresh.uploaded || uploadedUrl;
        state = clone(saved);
        undoStack = [];
        redoStack = [];
        saving = false;
        savebar.setAttribute('data-state', 'gespeichert');
        toast('ok', T('saved_title'), T('saved_text'));
        changed(true);
        return true;
      })
      .catch(function (error) {
        saving = false;
        savebar.setAttribute('data-state', '');
        toast('bad', T('not_saved'), error.message);
        if (error.field) {
          var category = categoryForField(error.field);
          if (category && category !== current) selectCategory(category, true);
        }
        updateSavebar();
        return false;
      })
      .then(function (result) {
        saveButton.disabled = false;
        discardButton.disabled = false;
        return result;
      });
  }

  function discard() {
    if (!isDirty()) return;
    undoStack.push(JSON.stringify(state));
    redoStack = [];
    state = clone(saved);
    changed(true);
    toast('info', T('discarded_title'), T('discarded_text'));
  }

  saveButton.addEventListener('click', save);
  discardButton.addEventListener('click', discard);
  undoButton.addEventListener('click', undo);
  redoButton.addEventListener('click', redo);

  /* ================= Meldungen und Dialog ================= */

  var toasts = document.getElementById('dz-toasts');
  function toast(tone, title, text) {
    var node = h('div', { class: 'dz-toast', 'data-tone': tone, role: tone === 'bad' ? 'alert' : 'status' }, [
      icon(tone === 'ok' ? 'ok' : tone === 'bad' ? 'alert' : 'info'),
      h('div', {}, [h('strong', { text: title }), text ? h('span', { text: text }) : null]),
    ]);
    toasts.appendChild(node);
    while (toasts.children.length > 3) toasts.removeChild(toasts.firstChild);
    setTimeout(
      function () {
        node.setAttribute('data-leaving', 'ja');
        setTimeout(function () {
          if (node.parentNode) node.parentNode.removeChild(node);
        }, 300);
      },
      tone === 'bad' ? 7000 : 3800
    );
  }

  var modal = document.getElementById('dz-modal');
  var modalTitle = document.getElementById('dz-modal-title');
  var modalText = document.getElementById('dz-modal-text');
  var modalActions = document.getElementById('dz-modal-actions');
  var modalReturn = null;

  function openModal(title, body, actions) {
    modalReturn = document.activeElement;
    modalTitle.textContent = title;
    modalText.textContent = '';
    append(modalText, body);
    modalActions.textContent = '';
    actions.forEach(function (action) {
      modalActions.appendChild(
        h('button', {
          type: 'button',
          class: 'dz-btn' + (action.primary ? ' dz-btn-go' : action.ghost ? ' dz-btn-ghost' : ''),
          text: action.label,
          on: {
            click: function () {
              closeModal();
              if (action.run) action.run();
            },
          },
        })
      );
    });
    modal.hidden = false;
    var buttons = modalActions.querySelectorAll('button');
    if (buttons.length) buttons[buttons.length - 1].focus();
  }

  function closeModal() {
    modal.hidden = true;
    if (modalReturn && modalReturn.focus) modalReturn.focus();
  }

  modal.addEventListener('click', function (event) {
    if (event.target.hasAttribute && event.target.hasAttribute('data-close')) closeModal();
  });
  modal.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
    }
    if (event.key === 'Tab') {
      var focusable = modal.querySelectorAll('button');
      if (!focusable.length) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });

  document.getElementById('dz-help').addEventListener('click', function () {
    var keys = ['save', 'undo', 'redo', 'cats', 'arrows', 'esc'].map(function (key) {
      return [T('key_' + key), T('key_' + key + '_text')];
    });
    var list = h('div', { class: 'dz-keys' });
    keys.forEach(function (key) {
      append(list, [h('kbd', { text: key[0] }), h('span', { text: key[1] })]);
    });
    openModal(T('shortcuts'), list, [{ label: T('close'), primary: true }]);
  });

  // Verlassen mit ungespeicherten Aenderungen
  Array.prototype.forEach.call(document.querySelectorAll('[data-leave]'), function (link) {
    link.addEventListener('click', function (event) {
      if (!isDirty()) return;
      event.preventDefault();
      var href = link.getAttribute('href');
      openModal(T('leave_title'), T('leave_text', { count: dirtyPaths().length }), [
        { label: T('cancel'), ghost: true },
        {
          label: T('leave_discard'),
          run: function () {
            state = clone(saved);
            window.location.href = href;
          },
        },
        {
          label: T('leave_save'),
          primary: true,
          run: function () {
            save().then(function (ok) {
              if (ok) window.location.href = href;
            });
          },
        },
      ]);
    });
  });

  window.addEventListener('beforeunload', function (event) {
    if (!isDirty()) return;
    event.preventDefault();
    event.returnValue = '';
  });

  /* ================= Tastatur ================= */

  document.addEventListener('keydown', function (event) {
    if (!modal.hidden) return;
    var mod = event.ctrlKey || event.metaKey;
    var key = event.key.toLowerCase();
    var typing = /^(input|textarea|select)$/i.test((event.target && event.target.tagName) || '') && event.target.type !== 'range' && event.target.type !== 'color';
    if (mod && key === 's') {
      event.preventDefault();
      save();
      return;
    }
    if (mod && !typing && key === 'z') {
      event.preventDefault();
      if (event.shiftKey) redo();
      else undo();
      return;
    }
    if (mod && !typing && key === 'y') {
      event.preventDefault();
      redo();
      return;
    }
    if (event.altKey && !mod && /^[1-8]$/.test(event.key)) {
      var category = CATEGORIES[Number(event.key) - 1];
      if (category) {
        event.preventDefault();
        selectCategory(category, true);
        catButtons[category.key].focus();
      }
      return;
    }
    if (event.key === 'Escape' && app.getAttribute('data-stage') === 'offen') {
      app.removeAttribute('data-stage');
      stageOpen.focus();
    }
  });

  /* ================= Start ================= */

  var startKey = null;
  try {
    startKey = sessionStorage.getItem('techtyl-designer-cat');
  } catch (error) {
    startKey = null;
  }
  var start =
    CATEGORIES.find(function (category) {
      return category.key === startKey;
    }) || CATEGORIES[0];
  current = start;
  selectCategory(start, true);
  applyChrome();
  updateSavebar();
  updateCategoryMarks();
  updatePages(previewPage);
  updateDevices(deviceKind);
  loadFrame();
  fit();
  app.setAttribute('data-ready', 'ja');
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () {
      placeCatMarker();
      updatePages(previewPage);
      updateDevices(deviceKind);
    });
  }
})();
