// graphql/mutations/insertRefill.js
import httpClient from '../client.js';
function getCurrentDateTimeString() {
  const now = new Date();

  const pad = (n) => n.toString().padStart(2, '0');

  const year = now.getFullYear();
  const month = pad(now.getMonth() + 1);  // Januari = 0
  const day = pad(now.getDate());
  const hour = pad(now.getHours());
  const minute = pad(now.getMinutes());
  const second = pad(now.getSeconds());

  return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
}

async function insertRefill(proc_inst_id, sku, part_id, destination_location_id,quantity_request) {
  const created_at = getCurrentDateTimeString();
  const created_by = 'System'; // Assuming this is a constant value for created by
  const refill_type = 'summary'; // Assuming this is a constant value for refill type
  const query = `
    mutation insertRefill(
      $proc_inst_id: String!,
      $sku: String!,
      $part_id: Int!,
      $destination_location_id: Int!,
      $quantity_request: Int!,
      $created_at: timestamp!,
      $refill_type: String!,
      $created_by: String!
    ) {
      insert_mo_refill(objects: {
        proc_inst_id: $proc_inst_id,
        sku: $sku,
        part_id: $part_id,
        destination_location_id: $destination_location_id,
        quantity_request: $quantity_request,
        created_at: $created_at,
        refill_type: $refill_type,
        created_by: $created_by
      }) {
        returning {
          sku
        }
      }
    }
  `;

  const variables = { proc_inst_id, sku, part_id, destination_location_id,quantity_request, created_at, refill_type,created_by };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_refill.returning[0];
  } catch (error) {
    console.error('Error creating order:', error);
    throw error;
  }
}

export { insertRefill };