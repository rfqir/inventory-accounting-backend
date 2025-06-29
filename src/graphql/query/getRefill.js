// graphql/queries/getMoOrder.js
import httpClient from '../client.js';
function getCurrentDate() {
  const now = new Date();

  const pad = (n) => n.toString().padStart(2, '0');

  const year = now.getFullYear();
  const month = pad(now.getMonth() + 1);  // Januari = 0
  const day = pad(now.getDate());

  return `${year}-${month}-${day}`;
}

async function getRefill(sku) {
  const gte = `${getCurrentDate()} 00:00:00`;
  const lt = `${getCurrentDate()} 23:59:59`;
  const query = `
    query getRefill($gte: timestamp!,$lt: timestamp!, $sku: String!) {
      mo_refill(
        where: {
          created_at: {
        _gte: $gte,
        _lt: $lt
      },
          sku:{ _eq: $sku }
        }
      ) {
        sku
      }
    }
  `;

  const variables = { gte,lt, sku };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.mo_refill[0];
  } catch (error) {
    console.error('Error get mo_order:', error);
    throw error;
  }
}

export { getRefill };
