import shutil
import os
from pathlib import Path

src_base = Path(r'E:\Claude\Techtyl\pterodactyl-installer-master\pterodactyl-installer-master')
dst_base = Path(r'E:\Claude\Techtyl\techtyl-installer')

print(f"Source: {src_base}")
print(f"Destination: {dst_base}")
print()

# Directories to copy
dirs = ['installers', 'configs', 'scripts']

for dir_name in dirs:
    src_dir = src_base / dir_name
    dst_dir = dst_base / dir_name

    if src_dir.exists():
        print(f"Copying {dir_name}/...")
        if dst_dir.exists():
            shutil.rmtree(dst_dir)
        shutil.copytree(src_dir, dst_dir)
        file_count = len(list(dst_dir.rglob('*')))
        print(f"  -> {file_count} items copied")
    else:
        print(f"  -> {dir_name}/ not found in source")

# Copy CHANGELOG.md
src_changelog = src_base / 'CHANGELOG.md'
dst_changelog = dst_base / 'CHANGELOG.md'
if src_changelog.exists():
    shutil.copy(src_changelog, dst_changelog)
    print(f"\nCopied CHANGELOG.md")

print("\nFertig!")
