# Techtyl

Blueprint-Extension für das Pterodactyl-Panel: Theme mit Designer, eigenen Hintergründen und Server-Bannern.

Stand: Grundgerüst. Der Designer speichert Variante, Farbe, Ecken, Hintergrund und Anordnung; die Umgestaltung der Panel-Oberfläche und die weiteren Module folgen.

## Voraussetzungen

- Pterodactyl Panel 1.15.x
- Blueprint `beta-2026-08`

## Entwicklung

Quellordner nach `.blueprint/dev` des Panels kopieren und bauen:

```sh
blueprint -build
```

Die Einstellungen liegen danach unter `/admin/extensions/techtyl`.

## Aufbau

| Pfad | Inhalt |
|---|---|
| `conf.yml` | Blueprint-Konfiguration |
| `app/Design.php` | Standardwerte, Prüfung und CSS-Ausgabe der Design-Einstellungen |
| `admin/` | Einstellungsseite, Controller, Admin-Stylesheet und -Wrapper |
| `dashboard/` | Dashboard-Stylesheet und -Wrapper |
| `public/` | Öffentlich ausgelieferte Dateien |
