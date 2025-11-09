# 🚀 TECHTYL INSTALLER - INSTALLATIONSBEFEHL

## ✅ KORREKTER INSTALLATIONSBEFEHL:

```bash
sudo bash -c "$(curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/install.sh)"
```

**WICHTIG:** Verwende `bash -c "$(curl ...)"` statt `bash <(curl ...)`!

---

## 📋 Vor der Installation auf dem Server:

### 1️⃣ Installer MUSS vervollständigt sein!

**PowerShell (als Administrator):**

```powershell
cd E:\Claude\Techtyl\techtyl-installer

Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\installers" -Destination . -Recurse -Force
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\configs" -Destination . -Recurse -Force
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\scripts" -Destination . -Recurse -Force

Write-Host "Fertig!" -ForegroundColor Green
```

### 2️⃣ Zu GitHub pushen:

```bash
cd E:\Claude\Techtyl

git add techtyl-installer/
git commit -m "Add complete techtyl-installer files"
git push
```

### 3️⃣ Warte 1 Minute (GitHub Cache)

GitHub braucht einen Moment, um die Dateien verfügbar zu machen.

---

## 🖥️ Installation auf dem Server:

```bash
# Als root oder mit sudo
sudo bash -c "$(curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/install.sh)"
```

---

## ⚠️ Fehler beheben:

### Fehler: "404: command not found" oder "lib.sh not found"

**Ursache:** Die Dateien sind nicht auf GitHub oder nicht vollständig.

**Lösung:**

1. Überprüfe, ob die Dateien auf GitHub sind:
   - https://github.com/theredstonee/Techtyl/tree/main/techtyl-installer
   - Müssen vorhanden sein: `lib/`, `ui/`, `installers/`, `configs/`

2. Wenn Ordner fehlen, siehe Schritt 1️⃣ und 2️⃣ oben

3. Teste die URLs manuell:
   ```bash
   curl -I https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/lib/lib.sh
   ```

   Sollte `200 OK` zurückgeben, nicht `404`!

---

## 📁 Erforderliche Struktur auf GitHub:

```
theredstonee/Techtyl/
└── techtyl-installer/
    ├── install.sh              ✓ Vorhanden
    ├── lib/
    │   ├── lib.sh              ✓ Muss vorhanden sein
    │   └── verify-fqdn.sh      ✓ Muss vorhanden sein
    ├── ui/
    │   ├── panel.sh            ✓ Muss vorhanden sein
    │   ├── wings.sh            ✓ Muss vorhanden sein
    │   └── uninstall.sh        ✓ Muss vorhanden sein
    ├── installers/             ⚠️ Nach Schritt 1 kopieren!
    │   ├── panel.sh
    │   ├── wings.sh
    │   └── uninstall.sh
    ├── configs/                ⚠️ Nach Schritt 1 kopieren!
    │   ├── nginx.conf
    │   └── ...
    └── scripts/                ⚠️ Nach Schritt 1 kopieren!
```

---

## 🎯 Zusammenfassung:

1. ✅ Vervollständige Installer lokal (PowerShell-Befehl oben)
2. ✅ Push zu GitHub (`git push`)
3. ✅ Warte 1 Minute
4. ✅ Auf Server installieren: `sudo bash -c "$(curl -sSL ...)"`

---

**Bei Problemen:**
- Prüfe GitHub: https://github.com/theredstonee/Techtyl/tree/main/techtyl-installer
- Alle Ordner müssen sichtbar sein!
- Cache leeren: Warte 1-2 Minuten nach dem Push

---

**Der richtige Befehl:**
```bash
sudo bash -c "$(curl -sSL https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/install.sh)"
```

**🚀 Viel Erfolg!**
