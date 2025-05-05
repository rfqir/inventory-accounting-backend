import httpClient from '../../../utils/httpClient.js';

async function getCoa(accountName) {
    const response = await httpClient.get(`/accounts?search_keyword=${accountName}`);
    const account = response.data.accounts.find(i => i.name === accountName);
    return account.id;

}

export { getCoa };
