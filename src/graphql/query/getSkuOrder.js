// graphql/queries/getMoOrder.js
import httpClient from '../client.js';

async function getSkuOrder(sku) {
  const query = `
    query GetSkuOrder($sku: String!) {
      mo_order_shop_aggregate(
        where: {
          sku_toko: { _eq: $sku },
          picked_status: { _is_null: true }
        }
      ) {
        aggregate {
          sum {
            quantity_convert
          }
        }
      }
    }
  `;

  const variables = { sku };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.mo_order_shop_aggregate.aggregate.sum.quantity_convert || 0;
  } catch (error) {
    console.error('Error get mo_order:', error);
    throw error;
  }
}

export { getSkuOrder };
