// graphql/queries/getMoOrder.js
import httpClient from '../client.js';

async function getMoOrder(courier_name) {
  const query = `
    query GetMoOrder($courier_name: String!) {
      mo_order(
        where: {
          courier_name: { _eq: $courier_name },
          categorized_at: { _is_null: false },
          handover_to_courier_at: {_is_null: true},
          canceled_at: {_is_null: true}
        }
      ) {
        invoice
      }
    }
  `;

  const variables = { courier_name };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.mo_order;
  } catch (error) {
    console.error('Error get mo_order:', error);
    throw error;
  }
}

export { getMoOrder };
