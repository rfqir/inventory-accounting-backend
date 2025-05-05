import fs from 'fs';
import XLSX from 'xlsx';
console.log(await orderShopee('Order.all.20250426_20250429.xlsx'));
// Baca file excel
async function orderShopee(fileName) {
  const workbook = XLSX.readFile(`./assets/excel/${fileName}`);
  const sheetName = workbook.SheetNames[0];
  const worksheet = workbook.Sheets[sheetName];
  const jsonData = XLSX.utils.sheet_to_json(worksheet);
  
  // Proses gabungkan berdasarkan No. Pesanan
  const orders = {};
  
  jsonData.forEach((row) => {
    const noPesanan = row['Order/adjustment ID  '];
  
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
        items: []
      };
    }
  
    // Masukkan produk ke items
    orders[noPesanan].items.push({
      'productName': row['Nama Produk'],
      'sku': row['Nomor Referensi SKU'],
      'variationName': row['Nama Variasi'],
      'startingPrice': row['Harga Awal'],
      'priceAfterDiscount': row['Harga Setelah Diskon'],
      'amount': row['Jumlah'],
      'totalPrice': row['Total Harga Produk'],
      'Weight': row['Berat Produk']
    });
  });
  
  // Ubah object orders menjadi array
  const result = Object.values(orders);
  
  // Tampilkan hasil
  return JSON.stringify(result, null, 2);
    
}

export {orderShopee};