#!/usr/bin/env python3
"""Ruheprobe der Hintergründe.

Schreibt .art/vorschau.html und .art/probe/<key>.png
Jede Probe zeigt: Bild, Graustufen, und drei Abdunklungen (35/50/55 %) mit vier Flächen.
"""
import html
from pathlib import Path

from PIL import Image, ImageDraw, ImageOps

HERE = Path(__file__).resolve().parent
BG = HERE.parent / "public" / "defaults" / "backgrounds"
PROBE = HERE / "probe"
KEYS = [
    "werft", "papier", "hafen", "kiefer", "daemmerung",
    "polarlicht", "sternenzelt", "megalith", "skyline", "tidepool",
]
LIGHT = {"papier"}


def banding(im):
    px = im.load()
    w, h = im.size
    hits = 0
    worst = 0
    for x in range(8, w, 48):
        prev = px[x, 0]
        run = 1
        for y in range(1, h):
            p = px[x, y]
            d = abs(p[0] - prev[0]) + abs(p[1] - prev[1]) + abs(p[2] - prev[2])
            if d == 0:
                run += 1
            else:
                if run >= 20 and 3 <= d <= 12:
                    hits += 1
                    if run > worst:
                        worst = run
                run = 1
                prev = p
    return hits, worst


def cards(im, dim):
    w, h = im.size
    base = Image.blend(im, Image.new("RGB", im.size, (0, 0, 0)), dim)
    ov = Image.new("RGBA", im.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(ov)
    light = False
    panel = (246, 244, 240, 232)
    ink = (70, 64, 58, 150)
    line = (255, 255, 255, 40)
    # Vier grosse Flächen über der Bildmitte
    rects = [
        (int(w * 0.03), int(h * 0.06), int(w * 0.20), int(h * 0.94)),
        (int(w * 0.24), int(h * 0.10), int(w * 0.58), int(h * 0.42)),
        (int(w * 0.61), int(h * 0.10), int(w * 0.96), int(h * 0.42)),
        (int(w * 0.24), int(h * 0.50), int(w * 0.96), int(h * 0.90)),
    ]
    for a, b, c, e in rects:
        d.rounded_rectangle((a, b, c, e), 16, fill=panel, outline=line)
        y = b + 28
        x0 = a + 22
        width = c - a - 44
        while y < e - 24:
            d.rectangle((x0, y, x0 + int(width * (0.45 + (y % 5) * 0.08)), y + 8), fill=ink)
            y += 22
    # Rahmen des ruhigen Mittelbereichs, nur in der Probe
    d.rectangle((int(w * 0.3), int(h * 0.3), int(w * 0.7), int(h * 0.7)), outline=(255, 180, 60, 90))
    return Image.alpha_composite(base.convert("RGBA"), ov).convert("RGB")


def main():
    PROBE.mkdir(exist_ok=True)
    tw, th = 960, 540
    rows = []
    for key in KEYS:
        src = BG / (key + ".webp")
        im = Image.open(src).convert("RGB")
        hits, worst = banding(im)
        small = im.resize((tw, th), Image.LANCZOS)
        grey = ImageOps.grayscale(small).convert("RGB")
        dim50 = cards(small, 0.50)
        sheet = Image.new("RGB", (tw, th * 3 + 8), "#101010")
        sheet.paste(small, (0, 0))
        sheet.paste(grey, (0, th + 4))
        sheet.paste(dim50, (0, th * 2 + 8))
        sheet.save(PROBE / (key + ".png"), optimize=True)
        rows.append((key, hits, worst, src.stat().st_size))
        print("%-14s %4d KB  banding-steps %4d  longest-run %d" % (key, round(src.stat().st_size / 1024), hits, worst))

    # Drei Abdunklungen nebeneinander, für die HTML-Seite als eine Leiste
    parts = []
    page = [
        "<!doctype html><html lang=de><meta charset=utf-8><title>Ruheprobe</title>",
        "<style>body{margin:0;background:#111;color:#ddd;font:14px/1.4 system-ui}",
        "figure{margin:0 0 28px}img{width:100%;height:auto}figcaption{padding:8px 12px}</style><body>",
        "<h1 style='padding:16px'>Hintergründe – oben Bild, Mitte Grauwert, unten 50 % Schwarz und vier Flächen</h1>",
    ]
    for key, hits, worst, size in rows:
        rel = "probe/" + key + ".png"
        page.append("<figure><img src='%s' alt=''><figcaption>%s · %d KB · Stufen %d</figcaption></figure>" % (
            html.escape(rel), html.escape(key), round(size / 1024), hits))
    # Varianten 35 / 50 / 55 für das erste Bildformat, alle Keys untereinander als dreier
    page.append("<h2 style='padding:8px 12px'>Drei Abdunklungen: 35 %, 50 %, 55 %</h2>")
    for key, hits, worst, size in rows:
        im = Image.open(BG / (key + ".webp")).convert("RGB").resize((640, 360), Image.LANCZOS)
        strip = Image.new("RGB", (640 * 3 + 8, 360), "#000")
        for i, dim in enumerate((0.35, 0.50, 0.55)):
            strip.paste(cards(im, dim), (i * (640 + 4), 0))
        dest = PROBE / (key + "-dim.png")
        strip.save(dest, optimize=True)
        page.append("<figure><img src='probe/%s-dim.png' alt=''><figcaption>%s</figcaption></figure>" % (html.escape(key), html.escape(key)))
    page.append("</body></html>")
    (HERE / "vorschau.html").write_text("\n".join(page), encoding="utf-8")
    print("vorschau", HERE / "vorschau.html")


if __name__ == "__main__":
    main()
