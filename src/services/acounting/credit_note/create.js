import httpClient from '../../../utils/httpClient.js';

async function createCreditNote(customerId, creditNoteDate, invoice, itemIds, quantities, sellPrice, open) {
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

    const data = {
        customer_id: customerId,
        credit_note_date: creditNoteDate,
        reference_no: invoice,
        open: open || false,
        entries: entries
    };

    try {
        const response = await httpClient.post('/sales/credit_notes', data);
        
        if (response && response.status === 200) {
            
            return response.data;  // Assuming the data is returned in the 'data' property
        } else {
            // console.error('Failed to create invoice, status:', response.status);
            throw new Error('Invoice creation failed');
        }
    } catch (error) {
        console.error('Error creating invoice:', error.message);
        throw error;  // Rethrow the error to propagate it
    }
}

export { createCreditNote };
