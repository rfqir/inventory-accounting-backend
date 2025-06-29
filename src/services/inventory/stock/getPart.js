import httpClient from '../../../utils/httpClientInventory.js';

async function getPart(pk) {
    try {
        const response = await httpClient.get(`/stock/?location=${pk}`);
        return response.data[0];
    } catch (error) {
        console.error('getPart Error: ', error);
        throw error
    }
}

export { getPart };
