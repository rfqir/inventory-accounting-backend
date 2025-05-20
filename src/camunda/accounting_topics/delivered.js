import client,{ handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { delivered } from '../../services/acounting/sales_invoice/delivered.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('delivered', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  try {
      const responseGetInvoice = await getInvoice(invoice);
      try {
        console.log('invoiceId: ' + responseGetInvoice.id );
        const responseDelivered = await delivered(responseGetInvoice.id);
        await taskService.complete(task);
      } catch (error) {
        console.error('error deliver 2 ');
        throw error
      }
  } catch (error) {
    console.error('error delivered');
    console.error('data: ' + error.data);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});
