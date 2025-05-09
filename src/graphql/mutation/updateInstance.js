// graphql/mutations/updateOrder.js
import httpClient from '../client.js';

async function updateInstance(proc_inst_id, processDefinitionKey, invoice) {
  const mutationShop = `
    mutation UpdateOrderShop($proc_inst_id: String!, $invoice: String!) {
      update_mo_order_shop(
        _set: { proc_inst_id: $proc_inst_id },
        where: { invoice: { _eq: $invoice } }
      ) {
        returning {
          id_shop
          proc_inst_id
          invoice
        }
      }
    }
  `;

  const variablesShop = { proc_inst_id, invoice };

  try {
    const shopResponse = await httpClient.post('', {
      query: mutationShop,
      variables: variablesShop,
    });

    if (shopResponse.data.errors) {
      console.error('GraphQL error (shop):', shopResponse.data.errors);
      throw new Error('Failed to update mo_order_shop');
    }

    const mutationOrder = `
      mutation UpdateOrder($proc_inst_id: String!, $proc_def_key: String!, $invoice: String!) {
        update_mo_order(
          _set: { proc_inst_id: $proc_inst_id, proc_def_key: $proc_def_key },
          where: { invoice: { _eq: $invoice } }
        ) {
          returning {
            proc_inst_id
            proc_def_key
            invoice
          }
        }
      }
    `;

    const variablesOrder = { proc_inst_id, proc_def_key: processDefinitionKey, invoice };

    const orderResponse = await httpClient.post('', {
      query: mutationOrder,
      variables: variablesOrder,
    });

    if (orderResponse.data.errors) {
      console.error('GraphQL error (order):', orderResponse.data.errors);
      throw new Error('Failed to update mo_order');
    }

    return {
      shop: shopResponse.data.data.update_mo_order_shop.returning,
      order: orderResponse.data.data.update_mo_order.returning,
    };
  } catch (error) {
    console.error('Error updating instance:', error);
    throw error;
  }
}

export { updateInstance };
