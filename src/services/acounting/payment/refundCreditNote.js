import httpClient from '../../../utils/httpClient.js';

function addDays(dateStr, days) {
    const date = new Date(dateStr);
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];  // Format YYYY-MM-DD
}

async function refundCreditNote(creditNoteId, refundDate, amount, invoice) {

    console.log('Creating refud for creditNoteId: ' + creditNoteId);
    const data = {
        from_account_id: 1000,
        reference_no: invoice,
        amount: amount,
        date: refundDate
    };

    try {
        const response = await httpClient.post(`/sales/credit_notes/${creditNoteId}/refund`, data);
        
        if (response && response.status === 200) {
            console.log('closed credit Note: ' + creditNoteId);
            
            return response.data;  // Assuming the data is returned in the 'data' property
        } else {
            // console.error('Failed to create invoice, status:', response.status);
            throw new Error('Invoice creation failed');
        }
    } catch (error) {
        console.error('Error creating invoice:');
        throw error;  // Rethrow the error to propagate it
    }
}

export { refundCreditNote };
