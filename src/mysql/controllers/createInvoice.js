import pool from "../db/connection.js"; // sesuaikan dengan koneksi DB kamu

function getCurrentDateTimeString() {
  const now = new Date();

  const pad = (n) => n.toString().padStart(2, '0');

  const year = now.getFullYear();
  const month = pad(now.getMonth() + 1);  // Januari = 0
  const day = pad(now.getDate());
  const hour = pad(now.getHours());
  const minute = pad(now.getMinutes());
  const second = pad(now.getSeconds());

  return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
}

export async function insertSalesInvoiceAndItems({
  customerId, invoiceDate, invoiceNo, referenceNo, duedate,entries // entries: [{item_id, quantity, rate}]
}) {
console.log('invoiceNo: ',invoiceNo)
console.log('entries: ',entries)
  const createdAt = getCurrentDateTimeString();
  const dueDateStr = duedate;

  // Hitung balance = total dari quantity * rate
  const balance = entries.reduce((sum, e) => sum + e.quantity * e.rate, 0);

  // Insert SALES_INVOICES
  const salesInvoiceInsertQuery = `
  INSERT INTO SALES_INVOICES (
    customer_id, invoice_date, due_date, invoice_no, reference_no, balance,
    currency_code, EXCHANGE_RATE, DISCOUNT_TYPE, USER_ID, CREATED_AT, IS_INCLUSIVE_TAX, PDF_TEMPLATE_ID
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
`;

  const salesInvoiceValues = [
    customerId,
    invoiceDate,
    dueDateStr,
    invoiceNo,
    referenceNo,
    balance,
    'IDR',
    1,
    'amount',
    1,
    createdAt,
    1,
    1
  ];

  const [result] = await pool.execute(salesInvoiceInsertQuery, salesInvoiceValues);
  const salesInvoiceId = result.insertId;

  // Insert ITEM_ENTRIES dengan sell_account_id = 1025
  for (let i = 0; i < entries.length; i++) {
    const e = entries[i];
    const itemEntryInsertQuery = `
    INSERT INTO ITEMS_ENTRIES (
        REFERENCE_TYPE, REFERENCE_ID, \`INDEX\`, ITEM_ID, QUANTITY, RATE, SELL_ACCOUNT_ID, CREATED_AT, IS_INCLUSIVE_TAX
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    const itemEntryValues = [
      'SaleInvoice',
      salesInvoiceId,
      i + 1,
      e.item_id,
      e.quantity,
      e.rate,
      1025,         // fixed sell_account_id
      createdAt,
      1
    ];

    await pool.execute(itemEntryInsertQuery, itemEntryValues);
  }

  return salesInvoiceId;
}
