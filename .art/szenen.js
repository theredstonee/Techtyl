/* Hintergründe (2560×1440). Vier Ebenen: Vordergrundrahmen, Mittelgrund,
   Ferne, Himmel. Eine Lichtquelle (Techtyl-Punkt). Mittleres 40 % kontrastarm. */
(function () {
  const A = window.A, GW = 2560, GH = 1440;

  function pine(ctx, x, y, h, col, r, rim) {
    ctx.fillStyle = col;
    const tw = Math.max(1.5, h * 0.016);
    ctx.fillRect(x - tw * 0.5, y - h * 0.2, tw, h * 0.22);
    const N = 6 + ((r() * 3) | 0);
    const lean = (r() - 0.5) * h * 0.05;
    for (let t = 0; t < N; t++) {
      const u = t / (N - 1);
      const yt = y - h * (0.98 - u * 0.8);
      const hw = h * (0.015 + Math.pow(u, 0.8) * 0.2) * (0.72 + r() * 0.5);
      const drop = h * (0.02 + u * 0.04);
      const x0 = x + lean * (1 - u);
      const right = hw * (0.8 + r() * 0.45);
      const left = hw * (0.75 + r() * 0.5);
      ctx.beginPath();
      ctx.moveTo(x0, yt - h * 0.03);
      ctx.quadraticCurveTo(x0 + right * 0.6, yt + drop * 0.15, x0 + right, yt + drop);
      ctx.quadraticCurveTo(x0 + right * 0.25, yt + drop * 0.45, x0, yt + drop * 0.12);
      ctx.quadraticCurveTo(x0 - left * 0.3, yt + drop * 0.5, x0 - left, yt + drop * 0.9);
      ctx.quadraticCurveTo(x0 - left * 0.35, yt + drop * 0.1, x0, yt - h * 0.03);
      ctx.fill();
    }
    if (rim) {
      ctx.strokeStyle = rim;
      ctx.lineWidth = Math.max(1.2, h * 0.008);
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(x + lean * 0.15, y - h * 0.96);
      ctx.quadraticCurveTo(x + h * 0.03, y - h * 0.72, x + h * 0.06, y - h * 0.5);
      ctx.stroke();
    }
  }

  function valley(W, base, lift, amp, scale, n, off) {
    const ys = new Float32Array(W + 1);
    for (let x = 0; x <= W; x++) {
      const e = Math.pow(Math.abs(x / W - 0.5) * 2, 1.55);
      const v = Math.abs(n.fbm(x / scale + off, off * 1.3, 4));
      const jag = Math.abs(n(x / (scale * 0.33) + off, 2.4));
      ys[x] = base - lift * e - amp * (0.62 * v + 0.38 * jag) * (0.35 + 0.65 * e);
    }
    return ys;
  }

  function forest(ctx, ys, W, col, r, o) {
    let x = o.x0 == null ? -40 : o.x0;
    const x1 = o.x1 == null ? W + 40 : o.x1;
    while (x < x1) {
      const e = Math.abs(x / W - 0.5) * 2;
      if (o.skip && e < o.skip) { x += o.step || 90; continue; }
      const boost = o.edge ? (0.45 + 0.55 * Math.min(1, e)) : 1;
      const h = (o.min + r() * (o.max - o.min)) * boost;
      const xi = A.clamp(Math.round(x), 0, W);
      const c = r() < 0.4 ? A.shade(col, 0.86) : col;
      const rim = o.rim && e > 0.45 && r() < 0.55 ? o.rim : null;
      pine(ctx, x, ys[xi] + h * 0.04, h, c, r, rim);
      x += Math.max(18, h * (0.18 + r() * 0.28));
    }
  }

  function mist(ctx, ys, col, lift, thick) {
    const W = ys.length - 1;
    ctx.beginPath();
    ctx.moveTo(0, ys[0] - lift);
    for (let x = 1; x <= W; x++) ctx.lineTo(x, ys[x] - lift);
    for (let x = W; x >= 0; x--) {
      const e = A.clamp(1.15 - Math.abs(x / W - 0.5) * 1.7, 0.2, 1);
      ctx.lineTo(x, ys[x] + thick * e);
    }
    ctx.closePath();
    ctx.fillStyle = col;
    ctx.fill();
  }

  function cloudBank(ctx, W, y, h, color, alpha, seed) {
    const r = A.rng(seed);
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(-30, y + h * 0.35);
    let x = -30;
    while (x < W + 40) {
      const w = 180 + r() * 340;
      const rise = h * (0.35 + r() * 0.9) * (0.45 + 0.55 * Math.abs(x / W - 0.5) * 1.4);
      ctx.quadraticCurveTo(x + w * 0.3, y + h - rise, x + w * 0.55, y + h - rise * 0.55);
      ctx.quadraticCurveTo(x + w * 0.8, y + h - rise * 0.15, x + w, y + h * 0.7);
      x += w * 0.62;
    }
    ctx.lineTo(W + 20, y + h + 70);
    ctx.lineTo(-30, y + h + 70);
    ctx.closePath();
    const g = ctx.createLinearGradient(0, y, 0, y + h + 70);
    g.addColorStop(0, A.rgba(color, alpha));
    g.addColorStop(0.42, A.rgba(color, alpha * 0.75));
    g.addColorStop(1, A.rgba(color, 0));
    ctx.fillStyle = g;
    ctx.fill();
    ctx.restore();
  }

  // Weicher Temperaturverlauf. Harte Bänder würden als Streifen stehen bleiben.
  function bands(ctx, W, rows) {
    const last = rows[rows.length - 1];
    const maxY = last[2] || last[0];
    const g = ctx.createLinearGradient(0, 0, 0, maxY);
    for (let i = 0; i < rows.length; i++) {
      g.addColorStop(A.clamp(rows[i][0] / maxY, 0, 0.998), rows[i][1]);
    }
    if (last[0] / maxY < 0.998) g.addColorStop(1, last[1]);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, maxY);
  }

  function soften() {}

  function sheet(ctx, ys, color, shade) {
    ctx.save();
    ctx.translate(-14, 16);
    A.fillRidge(ctx, ys, GH + 40, shade);
    ctx.restore();
    A.fillRidge(ctx, ys, GH + 40, color);
    ctx.strokeStyle = 'rgba(62,48,40,.22)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    for (let x = 0; x < ys.length; x += 2) ctx.lineTo(x, ys[x] + 0.5);
    ctx.stroke();
  }

  function paperRidge(W, base, lift, amp, scale, n, off) {
    const ys = new Float32Array(W + 1);
    for (let x = 0; x <= W; x++) {
      const e = Math.pow(Math.abs(x / W - 0.5) * 2, 1.45);
      const v = Math.abs(n.fbm(x / scale + off, off * 0.7, 4));
      const peak = Math.pow(Math.abs(n(x / (scale * 0.42) + off, 1.7)), 0.55);
      ys[x] = base - lift * e - amp * (0.5 * v + 0.5 * peak);
    }
    return ys;
  }

  function face(ctx, pts, col) {
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
    ctx.closePath();
    ctx.fillStyle = col;
    ctx.fill();
  }

  function strata(ctx, pts, col, r, gap, dots) {
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
    ctx.closePath();
    ctx.clip();
    let minX = 1e9, maxX = -1e9, minY = 1e9, maxY = -1e9;
    for (let i = 0; i < pts.length; i++) {
      const p = pts[i];
      if (p[0] < minX) minX = p[0];
      if (p[0] > maxX) maxX = p[0];
      if (p[1] < minY) minY = p[1];
      if (p[1] > maxY) maxY = p[1];
    }
    ctx.strokeStyle = col;
    ctx.lineWidth = 1.15;
    ctx.beginPath();
    for (let y = minY + gap * 0.5; y < maxY; y += gap) {
      const j = Math.sin(y * 0.08 + minX) * 3;
      if (r() < 0.18) continue;
      ctx.moveTo(minX - 4, y);
      ctx.quadraticCurveTo((minX + maxX) * 0.5, y + j, maxX + 4, y + 1);
    }
    ctx.stroke();
    if (dots) A.stipple(ctx, minX, minY, Math.max(2, maxX - minX), Math.max(2, maxY - minY), dots, col, function () { return 0.65; }, r, 1.6);
    ctx.restore();
  }

  function stone(ctx, s, pal, r) {
    const x = s.x, yb = s.y, w = s.w, h = s.h, lean = s.lean || 0;
    const dx = lean * h;
    const top = yb - h;
    const b0 = x, b1 = x + w * (s.split || 0.4), b2 = x + w;
    const t0 = x + w * 0.05 + dx * 0.25, t1 = x + w * (s.split || 0.4) + dx, t2 = x + w * 0.9 + dx * 0.1;
    const notch = s.notch || 0;
    const left = [[b0, yb], [t0, top + h * 0.07], [t1, top + notch], [b1, yb]];
    const right = [[b1, yb], [t1, top + notch], [t2, top + h * 0.045], [b2, yb]];
    face(ctx, left, pal.shade);
    strata(ctx, left, pal.line, r, s.gap || 18, s.dots || 1800);
    face(ctx, right, pal.lit);
    strata(ctx, right, pal.lineLit || 'rgba(90,60,30,.22)', r, (s.gap || 18) + 4, Math.round((s.dots || 1800) * 0.45));
    ctx.strokeStyle = pal.rim;
    ctx.lineWidth = Math.max(1.4, w * 0.03);
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(t1, top + notch);
    ctx.lineTo(t2, top + h * 0.045);
    ctx.lineTo(b2, yb - 2);
    ctx.stroke();
    return { top: top + notch, x: t1 };
  }

  function lintel(ctx, x, y, w, th, pal) {
    face(ctx, [[x, y], [x + w * 0.08, y - th * 0.35], [x + w * 0.5, y - th * 0.2], [x + w * 0.5, y + th * 0.45]], pal.shade);
    face(ctx, [[x + w * 0.5, y + th * 0.45], [x + w * 0.5, y - th * 0.2], [x + w * 0.94, y - th * 0.55], [x + w, y + th * 0.15]], pal.lit);
    face(ctx, [[x + w * 0.08, y - th * 0.35], [x + w * 0.2, y - th * 0.85], [x + w * 0.86, y - th], [x + w * 0.94, y - th * 0.55], [x + w * 0.5, y - th * 0.2]], pal.top);
    ctx.strokeStyle = pal.rim;
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(x + w * 0.2, y - th * 0.85);
    ctx.lineTo(x + w * 0.86, y - th);
    ctx.lineTo(x + w * 0.94, y - th * 0.55);
    ctx.stroke();
  }

  function hull(ctx, x, y, len, h, col, cabin) {
    ctx.fillStyle = col;
    ctx.beginPath();
    ctx.moveTo(x, y + 2);
    ctx.lineTo(x + len * 0.06, y - h * 0.2);
    ctx.lineTo(x + len * 0.62, y - h * 0.42);
    ctx.lineTo(x + len * 0.82, y - h * 0.22);
    ctx.lineTo(x + len, y - h * 0.7);
    ctx.lineTo(x + len * 0.72, y + 2);
    ctx.closePath();
    ctx.fill();
    if (cabin) {
      ctx.fillRect(x + len * 0.3, y - h * 1.25, len * 0.22, h * 0.9);
      ctx.fillRect(x + len * 0.22, y - h * 0.5, len * 0.4, h * 0.16);
      ctx.fillRect(x + len * 0.39, y - h * 2.3, Math.max(3, len * 0.012), h * 1.15);
    }
  }

  function crane(ctx, x, base, s, col, kind) {
    ctx.strokeStyle = col;
    ctx.fillStyle = col;
    ctx.lineCap = 'butt';
    ctx.lineJoin = 'round';
    if (kind === 'portal') {
      ctx.lineWidth = 9 * s;
      ctx.beginPath();
      ctx.moveTo(x, base); ctx.lineTo(x + 8 * s, base - 300 * s);
      ctx.moveTo(x + 86 * s, base); ctx.lineTo(x + 74 * s, base - 300 * s);
      ctx.moveTo(x - 30 * s, base - 286 * s); ctx.lineTo(x + 250 * s, base - 300 * s);
      ctx.stroke();
      ctx.lineWidth = 3.2 * s;
      ctx.beginPath();
      ctx.moveTo(x, base - 70 * s); ctx.lineTo(x + 80 * s, base - 170 * s);
      ctx.moveTo(x + 86 * s, base - 70 * s); ctx.lineTo(x + 6 * s, base - 170 * s);
      ctx.moveTo(x + 4 * s, base - 180 * s); ctx.lineTo(x + 78 * s, base - 270 * s);
      ctx.moveTo(x + 80 * s, base - 180 * s); ctx.lineTo(x + 8 * s, base - 270 * s);
      ctx.moveTo(x + 150 * s, base - 294 * s); ctx.lineTo(x + 150 * s, base - 160 * s);
      ctx.stroke();
      ctx.fillRect(x + 132 * s, base - 168 * s, 36 * s, 22 * s);
    } else if (kind === 'derrick') {
      ctx.lineWidth = 8 * s;
      ctx.beginPath();
      ctx.moveTo(x, base); ctx.lineTo(x + 10 * s, base - 240 * s);
      ctx.moveTo(x + 70 * s, base); ctx.lineTo(x + 28 * s, base - 240 * s);
      ctx.moveTo(x - 120 * s, base - 210 * s); ctx.lineTo(x + 210 * s, base - 250 * s);
      ctx.stroke();
      ctx.lineWidth = 2.6 * s;
      ctx.beginPath();
      ctx.moveTo(x + 18 * s, base - 240 * s); ctx.lineTo(x + 190 * s, base - 246 * s);
      ctx.moveTo(x + 120 * s, base - 236 * s); ctx.lineTo(x + 108 * s, base - 90 * s);
      ctx.stroke();
      ctx.fillRect(x + 92 * s, base - 96 * s, 30 * s, 18 * s);
    } else {
      ctx.lineWidth = 6 * s;
      ctx.beginPath();
      ctx.moveTo(x, base); ctx.lineTo(x - 8 * s, base - 180 * s);
      ctx.moveTo(x + 48 * s, base); ctx.lineTo(x + 36 * s, base - 180 * s);
      ctx.moveTo(x - 70 * s, base - 168 * s); ctx.lineTo(x + 130 * s, base - 188 * s);
      ctx.stroke();
      ctx.lineWidth = 2 * s;
      ctx.beginPath();
      ctx.moveTo(x - 4 * s, base - 40 * s); ctx.lineTo(x + 40 * s, base - 120 * s);
      ctx.moveTo(x + 44 * s, base - 40 * s); ctx.lineTo(x, base - 120 * s);
      ctx.stroke();
    }
  }

  function shed(ctx, x, y, w, h, col) {
    ctx.fillStyle = col;
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x, y - h);
    ctx.lineTo(x + w * 0.18, y - h * 1.22);
    ctx.lineTo(x + w * 0.4, y - h * 0.98);
    ctx.lineTo(x + w * 0.58, y - h * 1.16);
    ctx.lineTo(x + w * 0.78, y - h);
    ctx.lineTo(x + w, y - h * 0.9);
    ctx.lineTo(x + w, y);
    ctx.closePath();
    ctx.fill();
  }

  function curtain(ctx, W, o, n) {
    const step = 4;
    for (let x = 0; x < W; x += step) {
      const fold = n.fbm(x / o.scale + o.off, o.off * 0.3, 3);
      const brk = n(x / o.brk + o.off, 4);
      if (brk > o.cut) continue;
      const h = o.h * (0.45 + 0.55 * A.smooth(-0.6, 0.8, fold));
      const y0 = o.y + fold * o.wander;
      const edge = A.clamp(1 - Math.abs(x / W - o.cx) * o.fall, 0, 1);
      const it = edge * (0.35 + 0.65 * (1 - Math.max(0, brk)));
      if (it < 0.08) continue;
      ctx.fillStyle = A.rgba(o.edge, 0.45 * it);
      ctx.fillRect(x, y0 - h, step + 0.5, h);
      ctx.fillStyle = A.rgba(o.core, 0.7 * it);
      ctx.fillRect(x, y0 - h * 0.72, step + 0.5, h * 0.42);
      ctx.fillStyle = A.rgba(o.hot, 0.55 * it);
      ctx.fillRect(x, y0 - h * 0.58, step + 0.5, h * 0.12);
    }
  }

  function stars(ctx, W, H, r, count, yMax) {
    for (let k = 0; k < count; k++) {
      const x = r() * W, y = r() * yMax;
      const cx = (x - W * 0.5) / (W * 0.22), cy = (y - H * 0.48) / (H * 0.2);
      if (cx * cx + cy * cy < 1 && r() < 0.7) continue;
      const mag = r();
      const s = mag > 0.975 ? 2.6 : mag > 0.88 ? 1.6 : 1;
      const a = mag > 0.975 ? 0.9 : 0.2 + mag * 0.45;
      ctx.fillStyle = r() < 0.16 ? 'rgba(255,232,206,' + a + ')' : 'rgba(236,240,255,' + a + ')';
      ctx.fillRect(x, y, s, s);
      if (mag > 0.988) {
        ctx.fillRect(x - 3.5, y + s * 0.3, s + 7, 1);
        ctx.fillRect(x + s * 0.3, y - 3.5, 1, s + 7);
      }
    }
  }

  function blob(ctx, pts, col) {
    ctx.beginPath();
    const p0 = pts[0], p1 = pts[1];
    ctx.moveTo((p0[0] + p1[0]) / 2, (p0[1] + p1[1]) / 2);
    for (let i = 1; i <= pts.length; i++) {
      const p = pts[i % pts.length], n = pts[(i + 1) % pts.length];
      ctx.quadraticCurveTo(p[0], p[1], (p[0] + n[0]) / 2, (p[1] + n[1]) / 2);
    }
    ctx.closePath();
    if (col) { ctx.fillStyle = col; ctx.fill(); }
  }

  // —— Papier: Schnittlagen, eine Tinte, die Sonne ist der Punkt ——
  A.scene('bg-papier', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(201);
    ctx.fillStyle = '#f6f1e6';
    ctx.fillRect(0, 0, W, H);
    A.radial(ctx, W * 0.78, H * 0.2, 720, 'rgba(255,244,220,.85)', 'rgba(255,244,220,0)');
    cloudBank(ctx, W, 40, 120, '#efe4d2', 0.45, 11);
    cloudBank(ctx, W, 90, 90, '#f7f3ea', 0.7, 19);
    const far = paperRidge(W, H * 0.48, 180, 110, 620, n, 1.2);
    const mid = paperRidge(W, H * 0.6, 240, 140, 480, n, 4.4);
    const near = paperRidge(W, H * 0.72, 320, 90, 540, n, 8.1);
    function summit(ys, cx, h, w) {
      for (let x = 0; x < ys.length; x++) ys[x] -= h * Math.exp(-Math.pow((x - cx) / w, 2));
    }
    summit(far, 280, 70, 180);
    summit(far, 2100, 90, 240);
    summit(mid, 520, 100, 200);
    summit(mid, 1680, 130, 220);
    summit(near, 160, 180, 260);
    summit(near, 2360, 210, 300);
    sheet(ctx, far, '#efe6d6', 'rgba(70,58,48,.13)');
    sheet(ctx, mid, '#e3d5c2', 'rgba(70,58,48,.16)');
    sheet(ctx, near, '#d4c3ab', 'rgba(62,48,40,.2)');
    const sea1 = paperRidge(W, H * 0.78, 70, 64, 700, n, 2.2);
    const sea2 = paperRidge(W, H * 0.86, 50, 48, 520, n, 6.5);
    const sea3 = paperRidge(W, H * 0.94, 36, 40, 640, n, 9.4);
    for (let x = 0; x <= W; x++) {
      const e = Math.pow(Math.abs(x / W - 0.5) * 2, 1.15);
      sea3[x] -= 90 * e;
      sea2[x] -= 36 * e;
    }
    sheet(ctx, sea1, '#e7eeea', 'rgba(50,60,70,.10)');
    sheet(ctx, sea2, '#d5e0dc', 'rgba(36,60,98,.12)');
    sheet(ctx, sea3, '#243c62', 'rgba(20,28,48,.20)');
    // Tintenlinie nur auf dem nächsten Grat, als einziger Akzent oben
    ctx.strokeStyle = 'rgba(36,60,98,.55)';
    ctx.lineWidth = 2.2;
    ctx.beginPath();
    for (let x = 0; x < W; x += 2) if (x < W * 0.22 || x > W * 0.78) ctx.lineTo(x, near[x]);
    else ctx.moveTo(x, near[x]);
    ctx.stroke();
    // Fasern, sehr zurückhaltend
    const r = A.rng(201);
    ctx.lineCap = 'round';
    for (let k = 0; k < 900; k++) {
      const x = r() * W, y = r() * H, a = r() * 6.28, len = 6 + r() * 16;
      ctx.strokeStyle = r() < 0.5 ? 'rgba(80,64,48,.035)' : 'rgba(255,252,246,.4)';
      ctx.lineWidth = 0.8;
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(x + Math.cos(a) * len, y + Math.sin(a) * len * 0.4);
      ctx.stroke();
    }
    const sx = W * 0.76, sy = H * 0.18;
    A.radial(ctx, sx, sy + 40, 780, 'rgba(244,206,150,.42)', 'rgba(244,206,150,0)', 'screen', 1.15, 0.72);
    ctx.fillStyle = 'rgba(92,72,52,.28)';
    A.rrect(ctx, sx - 16, sy - 10, 58, 58, 14);
    ctx.fill();
    A.punkt(ctx, sx, sy, 54, '#fff8ec', '#e8c48a', 460);
    ctx.strokeStyle = 'rgba(120,82,42,.8)';
    ctx.lineWidth = 2.6;
    A.rrect(ctx, sx - 27, sy - 27, 54, 54, 12);
    ctx.stroke();
    A.dither(ctx, W, H, { amp: 7, grain: 1.7, seed: 201 });
  });

  // —— Hafen ——
  A.scene('bg-hafen', GW, GH, function (ctx, W, H) {
    const r = A.rng(212);
    const hz = 820;
    const lx = 1988, ly = 470;
    bands(ctx, W, [[0, '#140c18', 260], [260, '#241428', 460], [460, '#4a2844', 640], [640, '#8a4860', hz], [hz, '#3a2438', hz + 80]]);
    soften(ctx, W, 260, 50, '#241428', 22000, r);
    soften(ctx, W, 460, 56, '#4a2844', 26000, r);
    soften(ctx, W, 640, 70, '#8a4860', 30000, r);
    cloudBank(ctx, W, 30, 150, '#1a1022', 0.55, 21);
    cloudBank(ctx, W, 80, 120, '#2a1830', 0.4, 33);
    // Wasser
    const g = ctx.createLinearGradient(0, hz, 0, H);
    g.addColorStop(0, '#4a2844');
    g.addColorStop(0.18, '#2a1830');
    g.addColorStop(1, '#100814');
    ctx.fillStyle = g;
    ctx.fillRect(0, hz, W, H - hz);
    // Ferne Hallen, kontrastarm, mittig niedrig
    crane(ctx, 1880, hz, 0.72, '#3a2844', 'derrick');
    shed(ctx, 2140, hz + 4, 280, 78, '#241624');
    crane(ctx, 80, hz, 1.08, '#120810', 'portal');
    crane(ctx, 390, hz, 0.84, '#160c14', 'derrick');
    shed(ctx, 20, hz + 6, 220, 58, '#160c14');
    crane(ctx, 640, hz, 0.48, '#3a2844', 'small');
    hull(ctx, 500, hz + 6, 300, 40, '#120810', true);
    hull(ctx, 1560, hz + 8, 340, 46, '#10080e', true);
    // Vordergrund: Pier links, Bug rechts
    ctx.fillStyle = '#100814';
    ctx.fillRect(0, hz + 20, 340, 28);
    for (let i = 0; i < 5; i++) ctx.fillRect(30 + i * 62, hz + 40, 14, 90 + i * 8);
    ctx.beginPath();
    ctx.moveTo(1880, H + 10);
    ctx.lineTo(2100, hz + 150);
    ctx.quadraticCurveTo(2360, hz + 90, 2680, hz + 180);
    ctx.lineTo(2680, H + 10);
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,190,170,.45)';
    ctx.lineWidth = 2.4;
    ctx.beginPath();
    ctx.moveTo(2100, hz + 150);
    ctx.quadraticCurveTo(2360, hz + 90, 2620, hz + 168);
    ctx.stroke();
    // Wellen
    for (let k = 0; k < 28; k++) {
      const t = k / 28;
      const y = hz + 18 + Math.pow(t, 1.35) * (H - hz - 40) + r() * 6;
      const gap = 28 + t * 70 + r() * 20;
      ctx.fillStyle = 'rgba(232,186,196,' + (0.03 + (1 - t) * 0.045) + ')';
      let x = r() * gap;
      while (x < W) {
        if (r() > 0.35) ctx.fillRect(x, y, gap * (0.2 + r() * 0.35), 1.5);
        x += gap;
      }
    }
    // Eine Spiegelung
    for (let k = 0; k < 22; k++) {
      const t = k / 22;
      const y = hz + 8 + t * t * 340;
      const w = 8 + t * 46;
      if (k % 3 === 0) continue;
      ctx.fillStyle = 'rgba(255,176,156,' + (0.42 * (1 - t)) + ')';
      ctx.fillRect(lx - w / 2 + Math.sin(k * 1.4) * t * 14, y, w, 2.2);
    }
    A.volumetric(ctx, lx, ly, '#ffb0a0', { r: 720, a: 0.26, shafts: 0, sy: 0.85, sx: 1.15 });
    // Mast unter dem Punkt
    ctx.strokeStyle = '#1a101c';
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.moveTo(lx, ly + 16); ctx.lineTo(lx - 6, hz); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,200,184,.7)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(lx + 2, ly + 18); ctx.lineTo(lx + 4, hz - 40); ctx.stroke();
    A.punkt(ctx, lx, ly, 32, '#ffe4da', '#ffb0a0', 220);
    A.vignette(ctx, W, H, '#0c0610', 0.4);
    A.dither(ctx, W, H, { amp: 12, grain: 2.4, seed: 212 });
  });

  // Geschlossene Silhouette. Breit in der unteren Mitte, am Boden schmaler, damit kein Schnitt entsteht.
  function pineTree(ctx, x, base, h, col, r, opt) {
    opt = opt || {};
    const spread = opt.spread != null ? opt.spread : 0.64 + r() * 0.34;
    const lean = opt.lean != null ? opt.lean : (r() - 0.5) * 0.035;
    let tiers = h > 420 ? 8 : h > 180 ? 7 : h > 80 ? 6 : 5;
    if (r() < 0.35) tiers += 1;
    ctx.fillStyle = col;
    const top = base - h;
    for (let t = 0; t < tiers; t++) {
      const u = t / Math.max(1, tiers - 1);
      const yTip = top + u * h * 0.58;
      const tierH = h * (0.38 + u * 0.12);
      const yBot = Math.min(base + 1, yTip + tierH);
      const belly = 0.22 + 0.78 * Math.sin(Math.min(0.84, u) * Math.PI);
      const hw = h * spread * (0.018 + belly * 0.2);
      const wl = hw * (0.84 + r() * 0.26);
      const wr = hw * (0.84 + r() * 0.26);
      const xTip = x + (yTip - top) * lean;
      const xBot = x + (yBot - top) * lean * 0.5;
      ctx.beginPath();
      ctx.moveTo(xTip, yTip);
      ctx.lineTo(xBot + wr, yBot);
      ctx.lineTo(xBot - wl, yBot);
      ctx.closePath();
      ctx.fill();
    }
  }

  function crestFrom(W, pts, n, jag) {
    const ys = new Float32Array(W + 1);
    let i = 0;
    for (let x = 0; x <= W; x++) {
      while (i < pts.length - 2 && x >= pts[i + 1][0]) i++;
      const a = pts[i], b = pts[i + 1];
      let t = (x - a[0]) / Math.max(1, b[0] - a[0]);
      if (t < 0) t = 0;
      if (t > 1) t = 1;
      const s = t * t * (3 - 2 * t);
      let y = a[1] + (b[1] - a[1]) * s;
      y += n.fbm(x / 260 + jag, 1.4 + jag * 0.17, 3) * 12;
      y += n(x / 78 + jag, 3.1) * 5;
      ys[x] = y;
    }
    return ys;
  }

  // Bäume auf einer Kammlinie. Horste und Lücken, kürzere zuerst.
  function stand(ctx, ys, col, r, o) {
    const W = ys.length - 1;
    const items = [];
    let x = o.from == null ? -30 : o.from;
    const to = o.to == null ? W + 30 : o.to;
    let guard = 0;
    while (x < to && guard++ < 900) {
      const edge = Math.abs(x / Math.max(1, W) - 0.5) * 2;
      const q = o.quiet || 0;
      const atten = q <= 0 || edge >= q ? 1 : edge / q;
      if (o.skip && r() < (1 - atten) * o.skip) {
        x += 48 + r() * 110;
        continue;
      }
      const wave = 0.5 + 0.5 * Math.sin(x * 0.02 + (o.phase || 1.7));
      let hh = o.min + (o.max - o.min) * (0.22 + 0.78 * Math.pow(r(), 0.62)) * (0.5 + 0.5 * wave);
      const roll = r();
      if (roll < 0.16) hh = o.max * (1.12 + r() * 0.38);
      else if (roll < 0.26) hh = o.min * (0.5 + r() * 0.35);
      if (o.fade) hh *= 0.28 + 0.72 * Math.pow(Math.max(0.04, atten), 0.75);
      if (hh < 20) { x += 26; continue; }
      const xi = Math.max(0, Math.min(W, Math.round(Math.max(0, x))));
      const tone = r() < 0.38 ? A.shade(col, 0.86 + r() * 0.16) : col;
      items.push([x + (r() - 0.5) * 8, ys[xi] + (o.drop || 0), hh, tone]);
      const sp = o.space || 1;
      const open = r() < 0.14;
      const step = open ? hh * (0.9 + r() * 0.45) : hh * (0.34 + r() * 0.22);
      x += Math.max(o.gap || 18, step * sp);
    }
    items.sort(function (a, b) { return a[2] - b[2]; });
    for (let i = 0; i < items.length; i++) pineTree(ctx, items[i][0], items[i][1], items[i][2], items[i][3], r);
  }

  // Weicher Dunst ohne harte Kontur. Liegt zwischen den Kämmen, dicker zur Talmitte.
  function mistWash(ctx, W, y, h, color, alpha) {
    const c = A.layer(W, GH);
    A.vgrad(c, 0, y, W, h, [
      [0, A.rgba(color, 0)],
      [0.4, A.rgba(color, alpha)],
      [0.68, A.rgba(color, alpha * 0.4)],
      [1, A.rgba(color, 0)]
    ]);
    c.globalCompositeOperation = 'destination-in';
    const hg = c.createLinearGradient(0, 0, W, 0);
    hg.addColorStop(0, 'rgba(0,0,0,0.2)');
    hg.addColorStop(0.16, 'rgba(0,0,0,0.7)');
    hg.addColorStop(0.5, 'rgba(0,0,0,1)');
    hg.addColorStop(0.84, 'rgba(0,0,0,0.7)');
    hg.addColorStop(1, 'rgba(0,0,0,0.2)');
    c.fillStyle = hg;
    c.fillRect(0, 0, W, GH);
    ctx.drawImage(c.canvas, 0, 0);
  }

  function crestLight(ctx, ys, x0, x1) {
    ctx.save();
    ctx.strokeStyle = 'rgba(244,236,214,0.38)';
    ctx.lineWidth = 1.6;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.beginPath();
    for (let x = x0; x <= x1; x += 2) {
      if (x === x0) ctx.moveTo(x, ys[x] - 0.5);
      else ctx.lineTo(x, ys[x] - 0.5);
    }
    ctx.stroke();
    ctx.restore();
  }

  function skirt(ctx, ys, depth, color) {
    const W = ys.length - 1;
    ctx.beginPath();
    ctx.moveTo(0, ys[0]);
    for (let x = 1; x <= W; x++) ctx.lineTo(x, ys[x]);
    for (let x = W; x >= 0; x--) ctx.lineTo(x, ys[x] + depth);
    ctx.closePath();
    ctx.fillStyle = color;
    ctx.fill();
  }

  function moonFace(ctx, ys, mx, my, radius, alpha) {
    ctx.save();
    A.fillRidge(ctx, ys, GH + 8, null);
    ctx.clip();
    A.radial(ctx, mx, my, radius, 'rgba(236,228,206,' + alpha + ')', 'rgba(236,228,206,0)', 'screen', 1.05, 0.7);
    ctx.restore();
  }

  // —— Kiefer: Nebelwald. Seitliche Kämme, offenes Tal, dunkler Vordergrund. ——
  A.scene('bg-kiefer', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(223), r = A.rng(223);
    const mx = 1500, my = 188;
    bands(ctx, W, [
      [0, '#101814'],
      [160, '#1a2c24'],
      [380, '#3a5648'],
      [640, '#7d9a8a'],
      [920, '#c5d4c2'],
      [1180, '#a8bba8'],
      [H, '#31483e', H]
    ]);
    A.radial(ctx, mx, my, 460, 'rgba(236,230,206,0.16)', 'rgba(236,230,206,0)', 'screen', 1.15, 0.62);
    const peak = crestFrom(W, [
      [0, 640], [200, 520], [480, 440], [760, 510], [1040, 660],
      [1280, 780], [1520, 820], [1800, 660], [2060, 440],
      [2340, 520], [2560, 640]
    ], n, 1.15);
    const canopy = A.layer(W, H);
    stand(canopy, peak, '#1a2c24', r, { to: 920, min: 240, max: 580, space: 0.74, phase: 0.4 });
    stand(canopy, peak, '#20342a', r, { from: 1660, min: 250, max: 600, space: 0.72, phase: 2.2 });
    stand(canopy, peak, '#3a5246', r, { from: 680, to: 960, min: 180, max: 360, space: 1.35, gap: 50, phase: 0.8 });
    stand(canopy, peak, '#3a5246', r, { from: 1620, to: 1920, min: 180, max: 380, space: 1.35, gap: 50, phase: 1.9 });
    canopy.globalCompositeOperation = 'destination-in';
    const sink = canopy.createLinearGradient(0, 280, 0, 560);
    sink.addColorStop(0, 'rgba(0,0,0,1)');
    sink.addColorStop(0.22, 'rgba(0,0,0,1)');
    sink.addColorStop(1, 'rgba(0,0,0,0)');
    canopy.fillStyle = sink;
    canopy.fillRect(0, 0, W, H);
    ctx.drawImage(canopy.canvas, 0, 0);
    mistWash(ctx, W, 400, 360, '#d7e6d4', 0.32);
    const far = crestFrom(W, [
      [0, 820], [320, 880], [700, 950], [1080, 1000], [1480, 990],
      [1900, 940], [2240, 870], [2560, 810]
    ], n, 6.2);
    A.fillRidge(ctx, far, H, '#7c968a');
    mistWash(ctx, W, 760, 300, '#d5e3d2', 0.28);
    stand(ctx, far, '#2a4036', r, { min: 48, max: 190, quiet: 0.48, skip: 0.62, fade: true, space: 0.78, phase: 0.6 });
    mistWash(ctx, W, 980, 220, '#c9d8c6', 0.14);
    const near = crestFrom(W, [
      [0, 1080], [300, 1180], [640, 1280], [1000, 1360],
      [1560, 1360], [1940, 1260], [2260, 1140], [2560, 1040]
    ], n, 3.2);
    A.fillRidge(ctx, near, H, '#101814');
    stand(ctx, near, '#0c1410', r, { to: 700, min: 120, max: 360, space: 0.72, phase: 1.4 });
    stand(ctx, near, '#0c1410', r, { from: 1880, min: 120, max: 380, space: 0.7, phase: 2.5 });
    pineTree(ctx, -60, 1680, 980, '#070c09', r, { spread: 0.86 });
    pineTree(ctx, 150, 1560, 560, '#0a120e', r, { spread: 0.74 });
    pineTree(ctx, 2620, 1700, 1040, '#070c09', r, { spread: 0.88 });
    pineTree(ctx, 2360, 1560, 520, '#0a120e', r, { spread: 0.72 });
    A.punkt(ctx, mx, my, 34, '#f4efe2', '#e7e0cc', 160);
    A.vignette(ctx, W, H, '#070c09', 0.34);
    A.dither(ctx, W, H, { amp: 11, grain: 2.2, seed: 223 });
  });

  function starfield(ctx, W, yMax, r, count, mx, my) {
    for (let i = 0; i < count; i++) {
      const x = r() * W;
      const y = r() * yMax;
      if (Math.hypot(x - mx, y - my) < 110) continue;
      const mag = r();
      const s = mag > 0.986 ? 2.15 : mag > 0.9 ? 1.45 : 1;
      const a = mag > 0.986 ? 0.82 : 0.16 + mag * 0.38;
      ctx.fillStyle = 'rgba(224,230,240,' + a.toFixed(3) + ')';
      ctx.fillRect(x, y, s, s);
    }
  }

  // Weiche Vorhänge: überlappende, sich verjüngende Bänder. Helligkeit unten, Ränder auf null.
  function veil(target, spec, rnd) {
    function ribbon(width, alpha, core) {
      const t = 0.04 + rnd() * 0.92;
      const edge = Math.sin(t * Math.PI);
      const x = spec.x + (t - 0.5) * spec.spread;
      const h = spec.h * (0.42 + 0.58 * edge) * (0.84 + rnd() * 0.22);
      const yb = spec.base + (rnd() - 0.5) * 40 - (1 - edge) * 28;
      const yt = yb - h;
      const phase = spec.phase + (t - 0.5) * 1.6;
      const sway = (78 + 120 * edge) * (spec.sway || 1);
      const pick = rnd();
      let col;
      if (edge < 0.32) col = pick < 0.55 ? '#6a4cc4' : '#9078d8';
      else if (core > 0.75) col = pick < 0.34 ? '#e4fff6' : pick < 0.7 ? '#7eecc4' : '#62d0c4';
      else if (pick < 0.3) col = '#7056c8';
      else if (pick < 0.62) col = '#3fbfae';
      else col = '#8adcb0';
      const a = alpha * (spec.gain || 1) * (0.18 + 0.82 * edge);
      const g = target.createLinearGradient(0, yt, 0, yb);
      g.addColorStop(0, A.rgba(col, 0));
      g.addColorStop(0.2, A.rgba(col, a * 0.06));
      g.addColorStop(0.45, A.rgba(col, a * 0.26));
      g.addColorStop(0.64, A.rgba(col, a * 0.5));
      g.addColorStop(0.8, A.rgba(col, a));
      g.addColorStop(0.93, A.rgba(col, a * 0.28));
      g.addColorStop(1, A.rgba(col, 0));
      target.fillStyle = g;
      target.beginPath();
      const steps = 16;
      const xs = new Array(steps + 1);
      const ys = new Array(steps + 1);
      const ws = new Array(steps + 1);
      for (let i = 0; i <= steps; i++) {
        const u = i / steps;
        const y = yt + (yb - yt) * u;
        const fold = Math.sin(u * 1.25 + phase) * sway * (0.2 + 0.8 * u);
        const fold2 = Math.sin(u * 2.6 + phase * 1.3) * sway * 0.16;
        const taper = Math.pow(Math.sin(u * Math.PI), 0.72);
        xs[i] = x + fold + fold2 + spec.lean * (y - yt) * 0.22;
        ys[i] = y;
        ws[i] = width * (0.22 + 0.78 * taper);
      }
      target.moveTo(xs[0] - ws[0] * 0.5, ys[0]);
      for (let i = 1; i <= steps; i++) target.lineTo(xs[i] - ws[i] * 0.5, ys[i]);
      for (let i = steps; i >= 0; i--) target.lineTo(xs[i] + ws[i] * 0.5, ys[i]);
      target.closePath();
      target.fill();
    }
    let s;
    for (s = 0; s < 7; s++) ribbon(170 + rnd() * 90, 0.05, 0);
    for (s = 0; s < 16; s++) ribbon(70 + rnd() * 50, 0.055, 0.25);
    for (s = 0; s < 18; s++) ribbon(28 + rnd() * 22, 0.06, 0.55);
    for (s = 0; s < 8; s++) ribbon(12 + rnd() * 10, 0.055, 1);
  }

  function iceCrack(ctx, x, y, len, seed) {
    const rnd = A.rng(seed);
    ctx.save();
    ctx.strokeStyle = 'rgba(186,214,208,0.11)';
    ctx.lineWidth = 1.15;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(x, y);
    let px = x, py = y;
    const parts = 5 + ((rnd() * 4) | 0);
    for (let i = 0; i < parts; i++) {
      px += (len / parts) * (0.65 + rnd() * 0.7);
      py += (rnd() - 0.5) * 16;
      ctx.lineTo(px, py);
    }
    ctx.stroke();
    ctx.globalAlpha = 0.7;
    ctx.beginPath();
    ctx.moveTo(px - len * 0.25, py);
    ctx.lineTo(px - len * 0.25 + (rnd() - 0.3) * 50, py + (rnd() - 0.5) * 22);
    ctx.stroke();
    ctx.restore();
  }

  // —— Polarlicht: weiche Vorhänge über einem zugefrorenen See ——
  A.scene('bg-polarlicht', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(245), r = A.rng(245);
    const mx = 1500, my = 214;
    bands(ctx, W, [
      [0, '#070910'],
      [260, '#0c1420'],
      [620, '#101c2a'],
      [H, '#12202c', H]
    ]);
    const shore = crestFrom(W, [
      [0, 820], [280, 860], [640, 900], [1000, 930], [1280, 944],
      [1600, 928], [1960, 890], [2280, 850], [2560, 810]
    ], n, 3.3);
    starfield(ctx, W, 900, r, 720, mx, my);
    const groups = [
      { x: 520, spread: 720, base: 790, h: 880, phase: 0.5, lean: -0.035, gain: 0.72, sway: 1 },
      { x: 780, spread: 340, base: 830, h: 600, phase: 1.9, lean: 0.02, gain: 0.58, sway: 0.85 },
      { x: 1960, spread: 700, base: 770, h: 900, phase: 1.15, lean: 0.04, gain: 0.76, sway: 1.08 },
      { x: 2260, spread: 300, base: 820, h: 540, phase: 2.7, lean: -0.02, gain: 0.48, sway: 0.8 }
    ];
    const skyVeil = A.layer(W, H);
    for (let i = 0; i < groups.length; i++) veil(skyVeil, groups[i], r);
    const soft = A.layer(W, H);
    soft.filter = 'blur(14px)';
    soft.drawImage(skyVeil.canvas, 0, 0);
    ctx.save();
    ctx.globalCompositeOperation = 'screen';
    ctx.drawImage(soft.canvas, 0, 0);
    ctx.restore();
    ctx.save();
    A.fillRidge(ctx, shore, H, null);
    ctx.clip();
    A.vgrad(ctx, 0, 800, W, H - 800, [[0, '#102830'], [0.3, '#0c1c24'], [0.65, '#09141a'], [1, '#070c10']]);
    ctx.save();
    ctx.globalCompositeOperation = 'screen';
    for (let i = 0; i < 46; i++) {
      const v = i / 46;
      const srcY = 740 - v * 500;
      const destY = 900 + v * 440;
      const ox = Math.sin(v * 7.4 + 0.4) * (5 + v * 26) + Math.sin(v * 19) * 2.5;
      ctx.globalAlpha = 0.14 * Math.pow(1 - v, 1.05);
      ctx.drawImage(soft.canvas, 0, srcY, W, 16, ox, destY, W, 20 + v * 14);
    }
    ctx.restore();
    iceCrack(ctx, 180, 1040, 380, 11);
    iceCrack(ctx, 520, 1180, 320, 19);
    iceCrack(ctx, 980, 1120, 240, 23);
    iceCrack(ctx, 1400, 1260, 300, 29);
    iceCrack(ctx, 1760, 1100, 260, 37);
    iceCrack(ctx, 2100, 1220, 340, 41);
    iceCrack(ctx, 680, 1340, 200, 53);
    ctx.restore();
    stand(ctx, shore, '#070b10', r, { min: 40, max: 260, quiet: 0.42, skip: 0.32, fade: true, space: 0.86 });
    ctx.fillStyle = '#06080c';
    ctx.beginPath();
    ctx.moveTo(0, H + 4);
    ctx.lineTo(0, 1260);
    ctx.quadraticCurveTo(220, 1160, 480, 1320);
    ctx.quadraticCurveTo(860, 1420, 1280, 1460);
    ctx.quadraticCurveTo(1760, 1400, 2100, 1280);
    ctx.quadraticCurveTo(2340, 1180, W, 1230);
    ctx.lineTo(W, H + 4);
    ctx.fill();
    pineTree(ctx, 30, 1580, 740, '#05070a', r, { spread: 0.7 });
    pineTree(ctx, 230, 1540, 460, '#05070a', r, { spread: 0.64 });
    pineTree(ctx, 2520, 1600, 780, '#05070a', r, { spread: 0.72 });
    pineTree(ctx, 2300, 1520, 440, '#05070a', r, { spread: 0.62 });
    A.radial(ctx, mx, my, 240, 'rgba(184,255,228,0.2)', 'rgba(184,255,228,0)', 'screen', 1.05, 0.72);
    A.punkt(ctx, mx, my, 28, '#e9fff8', '#b7ffe6', 140);
    A.vignette(ctx, W, H, '#04060a', 0.4);
    A.dither(ctx, W, H, { amp: 11, grain: 2.2, seed: 245 });
  });


  // —— Sternenzelt ——
  A.scene('bg-sternenzelt', GW, GH, function (ctx, W, H) {
    const n = A.makeNoise(256), r = A.rng(256);
    bands(ctx, W, [[0, '#070814', 420], [420, '#101628', 820], [820, '#1a2036', H]]);
    const im = ctx.getImageData(0, 0, W, H), d = im.data;
    const cool = A.rgb('#8ea0dc'), warm = A.rgb('#f0e2c8');
    for (let y = 0; y < H * 0.5; y += 2) {
      for (let x = 0; x < W; x += 2) {
        const u = x / W;
        const cy = 70 + u * 300 + 28 * Math.sin(u * 7.4);
        const width = 46 + 28 * (n.fbm(x / 520, 1.4, 2) * 0.5 + 0.5);
        const dist = y - cy;
        let core = Math.exp(-(dist * dist) / (width * width));
        const halo = Math.exp(-(dist * dist) / ((width * 3.1) * (width * 3.1))) * 0.38;
        const lane1 = Math.exp(-Math.pow((dist - 14 * Math.sin(u * 9)) / 22, 2));
        const lane2 = Math.exp(-Math.pow((dist + 26 + 12 * Math.sin(u * 5 + 1.2)) / 16, 2));
        let band = (core + halo) * (1 - 0.55 * lane1 - 0.38 * lane2);
        const clump = n.fbm(x / 160, y / 80, 3) * 0.5 + 0.5;
        band *= 0.45 + clump * 0.85;
        if (band < 0.035) continue;
        const tone = A.clamp(0.2 + core * 0.8, 0, 1);
        const cr = cool[0] + (warm[0] - cool[0]) * tone;
        const cg = cool[1] + (warm[1] - cool[1]) * tone;
        const cb = cool[2] + (warm[2] - cool[2]) * tone;
        const a = Math.min(0.58, band * 0.8);
        for (let oy = 0; oy < 2 && y + oy < H; oy++) for (let ox = 0; ox < 2 && x + ox < W; ox++) {
          const i = ((y + oy) * W + (x + ox)) * 4;
          d[i] = d[i] * (1 - a) + cr * a;
          d[i + 1] = d[i + 1] * (1 - a) + cg * a;
          d[i + 2] = d[i + 2] * (1 - a) + cb * a;
        }
      }
    }
    ctx.putImageData(im, 0, 0);
    stars(ctx, W, H, r, 3600, H * 0.86);
    for (let k = 0; k < 1800; k++) {
      const x = r() * W;
      const u = x / W;
      const cy = 70 + u * 300 + 28 * Math.sin(u * 7.4);
      const y = cy + (r() - 0.5) * 90;
      if (y < 4 || y > H * 0.55) continue;
      const mag = r();
      const s = mag > 0.92 ? 2.2 : 1.1;
      ctx.fillStyle = mag > 0.8 ? 'rgba(255,244,224,.85)' : 'rgba(214,224,255,.7)';
      ctx.fillRect(x, y, s, s);
    }
    const ridge = valley(W, H * 0.9, 90, 70, 380, n, 2.4);
    // Felsturm rechts, der die Laterne trägt
    for (let x = 0; x <= W; x++) {
      const u = (x - W * 0.74) / (W * 0.04);
      const u2 = (x - W * 0.16) / (W * 0.08);
      ridge[x] -= 160 * Math.exp(-u * u) + 80 * Math.exp(-u2 * u2);
    }
    A.fillRidge(ctx, ridge, H, '#12141e');
    A.crest(ctx, ridge, 'rgba(230,210,180,.22)', 1.4);
    // Vordergrundfelsen
    ctx.fillStyle = '#07080e';
    ctx.beginPath();
    ctx.moveTo(-20, H + 10);
    ctx.lineTo(-20, 1180);
    ctx.quadraticCurveTo(180, 1100, 420, 1280);
    ctx.lineTo(260, H + 10);
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(W + 20, H + 10);
    ctx.lineTo(W + 20, 1120);
    ctx.quadraticCurveTo(2300, 1040, 2080, 1240);
    ctx.lineTo(2360, H + 10);
    ctx.fill();
    const lx = 1960, ly = ridge[1960] - 28;
    A.radial(ctx, lx, ly + 40, 220, 'rgba(255,214,160,.2)', 'rgba(255,214,160,0)', 'screen');
    ctx.strokeStyle = 'rgba(255,220,180,.65)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(lx + 8, ly + 10);
    ctx.lineTo(lx + 24, ridge[Math.round(lx + 24)] - 2);
    ctx.stroke();
    A.punkt(ctx, lx, ly, 28, '#fff3dd', '#ffd8a8', 180);
    A.vignette(ctx, W, H, '#04050a', 0.45);
    A.dither(ctx, W, H, { amp: 11, grain: 2.2, seed: 256 });
  });

  // —— Megalith ——
  A.scene('bg-megalith', GW, GH, function (ctx, W, H) {
    const r = A.rng(301);
    const lx = 1960, ly = 280;
    bands(ctx, W, [[0, '#141820', 240], [240, '#1e2834', 460], [460, '#3a342e', 700], [700, '#6a5648', 980], [980, '#2a241e', H]]);
    soften(ctx, W, 240, 60, '#1e2834', 20000, r);
    soften(ctx, W, 460, 70, '#3a342e', 24000, r);
    soften(ctx, W, 700, 80, '#6a5648', 26000, r);
    cloudBank(ctx, W, 20, 160, '#121820', 0.72, 51);
    cloudBank(ctx, W, 70, 130, '#2a313c', 0.55, 63);
    // warme Unterseite der Wolken, nur rechts beim Licht
    A.stipple(ctx, W * 0.45, 40, W * 0.55, 220, 30000, 'rgba(224,186,140,.18)', function (x, y) {
      return A.clamp((x - W * 0.5) / (W * 0.4), 0, 1) * A.clamp(1 - y / 260, 0, 1);
    }, r, 1.8);
    A.radial(ctx, lx, ly, 820, 'rgba(232,196,150,.2)', 'rgba(232,196,150,0)', 'screen', 1.3, 0.7);
    // Boden und Dunst
    A.band(ctx, W, 990, 240, '#d2c0a4', 0.26);
    // Lichtpfützen
    [[1500, 1120, 260], [1760, 1080, 180], [2140, 1180, 320]].forEach(function (p) {
      A.radial(ctx, p[0], p[1], p[2], 'rgba(232,196,150,.16)', 'rgba(232,196,150,0)', 'screen', 1.6, 0.45);
    });
    function pal(t) {
      return {
        shade: A.mix('#1a2430', '#4a4038', t),
        lit: A.mix('#e2cbb0', '#6a5c50', t),
        line: 'rgba(8,12,18,.4)',
        lineLit: 'rgba(80,50,24,.25)',
        rim: A.rgba('#f0e2cc', 0.85 * (1 - t * 0.65)),
        top: A.mix('#f0e2cc', '#6a5c50', t)
      };
    }
    // Ferne, klein, in den Dunst gesetzt, außerhalb der ruhigen Mitte
    [[540, 1040, 34, 90, 0.02], [2140, 1020, 40, 110, -0.03], [1880, 1060, 26, 70, 0.04]].forEach(function (s) {
      stone(ctx, { x: s[0], y: s[1], w: s[2], h: s[3], lean: s[4], gap: 14, dots: 400 }, pal(0.62), r);
    });
    // Mittelgrund, verschiedene Höhen entlang des Wegs
    stone(ctx, { x: 250, y: 1240, w: 78, h: 340, lean: -0.03, notch: 10, gap: 20, dots: 2200 }, pal(0.28), r);
    stone(ctx, { x: 430, y: 1200, w: 54, h: 230, lean: 0.05, gap: 16, dots: 1400 }, pal(0.34), r);
    stone(ctx, { x: 1680, y: 1180, w: 64, h: 250, lean: -0.02, notch: 18, gap: 18, dots: 1600 }, pal(0.3), r);
    const a = stone(ctx, { x: 1860, y: 1220, w: 70, h: 320, lean: 0.04, gap: 20, dots: 2000 }, pal(0.18), r);
    const b = stone(ctx, { x: 2060, y: 1190, w: 76, h: 280, lean: -0.05, notch: 8, gap: 18, dots: 1800 }, pal(0.16), r);
    lintel(ctx, 1888, a.top + 8, 250, 54, pal(0.12));
    // Vordergrund, angeschnitten
    stone(ctx, { x: -70, y: 1500, w: 210, h: 860, lean: -0.04, notch: 20, gap: 26, dots: 5000, split: 0.36 }, pal(0), r);
    stone(ctx, { x: 150, y: 1480, w: 130, h: 560, lean: 0.06, gap: 22, dots: 2800 }, pal(0.05), r);
    stone(ctx, { x: 2280, y: 1520, w: 280, h: 980, lean: 0.05, notch: -10, gap: 28, dots: 6000, split: 0.34 }, pal(0), r);
    stone(ctx, { x: 2100, y: 1460, w: 150, h: 520, lean: -0.04, gap: 20, dots: 2400 }, pal(0.08), r);
    // Wegplatten, niedrig, zum Dunst hin auslaufend
    A.volumetric(ctx, lx, ly, '#e4c49a', { r: 860, a: 0.2, shafts: 0, sy: 0.75, sx: 1.2 });
    A.punkt(ctx, lx, ly, 40, '#f4ead8', '#e4c49a', 220);
    A.stipple(ctx, 0, 1000, W, 440, 80000, 'rgba(12,10,8,.4)', function (x, y) {
      const e = Math.abs(x / W - 0.5);
      return A.clamp(e * 1.3, 0.05, 0.8) * A.clamp((y - 1000) / 300, 0, 1);
    }, r, 1.6);
    A.vignette(ctx, W, H, '#0c0e12', 0.4);
    A.dither(ctx, W, H, { amp: 12, grain: 2.3, seed: 301 });
    void b;
  });

  // —— Nachthorizont ——
  A.scene('bg-skyline', GW, GH, function (ctx, W, H) {
    const r = A.rng(312);
    const base = 980;
    const lx = 2080, ly = 240;
    bands(ctx, W, [[0, '#0c1018', 280], [280, '#141c28', 640], [640, '#243044', 880], [880, '#5a4636', base], [base, '#10141a', H]]);
    soften(ctx, W, 300, 60, '#18202c', 18000, r);
    soften(ctx, W, 560, 70, '#2a3848', 20000, r);
    soften(ctx, W, 820, 80, '#6a5844', 22000, r);
    cloudBank(ctx, W, 16, 150, '#10141c', 0.7, 71);
    cloudBank(ctx, W, 60, 100, '#1c2632', 0.45, 83);
    A.radial(ctx, lx, ly, 640, 'rgba(210,220,230,.16)', 'rgba(210,220,230,0)', 'screen');
    function tower(x, w, h, col) {
      ctx.fillStyle = A.shade(col, 0.62);
      ctx.fillRect(x, base - h, w * 0.28, h + 30);
      ctx.fillStyle = col;
      ctx.fillRect(x + w * 0.28, base - h, w * 0.72, h + 30);
      if (h > 240) {
        ctx.fillStyle = A.shade(col, 0.8);
        ctx.fillRect(x + w * 0.34, base - h - h * 0.09, w * 0.42, h * 0.09 + 2);
      }
      ctx.fillStyle = 'rgba(214,222,230,.62)';
      ctx.fillRect(x + w - Math.max(2.5, w * 0.045), base - h, Math.max(2.5, w * 0.045), h);
    }
    tower(40, 150, 380, '#121820');
    tower(200, 90, 240, '#161c26');
    tower(300, 70, 160, '#1a222c');
    tower(390, 120, 300, '#141a24');
    // Antenne
    ctx.fillStyle = '#121820';
    ctx.fillRect(108, base - 470, 6, 90);
    tower(1760, 130, 260, '#1a222c');
    tower(1900, 160, 420, '#121820');
    tower(2070, 80, 220, '#182028');
    tower(2160, 180, 520, '#10161e');
    tower(2350, 110, 280, '#161c24');
    ctx.fillRect(2420, base - 360, 5, 80);
    // Vordergrundbrüstung
    ctx.fillStyle = '#161c26';
    ctx.beginPath();
    ctx.moveTo(0, 1180);
    ctx.lineTo(0, H);
    ctx.lineTo(W, H);
    ctx.lineTo(W, 1220);
    ctx.lineTo(2100, 1160);
    ctx.lineTo(1600, 1240);
    ctx.lineTo(700, 1190);
    ctx.lineTo(180, 1280);
    ctx.fill();
    // Wasserturm links, Schlot rechts
    ctx.fillRect(70, 1040, 36, 180);
    ctx.beginPath(); ctx.ellipse(88, 1040, 28, 12, 0, 0, 7); ctx.fill();
    ctx.fillRect(2360, 1080, 28, 160);
    ctx.fillRect(2344, 1064, 60, 16);
    A.band(ctx, W, base - 10, 90, '#8a98a4', 0.1);
    A.punkt(ctx, lx, ly, 30, '#f4f6f8', '#d5dde6', 180);
    A.vignette(ctx, W, H, '#06080c', 0.4);
    A.dither(ctx, W, H, { amp: 12, grain: 2.2, seed: 312 });
  });

  // —— Gezeitentümpel ——
  A.scene('bg-tidepool', GW, GH, function (ctx, W, H) {
    const r = A.rng(323);
    const lx = 2140, ly = 220;
    bands(ctx, W, [[0, '#102022', 260], [260, '#1a3334', 460], [460, '#3d5348', 640], [640, '#1a3030', H]]);
    soften(ctx, W, 260, 50, '#1a3334', 18000, r);
    soften(ctx, W, 460, 60, '#3d5348', 20000, r);
    cloudBank(ctx, W, 10, 120, '#0e1c1e', 0.55, 91);
    A.radial(ctx, lx, ly, 560, 'rgba(232,236,226,.18)', 'rgba(232,236,226,0)', 'screen');
    // Ferne Klippe
    const cliff = valley(W, 560, 40, 50, 700, A.makeNoise(19), 2);
    A.fillRidge(ctx, cliff, H, '#1e3838');
    A.crest(ctx, cliff, 'rgba(200,210,200,.2)', 1.3);
    // Felsplatte: Mitte tief und ruhig, die Ränder steigen in den Rahmen.
    const rock = new Float32Array(W + 1);
    const rn = A.makeNoise(17);
    for (let x = 0; x <= W; x++) {
      const e = Math.pow(Math.abs(x / W - 0.5) * 2, 1.35);
      const jag = Math.abs(rn.fbm(x / 260, 2.2, 3));
      rock[x] = 1000 - 260 * e - 70 * jag * (0.25 + e);
    }
    A.fillRidge(ctx, rock, H, '#142422');
    ctx.save();
    A.fillRidge(ctx, rock, H, null);
    ctx.clip();
    A.radial(ctx, lx, 1040, 640, 'rgba(210,200,170,.16)', 'rgba(210,200,170,0)', 'screen', 1.3, 0.55);
    ctx.strokeStyle = 'rgba(8,18,18,.4)';
    ctx.lineWidth = 1.2;
    ctx.beginPath();
    for (let k = 1; k <= 5; k++) {
      for (let x = 0; x <= W; x += 5) {
        const y = rock[x] + 18 * k + 4 * Math.sin(x * 0.017 + k);
        if (x > W * 0.28 && x < W * 0.72) { ctx.moveTo(x, y); continue; }
        if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      }
    }
    ctx.stroke();
    ctx.restore();
    // Tümpel nur außerhalb der Kartenmitte
    const leftPool = [[-80, 1240], [40, 1080], [220, 1160], [300, 1320], [120, 1420], [-40, 1360]];
    const rightPool = [[1900, 1120], [2060, 1000], [2260, 1080], [2380, 1220], [2200, 1360], [1980, 1320], [1880, 1200]];
    blob(ctx, leftPool, '#0c1e22');
    ctx.fillStyle = '#101c1c';
    ctx.beginPath();
    ctx.moveTo(-20, 1060);
    ctx.quadraticCurveTo(180, 1160, 60, 1320);
    ctx.lineTo(-20, 1320);
    ctx.fill();
    blob(ctx, rightPool, '#10282c');
    ctx.fillStyle = '#101c1c';
    ctx.beginPath();
    ctx.moveTo(W + 20, 960);
    ctx.quadraticCurveTo(2280, 1080, 2460, 1240);
    ctx.lineTo(W + 20, 1260);
    ctx.fill();
    ctx.save();
    blob(ctx, rightPool, null);
    ctx.clip();
    for (let k = 0; k < 6; k++) {
      const t = k / 6;
      const y = 990 + t * 160;
      const w = 10 + t * 26;
      ctx.fillStyle = 'rgba(236,240,232,' + (0.16 * (1 - t)) + ')';
      ctx.fillRect(2140 - w / 2 + Math.sin(k) * 8, y, w, 2);
    }
    ctx.restore();
    ctx.fillStyle = '#0c1818';
    ctx.beginPath();
    ctx.moveTo(0, 900); ctx.quadraticCurveTo(260, 1080, 40, H + 10); ctx.lineTo(0, H + 10); ctx.fill();
    ctx.beginPath();
    ctx.moveTo(W, 820); ctx.quadraticCurveTo(2140, 1040, W - 20, H + 10); ctx.lineTo(W, H + 10); ctx.fill();
    ctx.beginPath();
    ctx.moveTo(0, 160); ctx.quadraticCurveTo(220, 240, 40, 460); ctx.lineTo(0, 460); ctx.fill();
    A.stipple(ctx, 0, 860, 620, 580, 24000, 'rgba(8,16,16,.55)', function (x) { return x < 520 ? 0.55 : 0.15; }, r, 1.7);
    A.stipple(ctx, 1900, 820, 660, 620, 24000, 'rgba(8,16,16,.5)', function () { return 0.5; }, r, 1.7);
    A.punkt(ctx, lx, ly, 28, '#f2f6f2', '#d5e0d4', 170);
    A.vignette(ctx, W, H, '#081210', 0.38);
    A.dither(ctx, W, H, { amp: 12, grain: 2.2, seed: 323 });
  });
})();
