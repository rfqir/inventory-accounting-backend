import fs from 'fs';
import XLSX from 'xlsx';
// Baca file excel
async function cashInTokopedia(fileName) {
  const workbook = XLSX.readFile(`./assets/excel/${fileName}`);
  const sheetName = workbook.SheetNames[0];
  const worksheet = workbook.Sheets[sheetName];
  const jsonData = XLSX.utils.sheet_to_json(worksheet);
  
  // Proses gabungkan berdasarkan No. Pesanan
  const orders = {};
  
  jsonData.forEach((row) => {
    const noPesanan = row['Order/adjustment ID'];
  
    if (!orders[noPesanan]) {
      // Data utama (ambil kolom penting saja)
      orders[noPesanan] = {
        'invoice': noPesanan,
        'Type': row['Type'],
        'settlementAmount': row['Total settlement amount'],
        'TotalRevenue': row['Total revenue'],
        'SellerDiscounts': row['Seller discounts'],
        'marketPlaceFee': row['TikTok Shop commission fee'],
        'sfpFee': row['SFP service fee'],
        'liveFee': row['LIVE Specials Service Fee'],
        'voucerFee': row['Voucher Xtra Service Fee'],
        'flashSaleFee': row['Brand Crazy Deals/Flash Sale service fee'],
        'cashBackFee': row['Bonus cashback service fee'],
        'paylaterFee': row['PayLater Handling Fee'],
        'adjustmentAmount': row['Adjustment amount'],
        'affiliateCommission': row['Affiliate commission']
      };
    }
  });
  
  // Ubah object orders menjadi array
  const result = Object.values(orders);
  
  // Tampilkan hasil
  return JSON.stringify(result, null, 2);
    
}

export {cashInTokopedia};