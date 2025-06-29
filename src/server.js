import dotenv from 'dotenv';
dotenv.config();

import fastify from './app.js';


fastify.listen({ port: 8011 }, (err, address) => {
  if (err) {
    fastify.log.error(err);
    process.exit(1);
  }
  console.log(`Server listening at ${address}`);
});
