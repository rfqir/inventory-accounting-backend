import httpClient from '../../../utils/httpClient.js';

async function getItems(codeItems) {
    // Check if codeItems is an array
    if (!Array.isArray(codeItems)) {
        throw new Error('codeItems should be an array');
    }

    // Use Promise.all to fetch all items concurrently
    const itemIds = await Promise.all(
        codeItems.map(async (codeItem) => {
            const response = await httpClient.get(`/items?search_keyword=${codeItem}`);
            const item = response.data.items.find(i => i.code === codeItem);
            return item ? item.id : null;
        })
    );

    return itemIds.filter(id => id !== null); // Remove null values
}

export { getItems };
