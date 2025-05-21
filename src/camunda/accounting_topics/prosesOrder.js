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

// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('processOrder', async ({ task, taskService }) => {
  // Retrieve file name variable from the task
  const fileName = "Order.all3.xlsx"// task.variables.get("file");

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
        if (orderStatus.toLowerCase().includes('batal') || orderStatus.toLowerCase().includes('cancel')) {
          await cancelInvoice(invoice, staorderStatustus)
        } else {
          updateStatusMp(orderStatus, invoice)
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
      
      // Loop through each item in the order
      for (const item of items) {
        const sku = item.sku;
        const amount = item.amount;
        const startingPrice = item.startingPrice;
        const productName = item.productName;
        const getItemInventree = await getItem(sku);
        await insertMoOrderShop(invoice, noResi, productName, amount, sku, getItemInventree.part)
        // Get item ID from SKU
        const itemId = await getItems(sku);
        itemIds.push(itemId);
        quantities.push(amount);
        prices.push(startingPrice);
      }
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
      await insertMoOrder(invoice, noResi, orderStatus, courir_name)
      // Create an invoice for the current order
      await createInvoice(
        customerId,
        paidTime,
        invoice,
        noResi,
        itemIds,
        quantities,
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
    console.log(dataInv, dataCourier, dataJumlah,dataNow);
    
    const variablesCamunda = new Variables();
    // variablesCamunda.set("countInvoice", 3);
    // variablesCamunda.set("invoice", "2504268R4DM0RS,2504268R5UEMUP,2504268RCTJUVG");
    // variablesCamunda.set("courier_name", "shopee,shopee,shopee");
    // variablesCamunda.set("date_time", "2025-05-10 06:29:38");
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