import fs from 'fs';
import XLSX from 'xlsx';
// Baca file excel
async function orderShopee(fileName) {
  const workbook = XLSX.readFile(`./assets/excel/${fileName}`);
  const sheetName = workbook.SheetNames[0];
  const worksheet = workbook.Sheets[sheetName];
  const jsonData = XLSX.utils.sheet_to_json(worksheet);
  // Proses gabungkan berdasarkan No. Pesanan
  const orders = {};
  
  jsonData.forEach((row) => {
    const noPesanan = row['No. Pesanan'];
  
    if (!orders[noPesanan]) {
      // Data utama (ambil kolom penting saja)
      orders[noPesanan] = {
        'invoice': noPesanan,
        'noResi': row['No. Resi'],
        'orderStatus': `${row['Status Pesanan']} ${row['Alasan Pembatalan']} ${row['Status Pembatalan/ Pengembalian']}`,
        'paidTime': row['Waktu Pembayaran Dilakukan'],
        'totalPembayaran': row['Total Pembayaran'],
        'username': row['Username (Pembeli)'],
        'recipient': row['Nama Penerima'],
        'shipingProvider': row['Opsi Pengiriman'],
        'ShippingFee': row['Perkiraan Ongkos Kirim'],
        items: []
      };
    }
  
    // Masukkan produk ke items
    orders[noPesanan].items.push({
      'productName': `${row['Nama Produk']} (${row['Nama Variasi']})`,
      'sku': row['Nomor Referensi SKU'],
      'variationName': row['Nama Variasi'],
      'startingPrice': Number(String(row['Harga Awal']).replace(/\./g, '')),
      'priceAfterDiscount': Number(String(row['Harga Setelah Diskon']).replace(/\./g, '')),
      'amount': Number(String(row['Jumlah']).replace(/\./g, '')),
      'totalPrice': Number(String(row['Total Harga Produk']).replace(/\./g, '')),
      'Weight': row['Berat Produk']
    });
  });
  
  // Ubah object orders menjadi array
  const result = Object.values(orders);
  
  // Tampilkan hasil
  return result
    
}

export {orderShopee};