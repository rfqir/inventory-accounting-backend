import client,{ handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getCustomer } from '../../services/acounting/customer/customer.js';
import { getItems } from '../../services/acounting/items/items.js';
import { createInvoice } from '../../services/acounting/sales_invoice/create.js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { insertMoOrderShop } from '../../graphql/mutation/insertMoOrderShop.js';
import { insertMoOrder } from '../../graphql/mutation/insertMoOrder.js';
import { updateStatusMp } from '../../graphql/mutation/updateStatusMoOrder.js';
import { insertMoOrderCost } from '../../graphql/mutation/insertMoOrderCost.js';
import { getItem } from '../../services/inventory/stock/getItemInventree.js';
import { cancelInvoice } from '../../services/order/cancel.js';
import { getCourierName } from '../../services/order/courier_name.js';
import { shouldSkipOrder } from '../../services/order/shouldSkipOrder.js';
async function getLorong(sku) {
  const match = sku.match(/^(\d+)/);
  const result = match ? parseInt(match[1], 10) : null;

  if (result !== null) {
    if (result <= 7 || result === 15) {
      return 1;
    } else if ([8, 9, 11, 12, 14, 16, 17].includes(result)) {
      return 2;
    } else if ([10, 13, 18].includes(result) || (result >= 19 && result <= 21)) {
      return 3;
    }
  }

  return null;
}
// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('processOrder', async ({ task, taskService }) => {
  // Retrieve file name variable from the task
  const fileName = "Order.all4.xlsx" //task.variables.get("file");
  console.log('file: ', fileName);

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
    const resiCamunda = [];
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
        items,
        ShippingFee
      } = order;
      
      // Gunakan kondisi original untuk menghindari perubahan logika
      if (shouldSkipOrder(orderStatus, shipingProvider)) {
        console.log(`[Skip] ${orderStatus}`);
        if (orderStatus.toLowerCase().includes('batal') || orderStatus.toLowerCase().includes('cancel')) {
          // await cancelInvoice(invoice, orderStatus);
        } else {
          // await updateStatusMp(orderStatus, invoice);
        }
        continue;
      }
      const findInvoice = await getInvoice(invoice);
      if (findInvoice) {continue;}
      // Get customer ID using the buyer's username
      const customerId = await getCustomer(username);

      // Arrays to hold item details for this invoice
      const itemIds = [];
      const quantities = [];
      const prices = [];
      const konfersi = [];
      const lorong = [];
      // Loop through each item in the order
      for (const item of items) {
        const sku = item.sku;
        const amount = item.amount;
        const startingPrice = item.startingPrice;
        const productName = item.productName;
        // Parsing SKU
        let skuPrefix = null;
        let skuSuffix = null;
        let quantityKonfersi;
        if (sku.includes('-')) {
          const skuParts = sku.split('-');
          skuPrefix = skuParts[0]; // Sebelum '-' pertama
          skuSuffix = skuParts[skuParts.length - 1]; // Setelah '-' terakhir
          quantityKonfersi =  amount * parseInt(skuSuffix)
          konfersi.push(quantityKonfersi);
          console.log(`SKU: ${sku}, Prefix: ${skuPrefix}, Suffix: ${skuSuffix}`);
        } else {
          skuPrefix = sku;
          skuSuffix = "1";
          quantityKonfersi =  amount
          konfersi.push(quantityKonfersi);
          console.log(`SKU: ${sku} tidak memiliki '-' → skip parsing`);
        }
        lorong.push(await getLorong(skuSuffix));
        const getItemInventree = await getItem(skuPrefix);
        // await insertMoOrderShop(invoice, noResi, productName, amount, sku, getItemInventree.part, quantityKonfersi)
        // Get item ID from SKU
        const itemId = await getItems(skuPrefix);
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);

      }
      const uniqueLorong = [...new Set(lorong.filter(l => l !== null))];
      // Urutkan untuk konsistensi hasil output
      uniqueLorong.sort((a, b) => a - b);
      // Gabungkan hasil sebagai string
      const finalLorong = uniqueLorong.join(',');
      const courir_name = await getCourierName(shipingProvider);
      // await insertMoOrder(invoice, noResi, orderStatus, courir_name,finalLorong)
      // Create an invoice for the current order
      await createInvoice(
        customerId,
        paidTime,
        invoice,
        noResi,
        itemIds,
        konfersi,
        prices
      );
      // await insertMoOrderCost(invoice, ShippingFee)
      // Store invoice and shipping provider info for Camunda variables
      invoiceCamunda.push(invoice);
      resiCamunda.push(noResi);
      shippingProviders.push(courir_name);
    }
    console.log('haiii');
    
    const jumlah = invoiceCamunda.length;
    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
    // Set process variables to be sent back to Camunda
    const dataInv = invoiceCamunda.join(',');
    const dataCourier = shippingProviders.join(',');
    const dataJumlah = jumlah;
    const dataNow = now;
    
    const variablesCamunda = new Variables();
    variablesCamunda.set("countInvoice", dataJumlah);
    variablesCamunda.set("invoice", dataInv);
    variablesCamunda.set("courier_name", dataCourier);
    variablesCamunda.set("date_time", dataNow);
    // Complete the task and send variables to the process instance
    await taskService.complete(task,variablesCamunda);
  } catch (error) {
    console.error('error processOder');
    console.error('data: ' + error);
    await handleFailureDefault(taskService, task, error)
    throw error;
  }
});
