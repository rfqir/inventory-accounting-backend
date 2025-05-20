// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertMoOrderCost(invoice, provider_fee) {
  const query = `
    mutation CreateMoOrderCost(
      $invoice: String!,
      $provider_fee: String!,
    ) {
      insert_mo_order_cost(objects: {
        invoice: $invoice,
        provider_fee: $provider_fee,
      }) {
        returning {
          invoice
        }
      }
    }
  `;

  const variables = { invoice, provider_fee};

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_cost.returning[0];
  } catch (error) {
    console.error('Error creating mo_order_cost:', error);
    throw error;
  }
}

export { insertMoOrderCost };
