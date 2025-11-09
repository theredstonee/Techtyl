import zipfile
import os

zip_path = 'pterodactyl-installer-master.zip'
extract_path = 'pterodactyl-temp'

print(f"Opening {zip_path}...")
with zipfile.ZipFile(zip_path, 'r') as zip_ref:
    print(f"Files in archive:")
    for name in zip_ref.namelist()[:20]:
        print(f"  {name}")

    print(f"\nExtracting to {extract_path}...")
    zip_ref.extractall(extract_path)
    print("Extraction complete!")

print(f"\nListing extracted files:")
for root, dirs, files in os.walk(extract_path):
    for file in files[:10]:
        print(f"  {os.path.join(root, file)}")
