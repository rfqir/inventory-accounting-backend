async function getCourierName(provider) {
  const p = provider.toLowerCase();

  if (p.includes("jne")) return "jne";
  if (p.includes("rex")) return "rex";
  if (p.includes("rekomendasi")) return "rekomendasi";
  if (p.includes("sicepat")) return "sicepat";
  if (p.includes("kargo anteraja")) return "kargo anteraja";
  if (p.includes("anteraja")) return "anteraja";
  if ((p.includes("spx") || p.includes("hemat")) &&
      !p.includes("sameday") && !p.includes("instant") && !p.includes('sicepat')) {
    return "shopee";
  }
  if ((p.includes("j&t") && p.includes("cargo")) || p.includes("kargo")) return "j&t cargo";
  if (p.includes("j&t")) return "j&t";
  if (p.includes("paxel")) return "paxel";
  if (p.includes("gtl(regular)")) return "gtl";

  return "instant";
}

export {getCourierName};
