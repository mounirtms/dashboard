const sharp = require('sharp');
const fs = require('fs-extra');
const path = require('path');
const glob = require('glob');

const IMAGE_DIR = '/home/technadminy7/public_html/pub/media/catalog/product';
const TARGET_WIDTH = 1400; // Optional resizing (won't upscale)
const SUPPORTED_EXTENSIONS = ['.jpg', '.jpeg', '.png'];

const optimizeImage = async (filePath) => {
    const ext = path.extname(filePath).toLowerCase();
    const baseName = path.basename(filePath, ext);
    const dirName = path.dirname(filePath);
    const outputWebP = path.join(dirName, `${baseName}.webp`);

    try {
        const input = sharp(filePath);

        const metadata = await input.metadata();
        const resizeNeeded = metadata.width && metadata.width > TARGET_WIDTH;

        const pipeline = resizeNeeded ? input.resize(TARGET_WIDTH) : input;

        // Convert to WebP
        const buffer = await pipeline.webp({ quality: 75 }).toBuffer();
        await fs.writeFile(outputWebP, buffer);

        // Optional: Also optimize original JPG/PNG
        if (ext === '.jpg' || ext === '.jpeg') {
            const jpgBuffer = await pipeline.jpeg({ quality: 80 }).toBuffer();
            await fs.writeFile(filePath, jpgBuffer);
        } else if (ext === '.png') {
            const pngBuffer = await pipeline.png({ compressionLevel: 9 }).toBuffer();
            await fs.writeFile(filePath, pngBuffer);
        }

        console.log(`✅ Optimized: ${filePath} → ${outputWebP}`);
    } catch (err) {
        console.warn(`❌ Failed: ${filePath} - ${err.message}`);
    }
};

const run = async () => {
    const files = glob.sync(`${IMAGE_DIR}/**/*.{jpg,jpeg,png}`, {
        nodir: true,
        absolute: true,
    });

    console.log(`🔍 Found ${files.length} images`);
    for (const file of files) {
        await optimizeImage(file);
    }

    console.log('🎉 Image compression & WebP conversion complete.');
};

run();
