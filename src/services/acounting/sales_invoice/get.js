import httpClient from '../../../utils/httpClient.js';

async function getInvoice(invoice) {
    try {
        const response = await httpClient.get(`/sales/invoices?search_keyword=${invoice}`);
        const invoicedata = response.data.sales_invoices.find(i => i.invoice_no === invoice);
        return invoicedata;
    } catch (error) {
        throw error;
    }
}

export { getInvoice };
