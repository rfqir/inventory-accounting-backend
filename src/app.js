import fastify from 'fastify';
import cors from '@fastify/cors';
import {} from 'dotenv/config'
import "./camunda/index.js";
// Konfigurasi Fastify dan route
const app = fastify();

app.register(cors, {
  origin: '*',
});

export default app; // Ekspor aplikasi untuk digunakan di server.js
