import httpClient from '../../../utils/httpClient.js';

async function delivered(id) {
    try {
        const response = await httpClient.post(`/sales/invoices/${id}/deliver`);
        return response;
    } catch (error) {
        throw error; 
    }
}

export { delivered };
