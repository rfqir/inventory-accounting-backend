import httpClient from '../../../utils/httpClientInventory.js';

async function getStock(pk) {
    try {
        const response = await httpClient.get(`/stock/?location=${pk}`);
        const data = response.data;
        const totalQuantity = data.reduce((sum, item) => sum + item.quantity, 0);
        return totalQuantity;
    } catch (error) {
        console.error('getStock Error: ', error);
        throw error
    }

}

export { getStock };
