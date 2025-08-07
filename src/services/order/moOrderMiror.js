import {getInvoicesMiror,insertMoOrderMiror,updateStatusMiror} from '../../postgresql/controller/moOrderMiror.js';

async function moOrderMiror(invoice, noResi, orderStatus, paidTime, RTSTime, shipingProvider) {
  try {
    // Normalisasi paidTime dan RTSTime agar aman dari null/undefined/spasi
    const paid = (paidTime || '').trim();
    const rts = (RTSTime || '').trim();

    // Skip jika paid dan rts keduanya "-"
    const isValidDate = (val) => val !== '-' && !isNaN(Date.parse(val));

if (!isValidDate(paid) && !isValidDate(rts)) {
  console.log(`[Skip Invalid Date] ${invoice}`);
  return;
}


    // Skip jika status batal/cancel
          if (orderStatus.toLowerCase().includes('batal')) {
      const invoiceExists = await getInvoicesMiror(invoice);
      if (invoiceExists) {
        console.log(`[Skip Invoice Exists] ${invoice}`);
        await updateStatusMiror(invoice, orderStatus);
        return;
      }
      return;
    }

    const invoiceExists = await getInvoicesMiror(invoice);
    if (invoiceExists) {
      console.log(`[Skip Invoice Exists] ${invoice}`);
      await updateStatusMiror(invoice, orderStatus);
      return;
    }

    // Insert jika tidak batal/cancel dan ada minimal salah satu paidTime atau RTSTime valid
    await insertMoOrderMiror(invoice, noResi, orderStatus, paidTime, RTSTime, shipingProvider);
    
  } catch (error) {
    console.error(`[Error moOrderMiror] ${invoice}:`, error);
  }
}

export { moOrderMiror };
