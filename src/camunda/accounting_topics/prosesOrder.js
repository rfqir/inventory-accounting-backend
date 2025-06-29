import client, { handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getCustomers } from '../../mysql/controllers/customer.js';
import  getItems  from '../../mysql/controllers/item.js';
import { processOrder } from '../../services/camunda/start.js';
import { updateResi } from '../../graphql/mutation/updateResi.js';
import { createInvoice } from '../../services/acounting/sales_invoice/create.js';
import { getInvoices } from '../../mysql/controllers/invoice.js';
import { insertMoOrderShop } from '../../graphql/mutation/insertMoOrderShop.js';
import { insertMoOrder } from '../../graphql/mutation/insertMoOrder.js';
import { updateStatusMp } from '../../graphql/mutation/updateStatusMoOrder.js';
import { insertMoOrderShopMiror } from '../../graphql/mutation/insertMoOrderShopMiror.js';
import { insertMoOrderCost } from '../../graphql/mutation/insertMoOrderCost.js';
import { getStocks } from '../../postgresql/controller/stock.js';
import { cancelInvoice } from '../../services/order/cancel.js';
import { getCourierName } from '../../services/order/courier_name.js';
import { shouldSkipOrder } from '../../services/order/shouldSkipOrder.js';

const MAX_CONCURRENT = 10;

function chunkArray(array, size) {
  const result = [];
  for (let i = 0; i < array.length; i += size) {
    result.push(array.slice(i, i + size));
  }
  return result;
}

async function getLorong(sku) {
  const match = sku.match(/^(\d+)/);
  const result = match ? parseInt(match[1], 10) : null;
  if (result !== null) {
    if (result <= 7 || result === 15) return 1;
    if ([8, 9, 11, 12, 14, 16, 17].includes(result)) return 2;
    if ([10, 13, 18].includes(result) || (result >= 19 && result <= 21)) return 3;
  }
  return null;
}

client.subscribe('processOrder', {
  lockDuration: 600000
}, async ({ task, taskService }) => {
  const fileName = task.variables.get("file");
  console.log('file: ', fileName);

  let data;
  try {
    if (fileName.includes('Order.all')) {
      data = await orderShopee(fileName);
    } else if (fileName.includes('Semua pesanan') || fileName.includes('Untuk Dikirim')) {
      data = await orderTokopedia(fileName);
    } else {
      console.error('Unrecognized file name:', fileName);
      return;
    }

    const invoiceCamunda = [];
    const resiCamunda = [];
    const shippingProviders = [];

    const allInvoices = data.map(order => order.invoice);
    const foundInvoices = await getInvoices(allInvoices);

    // Ambil semua SKU unik
    const allSkusSet = new Set();
    data.forEach(order => {
      order.items.forEach(item => {
        const skuPrefix = item.sku.includes('-') ? item.sku.split('-')[0] : item.sku;
        allSkusSet.add(skuPrefix);
      });
    });
    const allSkus = Array.from(allSkusSet);

    // Ambil data stok dan buat mapping SKU → part_id
    const stocksFromDb = await getStocks(allSkus); // pastikan kembalikan { code, part_id }
    const skuToPartId = new Map();
    for (const stock of stocksFromDb) {
      skuToPartId.set(stock.sku, stock.part_id);
    }

    // Ambil data item dan buat mapping SKU → item.id
    const skuToItemId = new Map();
    const items = await getItems(allSkus);

    for (const item of items) {
      skuToItemId.set(item.sku, item.id);
    }

    const batches = chunkArray(data, MAX_CONCURRENT);

    for (const batch of batches) {
      await Promise.all(batch.map(async (order) => {
        const {
          invoice, noResi, paidTime, username, shipingProvider,
          orderStatus, items, ShippingFee
        } = order;

        if (await shouldSkipOrder(orderStatus, shipingProvider)) {
          if (orderStatus.toLowerCase().includes('batal') || orderStatus.toLowerCase().includes('cancel')) {
            await cancelInvoice(invoice, orderStatus);
          } else {
           console.log(`[Skip Invoice Exists] ${orderStatus} and ${shipingProvider}`);
            await updateStatusMp(orderStatus, invoice);
          }
          return;
        }

        if (foundInvoices.has(invoice)) {
          console.log(`[Skip Invoice Exists] ${invoice}`);
          await updateResi(invoice, noResi);
          return;
        }

        const customerId = await getCustomers(username);
        const itemIds = [], quantities = [], prices = [], konfersi = [], lorong = [];

        for (const item of items) {
          const { sku, amount, startingPrice, productName } = item;
          let skuPrefix = sku, skuSuffix = 1;

          if (sku.includes('-')) {
            const skuParts = sku.split('-');

            skuPrefix = skuParts[0];
            skuSuffix = parseInt(skuParts[skuParts.length - 1], 10);
            const quantityKonfersi = amount * skuSuffix;
            konfersi.push(amount);
            lorong.push(await getLorong(skuPrefix));

            const partId = skuToPartId.get(skuPrefix) || 0;
            
            await insertMoOrderShopMiror(invoice, noResi, productName, amount, sku, partId, quantityKonfersi);
            await insertMoOrderShop(invoice, noResi, productName, amount, sku, partId, quantityKonfersi);
          }else {
            const quantityKonfersi = amount * skuSuffix;
            konfersi.push(quantityKonfersi);
            lorong.push(await getLorong(skuPrefix));

            const partId = skuToPartId.get(skuPrefix) || 0;
            await insertMoOrderShopMiror(invoice, noResi, productName, amount, skuPrefix, partId, quantityKonfersi);
            await insertMoOrderShop(invoice, noResi, productName, amount, skuPrefix, partId, quantityKonfersi);
          }
          
          const itemId = skuToItemId.get(skuPrefix) || 0;
          itemIds.push(itemId);
          quantities.push(amount);
          prices.push(startingPrice);
        }

        const uniqueLorong = [...new Set(lorong.filter(l => l !== null))].sort((a, b) => a - b).join(',');
        const courierName = await getCourierName(shipingProvider);

        await insertMoOrder(invoice, noResi, orderStatus, courierName, uniqueLorong);
        await createInvoice(customerId, paidTime, invoice, noResi, itemIds, konfersi, prices);
        // await insertMoOrderCost(invoice, ShippingFee); // Uncomment if needed
	const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
        const date = now;
        await processOrder(invoice, courierName,date )
      }));
    }
    await taskService.complete(task);
  } catch (error) {
    console.error('error processOrder', error);
    await handleFailureDefault(taskService, task, error);
  }
});

