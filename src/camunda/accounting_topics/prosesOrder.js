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
import { getStocks, getStock, skuGudang } from '../../postgresql/controller/stock.js';
import { cancelInvoice } from '../../services/order/cancel.js';
import { getCourierName } from '../../services/order/courier_name.js';
import { shouldSkipOrder } from '../../services/order/shouldSkipOrder.js';
import { moOrderMiror } from '../../services/order/moOrderMiror.js';
import { processRefill } from '../../services/camunda/start.js';
import { getSkuOrder } from '../../graphql/query/getSkuOrder.js';
import { getRefill } from '../../graphql/query/getRefill.js';
import {updateCourierName} from '../../graphql/mutation/updateCourier.js';
const MAX_CONCURRENT = 3;

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
  lockDuration: 60000
}, async ({ task, taskService }) => {
  const fileName = task.variables.get("file");
  const type_pesanan = task.variables.get("type_pesanan");
  console.log('file: ', fileName);

  let data;
  let customer;
  try {
    if (fileName.includes('Order.all')) {
      data = await orderShopee(fileName);
      customer = "shopee";
    } else if (fileName.includes('Semua pesanan') || fileName.includes('Untuk Dikirim')) {
      customer = "tokopedia";
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
    const stocksFromDb = await getStocks(allSkus); // stock gabungan
    const stockToko = await getStock(allSkus); // stock toko
    const availableStockBySku = new Map();
    const availableStockToko = new Map();
    
    // Ambil data stok dan buat mapping SKU → part_id
    const skuToPartId = new Map();
    for (const row of stockToko) {
        const sku = row.sku; // Pastikan getStock() sudah mengembalikan kolom sku
        const quantity = parseFloat(row.quantity);
        const partId = row.part_id;
        const location_id = row.id;
        if (availableStockToko.has(sku)) {
            availableStockToko.set(sku, availableStockToko.get(sku) + quantity);
        } else {
            availableStockToko.set(sku, quantity);
        }
        if (!skuToPartId.has(sku)) {
            skuToPartId.set(sku, { partId, location_id });
        }
    }
    allSkus.forEach(sku => {
      if (!availableStockToko.has(sku)) availableStockToko.set(sku, 0);
    });
    const refillSkus = [];
    const refillParts = [];
    const refillRequests = [];
    // Ambil data item dan buat mapping SKU → item.id
    const skuToItemId = new Map();
    const items = await getItems(allSkus);

    for (const item of items) {
      skuToItemId.set(item.sku, item.id);
    }
    for (const sku of allSkus) {
        // const rawStock = availableStockToko.get(sku) || 0;
        // const outstanding = await getSkuOrder(sku);
        // const finalStock = Math.max(0, rawStock - outstanding);
        // availableStockToko.set(sku, finalStock);
        // if (finalStock <= 0) {
        //     const stockInfo = skuToPartId.get(sku); // ambil object { partId, location_id }
        //     if (stockInfo) {
        //         refillSkus.push(sku);
        //         refillParts.push(stockInfo.partId);
        //         refillRequests.push({
        //             partId: stockInfo.partId,
        //             location_id: stockInfo.location_id,
        //             sku: sku,
        //             quantity: 10 // default refill quantity, bisa diganti logikanya
        //         });
        //         console.log(`REFILL DIBUTUHKAN: SKU ${sku}, partId: ${stockInfo.partId}, location_id: ${stockInfo.location_id}`);
        //     }
        // }
    }
    const batches = chunkArray(data, MAX_CONCURRENT);

    for (const batch of batches) {
      await Promise.all(batch.map(async (order) => {
        const {
          invoice, noResi, paidTime, username, shipingProvider,
          orderStatus, items, ShippingFee,RTSTime
        } = order;
        await moOrderMiror(invoice, noResi, orderStatus,paidTime, RTSTime, shipingProvider);
        if (await shouldSkipOrder(orderStatus, shipingProvider)) {
          if (orderStatus.toLowerCase().includes('dibatalkan')) {
            await cancelInvoice(invoice, orderStatus);
          } else {
           // console.log(`[Skip Invoice Exists] ${orderStatus} and ${shipingProvider}`);
            await updateStatusMp(orderStatus, invoice);
          }
          return;
        }
        const namaKurir = await getCourierName(shipingProvider);
        if (foundInvoices.has(invoice)) {
          // console.log(`[Skip Invoice Exists] ${invoice}`);
          // await updateCourierName(namaKurir, invoice)
          await updateResi(invoice, noResi);
          return;
        }

        if (type_pesanan == 'instant' && namaKurir != 'instant') {
          return;
        }
        let hasStock = false;     // ada item yang bisa dipenuhi
        let missingStock = false; // ada item yang tidak bisa dipenuhi

        const customerId = await getCustomers(customer);
        const itemIds = [], quantities = [], prices = [], konfersi = [], lorong = [];

        for (const item of items) {
          let { sku, amount, startingPrice, productName } = item;
          if (sku == ''){
          sku = 'none'}
          let skuPrefix = sku, skuSuffix = 1;

          if (sku.includes('-')) {
            const skuParts = sku.split('-');

            skuPrefix = skuParts[0];
            skuSuffix = parseInt(skuParts[skuParts.length - 1], 10);
            const quantityKonfersi = amount * skuSuffix;
            konfersi.push(amount);
            lorong.push(await getLorong(skuPrefix));

            const partId = skuToPartId.get(skuPrefix) || 0;
            const stockToko = availableStockToko.get(skuPrefix) || 0;
            if (stockToko > 0) {
                const usedQty = Math.min(quantityKonfersi, stockToko);
                availableStockToko.set(skuPrefix, stockToko - usedQty);
            }

            const validasi = availableStockToko.get(skuPrefix) || 0;

            // const stockInfo = skuToPartId.get(skuPrefix); // ambil object { partId, location_id }
            //             if (stockInfo) {
            //                 refillSkus.push(skuPrefix);

            //                 refillParts.push(stockInfo.partId);
            //                 refillRequests.push({
            //                     partId: stockInfo.partId,
            //                     location_id: stockInfo.location_id,
            //                     sku: skuPrefix,
            //                     quantity: quantityKonfersi
            //                 });
            //             }
            
            await insertMoOrderShopMiror(invoice, noResi, productName, amount, sku, partId.partId, quantityKonfersi);
            await insertMoOrderShop(invoice, noResi, productName, amount, sku, partId.partId, quantityKonfersi);
            
          }else {
            const quantityKonfersi = amount * skuSuffix;
            konfersi.push(quantityKonfersi);
            lorong.push(await getLorong(skuPrefix));

            const partId = skuToPartId.get(skuPrefix) || 0;
            const stockToko = availableStockToko.get(skuPrefix) || 0;
            if (stockToko > 0) {
                const usedQty = Math.min(quantityKonfersi, stockToko);
                availableStockToko.set(skuPrefix, stockToko - usedQty);
            }

            const validasi = availableStockToko.get(skuPrefix) || 0;

		// const stockInfo = skuToPartId.get(skuPrefix); // ambil object { partId, location_id }
    //                     if (stockInfo) {
    //                         refillSkus.push(skuPrefix);

    //                         refillParts.push(stockInfo.partId);
    //                         refillRequests.push({
    //                             partId: stockInfo.partId,
    //                             location_id: stockInfo.location_id,
    //                             sku: skuPrefix,
    //                             quantity: quantityKonfersi
    //                         });
    //                     }
            await insertMoOrderShopMiror(invoice, noResi, productName, amount, skuPrefix, partId.partId, quantityKonfersi);
            await insertMoOrderShop(invoice, noResi, productName, amount, skuPrefix, partId.partId, quantityKonfersi);
          }
          
          const itemId = skuToItemId.get(skuPrefix) || 0;
          itemIds.push(itemId);
          quantities.push(amount);
          prices.push(startingPrice);
        }

        // const uniqueRefillParts = [...new Set(refillParts)];
        // console.log('Unique refill parts:', refillParts);

        // if (uniqueRefillParts.length > 0) {

        //     console.log('Refill stock for SKUs:', uniqueRefillParts);

        //     for (const req of refillRequests) {
        //         const skuData = await skuGudang(req.partId);
        //         const name = skuData[0]?.name || '';
        //         const hurufKedua = name.charAt(1);
        //         const refillData = await getRefill(req.sku);
        //         if (refillData) {
        //             // console.log(`Refill already exists for SKU: ${req.sku}, Part ID: ${req.partId}, Location ID: ${req.location_id}`);
        //             continue; // Skip if refill already exists
        //         }
        //         // console.log(`Processing refill for SKU: ${req.sku}, huruf kedua: ${hurufKedua}, Part ID: ${req.partId}, Location ID: ${req.location_id}, Quantity: ${req.quantity}`);
        //         if (skuData.length > 0) {
        //             if (req.quantity) {
        //                // await processRefill(req.sku, hurufKedua, req.partId, req.location_id, req.quantity);
        //             } else {
        //                // await processRefill(req.sku, hurufKedua, req.partId, req.location_id, 10);
        //             }
        //         } else {
        //             //console.warn(`Part ${req.partId} not found in stock data.`);
        //         }
        //     }
        // }
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

