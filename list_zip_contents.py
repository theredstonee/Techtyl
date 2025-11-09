import zipfile
import sys

zip_path = 'pterodactyl-installer-master.zip'

try:
    with zipfile.ZipFile(zip_path, 'r') as zip_ref:
        with open('zip_contents.txt', 'w', encoding='utf-8') as f:
            f.write(f"Files in {zip_path}:\n")
            f.write("=" * 80 + "\n\n")
            for info in zip_ref.filelist:
                f.write(f"{info.filename}\n")
        print("Contents written to zip_contents.txt")
except Exception as e:
    with open('zip_error.txt', 'w') as f:
        f.write(f"Error: {str(e)}\n")
        f.write(f"Error type: {type(e).__name__}\n")
    print(f"Error occurred: {e}")
