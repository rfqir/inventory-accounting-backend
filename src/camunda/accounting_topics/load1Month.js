import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { orderShopee } from '../../services/excel/orderShopee.js';
import { orderTokopedia } from '../../services/excel/orderTokopedia.js';
import { createPaymentReceive } from '../../services/acounting/payment/createPaymentRecieve.js';
import { getInvoicesIdBalance } from '../../mysql/controllers/invoice.js';
import { uniqSignal } from '../signal/collected.js';
import { updateStatusMp } from '../../graphql/mutation/updateStatusMoOrder.js';
const MAX_CONCURRENT = 3;

function chunkArray(array, size) {
  const result = [];
  for (let i = 0; i < array.length; i += size) {
    result.push(array.slice(i, i + size));
  }
  return result;
}
function addDays(dateStr, days) {
    const date = new Date(dateStr);
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];  // Format YYYY-MM-DD
}
client.subscribe('load1Month', {
    lockDuration: 600000
}, async ({ task, taskService }) => {
    const fileName = task.variables.get("file");;
    try {
        let data;
        let customerId;
        const currentDate = new Date().toISOString().split('T')[0]; // current date
        if (fileName.includes('Order.all')) {
            console.log('shopee check');
            customerId = 1;
            try {
                data = await orderShopee(fileName);
            } catch (error) {
                console.error('Gagal baca file Shopee:', error);
                return;
            }
        } else if (fileName.includes('Semua pesanan') || fileName.includes('Untuk Dikirim')) {
            console.log('tokopedia');
            customerId = 2;
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
        const allInvoices = data.map(order => order.invoice);
        const getInvoiceBalance = await getInvoicesIdBalance(allInvoices);
        const batches = chunkArray(data, MAX_CONCURRENT);
        for (const batch of batches) {
            await Promise.all(batch.map(async (order) => {
                const {
                    invoice, noResi, paidTime, username, shipingProvider,
                    orderStatus, items, ShippingFee,RTSTime
                } = order;
                await updateStatusMp(orderStatus, invoice);
                const invoiceData = getInvoiceBalance.get(invoice);
                // console.log(`Processing invoice: ${invoice}`);
                // console.log(`invoiceData:`, invoiceData);
                
                if (!invoiceData) {
                    // console.warn(`Invoice not found: ${invoice}`);
                    return;
                }
                
                const { id, balance,invoice_date } = invoiceData;
                const dueDate = addDays(invoice_date, 30);
                if (orderStatus.toLowerCase().includes('perlu dikirim') || orderStatus.toLowerCase().includes('dibatalkan')){
                    // console.log(`Perlu dikirim: ${invoice}`);
                    await uniqSignal('shippedOrder',invoice,'status','All')
                }else if(orderStatus.toLowerCase().includes('dikirim') || orderStatus.toLowerCase().includes('selesai') || orderStatus.toLowerCase().includes('return') || orderStatus.toLowerCase().includes('completed')){
                    // console.log(`Dikirim: ${invoice}`);
                    await uniqSignal('shippedOrder',invoice,'status','Dikirim')
                } else {
                    // console.log(`skip invoice 1 bulan: ${invoice} with status: ${orderStatus}`);
                }
                if (orderStatus.toLowerCase() == 'shopee selesai  masalah diselesaikan' || orderStatus.toLowerCase() == 'shopee selesai  ' || orderStatus.toLowerCase() == 'shopee selesai  permintaan disetujui' || orderStatus.toLowerCase() == 'tokopedia Selesai Selesai ') {
                    // console.log('invoice selesai: ', invoice);
                    await uniqSignal('collected',invoice,'AR','cashin')
                } else if(orderStatus.toLowerCase().includes('return')) {
                    // await uniqSignal('collected',invoice,'AR','retur')
                } else if (orderStatus.toLowerCase().includes('perlu dikirim') && new Date(dueDate) < new Date()) {
                    await uniqSignal('collected',invoice,'AR','1month')
                }
            }));
        }
        await taskService.complete(task);
    } catch (error) {
        console.error('Failed to complete task:', error);
        
    }
    
});
