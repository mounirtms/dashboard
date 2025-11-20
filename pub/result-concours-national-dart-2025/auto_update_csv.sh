#!/bin/bash
# Script to automatically update the CSV file with latest form submissions

# Change to the directory containing the script
cd /home/technadminy7/public_html/pub/result-concours-national-dart-2025

# Run the PHP script to update the CSV
php update_csv.php

# Log the update with timestamp
echo "CSV updated at $(date)" >> update_log.txt