import pool from '../db/connection.js';
async function getItems(codes) {
  if (!Array.isArray(codes) || codes.length === 0) { // Validasi input
    throw new Error("codes parameter is required and must be a non-empty array.");// Pastikan codes adalah array dan tidak kosong
  }

  try {
    const placeholders = codes.map(() => '?').join(','); // Buat ?,?,?... sesuai jumlah
    const query = `SELECT id,code as sku FROM ITEMS WHERE code IN (${placeholders})`;

    const [rows] = await pool.query(query, codes); // Eksekusi query dengan parameter

    if (rows.length === 0) {// Cek apakah ada hasil yang ditemukan
      throw new Error("No item found for the provided codes.");// Jika tidak ada hasil, lempar error
    }
	console.log('get', rows)
    return rows;// Kembalikan hasil query
  } catch (error) {// Tangani error jika terjadi kesalahan saat query
    console.error("Error fetching items:", error);// Log error untuk debugging
    throw error;
  }
}

export default getItems;
