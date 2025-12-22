#!/usr/bin/env node

const puppeteer = require('puppeteer');
const fs = require('fs');

// Read config from file path passed as first argument
const configPath = process.argv[2];
if (!configPath) {
    console.error('Usage: node screenshot.cjs <config-file.json>');
    process.exit(1);
}

const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));

(async () => {
    const browser = await puppeteer.launch({
        executablePath: config.chromePath,
        args: [
            '--no-sandbox', 
            '--disable-gpu', 
            '--disable-dev-shm-usage',
            '--disable-setuid-sandbox',
            '--disable-crash-reporter'
        ],
        userDataDir: config.userDataDir || '/tmp/chrome-user-data',
        headless: true
    });

    const page = await browser.newPage();
    
    await page.setViewport({
        width: config.width,
        height: config.height,
        deviceScaleFactor: config.deviceScaleFactor || 1
    });

    await page.goto(config.url, { waitUntil: 'networkidle0', timeout: 60000 });

    await page.screenshot({
        path: config.outputPath,
        type: config.format || 'png',
        fullPage: false
    });

    await browser.close();
    console.log('Screenshot saved to: ' + config.outputPath);
})().catch(err => {
    console.error('Error:', err);
    process.exit(1);
});
