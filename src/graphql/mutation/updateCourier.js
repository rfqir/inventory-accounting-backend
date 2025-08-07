// graphql/mutations/updateOrder.js
import httpClient from '../client.js';
import { httpClient as httpCamunda } from '../../utils/camundaClient.js';

async function updateCourierName(courier_name, invoice) {
  try {
    const mutationGetCourier = `
      query getCourierName($courier_name: String!, $invoice: String!) {
        mo_order(
          where: {
            invoice: { _eq: $invoice },
            courier_name: { _eq: $courier_name }
          }
        ) {
          proc_inst_id
        }
      }`;

    const variablesOrder = { courier_name, invoice };
    const courierResponse = await httpClient.post('', {
      query: mutationGetCourier,
      variables: variablesOrder,
    });

    const moOrderList = courierResponse.data?.data?.mo_order || [];

    if (moOrderList.length === 0) {
      try {
        const mutationOrder = `
          mutation updateCourierName($courier_name: String!, $invoice: String!) {
            update_mo_order(
              _set: { courier_name: $courier_name },
              where: { invoice: { _eq: $invoice } }
            ) {
              returning {
                proc_inst_id
              }
            }
          }`;

        const updateResponse = await httpClient.post('', {
          query: mutationOrder,
          variables: variablesOrder,
        });

        if (updateResponse.data.errors) {
          console.error('GraphQL error (order):', updateResponse.data.errors);
          throw new Error('Failed to update mo_order');
        }

        const procInstId = updateResponse.data.data.update_mo_order.returning[0]?.proc_inst_id;
        if (procInstId) {
          try {
            const data = {
              value: courier_name,
              type: "String",
            };
            await httpCamunda.post(`/process-instance/${procInstId}/variables/courier_name`, data);
          } catch (error) {
            console.error('Error updating courier name in camunda:', error);
            throw error;
          }
        }

        return {
          order: updateResponse.data.data.update_mo_order.returning,
        };
      } catch (error) {
        console.error('Error updating instance:', error);
        throw error;
      }
    } else {
      return 'Nama courier sama dengan yang ada di order';
    }
  } catch (error) {
    console.error('Error in getCourierName:', error.message);
    throw error;
  }
}

export { updateCourierName };

