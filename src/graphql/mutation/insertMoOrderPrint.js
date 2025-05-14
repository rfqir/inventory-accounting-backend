// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertMoOrderPrint(invoice, proc_inst_id,courier_name) {
  const query = `
    mutation CreateMoOrderPrint(
      $invoice: String!,
      $proc_inst_id: String!,
      $courier_name: String!
    ) {
      insert_mo_order_print(objects: {
        invoice: $invoice,
        proc_inst_id: $proc_inst_id,
        courier_name: $courier_name
      }) {
        returning {
          invoice
        }
      }
    }
  `;

  const variables = { invoice, proc_inst_id,courier_name};

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_print.returning[0];
  } catch (error) {
    console.error('Error creating mo_order_print:', error);
    throw error;
  }
}

export { insertMoOrderPrint };
