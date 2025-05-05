import httpClient from '../../../utils/httpClient.js';

async function opened(id) {
    try {
        const response = await httpClient.post(`/sales/credit_notes/${id}/open`);
        return response;
    } catch (error) {
        throw error; 
    }
}

export { opened };
