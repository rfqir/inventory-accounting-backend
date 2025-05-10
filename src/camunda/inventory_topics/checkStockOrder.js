import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getItems } from '../../services/acounting/items/items.js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { insertStockCheck } from '../../graphql/mutation/insertMoOrderStockCheck.js';
import { getLocation } from '../../services/inventory/stock/getLocation.js';
import { getStock } from '../../services/inventory/stock/getStock.js';

function getCourierName(provider) {
  const p = provider.toLowerCase();

  if (provider.includes("JNE")) return "JNE";
  if (provider.includes("Rekomendasi")) return "rekomendasi";
  if (provider.includes("SiCepat")) return "sicepat";
  if ((p.includes("j&t") && p.includes("cargo")) || p.includes("kargo")) return "j&t cargo";
  if (p.includes("j&t")) return "j&t";
  if (provider.includes("Paxel")) return "paxel";
  if (provider.includes("GTL(Regular)")) return "gtl";
  if ((provider.includes("SPX") || provider.includes("Hemat")) &&
      !provider.includes("Sameday") && !provider.includes("Instant")) {
    return "shopee";
  }
  return "instant";
}

client.subscribe('checkStockOrder', async ({ task, taskService }) => {
  const fileName = "Order.all.xlsx"; // task.variables.get("file");
  const proc_inst_id = task.processInstanceId;

  let data;
  if (fileName.includes('Order.all')) {
    console.log('shopee check');
    data = await orderShopee(fileName);
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

      const lowerStatus = orderStatus.toLowerCase();
      const lowerProvider = shipingProvider.toLowerCase();

      if (
        orderStatus !== 'Perlu Dikirim  ' &&
        !(
          orderStatus === 'To ship Awaiting collection' ||
          (
            (lowerProvider.includes('sameday') ||
              lowerProvider.includes('same day') ||
              lowerProvider.includes('instant')) &&
            orderStatus === 'To ship Awaiting shipment'
          )
        )
      ) {
        console.log('skip: ', orderStatus);
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
        const stockLocation = await getLocation(sku);
        const stock = await getStock(stockLocation);
        const quantity = stock.quantity;

        const available = (quantity - amount) >= 0;
        check.push(available);

        const itemId = await getItems(sku);
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);
      }

      const allFalse = check.every(val => val === false);
      if (!allFalse) continue;

      await insertStockCheck(invoice, proc_inst_id);
      console.log('false: ', allFalse);

      const courierName = getCourierName(shipingProvider);

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
