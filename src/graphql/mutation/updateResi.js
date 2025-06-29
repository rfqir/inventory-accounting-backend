// graphql/mutations/updateOrder.js
import httpClient from '../client.js';

async function updateResi(invoice, resi) {
  try {

    const mutationOrder = `
      mutation updateResi($resi: String!, $invoice: String!) {
        update_mo_order(
          _set: { resi: $resi },
          where: { invoice: { _eq: $invoice } }
        ) {
          returning {
            invoice
          }
        }
      }
    `;

    const variablesOrder = { resi, invoice };

    const orderResponse = await httpClient.post('', {
      query: mutationOrder,
      variables: variablesOrder,
    });

    if (orderResponse.data.errors) {
      console.error('GraphQL error (order):', orderResponse.data.errors);
      throw new Error('Failed to update mo_order');
    }

    
    try {
        const mutationShop = `
            mutation UpdateOrderShop($resi: String!, $invoice: String!) {
            update_mo_order_shop(
                _set: { resi: $resi },
                where: { invoice: { _eq: $invoice } }
            ) {
                returning {
                invoice
                }
            }
        }
        `;

        const variablesShop = { resi, invoice };
        const shopResponse = await httpClient.post('', {
      query: mutationShop,
      variables: variablesShop,
    });

    if (shopResponse.data.errors) {
      console.error('GraphQL error (shop):', shopResponse.data.errors);
      throw new Error('Failed to update mo_order_shop');
    }
    } catch (error) {
        console.error('Error updating resi mo_order_shop:', error);
        throw error;   
    }
    return {
      order: orderResponse.data.data.update_mo_order.returning,
    };
  } catch (error) {
    console.error('Error updating resi mo_order:', error);
    throw error;
  }
}

export { updateResi };
