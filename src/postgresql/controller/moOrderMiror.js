import pool from '../db/mo.js';

async function getInvoicesMiror(invoice) {
  if (!invoice) return false;

  try {
    const query = `SELECT invoice FROM mo_order_miror WHERE invoice = $1`;
    const result = await pool.query(query, [invoice]);
    return result.rowCount > 0; // langsung return true/false
  } catch (error) {
    console.error("Error fetching invoice:", error);
    throw error;
  }
}




async function insertMoOrderMiror(invoices,resi,status,paidTime,RTSTime, shipingProvider) {
  try {
    const query = `INSERT INTO mo_order_miror (invoice, resi, status,paid_time,rts_time,courier_name) VALUES ($1, $2, $3, $4,$5,$6)`;
    const values = [invoices, resi, status, paidTime,RTSTime,shipingProvider];
    const result = await pool.query(query, values);
    // Kembalikan sebagai Set agar cepat saat pengecekan
    return result.rowCount > 0 ? 'success' : 'failed';
  } catch (error) {
    console.error("Error fetching invoices:", error);
    throw error;
  }
}

async function updateStatusMiror(invoice, status ) {
  try {
    const updateQuery = `UPDATE mo_order_miror SET status = $1 WHERE invoice = $2`;
    const values = [status, invoice];
    const result = await pool.query(updateQuery, values);
    return result.rowCount > 0 ? 'success' : 'failed';
  } catch (error) {
    console.error("Error updating status in mo_order_miror:", error);
    throw error;
  }
}
export { getInvoicesMiror, insertMoOrderMiror,updateStatusMiror};
