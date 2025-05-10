import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getCustomer } from '../../services/acounting/customer/customer.js';
import { getItems } from '../../services/acounting/items/items.js';
import { createInvoice } from '../../services/acounting/sales_invoice/create.js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { insertMoOrderShop } from '../../graphql/mutation/insertMoOrderShop.js';
import { insertMoOrder } from '../../graphql/mutation/insertMoOrder.js';

const getCourierName = (provider) => {
  const p = provider.toLowerCase();
  if (provider.includes("JNE")) return "JNE";
  if (provider.includes("Rekomendasi")) return "rekomendasi";
  if (provider.includes("SiCepat")) return "sicepat";
  if ((p.includes("j&t") && p.includes("cargo")) || provider.includes("Kargo")) return "j&t cargo";
  if (provider.includes("j&t")) return "j&t";
  if (provider.includes("Paxel")) return "paxel";
  if (provider.includes("GTL(Regular)")) return "gtl";
  if ((provider.includes("SPX") || provider.includes("Hemat")) &&
      !provider.includes("Sameday") && !provider.includes("Instant")) {
    return "shopee";
  }
  return "instant";
};

client.subscribe('processOrder', async ({ task, taskService }) => {
  const fileName = "Order.all3.xlsx"; // Replace with task.variables.get("file") for dynamic

  let data;
  if (fileName.includes('Order.all')) {
    console.log('shopee');
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

    for (const {
      invoice, noResi, paidTime, username, shipingProvider, orderStatus, items
    } of data) {

      const orderStatusClean = orderStatus?.trim();

      const isInstantType = ['sameday', 'same day', 'instant']
        .some(keyword => shipingProvider.toLowerCase().includes(keyword));

      const isValidOrder =
        orderStatusClean === 'Perlu Dikirim  ' ||
        orderStatusClean === 'To ship Awaiting collection' ||
        (isInstantType && orderStatusClean === 'To ship Awaiting shipment');

      if (!isValidOrder) {
        console.log('skip: ', orderStatus);
        continue;
      }

      const invoiceExists = await getInvoice(invoice);
      if (invoiceExists) continue;

      const customerId = await getCustomer(username);
      const itemIds = [];
      const quantities = [];
      const prices = [];

      for (const { sku, amount, startingPrice, productName } of items) {
        await insertMoOrderShop(invoice, noResi, productName, amount, sku);
        const itemId = await getItems(sku);
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);
      }

      const courierName = getCourierName(shipingProvider);
      await insertMoOrder(invoice, noResi, orderStatus, courierName);

      await createInvoice(customerId, paidTime, invoice, noResi, itemIds, quantities, prices);

      invoiceCamunda.push(invoice);
      resiCamunda.push(noResi);
      shippingProviders.push(courierName);
    }

    const variablesCamunda = new Variables()
      .set("countInvoice", invoiceCamunda.length)
      .set("invoice", invoiceCamunda.join(','))
      .set("courier_name", shippingProviders.join(','))
      .set("date_time", new Date().toISOString().replace('T', ' ').slice(0, 19));

    await taskService.complete(task, variablesCamunda);
  } catch (error) {
    console.error('Failed to process orders:', error);
  }
});
