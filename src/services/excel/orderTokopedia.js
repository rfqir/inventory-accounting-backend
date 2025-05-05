import fs from 'fs';
import XLSX from 'xlsx';

// Baca file excel
async function orderTokopedia(fileName) {
  const workbook = XLSX.readFile(`./assets/excel/${fileName}`);
  const sheetName = workbook.SheetNames[0];
  const worksheet = workbook.Sheets[sheetName];
  const jsonData = XLSX.utils.sheet_to_json(worksheet);
  
  // Proses gabungkan berdasarkan No. Pesanan
  const orders = {};
  
  jsonData.forEach((row) => {
    const noPesanan = row['Order ID'];
  
    if (!orders[noPesanan]) {
      // Data utama (ambil kolom penting saja)
      orders[noPesanan] = {
        'Order ID': noPesanan,
        'Tracking ID': row['Tracking ID'],
        'Order Status': row['Order Status'],
        'Order Substatus': row['Order Substatus'],
        'Cancelation/Return Type': row['Cancelation/Return Type'],
        'Paid Time': row['Paid Time'],
        'Delivery Option': row['Delivery Option'],
        'Delivery Option': row['Delivery Option'],
        'Shipping Provider Name': row['Shipping Provider Name'],
        'Buyer Username': row['Buyer Username'],
        'Recipient': row['Recipient'],
        'Regency and City': row['Regency and City'],
        items: []
      };
    }
  
    // Masukkan produk ke items
    orders[noPesanan].items.push({
      'SKU Induk': row['SKU Induk'],
      'Product Name': row['Product Name'],
      'Seller SKU': row['Seller SKU'],
      'Variation': row['Variation'],
      'SKU Unit Original Price': row['SKU Unit Original Price'],
      'Harga Setelah Diskon': row['Harga Setelah Diskon'],
      'Quantity': row['Quantity'],
      'SKU Subtotal After Discount': row['SKU Subtotal After Discount'],
      'Weight(kg)': row['Weight(kg)']
    });
  });
  
  // Ubah object orders menjadi array
  const result = Object.values(orders);
  
  // Tampilkan hasil
  return result;
    
}

export {orderTokopedia};