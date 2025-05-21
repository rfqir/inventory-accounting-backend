// graphql/queries/getMoOrder.js
import httpClient from '../client.js';

async function getInstantMoOrder(invoice) {
  const query = `
    query getInstantMoOrder($invoice: String!) {
      mo_order(
        where: {
          invoice: { _eq: $invoice }
        }
      ) {
        proc_inst_id
      }
    }
  `;

  const variables = { invoice };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.mo_order[0];
  } catch (error) {
    console.error('Error get mo_order:', error);
    throw error;
  }
}

export { getInstantMoOrder };
