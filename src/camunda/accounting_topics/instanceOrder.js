import client,{ handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { updateInstance } from '../../graphql/mutation/updateInstance.js';
import { insertMoOrder } from '../../graphql/mutation/insertMoOrder.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('insertInstanceOrder', async ({ task, taskService }) => {
  const instanceId = task.processInstanceId;
  const processDefinitionKey = task.processDefinitionKey;
  const invoice = task.variables.get('invoice');
  try {
    await updateInstance(instanceId,processDefinitionKey, invoice)
    await taskService.complete(task);
  } catch (error) {
    console.error('error insertInstanceOrder');
    console.error('data: ' + error.data);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});
