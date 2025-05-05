import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createCreditNote } from '../../services/acounting/credit_note/create.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('refund', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  try {
      const responseGetInvoice = await getInvoice(invoice);
      try {
        const customerId = responseGetInvoice.customer_id; // customer ID
        const currentDate = new Date().toISOString().split('T')[0]; // current date

        const itemIds = []; // items array
        const quantities = []; // quantity array
        const prices = []; // prices array
        // get items in entries data
        for (const entriess of responseGetInvoice.entries) {
            const {item} = entriess
            const id = item.id;
            const quantity = item.quantity;
            const price = item.rate;

            itemIds.push(id);
            quantities.push(quantity);
            prices.push(price);
        }
        const createCredit = createCreditNote(customerId, currentDate, invoice, itemIds, quantities)
        await taskService.complete(task);
      } catch (error) {
        console.error('status: ' + error.status);
        console.error('data: ' + error.data);
      }
  } catch (error) {
    console.error('status: ' + error.status);
    console.error('data: ' + error.data);
  }
});
