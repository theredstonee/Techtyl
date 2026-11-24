"""Erzeugt die lokale Uebersichtsseite aller Bilder: python uebersicht.py  ->  Downloads/techtyl-bilder.html"""
import json, html
from pathlib import Path
d = (Path(__file__).resolve().parent.parent / 'public' / 'defaults')
m = json.loads((d / 'manifest.json').read_text(encoding='utf-8'))
base = d.as_uri() + '/'
tint = dict(werft='#0f1c23', papier='#e9ecef', hafen='#190f24', kiefer='#121f19')
surf = dict(werft='#172a33', papier='#f8f9fa', hafen='#2a1b3a', kiefer='#1b2d24')
eggs = {}
for e, b in m['banners']['eggs'].items(): eggs.setdefault(b, []).append(e)
def kb(p): return round(p.stat().st_size / 1024)
out = ['''<!doctype html><html lang="de"><head><meta charset="utf-8"><title>Techtyl – mitgelieferte Bilder</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--bg:#0f1c23;--surf:#172a33;--line:#2c4753;--text:#e9f0ee;--muted:#93a9ac;--accent:#f0a33b}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,Segoe UI,sans-serif;padding:32px clamp(16px,4vw,56px) 80px}
h1{font-size:30px;margin:0 0 4px}h2{font-size:20px;margin:48px 0 6px;padding-top:20px;border-top:1px solid var(--line)}
p{color:var(--muted);margin:0 0 18px;max-width:900px}code{font-family:ui-monospace,Consolas,monospace;font-size:13px;color:var(--muted)}
.grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fill,minmax(520px,1fr))}
.bgs{grid-template-columns:repeat(auto-fill,minmax(560px,1fr))}
figure{margin:0;background:var(--surf);border:1px solid var(--line);border-radius:10px;overflow:hidden}
figcaption{padding:10px 14px 12px;display:flex;justify-content:space-between;gap:12px;align-items:baseline;flex-wrap:wrap}
figcaption b{font-size:15px}figcaption span{color:var(--muted);font-size:13px}
figure>a>img{display:block;width:100%;height:auto}
.demo{position:relative;aspect-ratio:16/9;background-size:cover;background-position:center}
.demo::before{content:"";position:absolute;inset:0;background:var(--t);opacity:.45}
.demo i{position:absolute;border-radius:8px;background:var(--s);opacity:.94}
.demo i:nth-child(1){left:0;top:0;bottom:0;width:15%;border-radius:0}
.demo i:nth-child(2){left:19%;top:14%;width:23%;height:30%}.demo i:nth-child(3){left:45%;top:14%;width:23%;height:30%}.demo i:nth-child(4){left:71%;top:14%;width:23%;height:30%}
.demo i:nth-child(5){left:19%;top:50%;width:75%;height:34%}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--line)}
.pair>*{min-width:0}
.cards{display:flex;gap:14px;padding:14px;align-items:flex-start;background:var(--bg);flex-wrap:wrap}
.card{background:var(--surf);border:1px solid var(--line);border-radius:10px;overflow:hidden}
.card .ban{background-size:cover;background-position:50% 30%;position:relative}
.card .ban::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 35%,var(--surf))}
.card .t{padding:0 14px 12px;margin-top:-18px;position:relative;font-weight:600}
.card .t small{display:block;color:var(--muted);font-weight:400;font-family:ui-monospace,Consolas,monospace}
.c1{width:330px}.c1 .ban{height:96px}.c2{flex:1 1 600px;max-width:1200px}.c2 .ban{height:160px}
</style></head><body>
<h1>Techtyl – mitgelieferte Bilder</h1>
<p>Alle Motive sind prozedural gezeichnet (Canvas), ohne fremde Grafiken. Quellen und Render-Skript liegen in <code>.art/</code>. Klick auf ein Bild öffnet es in voller Größe.</p>
<h2>Hintergründe (2560×1440)</h2>
<p>Links das Bild, rechts dieselbe Datei mit 45 % Abdunklung und aufgelegten Flächen – so wie es im Panel wirkt. Die ersten vier gehören fest zu den Farbvarianten, die übrigen sind frei wählbar.</p>
<div class="grid bgs">''']
for it in m['backgrounds']['items']:
    v = it['variant'] or 'werft'
    u = base + 'backgrounds/' + it['file']
    who = ('Standard für „' + it['variant'] + '“') if it['variant'] else 'wählbar'
    out.append('<figure><div class="pair"><a href="%s"><img src="%s" loading="lazy" alt="" style="display:block;width:100%%"></a><div class="demo" style="background-image:url(%s);--t:%s;--s:%s"><i></i><i></i><i></i><i></i><i></i></div></div><figcaption><b>%s</b><span>%s · <code>backgrounds/%s</code> · %d KB</span></figcaption></figure>' % (u, u, u, tint[v], surf[v], html.escape(it['label']), who, it['file'], kb(d / 'backgrounds' / it['file'])))
out.append('</div><h2>Server-Banner (1600×520)</h2><p>Oben das ganze Bild, darunter die beiden Zuschnitte der Server-Karte (330×96 und breit mit 160 px Höhe), jeweils nach unten ausgeblendet. Ausrichtung <code>background-position: 50% 30%</code>.</p><div class="grid">')
for it in m['banners']['items']:
    u = base + 'banners/' + it['file']
    names = eggs.get(it['key'], [])
    egg = ', '.join(names) if names else 'Rückfall für alle übrigen Eggs'
    out.append('<figure><a href="%s"><img src="%s" loading="lazy" alt=""></a><div class="cards"><div class="card c1"><div class="ban" style="background-image:url(%s)"></div><div class="t">%s<small>%s</small></div></div><div class="card c2"><div class="ban" style="background-image:url(%s)"></div><div class="t">%s<small>%s</small></div></div></div><figcaption><b>%s</b><span>%s · <code>banners/%s</code> · %d KB</span></figcaption></figure>' % (u, u, u, html.escape(it['label']), html.escape(names[0] if names else 'Standard'), u, html.escape(it['label']), html.escape(names[0] if names else 'Standard'), html.escape(it['label']), html.escape(egg), it['file'], kb(d / 'banners' / it['file'])))
out.append('</div></body></html>')
(Path.home() / 'Downloads' / 'techtyl-bilder.html').write_text('\n'.join(out), encoding='utf-8')
print('ok')
