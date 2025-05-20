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
        'invoice': noPesanan,
        'noResi': row['Tracking ID'],
        'orderStatus': `${row['Order Status']} ${row['Order Substatus']} ${row['Cancelation/Return Type']}`,
        'paidTime': row['Paid Time'],
        'totalPembayaran': row['Order Amount'],
        'username': row['Buyer Username'],
        'recipient': row['Recipient'],
        'shipingProvider': `${row['Delivery Option']} ${row['Shipping Provider Name']}`,
        'ShippingFee': row['Original Shipping Fee'],
        items: []
      };
    }
  
    // Masukkan produk ke items
    orders[noPesanan].items.push({
      'productName': row['Product Name'],
      'sku': row['Seller SKU'],
      'variationName': row['Variation'],
      'startingPrice': row['SKU Unit Original Price'],
      'priceAfterDiscount': row['SKU Unit Original Price'] - row['SKU Platform Discount'],
      'amount': row['Quantity'],
      'totalPrice': row['SKU Subtotal Before Discount'],
      'Weight(kg)': row['Weight(kg)']
    });
  });
  
  // Ubah object orders menjadi array
  const result = Object.values(orders);
  
  // Tampilkan hasil
  return result;
    
}

export {orderTokopedia};