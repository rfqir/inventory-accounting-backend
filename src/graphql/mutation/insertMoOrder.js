// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertMoOrder(invoice, resi, status_mp, courier_name) {
  const query = `
    mutation CreateMoOrder(
      $invoice: String!,
      $resi: String!,
      $status_mp: String!,
      $courier_name: String!
    ) {
      insert_mo_order(objects: {
        invoice: $invoice,
        resi: $resi,
        status_mp: $status_mp,
        courier_name: $courier_name,
      }) {
        returning {
          invoice
          resi
          status_mp
          courier_name
        }
      }
    }
  `;

  const variables = { invoice, resi, status_mp, courier_name};

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order.returning[0];
  } catch (error) {
    console.error('Error creating mo_order:', error);
    throw error;
  }
}

export { insertMoOrder };
