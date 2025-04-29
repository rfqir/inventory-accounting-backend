import client from '../client.js';
import {getCustomer} from '../../services/acounting/customer/customer.js';
import {getItems} from '../../services/acounting/items/items.js';
import {createInvoice} from '../../services/acounting/sales_invoice/create.js';
client.subscribe('prosesOrder', async ({ task, taskService }) => {
  const customerName = 'bagas';
  const invoiceDate = '2025-04-27';
  const invoice = 'khdsayu,jvdwfjcla';
  const resi = 'resi/kjgsadhj';
  const items = ['6E5','11A5'];        // Make this an array
  const quantities = [2,3];       // Make this an array of numbers
  const sellPrice = [5000,10000];     // Make this an array
  const customerId = await getCustomer(customerName);
  const itemIds = await getItems(items);
  console.log(itemIds);
  
  try {
    const responseInvoice = await createInvoice(customerId, invoiceDate, invoice, resi, itemIds, quantities,sellPrice);
    await taskService.complete(task);
  } catch (error) {
    
  }
});
