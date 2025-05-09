import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getCustomer } from '../../services/acounting/customer/customer.js';
import { getItems } from '../../services/acounting/items/items.js';
import { createInvoice } from '../../services/acounting/sales_invoice/create.js';

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('cashIn', async ({ task, taskService }) => {
  // Retrieve file name variable from the task
  const fileName = task.variables.get("fileName");

  let data;

  // Determine source of the order based on the filename content
  if (fileName.includes('Order.all')) {
    // Shopee orders
    console.log('shopee');
    
    data = await orderShopee(fileName);
  } else if (fileName.includes('Semua pesanan')) {
    console.log('tokopedia');
    // Tokopedia orders
    data = await orderTokopedia(fileName);
  } else {
    // Unknown file source
    console.error('Unrecognized file name:', fileName);
    return;
  }
  
  try {
    // Arrays to collect data for Camunda process variables
    const invoiceCamunda = [];
    const shippingProviders = [];

    // Loop through each order in the Excel data
    for (const order of data) {
      const {
        invoice,
        noResi,
        paidTime,
        username,
        shipingProvider,
        orderStatus,
        items
      } = order;
      
      // Gunakan kondisi original untuk menghindari perubahan logika
      if (orderStatus !== 'Perlu Dikirim  ') {
        continue;
      }

      // Get customer ID using the buyer's username
      const customerId = await getCustomer(username);

      // Arrays to hold item details for this invoice
      const itemIds = [];
      const quantities = [];
      const prices = [];
      
      // Loop through each item in the order
      for (const item of items) {
        const sku = item.sku;
        const amount = item.amount;
        const startingPrice = item.startingPrice;

        // Get item ID from SKU
        const itemId = await getItems(sku);
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);
      }

      // Create an invoice for the current order
      await createInvoice(
        customerId,
        paidTime,
        invoice,
        noResi,
        itemIds,
        quantities,
        prices
      );

      // Store invoice and shipping provider info for Camunda variables
      invoiceCamunda.push(invoice);
      shippingProviders.push(shipingProvider);
    }
    
    const jumlah = invoiceCamunda.length;
    
    // Set process variables to be sent back to Camunda
    const variablesCamunda = new Variables();
    variablesCamunda.set("countInvoice", jumlah);
    variablesCamunda.set("invoice", invoiceCamunda.join(','));
    variablesCamunda.set("kurir", shippingProviders.join(','));

    // Complete the task and send variables to the process instance
    await taskService.complete(task, variablesCamunda);
  } catch (error) {
    // Handle and log any errors during the process
    console.error('Failed to process orders:', error);
  }
});