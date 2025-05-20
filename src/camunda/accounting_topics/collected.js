import client,{ handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createPaymentReceive } from '../../services/acounting/payment/createPaymentRecieve.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('collectedInvoice', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  try {
      const currentDate = new Date().toISOString().split('T')[0]; // current date
      const responseGetInvoice = await getInvoice(invoice);
      try {
        const invoiceId = responseGetInvoice.id;
        const customerId = responseGetInvoice.customer_id;
        const paymentAmount = responseGetInvoice.subtotal_local;
        const collect = await createPaymentReceive(customerId, currentDate, paymentAmount, 1000, invoiceId)
        if (collect) {
            await taskService.complete(task);
        }
      } catch (error) {
        console.error('error createPaymentReceive');
        throw error
      }
  } catch (error) {
    console.error('error collectedInvoice');
    console.error('data: ' + error.data);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});
