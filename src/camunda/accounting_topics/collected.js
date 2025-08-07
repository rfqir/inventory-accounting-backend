import client,{ handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createPaymentReceive } from '../../services/acounting/payment/createPaymentRecieve.js';
import { updateStatusMp } from '../../graphql/mutation/updateStatusMoOrder.js';
import { getInvoicesIdBalance } from '../../mysql/controllers/invoice.js';
import { delivered } from '../../services/acounting/sales_invoice/delivered.js';
// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('collectedInvoice', {
    lockDuration: 600000
}, async ({ task, taskService }) => {
  try {
    const invoice = task.variables.get('invoice');
    let customerId;
    if (invoice.startsWith('25')) {
      customerId = 1;
    } else if (invoice.startsWith('57')) {
      customerId = 2;
    } else {
      console.warn(`Invoice tidak dikenal: ${invoice}`);
    }
     const currentDate = new Date().toISOString().split('T')[0]; // current date
     const getInvoiceBalance = await getInvoicesIdBalance(invoice);
     const invoiceData = getInvoiceBalance.get(invoice);
     const { id, balance,invoice_date } = invoiceData;
     try {
       const collect = await createPaymentReceive(customerId, currentDate, balance, 1000, id);
     } catch (error) {
       await delivered(id);
       try {
         await createPaymentReceive(customerId, currentDate, balance, 1000, id);
       } catch (error) {
        console.error('error payment delivered');
       }
     }
     await taskService.complete(task);
  } catch (error) {
    console.error('error collectedInvoice');
    console.error('data: ' + error);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});

