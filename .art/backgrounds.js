/* Hintergründe, Teil 1: Seekarte. 2560×1440.
   Mitte (mittleres 40 %) bleibt ruhiges Wasser. Drama an Kanten, Ecken, Horizont.
   A.sample bleibt exportiert – das Standard-Banner liest das Raster darüber. */
(function () {
  const A = window.A, GW = 2560, GH = 1440;

  function sample(grid, x, y) {
    const G = grid[0], nx = grid[1], c = grid[3];
    const fx = x / c, fy = y / c, x0 = fx | 0, y0 = fy | 0, tx = fx - x0, ty = fy - y0;
    const a = G[y0 * nx + x0], b = G[y0 * nx + x0 + 1], d = G[(y0 + 1) * nx + x0], e = G[(y0 + 1) * nx + x0 + 1];
    return a + (b - a) * tx + (d - a + (e - b - d + a) * tx) * ty;
  }
  A.sample = sample;

  function blob(nx, ny, cx, cy, ax, ay) {
    const dx = (nx - cx) / ax, dy = (ny - cy) / ay;
    return Math.exp(-(dx * dx + dy * dy));
  }

  /* Relief einer Küste: 0 tief, höher = Land. Die Bildmitte ist das tiefe Becken. */
  function coastField(n, W, H) {
    return function (x, y) {
      const nx = x / W, ny = y / H;
      const shelf = n.fbm(x / 820 + 1.4, y / 740 + 2.2, 4) * 0.5 + 0.5;
      const fine = n.fbm(x / 260 + 8, y / 260, 2) * 0.5 + 0.5;
      let land = 0;
      land += blob(nx, ny, -0.04, 1.02, 0.38, 0.30) * 1.25;
      land += blob(nx, ny, 0.16, 0.78, 0.13, 0.08) * 0.55;
      land += blob(nx, ny, 1.06, 0.92, 0.30, 0.24) * 1.05;
      land += blob(nx, ny, 1.04, 0.36, 0.20, 0.16) * 1.15;
      land += blob(nx, ny, 0.90, 0.30, 0.07, 0.05) * 0.45;
      land += blob(nx, ny, 0.14, 0.12, 0.20, 0.13) * 0.8;
      land += blob(nx, ny, 0.30, 0.20, 0.07, 0.055) * 0.62;
      land += blob(nx, ny, -0.02, 0.02, 0.16, 0.10) * 0.5;
      land += blob(nx, ny, 0.72, 0.96, 0.10, 0.06) * 0.35;
      const basin = Math.exp(-Math.pow((nx - 0.46) / 0.33, 2) - Math.pow((ny - 0.50) / 0.28, 2));
      const h = land + shelf * 0.20 * Math.min(1, land * 2.2 + 0.12) + fine * 0.04 * land;
      return h * (1 - basin * 0.78) + (1 - basin) * 0.05;
    };
  }

  function compass(ctx, x, y, R, col) {
    ctx.save();
    ctx.translate(x, y);
    ctx.strokeStyle = col;
    ctx.lineJoin = 'round';
    [1, 0.74, 0.16].forEach(function (k, i) {
      ctx.lineWidth = i === 0 ? 1.6 : 1.1;
      ctx.beginPath(); ctx.arc(0, 0, R * k, 0, 7); ctx.stroke();
    });
    ctx.lineWidth = 1.15;
    ctx.beginPath();
    for (let k = 0; k < 72; k++) {
      const a = k * Math.PI / 36;
      const len = k % 18 === 0 ? 22 : k % 3 === 0 ? 12 : 6;
      ctx.moveTo(Math.cos(a) * R, Math.sin(a) * R);
      ctx.lineTo(Math.cos(a) * (R - len), Math.sin(a) * (R - len));
    }
    ctx.stroke();
    ctx.lineWidth = 1.7;
    ctx.beginPath();
    for (let k = 0; k < 4; k++) {
      const a = -Math.PI / 2 + k * Math.PI / 2;
      const b = a + Math.PI / 4;
      const px = Math.cos(a) * R * 0.66, py = Math.sin(a) * R * 0.66;
      const qx = Math.cos(b) * R * 0.15, qy = Math.sin(b) * R * 0.15;
      if (k === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
      ctx.lineTo(qx, qy);
    }
    ctx.closePath(); ctx.stroke();
    ctx.lineWidth = 1.15;
    ctx.beginPath();
    for (let k = 0; k < 4; k++) {
      const a = -Math.PI / 4 + k * Math.PI / 2;
      const b = a + Math.PI / 4;
      const px = Math.cos(a) * R * 0.40, py = Math.sin(a) * R * 0.40;
      const qx = Math.cos(b) * R * 0.12, qy = Math.sin(b) * R * 0.12;
      if (k === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
      ctx.lineTo(qx, qy);
    }
    ctx.closePath(); ctx.stroke();
    ctx.restore();
  }

  function poly(ctx, pts, col) {
    ctx.fillStyle = col;
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
    ctx.closePath();
    ctx.fill();
  }

  A.scene('bg-werft', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(77), r = A.rng(77);
    const coast = 0.46;
    const grid = A.grid(W, H, 5, coastField(n, W, H));
    const sea = ['#07141c', '#0c222b', '#12363e', '#18484e', '#1c5854'].map(A.rgb);
    const land = ['#102830', '#17343c', '#21444c'].map(A.rgb);
    const lx = 2148, ly = 392;
    const im = ctx.createImageData(W, H), d = im.data;
    for (let y = 0; y < H; y++) {
      for (let x = 0; x < W; x++) {
        let h = sample(grid, x, y);
        if (h < 0) h = 0;
        const hr = sample(grid, Math.min(W - 2, x + 7), y);
        const hd = sample(grid, x, Math.min(H - 2, y + 6));
        let c;
        if (h >= coast) {
          const t = A.clamp((h - coast) / 0.55, 0, 0.999);
          c = land[(t * land.length) | 0].slice();
          if (hr < h - 0.02) { c[0] += 16; c[1] += 14; c[2] += 8; }
          if (hr > h + 0.03) { c[0] *= 0.82; c[1] *= 0.86; c[2] *= 0.9; }
        } else {
          const t = A.clamp(h / coast, 0, 0.999);
          const i = (t * sea.length) | 0;
          c = sea[i].slice();
          const step = coast / sea.length;
          const edge = (h % step) / step;
          if (edge > 0.82 && hr > h) { c[0] += 14; c[1] += 18; c[2] += 10; }
          else if (edge < 0.12 && hd < h) { c[0] *= 0.78; c[1] *= 0.84; c[2] *= 0.88; }
        }
        const p = (y * W + x) * 4;
        d[p] = c[0]; d[p + 1] = c[1]; d[p + 2] = c[2]; d[p + 3] = 255;
      }
    }
    ctx.putImageData(im, 0, 0);

    const L = A.layer(W, H);
    L.lineCap = 'round'; L.lineJoin = 'round';
    for (let k = 1; k <= 5; k++) {
      L.strokeStyle = 'rgba(214,226,220,' + (k % 2 ? 0.16 : 0.28) + ')';
      L.lineWidth = k % 2 ? 1 : 1.35;
      A.isoline(L, grid, coast * k / 6);
    }
    L.strokeStyle = 'rgba(232,196,122,.72)'; L.lineWidth = 2.1;
    A.isoline(L, grid, coast);
    L.strokeStyle = 'rgba(214,226,220,.2)'; L.lineWidth = 1;
    A.isoline(L, grid, coast + 0.08);
    A.isoline(L, grid, coast + 0.16);
    // Mitte der Linien ausdünnen, damit die Kartenflächen ruhig bleiben
    L.globalCompositeOperation = 'destination-out';
    A.radial(L, W * 0.46, H * 0.50, H * 0.34, 'rgba(0,0,0,.72)', 'rgba(0,0,0,0)', null, 1.35, 1);
    ctx.drawImage(L.canvas, 0, 0);

    // Lotungen: Kreuze und Ringe, keine Ziffern. Nicht im mittleren Becken.
    ctx.strokeStyle = 'rgba(214,226,220,.32)'; ctx.lineWidth = 1.15;
    ctx.beginPath();
    for (let k = 0; k < 48; k++) {
      const x = 80 + r() * (W - 160), y = 70 + r() * (H - 140);
      const dx = (x - W * 0.46) / (W * 0.22), dy = (y - H * 0.50) / (H * 0.20);
      if (dx * dx + dy * dy < 1) continue;
      if (sample(grid, x, y) > coast - 0.02) continue;
      if (r() < 0.45) {
        ctx.moveTo(x - 5, y); ctx.lineTo(x + 5, y); ctx.moveTo(x, y - 5); ctx.lineTo(x, y + 5);
      } else {
        ctx.moveTo(x + 4, y); ctx.arc(x, y, 4, 0, 7);
      }
    }
    ctx.stroke();

    // Vordergrund: Kaps, die das Kartenrelief überdecken
    poly(ctx, [[0, 860], [180, 980], [340, 1040], [520, 1180], [430, 1320], [180, 1440], [0, 1440]], '#08141a');
    poly(ctx, [[0, 1440], [220, 1280], [80, 1160], [0, 1200]], '#0c1c22');
    poly(ctx, [[2560, 980], [2280, 1120], [2140, 1280], [2360, 1440], [2560, 1440]], '#08141a');
    poly(ctx, [[2560, 250], [2360, 300], [2220, 390], [2140, 520], [2280, 560], [2480, 470], [2560, 500]], '#0a1820');
    // Schichtung der Kaps als zweite, knapp hellere Terrasse
    poly(ctx, [[0, 1040], [240, 1140], [160, 1280], [0, 1360]], '#102830');
    ctx.strokeStyle = 'rgba(232,196,122,.45)'; ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.moveTo(0, 868); ctx.quadraticCurveTo(200, 1000, 500, 1190);
    ctx.moveTo(2560, 990); ctx.quadraticCurveTo(2300, 1140, 2160, 1290);
    ctx.moveTo(2220, 400); ctx.quadraticCurveTo(2320, 460, 2560, 490);
    ctx.stroke();

    // Sockel des Leuchtfeuers, damit der Punkt auf dem Kap sitzt
    ctx.fillStyle = '#102830';
    ctx.beginPath();
    ctx.moveTo(lx - 16, ly + 16); ctx.lineTo(lx - 26, ly + 78); ctx.lineTo(lx + 28, ly + 86); ctx.lineTo(lx + 18, ly + 16);
    ctx.closePath(); ctx.fill();
    ctx.strokeStyle = 'rgba(240,210,160,.55)'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(lx + 18, ly + 16); ctx.lineTo(lx + 28, ly + 84); ctx.stroke();

    // Kartenrand
    ctx.strokeStyle = 'rgba(214,226,220,.2)'; ctx.lineWidth = 1; ctx.beginPath();
    for (let x = 18; x < W - 16; x += 14) {
      const len = (x - 18) % 140 === 0 ? 16 : (x - 18) % 70 === 0 ? 10 : 5;
      ctx.moveTo(x + 0.5, 16); ctx.lineTo(x + 0.5, 16 + len);
      ctx.moveTo(x + 0.5, H - 16); ctx.lineTo(x + 0.5, H - 16 - len);
    }
    for (let y = 16; y < H - 16; y += 14) {
      const len = (y - 16) % 140 === 0 ? 16 : (y - 16) % 70 === 0 ? 10 : 5;
      ctx.moveTo(16, y + 0.5); ctx.lineTo(16 + len, y + 0.5);
      ctx.moveTo(W - 16, y + 0.5); ctx.lineTo(W - 16 - len, y + 0.5);
    }
    ctx.stroke();

    compass(ctx, 300, 1188, 148, 'rgba(232,196,122,.7)');

    // Strahl über das Wasser, eine Lichtquelle
    A.radial(ctx, lx - 520, ly + 180, 980, 'rgba(232,176,90,.2)', 'rgba(232,176,90,0)', 'screen', 1.7, 0.42);
    A.radial(ctx, W * 0.4, H * 0.58, 760, 'rgba(232,176,90,.1)', 'rgba(232,176,90,0)', 'screen', 1.6, 0.6);
    A.punkt(ctx, lx, ly, 34, '#f0c27a', '#e8b15a', 280);
    A.stipple(ctx, 0, 0, W, H, 70000, 'rgba(240,210,160,.14)', function (x, y) {
      const dx = (x - lx) / W, dy = (y - ly) / H;
      const beam = Math.exp(-(dy * dy) / 0.02) * Math.exp(-Math.abs(dx) * 1.4);
      const mid = Math.hypot((x - W * 0.46) / (W * 0.2), (y - H * 0.5) / (H * 0.18));
      if (sample(grid, x, y) > coast) return 0;
      return beam * 0.8 * A.clamp(mid - 0.2, 0, 1);
    }, r, 1.5);
    A.vignette(ctx, W, H, '#050d12', 0.42);
    A.dither(ctx, W, H, { amp: 12, grain: 2.5, seed: 77 });
  });
})();
