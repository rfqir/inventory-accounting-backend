import httpClient from '../../../utils/httpClient.js';
import { insertSalesInvoiceAndItems } from '../../../mysql/controllers/createInvoice.js';

function addDays(dateStr, days) {
    const date = new Date(dateStr);
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];  // Format YYYY-MM-DD
}

async function createInvoice(customerId, invoiceDate, invoice, resi, itemIds, quantities, sellPrice) {
    console.log('masuk invoice');
    
    if (!Array.isArray(itemIds) || !Array.isArray(quantities) || itemIds.length !== quantities.length || itemIds.length !== sellPrice.length) {
        console.log('gagal');
        
        throw new Error("itemIds dan quantities harus array dengan panjang yang sama");
    }

    console.log('Creating invoice for customer: ' + customerId);

    const entries = itemIds.map((id, index) => ({
        index: index + 1,
        item_id: id,
        quantity: quantities[index],
        rate: sellPrice[index],
    }));

    const dueDate = addDays(invoiceDate, 30);
    // const data = {
    //     customer_id: customerId,
    //     invoice_date: invoiceDate,
    //     due_date: dueDate,
    //     invoice_no: invoice,
    //     reference_no: resi,
    //     delivered: false,
    //     entries: entries
    // };

    try {
        await insertSalesInvoiceAndItems({
  customerId,
  invoiceDate,
  invoiceNo: invoice,
  referenceNo: resi,
  duedate: dueDate,
  entries
});

        // const response = await httpClient.post('/sales/invoices', data);
        
        // if (response && response.status === 200) {
        //     return response.data;  // Assuming the data is returned in the 'data' property
        // } else {
        //     console.error('Failed to create invoice, status:', response.status);
        //     throw new Error('Invoice creation failed');
        // }
        return { success: true, message: 'Invoice created successfully' };
    } catch (error) {
        console.error('Error creating invoice:', error.message);
        throw error;  // Rethrow the error to propagate it
    }
}

export { createInvoice };
