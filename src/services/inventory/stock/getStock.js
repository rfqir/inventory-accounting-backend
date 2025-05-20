import httpClient from '../../../utils/httpClientInventory.js';

async function getStock(pk) {
    try {
        const response = await httpClient.get(`/stock/?location=${pk}`);
        return response.data[0];
    } catch (error) {
        console.error('getStock Error: ', error);
        throw error
    }

}

export { getStock };
