export async function shouldSkipOrder(status, provider) {
  if (!status || !provider) return true; // Skip jika status/provider kosong

  const normalizedStatus = status.trim().toLowerCase();       // Normalisasi status

  let skip = false;

  if (normalizedStatus.includes('shopee')) {
    skip = normalizedStatus !== 'shopee perlu dikirim';
  } else if (normalizedStatus.includes('tokopedia')) {
    skip = normalizedStatus !== 'tokopedia perlu dikirim menunggu pengiriman';
  } else {
    skip = true; // Skip jika status tidak mengandung 'shopee' atau 'tokopedia'
  }

  return skip;
}

