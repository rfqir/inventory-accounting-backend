import httpClient from '../../../utils/httpClient.js';

async function getCredit(invoice) {
    try {
        const response = await httpClient.get(`/sales/credit_notes?search_keyword=${invoice}`);
        const creditNoteData = response.data.credit_notes.find(i => i.reference_no === invoice);
        
        return creditNoteData ?? null;
    } catch (error) {
        throw error;
    }
}

export { getCredit };
