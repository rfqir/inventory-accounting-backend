import { Client } from 'pg';

// Ganti parameter berikut sesuai dengan pengaturan PostgreSQL Anda
const client = new Client({
  host: '192.168.1.116',
  port: 5432,
  user: 'postgres',
  password: '1234',
  database: 'db_configure'
});

client.connect()
  .then(() => {
    console.log('Koneksi ke PostgreSQL berhasil!');
    return client.end(); // Menutup koneksi setelah tes berhasil
  })
  .catch(err => {
    console.error('Gagal terkoneksi ke PostgreSQL:', err.message);
  });
