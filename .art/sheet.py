#!/usr/bin/env python3
"""Kontaktbogen zur Sichtpruefung: python sheet.py <ziel.png> <modus> <bild> [<bild> ...]

modus:
  voll    Bilder verkleinert nebeneinander
  karte   Banner im Zielzuschnitt: schmal 330x96 und breit 1200x160, jeweils nach unten ausgeblendet
  dunkel  Hintergrund mit 45 % Abdunklung in der Variantenfarbe und aufgelegten Flaechen
"""
import sys
from pathlib import Path

from PIL import Image, ImageDraw


def cover(im, w, h, pos=0.30):
    s = max(w / im.width, h / im.height)
    r = im.resize((round(im.width * s), round(im.height * s)), Image.LANCZOS)
    x = (r.width - w) // 2
    y = round((r.height - h) * pos)
    return r.crop((x, y, x + w, y + h))


def fade(im, color):
    w, h = im.size
    mask = Image.new("L", (1, h))
    for y in range(h):
        t = max(0.0, (y / h - 0.35) / 0.65)
        mask.putpixel((0, y), round(255 * t))
    return Image.composite(Image.new("RGB", (w, h), color), im, mask.resize((w, h)))


def main():
    dest, mode, files = sys.argv[1], sys.argv[2], sys.argv[3:]
    ims = [Image.open(f).convert("RGB") for f in files]
    if mode == "voll":
        tw = 780
        th = round(tw * ims[0].height / ims[0].width)
        cols = 2
        rows = (len(ims) + 1) // 2
        sheet = Image.new("RGB", (cols * (tw + 8), rows * (th + 8)), "#202020")
        for k, im in enumerate(ims):
            sheet.paste(im.resize((tw, th), Image.LANCZOS), ((k % cols) * (tw + 8), (k // cols) * (th + 8)))
    elif mode == "karte":
        surf = "#172a33"
        sheet = Image.new("RGB", (1560, len(ims) * 176), "#0f1c23")
        for k, im in enumerate(ims):
            y = k * 176
            sheet.paste(fade(cover(im, 330, 96), surf), (8, y + 8))
            sheet.paste(fade(cover(im, 1200, 160), surf), (350, y + 8))
    else:
        tints = dict(werft="#0f1c23", papier="#e9ecef", hafen="#190f24", kiefer="#121f19")
        tw, th = 780, 439
        sheet = Image.new("RGB", (2 * (tw + 8), ((len(ims) + 1) // 2) * (th + 8)), "#202020")
        for k, (im, f) in enumerate(zip(ims, files)):
            name = Path(f).stem.replace("bg-", "")
            tint = tints.get(name, "#0f1c23")
            light = name == "papier"
            small = im.resize((tw, th), Image.LANCZOS)
            small = Image.blend(small, Image.new("RGB", (tw, th), tint), 0.45)
            ov = Image.new("RGBA", (tw, th), (0, 0, 0, 0))
            d = ImageDraw.Draw(ov)
            panel = (248, 249, 250, 235) if light else tuple(min(255, c + 12) for c in Image.new("RGB", (1, 1), tint).getpixel((0, 0))) + (235,)
            d.rectangle((0, 0, 120, th), fill=panel)
            for i in range(3):
                for j in range(2):
                    d.rounded_rectangle((150 + i * 205, 70 + j * 170, 150 + i * 205 + 190, 70 + j * 170 + 150), 6, fill=panel)
            sheet.paste(Image.alpha_composite(small.convert("RGBA"), ov).convert("RGB"), ((k % 2) * (tw + 8), (k // 2) * (th + 8)))
    sheet.save(dest)


if __name__ == "__main__":
    main()
