import httpClient from '../../../utils/httpClient.js';

function addDays(dateStr, days) {
    const date = new Date(dateStr);
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];  // Format YYYY-MM-DD
}

async function createPaymentReceive(customerId, paymentDate, paymentAmount, coaId, invoiceId) {

    console.log('Creating payment invoice: ' + invoiceId);
    const data = {
        customer_id: customerId,
        payment_date: paymentDate,
        deposit_account_id: coaId,
        amount: paymentAmount,
        entries: [
            {
                invoice_id:invoiceId,
                payment_amount:paymentAmount
            }
        ]
    };

    try {
        const response = await httpClient.post('/sales/payment_receives', data);
        
        if (response && response.status === 200) {
            return response.data;  // Assuming the data is returned in the 'data' property
        } else {
            console.error('Failed to create invoice, status:', response.data);
            throw new Error('Invoice creation failed gagal');
        }
    } catch (error) {
        console.error('Error creating invoicess:', error);
        throw error;  // Rethrow the error to propagate it
    }
}

export { createPaymentReceive };
