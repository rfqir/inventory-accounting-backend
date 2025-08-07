// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertStockItems(invoice,part_pk, quantity, stock) {
  const query = `
    mutation CreateMoOrderStockItems(
      $invoice: String!,
      $part_pk: Int!,
      $quantity: Int!,
      $stock: Int!
    ) {
      insert_mo_order_stock_items(objects: {
        invoice: $invoice,
        part_pk: $part_pk,
        quantity: $quantity,
        stock: $stock
      }) {
        returning {
          invoice
        }
      }
    }
  `;

  const variables = { invoice, part_pk, quantity, stock };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_stock_items.returning[0];
  } catch (error) {
    console.error('Error creating mo_order_stock_items:', error);
    throw error;
  }
}

export { insertStockItems };
