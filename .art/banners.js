/* Flächige Banner (1600x520). Regel: Horizont und Hauptmotiv in der oberen Hälfte,
   Lichtquelle = Techtyl-Punkt rechts der Mitte, unten ruhige dunklere Fläche (wird ausgeblendet). */
(function () {
  const A = window.A, BW = 1600, BH = 520;

  // Hang-Schattierung als Punktraster: dunkel, wo der Hang vom Licht (rechts) abgewandt ist
  function shadeRidge(ctx, ys, H, color, r, n, strength, depth) {
    ctx.save(); A.fillRidge(ctx, ys, H, null); ctx.clip();
    A.stipple(ctx, 0, 0, ys.length, H, n, color, function (x, y) {
      const xi = Math.min(ys.length - 9, Math.max(8, x | 0));
      const slope = (ys[xi - 8] - ys[xi + 8]) / 16; // >0: steigt nach rechts an -> lichtabgewandt
      const d = (y - ys[xi]) / (depth || 120);
      return A.clamp(slope * 2.2 + 0.25, 0, 1) * strength * A.clamp(1.1 - d, 0, 1) + d * 0.12 * strength;
    }, r, 1.4);
    ctx.restore();
  }
  function crest(ctx, ys, color, lw) {
    ctx.strokeStyle = color; ctx.lineWidth = lw || 1.5; ctx.beginPath();
    for (let x = 0; x < ys.length; x++) ctx.lineTo(x, ys[x] + 0.5);
    ctx.stroke();
  }
  function finish(ctx, W, H, vig, amt, seed) {
    A.vignette(ctx, W, H, vig || '#000000', 0.3);
    A.grain(ctx, W, H, amt === undefined ? 9 : amt, seed || 3);
  }
  A.shadeRidge = shadeRidge; A.crest = crest; A.finish = finish;

  // Gittermast-Silhouette
  function mast(ctx, x, y, h, w, color) {
    ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(x - w, y); ctx.lineTo(x - w * 0.25, y - h); ctx.moveTo(x + w, y); ctx.lineTo(x + w * 0.25, y - h);
    const k = 6;
    for (let s = 0; s < k; s++) {
      const t0 = s / k, t1 = (s + 1) / k;
      const w0 = w * (1 - 0.75 * t0), w1 = w * (1 - 0.75 * t1);
      ctx.moveTo(x - w0, y - h * t0); ctx.lineTo(x + w1, y - h * t1);
      ctx.moveTo(x + w0, y - h * t0); ctx.lineTo(x - w1, y - h * t1);
      ctx.moveTo(x - w1, y - h * t1); ctx.lineTo(x + w1, y - h * t1);
    }
    ctx.stroke();
    ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(x - w * 0.9, y - h * 1.0); ctx.lineTo(x + w * 0.9, y - h * 1.0); ctx.stroke();
  }
  A.mast = mast;

  // Rust: Dünen in Rosttönen, tiefe Sonne, verlassene Technik
  A.scene('bn-rust', BW, BH, function (ctx, W, H) {
    const n = A.makeNoise(31), r = A.rng(31);
    A.vgrad(ctx, 0, 0, W, H, [[0, '#231426'], [0.2, '#55232f'], [0.38, '#a8482d'], [0.52, '#e3924e'], [1, '#e9a35c']]);
    // Dunstbänder
    for (let k = 0; k < 9; k++) {
      const y = 60 + k * 21 + r() * 8, x = r() * W, w = 300 + r() * 700;
      ctx.fillStyle = A.rgba(k < 4 ? '#7a2f33' : '#f0b070', 0.16);
      A.rrect(ctx, x - w / 2, y, w, 3 + r() * 5, 3); ctx.fill();
    }
    A.punkt(ctx, 1128, 176, 60, '#ffe6b8', '#ffb262', 300);
    // ferne Tafelberge
    const mesa = new Float32Array(W + 1);
    for (let x = 0; x <= W; x++) {
      const m = n.fbm(x / 260 + 9, 2.2, 2);
      mesa[x] = 262 - 46 * A.smooth(0.02, 0.1, m) - 22 * A.smooth(0.22, 0.27, m) - 3 * n(x / 30, 5);
    }
    A.fillRidge(ctx, mesa, H, '#b9603a');
    A.vgrad(ctx, 0, 200, W, 80, [[0, 'rgba(233,163,92,0)'], [1, 'rgba(233,163,92,.55)']]);
    // Dünen
    const pal = [['#c9693a', '#7e3327'], ['#b4552f', '#6a2a25'], ['#963f28', '#4f1f22'], ['#6f2c22', '#2f141c']];
    pal.forEach(function (p, k) {
      const ys = A.ridge(W, 268 + k * 46, 30 + k * 16, 520 - k * 60, n, 3.3 * (k + 1), 3);
      ctx.save(); A.fillRidge(ctx, ys, H, null); ctx.clip();
      A.vgrad(ctx, 0, 220 + k * 40, W, 240, [[0, p[0]], [1, p[1]]]);
      ctx.restore();
      shadeRidge(ctx, ys, H, A.rgba(p[1], 0.9), r, 150000, 0.75, 110);
      crest(ctx, ys, A.rgba('#ffc98a', 0.55 - k * 0.1), 1.5);
      if (k === 1) {
        const yb = function (x) { return ys[x] + 3; };
        mast(ctx, 356, yb(356), 150, 26, '#2a1220');
        ctx.strokeStyle = '#2a1220'; ctx.lineWidth = 3; ctx.lineCap = 'round';
        [[196, 62, -0.06], [540, 70, 0.1], [700, 58, -0.03]].forEach(function (q) {
          ctx.beginPath(); ctx.moveTo(q[0], yb(q[0])); ctx.lineTo(q[0] + q[1] * q[2], yb(q[0]) - q[1]); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(q[0] + q[1] * q[2] - 9, yb(q[0]) - q[1] + 8); ctx.lineTo(q[0] + q[1] * q[2] + 9, yb(q[0]) - q[1] + 6); ctx.stroke();
        });
        ctx.lineWidth = 1.2; ctx.beginPath();
        ctx.moveTo(-10, yb(10) - 48); ctx.quadraticCurveTo(100, yb(100) - 30, 192, yb(196) - 56);
        ctx.quadraticCurveTo(280, yb(280) - 62, 356, yb(356) - 150);
        ctx.quadraticCurveTo(450, yb(450) - 70, 547, yb(540) - 64);
        ctx.quadraticCurveTo(630, yb(630) - 40, 698, yb(700) - 52); ctx.stroke();
      }
    });
    finish(ctx, W, H, '#1a0a14', 9, 31);
  });
})();
