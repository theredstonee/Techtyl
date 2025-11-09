# 🚀 TECHTYL INSTALLER - INSTALLATIONSBEFEHL

## ✅ Dein korrekter Installationsbefehl:

```bash
bash <(curl -s https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/install.sh)
```

---

## 📁 Repository-Struktur auf GitHub:

```
theredstonee/Techtyl                    <- Haupt-Repository
└── techtyl-installer/                  <- Unterordner
    ├── install.sh                      <- Hauptinstaller
    ├── lib/
    │   ├── lib.sh
    │   └── verify-fqdn.sh
    ├── ui/
    │   ├── panel.sh
    │   ├── wings.sh
    │   └── uninstall.sh
    ├── installers/                     <- Nach Vervollständigung
    ├── configs/                        <- Nach Vervollständigung
    └── scripts/                        <- Nach Vervollständigung
```

---

## 📋 Nächste Schritte:

### 1️⃣ Installer vervollständigen (PowerShell):

```powershell
cd E:\Claude\Techtyl\techtyl-installer

Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\installers" -Destination . -Recurse -Force
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\configs" -Destination . -Recurse -Force
Copy-Item -Path "..\pterodactyl-installer-master\pterodactyl-installer-master\scripts" -Destination . -Recurse -Force

Write-Host "Fertig!" -ForegroundColor Green
```

### 2️⃣ Änderungen zu GitHub pushen:

```bash
cd E:\Claude\Techtyl

git add techtyl-installer/
git commit -m "Update techtyl-installer with correct GitHub URLs"
git push
```

### 3️⃣ Auf Server installieren:

```bash
bash <(curl -s https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/install.sh)
```

---

## ✅ Was wurde angepasst:

- ✅ `install.sh` → Korrekte GitHub URLs für `Techtyl/techtyl-installer`
- ✅ `lib/lib.sh` → Korrekte GitHub URLs für `Techtyl/techtyl-installer`
- ✅ `update_lib_source()` → Korrekter Pfad mit `/techtyl-installer`

---

## 🎯 URL-Beispiele:

**Installer:**
```
https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/install.sh
```

**Lib:**
```
https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/lib/lib.sh
```

**Configs:**
```
https://raw.githubusercontent.com/theredstonee/Techtyl/main/techtyl-installer/configs/nginx.conf
```

---

**Alles bereit! 🚀**
