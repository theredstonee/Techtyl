#!/usr/bin/env python3
"""
Script to adapt Pterodactyl branding to Techtyl
This will replace branding elements while keeping core functionality
"""

import os
import re
from pathlib import Path

def adapt_file(filepath):
    """Adapt a single file's branding"""
    print(f"Processing: {filepath}")

    try:
        # Read the file
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()

        # Create backup
        backup_path = str(filepath) + '.bak'
        with open(backup_path, 'w', encoding='utf-8') as f:
            f.write(content)

        # Replace project name in comments
        content = content.replace(
            "Project 'pterodactyl-installer'",
            "Project 'techtyl-installer'\n# Based on 'pterodactyl-installer'"
        )

        # Update copyright
        content = content.replace(
            "Copyright (C) 2018 - 2025, Vilhelm Prytz, <vilhelm@prytznet.se>",
            "Original Copyright (C) 2018 - 2025, Vilhelm Prytz, <vilhelm@prytznet.se>\n# Techtyl Adaptation (C) 2025"
        )

        # Update project association note
        content = content.replace(
            "This script is not associated with the official Pterodactyl Project.",
            "This script is adapted for Techtyl and is not associated with the\n# official Pterodactyl Project."
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

        # Replace display messages (case-insensitive where appropriate)
        # User-facing messages
        content = re.sub(r'(?i)pterodactyl panel', 'Techtyl panel', content)
        content = content.replace('Pterodactyl Panel', 'Techtyl Panel')
        content = re.sub(r'(?i)pterodactyl wings', 'Techtyl Wings', content)
        content = content.replace('Pterodactyl Wings', 'Techtyl Wings')

        # Replace variable names
        content = content.replace('PTERODACTYL_PANEL_VERSION', 'TECHTYL_PANEL_VERSION')
        content = content.replace('PTERODACTYL_WINGS_VERSION', 'TECHTYL_WINGS_VERSION')

        # Replace "already have Pterodactyl panel" message
        content = content.replace(
            'already have Pterodactyl panel on your system',
            'already have Techtyl panel on your system'
        )

        # Write the modified content
        with open(filepath, 'w', encoding='utf-8', newline='\n') as f:
            f.write(content)

        print(f"  ✓ Adapted: {filepath}")
        return True

    except Exception as e:
        print(f"  ✗ Error processing {filepath}: {e}")
        return False

def main():
    """Main function to process all .sh files"""
    print("Adapting branding to Techtyl...\n")

    # Get the directory of this script
    script_dir = Path(__file__).parent

    # Find all .sh files except the adapt script itself
    sh_files = []
    for root, dirs, files in os.walk(script_dir):
        for file in files:
            if file.endswith('.sh') and file not in ['adapt_branding.sh']:
                sh_files.append(Path(root) / file)

    print(f"Found {len(sh_files)} .sh files to process\n")

    # Process each file
    success_count = 0
    for filepath in sh_files:
        if adapt_file(filepath):
            success_count += 1

    print(f"\nBranding adaptation complete!")
    print(f"Successfully adapted {success_count}/{len(sh_files)} files")
    print(f"Original files backed up with .bak extension")

if __name__ == '__main__':
    main()
