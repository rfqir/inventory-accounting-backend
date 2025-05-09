import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createCreditNote } from '../../services/acounting/credit_note/create.js';
import { refundCreditNote } from '../../services/acounting/payment/refundCreditNote.js';
import { createPaymentReceive } from '../../services/acounting/payment/createPaymentRecieve.js';
import { delivered } from '../../services/acounting/sales_invoice/delivered.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('cancelOrder', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  try {
      const responseGetInvoice = await getInvoice(invoice);
      try {
        const customerId = responseGetInvoice.customer_id; // customer ID
        const currentDate = new Date().toISOString().split('T')[0]; // current date

        const itemIds = []; // items array
        const quantities = []; // quantity array
        const prices = []; // prices array
        const totals = [];
        // get items in entries data
        for (const entriess of responseGetInvoice.entries) {
            const {item} = entriess
            const id = item.id;
            const quantity = entriess.quantity;
            const price = entriess.rate;
            const totalPrice = entriess.total;

            itemIds.push(id);
            quantities.push(quantity);
            prices.push(price);
            totals.push(totalPrice);
        }
        const createCredit = await createCreditNote(customerId, currentDate, invoice, itemIds, quantities,prices, true);
        try {
            const amount = totals.reduce((acc, curr) => acc + curr, 0)
            
            const refund = await refundCreditNote(createCredit.id, currentDate, amount, invoice);
            try {
                const responseDelivered = await delivered(responseGetInvoice.id);
                try {
                    const collect = await createPaymentReceive(customerId, currentDate, amount, 1000, responseGetInvoice.id)
                    await taskService.complete(task);
                } catch (error) {
                    console.error('error payment cancel');
                }
            } catch (error) {
                console.error('error deliver cancel');   
            }
        } catch (error) {
            console.error("error refund");            
        }
      } catch (error) {
        console.error('status: ');
        console.error('data: ');
      }
  } catch (error) {
    console.error('status: ');
    console.error('data: ' + error.data);
  }
});
