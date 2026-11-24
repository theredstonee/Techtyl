#!/usr/bin/env python3
"""Rendert die Techtyl-Bildserie: art.html?s=<schluessel> -> PNG (Headless-Chrome) -> WebP (Pillow).

Aufruf (im Ordner .art):
    python render.py                 alle Szenen
    python render.py bn-paper bg-*   einzelne Szenen (Platzhalter * erlaubt)
    python render.py --png DIR ...   zusaetzlich die PNG-Zwischenstaende nach DIR legen

bn-<name>  -> public/defaults/banners/<name>.webp      (1600x520, Ziel < 120 KB)
bg-<name>  -> public/defaults/backgrounds/<name>.webp  (2560x1440, Ziel < 450 KB)

Chrome laeuft als eigene Headless-Instanz mit eigenem Profil; der normale Browser bleibt unberuehrt.
"""
import fnmatch
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / "public" / "defaults"
CHROME = os.environ.get("CHROME", r"C:\Program Files\Google\Chrome\Application\chrome.exe")
PROFILE = Path(tempfile.gettempdir()) / "techtyl-art-grok-art3"
LIMIT = dict(bn=125_000, bg=450 * 1024)
START_Q = dict(bn=86, bg=90)
# Zeichenfolgen, die Blueprint beim Bauen ersetzt - duerfen in keiner Datei stehen
WORDS = "identifier name author version root webroot fs mode target engine random timestamp viewcontext appcontext".split()
TOKENS = [("\x7b" + w + "\x7d").encode() for w in WORDS]


def scenes():
    found = []
    for js in sorted(HERE.glob("*.js")):
        for m in re.finditer(r"A\.scene\('([a-z0-9-]+)',\s*(\w+),\s*(\w+)", js.read_text(encoding="utf-8")):
            found.append(m.group(1))
    return found


def size_of(key):
    return (1600, 520) if key.startswith("bn-") else (2560, 1440)


def shoot(key, png):
    w, h = size_of(key)
    url = (HERE / "art.html").as_uri() + "?s=" + key
    cmd = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--force-device-scale-factor=1",
           "--window-size=%d,%d" % (w, h), "--user-data-dir=" + str(PROFILE), "--no-first-run",
           "--virtual-time-budget=40000", "--screenshot=" + str(png), url]
    subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=180)


def encode(key, png):
    kind, name = key[:2], key[3:]
    dest = OUT / ("banners" if kind == "bn" else "backgrounds") / (name + ".webp")
    dest.parent.mkdir(parents=True, exist_ok=True)
    im = Image.open(png).convert("RGB")
    assert im.size == size_of(key), "falsche Groesse %s fuer %s" % (im.size, key)
    q = START_Q[kind]
    hit = []
    while True:
        im.save(dest, "WEBP", quality=q, method=6)
        data = dest.read_bytes()
        hit = [t for t in TOKENS if t in data]
        if (len(data) <= LIMIT[kind] and not hit) or q <= 40:
            break
        q -= 1 if hit and len(data) <= LIMIT[kind] else 3
    return dest, len(data), q, hit


def main(argv):
    keep = None
    if argv and argv[0] == "--png":
        keep = Path(argv[1]); keep.mkdir(parents=True, exist_ok=True); argv = argv[2:]
    keys = scenes()
    if argv:
        keys = [k for k in keys if any(fnmatch.fnmatch(k, a) for a in argv)]
    tmp = Path(tempfile.mkdtemp(prefix="techtyl-art-"))
    for key in keys:
        png = (keep or tmp) / (key + ".png")
        if png.exists():
            png.unlink()
        shoot(key, png)
        dest, n, q, hit = encode(key, png)
        flag = "CLEAN" if not hit else "HIT " + ",".join(t.decode() for t in hit)
        print("%-28s %4d KB  q%d  %s  %s" % (key, round(n / 1024), q, dest.name, flag))
        if not keep:
            png.unlink()


if __name__ == "__main__":
    main(sys.argv[1:])
