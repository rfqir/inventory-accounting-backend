import { Client, logger } from 'camunda-external-task-client-js';

export function handleFailureDefault(taskService, task, error) {
  return taskService.handleFailure(task, {
    errorMessage: "Task failed",
    errorDetails: error.message,
    retries: 0,
    retryTimeout: 1000
  });
}

const client = new Client({
  baseUrl: process.env.ENDPOINT_CAMUNDA,
  use: logger,
  asyncResponseTimeout: 30000,
});
console.log(process.env.ENDPOINT_CAMUNDA);

export default client;
