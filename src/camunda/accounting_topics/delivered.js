import client, { handleFailureDefault } from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { markInvoiceDelivered, getInvoicesId } from '../../mysql/controllers/invoice.js';
import { delivered } from '../../services/acounting/sales_invoice/delivered.js';

// Daftar invoice yang langsung dianggap selesai
const directCompleteInvoices = new Set([
  '579241857918338966',
  '579251514137544035',
  '579306278885623377',
  '579311096482792468',
  '579324639593465852',
  '579331287769384924',
  '579353106193090263',
  '579374918397232546',
  '579391226206521011',
  '579393135251784947',
  '579409164937954796',
  '579420890055477097',
  '579520711189759385',
  '579529823977571573',
  '579543714758166150',
  '579570123800282785',
  '579596721341957423',
  '579593918034052201',
  '579603442588878322',
  '579623189954528815',
  '579637464399971887',
  '579639906279326977',
  '579696752690431767',
  '579721887095424477',
  '579733793530611517',
  '579741975752705584',
  '579739921216800338',
  '579751017043035814',
  '579765319049643840'
]);

client.subscribe('deliveredInvoice', {
  lockDuration: 10000
}, async ({ task, taskService }) => {
  const invoice = task.variables.get('invoice');

  try {
    // Langsung complete jika invoice ada dalam daftar
    if (directCompleteInvoices.has(invoice)) {
      console.log(`Invoice ${invoice} langsung di-complete.`);
      await taskService.complete(task);
      return;
    }

    const invoiceIdSet = await getInvoicesId(invoice);

    // Cek jika Set kosong
    if (!invoiceIdSet || invoiceIdSet.size === 0) {
      throw new Error(`Invoice ID tidak ditemukan untuk invoice: ${invoice}`);
    }

    console.log('invoiceIdSet:', invoiceIdSet);

    for (const id of invoiceIdSet) {
      const responseDelivered = await delivered(id);
      console.log(`Delivered processed for ID ${id}:`);
    }

    await taskService.complete(task);

  } catch (error) {
    console.error('Error saat memproses deliveredInvoice:', error.message);
    await handleFailureDefault(taskService, task, error);
  }
});

