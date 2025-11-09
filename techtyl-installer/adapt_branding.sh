#!/bin/bash

# Script to adapt Pterodactyl branding to Techtyl
# This will replace branding elements while keeping core functionality

echo "Adapting branding to Techtyl..."

# Find all .sh files in the techtyl-installer directory
find . -name "*.sh" -not -name "adapt_branding.sh" -type f | while read -r file; do
  echo "Processing: $file"

  # Create backup
  cp "$file" "$file.bak"

  # Replace project name in comments
  sed -i "s/Project 'pterodactyl-installer'/Project 'techtyl-installer'\\nBased on 'pterodactyl-installer'/g" "$file"

  # Update copyright
  sed -i "s/Copyright (C) 2018 - 2025, Vilhelm Prytz, <vilhelm@prytznet.se>/Original Copyright (C) 2018 - 2025, Vilhelm Prytz, <vilhelm@prytznet.se>\\nTechtyl Adaptation (C) 2025/g" "$file"

  # Update project association note
  sed -i "s/This script is not associated with the official Pterodactyl Project./This script is adapted for Techtyl and is not associated with the official Pterodactyl Project./g" "$file"

  # Replace GitHub URLs
  sed -i "s|pterodactyl-installer/pterodactyl-installer|techtyl/techtyl-installer|g" "$file"
  sed -i "s|pterodactyl-installer.se|techtyl.com|g" "$file"

  # Replace log paths
  sed -i "s|/var/log/pterodactyl-installer.log|/var/log/techtyl-installer.log|g" "$file"

  # Replace display messages (user-facing text)
  sed -i "s/pterodactyl panel/techtyl panel/gi" "$file"
  sed -i "s/Pterodactyl panel/Techtyl panel/g" "$file"
  sed -i "s/Pterodactyl Panel/Techtyl Panel/g" "$file"
  sed -i "s/pterodactyl wings/techtyl wings/gi" "$file"
  sed -i "s/Pterodactyl Wings/Techtyl Wings/g" "$file"
  sed -i "s/pterodactyl or wings/techtyl panel or wings/gi" "$file"

  # Replace variable names
  sed -i "s/PTERODACTYL_PANEL_VERSION/TECHTYL_PANEL_VERSION/g" "$file"
  sed -i "s/PTERODACTYL_WINGS_VERSION/TECHTYL_WINGS_VERSION/g" "$file"

  # Important: DO NOT replace /var/www/pterodactyl as this is the actual installation path
  # Important: DO NOT replace pterodactyl/panel and pterodactyl/wings GitHub repos
  # as we're still using the original Pterodactyl software

  echo "  ✓ Adapted: $file"
done

echo ""
echo "Branding adaptation complete!"
echo "Original files backed up with .bak extension"
