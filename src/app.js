import fastifyHttpProxy from 'fastify-http-proxy';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import fastify from 'fastify';
import cors from '@fastify/cors';
import {} from 'dotenv/config'
import "./camunda/index.js";
// Konfigurasi Fastify dan route
const app = fastify();
// Setup __dirname di ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const server = fastify({
  https: {
    key: fs.readFileSync(path.join(__dirname, '../../backend/express_camunda/apache-certs/mirorim.ddns.net/privkey1.pem')),
    cert: fs.readFileSync(path.join(__dirname, '../../backend/express_camunda/apache-certs/mirorim.ddns.net/cert1.pem')),
    ca: fs.readFileSync(path.join(__dirname, '../../backend/express_camunda/apache-certs/mirorim.ddns.net/fullchain1.pem')),
  },
  logger: true,
});
server.register(fastifyHttpProxy, {
  upstream: 'http://localhost:8004',
  prefix: '/',
  rewritePrefix: '',
  http2: false,
});
const PORT = 8004;
server.listen({ port: 8111, host: '0.0.0.0' })
  .then(address => {
    console.log(`Fastify HTTPS reverse proxy running on ${address}`);
  })
  .catch(err => {
    server.log.error(err);
    process.exit(1);
  });
app.register(cors, {
  origin: '*',
});

export default app; // Ekspor aplikasi untuk digunakan di server.js
