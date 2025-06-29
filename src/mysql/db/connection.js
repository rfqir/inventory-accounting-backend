import mysql  from'mysql2/promise';

// Konfigurasi koneksi
const pool = mysql.createPool({
  host: process.env.MYSQL_HOST || 'localhost', // ganti dengan host database kamu
  port: process.env.MYSQL_PORT || 3306,// ganti dengan port database kamu
  user: process.env.MYSQL_USER || 'root', // ganti dengan username kamu
  password: process.env.MYSQL_PASSWORD || '', // ganti dengan password kamu
  database: process.env.MYSQL_DATABASE || 'my_database', // ganti dengan nama database kamu
  waitForConnections: true, // Tunggu koneksi yang tersedia
  connectionLimit: 100, // Batasi jumlah koneksi yang dibuat
  queueLimit: 0 // Tidak ada batasan antrian koneksi
});

export default pool;
// Export pool untuk digunakan di file lain
