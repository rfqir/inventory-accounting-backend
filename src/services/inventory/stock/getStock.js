import httpClient from '../../../utils/httpClientInventory.js';

async function getStock(pk) {
    const response = await httpClient.get(`/stock/?location=${pk}`);
    console.log("res: ", response.data[0]);
    
    return response.data[0];

}

export { getStock };
