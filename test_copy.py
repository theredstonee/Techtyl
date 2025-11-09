import os
import shutil
from pathlib import Path

src_base = Path(r"E:\Claude\Techtyl\pterodactyl-installer-master\pterodactyl-installer-master")
dst_base = Path(r"E:\Claude\Techtyl\techtyl-installer")

print(f"Source exists: {src_base.exists()}")
print(f"Destination exists: {dst_base.exists()}")

if src_base.exists():
    print(f"\nSource contents:")
    for item in src_base.iterdir():
        print(f"  - {item.name}")

    # Try to copy ui directory
    src_ui = src_base / 'ui'
    dst_ui = dst_base / 'ui'

    if src_ui.exists():
        print(f"\nUI directory exists, contains {len(list(src_ui.glob('*')))} files")
        print("Copying UI directory...")

        try:
            if dst_ui.exists():
                shutil.rmtree(dst_ui)
            shutil.copytree(src_ui, dst_ui)
            print("✓ UI directory copied successfully!")
        except Exception as e:
            print(f"✗ Error: {e}")
else:
    print("Source directory does not exist!")
