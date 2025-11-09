import zipfile
import os
import sys

# Force create output file
result_file = 'extract_result.txt'

# Start
with open(result_file, 'w') as f:
    f.write("Starting extraction...\n")
    f.flush()

    try:
        # Check if zip exists
        if not os.path.exists('pterodactyl-installer-master.zip'):
            f.write("ERROR: ZIP file not found!\n")
            sys.exit(1)

        f.write("ZIP file found.\n")
        f.flush()

        # Open and extract
        with zipfile.ZipFile('pterodactyl-installer-master.zip', 'r') as z:
            f.write(f"ZIP opened. Contains {len(z.filelist)} files.\n")
            f.flush()

            # List first 10 files
            for i, info in enumerate(z.filelist[:10]):
                f.write(f"  - {info.filename}\n")

            f.write("\nExtracting...\n")
            f.flush()

            z.extractall('.')
            f.write("Extraction complete!\n")

    except Exception as e:
        f.write(f"\nERROR: {e}\n")
        import traceback
        f.write(traceback.format_exc())

print("Done")
