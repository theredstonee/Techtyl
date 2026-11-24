/* Living scenes for canvas#techtyl-scene. API: TechtylScene.scenes and TechtylScene.refresh(). */
(function () {
  'use strict';
  if (window.TechtylScene) return;

  var SCENE_KEYS = ['contours', 'aurora', 'stars', 'forest', 'harbor', 'grid'];
  var HEAVY = { harbor: 1 };
  var SEEDS = {
    contours: 0x51ed2,
    aurora: 0x0a7c4,
    stars: 0x57a11,
    forest: 0x0f04e,
    harbor: 0x4a8b3,
    grid: 0x061d7
  };
  var TAU = 6.283185307179586; var STEP_MS = 1000 / 30; var FADE_MS = 400; var STILL_T = 10;

  var api = {
    scenes: SCENE_KEYS,
    refresh: function () {}
  };
  window.TechtylScene = api;

  var host = null, canvas = null, ctx = null, overlay = null, snap = null;
  var haveSnap = false, current = null, builtSig = '', bufW = 0, bufH = 0, ratio = 1;
  var tier = 0, strikes = 0, clock = 0, running = false, raf = 0, lastTs = 0, acc = 0;
  var fadeLeft = 0, hiding = false, queued = 0, resizeTimer = 0, applying = false;
  var meterT = 0, meterSum = 0, meterN = 0, meterWarm = 8, grainTile = null, motionQuery = null, started = false;

  var FALLBACK = {
    accent: { r: 240, g: 163, b: 59, a: 1 },
    bg: { r: 15, g: 28, b: 35, a: 1 },
    surface: { r: 23, g: 42, b: 51, a: 1 },
    raise: { r: 31, g: 55, b: 66, a: 1 },
    text: { r: 233, g: 240, b: 238, a: 1 }
  };

  /* Marching squares. Bits: 1 TL, 2 TR, 4 BR, 8 BL. Edges: 0 top, 1 right, 2 bottom, 3 left. */
  var MARCH = [
    [],
    [3, 0],
    [0, 1],
    [3, 1],
    [1, 2],
    [3, 0, 1, 2],
    [0, 2],
    [3, 2],
    [2, 3],
    [0, 2],
    [0, 1, 2, 3],
    [1, 2],
    [1, 3],
    [0, 1],
    [0, 3],
    []
  ]; var S5A = [3, 2, 0, 1]; var S5B = [3, 0, 1, 2]; var S10A = [0, 3, 1, 2]; var S10B = [0, 1, 2, 3];

  function mulberry(seed) {
    var s = seed >>> 0;
    return function () {
      s = (s + 0x6d2b79f5) | 0; var t = Math.imul(s ^ (s >>> 15), 1 | s);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  function makeCanvas(w, h) {
    var c = document.createElement('canvas'); c.width = Math.max(1, w | 0);
    c.height = Math.max(1, h | 0); return c;
  }

  function rgbCss(c) {
    return 'rgb(' + (c.r | 0) + ',' + (c.g | 0) + ',' + (c.b | 0) + ')';
  }

  function rgba(c, a) {
    var v = a < 0 ? 0 : a > 1 ? 1 : a;
    return 'rgba(' + (c.r | 0) + ',' + (c.g | 0) + ',' + (c.b | 0) + ',' + v + ')';
  }

  function mix(a, b, t) {
    return {
      r: a.r + (b.r - a.r) * t,
      g: a.g + (b.g - a.g) * t,
      b: a.b + (b.b - a.b) * t,
      a: 1
    };
  }

  function luma(c) {
    return (0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b) / 255;
  }

  function hue(p, q, t) {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 0.5) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  }

  /* Shift the accent around the wheel. Saturation and lightness are clamped so neighbours stay quiet. */
  function shiftHue(c, deg, satMul, litAdd) {
    var r = c.r / 255, g = c.g / 255, b = c.b / 255;
    var max = Math.max(r, g, b), min = Math.min(r, g, b), h = 0, s = 0, l = (max + min) / 2, d = max - min;
    if (d > 1e-5) {
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      h = (max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4) / 6;
    }
    h = h + deg / 360; h -= Math.floor(h);
    s = Math.max(0, Math.min(1, s * satMul)); l = Math.max(0, Math.min(0.86, l + litAdd));
    var q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
    if (s < 1e-5) r = g = b = l;
    else { r = hue(p, q, h + 1 / 3); g = hue(p, q, h); b = hue(p, q, h - 1 / 3); }
    return { r: r * 255, g: g * 255, b: b * 255, a: 1 };
  }

  function parseCssColor(computed) {
    if (!computed) return null;
    var nums = computed.match(/[\d.]+/g);
    if (!nums || nums.length < 3) return null;
    var a = 1;
    if (nums.length >= 4) {
      a = +nums[3];
      if (computed.indexOf('%') !== -1 && a > 1) a = a / 100;
    }
    return { r: +nums[0], g: +nums[1], b: +nums[2], a: a };
  }

  function resolveColor(raw, fallback) {
    if (!raw || !canvas) return fallback;
    canvas.style.color = raw; var computed = '';
    try {
      computed = getComputedStyle(canvas).color;
    } catch (err) {
      computed = '';
    }
    canvas.style.color = ''; return parseCssColor(computed) || fallback;
  }

  function roundRect(g, x, y, w, h, r) {
    var rr = r;
    if (rr > w * 0.5) rr = w * 0.5;
    if (rr > h * 0.5) rr = h * 0.5;
    if (rr < 0) rr = 0;
    g.beginPath(); g.moveTo(x + rr, y);
    g.arcTo(x + w, y, x + w, y + h, rr); g.arcTo(x + w, y + h, x, y + h, rr);
    g.arcTo(x, y + h, x, y, rr); g.arcTo(x, y, x + w, y, rr); g.closePath();
  }

  /* Two pulses a minute. */
  function breathOf(t) {
    return 0.5 + 0.5 * Math.sin(t * Math.PI / 15);
  }

  /* About 0.42 in the central 40% of the width, 1 at the edges. */
  function centerQuiet(x, y, w, h) {
    var dx = (x - w * 0.5) / (w * 0.2); var dy = (y - h * 0.46) / (h * 0.3); var q = dx * dx + dy * dy;
    if (q >= 1) return 1;
    return 0.42 + 0.58 * q;
  }

  function scaledCount(base, env) {
    var n = Math.round(base * env.count);
    if (n < 1) n = 1;
    return n;
  }

  function hazeOf(variant) {
    if (variant === 'kiefer') return 1.22;
    if (variant === 'hafen') return 1.14;
    if (variant === 'papier') return 0.78;
    return 1;
  }

  function paintDot(g, x, y, s, pal, breath, halo) {
    var i; var rad; var a; var k; g.globalCompositeOperation = 'source-over';
    if (!pal.light) {
      var n = 6;
      for (i = n; i >= 1; i--) {
        k = i / n; rad = s * (1.7 + k * (5.2 + breath * 1.8) * halo);
        a = (0.1 + breath * 0.08) * (1 - k) * (1 - k) * halo; g.globalAlpha = a;
        g.fillStyle = pal.accentCss; g.beginPath(); g.arc(x, y, rad, 0, TAU); g.fill();
      }
      g.globalAlpha = 0.98; g.fillStyle = pal.dotCss;
      roundRect(g, x - s, y - s, s * 2, s * 2, s * 0.44); g.fill();
    } else {
      g.globalAlpha = 0.07; g.fillStyle = pal.inkCss;
      roundRect(g, x - s - s * 0.35, y - s + s * 0.2, s * 2.7, s * 2.9, s * 0.6); g.fill();
      g.globalAlpha = 0.12; roundRect(g, x - s + 0.8, y - s + 2.2, s * 2, s * 2, s * 0.44);
      g.fill(); g.globalAlpha = 1; g.fillStyle = pal.inkCss; roundRect(g, x - s, y - s, s * 2, s * 2, s * 0.42);
      g.fill();
    }
    g.globalAlpha = 1;
  }

  function ensureGrain() {
    if (grainTile) return grainTile;
    var rand = mulberry(0xc0ffee); var size = 256;
    grainTile = makeCanvas(size, size); var g = grainTile.getContext('2d');
    var img = g.createImageData(size, size); var data = img.data; var i; var n;
    for (i = 0; i < data.length; i += 4) {
      n = rand();
      if (n > 0.9) {
        var lit = n > 0.955; var v = lit ? 255 : 0; data[i] = v; data[i + 1] = v;
        data[i + 2] = v; data[i + 3] = 18 + ((n * 180) & 28);
      }
    }
    g.putImageData(img, 0, 0); return grainTile;
  }

  function buildOverlay(w, h, pal) {
    var c = makeCanvas(w, h); var g = c.getContext('2d'); g.save(); g.translate(w * 0.5, h * 0.46);
    g.scale(w * 0.4, h * 0.34); var veil = g.createRadialGradient(0, 0, 0.12, 0, 0, 1);
    veil.addColorStop(0, rgba(pal.bg, pal.light ? 0.2 : 0.18)); veil.addColorStop(1, rgba(pal.bg, 0));
    g.fillStyle = veil; g.beginPath(); g.arc(0, 0, 1, 0, TAU); g.fill(); g.restore();

    var edge = pal.light ? mix(pal.bg, { r: 78, g: 92, b: 118 }, 0.35) : mix(pal.bg, { r: 0, g: 0, b: 0 }, 0.55);
    var reach = Math.sqrt(w * w + h * h) * 0.68;
    var vig = g.createRadialGradient(w * 0.5, h * 0.46, reach * 0.42, w * 0.5, h * 0.5, reach);
    vig.addColorStop(0, rgba(edge, 0)); vig.addColorStop(1, rgba(edge, pal.light ? 0.14 : 0.26));
    g.fillStyle = vig; g.fillRect(0, 0, w, h);

    var tile = ensureGrain(); var pat = g.createPattern(tile, 'repeat');
    if (pat) {
      g.globalAlpha = pal.light ? 0.55 : 0.7; g.fillStyle = pat; g.fillRect(0, 0, w, h); g.globalAlpha = 1;
    }
    return c;
  }

  function readPalette(variant) {
    var cs = getComputedStyle(document.documentElement);
    function grab(prop, fallback) {
      var raw = cs.getPropertyValue(prop); raw = raw ? raw.trim() : ''; return resolveColor(raw, fallback);
    }
    var pal = {
      accent: grab('--techtyl-accent', FALLBACK.accent),
      bg: grab('--techtyl-bg', FALLBACK.bg),
      surface: grab('--techtyl-surface-c', FALLBACK.surface),
      raise: grab('--techtyl-raise-c', FALLBACK.raise),
      text: grab('--techtyl-text', FALLBACK.text)
    };
    pal.light = variant === 'papier' || luma(pal.bg) > 0.62; pal.accentCss = rgbCss(pal.accent);
    pal.textCss = rgbCss(pal.text);
    pal.ink = mix(pal.text, { r: 18, g: 48, b: 102 }, 0.48);
    pal.inkCss = rgbCss(pal.ink);
    pal.dotCss = rgbCss(pal.light ? pal.ink : mix(pal.accent, { r: 255, g: 248, b: 236 }, 0.2));
    pal.warm = shiftHue(pal.accent, -34, pal.light ? 0.55 : 0.9, pal.light ? -0.2 : 0.04);
    pal.cool = shiftHue(pal.accent, 35, pal.light ? 0.5 : 0.82, pal.light ? -0.14 : 0.06);
    pal.deep = pal.light ? mix(pal.text, pal.bg, 0.72) : mix(pal.bg, { r: 0, g: 0, b: 0 }, 0.4);
    pal.deepCss = rgbCss(pal.deep); pal.mist = mix(pal.surface, pal.bg, pal.light ? 0.35 : 0.25);
    pal.lineCss = pal.light ? pal.inkCss : pal.textCss; return pal;
  }

  function palSig(pal) {
    function s(c) { return (c.r | 0) + '.' + (c.g | 0) + '.' + (c.b | 0); }
    return s(pal.accent) + '|' + s(pal.bg) + '|' + s(pal.surface) + '|' + s(pal.raise) + '|' + s(pal.text);
  }

  /* Contours: a nautical chart. Coarse noise drifts; lines march on a finer lattice up to one summit. */
  function initContours(w, h, pal, rand, env) {
    var cell = Math.round((env.calm || env.tier ? 132 : 106) * env.ratio);
    if (cell < 12) cell = 12;
    var cols = Math.ceil(w / cell) + 1; var rows = Math.ceil(h / cell) + 1;
    if (cols < 3) cols = 3;
    if (rows < 3) rows = 3;
    var coarse = new Float32Array(cols * rows); var dotX = w * 0.73;
    var dotY = h * 0.34; var dotS = 9 * env.ratio;
    var levels = [0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.02, 1.16]; var ex1 = 0; var ey1 = 0; var ex2 = 0;
    var ey2 = 0; var noise = makeNoise(rand);

    function sample(px, py) {
      var gx = px / cell; var gy = py / cell; var x0 = gx | 0; var y0 = gy | 0;
      if (x0 < 0) x0 = 0;
      if (y0 < 0) y0 = 0;
      if (x0 > cols - 2) x0 = cols - 2;
      if (y0 > rows - 2) y0 = rows - 2;
      var tx = gx - x0; var ty = gy - y0;
      var i = y0 * cols + x0; var v0 = coarse[i] + (coarse[i + 1] - coarse[i]) * tx;
      var v1 = coarse[i + cols] + (coarse[i + cols + 1] - coarse[i + cols]) * tx; var v = v0 + (v1 - v0) * ty;
      var dx = (px - dotX) / w; var dy = (py - dotY) / h; var q = dx * dx * 16 + dy * dy * 22;
      if (q < 6) v += 0.78 * Math.exp(-q);
      return v;
    }

    function setEdge(edge, x, y, step, vTL, vTR, vBR, vBL, level, slot) {
      var va = edge === 0 ? vTL : edge === 1 ? vTR : edge === 2 ? vBR : vBL;
      var vb = edge === 0 ? vTR : edge === 1 ? vBR : edge === 2 ? vBL : vTL;
      var t = va === vb ? 0.5 : (level - va) / (vb - va);
      if (t < 0) t = 0;
      else if (t > 1) t = 1;
      var px = edge === 1 ? x + step : edge === 2 ? x + (1 - t) * step : edge === 0 ? x + t * step : x;
      var py = edge === 0 ? y : edge === 2 ? y + step : edge === 1 ? y + t * step : y + (1 - t) * step;
      if (slot) { ex2 = px; ey2 = py; } else { ex1 = px; ey1 = py; }
    }

    function strokeLines(g, t) {
      var ox = t * 0.02 * env.speed; var oy = t * 0.008 * env.speed; var x; var y;
      var n; var px; var py; var mid;
      for (y = 0; y < rows; y++) {
        for (x = 0; x < cols; x++) {
          n = noise(x * 0.7 + ox, y * 0.7 + oy) * 0.66 + noise(x * 1.45 + 9 + ox * 0.6, y * 1.45 + oy * 0.6) * 0.34;
          px = x * cell; py = y * cell; mid = (px / w - 0.5) / 0.22; mid = Math.exp(-(mid * mid));
          coarse[y * cols + x] = n * (1 - 0.48 * mid) + 0.5 * mid;
        }
      }
      var step = cell / 3; var estimate = (w / step) * (h / step);
      if (estimate > 3400) step = Math.sqrt((w * h) / 3400);
      var li; var level; var x0; var y0; var vTL; var vTR; var vBR; var vBL; var idx; var avg; var segs; var si;
      for (li = 0; li < levels.length; li++) {
        if (env.calm && li % 2 === 1) continue;
        level = levels[li]; g.beginPath();
        for (y0 = 0; y0 < h; y0 += step) {
          for (x0 = 0; x0 < w; x0 += step) {
            vTL = sample(x0, y0); vTR = sample(x0 + step, y0);
            vBR = sample(x0 + step, y0 + step); vBL = sample(x0, y0 + step); idx = 0;
            if (vTL >= level) idx |= 1;
            if (vTR >= level) idx |= 2;
            if (vBR >= level) idx |= 4;
            if (vBL >= level) idx |= 8;
            if (idx === 0 || idx === 15) continue;
            avg = (vTL + vTR + vBR + vBL) * 0.25; segs = MARCH[idx];
            if (idx === 5) segs = avg >= level ? S5A : S5B;
            else if (idx === 10) segs = avg >= level ? S10A : S10B;
            for (si = 0; si < segs.length; si += 2) {
              setEdge(segs[si], x0, y0, step, vTL, vTR, vBR, vBL, level, 0);
              setEdge(segs[si + 1], x0, y0, step, vTL, vTR, vBR, vBL, level, 1); g.moveTo(ex1, ey1);
              g.lineTo(ex2, ey2);
            }
          }
        }
        var accent = li === 6; var strong = li % 5 === 0; g.strokeStyle = accent ? pal.accentCss : pal.lineCss;
        /* Alphas sit above the brief's 0.12-0.22 so the lines still read under the panel's dim wash. */
        g.globalAlpha = accent ? 0.62 : strong ? 0.52 : 0.4;
        if (pal.light && !accent) g.globalAlpha = strong ? 0.5 : 0.36;
        g.lineWidth = strong ? 1.35 : 1; g.stroke();
      }
      g.globalAlpha = 1;
    }

    /* Offset moves a fraction of a pixel between rebuilds, so the cache stays continuous. */
    var cache = null; var cacheN = 99; var gap = env.tier ? 8 : 4;
    return {
      frame: function (t) {
        cacheN++;
        if (!cache || cacheN >= gap) {
          cacheN = 0;
          if (!cache) cache = makeCanvas(w, h);
          var g = cache.getContext('2d'); g.clearRect(0, 0, w, h); g.lineCap = 'round'; g.lineJoin = 'round';
          strokeLines(g, t);
        }
        ctx.drawImage(cache, 0, 0);
      },
      mark: function (t) {
        drawCompass(ctx, dotX, dotY, env.ratio, pal);
        paintDot(ctx, dotX, dotY, dotS, pal, breathOf(t), pal.light ? 0 : 1.05);
      }
    };
  }

  function drawCompass(g, x, y, scale, pal) {
    g.strokeStyle = pal.accentCss; g.globalAlpha = pal.light ? 0.5 : 0.42; g.lineWidth = 1; g.beginPath();
    g.arc(x, y, 46 * scale, 0, TAU); g.stroke(); g.globalAlpha = 1;
  }

  function makeNoise(rand) {
    var perm = new Uint8Array(512); var val = new Float32Array(256); var i; var j; var tmp;
    for (i = 0; i < 256; i++) {
      perm[i] = i; val[i] = rand();
    }
    for (i = 255; i > 0; i--) {
      j = (rand() * (i + 1)) | 0; tmp = perm[i]; perm[i] = perm[j]; perm[j] = tmp;
    }
    for (i = 0; i < 256; i++) perm[i + 256] = perm[i];
    return function (x, y) {
      var x0 = Math.floor(x); var y0 = Math.floor(y); var xf = x - x0; var yf = y - y0;
      var u = xf * xf * (3 - 2 * xf); var v = yf * yf * (3 - 2 * yf); x0 &= 255; y0 &= 255;
      var v00 = val[perm[perm[x0] + y0]]; var v10 = val[perm[perm[x0 + 1] + y0]];
      var v01 = val[perm[perm[x0] + y0 + 1]]; var v11 = val[perm[perm[x0 + 1] + y0 + 1]];
      var a = v00 + (v10 - v00) * u; var b = v01 + (v11 - v01) * u; return a + (b - a) * v;
    };
  }

  /* Aurora: slow curtains in the accent and two neighbours, over a faint star field and a low moon. */
  function initAurora(w, h, pal, rand, env) {
    var haze = hazeOf(env.variant); var back = makeCanvas(w, h);
    paintAuroraBack(back.getContext('2d'), w, h, pal, rand, env, haze);
    var specs = [
      { x: 0.0, width: 0.36, phase: 0.4, speed: 0.15, amp: 28, alpha: 0.85, col: 0 },
      { x: 0.14, width: 0.3, phase: 2.2, speed: 0.11, amp: 18, alpha: 0.45, col: 1 },
      { x: 0.56, width: 0.3, phase: 1.1, speed: 0.13, amp: 22, alpha: 0.62, col: 2 },
      { x: 0.72, width: 0.34, phase: 3.5, speed: 0.1, amp: 20, alpha: 0.8, col: 0 }
    ];
    if (!env.calm && !env.tier && w > 900) {
      specs.push({ x: 0.36, width: 0.24, phase: 4.4, speed: 0.09, amp: 12, alpha: 0.28, col: 1 });
    }
    var use = []; var i;
    for (i = 0; i < specs.length; i++) {
      if (env.calm && i === 1) continue;
      if (env.tier && i === 2) continue;
      use.push(specs[i]);
    }
    var curtains = [];
    for (i = 0; i < use.length; i++) curtains.push(buildCurtain(w, h, pal, use[i]));
    var dotX = w * 0.74; var dotY = h * 0.6; var dotS = 9 * env.ratio;
    /* One bow down the curtain. Slice count keeps neighbouring offsets under ~2 px, so no stair-steps. */
    var slices = env.calm ? 32 : 52;
    return {
      frame: function (t) {
        ctx.drawImage(back, 0, 0);
        if (!pal.light) ctx.globalCompositeOperation = 'lighter';
        var c; var k; var s0; var s1; var y0; var y1; var sway; var envl; var u;
        for (i = 0; i < curtains.length; i++) {
          c = curtains[i];
          for (k = 0; k < slices; k++) {
            u = (k + 0.5) / slices;
            s0 = (k * c.sprite.height / slices) | 0;
            s1 = ((k + 1) * c.sprite.height / slices) | 0;
            y0 = (k * h / slices) | 0;
            y1 = ((k + 1) * h / slices) | 0;
            if (s1 <= s0) s1 = s0 + 1;
            if (y1 <= y0) y1 = y0 + 1;
            envl = Math.sin(u * Math.PI);
            sway = Math.sin(t * c.speed * env.speed + c.phase + u * Math.PI) * c.amp * env.speed * env.ratio * envl;
            ctx.globalAlpha = c.alpha * (pal.light ? 0.82 : 0.72);
            ctx.drawImage(c.sprite, 0, s0, c.sprite.width, s1 - s0, c.baseX + sway, y0, c.destW, y1 - y0);
          }
        }
        ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
      },
      mark: function (t) {
        paintDot(ctx, dotX, dotY, dotS, pal, breathOf(t), pal.light ? 0 : 1.4);
      }
    };
  }

  function paintAuroraBack(g, w, h, pal, rand, env, haze) {
    var sky = g.createLinearGradient(0, 0, 0, h);
    sky.addColorStop(0, rgba(pal.raise, pal.light ? 0.05 : 0.18 * haze));
    sky.addColorStop(0.55, rgba(pal.surface, pal.light ? 0.04 : 0.1 * haze));
    sky.addColorStop(1, rgba(pal.bg, 0)); g.fillStyle = sky; g.fillRect(0, 0, w, h);
    var wash = g.createRadialGradient(w * 0.74, h * 0.62, 8, w * 0.7, h * 0.7, w * 0.48);
    wash.addColorStop(0, rgba(pal.accent, pal.light ? 0.05 : 0.08)); wash.addColorStop(1, rgba(pal.accent, 0));
    g.fillStyle = wash; g.fillRect(0, 0, w, h); var n = scaledCount(70, env); var i;
    var x; var y; var s; g.fillStyle = pal.light ? pal.inkCss : pal.textCss;
    for (i = 0; i < n; i++) {
      x = rand() * w; y = rand() * h * 0.78; s = rand() > 0.88 ? 2 * env.ratio : env.ratio;
      g.globalAlpha = (0.15 + rand() * 0.4) * centerQuiet(x, y, w, h) * (pal.light ? 0.45 : 1);
      g.fillRect(x, y, s, s);
    }
    g.globalAlpha = 1;
  }

  function buildCurtain(w, h, pal, spec) {
    var destW = Math.max(8, w * spec.width); var cw = destW > 720 ? 720 : destW | 0;
    var ch = h > 900 ? 900 : h | 0; var sprite = makeCanvas(cw, ch);
    var g = sprite.getContext('2d'); var colors = [pal.accent, pal.warm, pal.cool];
    var col = colors[spec.col] || pal.accent; var a = pal.light ? 0.62 : 0.55;
    /* One soft vertical bell. Two peaks read as horizontal stripes once the curtain is sliced. */
    var grd = g.createLinearGradient(0, 0, 0, ch); grd.addColorStop(0, rgba(col, 0));
    grd.addColorStop(0.16, rgba(col, a * 0.2)); grd.addColorStop(0.4, rgba(col, a));
    grd.addColorStop(0.64, rgba(col, a * 0.38)); grd.addColorStop(0.88, rgba(col, 0));
    g.fillStyle = grd;
    g.fillRect(0, 0, cw, ch); g.globalCompositeOperation = 'destination-in';
    var mask = g.createLinearGradient(0, 0, cw, 0); mask.addColorStop(0, 'rgba(0,0,0,0)');
    mask.addColorStop(0.2, 'rgba(0,0,0,0.45)'); mask.addColorStop(0.5, 'rgba(0,0,0,1)');
    mask.addColorStop(0.8, 'rgba(0,0,0,0.45)'); mask.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = mask; g.fillRect(0, 0, cw, ch);
    return {
      sprite: sprite,
      destW: destW,
      baseX: w * spec.x,
      phase: spec.phase,
      speed: spec.speed,
      amp: spec.amp,
      alpha: spec.alpha
    };
  }

  /* Stars: three parallax depths, a baked galactic band, a rare soft streak, one lantern. */
  function initStars(w, h, pal, rand, env) {
    var back = makeCanvas(w, h); paintGalaxy(back.getContext('2d'), w, h, pal, rand);
    var layers = [
      makeStarLayer(scaledCount(78, env), w, h, env, rand, 0.28, 1.6),
      makeStarLayer(scaledCount(36, env), w, h, env, rand, 0.42, 4.2),
      makeStarLayer(scaledCount(14, env), w, h, env, rand, 0.62, 8)
    ]; var dotX = w * 0.78; var dotY = h * 0.28; var dotS = 9.2 * env.ratio;
    var shoot = { age: -1, wait: 18, x: 0, y: 0, vx: 0, vy: 0, dur: 1.35, n: 0 };
    var allowShot = !env.calm && !env.tier;
    return {
      frame: function (t, dt) {
        ctx.drawImage(back, 0, 0); var li; var list; var i;
        var s; var tw; ctx.fillStyle = pal.light ? pal.inkCss : pal.textCss;
        for (li = 0; li < layers.length; li++) {
          list = layers[li];
          for (i = 0; i < list.length; i++) {
            s = list[i]; s.x -= s.speed * env.speed * dt;
            if (s.x < -4) s.x += w + 8;
            tw = 0.72 + 0.28 * Math.sin(t * s.hz + s.p);
            ctx.globalAlpha = s.a * tw * centerQuiet(s.x, s.y, w, h); ctx.fillRect(s.x, s.y, s.sz, s.sz);
          }
        }
        if (allowShot) drawShot(ctx, shoot, t, dt, w, h, env, pal);
        ctx.globalAlpha = 1;
      },
      mark: function (t) {
        paintDot(ctx, dotX, dotY, dotS, pal, breathOf(t), pal.light ? 0 : 1.3); var a1 = t * (TAU / 56);
        var a2 = t * (TAU / 92) + 1.4; ctx.fillStyle = pal.light ? pal.inkCss : pal.accentCss;
        ctx.globalAlpha = pal.light ? 0.75 : 0.8; var s = Math.max(1.2, 1.6 * env.ratio);
        ctx.fillRect(dotX + Math.cos(a1) * 30 * env.ratio - s, dotY + Math.sin(a1) * 12 * env.ratio - s, s * 2, s * 2);
        ctx.globalAlpha = pal.light ? 0.55 : 0.6; s = Math.max(1, 1.2 * env.ratio);
        ctx.fillRect(dotX + Math.cos(a2) * 48 * env.ratio - s, dotY + Math.sin(a2) * 18 * env.ratio - s, s * 2, s * 2);
        ctx.globalAlpha = 1;
      }
    };
  }

  function makeStarLayer(n, w, h, env, rand, alphaBase, speed) {
    var list = []; var i;
    for (i = 0; i < n; i++) {
      list.push({
        x: rand() * w,
        y: rand() * h * 0.9,
        sz: (rand() > 0.9 ? 2.2 : 1.15) * env.ratio,
        a: alphaBase + rand() * 0.35,
        p: rand() * TAU,
        hz: 0.32 + rand() * 0.4,
        speed: speed * (0.75 + rand() * 0.5)
      });
    }
    return list;
  }

  function paintGalaxy(g, w, h, pal, rand) {
    var color = mix(pal.raise, pal.accent, pal.light ? 0.08 : 0.22); g.save();
    g.translate(w * 0.46, h * 0.4); g.rotate(-0.36); var i;
    for (i = 0; i < 7; i++) {
      var x = (i - 3) * w * 0.12 + (rand() - 0.5) * w * 0.04; var y = (rand() - 0.5) * h * 0.05;
      var rw = w * (0.16 + rand() * 0.12); var rh = h * (0.035 + rand() * 0.03);
      var grd = g.createRadialGradient(x, y, 0, x, y, rw);
      grd.addColorStop(0, rgba(color, pal.light ? 0.05 : 0.1)); grd.addColorStop(1, rgba(color, 0));
      g.fillStyle = grd; g.beginPath(); g.ellipse(x, y, rw, rh, 0, 0, TAU); g.fill();
    }
    g.restore(); g.fillStyle = pal.deepCss; g.globalAlpha = pal.light ? 0.08 : 0.88; g.beginPath();
    g.moveTo(0, h); var x2; var u;
    for (x2 = 0; x2 <= w; x2 += 20) {
      u = x2 / w; g.lineTo(x2, h * (0.9 + 0.07 * Math.sin(u * Math.PI)));
    }
    g.lineTo(w, h); g.closePath(); g.fill(); g.globalAlpha = 1;
  }

  function drawShot(g, shoot, t, dt, w, h, env, pal) {
    if (shoot.age < 0) {
      shoot.wait -= dt;
      if (shoot.wait > 0) return;
      shoot.age = 0; shoot.n += 1;
      var dir = shoot.n % 2 === 0 ? 1 : -1; shoot.x = dir > 0 ? w * 0.12 : w * 0.82;
      shoot.y = h * (0.1 + (shoot.n % 3) * 0.06); shoot.vx = dir * w * 0.42; shoot.vy = h * 0.16; return;
    }
    shoot.age += dt; shoot.x += shoot.vx * dt; shoot.y += shoot.vy * dt; var life = shoot.age / 1.35;
    if (life > 1) {
      shoot.age = -1; shoot.wait = 22 + (shoot.n % 6) * 3; return;
    }
    var envl = Math.sin(life * Math.PI); var mag = Math.sqrt(shoot.vx * shoot.vx + shoot.vy * shoot.vy) || 1;
    var len = 170 * env.ratio; var i; g.strokeStyle = pal.light ? pal.inkCss : pal.textCss; g.lineWidth = 1;
    g.beginPath();
    for (i = 0; i <= 6; i++) {
      g.globalAlpha = envl * (1 - i / 6) * (pal.light ? 0.22 : 0.32);
      var bx = shoot.x - (shoot.vx / mag) * len * (i / 6); var by = shoot.y - (shoot.vy / mag) * len * (i / 6);
      if (i) g.lineTo(bx, by);
      else g.moveTo(bx, by);
      g.stroke(); g.beginPath(); g.moveTo(bx, by);
    }
    g.globalAlpha = envl * 0.7; g.fillStyle = pal.dotCss;
    var s = Math.max(1, env.ratio); g.fillRect(shoot.x - s, shoot.y - s, s * 2, s * 2);
  }

  /* Forest: parallax pines sway (the valley stays put), fog travels, fireflies and a lantern shaft. */
  function initForest(w, h, pal, rand, env) {
    var haze = hazeOf(env.variant); var sky = makeCanvas(w, h);
    var sg = sky.getContext('2d'); var skyG = sg.createLinearGradient(0, 0, 0, h);
    if (pal.light) {
      skyG.addColorStop(0, rgba(mix(pal.bg, pal.ink, 0.08), 0.45)); skyG.addColorStop(0.5, rgba(pal.bg, 0));
      skyG.addColorStop(1, rgba(pal.bg, 0));
    } else {
      skyG.addColorStop(0, rgba(mix(pal.bg, pal.raise, 0.55), 0.28 * haze));
      skyG.addColorStop(0.42, rgba(mix(pal.surface, pal.bg, 0.3), 0.22 * haze));
      skyG.addColorStop(1, rgba(pal.bg, 0));
    }
    sg.fillStyle = skyG; sg.fillRect(0, 0, w, h);

    var farC = pal.light ? mix(pal.ink, pal.bg, 0.72) : mix(pal.bg, pal.surface, 0.85);
    var midC = pal.light ? mix(pal.ink, pal.bg, 0.55) : mix(pal.bg, { r: 0, g: 0, b: 0 }, 0.28);
    var nearC = pal.light ? mix(pal.ink, pal.bg, 0.4) : mix(pal.bg, { r: 0, g: 0, b: 0 }, 0.48);
    var frontC = pal.light ? mix(pal.ink, pal.bg, 0.28) : mix(pal.bg, { r: 0, g: 0, b: 0 }, 0.66);
    var layerSpecs = [
      { base: 0.63, dip: 0.055, amp: 0.012, trees: scaledCount(40, env), hMin: 0.04, hMax: 0.085, sway: 8, period: 78, phase: rand() * TAU, color: farC },
      { base: 0.72, dip: 0.06, amp: 0.015, trees: scaledCount(28, env), hMin: 0.07, hMax: 0.13, sway: 13, period: 58, phase: rand() * TAU, color: midC },
      { base: 0.83, dip: 0.045, amp: 0.018, trees: scaledCount(18, env), hMin: 0.11, hMax: 0.2, sway: 18, period: 46, phase: rand() * TAU, color: nearC }
    ];
    if (!env.calm) {
      layerSpecs.push({ base: 0.94, dip: 0.02, amp: 0.012, trees: scaledCount(12, env), hMin: 0.16, hMax: 0.26, sway: 24, period: 38, phase: rand() * TAU, color: frontC });
    }
    var ridges = []; var i;
    for (i = 0; i < layerSpecs.length; i++) ridges.push(buildRidge(w, h, layerSpecs[i], env.ratio, rand));

    var fogA = pal.light ? mix(pal.bg, { r: 255, g: 255, b: 255 }, 0.4) : mix(pal.mist, pal.surface, 0.45);
    var fogs = [
      { img: buildFog(w, Math.round(h * 0.2), fogA, rand, pal.light ? 0.1 : 0.16 * haze), y: h * 0.48, speed: 7 * env.ratio },
      { img: buildFog(w, Math.round(h * 0.18), fogA, rand, pal.light ? 0.08 : 0.12 * haze), y: h * 0.6, speed: 12 * env.ratio },
      { img: buildFog(w, Math.round(h * 0.16), fogA, rand, pal.light ? 0.06 : 0.1 * haze), y: h * 0.72, speed: 18 * env.ratio }
    ];

    var dotX = w * 0.76; var dotY = h * 0.2; var dotS = 9 * env.ratio; var shaftW = w * 0.34;
    var shaftH = h * 0.5;
    var shaft = makeShaft(Math.round(Math.min(shaftW, 520)), Math.round(Math.min(shaftH, 480)), pal.accent, pal.light);

    var flyN = scaledCount(32, env); var flies = [];
    for (i = 0; i < flyN; i++) {
      var u = rand(); u = u < 0.5 ? Math.pow(u * 2, 1.5) * 0.5 : 1 - Math.pow((1 - u) * 2, 1.5) * 0.5;
      flies.push({
        bx: u * w,
        by: h * (0.55 + rand() * 0.38),
        rx: (10 + rand() * 28) * env.ratio,
        ry: (6 + rand() * 16) * env.ratio,
        p1: rand() * TAU,
        p2: rand() * TAU,
        p3: rand() * TAU,
        s1: 0.12 + rand() * 0.1,
        s2: 0.1 + rand() * 0.08,
        s3: 0.35 + rand() * 0.35,
        sz: (1.1 + rand() * 1.3) * env.ratio
      });
    }
    var moteN = scaledCount(14, env); var motes = [];
    for (i = 0; i < moteN; i++) {
      motes.push({
        u: rand(),
        p: rand(),
        drift: rand() * TAU,
        speed: 0.018 + rand() * 0.02,
        sz: (0.7 + rand()) * env.ratio
      });
    }

    return {
      frame: function (t) {
        ctx.drawImage(sky, 0, 0); var r; var shift;
        for (i = 0; i < ridges.length; i++) {
          r = ridges[i]; shift = Math.sin(t * (TAU / r.period) * env.speed + r.phase) * r.sway * env.speed;
          ctx.drawImage(r.canvas, -r.pad + shift, r.top);
          if (i < fogs.length) {
            var fog = fogs[i]; var fs = (t * fog.speed * env.speed) % w;
            ctx.drawImage(fog.img, -fs, fog.y); ctx.drawImage(fog.img, w - fs, fog.y);
          }
        }
        ctx.save(); ctx.translate(dotX, dotY);
        ctx.rotate(0.14); ctx.globalAlpha = (0.45 + 0.25 * breathOf(t)) * (pal.light ? 0.28 : 0.75);
        ctx.drawImage(shaft, -shaftW * 0.42, 0, shaftW, shaftH); ctx.restore();
        ctx.fillStyle = pal.light ? pal.accentCss : pal.textCss; var m; var p; var xx; var yy;
        for (i = 0; i < motes.length; i++) {
          m = motes[i]; p = (m.p + t * m.speed * env.speed) % 1; yy = dotY + p * shaftH;
          xx = dotX + p * w * 0.05 + (m.u - 0.5) * shaftW * (0.15 + p) + Math.sin(t * 0.25 + m.drift) * 5 * env.ratio;
          ctx.globalAlpha = Math.sin(p * Math.PI) * (pal.light ? 0.18 : 0.28); ctx.fillRect(xx, yy, m.sz, m.sz);
        }
        var f; var pulse; ctx.fillStyle = pal.accentCss;
        for (i = 0; i < flies.length; i++) {
          f = flies[i]; xx = f.bx + Math.sin(t * f.s1 * env.speed + f.p1) * f.rx;
          yy = f.by + Math.cos(t * f.s2 * env.speed + f.p2) * f.ry;
          pulse = 0.3 + 0.7 * (0.5 + 0.5 * Math.sin(t * f.s3 + f.p3));
          ctx.globalAlpha = pulse * 0.55 * centerQuiet(xx, yy, w, h);
          if (!pal.light) {
            ctx.beginPath(); ctx.arc(xx, yy, f.sz * 2.4, 0, TAU); ctx.fill();
          }
          ctx.globalAlpha = pulse * (pal.light ? 0.7 : 0.9) * centerQuiet(xx, yy, w, h);
          ctx.fillRect(xx - f.sz * 0.5, yy - f.sz * 0.5, f.sz, f.sz);
        }
        ctx.globalAlpha = 1;
      },
      mark: function (t) {
        paintDot(ctx, dotX, dotY, dotS, pal, breathOf(t), pal.light ? 0 : 1.2);
      }
    };
  }

  function buildRidge(w, h, spec, scale, rand) {
    var pad = Math.round(40 * scale); var maxTree = h * spec.hMax;
    var baseY = h * spec.base; var top = baseY - h * spec.dip - h * spec.amp - maxTree - 4;
    if (top < 0) top = 0;
    var sw = (w + pad * 2) | 0; var sh = Math.max(1, Math.ceil(h - top));
    var c = makeCanvas(sw, sh); var g = c.getContext('2d');
    function ground(sx) {
      var u = sx / w; var bowl = Math.exp(-Math.pow((u - 0.5) / 0.3, 2));
      return (baseY - top) + bowl * h * spec.dip + Math.sin(u * TAU + spec.phase) * h * spec.amp + Math.sin(u * TAU * 2 + spec.phase * 1.3) * h * spec.amp * 0.35;
    }
    g.fillStyle = rgbCss(spec.color); g.beginPath(); g.moveTo(0, sh); var x; var step = Math.max(6, 7 * scale);
    for (x = 0; x <= sw; x += step) g.lineTo(x, ground(x - pad));
    g.lineTo(sw, ground(w + pad)); g.lineTo(sw, sh); g.closePath(); g.fill();
    g.beginPath(); var i; var u; var px; var th;
    for (i = 0; i < spec.trees; i++) {
      u = rand(); u = u < 0.5 ? Math.pow(u * 2, 1.45) * 0.5 : 1 - Math.pow((1 - u) * 2, 1.45) * 0.5;
      px = u * w; th = h * (spec.hMin + rand() * (spec.hMax - spec.hMin));
      th *= 0.5 + 0.5 * Math.min(1, Math.abs(u - 0.5) * 2.6); pinePath(g, px + pad, ground(px), th);
    }
    g.fill();
    return { canvas: c, pad: pad, top: top, sway: spec.sway * scale, period: spec.period, phase: spec.phase };
  }

  function pinePath(g, x, gy, th) {
    var trunk = th * 0.14; var tw = Math.max(0.7, th * 0.04); g.moveTo(x - tw, gy); g.lineTo(x + tw, gy);
    g.lineTo(x + tw, gy - trunk); g.lineTo(x - tw, gy - trunk); g.closePath(); var y = gy - trunk * 0.5;
    var k; var lw; var lh;
    for (k = 0; k < 3; k++) {
      lw = th * (0.36 - k * 0.075); lh = th * 0.34; g.moveTo(x, y - lh); g.lineTo(x + lw, y + 1);
      g.lineTo(x - lw, y + 1); g.closePath(); y -= lh * 0.46;
    }
  }

  function buildFog(w, h, color, rand, alpha) {
    var c = makeCanvas(w, Math.max(8, h)); var g = c.getContext('2d'); var i;
    function puff(cx, cy, rw, rh, a) {
      var grd = g.createRadialGradient(cx, cy, 0, cx, cy, rw); grd.addColorStop(0, rgba(color, a));
      grd.addColorStop(1, rgba(color, 0)); g.fillStyle = grd;
      g.beginPath(); g.ellipse(cx, cy, rw, rh, 0, 0, TAU); g.fill();
    }
    for (i = 0; i < 5; i++) {
      var x = rand() * w; var y = h * (0.3 + rand() * 0.4);
      var rw = w * (0.2 + rand() * 0.22); var rh = h * (0.28 + rand() * 0.24);
      var a = alpha * (0.65 + rand() * 0.5); puff(x, y, rw, rh, a);
      puff(x - w, y, rw, rh, a); puff(x + w, y, rw, rh, a);
    }
    return c;
  }

  function makeShaft(sw, sh, color, light) {
    var c = makeCanvas(sw, sh); var g = c.getContext('2d');
    var grd = g.createLinearGradient(sw * 0.5, 0, sw * 0.5, sh);
    grd.addColorStop(0, rgba(color, light ? 0.08 : 0.16)); grd.addColorStop(1, rgba(color, 0));
    g.fillStyle = grd; g.beginPath(); g.moveTo(sw * 0.46, 0); g.lineTo(sw * 0.54, 0);
    g.lineTo(sw, sh); g.lineTo(0, sh); g.closePath(); g.fill(); return c;
  }

  /* Harbor: edge quays, rare window breaths, shimmering water, one boat, a moon on the fairway. */
  function initHarbor(w, h, pal, rand, env) {
    var horizon = h * 0.58; var back = makeCanvas(w, h);
    var wins = []; paintHarborBack(back.getContext('2d'), w, h, pal, rand, env, horizon, wins);
    var dotX = w * 0.76; var dotY = h * 0.22;
    var dotS = 8.6 * env.ratio; var lineStep = Math.max(8, ((h - horizon) / (env.calm ? 11 : 16)) | 0);
    var xStep = Math.max(32, (w / (env.calm ? 28 : 42)) | 0); var reflectN = wins.length;
    return {
      frame: function (t) {
        ctx.drawImage(back, 0, 0); var y; var x; var depth;
        var amp; var yy; ctx.strokeStyle = pal.light ? pal.inkCss : pal.textCss; ctx.lineWidth = 1;
        for (y = horizon + 8; y < h; y += lineStep) {
          depth = (y - horizon) / (h - horizon); amp = (3 + depth * 8) * env.ratio;
          ctx.globalAlpha = (0.08 + depth * 0.16) * (pal.light ? 0.75 : 1) * (0.55 + 0.45 * centerQuiet(w * 0.5, y, w, h));
          ctx.beginPath();
          for (x = 0; x <= w; x += xStep) {
            yy = y + Math.sin(x * 0.0075 + t * 0.32 * env.speed) * amp + Math.sin(x * 0.019 + y * 0.03 + t * 0.51 * env.speed) * amp * 0.35;
            if (x === 0) ctx.moveTo(0, yy);
            else ctx.lineTo(x, yy);
          }
          ctx.stroke();
        }
        var i; var win; var local; var bright; var len;
        for (i = 0; i < reflectN; i++) {
          win = wins[i]; local = (t + win.phase) % win.period; bright = 1;
          if (local < win.dur) bright = 1 - 0.4 * Math.sin((local / win.dur) * Math.PI);
          ctx.globalAlpha = (win.lamp ? 0.85 : 0.7) * bright;
          ctx.fillStyle = win.lamp && !pal.light ? pal.dotCss : pal.accentCss;
          ctx.fillRect(win.x, win.y, win.s, win.s);
          len = (win.lamp ? h * 0.28 : h * 0.16) * (0.7 + 0.3 * bright);
          streak(ctx, win.x, horizon, len, pal.accentCss, (win.lamp ? 0.16 : 0.07) * bright, win.phase, t, env);
        }
        streak(ctx, dotX, horizon, h * 0.34, pal.accentCss, pal.light ? 0.08 : 0.14, 0.4, t, env);
        drawBoat(ctx, t, w, horizon, env, pal); ctx.globalAlpha = 1;
      },
      mark: function (t) {
        paintDot(ctx, dotX, dotY, dotS, pal, breathOf(t), pal.light ? 0 : 1.25);
      }
    };
  }

  function paintHarborBack(g, w, h, pal, rand, env, horizon, wins) {
    var sky = g.createLinearGradient(0, 0, 0, horizon);
    sky.addColorStop(0, rgba(pal.raise, pal.light ? 0.04 : 0.16));
    sky.addColorStop(1, rgba(pal.accent, pal.light ? 0.03 : 0.06)); g.fillStyle = sky;
    g.fillRect(0, 0, w, horizon); var water = g.createLinearGradient(0, horizon, 0, h);
    water.addColorStop(0, rgba(pal.surface, pal.light ? 0.18 : 0.35));
    water.addColorStop(1, rgba(pal.deep, pal.light ? 0.12 : 0.45)); g.fillStyle = water;
    g.fillRect(0, horizon, w, h - horizon);
    var haze = g.createLinearGradient(0, horizon - h * 0.12, 0, horizon + h * 0.08);
    haze.addColorStop(0, rgba(pal.mist, 0)); haze.addColorStop(0.6, rgba(pal.mist, pal.light ? 0.08 : 0.16));
    haze.addColorStop(1, rgba(pal.mist, 0)); g.fillStyle = haze; g.fillRect(0, horizon - h * 0.12, w, h * 0.2);

    g.fillStyle = pal.deepCss; g.globalAlpha = pal.light ? 0.55 : 0.92;
    var clusters = [[0.02, 0.36], [0.64, 0.98]]; var blocks = []; var ci; var x; var bw; var bh;
    for (ci = 0; ci < clusters.length; ci++) {
      x = w * clusters[ci][0];
      while (x < w * clusters[ci][1]) {
        bw = (26 + rand() * 64) * env.ratio; bh = (16 + rand() * (rand() > 0.82 ? 78 : 42)) * env.ratio;
        g.fillRect(x, horizon - bh, bw, bh);
        blocks.push({ x: x, y: horizon - bh, w: bw, h: bh });
        x += bw + (5 + rand() * 12) * env.ratio;
      }
    }
    crane(g, w * 0.08, horizon, env.ratio, rand); crane(g, w * 0.9, horizon, env.ratio, rand);
    g.globalAlpha = 1; g.fillStyle = pal.light ? pal.inkCss : pal.deepCss;
    g.globalAlpha = pal.light ? 0.35 : 0.8; g.fillRect(0, horizon - 1, w * 0.38, Math.max(2, env.ratio));
    g.fillRect(w * 0.62, horizon - 1, w * 0.38, Math.max(2, env.ratio)); g.globalAlpha = 1;

    var maxWins = scaledCount(16, env); var i; var b;
    if (!blocks.length) return;
    for (i = 0; i < maxWins; i++) {
      b = blocks[(rand() * blocks.length) | 0];
      if (b.h < 12 * env.ratio || b.w < 10 * env.ratio) continue;
      wins.push({
        x: b.x + 3 * env.ratio + rand() * Math.max(1, b.w - 7 * env.ratio),
        y: b.y + 3 * env.ratio + rand() * Math.max(1, b.h - 7 * env.ratio),
        s: Math.max(1.2, 1.6 * env.ratio),
        phase: rand() * 70,
        period: 34 + rand() * 48,
        dur: 1.8,
        lamp: 0
      });
    }
    wins.push({ x: w * 0.08, y: horizon - 78 * env.ratio, s: Math.max(1.5, 2 * env.ratio), phase: 2, period: 70, dur: 2, lamp: 1 });
    wins.push({ x: w * 0.9, y: horizon - 64 * env.ratio, s: Math.max(1.5, 2 * env.ratio), phase: 11, period: 80, dur: 2, lamp: 1 });
  }

  function crane(g, x, horizon, scale, rand) {
    var mast = (58 + rand() * 24) * scale; var boom = (46 + rand() * 20) * scale;
    var thick = Math.max(1.5, 2.2 * scale); g.fillRect(x, horizon - mast, thick, mast);
    g.fillRect(x - boom, horizon - mast, boom + 16 * scale, thick);
    g.fillRect(x - 3 * scale, horizon - mast - 5 * scale, 8 * scale, 5 * scale);
  }

  function streak(g, x, y0, len, css, alpha, phase, t, env) {
    var n = 8; var i; var k; var y;
    var xx; g.strokeStyle = css; g.lineWidth = Math.max(1, env.ratio); g.beginPath();
    for (i = 0; i <= n; i++) {
      k = i / n; y = y0 + len * k * k;
      xx = x + Math.sin(t * 0.8 * env.speed + phase + k * 5) * (2.4 * env.ratio * (0.3 + k));
      if (i === 0) g.moveTo(xx, y);
      else g.lineTo(xx, y);
    }
    g.globalAlpha = alpha; g.stroke();
  }

  function drawBoat(g, t, w, horizon, env, pal) {
    var travel = w + 220 * env.ratio;
    var bx = ((t * 12 * env.speed * env.ratio + w * 0.58) % travel) - 80 * env.ratio;
    var edge = bx / (90 * env.ratio); var edgeR = (w - bx) / (90 * env.ratio);
    if (edgeR < edge) edge = edgeR;
    if (edge < 0) edge = 0;
    if (edge > 1) edge = 1;
    g.globalAlpha = 0.55 * edge * (pal.light ? 0.6 : 1); g.fillStyle = pal.deepCss;
    g.fillRect(bx, horizon - 3 * env.ratio, 34 * env.ratio, 3 * env.ratio); g.globalAlpha = 0.8 * edge;
    g.fillStyle = pal.accentCss; var s = Math.max(1, 1.4 * env.ratio);
    g.fillRect(bx + 4 * env.ratio, horizon - 6 * env.ratio, s, s);
    g.fillRect(bx + 26 * env.ratio, horizon - 5.5 * env.ratio, s, s);
  }

  /* Grid: a perspective floor. Latitudes use a power curve. Pulses are wide and slow, never a scanline. */
  function initGrid(w, h, pal, rand, env) {
    var horizon = h * 0.76; var vanishX = w * 0.5; var back = makeCanvas(w, h); var bg = back.getContext('2d');
    var glow = bg.createRadialGradient(vanishX, horizon, 4, vanishX, horizon, w * 0.42);
    glow.addColorStop(0, rgba(pal.accent, pal.light ? 0.06 : 0.1));
    glow.addColorStop(0.4, rgba(pal.accent, pal.light ? 0.025 : 0.04));
    glow.addColorStop(1, rgba(pal.accent, 0)); bg.fillStyle = glow;
    bg.fillRect(0, 0, w, h); var lineCol = pal.light ? pal.ink : pal.text;
    var vgrad = ctx.createLinearGradient(0, horizon, 0, h); vgrad.addColorStop(0, rgba(lineCol, 0));
    vgrad.addColorStop(0.16, rgba(lineCol, pal.light ? 0.22 : 0.2));
    vgrad.addColorStop(1, rgba(lineCol, pal.light ? 0.4 : 0.34)); var cols = env.calm || env.tier ? 5 : 9;
    var rows = env.calm || env.tier ? 7 : 12; var band = makeBand(pal.accent);
    var moteN = scaledCount(12, env); var motes = []; var i;
    for (i = 0; i < moteN; i++) {
      var side = rand() > 0.5 ? 1 : -1;
      motes.push({
        x: vanishX + side * (0.22 + rand() * 0.28) * w,
        phase: rand(),
        speed: 0.035 + rand() * 0.03,
        sz: (1.1 + rand() * 0.8) * env.ratio,
        drift: rand() * TAU
      });
    }
    var dotX = vanishX; var dotY = horizon - 18 * env.ratio; var dotS = 8.4 * env.ratio; var span = h - horizon;
    return {
      frame: function (t) {
        ctx.drawImage(back, 0, 0); var travel = (t * 0.055 * env.speed) % 1; var i2; var d;
        var y; var k; var half; ctx.strokeStyle = vgrad;
        ctx.lineWidth = 1; ctx.globalAlpha = 1; ctx.beginPath();
        for (i2 = 0; i2 < rows; i2++) {
          d = (i2 / rows + travel) % 1; y = horizon + Math.pow(d, 1.65) * span;
          k = (y - horizon) / span; half = w * 0.78 * Math.pow(Math.max(0.001, k), 0.82);
          ctx.moveTo(vanishX - half, y); ctx.lineTo(vanishX + half, y);
        }
        ctx.stroke(); ctx.beginPath(); var nx;
        for (i2 = 1; i2 <= cols; i2++) {
          nx = i2 / cols; ctx.moveTo(vanishX, horizon);
          ctx.lineTo(vanishX + nx * w * 0.78, h); ctx.moveTo(vanishX, horizon);
          ctx.lineTo(vanishX - nx * w * 0.78, h);
        }
        ctx.stroke(); drawPulse(ctx, (t * env.speed) % 14 / 14, horizon, span, vanishX, w, band, pal);
        drawPulse(ctx, (t * env.speed + 7) % 14 / 14, horizon, span, vanishX, w, band, pal); var m;
        var life; var my; var mx; ctx.fillStyle = pal.accentCss;
        for (i2 = 0; i2 < motes.length; i2++) {
          m = motes[i2]; life = (m.phase + t * m.speed * env.speed) % 1;
          my = h - life * (span + 40 * env.ratio); mx = m.x + Math.sin(t * 0.2 + m.drift) * 5 * env.ratio;
          ctx.globalAlpha = Math.sin(life * Math.PI) * (pal.light ? 0.28 : 0.4) * centerQuiet(mx, my, w, h);
          ctx.fillRect(mx, my, m.sz, m.sz);
        }
        ctx.globalAlpha = 1;
      },
      mark: function (t) {
        paintDot(ctx, dotX, dotY, dotS, pal, breathOf(t), pal.light ? 0 : 0.72);
      }
    };
  }

  function makeBand(color) {
    var c = makeCanvas(8, 64); var g = c.getContext('2d');
    var grd = g.createLinearGradient(0, 0, 0, 64); grd.addColorStop(0, rgba(color, 0));
    grd.addColorStop(0.5, rgba(color, 0.4)); grd.addColorStop(1, rgba(color, 0));
    g.fillStyle = grd; g.fillRect(0, 0, 8, 64); return c;
  }

  function drawPulse(g, d, horizon, span, vanishX, w, band, pal) {
    if (d <= 0 || d >= 1) return;
    var y = horizon + Math.pow(d, 1.65) * span; var k = (y - horizon) / span;
    var half = w * 0.78 * Math.pow(Math.max(0.001, k), 0.82); var bandH = span * 0.16;
    g.globalAlpha = Math.sin(d * Math.PI) * (pal.light ? 0.34 : 0.42);
    g.drawImage(band, vanishX - half, y - bandH * 0.5, half * 2, bandH);
  }

  var BUILD = {
    contours: initContours,
    aurora: initAurora,
    stars: initStars,
    forest: initForest,
    harbor: initHarbor,
    grid: initGrid
  };

  function viewportOk() {
    return window.innerWidth >= 320 && window.innerHeight >= 320;
  }

  function reducedMotion() {
    try {
      return !!(motionQuery && motionQuery.matches);
    } catch (err) {
      return false;
    }
  }

  function readInputs() {
    var el = document.documentElement;
    var scene = (el.getAttribute('data-techtyl-scene') || '').trim().toLowerCase();
    var motion = (el.getAttribute('data-techtyl-motion') || 'voll').trim().toLowerCase();
    var variant = (el.getAttribute('data-techtyl-variant') || 'werft').trim().toLowerCase();
    if (SCENE_KEYS.indexOf(scene) === -1) scene = 'none';
    if (motion !== 'voll' && motion !== 'dezent' && motion !== 'aus') motion = 'voll';
    return { scene: scene, motion: motion, variant: variant, reduced: reducedMotion() };
  }

  function pixelRatio(heavy) {
    var dpr = window.devicePixelRatio || 1;
    if (dpr < 1) dpr = 1;
    if (dpr > 1.25) dpr = 1.25;
    if (heavy || tier >= 1) dpr *= 0.5;
    return dpr;
  }

  function ensureCanvas() {
    if (!host) return false;
    if (!canvas) {
      canvas = document.createElement('canvas'); canvas.id = 'techtyl-scene';
      canvas.setAttribute('aria-hidden', 'true');
      canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;image-rendering:auto;background:transparent';
    }
    if (canvas.parentNode !== host) {
      if (host.firstChild) host.insertBefore(canvas, host.firstChild);
      else host.appendChild(canvas);
    } else if (host.firstChild !== canvas) {
      host.insertBefore(canvas, host.firstChild);
    }
    ctx = canvas.getContext('2d', { alpha: true });
    return !!ctx;
  }

  function removeCanvas() {
    hiding = false; fadeLeft = 0; haveSnap = false; current = null; overlay = null;
    if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
    canvas = null; ctx = null;
  }

  function stopLoop() {
    running = false;
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
  }

  function startLoop() {
    if (running) return;
    running = true; lastTs = 0; acc = 0; raf = requestAnimationFrame(loop);
  }

  function takeSnap() {
    if (!canvas || !canvas.width) return;
    if (!snap) snap = document.createElement('canvas');
    if (snap.width !== canvas.width || snap.height !== canvas.height) {
      snap.width = canvas.width; snap.height = canvas.height;
    }
    var sctx = snap.getContext('2d'); sctx.clearRect(0, 0, snap.width, snap.height);
    sctx.drawImage(canvas, 0, 0); haveSnap = true;
  }

  function paint(t, dt, fadeDt) {
    if (!ctx || !current) return;
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, bufW, bufH);
    ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
    ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = 1; current.frame(t, dt);
    ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
    if (overlay) ctx.drawImage(overlay, 0, 0, bufW, bufH);
    if (current.mark) current.mark(t, dt);
    ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
    if (fadeLeft > 0 && haveSnap && snap) {
      var a = fadeLeft / FADE_MS;
      if (a > 1) a = 1;
      ctx.globalAlpha = a; ctx.drawImage(snap, 0, 0, bufW, bufH); ctx.globalAlpha = 1;
      if (fadeDt) {
        fadeLeft -= fadeDt;
        if (fadeLeft < 0) fadeLeft = 0;
      }
    }
  }

  function paintSafe(t, dt, fadeDt) {
    try {
      paint(t, dt, fadeDt);
    } catch (err) {
      stopLoop();
    }
  }

  function meter(cost, dtMs) {
    if (tier >= 2 || hiding) return;
    if (meterWarm > 0) {
      meterWarm--; return;
    }
    meterT += dtMs; meterSum += cost; meterN++;
    if (meterT < 2000 || meterN < 8) return;
    var avg = meterSum / meterN; meterT = 0; meterSum = 0; meterN = 0;
    if (avg > 24) {
      strikes++;
      if (strikes === 1) tier = 1;
      else tier = 2;
      builtSig = ''; meterWarm = 8;
      if (!applying) {
        applying = true;
        try { syncBody(); } catch (err2) { /* keep the page alive */ }
        applying = false;
      }
    }
  }

  function loop(ts) {
    if (!running) return;
    raf = requestAnimationFrame(loop);
    if (document.hidden || !viewportOk()) {
      stopLoop(); return;
    }
    if (!lastTs) {
      lastTs = ts; return;
    }
    var delta = ts - lastTs; lastTs = ts;
    if (delta < 0) delta = 0;
    if (delta > 80) delta = 80;
    if (hiding) {
      fadeLeft -= delta; var op = fadeLeft / FADE_MS;
      if (op < 0) op = 0;
      if (canvas) canvas.style.opacity = String(op);
      if (fadeLeft <= 0) {
        removeCanvas(); stopLoop();
      }
      return;
    }
    acc += delta;
    if (acc < STEP_MS) return;
    var dtMs = acc > 68 ? 68 : acc; acc = 0; clock += dtMs / 1000; var t0 = performance.now();
    paintSafe(clock, dtMs / 1000, dtMs); meter(performance.now() - t0, dtMs);
  }

  function syncBody() {
    host = document.getElementById('techtyl-backdrop');
    if (!host) return;
    var next = readInputs(); var instant = next.reduced || next.motion === 'aus';
    if (next.scene === 'none') {
      builtSig = 'none';
      if (!canvas) {
        stopLoop(); return;
      }
      current = null;
      if (instant) {
        removeCanvas(); stopLoop();
      } else if (!hiding) {
        hiding = true; fadeLeft = FADE_MS; startLoop();
      }
      return;
    }
    if (!viewportOk()) {
      stopLoop(); return;
    }
    if (!ensureCanvas()) return;
    hiding = false; var heavy = !!HEAVY[next.scene] || tier >= 1;
    ratio = pixelRatio(heavy); var rect = host.getBoundingClientRect();
    var cssW = rect.width || window.innerWidth; var cssH = rect.height || window.innerHeight;
    if (cssW < 2 || cssH < 2) return;
    var nextW = Math.max(1, Math.round(cssW * ratio)); var nextH = Math.max(1, Math.round(cssH * ratio));
    var pal = readPalette(next.variant); var still = instant || tier >= 2;
    var sig = next.scene + '|' + next.motion + '|' + next.variant + '|' + (next.reduced ? '1' : '0') + '|' + tier + '|' + nextW + 'x' + nextH + '|' + palSig(pal);
    if (sig === builtSig && current) return;
    var had = !!(current && canvas.width);
    if (had && !instant) takeSnap();
    else haveSnap = false;
    bufW = nextW; bufH = nextH;
    if (canvas.width !== bufW || canvas.height !== bufH) {
      canvas.width = bufW; canvas.height = bufH;
    }
    ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return;
    overlay = buildOverlay(bufW, bufH, pal);
    var env = {
      calm: next.motion === 'dezent',
      tier: tier,
      ratio: ratio,
      speed: next.motion === 'dezent' ? 0.5 : 1,
      count: (next.motion === 'dezent' ? 0.5 : 1) * (tier >= 1 ? 0.5 : 1),
      variant: next.variant,
      w: bufW,
      h: bufH
    };
    var rand = mulberry(SEEDS[next.scene] || 1); current = BUILD[next.scene](bufW, bufH, pal, rand, env);
    builtSig = sig; canvas.style.opacity = '1';
    if (still) {
      fadeLeft = 0; stopLoop(); paintSafe(STILL_T, 0, 0);
    } else {
      fadeLeft = had && !instant ? FADE_MS : 0; paintSafe(clock, 0, 0); startLoop();
    }
  }

  function sync() {
    if (applying) return;
    applying = true;
    try {
      syncBody();
    } catch (err) { /* never throw into the page */ }
    applying = false;
  }

  function begin() {
    if (started && host && document.getElementById('techtyl-backdrop') === host) {
      sync(); return;
    }
    started = true;
    try {
      motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
      if (motionQuery.addEventListener) motionQuery.addEventListener('change', scheduleSync);
      else if (motionQuery.addListener) motionQuery.addListener(scheduleSync);
    } catch (err) {
      motionQuery = null;
    }
    try {
      var obs = new MutationObserver(scheduleSync);
      obs.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-techtyl-scene', 'data-techtyl-motion', 'data-techtyl-variant', 'style']
      });
    } catch (err2) { /* attribute reads still work via refresh */ }
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stopLoop();
      else {
        try { sync(); } catch (err) {}
      }
    });
    window.addEventListener('resize', function () {
      if (resizeTimer) clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        resizeTimer = 0; builtSig = '';
        try { sync(); } catch (err) {}
      }, 160);
    });
    sync();
  }

  function scheduleSync() {
    if (queued) return;
    queued = setTimeout(function () {
      queued = 0; builtSig = ''; sync();
    }, 48);
  }

  api.refresh = function () {
    if (queued) {
      clearTimeout(queued); queued = 0;
    }
    builtSig = '';
    try {
      if (!started) begin();
      else sync();
    } catch (err) {}
  };

  function boot() {
    var run = function () {
      var once = false;
      var go = function () {
        if (once) return;
        once = true;
        try { begin(); } catch (err) {}
      };
      if (window.requestIdleCallback) window.requestIdleCallback(go, { timeout: 280 });
      setTimeout(go, 40);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
    else run();
  }

  try { boot(); } catch (err) {}
}());
