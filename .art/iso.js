/* Isometrische Klötzchen-Welt – Grundmotiv für die Minecraft-Familie.
   Eigene, neutrale Würfel-Geometrie (keine Spieltexturen). */
(function () {
  const A = window.A;

  // Würfel; (x,y) = oberer Eckpunkt der Deckfläche
  A.cube = function (ctx, x, y, tw, th, d, cT, cL, cR, edge) {
    ctx.lineWidth = 1; ctx.lineJoin = 'round';
    if (d > 0) {
      ctx.fillStyle = ctx.strokeStyle = cL; ctx.beginPath();
      ctx.moveTo(x - tw, y + th); ctx.lineTo(x, y + 2 * th); ctx.lineTo(x, y + 2 * th + d); ctx.lineTo(x - tw, y + th + d);
      ctx.closePath(); ctx.fill(); ctx.stroke();
      ctx.fillStyle = ctx.strokeStyle = cR; ctx.beginPath();
      ctx.moveTo(x, y + 2 * th); ctx.lineTo(x + tw, y + th); ctx.lineTo(x + tw, y + th + d); ctx.lineTo(x, y + 2 * th + d);
      ctx.closePath(); ctx.fill(); ctx.stroke();
    }
    ctx.fillStyle = ctx.strokeStyle = cT; ctx.beginPath();
    ctx.moveTo(x, y); ctx.lineTo(x + tw, y + th); ctx.lineTo(x, y + 2 * th); ctx.lineTo(x - tw, y + th);
    ctx.closePath(); ctx.fill(); ctx.stroke();
    if (edge) {
      ctx.strokeStyle = edge; ctx.beginPath();
      ctx.moveTo(x - tw + 1, y + th); ctx.lineTo(x, y + 0.5); ctx.lineTo(x + tw - 1, y + th); ctx.stroke();
    }
  };

  A.hash = function (i, j, s) {
    let h = Math.imul(i | 0, 374761393) ^ Math.imul(j | 0, 668265263) ^ Math.imul(s | 0, 2147483647);
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    return ((h ^ (h >>> 16)) >>> 0) / 4294967296;
  };

  /* o: seed, bg, tw, bh, oy, dx, depth, cell(i,j,noise) -> {h, top, kind, tree, skip, glow, deco}
        sL, sR (Seitenfaktoren), shadow (Farbe), edge (Alpha), accent {fx, fy, color, halo, win}
        fog [farbe, alpha, bis], light [farbe, alpha], vig [farbe, alpha], grain, after(ctx, info) */
  A.isoScene = function (ctx, W, H, o) {
    const tw = o.tw || 34, th = tw / 2, bh = o.bh || 17;
    const n = A.makeNoise(o.seed);
    const ox = W / 2 + (o.dx || 0), oy = o.oy === undefined ? -150 : o.oy;
    const cache = new Map();
    const cell = function (i, j) {
      const k = i * 4096 + j;
      let c = cache.get(k);
      if (!c) { c = o.cell(i, j, n); c.eh = c.h + (c.tree ? 2 : 0); cache.set(k, c); }
      return c;
    };
    const sx = function (i, j) { return ox + (i - j) * tw; };
    const sy = function (i, j, h) { return oy + (i + j) * th - h * bh; };
    if (o.bg) { ctx.fillStyle = o.bg; ctx.fillRect(0, 0, W, H); }
    if (o.before) o.before(ctx, n);

    const U = Math.ceil(W / 2 / tw) + 3, V = Math.ceil((H - oy) / th) + 26;
    // Akzent-Zelle suchen: höchste Landzelle im Fenster
    let acc = null;
    if (o.accent) {
      let best = -1e9;
      for (let v = 0; v < V; v++) for (let u = -U; u <= U; u++) {
        if ((u + v) & 1) continue;
        const i = (u + v) / 2, j = (v - u) / 2, c = cell(i, j);
        if (c.skip || c.kind === 'water' || c.tree) continue;
        const X = sx(i, j), Y = sy(i, j, c.h);
        const dx = (X - o.accent.fx * W) / (o.accent.win || 150), dy = (Y - o.accent.fy * H) / ((o.accent.win || 150) * 0.5);
        const score = c.h * 0.6 - (dx * dx + dy * dy) * 3;
        if (score > best) { best = score; acc = [i, j]; }
      }
    }
    const sL = o.sL || 0.56, sR = o.sR || 0.78, depth = (o.depth || 7) * bh;
    const glows = [];
    for (let v = -4; v < V; v++) for (let u = -U; u <= U; u++) {
      if ((u + v) & 1) continue;
      const i = (u + v) / 2, j = (v - u) / 2, c = cell(i, j);
      if (c.skip) continue;
      const X = sx(i, j), Y = sy(i, j, c.h);
      if (Y > H + 4 || Y + depth + 2 * th < -80) continue;
      let top = c.top;
      const vr = (A.hash(i, j, o.seed) - 0.5) * (o.vary === undefined ? 0.07 : o.vary);
      top = A.shade(top, 1 + vr);
      let shadowed = false;
      if (c.kind !== 'glow') {
        for (let k = 1; k <= 3; k++) { const q = cell(i, j - k); if (!q.skip && q.eh - c.h >= k) { shadowed = true; break; } }
      }
      if (shadowed) top = A.mix(top, o.shadow || '#101820', o.shadowAmt || 0.36);
      // Ferne (oben im Bild) ins Himmelslicht ziehen, Vordergrund bleibt satt
      if (o.atmo && c.kind !== 'glow') {
        const span = (o.atmo.horizon || H * 0.42) - (o.atmo.farY == null ? -80 : o.atmo.farY);
        const f = A.clamp(((o.atmo.horizon || H * 0.42) - Y) / span, 0, 1);
        top = A.mix(top, o.atmo.color, f * (o.atmo.amt == null ? 0.45 : o.atmo.amt));
      }
      if (o.rim && !shadowed && c.kind !== 'water' && c.kind !== 'glow') {
        const f = A.clamp((X - W * 0.42) / (W * 0.5), 0, 1);
        top = A.mix(top, o.rim, f * (o.rimAmt == null ? 0.16 : o.rimAmt));
      }
      const side = c.side || c.top;
      let cL = A.mix(A.shade(side, sL), o.sideTint || '#000000', o.sideTintAmt || 0);
      let cR = A.mix(A.shade(side, sR), o.sideTint || '#000000', (o.sideTintAmt || 0) * 0.5);
      if (o.rim && c.kind !== 'water' && c.kind !== 'glow') {
        const f = A.clamp((X - W * 0.38) / (W * 0.55), 0, 1);
        cR = A.mix(cR, o.rim, (shadowed ? 0.08 : 0.28) * f);
      }
      const d = c.depth !== undefined ? c.depth * bh : depth;
      A.cube(ctx, X, Y, tw, th, d, top, cL, cR, c.kind === 'water' ? null : 'rgba(255,255,255,' + (o.edge === undefined ? 0.16 : o.edge) + ')');
      if (c.deco) c.deco(ctx, X, Y, tw, th, shadowed, i, j);
      if (c.kind === 'glow') glows.push([X, Y + th, c.glow || 60, c.top]);
      if (c.tree) {
        const t = c.tree;
        A.cube(ctx, X, Y + th * 0.72 - bh * 1.1, tw * 0.2, th * 0.2, bh * 1.1 + 2, A.shade(t.trunk, 1), A.shade(t.trunk, 0.6), A.shade(t.trunk, 0.8));
        const s1 = 0.74, y1 = Y - bh * 1.9 + th * (1 - s1);
        A.cube(ctx, X, y1, tw * s1, th * s1, bh * 1.05, t.leaf, A.shade(t.leaf, sL), A.shade(t.leaf, sR), 'rgba(255,255,255,.14)');
        const s2 = 0.44, y2 = y1 - bh * 0.72 + th * (s1 - s2);
        A.cube(ctx, X, y2, tw * s2, th * s2, bh * 0.72, A.shade(t.leaf, 1.08), A.shade(t.leaf, sL), A.shade(t.leaf, sR), 'rgba(255,255,255,.18)');
      }
      if (acc && acc[0] === i && acc[1] === j) {
        const s = 0.5, ah = bh * 0.95, ay = Y - ah + th * (1 - s);
        A.cube(ctx, X, ay, tw * s, th * s, ah, o.accent.color, A.shade(o.accent.color, 0.7), A.shade(o.accent.color, 0.86), 'rgba(255,255,255,.5)');
        acc.push(X, ay + th * s);
      }
    }
    glows.forEach(function (g) { A.radial(ctx, g[0], g[1], g[2], A.rgba(g[3], 0.2), A.rgba(g[3], 0), 'screen'); });
    if (o.after) o.after(ctx, n, { sx: sx, sy: sy, cell: cell, tw: tw, th: th, bh: bh });
    if (o.fog) A.vgrad(ctx, 0, 0, W, H * o.fog[2], [[0, A.rgba(o.fog[0], o.fog[1])], [1, A.rgba(o.fog[0], 0)]]);
    if (o.light) A.radial(ctx, W * 0.86, -H * 0.1, W * 0.62, A.rgba(o.light[0], o.light[1]), A.rgba(o.light[0], 0), o.light[2] || 'soft-light');
    if (acc && acc.length > 2 && o.accent.halo && !o.sun) {
      A.radial(ctx, acc[2], acc[3], o.accent.haloR || 150, A.rgba(o.accent.halo, 0.38), A.rgba(o.accent.halo, 0), 'screen');
      A.radial(ctx, acc[2], acc[3], (o.accent.haloR || 150) * 0.35, A.rgba(o.accent.halo, 0.4), A.rgba(o.accent.halo, 0), 'screen');
    }
    if (o.mist) A.band(ctx, W, o.mist[0], o.mist[1], o.mist[2], o.mist[3]);
    if (o.floor) A.vgrad(ctx, 0, H * (o.floorY == null ? 0.62 : o.floorY), W, H, [[0, A.rgba(o.floor, 0)], [1, A.rgba(o.floor, o.floorA == null ? 0.5 : o.floorA)]]);
    if (o.sun && acc && acc.length > 2) {
      const sx0 = acc[2] + (o.sun.dx || 36), sy0 = Math.max(28, acc[3] + (o.sun.dy || -50));
      A.volumetric(ctx, sx0, sy0, o.sun.halo, { r: o.sun.r || 280, a: o.sun.va == null ? 0.34 : o.sun.va, shafts: o.sun.shafts == null ? 4 : o.sun.shafts, sy: 0.7 });
      A.punkt(ctx, sx0, sy0, o.sun.s || 34, o.sun.color, o.sun.halo, o.sun.hr || 190);
    }
    if (o.vig) A.vignette(ctx, W, H, o.vig[0], o.vig[1]);
    A.grain(ctx, W, H, o.grain === undefined ? 9 : o.grain, o.seed);
  };

})();
