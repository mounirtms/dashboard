#!/bin/bash
# Debug image processing

cd /home/beta/public_html/fabercastel

echo "Checking images_temp directory..."
ls -la images_temp/ | head -10

echo ""
echo "Processing images..."

mkdir -p images_processed

count=0
for image in images_temp/*; do
    if [ -f "$image" ]; then
        filename=$(basename "$image")
        extension="${filename##*.}"
        basename_no_ext="${filename%.*}"
        base_ref=$(echo "$basename_no_ext" | sed 's/_.*//')
        output_name="${base_ref}.jpg"
        output_path="images_processed/$output_name"
        
        if [ "$extension" = "webp" ]; then
            convert "$image" -quality 100 "images_processed/temp_${basename_no_ext}.jpg"
            if [ $? -eq 0 ]; then
                mv "images_processed/temp_${basename_no_ext}.jpg" "$output_path"
                echo "✓ Converted: $filename -> $output_name"
            else
                echo "✗ Failed: $filename"
            fi
        else
            cp "$image" "$output_path"
            echo "✓ Copied: $filename -> $output_name"
        fi
        
        count=$((count + 1))
        if [ $count -ge 10 ]; then
            echo "... (showing first 10)"
            break
        fi
    fi
done

echo ""
echo "Processed files:"
ls -la images_processed/ | head -15
