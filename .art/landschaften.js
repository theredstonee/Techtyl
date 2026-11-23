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
    A.stipple(ctx, 0, 0, W, H, 60000, 'rgba(255,236,200,.2)', function (x, y) { return A.clamp(1 - Math.abs(y - 250) / 150, 0, 1) * 0.6; }, r, 1.4);
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
    A.finish(ctx, W, H, '#062022', 9, 63);
  });

  // Garry's Mod: Baukasten – schwebende Grundkörper über weiter Wiese
  A.scene('bn-garrys-mod', BW, BH, function (ctx, W, H) {
    const r = A.rng(74), hz = 262;
    A.vgrad(ctx, 0, 0, W, hz, [[0, '#4f93d8'], [1, '#c9e6f6']]);
    A.vgrad(ctx, 0, hz, W, H - hz, [[0, '#8cc65c'], [0.35, '#5a9c45'], [1, '#2f6a33']]);
    for (let k = 0; k < 9; k++) {
      const x = r() * W, y = 30 + r() * 150, s = 0.6 + r();
      ctx.fillStyle = 'rgba(255,255,255,.78)';
      A.rrect(ctx, x, y, 150 * s, 22 * s, 11 * s); ctx.fill(); A.rrect(ctx, x + 34 * s, y - 14 * s, 80 * s, 24 * s, 12 * s); ctx.fill();
    }
    A.punkt(ctx, 1196, 178, 50, '#fffdf0', '#fff6c8', 260);
    ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1; ctx.beginPath();
    for (let k = -30; k <= 30; k++) { ctx.moveTo(W * 0.5, hz); ctx.lineTo(W * 0.5 + k * 190, H); }
    for (let z = 1; z < 18; z++) { const y = hz + 270 / z; ctx.moveTo(0, y); ctx.lineTo(W, y); }
    ctx.stroke();
    function shadow(x, y, w) { ctx.fillStyle = 'rgba(20,60,30,.3)'; ctx.beginPath(); ctx.ellipse(x - 26, y, w, w * 0.2, 0, 0, 7); ctx.fill(); }
    function ball(x, y, rad, col) {
      shadow(x, 318 + rad * 0.3, rad * 1.1);
      const g = ctx.createRadialGradient(x + rad * 0.4, y - rad * 0.45, rad * 0.1, x, y, rad);
      g.addColorStop(0, A.mix(col, '#ffffff', 0.45)); g.addColorStop(0.55, col); g.addColorStop(1, A.shade(col, 0.6));
      ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, rad, 0, 7); ctx.fill();
      ctx.save(); ctx.beginPath(); ctx.arc(x, y, rad, 0, 7); ctx.clip();
      A.stipple(ctx, x - rad, y - rad, rad * 2, rad * 2, rad * rad * 1.6, A.rgba(A.shade(col, 0.4), 0.7), function (px, py) { return A.clamp(((x - px) + (py - y)) / rad * 0.5, 0, 1); }, r, 1.4);
      ctx.restore();
    }
    function box(x, y, tw, col) { shadow(x, 322 + tw * 0.2, tw * 1.25); A.cube(ctx, x, y, tw, tw / 2, tw * 1.05, A.mix(col, '#ffffff', 0.18), A.shade(col, 0.62), A.shade(col, 0.84), 'rgba(255,255,255,.45)'); }
    function cyl(x, y, w, h, col) {
      shadow(x, 320, w * 1.2);
      const g = ctx.createLinearGradient(x - w, 0, x + w, 0); g.addColorStop(0, A.shade(col, 0.6)); g.addColorStop(0.7, col); g.addColorStop(1, A.mix(col, '#ffffff', 0.25));
      ctx.fillStyle = g; ctx.beginPath(); ctx.ellipse(x, y + h, w, w * 0.32, 0, 0, Math.PI); ctx.lineTo(x - w, y); ctx.lineTo(x + w, y); ctx.fill();
      ctx.fillStyle = A.mix(col, '#ffffff', 0.3); ctx.beginPath(); ctx.ellipse(x, y, w, w * 0.32, 0, 0, 7); ctx.fill();
    }
    function cone(x, y, w, h, col) {
      shadow(x, 316, w * 1.2);
      const g = ctx.createLinearGradient(x - w, 0, x + w, 0); g.addColorStop(0, A.shade(col, 0.62)); g.addColorStop(0.7, col); g.addColorStop(1, A.mix(col, '#ffffff', 0.3));
      ctx.fillStyle = g; ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + w, y + h); ctx.ellipse(x, y + h, w, w * 0.3, 0, 0, Math.PI); ctx.lineTo(x, y); ctx.fill();
    }
    cone(170, 96, 44, 104, '#f2c230'); box(430, 70, 58, '#f0863a'); ball(690, 150, 50, '#3f7fd6'); cyl(905, 72, 38, 100, '#d9534f');
    box(1085, 150, 30, '#8a5cc9'); ball(1290, 110, 34, '#35b08a'); box(1470, 60, 46, '#e8e4da'); ball(300, 220, 20, '#e8e4da'); box(800, 40, 20, '#f2c230');
    A.finish(ctx, W, H, '#10301c', 8, 74);
  });

  // Custom Source Engine Game: nüchterner Testraum mit Messraster
  A.scene('bn-source', BW, BH, function (ctx, W, H) {
    const hz = 224, vx = W * 0.64, r = A.rng(85);
    A.vgrad(ctx, 0, 0, W, hz, [[0, '#14171c'], [1, '#39414c']]);
    A.vgrad(ctx, 0, hz, W, H - hz, [[0, '#3a424d'], [0.25, '#272c34'], [1, '#121418']]);
    // Rückwand-Paneele
    ctx.strokeStyle = 'rgba(255,255,255,.07)'; ctx.lineWidth = 1; ctx.beginPath();
    for (let x = vx % 96; x < W; x += 96) { ctx.moveTo(x + 0.5, 0); ctx.lineTo(x + 0.5, hz); }
    for (let y = hz - 48; y > 0; y -= 48) { ctx.moveTo(0, y + 0.5); ctx.lineTo(W, y + 0.5); }
    ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.035)';
    for (let x = vx % 96 - 96, k = 0; x < W; x += 96, k++) for (let y = hz - 48, j = 0; y > -48; y -= 48, j++) if ((k + j) & 1) ctx.fillRect(x, y, 96, 48);
    const X = function (kx, y) { return vx + kx * 150 * (y - hz) / 296; };
    const Y = function (z) { return hz + 296 / z; };
    for (let z = 1; z < 26; z++) for (let kx = -40; kx < 40; kx++) {
      const y0 = Y(z + 1), y1 = Y(z), h = A.hash(kx, z, 8);
      let col = null;
      if ((kx + z) & 1) col = 'rgba(255,255,255,.045)';
      if (h < 0.07 && z > 2) col = 'rgba(240,138,36,' + A.clamp(0.9 - z * 0.03, 0.3, 0.9) + ')';
      if (!col) continue;
      ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(X(kx, y0), y0); ctx.lineTo(X(kx + 1, y0), y0); ctx.lineTo(X(kx + 1, y1), y1); ctx.lineTo(X(kx, y1), y1); ctx.fill();
    }
    ctx.strokeStyle = 'rgba(190,205,225,.2)'; ctx.beginPath();
    for (let kx = -60; kx <= 60; kx++) { ctx.moveTo(vx, hz); ctx.lineTo(X(kx, H), H); }
    for (let z = 1; z < 40; z++) { ctx.moveTo(0, Y(z)); ctx.lineTo(W, Y(z)); }
    ctx.stroke();
    ctx.fillStyle = '#4a535f'; ctx.fillRect(0, hz - 1, W, 2);
    // Maßmarken und Lichtquelle
    ctx.fillStyle = 'rgba(240,138,36,.8)';
    for (let x = vx % 96; x < W; x += 96) ctx.fillRect(x - 1, hz - 14, 3, 10);
    A.radial(ctx, vx, hz + 50, 420, 'rgba(240,150,60,.3)', 'rgba(240,150,60,0)', 'screen', 1.6, 0.4);
    A.punkt(ctx, vx, 158, 46, '#f6a03c', '#f6a03c', 260);
    // zwei Messkörper
    A.cube(ctx, 380, 150, 62, 31, 70, '#59626e', '#2c323a', '#414952', 'rgba(255,255,255,.3)');
    A.cube(ctx, 470, 215, 34, 17, 38, '#f08a24', '#8f4f12', '#c26d1a', 'rgba(255,255,255,.4)');
    A.finish(ctx, W, H, '#050608', 8, 85);
  });

  // Deno: Regennacht über Hügeln
  A.scene('bn-deno', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(96), r = A.rng(96);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#0c1219'], [0.3, '#182431'], [0.52, '#33475a'], [1, '#33475a']]);
    A.punkt(ctx, 1140, 172, 48, '#f1f6f8', '#a9cbe0', 300);
    for (let k = 0; k < 12; k++) { const w = 500 + r() * 900, x = r() * W - w / 2, y = -24 + k * 6 + r() * 8; ctx.fillStyle = 'rgba(6,10,15,.3)'; A.rrect(ctx, x, y, w, 30 + r() * 26, 28); ctx.fill(); }
    [['#2b3e50', 262, 44, 380], ['#1c2b3a', 306, 50, 300], ['#111c27', 366, 44, 420]].forEach(function (q, k) {
      const ys = A.ridge(W, q[1], q[2], q[3], n, k * 4 + 1, 3);
      A.fillRidge(ctx, ys, H, q[0]);
      A.crest(ctx, ys, 'rgba(190,220,240,' + (0.3 - k * 0.07) + ')', 1.4);
      A.shadeRidge(ctx, ys, H, 'rgba(5,10,16,.6)', r, 70000, 0.6, 120);
    });
    ctx.lineCap = 'round';
    for (let k = 0; k < 1300; k++) {
      const x = r() * (W + 200), y = r() * (H + 40) - 40, L = 12 + r() * r() * 70;
      ctx.strokeStyle = 'rgba(214,232,244,' + (0.06 + r() * r() * 0.5) + ')'; ctx.lineWidth = 0.8 + r() * 1.2;
      ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - L * 0.3, y + L); ctx.stroke();
    }
    A.finish(ctx, W, H, '#04070b', 9, 96);
  });

  // Bun: warme, runde Teighügel mit Körnern
  A.scene('bn-bun', BW, BH, function (ctx, W, H) {
    const r = A.rng(107);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#fbe6cc'], [0.5, '#f9d3ae'], [1, '#f3bd94']]);
    A.radial(ctx, 1150, 148, 360, 'rgba(255,255,255,.75)', 'rgba(255,255,255,0)');
    A.punkt(ctx, 1150, 148, 48, '#f5906a');
    function dome(x, base, rx, ry, col, seeds) {
      const path = function () { ctx.beginPath(); ctx.ellipse(x, base, rx, ry, 0, Math.PI, 0); ctx.lineTo(x + rx, H + 10); ctx.lineTo(x - rx, H + 10); ctx.closePath(); };
      const g = ctx.createLinearGradient(0, base - ry, 0, base + 60); g.addColorStop(0, A.mix(col, '#fff3dc', 0.3)); g.addColorStop(0.5, col); g.addColorStop(1, A.shade(col, 0.82));
      path(); ctx.fillStyle = g; ctx.fill();
      ctx.save(); path(); ctx.clip();
      A.stipple(ctx, x - rx, base - ry, rx * 2, ry + 80, rx * ry * 0.5, A.rgba(A.shade(col, 0.55), 0.7), function (px, py) {
        const u = (x - px) / rx, v = (py - (base - ry)) / ry; return A.clamp(u * 0.7 + v * 0.45 - 0.25, 0, 1) * 0.7;
      }, r, 1.4);
      ctx.strokeStyle = 'rgba(255,246,226,.75)'; ctx.lineWidth = 3; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.ellipse(x, base, rx - 14, ry - 12, 0, -Math.PI * 0.42, -Math.PI * 0.14); ctx.stroke();
      if (seeds) for (let k = 0; k < seeds; k++) {
        const a = -Math.PI * (0.12 + r() * 0.76), d = 0.3 + r() * 0.62;
        ctx.save(); ctx.translate(x + Math.cos(a) * rx * d, base + Math.sin(a) * ry * d); ctx.rotate(r() * 3);
        ctx.fillStyle = 'rgba(120,60,30,.25)'; ctx.beginPath(); ctx.ellipse(1, 2, 7, 3.4, 0, 0, 7); ctx.fill();
        ctx.fillStyle = '#fff1d6'; ctx.beginPath(); ctx.ellipse(0, 0, 7, 3.4, 0, 0, 7); ctx.fill(); ctx.restore();
      }
      ctx.restore();
    }
    dome(250, 300, 300, 130, '#f2c9a2'); dome(900, 310, 380, 150, '#f0c298'); dome(1480, 300, 260, 120, '#f2c9a2');
    dome(560, 340, 280, 140, '#e9aa7c', 0); dome(1240, 350, 320, 130, '#e7a577', 0);
    dome(120, 400, 300, 170, '#d88a5e', 26); dome(760, 420, 340, 160, '#d4845a', 34); dome(1420, 420, 330, 180, '#d88a5e', 30);
    // Dampf
    ctx.strokeStyle = 'rgba(255,255,255,.6)'; ctx.lineWidth = 5; ctx.lineCap = 'round';
    [[520, 190], [575, 176], [1230, 204], [1290, 190]].forEach(function (q) {
      ctx.beginPath(); ctx.moveTo(q[0], q[1]); ctx.bezierCurveTo(q[0] - 26, q[1] - 34, q[0] + 26, q[1] - 62, q[0], q[1] - 100); ctx.stroke();
    });
    A.finish(ctx, W, H, '#6a2f20', 8, 107);
  });
})();
