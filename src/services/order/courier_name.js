async function getCourierName(provider) {
  const p = provider.toLowerCase();

  if (provider.includes("JNE")) return "JNE";
  if (provider.includes("Rekomendasi")) return "rekomendasi";
  if (provider.includes("SiCepat")) return "sicepat";
  if ((p.includes("j&t") && p.includes("cargo")) || p.includes("kargo")) return "j&t cargo";
  if (p.includes("j&t")) return "j&t";
  if (provider.includes("Paxel")) return "paxel";
  if (provider.includes("GTL(Regular)")) return "gtl";
  if ((provider.includes("SPX") || provider.includes("Hemat")) &&
      !provider.includes("Sameday") && !provider.includes("Instant")) {
    return "shopee";
  }
  return "instant";
}

export {getCourierName};