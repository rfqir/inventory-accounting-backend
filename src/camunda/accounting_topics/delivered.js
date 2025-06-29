import client, { handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { markInvoiceDelivered } from '../../mysql/controllers/invoice.js';

// Subscribe to the "deliveredInvoice" task from Camunda
client.subscribe('deliveredInvoice', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  try {
    const responseDelivered = await markInvoiceDelivered(invoice);
    await taskService.complete(task);
  } catch (error) {
    console.error('error delivered');
    console.error('data: ' + error.data);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});

