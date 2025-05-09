import httpClient from '../../../utils/httpClientInventory.js';

async function getLocation(locationName) {
    const response = await httpClient.get(`/stock/location/?name=${locationName}`);
    const location = response.data.find(i => i.name === locationName);
    return location.pk;

}

export { getLocation };
