import client,{ handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { createCreditNote } from '../../services/acounting/credit_note/create.js';

// Subscribe to the "updateOrderAccounting" task from Camunda
client.subscribe('updateOrderAccounting', async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');
  const items = task.variables.get('items');
  const dropship = task.variables.get('dropship');
	if (dropship){
	await taskService.complete(task);
	}
  const beforeSku = items.map(item => item.before.sku_toko);
  const beforeQty = items.map(item => item.before.quantity_order);
  const afterSku = items.map(item => item.after.sku_toko);
  const afterQty = items.map(item => item.after.quantity_order);

  // Deteksi pengurangan quantity
  const decreasedItems = [];
  for (let i = 0; i < beforeQty.length; i++) {
    if (afterQty[i] < beforeQty[i]) {
      decreasedItems.push({
        sku: afterSku[i],
        qtyReduced: beforeQty[i] - afterQty[i]
      });
    }
  }
  if (decreasedItems.length == 0){
  await taskService.complete(task);
  }
  console.log('Detail pengurangan:', decreasedItems);

  try {
    const responseGetInvoice = await getInvoice(invoice);

    try {
      const customerId = responseGetInvoice.customer_id;
      const currentDate = new Date().toISOString().split('T')[0];

      const itemIds = [];
      const quantities = [];
      const prices = [];
      const totals = [];
      const codes = [];

      for (const entriess of responseGetInvoice.entries) {
        const { item } = entriess;
        itemIds.push(item.id);
        quantities.push(item.quantity);
        prices.push(entriess.rate);
        totals.push(entriess.total);
        codes.push(item.code);
      }
      
      // Ambil data berdasarkan decreasedItems.sku
      const selectedItemIds = [];
      const selectedQuantities = [];
      const selectedPrices = [];

      for (const item of decreasedItems) {
        const index = codes.indexOf(item.sku);
        if (index !== -1) {
          selectedItemIds.push(itemIds[index]);
          selectedQuantities.push(item.qtyReduced);
          selectedPrices.push(prices[index]);
        }
      }

      if (selectedItemIds.length > 0) {
        await createCreditNote(customerId, currentDate, invoice, selectedItemIds, selectedQuantities, selectedPrices);
      } else {
        console.log('Tidak ada pengurangan, credit note tidak dibuat.');
      }

      await taskService.complete(task);
    } catch (error) {
      console.error('error createCreditNote UpdateOrderAccounting');
      throw error;
    }
  } catch (error) {
    console.error('error UpdateOrderAccounting: ' + error.status);
    console.error('data: ' + error.data);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});
