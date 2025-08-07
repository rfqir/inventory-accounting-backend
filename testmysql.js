import mysql from 'mysql2/promise';

// Konfigurasi koneksi
const client = mysql.createPool({
  host:  '192.168.1.102',
  port:  3306,
  user:  'bigcapital',
  password:  'B!gc4p!t4l',
  database:  'bigcapital_tenant_405ope1md3wigc5',
  waitForConnections: true,
  connectionLimit: 30, // Gunakan angka realistis, 30000 terlalu besar
  queueLimit: 0
});

// Tes koneksi dengan query sederhana
async function testConnection() {
  try {
    const [rows] = await client.query('SELECT 1');
    console.log('Koneksi ke MySQL berhasil!');
  } catch (err) {
    console.error('Gagal terkoneksi ke MySQL:', process.env.MYSQL_HOST, err.message);
  }
}

testConnection();

export default client;

