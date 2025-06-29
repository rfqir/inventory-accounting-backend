import pkg from 'pg';
const { Pool } = pkg;

const pool = new Pool({
  user: process.env.POSTGRES_USER || 'postgres', // Ganti dengan username PostgreSQL
  host: process.env.POSTGRES_HOST || 'localhost', // Ganti dengan host PostgreSQL
  database: process.env.POSTGRES_DB || 'my_database', // Ganti dengan nama database PostgreSQL
  password: process.env.POSTGRES_PASSWORD || '', // Ganti dengan password PostgreSQL
  port: process.env.POSTGRES_PORT || 5432, // Ganti dengan port PostgreSQL
  max: 1000, // Maksimal koneksi dalam pool
});

try {
  await pool.connect();
  console.log('Terhubung ke PostgreSQL');
} catch (err) {
  console.error('Gagal koneksi ke PostgreSQL:', err);
}

export default pool;
