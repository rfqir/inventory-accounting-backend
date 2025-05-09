import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { getItems } from '../../services/acounting/items/items.js';
import { getInvoice } from '../../services/acounting/sales_invoice/get.js';
import { insertMoOrderShop } from '../../graphql/mutation/insertMoOrderShop.js';
import { insertStockCheck } from '../../graphql/mutation/insertMoOrderStockCheck.js';
import { getLocation } from '../../services/inventory/stock/getLocation.js';
import { getStock } from '../../services/inventory/stock/getStock.js';
// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('checkStockOrder', async ({ task, taskService }) => {
  // Retrieve file name variable from the task
  const fileName = "Order.all.xlsx";//task.variables.get("file");
  const proc_inst_id = task.processInstanceId;
  let data;
  // Determine source of the order based on the filename content
  if (fileName.includes('Order.all')) {
    // Shopee orders
    console.log('shopee check');
    
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
        items
      } = order;
      
      // Gunakan kondisi original untuk menghindari perubahan logika
      if (
        orderStatus !== 'Perlu Dikirim  ' && 
        !(
          orderStatus === 'To ship Awaiting collection' ||
          (
            (
              shipingProvider.toLowerCase().includes('sameday') ||
              shipingProvider.toLowerCase().includes('same day') ||
              shipingProvider.toLowerCase().includes('instant')
            ) &&
            orderStatus === 'To ship Awaiting shipment'
          )
        )
      ) {
        console.log('skip: ', orderStatus);
        continue;
      }
      
      const findInvoice = await getInvoice(invoice);
      if (findInvoice) {continue;}
      // Get customer ID using the buyer's username

      // Arrays to hold item details for this invoice
      const itemIds = [];
      const quantities = [];
      const prices = [];
      const check = [];
      
      // Loop through each item in the order
      for (const item of items) {
        const sku = item.sku;
        const amount = item.amount;
        const startingPrice = item.startingPrice;
        const productName = item.productName;
        const stockLocation = await getLocation(sku);
        const stock = await getStock(stockLocation);
        const quantity = stock.quantity;
        console.log(quantity);
        
        const available = (quantity - amount) >= 0;
        // Get item ID from SKU
        const itemId = await getItems(sku);
        check.push(available)
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);
      }
      const allFalse = check.every(val => val === false);
      if (!allFalse){
        continue;
      }
      await insertStockCheck(invoice,proc_inst_id);
      console.log('false: ', allFalse);
      
      let courir_name;
      if (shipingProvider.includes("JNE")) {
        courir_name = "JNE"
      }else if (shipingProvider.includes("Rekomendasi")){
        courir_name = "rekomendasi"
      }else if (shipingProvider.includes("SiCepat")){
        courir_name = "sicepat"
      }else if (shipingProvider.includes("j&t") && shipingProvider.includes("Cargo") || shipingProvider.includes("Kargo") ){
        courir_name = "j&t cargo"
      }else if (shipingProvider.includes("j&t")){
        courir_name = "j&t"
      }else if (shipingProvider.includes("Paxel")){
        courir_name = "paxel"
      }else if (shipingProvider.includes("GTL(Regular)")){
        courir_name = "gtl"
      }else if (
        (shipingProvider.includes("SPX") || shipingProvider.includes("Hemat")) &&
        !shipingProvider.includes("Sameday") &&
        !shipingProvider.includes("Instant")
      ){
        courir_name = "shopee"
      }else{
        courir_name = "instant"
      }

      // Store invoice and shipping provider info for Camunda variables
      invoiceCamunda.push(invoice);
      resiCamunda.push(noResi);
      shippingProviders.push(courir_name);
    }
    
    const jumlah = invoiceCamunda.length;
    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
    // Set process variables to be sent back to Camunda
    const variablesCamunda = new Variables();
    variablesCamunda.set("invoice", invoiceCamunda);
    variablesCamunda.set("courier_name", shippingProviders);
    variablesCamunda.set("resi", resiCamunda);


    // Complete the task and send variables to the process instance
    await taskService.complete(task, variablesCamunda);
  } catch (error) {
    // Handle and log any errors during the process
    console.error('Failed to process orders:', error);
  }
});