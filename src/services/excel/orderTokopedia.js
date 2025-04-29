import * as XLSX from 'xlsx';  // Misalnya menggunakan 'xlsx' library

/**
 * Fungsi untuk membaca data dari file Excel
 * @param {string} filePath - Path ke file Excel
 * @returns {object} - Data dari Excel dalam bentuk JSON
 */
export function readExcel(filePath) {
  const workbook = XLSX.readFile(filePath);
  const sheetName = workbook.SheetNames[0];  // Mengambil sheet pertama
  const worksheet = workbook.Sheets[sheetName];
  const jsonData = XLSX.utils.sheet_to_json(worksheet);
  return jsonData;
}

/**
 * Fungsi untuk menulis data ke file Excel
 * @param {string} filePath - Path tujuan untuk menyimpan file
 * @param {object[]} data - Data yang akan ditulis ke dalam file Excel
 */
export function writeExcel(filePath, data) {
  const worksheet = XLSX.utils.json_to_sheet(data);  // Mengubah data JSON ke format sheet Excel
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');
  XLSX.writeFile(workbook, filePath);
}
