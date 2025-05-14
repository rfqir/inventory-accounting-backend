import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getMoOrder } from '../../graphql/query/listing.js';
import { insertMoOrderPrint } from '../../graphql/mutation/insertMoOrderPrint.js';


// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('listingCourir', async ({ task, taskService }) => {
  const courier_name = task.variables.get('courier_name');
  const proc_inst_id = task.processInstanceId;
  try {
    const responseGetInvoice = await getMoOrder(courier_name);
    const invoices = responseGetInvoice.map(item => item.invoice);

    for (const invoice of invoices) {
      await insertMoOrderPrint(invoice, proc_inst_id,courier_name); // Pass individual invoice here
    }
    await taskService.complete(task);
  } catch (error) {
    console.error('status: ' + error.status);
    console.error('data: ' + error.data);
  }
});
