import httpClient from '../../../utils/httpClientInventory.js';

async function getStock(pk) {
    const response = await httpClient.get(`/stock/?location=${pk}`);
    return response.data[0];

}

export { getStock };
