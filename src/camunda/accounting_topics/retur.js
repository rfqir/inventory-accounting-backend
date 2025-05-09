import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createCreditNote } from '../../services/acounting/credit_note/create.js';
import { refundCreditNote } from '../../services/acounting/payment/refundCreditNote.js';
import { createPaymentReceive } from '../../services/acounting/payment/createPaymentRecieve.js';
import { delivered } from '../../services/acounting/sales_invoice/delivered.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('returOrder', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  const itemsCamunda = task.variables.get('items');
  const quantitiesCamunda = task.variables.get('quantities');
  
  const itemsArray = itemsCamunda.split(',');
  const quantitiesArray = quantitiesCamunda.split(',').map(Number);
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
            const quantity = entriess.quantity;
            const price = entriess.rate;
            const totalPrice = entriess.total;
            const code = item.code
            itemIds.push(id);
            quantities.push(quantity);
            prices.push(price);
            totals.push(totalPrice);
            codes.push(code);
        }
        
        const resultId = itemsArray.map(code => {
            const index = codes.indexOf(code); // cari index di codes
            return itemIds[index]; // ambil itemId berdasarkan index
          });
        const resultPrice = itemsArray.map(code => {
            const index = codes.indexOf(code); // cari index di codes
            return prices[index]; // ambil itemId berdasarkan index
          });

        const createCredit = await createCreditNote(customerId, currentDate, invoice, resultId, quantitiesArray,resultPrice, true);
        try {
            const amount = resultPrice.reduce((acc, curr) => acc + curr, 0)
            
            const refund = await refundCreditNote(createCredit.id, currentDate, amount, invoice);
            await taskService.complete(task);
        } catch (error) {
            console.error("error refund");            
        }
      } catch (error) {
        console.error('status: ');
        console.error('data catch: ', error);
      }
  } catch (error) {
    console.error('status: ');
    console.error('data: ' + error.data);
  }
});
