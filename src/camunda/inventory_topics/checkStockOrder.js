import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getInvoices } from '../../mysql/controllers/invoice.js';
import { getStocks,getStock,skuGudang } from '../../postgresql/controller/stock.js';
import { insertStockCheck } from '../../graphql/mutation/insertMoOrderStockCheck.js';
import { getCourierName } from '../../services/order/courier_name.js';
import { shouldSkipOrder } from '../../services/order/shouldSkipCheckOrder.js';
import { getRefill } from '../../graphql/query/getRefill.js';
import { processRefill } from '../../services/camunda/start.js';

const MAX_CONCURRENT = 50;

function chunkArray(array, size) {
  const result = [];
  for (let i = 0; i < array.length; i += size) {
    result.push(array.slice(i, i + size));
  }
  return result;
}

client.subscribe('checkStockOrder', {
  lockDuration: 60000
}, async ({ task, taskService }) => {
  const fileName = task.variables.get("file");
  const proc_inst_id = task.processInstanceId;

  let data;
  if (fileName.includes('Order.all')) {
    console.log('shopee check');
    try {
      data = await orderShopee(fileName);
    } catch (error) {
      console.error('Gagal baca file Shopee:', error);
      return;
    }
  } else if (fileName.includes('Semua pesanan') || fileName.includes('Untuk Dikirim')) {
    console.log('tokopedia');
    try {
      data = await orderTokopedia(fileName);
    } catch (error) {
      console.error('Gagal baca file Tokopedia:', error);
      return;
    }
  } else {
    console.error('Unrecognized file name:', fileName);
    return;
  }

  try {
    const invoiceCamunda = [];
    const resiCamunda = [];
    const shippingProviders = [];
    // ✅ Ambil semua invoice dari data
    const allInvoices = data.map(order => order.invoice);
    const foundInvoices = await getInvoices(allInvoices);
    // Ambil semua sku yang ada di data order
    const allSkusSet = new Set();
    data.forEach(order => {
      order.items.forEach(item => {
        const skuPrefix = item.sku.includes('-') ? item.sku.split('-')[0] : item.sku;
        allSkusSet.add(skuPrefix);
      });
    });
    const allSkus = Array.from(allSkusSet);

    // Ambil stok semua sku sekaligus via getStocks()
    const stocksFromDb = await getStocks(allSkus); // [{ part_id: 'a', total: 15 }, { part_id: 'b', total: 20 }, ...]
    const stockToko = await getStock(allSkus); 

    // Buat map stok awal
    const availableStockBySku = new Map();
    const availableStockToko = new Map();

	for (const row of stocksFromDb) {
	  const sku = row.sku; // Pastikan getStocks() sudah mengembalikan kolom sku
	  const total = parseFloat(row.total);
	  if (availableStockBySku.has(sku)) {
	    availableStockBySku.set(sku, availableStockBySku.get(sku) + total);
	  } else {
	    availableStockBySku.set(sku, total);
	  }
	}
    // Kalau ada sku yang tidak ditemukan di DB, set stoknya 
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
    // Kalau ada sku yang tidak ditemukan di DB, set stoknya 0
    allSkus.forEach(sku => {
      if (!availableStockBySku.has(sku)) availableStockBySku.set(sku, 0);
    });
    allSkus.forEach(sku => {
      if (!availableStockToko.has(sku)) availableStockToko.set(sku, 0);
    });

    const refillSkus = [];
    const refillParts = [];
    const refillRequests = [];

    const batches = chunkArray(data, MAX_CONCURRENT);
    for (const batch of batches) {
      for (const order of batch) {
        const { invoice, noResi, shipingProvider, orderStatus, items } = order;

        if (await shouldSkipOrder(orderStatus, shipingProvider)) {
          console.log(`[Skip Status] ${invoice} (${orderStatus})`);
          continue;
        }

        if (foundInvoices.has(invoice)) {
          console.log(`[Skip Invoice Exists] ${invoice}`);
          continue;
        }

        let hasStock = false;     // ada item yang bisa dipenuhi
        let missingStock = false; // ada item yang tidak bisa dipenuhi

        // Cek stok dan update stok sementara di availableStockBySku
        for (const item of items) {
          let skuPrefix = item.sku.includes('-') ? item.sku.split('-')[0] : item.sku;
          let quantityKonfersi = item.amount;
          if (item.sku.includes('-')) {
            const skuParts = item.sku.split('-');
            const skuSuffix = parseInt(skuParts[1] || "1", 10);
            quantityKonfersi = item.amount * skuSuffix;
          }

          const currentStock = availableStockBySku.get(skuPrefix) || 0;
          const stockToko = availableStockToko.get(skuPrefix) || 0;
          if (currentStock > 0) {
            const usedQty = Math.min(quantityKonfersi, currentStock);
            availableStockBySku.set(skuPrefix, currentStock - usedQty);
            hasStock = true;
          }else {
            missingStock = true;
          }
          if (stockToko > 0) {
            const usedQty = Math.min(quantityKonfersi, stockToko);
            availableStockToko.set(skuPrefix, stockToko - usedQty);
          } else {
            const stockInfo = skuToPartId.get(skuPrefix); // ambil object { partId, location_id }
            if (stockInfo) {
              refillSkus.push(skuPrefix);
              
              refillParts.push(stockInfo.partId);
              refillRequests.push({
                partId: stockInfo.partId,
                location_id: stockInfo.location_id,
                sku: skuPrefix,
                quantity: quantityKonfersi
              });
            }
          }
        }

        if (hasStock && missingStock) {
          console.log(`[Sebagian stok kosong] ${invoice}`);
          await insertStockCheck(invoice, proc_inst_id, "Sebagian Stock Kosong"); // menandai invoice yg sebagian bisa diproses
        }

        if (!hasStock && missingStock) {
          console.log(`[Stock Kosong Semua] ${invoice}`);
          await insertStockCheck(invoice, proc_inst_id, "Stock Kosong Semua"); // menandai invoice yg tidak bisa diproses
          const courierName = await getCourierName(shipingProvider);
          invoiceCamunda.push(invoice);
          resiCamunda.push(noResi);
          shippingProviders.push(courierName);
          continue;
        }
      }
    }

    const uniqueRefillParts = [...new Set(refillParts)];
    console.log('Unique refill parts:', refillParts);

    if (uniqueRefillParts.length > 0) {

      console.log('Refill stock for SKUs:', uniqueRefillParts);
      
      for (const req of refillRequests) {
        const skuData = await skuGudang(req.partId);
        const name = skuData[0]?.name || '';
        const hurufKedua = name.charAt(1);
        const refillData = await getRefill(req.sku);
        if (refillData) {
          console.log(`Refill already exists for SKU: ${req.sku}, Part ID: ${req.partId}, Location ID: ${req.location_id}`);
          continue; // Skip if refill already exists
        }
        console.log(`Processing refill for SKU: ${req.sku}, huruf kedua: ${hurufKedua}, Part ID: ${req.partId}, Location ID: ${req.location_id}, Quantity: ${req.quantity}`);
        if (skuData.length > 0) {
        if (req.quantity){
          await processRefill(req.sku, hurufKedua, req.partId, req.location_id, req.quantity);
        }else{
        await processRefill(req.sku, hurufKedua, req.partId, req.location_id, 10);
        }
        } else {
          //console.warn(`Part ${req.partId} not found in stock data.`);
        }
      }
    }
    
    if (invoiceCamunda.length === 0) {
      await insertStockCheck('noexist', proc_inst_id);
      const variablesCamunda = new Variables();
      variablesCamunda.set("invoice", 'noexist');
      variablesCamunda.set("courier_name", 'noexist');
      variablesCamunda.set("resi", 'noexist');

      await taskService.complete(task, variablesCamunda);
    }else {
      const variablesCamunda = new Variables();
      variablesCamunda.set("invoice", invoiceCamunda);
      variablesCamunda.set("courier_name", shippingProviders);
      variablesCamunda.set("resi", resiCamunda);

      await taskService.complete(task, variablesCamunda);
    }

  } catch (error) {
    console.error('Failed to process orders:', error);
  }
});

