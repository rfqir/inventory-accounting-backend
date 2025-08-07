// graphql/mutations/createOrder.js
import httpClient from '../client.js';

async function insertMoOrderShop(invoice, resi, product_name, quantity_order, sku_toko,part_pk,quantity_convert) {
if (!resi) {
  resi = '-';
}
if (!part_pk){
part_pk = 0;
}
  const unique_id = `${invoice}-${sku_toko}-${product_name.slice(-20)}`;
  const query = `
    mutation CreateOrder(
      $invoice: String!,
      $resi: String!,
      $product_name: String!,
      $quantity_order: Int!,
      $sku_toko: String!,
      $part_pk: Int!,
      $quantity_convert: Int!,
      $unique_id: String!
    ) {
      insert_mo_order_shop(objects: {
        invoice: $invoice,
        resi: $resi,
        product_name: $product_name,
        quantity_order: $quantity_order,
        sku_toko: $sku_toko,
        part_pk: $part_pk,
        quantity_convert : $quantity_convert,
        unique_id: $unique_id
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

  const variables = { invoice, resi, product_name, quantity_order, sku_toko, part_pk,quantity_convert, unique_id };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_order_shop.returning[0];
  } catch (error) {
    console.error('Error creating order:', error);
    console.error('var ',variables)
    throw error;
  }
}

export { insertMoOrderShop };
