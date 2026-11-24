/* Weitere Landschafts-Banner: Source-Spiele, Deno, Bun. */
(function () {
  const A = window.A, BW = 1600, BH = 520;

  function plateau(n, W, base, amp, scale, off, a, b) {
    const ys = new Float32Array(W + 1);
    for (let x = 0; x <= W; x++) {
      const m = n.fbm(x / scale + off, off * 0.7, 2);
      ys[x] = base - amp * A.smooth(a, a + 0.07, m) - amp * 0.45 * A.smooth(b, b + 0.05, m) - 3 * n(x / 26, off);
    }
    return ys;
  }
  function hfill(ctx, ys, H, c0, c1) {
    const g = ctx.createLinearGradient(0, 0, ys.length, 0);
    g.addColorStop(0.12, c0); g.addColorStop(0.5, A.mix(c0, c1, 0.5)); g.addColorStop(0.88, c1);
    A.fillRidge(ctx, ys, H, g);
  }

  // Insurgency: staubige Flachdach-Stadt im Gegenlicht
  A.scene('bn-insurgency', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(41), r = A.rng(43);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#4b4654'], [0.24, '#8c766a'], [0.48, '#dcc196'], [1, '#dcc196']]);
    A.punkt(ctx, 1150, 166, 52, '#fdf3d6', '#ffe2aa', 300);
    A.fillRidge(ctx, A.ridge(W, 232, 30, 420, n, 2, 3), H, '#c8aa84');
    function town(base, color, minH, maxH, minW, maxW, detail, win) {
      let x = -30;
      ctx.fillStyle = color; ctx.fillRect(0, base, W, H);
      while (x < W + 30) {
        const w = minW + r() * (maxW - minW), h = minH + r() * (maxH - minH), top = base - h;
        ctx.fillStyle = color; ctx.fillRect(x, top, w + 1, h + 2);
        if (detail) {
          ctx.fillRect(x - 2, top - 3, w + 4, 4);
          if (r() < 0.35) ctx.fillRect(x + w * 0.2, top - 15, 16, 13);
          if (r() < 0.3) { ctx.fillRect(x + w * 0.7, top - 34, 2, 32); ctx.fillRect(x + w * 0.7 - 6, top - 28, 14, 2); }
          if (r() < 0.05) { const tx = x + w * 0.5; ctx.fillRect(tx - 1.5, top - 76, 3, 76); ctx.fillRect(tx - 8, top - 66, 16, 2); ctx.fillRect(tx - 6, top - 54, 12, 2); }
          if (r() < 0.12) { ctx.beginPath(); ctx.arc(x + w * 0.5, top, Math.min(22, w * 0.3), Math.PI, 0); ctx.fill(); }
          if (win) {
            for (let wy = top + 12; wy < base + 60; wy += 24) for (let wx = x + 9; wx < x + w - 12; wx += 20) {
              const q = r(); if (q < 0.35) continue;
              ctx.fillStyle = q > 0.95 ? '#f7c56c' : win; ctx.fillRect(wx, wy, 8, 12);
            }
            ctx.fillStyle = color;
          }
        }
        x += w + (r() < 0.25 ? r() * 36 : 0);
      }
    }
    town(258, '#b0916f', 10, 44, 30, 84, false);
    A.vgrad(ctx, 0, 190, W, 110, [[0, 'rgba(220,193,150,0)'], [1, 'rgba(220,193,150,.5)']]);
    town(296, '#846856', 22, 84, 44, 110, true, '#6f5648');
    A.vgrad(ctx, 0, 220, W, 130, [[0, 'rgba(214,180,140,0)'], [1, 'rgba(214,180,140,.42)']]);
    town(352, '#40323a', 36, 124, 60, 150, true, '#33272f');
    // Leitungen
    ctx.strokeStyle = 'rgba(52,38,46,.8)'; ctx.lineWidth = 1.2;
    [[-20, 250, 420, 236], [420, 236, 900, 262], [900, 262, 1640, 232]].forEach(function (q) {
      ctx.beginPath(); ctx.moveTo(q[0], q[1]); ctx.quadraticCurveTo((q[0] + q[2]) / 2, Math.max(q[1], q[3]) + 26, q[2], q[3]); ctx.stroke();
    });
    // Minarett als lesbare Silhouette, rechte Kante im Licht
    (function () {
      const x = 430, top = 150, b = 352;
      ctx.fillStyle = '#2a2228';
      ctx.fillRect(x, top, 28, b - top);
      ctx.beginPath(); ctx.moveTo(x - 8, top + 10); ctx.lineTo(x + 14, top - 36); ctx.lineTo(x + 36, top + 10); ctx.fill();
      ctx.strokeStyle = 'rgba(255,236,210,.65)'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(x + 28, top); ctx.lineTo(x + 28, b - 40); ctx.stroke();
    })();
    A.band(ctx, W, 250, 70, '#e6d2b4', 0.1);
    A.volumetric(ctx, 1150, 166, '#ffe2aa', { r: 300, a: 0.16, shafts: 3 });
    A.stipple(ctx, 0, 0, W, H, 30000, 'rgba(255,236,200,.16)', function (x, y) { return A.clamp(1 - Math.abs(y - 250) / 150, 0, 1) * 0.55; }, r, 1.4);
    A.finish(ctx, W, H, '#1c1418', 9, 41);
  });

  // Team Fortress 2: Tafelberge, warm gegen kalt, Industrie-Silhouetten
  A.scene('bn-team-fortress', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(52), r = A.rng(52);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#f1dcb0'], [0.46, '#f6cf98'], [1, '#f1c088']]);
    for (let k = 0; k < 7; k++) { const w = 240 + r() * 520; ctx.fillStyle = 'rgba(255,246,220,.5)'; A.rrect(ctx, r() * W - w / 2, 34 + k * 24 + r() * 10, w, 5 + r() * 7, 5); ctx.fill(); }
    A.punkt(ctx, 1010, 160, 54, '#fffbea', '#fff0c0', 240);
    hfill(ctx, plateau(n, W, 250, 60, 300, 1.5, 0, 0.2), H, '#e09a68', '#a8bfc4');
    const mid = plateau(n, W, 292, 74, 380, 6.2, -0.05, 0.18);
    hfill(ctx, mid, H, '#bd4b30', '#4f7f9a');
    A.shadeRidge(ctx, mid, H, 'rgba(60,20,30,.5)', r, 90000, 0.6, 130);
    const near = A.ridge(W, 352, 20, 500, n, 9, 2);
    hfill(ctx, near, H, '#6b261f', '#23455c');
    // Silhouetten auf der vorderen Kante
    const g = ctx.createLinearGradient(0, 0, W, 0); g.addColorStop(0.15, '#571d1a'); g.addColorStop(0.85, '#1b384c');
    ctx.fillStyle = g; ctx.strokeStyle = g; ctx.lineCap = 'butt';
    const gy = function (x) { return near[Math.round(x)] + 3; };
    function tower(x, s) { const y = gy(x); ctx.lineWidth = 3 * s; ctx.beginPath(); ctx.moveTo(x - 20 * s, y); ctx.lineTo(x - 13 * s, y - 70 * s); ctx.moveTo(x + 20 * s, y); ctx.lineTo(x + 13 * s, y - 70 * s); ctx.moveTo(x - 17 * s, y - 30 * s); ctx.lineTo(x + 15 * s, y - 60 * s); ctx.moveTo(x + 17 * s, y - 30 * s); ctx.lineTo(x - 15 * s, y - 60 * s); ctx.stroke(); A.rrect(ctx, x - 26 * s, y - 112 * s, 52 * s, 44 * s, 6 * s); ctx.fill(); ctx.beginPath(); ctx.moveTo(x - 28 * s, y - 110 * s); ctx.lineTo(x, y - 128 * s); ctx.lineTo(x + 28 * s, y - 110 * s); ctx.fill(); }
    function barn(x, w, h) { const y = gy(x + w / 2); ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x, y - h); ctx.lineTo(x + w * 0.22, y - h * 1.42); ctx.lineTo(x + w * 0.78, y - h * 1.42); ctx.lineTo(x + w, y - h); ctx.lineTo(x + w, y); ctx.fill(); }
    function silo(x, w, h) { const y = gy(x); ctx.fillRect(x - w / 2, y - h, w, h + 4); ctx.beginPath(); ctx.arc(x, y - h, w / 2, Math.PI, 0); ctx.fill(); }
    function stack(x, w, h) { const y = gy(x); ctx.beginPath(); ctx.moveTo(x - w / 2, y); ctx.lineTo(x - w * 0.34, y - h); ctx.lineTo(x + w * 0.34, y - h); ctx.lineTo(x + w / 2, y); ctx.fill(); ctx.fillRect(x - w * 0.42, y - h - 4, w * 0.84, 5); }
    barn(120, 150, 62); silo(300, 40, 96); silo(346, 34, 74); tower(470, 1); barn(560, 90, 40);
    ctx.fillRect(1090, gy(1150) - 70, 190, 74); ctx.fillRect(1120, gy(1150) - 96, 90, 30);
    stack(1130, 22, 150); stack(1330, 26, 176); stack(1372, 20, 128); tower(1470, 0.9);
    A.mast(ctx, 880, gy(880), 96, 15, '#3a2a36');
    ctx.strokeStyle = '#3a2a36'; ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(488, gy(470) - 112); ctx.quadraticCurveTo(690, gy(690) - 60, 880, gy(880) - 96); ctx.quadraticCurveTo(990, gy(990) - 70, 1092, gy(1150) - 70); ctx.stroke();
    A.band(ctx, W, 250, 60, '#f6e0c0', 0.1);
    A.volumetric(ctx, 1010, 160, '#fff0c0', { r: 280, a: 0.14, shafts: 3 });
    A.finish(ctx, W, H, '#2a1420', 9, 52);
  });

  // Ark: Urwald im Dunst, Farnwedel im Vordergrund, ferner Vulkankegel
  A.scene('bn-ark', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(63), r = A.rng(63);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#1f5f66'], [0.26, '#5fa697'], [0.5, '#e9ecc0'], [1, '#e9ecc0']]);
    A.punkt(ctx, 1136, 170, 54, '#fffbe0', '#fff2b8', 330);
    // Vulkankegel
    ctx.fillStyle = '#7fb5a4'; ctx.beginPath(); ctx.moveTo(520, 300); ctx.quadraticCurveTo(700, 250, 772, 118); ctx.lineTo(790, 126); ctx.lineTo(812, 116); ctx.lineTo(838, 122);
    ctx.quadraticCurveTo(920, 250, 1130, 300); ctx.fill();
    A.vgrad(ctx, 0, 150, W, 150, [[0, 'rgba(233,236,192,0)'], [1, 'rgba(233,236,192,.75)']]);
    function canopy(base, amp, off, color) {
      const ys = new Float32Array(W + 1);
      for (let x = 0; x <= W; x++) ys[x] = base - amp * (n.fbm(x / 300 + off, off, 3) * 0.5 + 0.5) - 9 * Math.abs(n(x / 16 + off, 3)) - 14 * Math.abs(n(x / 44 + off, 9));
      A.fillRidge(ctx, ys, H, color); return ys;
    }
    function palm(x, y, h, color) {
      ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineWidth = Math.max(2, h * 0.04); ctx.lineCap = 'round';
      const tx = x + h * 0.14, ty = y - h;
      ctx.beginPath(); ctx.moveTo(x, y); ctx.quadraticCurveTo(x - h * 0.05, y - h * 0.5, tx, ty); ctx.stroke();
      for (let k = 0; k < 7; k++) {
        const a = -Math.PI * 0.5 + (k - 3) * 0.52 + (r() - 0.5) * 0.2, L = h * (0.42 + r() * 0.12);
        const ex = tx + Math.cos(a) * L, ey = ty + Math.sin(a) * L * 0.55 + L * 0.42;
        ctx.beginPath(); ctx.moveTo(tx, ty); ctx.quadraticCurveTo(tx + Math.cos(a) * L * 0.6, ty + Math.sin(a) * L * 0.7 - L * 0.05, ex, ey);
        ctx.quadraticCurveTo(tx + Math.cos(a) * L * 0.5, ty + Math.sin(a) * L * 0.4 + L * 0.1, tx, ty); ctx.fill();
      }
    }
    const c1 = canopy(262, 50, 1.2, '#4f9a86');
    [150, 420, 660, 1290, 1480].forEach(function (x) { palm(x, c1[x] + 6, 70 + r() * 30, '#4f9a86'); });
    A.vgrad(ctx, 0, 200, W, 130, [[0, 'rgba(200,226,186,0)'], [1, 'rgba(200,226,186,.5)']]);
    const c2 = canopy(318, 60, 5.4, '#2a7466');
    [60, 300, 560, 960, 1210, 1400, 1560].forEach(function (x) { palm(x, c2[x] + 8, 100 + r() * 50, '#2a7466'); });
    A.shadeRidge(ctx, c2, H, 'rgba(10,50,50,.6)', r, 90000, 0.5, 130);
    canopy(396, 50, 9.1, '#124b47');
    // Farnwedel
    function frond(x0, y0, ang, len, bend, color) {
      ctx.fillStyle = color; ctx.strokeStyle = color; ctx.lineWidth = 3;
      const cx = x0 + Math.cos(ang) * len * 0.5 - Math.sin(ang) * bend, cy = y0 + Math.sin(ang) * len * 0.5 + Math.cos(ang) * bend;
      const ex = x0 + Math.cos(ang) * len, ey = y0 + Math.sin(ang) * len + Math.abs(bend) * 0.5;
      ctx.beginPath(); ctx.moveTo(x0, y0); ctx.quadraticCurveTo(cx, cy, ex, ey); ctx.stroke();
      const N = Math.round(len / 13);
      for (let k = 1; k < N; k++) {
        const t = k / N, u = 1 - t;
        const px = u * u * x0 + 2 * u * t * cx + t * t * ex, py = u * u * y0 + 2 * u * t * cy + t * t * ey;
        const dx = 2 * u * (cx - x0) + 2 * t * (ex - cx), dy = 2 * u * (cy - y0) + 2 * t * (ey - cy), dl = Math.hypot(dx, dy);
        const ux = dx / dl, uy = dy / dl, L = len * 0.24 * Math.pow(Math.sin(Math.PI * (0.08 + t * 0.92)), 0.7);
        [1, -1].forEach(function (s) {
          const lx = px + (-uy * s * 0.9 + ux * 0.5) * L, ly = py + (ux * s * 0.9 + uy * 0.5) * L;
          ctx.beginPath(); ctx.moveTo(px - ux * 5, py - uy * 5); ctx.quadraticCurveTo(px + (-uy * s) * L * 0.6, py + (ux * s) * L * 0.6, lx, ly); ctx.quadraticCurveTo(px + ux * 5 + (-uy * s) * L * 0.25, py + uy * 5 + (ux * s) * L * 0.25, px + ux * 6, py + uy * 6); ctx.fill();
        });
      }
    }
    frond(-30, 330, -0.5, 420, -70, '#0b3033'); frond(-40, 210, -0.2, 330, -50, '#0f3a3a'); frond(-20, 60, 0.35, 300, -40, '#0b3033');
    frond(1640, 300, Math.PI + 0.45, 400, 70, '#0b3033'); frond(1640, 120, Math.PI - 0.25, 300, 40, '#0f3a3a'); frond(1500, -30, Math.PI * 0.62, 260, 30, '#0b3033');
    A.band(ctx, W, 240, 70, '#e7f0c8', 0.1);
    A.volumetric(ctx, 1136, 170, '#fff2b8', { r: 300, a: 0.14, shafts: 3 });
    A.finish(ctx, W, H, '#062022', 9, 63);
  });

  // Garry's Mod: monumentaler Baukasten in der Dämmerung. Eine Steinmasse, ein Glutkörper,
  // der Punkt als Sonne. Keine bunten Spielzeugkörper.
  A.scene('bn-garrys-mod', BW, BH, function (ctx, W, H) {
    const r = A.rng(74);
    const sky = '#12151e', haze = '#8d7568', stone = '#6e655c', lit = '#f0e2d0', ember = '#e08a40';
    A.vgrad(ctx, 0, 0, W, H, [[0, sky], [0.28, '#222838'], [0.48, '#5c463c'], [0.62, '#c49a74'], [1, '#8a5a40']]);
    A.radial(ctx, 1210, 128, 700, 'rgba(255,186,120,.2)', 'rgba(255,186,120,0)', 'screen', 1.3, 0.7);
    // Ferne Blockskyline, fast nur Silhouette
    ctx.fillStyle = '#5c514a';
    [[40, 78], [150, 46], [230, 96], [340, 58], [430, 120], [560, 40], [1280, 70], [1390, 110], [1510, 52]].forEach(function (q) {
      ctx.fillRect(q[0], 236 - q[1], 70 + (q[0] % 50), q[1]);
    });
    A.band(ctx, W, 214, 70, '#e4cbb4', 0.22);
    // Boden, nach unten ruhig
    A.vgrad(ctx, 0, 248, W, H - 248, [[0, '#3a332c'], [0.35, '#241e1a'], [1, '#100e0c']]);
    ctx.strokeStyle = 'rgba(255,220,180,.07)'; ctx.lineWidth = 1; ctx.beginPath();
    for (let k = -6; k <= 8; k++) { ctx.moveTo(860, 250); ctx.lineTo(200 + k * 160, 470); }
    ctx.stroke();
    // Ferner Quader, ins Himmelslicht gemischt
    A.cube(ctx, 390, 188, 40, 20, 36, A.mix(haze, '#d8c4b0', 0.45), A.mix(haze, '#4a4038', 0.5), A.mix(haze, lit, 0.35));
    // Held: großer Steinquader, rechte Fläche im Licht
    const hx = 760, hy = 146, tw = 88, th = 44, d = 82;
    A.cube(ctx, hx, hy, tw, th, d, '#cfc2b2', '#3a342e', lit, 'rgba(255,244,230,.45)');
    A.cube(ctx, hx - 8, hy - 62, 46, 23, 52, '#e4d8c8', '#4a433c', '#f7ecde', 'rgba(255,244,230,.5)');
    // Zylinder als Druckfläche, nicht als Glanzkörper
    (function () {
      const x = 470, y = 196, w = 34, h = 78;
      const g = ctx.createLinearGradient(x - w, 0, x + w, 0);
      g.addColorStop(0, '#3a342e'); g.addColorStop(0.55, stone); g.addColorStop(1, A.mix(stone, lit, 0.55));
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.ellipse(x, y + h, w, w * 0.34, 0, 0, Math.PI); ctx.lineTo(x - w, y); ctx.lineTo(x + w, y); ctx.fill();
      ctx.fillStyle = A.mix(stone, '#e7d8c4', 0.4);
      ctx.beginPath(); ctx.ellipse(x, y, w, w * 0.34, 0, 0, 7); ctx.fill();
    })();
    // Einziger gesättigter Körper: die Glut, direkt im Lichtkegel
    A.cube(ctx, 1048, 196, 34, 17, 40, '#ffd7a8', '#a85a28', '#ffb368', 'rgba(255,244,220,.7)');
    A.radial(ctx, 1066, 214, 90, 'rgba(255,170,80,.35)', 'rgba(255,170,80,0)', 'screen');
    // Linke Fläche des Helden als Punktraster
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(hx - tw, hy + th); ctx.lineTo(hx, hy + 2 * th); ctx.lineTo(hx, hy + 2 * th + d); ctx.lineTo(hx - tw, hy + th + d);
    ctx.closePath(); ctx.clip();
    A.stipple(ctx, hx - tw, hy, tw, th * 2 + d, 7000, 'rgba(28,20,14,.4)', function (x, y) {
      return A.clamp((hx - x) / tw * 0.45 + (y - hy) / 260, 0, 0.8);
    }, r, 1.4);
    ctx.restore();
    A.band(ctx, W, 236, 64, '#f0d8c0', 0.16);
    A.volumetric(ctx, 1210, 128, '#ffb36a', { r: 460, a: 0.3, shafts: 4, sy: 0.78 });
    A.punkt(ctx, 1210, 128, 52, '#fff3dd', '#ffc48a', 280);
    // Vordergrund-Silhouette: schwere Quader, die den Helden rahmen
    A.cube(ctx, 130, 292, 128, 64, 110, '#161310', '#0c0b09', '#241e18');
    A.cube(ctx, 1560, 276, 108, 54, 100, '#161310', '#0c0b09', '#3a2c24');
    A.finish(ctx, W, H, '#0a090c', 9, 74);
  });

  // Custom Source Engine Game: Testraum als Portal. Vordergrundrahmen, Monolith im Licht, Dunst.
  A.scene('bn-source', BW, BH, function (ctx, W, H) {
    const r = A.rng(85);
    const openL = 214, openR = 1386, openT = 86, hz = 246;
    A.vgrad(ctx, 0, 0, W, H, [[0, '#141822'], [0.28, '#2a303c'], [0.46, '#6a5344'], [0.58, '#e0b48a'], [1, '#1a140f']]);
    A.vgrad(ctx, 0, hz, W, H - hz, [[0, 'rgba(28,32,40,0)'], [0.25, 'rgba(18,20,26,.55)'], [1, '#0c0e14']]);
    // Bodenraster, nur in der Öffnung und nach unten auslaufend
    ctx.save();
    ctx.beginPath(); ctx.rect(openL, openT, openR - openL, H); ctx.clip();
    ctx.strokeStyle = 'rgba(210,196,176,.16)'; ctx.lineWidth = 1.2; ctx.beginPath();
    for (let k = -7; k <= 7; k++) { ctx.moveTo(980 + k * 28, hz); ctx.lineTo(openL + (k + 7) * ((openR - openL) / 14), 500); }
    for (let i = 0; i < 5; i++) { const y = hz + 16 + i * i * 9; ctx.moveTo(openL, y); ctx.lineTo(openR, y); }
    ctx.stroke();
    ctx.restore();
    A.band(ctx, W, hz - 6, 70, '#f0d2b4', 0.2);
    // Ferner Block, kontrastarm
    A.cube(ctx, 900, 196, 36, 18, 34, '#5c656e', '#323840', '#8d7b6c', 'rgba(255,236,220,.2)');
    // Held-Monolith, rechte Fläche voll im Licht des Punktes
    const hx = 610, hy = 156, tw = 74, th = 37, d = 86;
    A.cube(ctx, hx, hy, tw, th, d, '#8b93a0', '#2c333c', '#f0d2b0', 'rgba(255,236,214,.55)');
    A.cube(ctx, hx + 10, hy - 58, 36, 18, 40, '#c5ccd4', '#3a424c', '#ffe6c8', 'rgba(255,244,230,.6)');
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(hx - tw, hy + th); ctx.lineTo(hx, hy + 2 * th); ctx.lineTo(hx, hy + 2 * th + d); ctx.lineTo(hx - tw, hy + th + d);
    ctx.closePath(); ctx.clip();
    A.stipple(ctx, hx - tw, hy, tw, th * 2 + d, 6000, 'rgba(12,16,22,.38)', function (x) { return A.clamp((hx - x) / tw * 0.7, 0, 0.75); }, r, 1.4);
    ctx.restore();
    A.volumetric(ctx, 1124, 142, '#ffb15a', { r: 420, a: 0.34, shafts: 4, sy: 0.7 });
    A.punkt(ctx, 1124, 142, 48, '#ffd7a4', '#ffb15a', 260);
    // Vordergrund: der Rahmen schneidet das Licht ab
    ctx.fillStyle = '#0e1218';
    ctx.fillRect(0, 0, W, openT);
    ctx.fillRect(0, 0, openL, H);
    ctx.fillRect(openR, 0, W - openR, H);
    ctx.fillStyle = '#1a140f';
    ctx.fillRect(openL, 430, openR - openL, H - 430);
    // Kante, die das Licht fängt
    ctx.strokeStyle = 'rgba(255,196,140,.75)'; ctx.lineWidth = 3; ctx.beginPath();
    ctx.moveTo(openL, openT); ctx.lineTo(openL, 360);
    ctx.moveTo(openR, openT); ctx.lineTo(openR, 340);
    ctx.moveTo(openL, openT); ctx.lineTo(openR, openT);
    ctx.stroke();
    A.finish(ctx, W, H, '#05060a', 8, 85);
  });

  // Deno: Regennacht über Hügeln
  A.scene('bn-deno', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(96), r = A.rng(96);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#0c1219'], [0.3, '#182431'], [0.52, '#33475a'], [1, '#33475a']]);
    A.punkt(ctx, 1140, 168, 46, '#f4f8fb', '#c5dff0', 280);
    A.volumetric(ctx, 1140, 168, '#c5dff0', { r: 320, a: 0.18, shafts: 3, a0: Math.PI * 0.35, a1: Math.PI * 0.85 });
    for (let k = 0; k < 12; k++) { const w = 500 + r() * 900, x = r() * W - w / 2, y = -24 + k * 6 + r() * 8; ctx.fillStyle = 'rgba(6,10,15,.3)'; A.rrect(ctx, x, y, w, 30 + r() * 26, 28); ctx.fill(); }
    [['#2b3e50', 262, 44, 380], ['#1c2b3a', 306, 50, 300], ['#111c27', 366, 44, 420]].forEach(function (q, k) {
      const ys = A.ridge(W, q[1], q[2], q[3], n, k * 4 + 1, 3);
      A.fillRidge(ctx, ys, H, q[0]);
      A.crest(ctx, ys, 'rgba(190,220,240,' + (0.3 - k * 0.07) + ')', 1.4);
      A.shadeRidge(ctx, ys, H, 'rgba(5,10,16,.6)', r, 40000, 0.45, 120);
    });
    // Mast auf dem mittleren Kamm, rechte Kante im Mondlicht
    (function () {
      const x = 640, yb = 300, h = 168;
      ctx.fillStyle = '#0c141c';
      ctx.beginPath(); ctx.moveTo(x - 16, yb); ctx.lineTo(x - 5, yb - h); ctx.lineTo(x + 6, yb - h); ctx.lineTo(x + 16, yb); ctx.fill();
      ctx.fillRect(x - 26, yb - h * 0.62, 52, 5);
      ctx.strokeStyle = 'rgba(230,242,250,.7)'; ctx.lineWidth = 2; ctx.beginPath();
      ctx.moveTo(x + 6, yb - h); ctx.lineTo(x + 16, yb); ctx.stroke();
    })();
    A.band(ctx, W, 250, 70, '#9eb6c8', 0.1);
    ctx.lineCap = 'round';
    for (let k = 0; k < 520; k++) {
      const x = r() * (W + 200), y = r() * H * 0.78 - 20, L = 16 + r() * r() * 80;
      if (y > H * 0.72) continue;
      ctx.strokeStyle = 'rgba(214,232,244,' + (0.08 + r() * r() * 0.45) + ')'; ctx.lineWidth = 1.3 + r() * 1.5;
      ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - L * 0.28, y + L); ctx.stroke();
    }
    A.finish(ctx, W, H, '#04070b', 9, 96);
  });

  // Bun: gebrannte Tonhügel im Gegenlicht. Rund, aber als Landschaft, nicht als Gebäck.
  A.scene('bn-bun', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(107), r = A.rng(107);
    function dome(base, amp, cx, width, wobble) {
      const ys = new Float32Array(W + 1);
      for (let x = 0; x <= W; x++) {
        const u = (x - cx) / width;
        const bell = Math.exp(-u * u * 1.7);
        const w = wobble * n.fbm(x / 380 + cx * 0.01, wobble, 3);
        ys[x] = base - amp * A.clamp(bell + w * (0.25 + bell), 0, 1.15);
      }
      return ys;
    }
    A.vgrad(ctx, 0, 0, W, H, [[0, '#f6e6d4'], [0.3, '#e7c3a2'], [0.5, '#d39a72'], [1, '#5c2c20']]);
    A.radial(ctx, 1200, 142, 520, 'rgba(255,210,170,.4)', 'rgba(255,210,170,0)');
    const farA = dome(248, 78, 380, 520, 0.35);
    const farB = dome(256, 64, 1280, 460, 0.3);
    A.fillRidge(ctx, farA, H, '#edd6c2');
    A.fillRidge(ctx, farB, H, '#e8d0ba');
    A.crest(ctx, farA, 'rgba(255,248,238,.35)', 1.2);
    A.band(ctx, W, 230, 80, '#f8ecdf', 0.28);
    const mid = dome(318, 132, 1040, 460, 0.22);
    A.fillRidge(ctx, mid, H, '#d4926a');
    A.shadeRidge(ctx, mid, H, 'rgba(90,40,24,.7)', r, 42000, 0.45, 150);
    // Grat nur auf der lichtzugewandten Schulter
    ctx.strokeStyle = 'rgba(255,236,214,.85)'; ctx.lineWidth = 2.4; ctx.beginPath();
    for (let x = 980; x < 1320; x++) ctx.lineTo(x, mid[x] + 0.5);
    ctx.stroke();
    A.volumetric(ctx, 1204, 146, '#ff8a55', { r: 340, a: 0.26, shafts: 3, a0: Math.PI * 0.5, a1: Math.PI * 0.95, sy: 0.55 });
    A.punkt(ctx, 1204, 146, 48, '#e25b32', '#ffb088', 220);
    const nearL = dome(400, 108, 260, 420, 0.2);
    const nearR = dome(424, 96, 1180, 560, 0.16);
    A.fillRidge(ctx, nearL, H, '#a85a38');
    A.shadeRidge(ctx, nearL, H, 'rgba(70,28,16,.75)', r, 28000, 0.4, 120);
    A.fillRidge(ctx, nearR, H, '#7a3a28');
    A.shadeRidge(ctx, nearR, H, 'rgba(50,20,12,.8)', r, 28000, 0.4, 130);
    A.crest(ctx, nearR, 'rgba(255,214,180,.28)', 1.3);
    // Vordergrund als fast schwarze Tonmasse, unten ruhig
    const front = dome(492, 78, 760, 700, 0.1);
    A.fillRidge(ctx, front, H, '#4e261c');
    // Brennofen als Vordergrund-Silhouette links, Öffnung dunkel, rechte Kante im Licht
    (function () {
      const x = 120, y = 210, w = 280, h = 250;
      ctx.fillStyle = '#4a2418';
      ctx.beginPath();
      ctx.moveTo(x, H + 4); ctx.lineTo(x, y + h * 0.42);
      ctx.quadraticCurveTo(x + w * 0.5, y, x + w, y + h * 0.42);
      ctx.lineTo(x + w, H + 4); ctx.fill();
      ctx.fillStyle = '#24110c';
      ctx.beginPath();
      ctx.moveTo(x + w * 0.28, H + 4); ctx.lineTo(x + w * 0.28, y + h * 0.58);
      ctx.quadraticCurveTo(x + w * 0.5, y + h * 0.34, x + w * 0.72, y + h * 0.58);
      ctx.lineTo(x + w * 0.72, H + 4); ctx.fill();
      ctx.strokeStyle = 'rgba(255,214,180,.75)'; ctx.lineWidth = 2.5; ctx.beginPath();
      ctx.moveTo(x + w, y + h * 0.42); ctx.lineTo(x + w, y + h); ctx.stroke();
    })();
    A.finish(ctx, W, H, '#3a1c14', 8, 107);
  });
})();
