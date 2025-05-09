// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertStockCheck(invoice,proc_inst_id) {
  const query = `
    mutation CreateMoOrderStockCheck(
      $invoice: String!,
      $proc_inst_id: String!
    ) {
      insert_mo_order_stock_check(objects: {
        invoice: $invoice,
        proc_inst_id: $proc_inst_id
      }) {
        returning {
          invoice
          proc_inst_id
        }
      }
    }
  `;

  const variables = { invoice, proc_inst_id};

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_stock_check.returning[0];
  } catch (error) {
    console.error('Error creating mo_order:', error);
    throw error;
  }
}

export { insertStockCheck };
