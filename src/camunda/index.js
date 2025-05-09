import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

// ESModule __dirname trick
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Daftar folder yang ingin dibaca
const topicFolders = ['accounting_topics', 'inventory_topics'];

for (const folder of topicFolders) {
  const topicsPath = path.join(__dirname, folder);
  
  if (fs.existsSync(topicsPath)) {
    const topicFiles = fs.readdirSync(topicsPath);
    
    for (const file of topicFiles) {
      const modulePath = path.join(topicsPath, file);
      const moduleURL = pathToFileURL(modulePath).href;
      await import(moduleURL);
    }
  } else {
    console.warn(`Folder ${folder} tidak ditemukan.`);
  }
}
