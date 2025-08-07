
import pool from "../db/connection.js";

/**
 * Mengambil daftar invoice yang ditemukan dari database.
 * @param {string[]|string} invoices - Satu invoice atau array invoice.
 * @returns {Promise<Set<string>>} - Set invoice yang ditemukan.
 */
export async function getInvoices(invoices) {
  // Normalisasi ke array
  const invoiceList = Array.isArray(invoices) ? invoices : [invoices];

  if (invoiceList.length === 0) {
    return new Set(); // Tidak ada yang perlu dicek
  }

  try {
    const placeholders = invoiceList.map(() => '?').join(',');
    const query = `SELECT invoice_no FROM SALES_INVOICES WHERE invoice_no IN (${placeholders})`;

    const [rows] = await pool.query(query, invoiceList);

    // Kembalikan sebagai Set agar cepat saat pengecekan
    return new Set(rows.map(row => row.invoice_no));
  } catch (error) {
    console.error("Error fetching invoices:", error);
    throw error;
  }
}
export async function getInvoicesId(invoices) {
  // Normalisasi ke array
  const invoiceList = Array.isArray(invoices) ? invoices : [invoices];

  if (invoiceList.length === 0) {
    return new Set(); // Tidak ada yang perlu dicek
  }

  try {
    const placeholders = invoiceList.map(() => '?').join(',');
    const query = `SELECT id FROM SALES_INVOICES WHERE invoice_no IN (${placeholders})`;

    const [rows] = await pool.query(query, invoiceList);

    // Kembalikan sebagai Set agar cepat saat pengecekan
    return new Set(rows.map(row => row.id));
  } catch (error) {
    console.error("Error fetching invoices:", error);
    throw error;
  }
}

export async function getInvoicesIdBalance(invoices) {
  // Normalisasi ke array
  const invoiceList = Array.isArray(invoices) ? invoices : [invoices];

  if (invoiceList.length === 0) {
    return new Set(); // Tidak ada yang perlu dicek
  }

  try {
    const placeholders = invoiceList.map(() => '?').join(',');
    const query = `SELECT id,invoice_no,floor(balance) as balance,invoice_date FROM SALES_INVOICES WHERE invoice_no IN (${placeholders})`;

    const [rows] = await pool.query(query, invoiceList);

    
    // Kembalikan sebagai Set agar cepat saat pengecekan
    return new Map(
  rows.map(row => [
    row.invoice_no.trim(),
    { id: row.id, balance: row.balance, invoice_date: row.invoice_date }
  ])
);

  } catch (error) {
    console.error("Error fetching invoices:", error);
    throw error;
  }
}
export async function getInvoicesDraf() {
  try {
    const query = `SELECT id FROM SALES_INVOICES WHERE delivered_at IS NULL and invoice_date < '2025-07-29' order by invoice_date asc limit 10000;`;
    const [rows] = await pool.query(query);
    // Kembalikan array, bukan Set (opsional, bisa tetap pakai Set kalau memang dibutuhkan)
    return rows.map(row => row.id);
  } catch (error) {
    console.error("Error fetching invoices:", error);
    throw error;
  }
}
export async function markInvoiceDelivered(invoice) {
  try {
    const dateNow = new Date();
    const query = `UPDATE SALES_INVOICES SET delivered_at = ? WHERE invoice_no = ?`;

    const [result] = await pool.query(query, [dateNow, invoice]);

    if (result.affectedRows === 0) {
      throw new Error(`Invoice ${invoice} tidak ditemukan atau tidak diupdate.`);
    }

    return 'success';
  } catch (error) {
    console.error("Error update invoice delivered_at:", error);
    throw error;
  }
}
