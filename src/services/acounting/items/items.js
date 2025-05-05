import httpClient from '../../../utils/httpClient.js';

async function getItems(codeItem) {
    const response = await httpClient.get(`/items?search_keyword=${codeItem}`);
    const item = response.data.items.find(i => i.code === codeItem);
    return item.id;

}

export { getItems };
