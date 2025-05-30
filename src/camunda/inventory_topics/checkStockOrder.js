import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getItems } from '../../services/acounting/items/items.js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { insertStockCheck } from '../../graphql/mutation/insertMoOrderStockCheck.js';
import { getLocation } from '../../services/inventory/stock/getLocation.js';
import { getStock } from '../../services/inventory/stock/getStock.js';
import { getCourierName } from '../../services/order/courier_name.js';
import { shouldSkipOrder } from '../../services/order/shouldSkipOrder.js';

client.subscribe('checkStockOrder', async ({ task, taskService }) => {
  const fileName = "Order.all.xlsx"; // task.variables.get("file");
  const proc_inst_id = task.processInstanceId;

  let data;
  if (fileName.includes('Order.all')) {
    console.log('shopee check');
    try {
      data = await orderShopee(fileName);
    } catch (error) {
      console.error('gagal: ', error);
    }
  } else if (fileName.includes('Semua pesanan')) {
    console.log('tokopedia');
    data = await orderTokopedia(fileName);
  } else {
    console.error('Unrecognized file name:', fileName);
    return;
  }

  try {
    const invoiceCamunda = [];
    const resiCamunda = [];
    const shippingProviders = [];

    for (const order of data) {
      const {
        invoice,
        noResi,
        shipingProvider,
        orderStatus,
        items
      } = order;

      if (shouldSkipOrder(orderStatus, shipingProvider)) {
        console.log(`[Skip] ${orderStatus}`);
        continue;
      }

      const findInvoice = await getInvoice(invoice);
      if (findInvoice) continue;

      const itemIds = [];
      const quantities = [];
      const prices = [];
      const check = [];

      for (const item of items) {
        const { sku, amount, startingPrice } = item;
        let skuPrefix = null;
        if (sku.includes('-')) {
          const skuParts = sku.split('-');
          skuPrefix = skuParts[0]; // Sebelum '-' pertama
          console.log(`SKU: ${sku}, Prefix: ${skuPrefix}, Suffix: ${skuSuffix}`);
        } else {
          skuPrefix = sku;
          console.log(`SKU: ${sku} tidak memiliki '-' → skip parsing`);
        }
        const stockLocation = await getLocation(skuPrefix);
        const stock = await getStock(stockLocation);
        const quantity = stock;
        const available = (quantity - amount) >= 0;
        check.push(available);

        const itemId = await getItems(skuPrefix);
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);
      }

      const allFalse = check.every(val => val === false);
      if (!allFalse) continue;

      // await insertStockCheck(invoice, proc_inst_id);

      const courierName = await getCourierName(shipingProvider);

      invoiceCamunda.push(invoice);
      resiCamunda.push(noResi);
      shippingProviders.push(courierName);
    }

    const variablesCamunda = new Variables();
    variablesCamunda.set("invoice", invoiceCamunda);
    variablesCamunda.set("courier_name", shippingProviders);
    variablesCamunda.set("resi", resiCamunda);

    await taskService.complete(task, variablesCamunda);
  } catch (error) {
    console.error('Failed to process orders:', error);
  }
});
