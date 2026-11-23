/* Weitere Hintergründe (2560x1440): Papier, Hafen, Kiefer sowie die frei wählbaren Motive. */
(function () {
  const A = window.A, GW = 2560, GH = 1440;

  function pine(ctx, x, y, h, col, r) {
    ctx.fillStyle = col;
    ctx.fillRect(x - h * 0.014, y - h * 0.3, h * 0.028, h * 0.32);
    const N = 8;
    for (let t = 0; t < N; t++) {
      const yt = y - h + t * (h * 0.8 / N), hw = h * (0.035 + (t + 1) / N * 0.17) * (0.86 + r() * 0.28), dr = h * 0.03;
      ctx.beginPath(); ctx.moveTo(x, yt - h * 0.07);
      ctx.quadraticCurveTo(x + hw * 0.4, yt + h * 0.05, x + hw, yt + h * 0.13 + dr * r());
      ctx.quadraticCurveTo(x, yt + h * 0.09, x - hw, yt + h * 0.13 + dr * r());
      ctx.quadraticCurveTo(x - hw * 0.4, yt + h * 0.05, x, yt - h * 0.07); ctx.fill();
    }
  }
  A.pine = pine;
  // Tal-Profil: zur Bildmitte hin tief, zu den Rändern ansteigend
  function valley(W, base, lift, amp, scale, n, off, ridged) {
    const ys = new Float32Array(W + 1);
    for (let x = 0; x <= W; x++) {
      const e = Math.pow(Math.abs(x / W - 0.5) * 2, 1.7);
      let v = n.fbm(x / scale + off, off * 1.3, 4);
      if (ridged) v = 1 - Math.abs(v) * 2.6; else v = v * 0.5 + 0.5;
      ys[x] = base - lift * e - amp * v * (0.45 + 0.55 * e);
    }
    return ys;
  }
  function stars(ctx, W, H, count, r, maxY) {
    for (let k = 0; k < count; k++) {
      const s = 0.8 + r() * r() * 2.2;
      ctx.fillStyle = 'rgba(' + (r() < 0.3 ? '255,236,210' : r() < 0.5 ? '200,220,255' : '255,255,255') + ',' + (0.2 + r() * 0.7) + ')';
      ctx.fillRect(r() * W, r() * r() * (maxY || H), s, s);
    }
  }

  // Papier: Scherenschnitt aus Papierlagen, Tintenblau nur am Rand
  A.scene('bg-papier', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(201), r = A.rng(201);
    ctx.fillStyle = '#eceef1'; ctx.fillRect(0, 0, W, H);
    A.radial(ctx, W * 0.5, H * 0.5, W * 0.6, 'rgba(255,255,255,.55)', 'rgba(255,255,255,0)');
    function sheet(fill, path, alpha) {
      ctx.save(); ctx.shadowColor = 'rgba(24,32,74,' + (alpha || 0.2) + ')'; ctx.shadowBlur = 60; ctx.shadowOffsetX = -12; ctx.shadowOffsetY = 18;
      ctx.fillStyle = fill; ctx.beginPath(); path(); ctx.closePath(); ctx.fill(); ctx.restore();
    }
    // obere rechte Ecke
    [['#f6f7f9', 760, 0.34, 1], ['#e6e9ee', 560, 0.48, 2], ['#f9fafb', 380, 0.6, 3], ['#2a3fa8', 200, 0.74, 4]].forEach(function (q) {
      sheet(q[0], function () {
        const x0 = W * q[2]; ctx.moveTo(x0 - 80, -80);
        for (let x = x0 - 80; x <= W + 80; x += 12) { const t = A.clamp((x - x0) / (W - x0), 0, 1.2); ctx.lineTo(x, Math.pow(t, 1.5) * q[1] + 46 * n.fbm(x / 520 + q[3] * 7, q[3], 2) * t - 20); }
        ctx.lineTo(W + 80, -80);
      }, q[3] === 4 ? 0.34 : 0.2);
    });
    // unterer Rand, links höher
    [['#f5f6f8', 0.64, 250, 1], ['#e4e8ed', 0.72, 210, 2], ['#f9fafb', 0.8, 170, 3], ['#dfe3ea', 0.875, 120, 4], ['#2a3fa8', 0.945, 80, 5]].forEach(function (q) {
      sheet(q[0], function () {
        ctx.moveTo(-80, H + 80);
        for (let x = -80; x <= W + 80; x += 12) { const t = x / W; ctx.lineTo(x, H * q[1] + q[2] * (t - 0.35) * 0.9 + 110 * n.fbm(x / 700 + q[3] * 4, q[3] * 2, 3) + 60 * Math.sin(t * 5 + q[3])); }
        ctx.lineTo(W + 80, H + 80);
      }, q[3] === 5 ? 0.34 : 0.2);
    });
    // Punkt als aufgelegtes Papierquadrat
    ctx.save(); ctx.shadowColor = 'rgba(24,32,74,.35)'; ctx.shadowBlur = 22; ctx.shadowOffsetX = -5; ctx.shadowOffsetY = 8;
    A.punkt(ctx, W * 0.79, H * 0.2, 46, '#2a3fa8'); ctx.restore();
    // Papierfasern
    ctx.lineCap = 'round';
    for (let k = 0; k < 2600; k++) {
      const x = r() * W, y = r() * H, a = r() * 6.3, L = 4 + r() * 14;
      ctx.strokeStyle = r() < 0.5 ? 'rgba(40,50,90,.05)' : 'rgba(255,255,255,.35)'; ctx.lineWidth = 0.8;
      ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + Math.cos(a) * L, y + Math.sin(a) * L); ctx.stroke();
    }
    A.grain(ctx, W, H, 4, 201);
  });

  // Hafen: Nachthafen, Lichter und ihre Spiegelungen
  A.scene('bg-hafen', GW, GH, function (ctx, W, H) {
    const r = A.rng(212), hz = Math.round(H * 0.66);
    A.vgrad(ctx, 0, 0, W, hz, [[0, '#110919'], [0.5, '#22113a'], [0.84, '#47204f'], [1, '#7c3058']]);
    A.radial(ctx, W * 0.79, hz, 1100, 'rgba(255,106,143,.42)', 'rgba(255,106,143,0)', 'screen', 1.5, 0.42);
    stars(ctx, W, H, 500, r, hz * 0.8);
    A.punkt(ctx, W * 0.79, H * 0.2, 46, '#ffd9cf', '#ff6a8f', 440);
    A.vgrad(ctx, 0, hz, W, H - hz, [[0, '#3c1a44'], [0.25, '#1f1030'], [1, '#0d0715']]);
    const sil = '#160b20';
    ctx.fillStyle = sil; ctx.strokeStyle = sil; ctx.lineCap = 'butt';
    // Kai und Hallen
    let x = -20;
    while (x < W + 20) {
      const mid = Math.abs(x / W - 0.5) < 0.2, w = 60 + r() * 200, h = (mid ? 14 : 26) + r() * (mid ? 22 : 62);
      ctx.fillRect(x, hz - h, w, h + 2);
      if (r() < 0.3) { ctx.beginPath(); ctx.moveTo(x, hz - h); ctx.lineTo(x + w / 2, hz - h - 16); ctx.lineTo(x + w, hz - h); ctx.fill(); }
      x += w + r() * 30;
    }
    function crane(cx, s, dir) {
      const b = hz, lw = 7 * s;
      ctx.lineWidth = lw; ctx.beginPath();
      ctx.moveTo(cx - 38 * s, b); ctx.lineTo(cx - 30 * s, b - 210 * s); ctx.moveTo(cx + 38 * s, b); ctx.lineTo(cx + 30 * s, b - 210 * s);
      ctx.moveTo(cx - 34 * s, b - 90 * s); ctx.lineTo(cx + 34 * s, b - 90 * s);
      ctx.moveTo(cx - 90 * s * dir, b - 210 * s); ctx.lineTo(cx + 250 * s * dir, b - 210 * s);
      ctx.moveTo(cx, b - 210 * s); ctx.lineTo(cx, b - 300 * s); ctx.stroke();
      ctx.lineWidth = 2.4 * s; ctx.beginPath();
      ctx.moveTo(cx, b - 300 * s); ctx.lineTo(cx + 244 * s * dir, b - 212 * s); ctx.moveTo(cx, b - 300 * s); ctx.lineTo(cx + 120 * s * dir, b - 212 * s);
      ctx.moveTo(cx, b - 300 * s); ctx.lineTo(cx - 86 * s * dir, b - 212 * s);
      ctx.moveTo(cx - 36 * s, b - 90 * s); ctx.lineTo(cx + 31 * s, b - 205 * s); ctx.moveTo(cx + 36 * s, b - 90 * s); ctx.lineTo(cx - 31 * s, b - 205 * s);
      ctx.moveTo(cx + 150 * s * dir, b - 210 * s); ctx.lineTo(cx + 150 * s * dir, b - 120 * s); ctx.stroke();
      ctx.fillRect(cx + 136 * s * dir, b - 124 * s, 28 * s, 16 * s); ctx.fillRect(cx - 20 * s, b - 236 * s, 40 * s, 26 * s);
      return [[cx, b - 300 * s], [cx + 250 * s * dir, b - 214 * s]];
    }
    const tops = [].concat(crane(210, 1.25, 1), crane(560, 0.95, 1), crane(880, 0.62, 1), crane(1960, 0.7, -1), crane(2360, 1.2, -1));
    // Schiff
    ctx.beginPath(); ctx.moveTo(1300, hz - 34); ctx.lineTo(1720, hz - 34); ctx.lineTo(1700, hz + 2); ctx.lineTo(1320, hz + 2); ctx.fill();
    for (let k = 0; k < 9; k++) ctx.fillRect(1330 + k * 34, hz - 34 - 14 * (1 + ((k * 7) % 3)), 30, 44);
    ctx.fillRect(1660, hz - 96, 34, 64);
    // Lichter
    const lights = [];
    const pal = ['#ff6a8f', '#ffb38a', '#ffd9a8', '#b693ff', '#ff8fb0'];
    for (let lx = 20; lx < W; lx += 30 + r() * 90) lights.push([lx, hz - 4 - r() * 14, pal[(r() * pal.length) | 0], 0.5 + r() * 0.5, 4]);
    tops.forEach(function (t) { lights.push([t[0], t[1], '#ff6a8f', 1, 6]); });
    lights.push([1677, hz - 100, '#ffd9a8', 1, 6]);
    lights.forEach(function (l) {
      A.radial(ctx, l[0], l[1], 26 + l[3] * 30, A.rgba(l[2], 0.5 * l[3]), A.rgba(l[2], 0), 'screen');
      ctx.fillStyle = A.mix(l[2], '#ffffff', 0.35); ctx.fillRect(l[0] - l[4] / 2, l[1] - l[4] / 2, l[4], l[4]);
      const dy = hz - l[1];
      for (let k = 0; k < 16; k++) {
        const y = hz + 6 + dy * 0.6 + k * (9 + k * 1.5), w = 8 + k * 2.6 + r() * 12;
        ctx.fillStyle = A.rgba(l[2], 0.55 * l[3] * (1 - k / 16)); ctx.fillRect(l[0] - w / 2 + (r() - 0.5) * (4 + k * 2), y, w, 2 + k * 0.22);
      }
    });
    // Mondspiegelung und Wellenstriche
    for (let k = 0; k < 44; k++) {
      const y = hz + 10 + k * (6 + k * 0.42), w = 30 + k * 7 + r() * 60;
      ctx.fillStyle = 'rgba(255,170,180,' + 0.3 * (1 - k / 44) + ')'; ctx.fillRect(W * 0.79 - w / 2 + (r() - 0.5) * (10 + k * 4), y, w, 2 + k * 0.1);
    }
    for (let k = 0; k < 900; k++) {
      const t = r(), y = hz + 4 + t * t * (H - hz), w = 20 + t * 160 * r();
      ctx.fillStyle = 'rgba(214,170,230,' + (0.03 + r() * 0.06) + ')'; ctx.fillRect(r() * W, y, w, 1.5);
    }
    A.vignette(ctx, W, H, '#07030c', 0.6);
    A.grain(ctx, W, H, 5, 212);
  });

  // Kiefer: Nadelwald-Kämme im Nebel
  A.scene('bg-kiefer', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(223), r = A.rng(223);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#15251d'], [0.4, '#2a4436'], [0.72, '#5a7660'], [1, '#5a7660']]);
    A.radial(ctx, W * 0.79, H * 0.2, 1200, 'rgba(228,209,154,.3)', 'rgba(228,209,154,0)', 'screen');
    A.punkt(ctx, W * 0.79, H * 0.2, 46, '#f3e6b8', '#e4d19a', 400);
    const L = 6;
    for (let k = 0; k < L; k++) {
      const t = k / (L - 1), col = A.mix('#5d7b65', '#09120d', Math.pow(t, 0.8));
      const ys = valley(W, H * (0.74 + t * 0.2), 150 + t * 330, 120 + t * 60, 700 - t * 250, n, k * 3.7 + 1);
      const th = 44 + Math.pow(t, 1.6) * 520;
      let x = -40;
      while (x < W + 40) {
        const e = Math.abs(x / W - 0.5) * 2, h = th * (0.6 + r() * 0.6) * (0.5 + 0.5 * Math.min(1, e * 1.4 + (1 - t)));
        const xi = A.clamp(Math.round(x), 0, W);
        if (!(k >= 4 && e < 0.42)) pine(ctx, x, ys[xi] + h * 0.05, h, col, r);
        x += h * (0.2 + r() * 0.34);
      }
      A.fillRidge(ctx, ys, H, col);
      if (k < L - 1) A.vgrad(ctx, 0, H * 0.38, W, H * 0.62, [[0, 'rgba(120,150,125,0)'], [0.55, 'rgba(120,150,125,' + (0.26 - t * 0.12) + ')'], [1, 'rgba(120,150,125,' + (0.4 - t * 0.2) + ')']]);
    }
    A.stipple(ctx, 0, H * 0.4, W, H * 0.6, 260000, 'rgba(230,236,214,.14)', function (x, y) { return A.clamp(1 - Math.abs(y - H * 0.72) / (H * 0.25), 0, 1) * 0.5; }, r, 1.6);
    A.vignette(ctx, W, H, '#050b08', 0.55);
    A.grain(ctx, W, H, 5, 223);
  });

  // Dämmerung: Bergketten im letzten Licht
  A.scene('bg-daemmerung', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(234), r = A.rng(234);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#101226'], [0.36, '#33274f'], [0.58, '#93486a'], [0.7, '#e89a66'], [1, '#f0b070']]);
    stars(ctx, W, H, 420, r, H * 0.4);
    A.punkt(ctx, W * 0.79, H * 0.2, 42, '#ffe9d2', '#ffb48a', 380);
    const L = 5;
    for (let k = 0; k < L; k++) {
      const t = k / (L - 1), col = A.mix('#8a4d73', '#120e22', Math.pow(t, 0.75));
      const ys = valley(W, H * (0.72 + t * 0.22), 190 + t * 240, 230 - t * 70, 900 - t * 300, n, k * 5.1 + 2, true);
      A.fillRidge(ctx, ys, H, col);
      A.shadeRidge(ctx, ys, H, A.rgba('#0c0a1c', 0.6), r, 240000, 0.7, 260);
      A.crest(ctx, ys, 'rgba(255,190,150,' + (0.4 - t * 0.3) + ')', 1.6);
      if (k < L - 1) A.vgrad(ctx, 0, H * 0.5, W, H * 0.5, [[0, 'rgba(232,154,110,0)'], [1, 'rgba(232,154,110,' + (0.3 - t * 0.2) + ')']]);
    }
    A.vignette(ctx, W, H, '#07061a', 0.55);
    A.grain(ctx, W, H, 5, 234);
  });

  // Polarlicht: Lichtvorhänge über dunklem Waldrand
  A.scene('bg-polarlicht', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(245), r = A.rng(245);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#050a13'], [0.55, '#0a1a28'], [0.86, '#12313a'], [1, '#0a1a22']]);
    stars(ctx, W, H, 1100, r, H * 0.9);
    const Lr = A.layer(W, H);
    (function (ctx) {
    [[0.3, 0, 1, '#58f0b0', '#7a6cf0'], [0.42, 1, 0.6, '#5ee6d0', '#a070e0']].forEach(function (c) {
      for (let x = 0; x < W; x += 3) {
        const y0 = H * c[0] + 150 * n.fbm(x / 800 + c[1] * 5.3, 1.1, 3) + 120 * (x / W - 0.5) * (c[1] ? -1 : 1);
        const band = A.smooth(-0.25, 0.35, n(x / 420 + c[1] * 9.7, 4.2)) * (0.55 + 0.45 * A.smooth(-0.4, 0.5, n(x / 24, c[1] * 3 + 0.5)));
        const edge = A.smooth(0, 0.35, 1 - Math.abs(x / W - 0.55) * 1.9);
        const it = band * c[2] * (0.35 + 0.65 * edge), len = 240 + 360 * band;
        if (it < 0.02) continue;
        const g = ctx.createLinearGradient(0, y0 - len, 0, y0 + 110);
        g.addColorStop(0, A.rgba(c[4], 0)); g.addColorStop(0.4, A.rgba(c[4], 0.2 * it)); g.addColorStop(0.7, A.rgba(c[3], 0.55 * it)); g.addColorStop(0.8, A.rgba('#d8ffe8', 0.6 * it)); g.addColorStop(0.88, A.rgba(c[3], 0.25 * it)); g.addColorStop(1, A.rgba(c[3], 0));
        ctx.fillStyle = g; ctx.fillRect(x, y0 - len, 3, len + 110);
      }
    });
    })(Lr);
    ctx.save(); ctx.globalCompositeOperation = 'screen';
    ctx.filter = 'blur(26px)'; ctx.drawImage(Lr.canvas, 0, 0);
    ctx.filter = 'blur(5px)'; ctx.globalAlpha = 0.75; ctx.drawImage(Lr.canvas, 0, 0);
    ctx.restore();
    A.punkt(ctx, W * 0.79, H * 0.2, 40, '#eafff4', '#8ff0c8', 300);
    const col = '#03070a', ys = valley(W, H * 0.93, 210, 70, 600, n, 3.3);
    let x = -30;
    while (x < W + 30) {
      const e = Math.abs(x / W - 0.5) * 2, h = (50 + r() * 70) * (0.7 + e * 1.6);
      pine(ctx, x, ys[A.clamp(Math.round(x), 0, W)] + 6, h, col, r); x += h * (0.2 + r() * 0.3);
    }
    A.fillRidge(ctx, ys, H, col);
    A.vignette(ctx, W, H, '#020408', 0.55);
    A.grain(ctx, W, H, 5, 245);
  });

  // Sternenzelt: Sternband über stillen Hügeln
  A.scene('bg-sternenzelt', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(256), r = A.rng(256);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#04060d'], [0.6, '#0b1124'], [0.9, '#1a2242'], [1, '#141a34']]);
    // Sternband im oberen Drittel (links mittig -> oben rechts)
    const band = function (x, y) { const d = (y - (H * 0.42 - x * 0.2)) * 0.98; return Math.exp(-Math.pow(d / 210, 2)); };
    const sw = W / 8, sh = H / 8, small = A.layer(sw, sh), im = small.createImageData(sw, sh), d = im.data;
    const ca = A.rgb('#5468d8'), cb = A.rgb('#b98fd0');
    for (let y = 0; y < sh; y++) for (let x = 0; x < sw; x++) {
      const X = x * 8, Y = y * 8, b = band(X, Y), f = n.fbm(X / 420, Y / 420, 4) * 0.5 + 0.5, dust = A.smooth(0.35, 0.6, n.fbm(X / 260 + 9, Y / 150 + 3, 3) * 0.5 + 0.5);
      const t = A.clamp(n(X / 600 + 4, Y / 600) * 0.5 + 0.5, 0, 1), p = (y * sw + x) * 4;
      d[p] = ca[0] + (cb[0] - ca[0]) * t; d[p + 1] = ca[1] + (cb[1] - ca[1]) * t; d[p + 2] = ca[2] + (cb[2] - ca[2]) * t;
      d[p + 3] = 255 * A.clamp(b * (0.3 + f * 0.7) * (1 - dust * 0.6) * 0.36, 0, 1);
    }
    small.putImageData(im, 0, 0);
    ctx.save(); ctx.globalCompositeOperation = 'screen'; ctx.imageSmoothingQuality = 'high'; ctx.drawImage(small.canvas, 0, 0, W, H); ctx.restore();
    for (let k = 0; k < 14000; k++) {
      const x = r() * W, y = r() * H * 0.92, b = band(x, y);
      if (r() > 0.1 + b * 0.9) continue;
      const s = 0.8 + r() * r() * 2.1;
      ctx.fillStyle = 'rgba(' + (r() < 0.25 ? '255,232,206' : r() < 0.5 ? '196,216,255' : '255,255,255') + ',' + (0.2 + r() * 0.75) + ')';
      ctx.fillRect(x, y, s, s);
    }
    for (let k = 0; k < 22; k++) {
      const x = r() * W, y = r() * H * 0.6, s = 2 + r() * 2;
      A.radial(ctx, x, y, 7 + r() * 7, 'rgba(210,225,255,.45)', 'rgba(210,225,255,0)', 'screen');
      ctx.fillStyle = '#ffffff'; ctx.fillRect(x - s / 2, y - s / 2, s, s);
    }
    A.punkt(ctx, W * 0.79, H * 0.2, 34, '#fff3d6', '#ffe0b0', 300);
    A.radial(ctx, W * 0.5, H * 1.04, 1300, 'rgba(90,110,200,.3)', 'rgba(90,110,200,0)', 'screen', 1.6, 0.3);
    const far = valley(W, H * 0.92, 120, 60, 800, n, 7.7), near = valley(W, H * 0.97, 190, 50, 500, n, 2.2);
    A.fillRidge(ctx, far, H, '#0c1226'); A.crest(ctx, far, 'rgba(150,170,240,.25)', 1.4);
    A.fillRidge(ctx, near, H, '#05070f');
    A.vignette(ctx, W, H, '#020308', 0.5);
    A.grain(ctx, W, H, 4, 256);
  });
})();
