import {getInvoicesDraf} from './src/mysql/controllers/invoice.js';
import {delivered} from './src/services/acounting/sales_invoice/delivered.js';
async function main(params) {
    try {
        const invoiceData = await getInvoicesDraf(); // Returns an array of invoice IDs
        console.log('Draft invoices:', invoiceData); // Debug: lihat daftar invoice draf
        let nomor = 0;
        for (const id of invoiceData) {
            // Here you can add your logic to process each invoice
            await delivered(id);
            console.log('No:', ++nomor);
            console.log('Processing invoice ID:', id);
        }
    } catch (error) {
        console.error('Error in main function:', error.message);
    }
}

main()
