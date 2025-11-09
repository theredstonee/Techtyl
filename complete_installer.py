#!/usr/bin/env python3
"""
Complete the Techtyl installer by copying and adapting all remaining files
"""

import os
import shutil
from pathlib import Path

def adapt_content(content, filename):
    """Adapt content by replacing Pterodactyl branding with Techtyl"""

    # Skip adaptation for certain files that should remain unchanged
    skip_full_adaptation = [
        'valid_timezones.txt',
        'nginx.conf',
        'nginx_ssl.conf',
        'www-pterodactyl.conf'
    ]

    if filename in skip_full_adaptation:
        return content

    # Replace project header
    content = content.replace(
        "# Project 'pterodactyl-installer'",
        "# Project 'techtyl-installer'\n# Based on 'pterodactyl-installer'"
    )

    # Update copyright
    content = content.replace(
        "# Copyright (C) 2018 - 2025, Vilhelm Prytz, <vilhelm@prytznet.se>",
        "# Original Copyright (C) 2018 - 2025, Vilhelm Prytz, <vilhelm@prytznet.se>\n# Techtyl Adaptation (C) 2025"
    )

    # Update association note
    content = content.replace(
        "# This script is not associated with the official Pterodactyl Project.",
        "# This script is adapted for Techtyl and is not associated with the\n# official Pterodactyl Project."
    )

    # Replace GitHub URLs
    content = content.replace(
        "pterodactyl-installer/pterodactyl-installer",
        "techtyl/techtyl-installer"
    )
    content = content.replace("pterodactyl-installer.se", "techtyl.com")

    # Replace log paths
    content = content.replace(
        "/var/log/pterodactyl-installer.log",
        "/var/log/techtyl-installer.log"
    )

    # Replace user-facing messages (case-sensitive)
    content = content.replace('Pterodactyl panel', 'Techtyl panel')
    content = content.replace('pterodactyl panel', 'techtyl panel')
    content = content.replace('Pterodactyl Panel', 'Techtyl Panel')
    content = content.replace('Pterodactyl Wings', 'Techtyl Wings')
    content = content.replace('pterodactyl wings', 'techtyl wings')

    # Replace variable names
    content = content.replace('PTERODACTYL_PANEL_VERSION', 'TECHTYL_PANEL_VERSION')
    content = content.replace('PTERODACTYL_WINGS_VERSION', 'TECHTYL_WINGS_VERSION')

    # Replace specific messages
    content = content.replace(
        'already have Pterodactyl panel on your system',
        'already have Techtyl panel on your system'
    )
    content = content.replace(
        'Downloading pterodactyl panel files',
        'Downloading panel files'
    )
    content = content.replace(
        'Downloaded pterodactyl panel files',
        'Downloaded panel files'
    )

    return content

def copy_file(src, dst):
    """Copy a single file and adapt if needed"""
    try:
        # Create destination directory
        dst.parent.mkdir(parents=True, exist_ok=True)

        # Read source
        with open(src, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()

        # Adapt if it's a script
        if src.suffix in ['.sh', '.service']:
            content = adapt_content(content, src.name)

        # Write to destination
        with open(dst, 'w', encoding='utf-8', newline='\n') as f:
            f.write(content)

        return True
    except Exception as e:
        print(f"  ✗ Error copying {src.name}: {e}")
        return False

def main():
    print("=" * 70)
    print("Completing Techtyl Installer")
    print("=" * 70)
    print()

    src_base = Path(r"E:\Claude\Techtyl\pterodactyl-installer-master\pterodactyl-installer-master")
    dst_base = Path(r"E:\Claude\Techtyl\techtyl-installer")

    if not src_base.exists():
        print(f"✗ Source directory not found: {src_base}")
        return

    # Files to copy
    files_to_copy = [
        # Installers
        ('installers/panel.sh', 'installers/panel.sh'),
        ('installers/wings.sh', 'installers/wings.sh'),
        ('installers/uninstall.sh', 'installers/uninstall.sh'),
        ('installers/phpmyadmin.sh', 'installers/phpmyadmin.sh'),

        # Lib
        ('lib/verify-fqdn.sh', 'lib/verify-fqdn.sh'),

        # Configs
        ('configs/www-pterodactyl.conf', 'configs/www-pterodactyl.conf'),
        ('configs/wings.service', 'configs/wings.service'),
        ('configs/pteroq.service', 'configs/pteroq.service'),
        ('configs/valid_timezones.txt', 'configs/valid_timezones.txt'),
        ('configs/nginx.conf', 'configs/nginx.conf'),
        ('configs/nginx_ssl.conf', 'configs/nginx_ssl.conf'),
    ]

    success_count = 0
    total_count = 0

    for src_rel, dst_rel in files_to_copy:
        src = src_base / src_rel
        dst = dst_base / dst_rel

        if not src.exists():
            print(f"⚠ Skipping missing file: {src_rel}")
            continue

        total_count += 1
        print(f"Copying: {src_rel}")

        if copy_file(src, dst):
            print(f"  ✓ Created: {dst_rel}")
            success_count += 1
        else:
            print(f"  ✗ Failed: {dst_rel}")

    print()
    print("=" * 70)
    print(f"Copy Complete: {success_count}/{total_count} files processed")
    print("=" * 70)
    print()

    # List what was created
    print("Techtyl Installer Structure:")
    print()
    for root, dirs, files in os.walk(dst_base):
        level = root.replace(str(dst_base), '').count(os.sep)
        indent = ' ' * 2 * level
        print(f"{indent}{Path(root).name}/")
        subindent = ' ' * 2 * (level + 1)
        for file in files:
            if not file.endswith('.bak') and not file.endswith('.py'):
                print(f"{subindent}{file}")

if __name__ == '__main__':
    main()
