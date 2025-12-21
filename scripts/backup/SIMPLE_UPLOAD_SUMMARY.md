# Simple iDrive Upload Script

## Purpose
Created a streamlined script that compresses the `/home/technadminy7/public_html/scripts/backup` folder and uploads it directly to iDrive.

## Features
1. Compresses the specified folder into a tar.gz archive
2. Uploads the compressed file directly to iDrive using AWS S3 CLI
3. Cleans up temporary files after successful upload
4. Provides clear logging and error handling

## Script Location
`/home/technadminy7/public_html/scripts/backup/simple-idrive-upload.sh`

## Usage
```bash
chmod +x /home/technadminy7/public_html/scripts/backup/simple-idrive-upload.sh
/home/technadminy7/public_html/scripts/backup/simple-idrive-upload.sh
```

## Configuration
The script uses the same iDrive S3 credentials as the existing backup system:
- AWS Access Key ID: prQjrOCZTP1yTOPfYvRl
- AWS Secret Access Key: 41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT
- S3 Bucket: s3://weektechno
- Endpoint: https://l0y0.la.idrivee2-27.com

## Verification
Successfully tested and verified that the script:
1. Compresses the backup folder correctly
2. Uploads the compressed file to iDrive
3. File is visible in the iDrive bucket with proper timestamp

## File Uploaded
- Name: backup-2025-12-16-16-02-20.tar.gz
- Size: 11517 bytes
- Location: s3://weektechno/backup-2025-12-16-16-02-20.tar.gz