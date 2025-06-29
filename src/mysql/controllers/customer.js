import pool from '../db/connection.js';
import httpClient from '../../utils/httpClient.js'; 

/**
 * Mendapatkan ID customer berdasarkan display_name, atau membuatnya jika belum ada.
 * @param {string} customerName - Nama pelanggan.
 * @returns {Promise<string>} - ID pelanggan.
 */
export async function getCustomers(customerName) {
  if (typeof customerName !== 'string' || customerName.trim() === '') {
    console.warn("Parameter 'customerName' harus berupa string dan tidak boleh kosong.");
    customerName = 'Anonym';
  }

  try {
    const query = `SELECT id FROM CONTACTS WHERE display_name = ?`;
    const [rows] = await pool.query(query, [customerName]);

    if (rows.length === 0) {
      // Jika tidak ditemukan, buat customer baru
      const data = {
        customer_type: "individual",
        display_name: customerName
      };

      const responsePost = await httpClient.post('/customers', data);
      return responsePost.data.id;
    }

    return rows[0].id; // Ambil ID dari hasil query
  } catch (error) {
    console.error("Error fetching or creating customer:", error);
    throw error;
  }
}
