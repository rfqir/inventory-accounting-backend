// graphql/queries/getMoOrder.js
import httpClient from '../client.js';

async function getMiror(invoice, sku_toko) {
  const query = `
    query getMiror($invoice: String!, $sku_toko: String!) {
      mo_order_shop_miror(
        where: {
          invoice: { _eq: $invoice },
          sku_toko:{ _eq: $sku_toko }
        }
      ) {
        invoice
      }
    }
  `;

  const variables = { invoice, sku_toko };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.mo_order_shop_miror[0];
  } catch (error) {
    console.error('Error get mo_order:', error);
    throw error;
  }
}

export { getMiror };
