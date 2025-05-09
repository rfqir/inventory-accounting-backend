import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createCreditNote } from '../../services/acounting/credit_note/create.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('updateOrderAccounting', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  const items = task.variables.get('items')

  const beforeSku = items.map(item => item.before.sku_toko);
  const beforeQty = items.map(item => item.before.quantity_order);
  const afterQty = items.map(item => item.after.quantity_order);
  const afterSku = items.map(item => item.after.sku_toko);
  try {
      const responseGetInvoice = await getInvoice(invoice);
      try {
        const customerId = responseGetInvoice.customer_id; // customer ID
        const currentDate = new Date().toISOString().split('T')[0]; // current date

        const itemIds = []; // items array
        const quantities = []; // quantity array
        const prices = []; // prices array
        const totals = [];
        const codes = [];
        // get items in entries data
        for (const entriess of responseGetInvoice.entries) {
            const {item} = entriess
            const id = item.id;
            const quantity = item.quantity;
            const price = item.rate;
            const totalPrice = entriess.total;
            const code = item.code
            itemIds.push(id);
            quantities.push(quantity);
            prices.push(price);
            totals.push(totalPrice);
            codes.push(code);
        }
        const resultId = beforeSku.map(code => {
          const index = codes.indexOf(code); // cari index di codes
          return itemIds[index]; // ambil itemId berdasarkan index
        });
      const resultPrice = beforeSku.map(code => {
          const index = codes.indexOf(code); // cari index di codes
          return prices[index]; // ambil itemId berdasarkan index
        });
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
