/* Muster-Banner: Laufzeiten (Node.js, Nodemon, Python), Voice-Server, Taktikplan, Gewebe, Standard. */
(function () {
  const A = window.A, BW = 1600, BH = 520;

  /* Leiterbahnen um einen zentralen Knoten. o: seed, bg[2], line, hi, chip, punkt, rings */
  function circuit(ctx, W, H, o) {
    const r = A.rng(o.seed), g = 24;
    A.vgrad(ctx, 0, 0, W, H, [[0, o.bg[0]], [1, o.bg[1]]]);
    const hx = Math.round(W * 0.72 / g) * g, hy = Math.round(H * 0.31 / g) * g;
    A.radial(ctx, hx, hy, 620, A.rgba(o.hi, 0.16), A.rgba(o.hi, 0), 'screen');
    ctx.fillStyle = A.rgba(o.line, 0.16);
    for (let y = g; y < H; y += g) for (let x = g; x < W; x += g) ctx.fillRect(x - 1, y - 1, 2, 2);
    if (o.rings) {
      ctx.strokeStyle = A.rgba(o.hi, 0.3); ctx.lineWidth = 1.5;
      [96, 150, 216, 296, 392].forEach(function (rad, k) {
        ctx.globalAlpha = 1 - k * 0.17; ctx.setLineDash(k % 2 ? [3, 9] : [22, 10]);
        ctx.beginPath(); ctx.arc(hx, hy, rad, 0, 7); ctx.stroke();
      });
      ctx.setLineDash([]); ctx.globalAlpha = 1;
    }
    const nx = Math.ceil(W / g) + 2, ny = Math.ceil(H / g) + 2, occ = new Uint8Array(nx * ny);
    const at = function (x, y) { return x < 0 || y < 0 || x >= nx || y >= ny ? 1 : occ[y * nx + x]; };
    const D = [[1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1]];
    const cx = hx / g, cy = hy / g;
    for (let y = -2; y <= 2; y++) for (let x = -2; x <= 2; x++) occ[(cy + y) * nx + cx + x] = 1;
    function walk(x, y, d, len, bright) {
      const pts = [[x, y]]; occ[y * nx + x] = 1;
      for (let s = 0; s < len; s++) {
        const opts = r() < 0.78 ? [d, (d + 1) & 7, (d + 7) & 7] : r() < 0.5 ? [(d + 1) & 7, d, (d + 7) & 7] : [(d + 7) & 7, d, (d + 1) & 7];
        let ok = false;
        for (let q = 0; q < 3; q++) {
          const dd = opts[q], X = x + D[dd][0], Y = y + D[dd][1];
          if (!at(X, Y) && !(dd & 1 && at(X, y) && at(x, Y))) { x = X; y = Y; d = dd; ok = true; break; }
        }
        if (!ok) break;
        occ[y * nx + x] = 1; pts.push([x, y]);
      }
      if (pts.length < 3) return;
      const col = bright ? o.hi : o.line;
      ctx.strokeStyle = A.rgba(col, bright ? 0.95 : 0.35 + r() * 0.35); ctx.lineWidth = bright ? 4.2 : 2.6; ctx.lineJoin = 'round'; ctx.lineCap = 'round';
      ctx.beginPath(); pts.forEach(function (p, k) { if (k) ctx.lineTo(p[0] * g, p[1] * g); else ctx.moveTo(p[0] * g, p[1] * g); }); ctx.stroke();
      const e = pts[pts.length - 1];
      if (bright && r() < 0.6) { ctx.fillStyle = col; A.rrect(ctx, e[0] * g - 6, e[1] * g - 6, 12, 12, 3); ctx.fill(); }
      else { ctx.fillStyle = o.bg[1]; ctx.strokeStyle = A.rgba(col, 0.9); ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(e[0] * g, e[1] * g, 4.5, 0, 7); ctx.fill(); ctx.stroke(); }
    }
    // Anschlüsse am Knoten
    for (let k = -2; k <= 2; k++) {
      walk(cx + 3, cy + k, k < 0 ? 7 : k > 0 ? 1 : 0, 10 + (r() * 40 | 0), r() < 0.5);
      walk(cx - 3, cy + k, k < 0 ? 5 : k > 0 ? 3 : 4, 10 + (r() * 46 | 0), r() < 0.5);
      walk(cx + k, cy + 3, 2, 6 + (r() * 14 | 0), r() < 0.35);
      walk(cx + k, cy - 3, 6, 2 + (r() * 4 | 0), r() < 0.35);
    }
    for (let k = 0; k < 70; k++) walk((r() * nx) | 0, (r() * ny) | 0, (r() * 8) | 0, 6 + (r() * 24 | 0), r() < 0.14);
    function bus(pts, w, col, al) {
      ctx.strokeStyle = A.rgba(col, al); ctx.lineWidth = w; ctx.lineJoin = 'round'; ctx.lineCap = 'round';
      ctx.beginPath(); pts.forEach(function (p, i) { if (i) ctx.lineTo(p[0], p[1]); else ctx.moveTo(p[0], p[1]); }); ctx.stroke();
    }
    // Ferne, dicke Bahn – liest sich auch in der kleinen Karte
    bus([[hx - 90, hy - 16], [hx - 300, hy - 16], [hx - 300, hy - 110], [60, hy - 110]], 13, o.hi, 0.92);
    bus([[hx + 90, hy + 8], [hx + 240, hy + 8], [hx + 240, hy + 86], [W - 30, hy + 86]], 13, o.hi, 0.8);
    bus([[hx - 8, hy + 90], [hx - 8, hy + 150], [hx - 200, hy + 150]], 15, o.line, 0.75);
    // Vordergrund: eine nahe, dunkle Bahn mit Lichtkante
    bus([[-30, 268], [160, 236], [160, 150]], 34, A.shade(o.bg[0], 0.55), 1);
    bus([[-30, 250], [154, 220], [154, 142]], 2.4, o.hi, 0.8);
    A.band(ctx, W, hy, 90, o.hi, 0.06);
    // Knoten
    ctx.fillStyle = o.chip; A.rrect(ctx, hx - 74, hy - 74, 148, 148, 18); ctx.fill();
    ctx.strokeStyle = A.rgba(o.hi, 0.75); ctx.lineWidth = 2.5; A.rrect(ctx, hx - 74, hy - 74, 148, 148, 18); ctx.stroke();
    ctx.strokeStyle = A.rgba(o.hi, 0.28); A.rrect(ctx, hx - 54, hy - 54, 108, 108, 12); ctx.stroke();
    A.volumetric(ctx, hx, hy, o.hi, { r: 260, a: 0.16, shafts: 3 });
    A.punkt(ctx, hx, hy, 48, o.punkt, o.hi, 210);
    A.finish(ctx, W, H, '#020403', 8, o.seed);
  }

  A.scene('bn-nodejs', BW, BH, function (ctx, W, H) {
    circuit(ctx, W, H, { seed: 118, bg: ['#0d1f16', '#0a1610'], line: '#3f9a57', hi: '#9be88a', chip: '#0f2a1b', punkt: '#c9f7b4' });
  });
  A.scene('bn-nodemon', BW, BH, function (ctx, W, H) {
    circuit(ctx, W, H, { seed: 129, bg: ['#1b1d1a', '#121411'], line: '#7c8a44', hi: '#e2e56a', chip: '#24261c', punkt: '#f6f7b0', rings: true });
  });

  // Python: drei Ebenen von Bändern. Oben Dunst, in der Mitte das Licht, unten ruhige Silhouette.
  A.scene('bn-python', BW, BH, function (ctx, W, H) {
    const r = A.rng(131);
    function ribbon(y0, amp, wl, ph, bw, col, shadeA, hiA) {
      const path = function (dy) { ctx.beginPath(); for (let x = -40; x <= W + 40; x += 6) ctx.lineTo(x, y0 + dy + Math.sin(x / wl * 6.283 + ph) * amp); };
      ctx.lineCap = 'round'; ctx.lineJoin = 'round';
      ctx.strokeStyle = 'rgba(6,10,22,' + shadeA + ')'; ctx.lineWidth = bw + 8; path(8); ctx.stroke();
      ctx.strokeStyle = col; ctx.lineWidth = bw; path(0); ctx.stroke();
      if (hiA) { ctx.strokeStyle = 'rgba(255,244,214,' + hiA + ')'; ctx.lineWidth = Math.max(2, bw * 0.08); path(-bw * 0.32); ctx.stroke(); }
    }
    A.vgrad(ctx, 0, 0, W, H, [[0, '#10182c'], [0.45, '#172744'], [1, '#0c1424']]);
    ribbon(78, 22, 560, 0.6, 16, '#243656', 0.2, 0);
    ribbon(118, 26, 640, 2.4, 14, '#1c3050', 0.15, 0);
    A.band(ctx, W, 150, 70, '#8aa4c8', 0.1);
    ribbon(214, 46, 460, 1.15, 58, '#e2b43e', 0.45, 0.7);
    ribbon(268, 40, 500, 2.7, 52, '#3d74b8', 0.4, 0.35);
    A.stipple(ctx, 0, 160, W, 220, 18000, 'rgba(8,14,28,.45)', function (x, y) { return A.clamp((y - 180) / 200, 0, 1) * 0.4; }, r, 1.4);
    A.volumetric(ctx, 1188, 148, '#ffe08a', { r: 300, a: 0.28, shafts: 3, a0: Math.PI * 0.4, a1: Math.PI * 0.85 });
    A.punkt(ctx, 1188, 148, 40, '#fff3c4', '#ffe08a', 180);
    ribbon(400, 32, 520, 0.4, 64, '#15243c', 0.25, 0);
    ribbon(468, 24, 440, 2.1, 70, '#10192e', 0.2, 0);
    A.finish(ctx, W, H, '#050914', 9, 131);
  });

  // Mumble: sich überlagernde Schallkreise
  A.scene('bn-mumble', BW, BH, function (ctx, W, H) {
    A.vgrad(ctx, 0, 0, W, H, [[0, '#1d2835'], [1, '#111922']]);
    ctx.globalCompositeOperation = 'screen';
    [[1150, 180, '#f4c58e', 0.75, 20], [430, 230, '#86bfe0', 0.5, 26], [820, -60, '#b9a8ea', 0.4, 30]].forEach(function (c) {
      A.radial(ctx, c[0], c[1], 520, A.rgba(c[2], 0.16), A.rgba(c[2], 0), 'screen');
      for (let rad = 34, k = 0; rad < 1300; rad += c[4], k++) {
        ctx.strokeStyle = A.rgba(c[2], c[3] * Math.exp(-rad / 520));
        ctx.lineWidth = 1 + 2.6 * Math.pow(Math.sin(k * 0.55) * 0.5 + 0.5, 2);
        ctx.beginPath(); ctx.arc(c[0], c[1], rad, 0, 7); ctx.stroke();
      }
    });
    ctx.globalCompositeOperation = 'source-over';
    A.punkt(ctx, 1150, 180, 42, '#fff0d4', '#ffd49a', 180);
    A.finish(ctx, W, H, '#05080c', 8, 142);
  });

  // TeamSpeak: wenige massive Pegel als Skyline. Muss bei 96 px Höhe noch als Rhythmus lesbar sein.
  A.scene('bn-teamspeak', BW, BH, function (ctx, W, H) {
    const ax = 300;
    const bars = [[250, 0.32], [360, 0.48], [470, 0.26], [600, 0.58], [730, 0.4], [860, 0.7], [990, 0.84], [1120, 1], [1250, 0.55], [1380, 0.36]];
    A.vgrad(ctx, 0, 0, W, H, [[0, '#070d1c'], [0.42, '#122848'], [0.55, '#17325a'], [1, '#060b16']]);
    // Ferne Bögen, dick und kontrastarm
    ctx.strokeStyle = 'rgba(150,190,240,.16)'; ctx.lineWidth = 10; ctx.lineCap = 'round';
    [220, 340, 480].forEach(function (rad) { ctx.beginPath(); ctx.arc(1160, 150, rad, Math.PI * 1.05, Math.PI * 1.85); ctx.stroke(); });
    A.band(ctx, W, 168, 90, '#8eb4e8', 0.1);
    const bw = 46;
    bars.forEach(function (b) {
      const h = 28 + 112 * b[1], x = b[0], top = ax - h;
      const g = ctx.createLinearGradient(x, 0, x + bw, 0);
      g.addColorStop(0, '#1d4e92'); g.addColorStop(0.72, '#5b96e0'); g.addColorStop(1, b[1] > 0.9 ? '#f4fbff' : '#d5ebff');
      ctx.fillStyle = g; A.rrect(ctx, x, top, bw, h, 8); ctx.fill();
      // Spiegelung als eine weiche Fläche, nicht als Kamm
      const g2 = ctx.createLinearGradient(0, ax + 8, 0, ax + 8 + h * 0.55);
      g2.addColorStop(0, 'rgba(120,170,240,.38)'); g2.addColorStop(1, 'rgba(120,170,240,0)');
      ctx.fillStyle = g2; A.rrect(ctx, x, ax + 8, bw, h * 0.5, 8); ctx.fill();
    });
    ctx.fillStyle = 'rgba(214,232,255,.55)'; ctx.fillRect(0, ax, W, 2);
    A.volumetric(ctx, 1143, 114, '#b9d8ff', { r: 300, a: 0.28, shafts: 3, a0: Math.PI * 0.45, a1: Math.PI * 0.85 });
    A.punkt(ctx, 1143, 114, 40, '#f7fbff', '#d5e8ff', 180);
    // Vordergrund: zwei dunkle Hörner, Spitzen noch im Kartenausschnitt
    ctx.fillStyle = '#050914';
    ctx.beginPath(); ctx.moveTo(-30, 520); ctx.lineTo(-30, 400); ctx.quadraticCurveTo(180, 300, 460, 520); ctx.fill();
    ctx.beginPath(); ctx.moveTo(1630, 520); ctx.lineTo(1630, 380); ctx.quadraticCurveTo(1400, 310, 1120, 520); ctx.fill();
    A.finish(ctx, W, H, '#03060e', 8, 153);
  });

  // Counter-Strike: Taktikplan von oben, zwei Zielzonen, Laufwege
  A.scene('bn-counter-strike', BW, BH, function (ctx, W, H) {
    const r = A.rng(164), u = 40;
    A.vgrad(ctx, 0, 0, W, H, [[0, '#121d30'], [1, '#0b1320']]);
    ctx.strokeStyle = 'rgba(140,170,220,.07)'; ctx.lineWidth = 1; ctx.beginPath();
    for (let x = 0; x < W; x += u) { ctx.moveTo(x + 0.5, 0); ctx.lineTo(x + 0.5, H); }
    for (let y = 20; y < H; y += u) { ctx.moveTo(0, y + 0.5); ctx.lineTo(W, y + 0.5); }
    ctx.stroke();
    for (let k = 0; k < 34; k++) {
      const w = (2 + (r() * 5 | 0)) * u, h = (1 + (r() * 3 | 0)) * u, x = ((r() * 40) | 0) * u - u, y = ((r() * 9) | 0) * u - 20;
      ctx.fillStyle = k % 5 === 0 ? '#1d2d47' : '#16233a'; ctx.fillRect(x, y, w, h);
      if (k % 4 === 1) { ctx.save(); ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip(); ctx.strokeStyle = 'rgba(120,150,200,.16)'; ctx.lineWidth = 1; ctx.beginPath(); for (let q = -h; q < w; q += 9) { ctx.moveTo(x + q, y + h); ctx.lineTo(x + q + h, y); } ctx.stroke(); ctx.restore(); }
      ctx.strokeStyle = '#6c84ad'; ctx.lineWidth = 2.5; ctx.strokeRect(x, y, w, h);
      if (r() < 0.5) { const bx = x + u * 0.5 + ((r() * (w / u - 1)) | 0) * u, by = y + h - 28; ctx.lineWidth = 1.5; ctx.strokeStyle = '#8fa6cc'; ctx.strokeRect(bx, by, 20, 20); ctx.beginPath(); ctx.moveTo(bx, by); ctx.lineTo(bx + 20, by + 20); ctx.moveTo(bx + 20, by); ctx.lineTo(bx, by + 20); ctx.stroke(); }
    }
    function route(pts, col) {
      ctx.strokeStyle = col; ctx.lineWidth = 6; ctx.lineJoin = 'round'; ctx.lineCap = 'round'; ctx.setLineDash([16, 12]);
      ctx.beginPath(); pts.forEach(function (p, k) { if (k) ctx.lineTo(p[0], p[1]); else ctx.moveTo(p[0], p[1]); }); ctx.stroke(); ctx.setLineDash([]);
      const a = pts[pts.length - 2], b = pts[pts.length - 1], an = Math.atan2(b[1] - a[1], b[0] - a[0]);
      ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(b[0] + Math.cos(an) * 16, b[1] + Math.sin(an) * 16); ctx.lineTo(b[0] + Math.cos(an + 2.4) * 13, b[1] + Math.sin(an + 2.4) * 13); ctx.lineTo(b[0] + Math.cos(an - 2.4) * 13, b[1] + Math.sin(an - 2.4) * 13); ctx.fill();
      ctx.beginPath(); ctx.arc(pts[0][0], pts[0][1], 8, 0, 7); ctx.fill();
    }
    route([[60, 300], [60, 180], [340, 180], [340, 100], [700, 100], [700, 160], [1060, 160]], '#ff9f31');
    route([[60, 300], [260, 300], [260, 380], [620, 380], [620, 260], [460, 260]], '#ff9f31');
    route([[1560, 60], [1400, 60], [1400, 220], [1250, 220]], '#7fd0ff');
    route([[1560, 60], [1560, 340], [900, 340], [900, 280], [540, 280]], '#7fd0ff');
    [[1150, 160], [460, 260]].forEach(function (c, k) {
      ctx.strokeStyle = '#ffb45a'; ctx.lineWidth = 2.5; ctx.setLineDash([10, 8]); A.rrect(ctx, c[0] - 66, c[1] - 66, 132, 132, 20); ctx.stroke(); ctx.setLineDash([]);
      ctx.fillStyle = 'rgba(255,160,50,.1)'; A.rrect(ctx, c[0] - 66, c[1] - 66, 132, 132, 20); ctx.fill();
      if (k) { ctx.strokeStyle = '#ffb45a'; A.rrect(ctx, c[0] - 16, c[1] - 16, 32, 32, 6); ctx.stroke(); }
    });
    A.vgrad(ctx, 0, 0, W, H * 0.42, [[0, 'rgba(90,50,40,0.18)'], [1, 'rgba(90,50,40,0)']]);
    A.volumetric(ctx, 1150, 160, '#ff9f31', { r: 260, a: 0.18, shafts: 3 });
    A.punkt(ctx, 1150, 160, 44, '#ffb043', '#ff9f31', 200);
    A.vgrad(ctx, 0, H * 0.62, W, H * 0.4, [[0, 'rgba(3,6,12,0)'], [1, 'rgba(3,6,12,.55)']]);
    A.finish(ctx, W, H, '#03060c', 8, 164);
  });

  // Fabric: Leinwandbindung mit farbigen Fäden
  A.scene('bn-fabric', BW, BH, function (ctx, W, H) {
    const r = A.rng(175), s = 62, bw = 51;
    ctx.fillStyle = '#2b221d'; ctx.fillRect(0, 0, W, H);
    ctx.save(); ctx.translate(W / 2, H / 2); ctx.rotate(-0.21); ctx.translate(-W / 2 - 260, -H / 2 - 380);
    const cols = Math.ceil((W + 520) / s), rows = Math.ceil((H + 760) / s);
    const warp = function (c) { return c % 9 === 4 ? '#b9553a' : c % 9 === 5 ? '#3d5c86' : c % 2 ? '#d8c39c' : '#cdb78e'; };
    const weft = function (q) { return q % 7 === 3 ? '#e2b649' : q % 2 ? '#f0e4c9' : '#e8dabb'; };
    function strand(x, y, horiz, col) {
      const g = horiz ? ctx.createLinearGradient(x - s / 2, 0, x + s / 2, 0) : ctx.createLinearGradient(0, y - s / 2, 0, y + s / 2);
      g.addColorStop(0, A.shade(col, 0.5)); g.addColorStop(0.24, col); g.addColorStop(0.5, A.mix(col, '#ffffff', 0.14)); g.addColorStop(0.76, col); g.addColorStop(1, A.shade(col, 0.5));
      ctx.fillStyle = g;
      if (horiz) ctx.fillRect(x - s / 2 - 0.5, y - bw / 2, s + 1, bw); else ctx.fillRect(x - bw / 2, y - s / 2 - 0.5, bw, s + 1);
      ctx.strokeStyle = A.rgba(A.shade(col, 0.6), 0.28); ctx.lineWidth = 1; ctx.beginPath();
      for (let k = -1; k <= 1; k++) { if (horiz) { ctx.moveTo(x - s / 2, y + k * 14); ctx.lineTo(x + s / 2, y + k * 14); } else { ctx.moveTo(x + k * 14, y - s / 2); ctx.lineTo(x + k * 14, y + s / 2); } }
      ctx.stroke();
    }
    for (let q = 0; q < rows; q++) for (let c = 0; c < cols; c++) {
      const x = c * s, y = q * s, over = (q + c) % 2 === 0;
      strand(x, y, !over, over ? warp(c) : weft(q));
      strand(x, y, over, over ? weft(q) : warp(c));
    }
    ctx.restore();
    A.radial(ctx, W * 0.74, H * 0.2, 760, 'rgba(255,240,200,.5)', 'rgba(255,240,200,0)', 'soft-light');
    A.volumetric(ctx, 1160, 168, '#ffb06a', { r: 240, a: 0.2, shafts: 3, a0: Math.PI * 0.15, a1: Math.PI * 0.55 });
    A.punkt(ctx, 1160, 168, 42, '#e07a32', '#ffc48a', 170);
    A.vgrad(ctx, 0, 0, W, H, [[0, 'rgba(30,18,10,0)'], [1, 'rgba(30,18,10,.5)']]);
    A.vignette(ctx, W, H, '#140c08', 0.6);
    A.grain(ctx, W, H, 5, 175);
  });

  // Standard: Techtyl-Seekarte im Kleinformat
  A.scene('bn-standard', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(186), px = 1150, py = 178;
    const grid = A.grid(W, H, 6, function (x, y) { return n.fbm(x / 430 + 1, y / 430 + 5, 4) * 0.75 + 0.42 * Math.exp(-Math.hypot(x - px, y - py) / 330); });
    const im = ctx.createImageData(W, H), d = im.data, c0 = A.rgb('#0a151b'), c1 = A.rgb('#1b3a46');
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
      const t = A.clamp(Math.floor((A.sample(grid, x, y) + 0.45) / 0.95 * 15) / 15, 0, 1), p = (y * W + x) * 4;
      d[p] = c0[0] + (c1[0] - c0[0]) * t; d[p + 1] = c0[1] + (c1[1] - c0[1]) * t; d[p + 2] = c0[2] + (c1[2] - c0[2]) * t; d[p + 3] = 255;
    }
    ctx.putImageData(im, 0, 0);
    ctx.lineCap = 'round';
    for (let k = 0; k <= 15; k++) {
      const idx = k % 4 === 1;
      ctx.strokeStyle = k === 11 ? 'rgba(240,163,59,.7)' : 'rgba(233,240,238,' + (idx ? 0.34 : 0.17) + ')'; ctx.lineWidth = idx || k === 11 ? 1.7 : 1.1;
      A.isoline(ctx, grid, -0.45 + 0.95 * k / 15);
    }
    ctx.strokeStyle = 'rgba(240,163,59,.1)'; ctx.lineWidth = 1; ctx.beginPath();
    for (let k = 0; k < 32; k++) { const a = k * Math.PI / 16 + 0.07; ctx.moveTo(px + Math.cos(a) * 112, py + Math.sin(a) * 112); ctx.lineTo(px + Math.cos(a) * 2000, py + Math.sin(a) * 2000); }
    ctx.stroke();
    ctx.fillStyle = 'rgba(10,21,27,.55)'; ctx.beginPath(); ctx.arc(px, py, 104, 0, 7); ctx.fill();
    [[70, 0.35, 1.2], [104, 0.6, 1.5], [111, 0.3, 1]].forEach(function (c) { ctx.strokeStyle = 'rgba(240,163,59,' + c[1] + ')'; ctx.lineWidth = c[2]; ctx.beginPath(); ctx.arc(px, py, c[0], 0, 7); ctx.stroke(); });
    ctx.strokeStyle = 'rgba(240,163,59,.65)'; ctx.lineWidth = 1.2; ctx.beginPath();
    for (let k = 0; k < 72; k++) { const a = k * Math.PI / 36, len = k % 18 === 0 ? 20 : k % 3 === 0 ? 11 : 6; ctx.moveTo(px + Math.cos(a) * 104, py + Math.sin(a) * 104); ctx.lineTo(px + Math.cos(a) * (104 - len), py + Math.sin(a) * (104 - len)); }
    ctx.stroke();
    A.punkt(ctx, px, py, 36, '#f0a33b', '#f0a33b', 150);
    A.vignette(ctx, W, H, '#050c10', 0.5); A.grain(ctx, W, H, 7, 186);
  });
})();
