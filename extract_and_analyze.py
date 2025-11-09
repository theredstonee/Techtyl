import zipfile
import os
import shutil

# Clean up old extraction
if os.path.exists('pterodactyl-temp'):
    shutil.rmtree('pterodactyl-temp')

# Extract
with zipfile.ZipFile('pterodactyl-installer-master.zip', 'r') as zip_ref:
    zip_ref.extractall('.')

# Find the extracted directory
for item in os.listdir('.'):
    if 'pterodactyl' in item.lower() and os.path.isdir(item) and item != 'pterodactyl-temp':
        print(f"Found extracted directory: {item}")

        # List main files
        for root, dirs, files in os.walk(item):
            for file in files:
                filepath = os.path.join(root, file)
                print(filepath)
        break
