/* Banner der Minecraft-Familie auf Basis der Klötzchen-Welt (iso.js). */
(function () {
  const A = window.A, BW = 1600, BH = 520;

  function terr(n, i, j, sc, amp, base) {
    return n.fbm(i / sc + 3.1, j / sc + 8.4, 4) * amp + 0.3 * amp * n(i / (sc * 2.8), j / (sc * 2.8)) + base;
  }

  // Vanilla: Tageslicht, Wiesen, Wasser, Fels
  A.scene('bn-minecraft-vanilla', BW, BH, function (ctx, W, H) {
    A.isoScene(ctx, W, H, {
      seed: 1107, bg: '#9fd0e8', tw: 34, bh: 17, oy: -230,
      cell: function (i, j, n) {
        const e = terr(n, i, j, 11, 15, 3.4);
        const h = Math.floor(e), wl = 1;
        if (h <= wl) return { h: wl, kind: 'water', top: A.mix('#62bcdb', '#2a74b0', A.clamp((wl - e) / 4, 0, 1)), depth: 2 };
        if (h === wl + 1) return { h: h, kind: 'sand', top: '#ecd99c' };
        if (h >= 9) return { h: h, kind: 'snow', top: '#f3f6f1', side: '#a9b0b3' };
        if (h >= 7) return { h: h, kind: 'stone', top: A.mix('#98a1a1', '#b5bcb8', (h - 7) / 2) };
        const c = { h: h, kind: 'grass', top: A.mix('#55a246', '#8fca5c', (h - 3) / 4), side: '#8a6a45' };
        if (A.hash(i, j, 5) < 0.1 + 0.26 * A.smooth(0.05, 0.3, n(i / 9 + 40, j / 9))) c.tree = { trunk: '#6b4a2e', leaf: A.hash(i, j, 9) < 0.3 ? '#2f7d3f' : '#3b8f45' };
        return c;
      },
      shadow: '#143042', shadowAmt: 0.38,
      accent: { fx: 0.73, fy: 0.35, color: '#ffb640', halo: '#ffc468', haloR: 150 },
      atmo: { color: '#d7eef6', horizon: 250, farY: -40, amt: 0.48 },
      rim: '#ffe6b0', rimAmt: 0.14,
      mist: [200, 78, '#f4edd2', 0.13],
      floor: '#102818', floorA: 0.32,
      sun: { dx: 46, dy: -54, s: 32, color: '#fff4d2', halo: '#ffc56a', r: 250, hr: 170, va: 0.26 },
      fog: ['#d6ecf4', 0.45, 0.28], light: ['#fff3c9', 0.55], vig: ['#0f2b3a', 0.4], grain: 8
    });
  });

  // Paper: dieselbe Welt als Papiermodell, Wasser in Tintenblau
  A.scene('bn-paper', BW, BH, function (ctx, W, H) {
    A.isoScene(ctx, W, H, {
      seed: 2203, bg: '#efeadf', tw: 34, bh: 17, oy: -230, vary: 0.03, sL: 0.74, sR: 0.88, edge: 0.6,
      cell: function (i, j, n) {
        const e = terr(n, i + 60, j - 20, 11, 15, 3.2);
        const h = Math.floor(e), wl = 1;
        if (h <= wl) return { h: wl, kind: 'water', top: A.mix('#4a62c8', '#2a3fa8', A.clamp((wl - e) / 3, 0, 1)), depth: 2 };
        const c = { h: h, kind: 'paper', top: A.mix('#ebe5d8', '#fbf8f0', A.clamp((h - 2) / 6, 0, 1)) };
        if (h < 7 && A.hash(i, j, 5) < 0.07 + 0.2 * A.smooth(0.05, 0.3, n(i / 9 + 40, j / 9))) c.tree = { trunk: '#cbbfa8', leaf: '#fdfbf5' };
        return c;
      },
      shadow: '#5b6399', shadowAmt: 0.3,
      accent: { fx: 0.73, fy: 0.35, color: '#ee5a2c', halo: '#ff9a6a', haloR: 90 },
      atmo: { color: '#f7f3ea', horizon: 240, farY: -30, amt: 0.4 },
      rim: '#fff8ee', rimAmt: 0.1,
      mist: [196, 70, '#fffaf4', 0.16],
      floor: '#d9d0c0', floorA: 0.28,
      sun: { dx: 40, dy: -48, s: 28, color: '#ff5c34', halo: '#ffb090', r: 210, hr: 140, va: 0.2 },
      fog: ['#f6f2e8', 0.5, 0.28], light: ['#fff6da', 0.5], vig: ['#3a3f66', 0.3], grain: 7
    });
  });

  // Forge: Basalt, Glutadern, Funkenflug
  A.scene('bn-forge', BW, BH, function (ctx, W, H) {
    const r = A.rng(77);
    A.isoScene(ctx, W, H, {
      seed: 3301, bg: '#120d12', tw: 34, bh: 17, oy: -230, sL: 0.5, sR: 0.72, edge: 0.08,
      cell: function (i, j, n) {
        const e = terr(n, i - 30, j + 90, 10, 15, 4.9);
        const h = Math.floor(e), wl = 1;
        if (h <= wl) return { h: wl, kind: 'glow', top: A.mix('#ffd76a', '#f2611c', A.clamp((wl - e) / 2.2 + A.hash(i, j, 3) * 0.3, 0, 1)), depth: 2, glow: 70 };
        if (h === wl + 1) return { h: h, kind: 'rim', top: '#5a2a22', side: '#3a1c1c' };
        return { h: h, kind: 'stone', top: A.mix('#2d2932', '#575260', A.clamp((h - 3) / 6, 0, 1)) };
      },
      shadow: '#07050a', shadowAmt: 0.45,
      accent: { fx: 0.72, fy: 0.35, color: '#fff0c2', halo: '#ffb24a', haloR: 170 },
      atmo: { color: '#2a1214', horizon: 210, farY: 0, amt: 0.26 },
      rim: '#ffb060', rimAmt: 0.18,
      mist: [188, 56, '#ff9a48', 0.06],
      floor: '#050306', floorA: 0.4,
      sun: { dx: 24, dy: -40, s: 26, color: '#fff6e2', halo: '#ffb24a', r: 230, hr: 150, va: 0.28, shafts: 3 },
      after: function (c) {
        c.globalCompositeOperation = 'screen';
        for (let k = 0; k < 150; k++) {
          const s = 2 + r() * 4;
          c.fillStyle = A.rgba(r() < 0.5 ? '#ffb24a' : '#ff7a2a', 0.25 + r() * 0.6);
          c.fillRect(r() * W, r() * H * 0.9, s, s);
        }
        c.globalCompositeOperation = 'source-over';
      },
      fog: ['#2a0f10', 0.5, 0.3], light: ['#ff8a3a', 0.25, 'screen'], vig: ['#050305', 0.6], grain: 9
    });
  });

  // Sponge: poröse gelbe Blöcke als Riff im türkisen Wasser
  A.scene('bn-sponge', BW, BH, function (ctx, W, H) {
    A.isoScene(ctx, W, H, {
      seed: 4409, bg: '#0f5c66', tw: 34, bh: 17, oy: -200, sL: 0.6, sR: 0.82,
      cell: function (i, j, n) {
        const e = terr(n, i + 200, j + 10, 9, 15, 1.2);
        const h = Math.floor(e), wl = 1;
        if (h <= wl) return { h: wl, kind: 'water', top: A.mix('#3fc1b8', '#0e6f80', A.clamp((wl - e) / 5, 0, 1)), depth: 2 };
        return {
          h: h, kind: 'sponge', top: A.mix('#e9bd33', '#f8dd62', A.clamp((h - 2) / 5, 0, 1)), side: '#d9a82a',
          deco: function (c, X, Y, tw, th, sh, ci, cj) {
            c.fillStyle = sh ? 'rgba(90,60,0,.3)' : 'rgba(150,100,0,.42)';
            for (let k = 0; k < 4; k++) {
              const a = A.hash(ci, cj, k + 20), b = A.hash(ci, cj, k + 30);
              const u = (a - 0.5) * 0.9, v = (b - 0.5) * 0.9;
              c.beginPath(); c.ellipse(X + (u - v) * tw * 0.8, Y + th + (u + v) * th * 0.8, 2.2 + a * 3.2, 1.1 + a * 1.6, 0, 0, 7); c.fill();
            }
          }
        };
      },
      shadow: '#0a3a48', shadowAmt: 0.36,
      accent: { fx: 0.73, fy: 0.35, color: '#ff7d8c', halo: '#ffb0a8', haloR: 110 },
      atmo: { color: '#c8f3ea', horizon: 220, farY: -20, amt: 0.36 },
      rim: '#fff6d4', rimAmt: 0.12,
      mist: [186, 64, '#e9fff8', 0.14],
      floor: '#08343a', floorA: 0.34,
      sun: { dx: 38, dy: -46, s: 28, color: '#ffe4dc', halo: '#ff9aa6', r: 210, hr: 140, va: 0.22 },
      fog: ['#9fe6dc', 0.35, 0.3], light: ['#f4ffd9', 0.5], vig: ['#052a33', 0.5], grain: 8
    });
  });

  // Bungeecord: schwebende Inseln, mit Seilen verbunden
  A.scene('bn-bungeecord', BW, BH, function (ctx, W, H) {
    A.vgrad(ctx, 0, 0, W, H, [[0, '#1b1f4a'], [0.6, '#2c2a66'], [1, '#191a3e']]);
    A.radial(ctx, W * 0.74, H * 0.2, 620, 'rgba(255,196,120,.28)', 'rgba(255,196,120,0)', 'screen');
    const isl = [[2, 14, 3.2, 5], [9, 9, 2.6, 6], [17, 6, 3.4, 5], [25, -1, 2.4, 7], [14, 15, 2.2, 3], [24, 7, 3, 4], [32, 2, 2.6, 5], [6, 22, 2.8, 3], [30, 11, 2.2, 2], [-3, 22, 2.6, 5], [38, -6, 3, 6], [20, 20, 2.4, 1], [38, 6, 2.2, 3]];
    const links = [[0, 1], [1, 2], [2, 3], [1, 4], [2, 5], [3, 6], [4, 7], [5, 8], [0, 9], [6, 10], [4, 11], [5, 6], [8, 12]];
    A.isoScene(ctx, W, H, {
      seed: 5507, tw: 30, bh: 15, oy: -150, sL: 0.5, sR: 0.74, grain: 0,
      cell: function (i, j, n) {
        for (let k = 0; k < isl.length; k++) {
          const q = isl[k], d = Math.hypot(i - q[0], j - q[1]) + n(i / 2.3 + k, j / 2.3) * 1.3;
          if (d < q[2]) {
            const edge = d / q[2];
            return { h: q[3] + (edge < 0.45 ? 1 : 0), kind: 'land', top: edge < 0.45 ? '#8fd6b2' : '#74c2a0', side: '#5b5aa8', depth: 1 + Math.round((1 - edge) * 3.2), tree: A.hash(i, j, 4) < 0.14 ? { trunk: '#5a4a6a', leaf: '#3f9b86' } : null };
          }
        }
        return { h: 0, skip: true };
      },
      shadow: '#1c2050', shadowAmt: 0.3,
      atmo: { color: '#3a3878', horizon: 200, farY: -40, amt: 0.4 },
      rim: '#ffe0a8', rimAmt: 0.12,
      mist: [170, 80, '#6a6498', 0.12],
      sun: { dx: 30, dy: -64, s: 34, color: '#fff1cf', halo: '#ffd27a', r: 240, hr: 160, va: 0.24, shafts: 3 },
      after: function (c, n, g) {
        const top = function (q) { return [g.sx(q[0], q[1]), g.sy(q[0], q[1], q[3] + 1) + g.th]; };
        c.lineCap = 'round';
        links.forEach(function (l) {
          const a = top(isl[l[0]]), b = top(isl[l[1]]), sag = 26 + Math.abs(a[0] - b[0]) * 0.16;
          for (let p = 0; p < 2; p++) {
            c.strokeStyle = p ? '#ffd27a' : 'rgba(20,16,50,.55)'; c.lineWidth = p ? 2.4 : 5;
            c.beginPath(); c.moveTo(a[0], a[1] - 12); c.quadraticCurveTo((a[0] + b[0]) / 2, (a[1] + b[1]) / 2 + sag, b[0], b[1] - 12); c.stroke();
          }
        });
        isl.forEach(function (q, k) {
          const a = top(q);
          c.fillStyle = '#3a2f5e'; c.fillRect(a[0] - 2.5, a[1] - 16, 5, 18);
          c.fillStyle = '#ffd27a'; A.rrect(c, a[0] - 5, a[1] - 22, 10, 10, 2); c.fill();
          if (k === 3) { c.fillStyle = '#fff1cf'; A.rrect(c, a[0] - 6, a[1] - 24, 12, 12, 2.5); c.fill(); }
        });
      }
    });
    A.vignette(ctx, W, H, '#0a0a24', 0.45); A.grain(ctx, W, H, 9, 55);
  });

  // Velocity: Würfel auf schnellen Bahnen
  A.scene('bn-velocity', BW, BH, function (ctx, W, H) {
    const r = A.rng(66);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#0a1230'], [0.55, '#10244f'], [1, '#081028']]);
    A.radial(ctx, W * 0.8, H * 0.1, 760, 'rgba(90,210,255,.25)', 'rgba(90,210,255,0)', 'screen');
    ctx.lineWidth = 1; ctx.strokeStyle = 'rgba(120,190,255,.09)'; ctx.beginPath();
    for (let k = -40; k < 80; k++) { ctx.moveTo(k * 68, 0); ctx.lineTo(k * 68 + H * 2, H); ctx.moveTo(k * 68, 0); ctx.lineTo(k * 68 - H * 2, H); }
    ctx.stroke();
    const items = [];
    for (let k = 0; k < 11; k++) items.push([r() * W * 1.05, 30 + r() * (H * 0.7), 14 + r() * 18, 280 + r() * 520, r()]);
    items.push([1040, 156, 54, 980, 2]);
    // Vordergrund: drei schwere Quader, fast schwarz, mit kurzer Bahn
    items.push([180, 340, 70, 420, 0.02]);
    items.push([620, 390, 86, 360, 0.02]);
    items.push([1480, 360, 74, 300, 0.02]);
    items.sort(function (a, b) { return a[1] - b[1]; });
    items.forEach(function (q) {
      const x = q[0], y = q[1], tw = q[2], th = tw / 2, d = tw * 0.95, L = q[3], hero = q[4] === 2;
      const near = q[4] === 0.02;
      const col = hero ? '#eafcff' : near ? '#07111f' : q[4] < 0.25 ? '#b9f3ff' : q[4] < 0.7 ? '#4fd0ff' : '#3f8cff';
      const ux = 2 / Math.sqrt(5), uy = -1 / Math.sqrt(5), tx = x - ux * L, ty = y + th - uy * L;
      const g = ctx.createLinearGradient(tx, ty, x, y + th);
      g.addColorStop(0, A.rgba(col, 0)); g.addColorStop(1, A.rgba(col, hero ? 0.7 : near ? 0.2 : 0.4));
      ctx.fillStyle = g; ctx.beginPath();
      ctx.moveTo(x - tw, y + th); ctx.lineTo(x, y + 2 * th); ctx.lineTo(x - ux * L, y + 2 * th - uy * L); ctx.lineTo(x - tw - ux * L, y + th - uy * L); ctx.closePath(); ctx.fill();
      const g2 = ctx.createLinearGradient(tx, ty, x, y + th);
      g2.addColorStop(0, A.rgba(col, 0)); g2.addColorStop(1, A.rgba(A.shade(col, 0.6), hero ? 0.55 : 0.4));
      ctx.fillStyle = g2; ctx.beginPath();
      ctx.moveTo(x, y + 2 * th); ctx.lineTo(x, y + 2 * th + d); ctx.lineTo(x - ux * L, y + 2 * th + d - uy * L); ctx.lineTo(x - ux * L, y + 2 * th - uy * L); ctx.closePath(); ctx.fill();
      if (hero) A.radial(ctx, x, y + th, 200, 'rgba(150,230,255,.45)', 'rgba(150,230,255,0)', 'screen');
      const edge = near ? null : 'rgba(255,255,255,.6)';
      const right = hero ? '#ffffff' : near ? '#102033' : A.shade(col, 0.74);
      A.cube(ctx, x, y, tw, th, d, near ? '#0c1828' : col, A.shade(col, near ? 0.35 : 0.5), right, edge);
    });
    A.band(ctx, W, 150, 70, '#8fd8ff', 0.08);
    A.volumetric(ctx, 1288, 108, '#7ad4ff', { r: 360, a: 0.26, shafts: 4, a0: Math.PI * 0.55, a1: Math.PI * 1.05 });
    A.punkt(ctx, 1288, 108, 40, '#f4feff', '#9ae4ff', 200);
    ctx.lineCap = 'round';
    for (let k = 0; k < 28; k++) {
      const x = r() * W * 1.2, y = r() * H * 1.3, L = 60 + r() * 260;
      ctx.strokeStyle = 'rgba(190,235,255,' + (0.08 + r() * 0.25) + ')'; ctx.lineWidth = 1 + r() * 1.4;
      ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + L * 0.894, y - L * 0.447); ctx.stroke();
    }
    A.vignette(ctx, W, H, '#040818', 0.5); A.grain(ctx, W, H, 9, 66);
  });
})();
