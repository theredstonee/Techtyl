/* Hintergründe (2560x1440). Regel: Bildmitte ruhig, Zeichnung an Rändern und Ecken,
   geringer Kontrast – Inhalte liegen als Flächen darüber, dazu 35–55 % Abdunklung. */
(function () {
  const A = window.A, GW = 2560, GH = 1440;

  // bilineare Abfrage eines A.grid-Rasters
  function sample(grid, x, y) {
    const G = grid[0], nx = grid[1], c = grid[3];
    const fx = x / c, fy = y / c, x0 = fx | 0, y0 = fy | 0, tx = fx - x0, ty = fy - y0;
    const a = G[y0 * nx + x0], b = G[y0 * nx + x0 + 1], d = G[(y0 + 1) * nx + x0], e = G[(y0 + 1) * nx + x0 + 1];
    return a + (b - a) * tx + (d - a + (e - b - d + a) * tx) * ty;
  }
  A.sample = sample;

  /* Seekarte: Tiefenlinien, Küste, Kompassring. pal = [tief, flach, land, linie, akzent] */
  function chart(ctx, W, H, o) {
    const n = A.makeNoise(o.seed), r = A.rng(o.seed);
    const grid = A.grid(W, H, 8, function (x, y) {
      const dl = Math.hypot(x, (H - y) * 1.25), dr = Math.hypot((W - x) * 0.8, y * 1.5);
      return n.fbm(x / 1150 + 2, y / 1150 + 7, 4) * 0.62 + 0.7 * Math.exp(-dl / 560) + 0.2 * Math.exp(-dr / 520)
        - 0.2 * Math.exp(-Math.hypot(x - W * 0.5, y - H * 0.5) / 700);
    });
    const coast = o.coast || 0.3, lo = -0.42, steps = 18;
    const im = ctx.createImageData(W, H), d = im.data;
    const cDeep = A.rgb(o.pal[0]), cShal = A.rgb(o.pal[1]), cLand = A.rgb(o.pal[2]);
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
      const f = sample(grid, x, y), p = (y * W + x) * 4;
      let c;
      if (f > coast) c = cLand;
      else { const t = A.clamp(Math.floor((f - lo) / (coast - lo) * steps) / steps, 0, 1); c = [cDeep[0] + (cShal[0] - cDeep[0]) * t, cDeep[1] + (cShal[1] - cDeep[1]) * t, cDeep[2] + (cShal[2] - cDeep[2]) * t]; }
      d[p] = c[0]; d[p + 1] = c[1]; d[p + 2] = c[2]; d[p + 3] = 255;
    }
    ctx.putImageData(im, 0, 0);
    // Linien auf eigener Ebene, Mitte ausgedünnt
    const L = A.layer(W, H);
    L.lineCap = 'round';
    for (let k = 0; k <= steps; k++) {
      const lv = lo + (coast - lo) * k / steps, idx = k % 4 === 2;
      L.strokeStyle = A.rgba(o.pal[3], idx ? 0.5 : 0.26); L.lineWidth = idx ? 1.6 : 1.1;
      A.isoline(L, grid, lv);
    }
    // Landschraffur
    const Ld = A.layer(W, H);
    Ld.strokeStyle = A.rgba(o.pal[3], 0.07); Ld.lineWidth = 1; Ld.beginPath();
    for (let k = -H; k < W; k += 11) { Ld.moveTo(k, H); Ld.lineTo(k + H, 0); }
    Ld.stroke();
    const mk = Ld.getImageData(0, 0, W, H), md = mk.data;
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) if (sample(grid, x, y) <= coast + 0.012) md[(y * W + x) * 4 + 3] = 0;
    Ld.putImageData(mk, 0, 0);
    L.drawImage(Ld.canvas, 0, 0);
    for (let k = 1; k <= 3; k++) { L.strokeStyle = A.rgba(o.pal[3], 0.1); L.lineWidth = 1; A.isoline(L, grid, coast + k * 0.07); }
    L.strokeStyle = A.rgba(o.pal[4], 0.55); L.lineWidth = 1.8; A.isoline(L, grid, coast);
    // Lotungs-Kreuze im Wasser
    L.strokeStyle = A.rgba(o.pal[3], 0.3); L.lineWidth = 1; L.beginPath();
    for (let y = 100; y < H; y += 170) for (let x = 90 + ((y / 170) & 1) * 85; x < W; x += 170) {
      if (sample(grid, x, y) > coast - 0.03) continue;
      L.moveTo(x - 5, y); L.lineTo(x + 5, y); L.moveTo(x, y - 5); L.lineTo(x, y + 5);
    }
    L.stroke();
    // Kompassring und Peilstrahlen um den Punkt
    const px = W * 0.79, py = H * 0.2;
    L.strokeStyle = A.rgba(o.pal[4], 0.13); L.lineWidth = 1; L.beginPath();
    for (let k = 0; k < 32; k++) { const a = k * Math.PI / 16 + 0.07; L.moveTo(px + Math.cos(a) * 236, py + Math.sin(a) * 236); L.lineTo(px + Math.cos(a) * 3400, py + Math.sin(a) * 3400); }
    L.stroke();
    [[150, 0.3, 1.2], [226, 0.42, 1.4], [236, 0.22, 1]].forEach(function (c) {
      L.strokeStyle = A.rgba(o.pal[4], c[1]); L.lineWidth = c[2]; L.beginPath(); L.arc(px, py, c[0], 0, 7); L.stroke();
    });
    L.strokeStyle = A.rgba(o.pal[4], 0.5); L.lineWidth = 1.2; L.beginPath();
    for (let k = 0; k < 120; k++) {
      const a = k * Math.PI / 60, len = k % 30 === 0 ? 30 : k % 5 === 0 ? 16 : 8;
      L.moveTo(px + Math.cos(a) * 226, py + Math.sin(a) * 226); L.lineTo(px + Math.cos(a) * (226 - len), py + Math.sin(a) * (226 - len));
    }
    L.stroke();
    // Mitte beruhigen
    L.globalCompositeOperation = 'destination-out';
    A.radial(L, W * 0.5, H * 0.54, H * 0.62, 'rgba(0,0,0,.62)', 'rgba(0,0,0,0)', null, 1.5, 1);
    ctx.drawImage(L.canvas, 0, 0);
    // Kartenrand-Skala
    ctx.strokeStyle = A.rgba(o.pal[3], 0.22); ctx.lineWidth = 1; ctx.beginPath();
    for (let x = 24; x < W - 20; x += 16) { const l = (x - 24) % 160 === 0 ? 16 : (x - 24) % 80 === 0 ? 10 : 5; ctx.moveTo(x + 0.5, 22); ctx.lineTo(x + 0.5, 22 + l); ctx.moveTo(x + 0.5, H - 22); ctx.lineTo(x + 0.5, H - 22 - l); }
    for (let y = 22; y < H - 20; y += 16) { const l = (y - 22) % 160 === 0 ? 16 : (y - 22) % 80 === 0 ? 10 : 5; ctx.moveTo(24, y + 0.5); ctx.lineTo(24 + l, y + 0.5); ctx.moveTo(W - 24, y + 0.5); ctx.lineTo(W - 24 - l, y + 0.5); }
    ctx.stroke();
    A.radial(ctx, px, py, 760, A.rgba(o.pal[4], 0.1), A.rgba(o.pal[4], 0), 'screen');
    A.punkt(ctx, px, py, 30, o.pal[4], o.pal[4], 120);
    A.vignette(ctx, W, H, o.pal[0], 0.5);
    A.grain(ctx, W, H, o.grain || 5, o.seed);
  }
  A.chart = chart;

  A.scene('bg-werft', GW, GH, function (ctx, W, H) {
    chart(ctx, W, H, { seed: 77, pal: ['#0a151b', '#16303a', '#1f3a45', '#e9f0ee', '#f0a33b'] });
  });
})();
