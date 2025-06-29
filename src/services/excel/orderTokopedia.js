import fs from 'fs';
import XLSX from 'xlsx';

function formatDateToYMD(dateString) {
  if (typeof dateString !== 'string' || !dateString.includes('/')) {
    console.warn('Invalid date format:', dateString);
    return null;
  }

  const [datePart] = dateString.split(' ');
  const [day, month, year] = datePart.split('/');

  if (!day || !month || !year) {
    console.warn('Incomplete date parts:', datePart);
    return null;
  }

  return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} 00:00`;
}

// Baca file excel
async function orderTokopedia(fileName) {
  const workbook = XLSX.readFile(`../portal-mirorim/server-doc/${fileName}`);
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
        'orderStatus': `tokopedia ${row['Order Status']} ${row['Order Substatus']} ${row['Cancelation/Return Type']}`,
        'paidTime': row['Paid Time'] ? formatDateToYMD(row['Paid Time']) : null,
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
      'productName': `${row['Product Name']} (${row['Variation']})`,
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
