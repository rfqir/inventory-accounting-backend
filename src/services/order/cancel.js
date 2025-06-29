import { updateStatusMp } from '../../graphql/mutation/updateStatusMoOrder.js';
import { getInstantMoOrder } from '../../graphql/query/getInvoice.js';
import { cancelOrder } from '../../camunda/signal/cancelOrder.js';

async function cancelInvoice(invoice, status) {
  try {
    const result = await getInstantMoOrder(invoice);

    if (!result || !result.proc_inst_id) {
      console.warn(`No instance found for cancel invoice: ${invoice}`);
      return null;
    }

    const { proc_inst_id: instanceId } = result;

    await updateStatusMp(status, invoice);
    const signalResult = await cancelOrder(invoice);

    return signalResult;
  } catch (error) {
    console.error('Error in cancelInvoice:', error);
    throw error;
  }
}


export { cancelInvoice };
