#!/usr/bin/env python3
"""
Script to copy and adapt Pterodactyl installer files to Techtyl
"""

import os
import shutil
from pathlib import Path

def adapt_content(content):
    """Adapt content by replacing Pterodactyl branding with Techtyl"""

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

    # Replace user-facing messages
    content = content.replace('Pterodactyl panel', 'Techtyl panel')
    content = content.replace('pterodactyl panel', 'techtyl panel')
    content = content.replace('Pterodactyl Panel', 'Techtyl Panel')
    content = content.replace('Pterodactyl Wings', 'Techtyl Wings')
    content = content.replace('pterodactyl wings', 'techtyl wings')
    content = content.replace('pterodactyl or wings', 'techtyl panel or wings')

    # Replace variable names
    content = content.replace('PTERODACTYL_PANEL_VERSION', 'TECHTYL_PANEL_VERSION')
    content = content.replace('PTERODACTYL_WINGS_VERSION', 'TECHTYL_WINGS_VERSION')

    # Replace "already have Pterodactyl" messages
    content = content.replace(
        'already have Pterodactyl panel on your system',
        'already have Techtyl panel on your system'
    )

    return content

def copy_and_adapt_file(src, dst):
    """Copy a file and adapt its content"""
    try:
        # Create destination directory if it doesn't exist
        dst.parent.mkdir(parents=True, exist_ok=True)

        # Read source file
        with open(src, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()

        # Adapt content for .sh files
        if src.suffix == '.sh':
            content = adapt_content(content)

        # Write to destination
        with open(dst, 'w', encoding='utf-8', newline='\n') as f:
            f.write(content)

        print(f"  ✓ Copied and adapted: {dst}")
        return True

    except Exception as e:
        print(f"  ✗ Error copying {src}: {e}")
        return False

def main():
    """Main function"""
    print("Copying and adapting Pterodactyl installer to Techtyl...\n")

    # Source and destination paths
    src_base = Path(r"E:\Claude\Techtyl\pterodactyl-installer-master\pterodactyl-installer-master")
    dst_base = Path(r"E:\Claude\Techtyl\techtyl-installer")

    # Directories to copy
    dirs_to_copy = ['ui', 'installers', 'configs', 'scripts']

    total_files = 0
    success_count = 0

    # Copy directories
    for dir_name in dirs_to_copy:
        src_dir = src_base / dir_name
        dst_dir = dst_base / dir_name

        if not src_dir.exists():
            print(f"Warning: Source directory {src_dir} does not exist")
            continue

        print(f"\nProcessing directory: {dir_name}/")

        # Walk through all files in the directory
        for root, dirs, files in os.walk(src_dir):
            for file in files:
                src_file = Path(root) / file
                # Calculate relative path
                rel_path = src_file.relative_to(src_dir)
                dst_file = dst_dir / rel_path

                total_files += 1
                if copy_and_adapt_file(src_file, dst_file):
                    success_count += 1

    # Also copy verify-fqdn.sh from lib
    src_verify = src_base / 'lib' / 'verify-fqdn.sh'
    dst_verify = dst_base / 'lib' / 'verify-fqdn.sh'
    if src_verify.exists():
        total_files += 1
        if copy_and_adapt_file(src_verify, dst_verify):
            success_count += 1

    print(f"\n{'='*60}")
    print(f"Copy and adaptation complete!")
    print(f"Successfully processed {success_count}/{total_files} files")
    print(f"{'='*60}")

if __name__ == '__main__':
    main()
