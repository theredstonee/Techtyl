#!/usr/bin/env python3
import zipfile
import os

output_file = 'zip_analysis.txt'

try:
    with open(output_file, 'w', encoding='utf-8') as out:
        out.write("Analyzing pterodactyl-installer-master.zip\n")
        out.write("=" * 80 + "\n\n")

        with zipfile.ZipFile('pterodactyl-installer-master.zip', 'r') as zip_ref:
            out.write(f"Total files: {len(zip_ref.filelist)}\n\n")
            out.write("File structure:\n")
            out.write("-" * 80 + "\n")

            for info in zip_ref.filelist:
                out.write(f"{info.filename} ({info.file_size} bytes)\n")

            out.write("\n" + "=" * 80 + "\n")
            out.write("Extraction starting...\n\n")

            # Extract to pterodactyl-installer-master directory
            zip_ref.extractall('.')

            out.write("Extraction completed successfully!\n")

except Exception as e:
    with open(output_file, 'w', encoding='utf-8') as out:
        out.write(f"ERROR: {str(e)}\n")
        out.write(f"Error type: {type(e).__name__}\n")
        import traceback
        out.write("\nTraceback:\n")
        out.write(traceback.format_exc())

print("Analysis written to zip_analysis.txt")
