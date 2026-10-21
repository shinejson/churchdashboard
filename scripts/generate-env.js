#!/usr/bin/env node
// generate-env.js
// Node script to generate frontend/js/env.js from env.dev.js or env.prod.js
// Usage: node scripts/generate-env.js

const fs = require('fs');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..');
const envModeFile = path.join(projectRoot, 'ENV_MODE');
let mode = 'dev';
if (fs.existsSync(envModeFile)) {
  const m = fs.readFileSync(envModeFile, 'utf8').trim();
  if (m === 'prod') mode = 'prod';
}

const srcDev = path.join(projectRoot, 'frontend', 'js', 'env.dev.js');
const srcProd = path.join(projectRoot, 'frontend', 'js', 'env.prod.js');
const dest = path.join(projectRoot, 'frontend', 'js', 'env.js');

try {
  if (mode === 'prod' && fs.existsSync(srcProd)) {
    fs.copyFileSync(srcProd, dest);
    console.log(`Generated env.js from env.prod.js (mode=prod) -> ${dest}`);
  } else if (mode === 'dev' && fs.existsSync(srcDev)) {
    fs.copyFileSync(srcDev, dest);
    console.log(`Generated env.js from env.dev.js (mode=dev) -> ${dest}`);
  } else {
    const fallback = "// auto-generated fallback env.js\nwindow.DEV = false;\nwindow.API_BASE = window.API_BASE || 'http://localhost/church/backend';\n";
    fs.writeFileSync(dest, fallback, 'utf8');
    console.log(`Generated fallback env.js -> ${dest}`);
  }
  process.exit(0);
} catch (err) {
  console.error('Error generating env.js:', err);
  process.exit(1);
}
