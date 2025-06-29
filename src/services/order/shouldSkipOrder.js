function getYearMonth() {
const now = new Date();

const pad = (n) => n.toString().padStart(2, '0');

const year = now.getFullYear();
const month = pad(now.getMonth() + 1);  // Januari = 0

return `${year}-${month}`;
}
export async function shouldSkipOrder(status, provider) {
  if (!status) return true; // Skip jika status/provider kosong

  const normalizedStatus = status.trim().toLowerCase();       // Normalisasi status
  let skip = false;
  const currentYearMonth = getYearMonth().toLowerCase();
  if (normalizedStatus.includes('shopee')) {
    skip = !normalizedStatus.includes(`shopee perlu dikirim`);
  } else if (normalizedStatus.includes('tokopedia')) {
    skip = normalizedStatus !== 'tokopedia perlu dikirim menunggu pengambilan';
  } else {
    skip = true; // Skip jika status tidak mengandung 'shopee' atau 'tokopedia'
  }

  return skip;
}

