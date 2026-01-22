#!/bin/bash

# Script to copy product images and rename them according to SKU with sequential numbering

INPUT_FILE="/home/technadminy7/public_html/product_images.csv"
OUTPUT_DIR="/home/technadminy7/public_html/product_images_by_sku"
MEDIA_BASE="/home/technadminy7/public_html/pub/media/catalog/product"

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

# Skip the header line and process each line
tail -n +2 "$INPUT_FILE" | while IFS=$'\t' read -r sku image_path; do
    # Skip if SKU or image path is empty
    if [[ -z "$sku" || -z "$image_path" ]]; then
        continue
    fi
    
    # Count how many images we already have for this SKU to determine the sequence number
    existing_count=$(ls "$OUTPUT_DIR/${sku}_"*.jpg 2>/dev/null | wc -l)
    
    # Calculate the next sequence number (starting from 01)
    seq_num=$((existing_count + 1))
    formatted_seq=$(printf "%02d" $seq_num)
    
    # Full path to source image
    source_image="$MEDIA_BASE$image_path"
    
    # Destination filename
    dest_filename="${sku}_${formatted_seq}.jpg"
    dest_path="$OUTPUT_DIR/$dest_filename"
    
    # Copy the file if source exists
    if [[ -f "$source_image" ]]; then
        cp "$source_image" "$dest_path"
        echo "Copied: $sku -> $dest_filename"
    else
        echo "Warning: Source image not found: $source_image"
    fi
done

echo "Processing complete!"