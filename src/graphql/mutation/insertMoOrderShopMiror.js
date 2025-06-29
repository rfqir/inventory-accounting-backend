// graphql/mutations/createOrder.js
import httpClient from '../client.js';

async function insertMoOrderShopMiror(invoice, resi, product_name, quantity_order, sku_toko,part_pk,quantity_convert) {
  const query = `
    mutation CreateOrder(
      $invoice: String!,
      $resi: String!,
      $product_name: String!,
      $quantity_order: Int!,
      $sku_toko: String!,
      $part_pk: Int!,
      $quantity_convert: Int!
    ) {
      insert_mo_order_shop_miror(objects: {
        invoice: $invoice,
        resi: $resi,
        product_name: $product_name,
        quantity_order: $quantity_order,
        sku_toko: $sku_toko,
        part_pk: $part_pk,
        quantity_convert : $quantity_convert
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

  const variables = { invoice, resi, product_name, quantity_order, sku_toko, part_pk,quantity_convert };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_shop_miror.returning[0];
  } catch (error) {
    console.error('Error creating order:', error);
    throw error;
  }
}

export { insertMoOrderShopMiror };