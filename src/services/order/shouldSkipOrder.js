function shouldSkipOrder(status, provider) {
  const p = provider.toLowerCase();
  return (
    status !== 'Perlu Dikirim  ' &&
    !(
      status === 'To ship Awaiting collection' ||
      ((p.includes('sameday') || p.includes('same day') || p.includes('instant')) &&
        status === 'To ship Awaiting shipment')
    )
  );
}
export {shouldSkipOrder};