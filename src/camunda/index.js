import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url'; // <- tambah pathToFileURL

// ESModule __dirname trick
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Auto import semua handler dari topics
const topicsPath = path.join(__dirname, 'acounting_topics');
const topicFiles = fs.readdirSync(topicsPath);

for (const file of topicFiles) {
  const modulePath = path.join(topicsPath, file);
  const moduleURL = pathToFileURL(modulePath).href; // <- ubah ke URL
  await import(moduleURL); // <- baru import
}
