// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertMoOrder(invoice, resi, status_mp, courier_name, categorized_location) {
  const query = `
    mutation CreateMoOrder(
      $invoice: String!,
      $resi: String!,
      $status_mp: String!,
      $courier_name: String!,
      $categorized_location: String!,
    ) {
      insert_mo_order(objects: {
        invoice: $invoice,
        resi: $resi,
        status_mp: $status_mp,
        courier_name: $courier_name,
        categorized_location : $categorized_location
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

  const variables = { invoice, resi, status_mp, courier_name, categorized_location};

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order.returning[0];
  } catch (error) {
    console.error('Error creating mo_order:', error);
    throw error;
  }
}

export { insertMoOrder };
