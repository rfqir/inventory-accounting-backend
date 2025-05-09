// graphql/mutations/createOrder.js
import httpClient from '../client.js';

async function insertMoOrderShop(invoice, resi, product_name, quantity_order, sku_toko) {
  const query = `
    mutation CreateOrder(
      $invoice: String!,
      $resi: String!,
      $product_name: String!,
      $quantity_order: Int!,
      $sku_toko: String!
    ) {
      insert_mo_order_shop(objects: {
        invoice: $invoice,
        resi: $resi,
        product_name: $product_name,
        quantity_order: $quantity_order,
        sku_toko: $sku_toko
      }) {
        returning {
          id_shop
          invoice
          resi
          product_name
          quantity_order
          sku_toko
        }
      }
    }
  `;

  const variables = { invoice, resi, product_name, quantity_order, sku_toko };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_shop.returning[0];
  } catch (error) {
    console.error('Error creating order:', error);
    throw error;
  }
}

export { insertMoOrderShop };
