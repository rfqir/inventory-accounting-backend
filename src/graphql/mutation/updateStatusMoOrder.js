// graphql/mutations/updateOrder.js
import httpClient from '../client.js';

async function updateStatusMp(status_mp, invoice) {
  try {

    const mutationOrder = `
      mutation updateStatusMp($status_mp: String!, $invoice: String!) {
        update_mo_order(
          _set: { status_mp: $status_mp },
          where: { invoice: { _eq: $invoice } }
        ) {
          returning {
            status_mp
            invoice
          }
        }
      }
    `;

    const variablesOrder = { status_mp, invoice };

    const orderResponse = await httpClient.post('', {
      query: mutationOrder,
      variables: variablesOrder,
    });

    if (orderResponse.data.errors) {
      console.error('GraphQL error (order):', orderResponse.data.errors);
      throw new Error('Failed to update mo_order');
    }

    return {
      order: orderResponse.data.data.update_mo_order.returning,
    };
  } catch (error) {
    console.error('Error updating instance:', error);
    throw error;
  }
}

export { updateStatusMp };
