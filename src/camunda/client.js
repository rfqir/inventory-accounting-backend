import { Client, logger } from 'camunda-external-task-client-js';

const client = new Client({
  baseUrl: process.env.ENDPOINT_CAMUNDA,
  use: logger,
  asyncResponseTimeout: 10000,
});

export default client;
