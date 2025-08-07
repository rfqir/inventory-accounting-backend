import pkg from 'pg';
const { Pool } = pkg;

const pool = new Pool({
  user: process.env.MO_USER || 'CAMUNDA', // Ganti dengan username CAMUNDAQL
  host: process.env.MO_HOST || 'localhost', // Ganti dengan host CAMUNDAQL
  database: process.env.MO_DATABASE || 'my_database', // Ganti dengan nama database CAMUNDAQL
  password: `${process.env.MO_PASSWORD}` || '', // Ganti dengan password CAMUNDAQL
  port: process.env.MO_PORT || 5432, // Ganti dengan port CAMUNDAQL
  max: 1000, // Maksimal koneksi dalam pool
});

try {
  await pool.connect();
  console.log('Terhubung ke CAMUNDADB');
} catch (err) {
  console.error('Gagal koneksi ke CAMUNDADB:', err);
}

export default pool;
