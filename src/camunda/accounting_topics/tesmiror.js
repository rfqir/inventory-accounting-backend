import client, { handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopeeMiror.js';
import { orderTokopedia } from '../../services/excel/orderTokopediaMiror.js';
import  getItems  from '../../mysql/controllers/item.js';
import { insertMoOrderShopMiror } from '../../graphql/mutation/insertMoOrderShopMiror.js';
import { getMiror } from '../../graphql/query/getMiror.js';
import { insertMoOrderCost } from '../../graphql/mutation/insertMoOrderCost.js';
import { getStocks } from '../../postgresql/controller/stock.js';
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

client.subscribe('processOrderMiror', {
  lockDuration: 600000
}, async ({ task, taskService }) => {
  const fileName = task.variables.get("file");
  console.log('file: ', fileName);

  let data;
  try {
    if (fileName.includes('Order.all')) {
      data = await orderShopee(fileName);
    } else if (fileName.includes('Semua pesanan')) {
      data = await orderTokopedia(fileName);
    } else {
      console.error('Unrecognized file name:', fileName);
      return;
    }


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
          return;
        }
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
            const miror = await getMiror(invoice, sku);
            if (miror) {
                console.log(`Miror already ${miror}`);
                return
            } else {
                console.log(`Miror not exist ${miror}`);
                await insertMoOrderShopMiror(invoice, noResi, productName, amount, sku, partId, quantityKonfersi);
            }
          }else {
            const quantityKonfersi = amount * skuSuffix;
            konfersi.push(quantityKonfersi);
            lorong.push(await getLorong(skuPrefix));

            const partId = skuToPartId.get(skuPrefix) || 0;
            const miror = await getMiror(invoice, skuPrefix);
            if (miror && Object.keys(miror).length > 0) {
                console.log(`Miror already ${miror}`);
                return
            } else {
                console.log(`Miror not exist ${miror}`);
                await insertMoOrderShopMiror(invoice, noResi, productName, amount, skuPrefix, partId, quantityKonfersi);
            }
          }
          
          const itemId = skuToItemId.get(skuPrefix) || 0;
          itemIds.push(itemId);
          quantities.push(amount);
          prices.push(startingPrice);
        }

        const uniqueLorong = [...new Set(lorong.filter(l => l !== null))].sort((a, b) => a - b).join(',');

        // await insertMoOrderCost(invoice, ShippingFee); // Uncomment if needed
        const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
        const date = now;
      }));
    }
    await taskService.complete(task);
  } catch (error) {
    console.error('error processOrderMiror', error);
    await handleFailureDefault(taskService, task, error);
  }
});
