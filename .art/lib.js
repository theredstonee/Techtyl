/* Techtyl Bildserie – gemeinsame Werkzeuge (Zufall, Rauschen, Farbe, Körnung, Formen).
   Alles prozedural, keine fremden Assets. */
(function () {
  const A = (window.A = {});
  A.SCENES = {};
  A.scene = function (key, w, h, draw) { A.SCENES[key] = [w, h, draw]; };

  A.rng = function (seed) {
    let a = seed >>> 0;
    return function () {
      a = (a + 0x6d2b79f5) >>> 0;
      let t = a;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  };

  // Gradientenrauschen (Perlin), Wertebereich ca. -1..1
  A.makeNoise = function (seed) {
    const r = A.rng(seed), p = new Uint8Array(512);
    const base = new Uint8Array(256);
    for (let i = 0; i < 256; i++) base[i] = i;
    for (let i = 255; i > 0; i--) { const k = (r() * (i + 1)) | 0; const t = base[i]; base[i] = base[k]; base[k] = t; }
    for (let i = 0; i < 512; i++) p[i] = base[i & 255];
    const g = function (h, x, y) {
      switch (h & 7) {
        case 0: return x + y; case 1: return x - y; case 2: return -x + y; case 3: return -x - y;
        case 4: return x * 1.4; case 5: return -x * 1.4; case 6: return y * 1.4; default: return -y * 1.4;
      }
    };
    const f = function (t) { return t * t * t * (t * (t * 6 - 15) + 10); };
    const n = function (x, y) {
      const X = Math.floor(x), Y = Math.floor(y);
      const xf = x - X, yf = y - Y, xi = X & 255, yi = Y & 255;
      const u = f(xf), v = f(yf);
      const aa = p[p[xi] + yi], ab = p[p[xi] + yi + 1], ba = p[p[xi + 1] + yi], bb = p[p[xi + 1] + yi + 1];
      const x1 = g(aa, xf, yf) + u * (g(ba, xf - 1, yf) - g(aa, xf, yf));
      const x2 = g(ab, xf, yf - 1) + u * (g(bb, xf - 1, yf - 1) - g(ab, xf, yf - 1));
      return (x1 + v * (x2 - x1)) * 0.75;
    };
    n.fbm = function (x, y, oct, gain) {
      let s = 0, a = 1, fq = 1, norm = 0;
      gain = gain || 0.5;
      for (let o = 0; o < (oct || 4); o++) { s += a * n(x * fq, y * fq); norm += a; a *= gain; fq *= 2; }
      return s / norm;
    };
    return n;
  };

  // Farbe
  A.rgb = function (hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    const v = parseInt(hex, 16);
    return [(v >> 16) & 255, (v >> 8) & 255, v & 255];
  };
  A.hex = function (c) {
    return '#' + c.map(function (v) { return Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0'); }).join('');
  };
  A.mix = function (a, b, t) {
    const x = A.rgb(a), y = A.rgb(b);
    return A.hex([x[0] + (y[0] - x[0]) * t, x[1] + (y[1] - x[1]) * t, x[2] + (y[2] - x[2]) * t]);
  };
  A.shade = function (a, f) { const x = A.rgb(a); return A.hex([x[0] * f, x[1] * f, x[2] * f]); };
  A.rgba = function (a, al) { const x = A.rgb(a); return 'rgba(' + x[0] + ',' + x[1] + ',' + x[2] + ',' + al + ')'; };
  A.clamp = function (v, a, b) { return v < a ? a : v > b ? b : v; };
  A.smooth = function (a, b, v) { const t = A.clamp((v - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); };
  A.lerp = function (a, b, t) { return a + (b - a) * t; };

  // Flächen
  A.vgrad = function (ctx, x, y, w, h, stops) {
    const g = ctx.createLinearGradient(0, y, 0, y + h);
    stops.forEach(function (s) { g.addColorStop(s[0], s[1]); });
    ctx.fillStyle = g; ctx.fillRect(x, y, w, h);
  };
  A.radial = function (ctx, cx, cy, r, c0, c1, comp, sx, sy) {
    ctx.save();
    if (comp) ctx.globalCompositeOperation = comp;
    ctx.translate(cx, cy); ctx.scale(sx || 1, sy || 1);
    const g = ctx.createRadialGradient(0, 0, 0, 0, 0, r);
    g.addColorStop(0, c0); g.addColorStop(1, c1);
    ctx.fillStyle = g; ctx.fillRect(-r, -r, r * 2, r * 2);
    ctx.restore();
  };
  A.rrect = function (ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  };
  // Der „Techtyl-Punkt“: abgerundetes Quadrat als einzige Lichtquelle jedes Bildes
  A.punkt = function (ctx, cx, cy, s, color, halo, haloR) {
    if (halo) {
      const R = haloR || s * 5;
      ctx.save(); ctx.globalCompositeOperation = 'screen';
      const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R);
      [[0, 0.62], [0.12, 0.4], [0.3, 0.2], [0.55, 0.075], [0.8, 0.018], [1, 0]].forEach(function (q) { g.addColorStop(q[0], A.rgba(halo, q[1])); });
      ctx.fillStyle = g; ctx.fillRect(cx - R, cy - R, R * 2, R * 2); ctx.restore();
    }
    ctx.fillStyle = color;
    A.rrect(ctx, cx - s / 2, cy - s / 2, s, s, s * 0.2); ctx.fill();
  };

  // Höhenzug: y-Werte je x
  A.ridge = function (W, base, amp, scale, noise, off, oct, step) {
    const ys = new Float32Array(W + 1);
    for (let x = 0; x <= W; x++) {
      const xx = step ? Math.floor(x / step) * step : x;
      let y = base - amp * (noise.fbm(xx / scale + (off || 0), (off || 0) * 1.7, oct || 4) * 0.5 + 0.5) * 2 + amp;
      if (step) y = Math.round(y / step) * step;
      ys[x] = y;
    }
    return ys;
  };
  A.fillRidge = function (ctx, ys, H, fill, x0) {
    ctx.beginPath(); ctx.moveTo(x0 || 0, H + 2);
    for (let x = 0; x < ys.length; x++) ctx.lineTo((x0 || 0) + x, ys[x]);
    ctx.lineTo((x0 || 0) + ys.length - 1, H + 2); ctx.closePath();
    if (fill) { ctx.fillStyle = fill; ctx.fill(); }
  };

  // Punktraster-Schattierung (Druck-Anmutung): n Versuche, Dichte über fn(x,y) 0..1
  A.stipple = function (ctx, x0, y0, w, h, n, color, fn, r, size) {
    ctx.fillStyle = color; size = size || 1.3;
    for (let k = 0; k < n; k++) {
      const x = x0 + r() * w, y = y0 + r() * h;
      if (r() < fn(x, y)) ctx.fillRect(x, y, size, size);
    }
  };

  // Feine Körnung über das ganze Bild (Helligkeitsrauschen)
  A.grain = function (ctx, W, H, amt, seed) {
    const im = ctx.getImageData(0, 0, W, H), d = im.data, r = A.rng(seed || 7);
    for (let i = 0; i < d.length; i += 4) {
      const n = (r() + r() - 1) * amt;
      d[i] += n; d[i + 1] += n; d[i + 2] += n;
    }
    ctx.putImageData(im, 0, 0);
  };

  // 8×8-Bayer. Zusammen mit feinem Korn bricht es stufige Verläufe, ohne Foto-Rauschen.
  A.BAYER8 = new Uint8Array([
    0, 32, 8, 40, 2, 34, 10, 42,
    48, 16, 56, 24, 50, 18, 58, 26,
    12, 44, 4, 36, 14, 46, 6, 38,
    60, 28, 52, 20, 62, 30, 54, 22,
    3, 35, 11, 43, 1, 33, 9, 41,
    51, 19, 59, 27, 49, 17, 57, 25,
    15, 47, 7, 39, 13, 45, 5, 37,
    63, 31, 55, 23, 61, 29, 53, 21
  ]);
  A.dither = function (ctx, W, H, o) {
    o = o || {};
    const amp = o.amp == null ? 8 : o.amp;
    const grain = o.grain == null ? 2.2 : o.grain;
    const r = A.rng(o.seed || 11);
    const B = A.BAYER8;
    const im = ctx.getImageData(0, 0, W, H), d = im.data;
    for (let y = 0; y < H; y++) {
      const br = (y & 7) << 3;
      const row = y * W;
      for (let x = 0; x < W; x++) {
        const n = (B[br + (x & 7)] - 31.5) * (amp / 63) + (r() + r() - 1) * grain;
        const i = (row + x) << 2;
        d[i] += n; d[i + 1] += n; d[i + 2] += n;
      }
    }
    ctx.putImageData(im, 0, 0);
  };
  A.vignette = function (ctx, W, H, color, strength) {
    const g = ctx.createRadialGradient(W / 2, H / 2, Math.min(W, H) * 0.35, W / 2, H / 2, Math.hypot(W, H) * 0.56);
    g.addColorStop(0, A.rgba(color, 0)); g.addColorStop(1, A.rgba(color, strength));
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
  };

  // Höhenlinien per Marching Squares; G = Raster (nx*ny), cell = Rasterweite
  A.grid = function (W, H, cell, fn) {
    const nx = Math.ceil(W / cell) + 2, ny = Math.ceil(H / cell) + 2, G = new Float32Array(nx * ny);
    for (let y = 0; y < ny; y++) for (let x = 0; x < nx; x++) G[y * nx + x] = fn(x * cell, y * cell);
    return [G, nx, ny, cell];
  };
  A.isoline = function (ctx, grid, level) {
    const G = grid[0], nx = grid[1], ny = grid[2], c = grid[3];
    ctx.beginPath();
    for (let y = 0; y < ny - 1; y++) for (let x = 0; x < nx - 1; x++) {
      const a = G[y * nx + x], b = G[y * nx + x + 1], d = G[(y + 1) * nx + x], e = G[(y + 1) * nx + x + 1];
      const k = (a > level ? 8 : 0) | (b > level ? 4 : 0) | (e > level ? 2 : 0) | (d > level ? 1 : 0);
      if (k === 0 || k === 15) continue;
      const X = x * c, Y = y * c;
      const T = [X + c * (level - a) / (b - a), Y], R = [X + c, Y + c * (level - b) / (e - b)];
      const B = [X + c * (level - d) / (e - d), Y + c], L = [X, Y + c * (level - a) / (d - a)];
      const seg = function (p, q) { ctx.moveTo(p[0], p[1]); ctx.lineTo(q[0], q[1]); };
      switch (k) {
        case 1: case 14: seg(L, B); break;
        case 2: case 13: seg(B, R); break;
        case 3: case 12: seg(L, R); break;
        case 4: case 11: seg(T, R); break;
        case 6: case 9: seg(T, B); break;
        case 7: case 8: seg(L, T); break;
        case 5: seg(L, T); seg(B, R); break;
        case 10: seg(L, B); seg(T, R); break;
      }
    }
    ctx.stroke();
  };

  A.layer = function (W, H) {
    const c = document.createElement('canvas'); c.width = W; c.height = H;
    return c.getContext('2d');
  };

  // Weiches Dunstband (atmosphärische Ebene zwischen den Motiven)
  A.band = function (ctx, W, y, h, color, alpha) {
    A.vgrad(ctx, 0, y - h * 0.5, W, h, [[0, A.rgba(color, 0)], [0.45, A.rgba(color, alpha)], [1, A.rgba(color, 0)]]);
  };

  // Volumenlicht aus dem Techtyl-Punkt: weicher Hof und wenige Strahlen, keine Strahlenkranz-Grafik
  A.volumetric = function (ctx, x, y, color, o) {
    o = o || {};
    const R = o.r || 420;
    A.radial(ctx, x, y, R, A.rgba(color, o.a == null ? 0.32 : o.a), A.rgba(color, 0), 'screen', o.sx || 1.2, o.sy || 0.72);
    ctx.save();
    ctx.globalCompositeOperation = 'screen';
    ctx.translate(x, y);
    const n = o.shafts == null ? 4 : o.shafts;
    const a0 = o.a0 == null ? Math.PI * 0.42 : o.a0;
    const a1 = o.a1 == null ? Math.PI * 0.82 : o.a1;
    for (let i = 0; i < n; i++) {
      const a = n === 1 ? (a0 + a1) / 2 : a0 + (a1 - a0) * (i / (n - 1));
      const spread = 0.045 + (i % 2) * 0.03;
      const len = R * (0.85 + (i % 3) * 0.22);
      ctx.fillStyle = A.rgba(color, 0.028 + (i === 1 ? 0.02 : 0));
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.lineTo(Math.cos(a - spread) * len, Math.sin(a - spread) * len);
      ctx.lineTo(Math.cos(a + spread) * len, Math.sin(a + spread) * len);
      ctx.closePath();
      ctx.fill();
    }
    ctx.restore();
  };
})();
