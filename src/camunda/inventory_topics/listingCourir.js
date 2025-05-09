import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getMoOrder } from '../../graphql/query/listing.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('listingCourir', async ({ task, taskService }) => {
  const courier_name = task.variables.get('courier_name');
  try {
      const responseGetInvoice = await getMoOrder(courier_name);
      const invoices = responseGetInvoice.map(item => item.invoice);
      const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
      const variablesCamunda = new Variables();
      variablesCamunda.set("invoices", invoices);
      variablesCamunda.set("dateTIme", now);
      await taskService.complete(task, variablesCamunda);
  } catch (error) {
    console.error('status: ' + error.status);
    console.error('data: ' + error.data);
  }
});
