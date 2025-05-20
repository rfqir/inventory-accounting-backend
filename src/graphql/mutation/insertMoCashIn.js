// graphql/mutations/createMoOrder.js
import httpClient from '../client.js';

async function insertMoCashIn({
  invoice,
  proc_inst_id,
  settlement_amount,
  total_revenue,
  seller_discounts,
  market_place_fee,
  sfp_fee,
  live_fee,
  voucer_fee,
  flash_sale_fee,
  cash_back_fee,
  paylater_fee,
  adjustment_amount,
  affiliate_commission,
  logistic_fee
}) {
  const query = `
    mutation CreateMoCashIn(
      $invoice: String!,
      $proc_inst_id: String!,
      $settlement_amount: Int!,
      $total_revenue: Int!,
      $seller_discounts: Int!,
      $market_place_fee: Int!,
      $sfp_fee: Int!,
      $live_fee: Int!,
      $voucer_fee: Int!,
      $flash_sale_fee: Int!,
      $cash_back_fee: Int!,
      $paylater_fee: Int!,
      $adjustment_amount: Int!,
      $affiliate_commission: Int!,
      $logistic_fee: Int!
    ) {
      insert_mo_cash_in(objects: {
        invoice: $invoice,
        proc_inst_id: $proc_inst_id,
        settlement_amount: $settlement_amount,
        total_revenue: $total_revenue,
        seller_discounts: $seller_discounts,
        market_place_fee: $market_place_fee,
        sfp_fee: $sfp_fee,
        live_fee: $live_fee,
        voucer_fee: $voucer_fee,
        flash_sale_fee: $flash_sale_fee,
        cash_back_fee: $cash_back_fee,
        paylater_fee: $paylater_fee,
        adjustment_amount: $adjustment_amount,
        affiliate_commission: $affiliate_commission,
        logistic_fee: $logistic_fee
      }) {
        returning {
          invoice
          proc_inst_id
        }
      }
    }
  `;

  const variables = {
    invoice,
    proc_inst_id,
    settlement_amount,
    total_revenue,
    seller_discounts,
    market_place_fee,
    sfp_fee,
    live_fee,
    voucer_fee,
    flash_sale_fee,
    cash_back_fee,
    paylater_fee,
    adjustment_amount,
    affiliate_commission,
    logistic_fee
  };

  try {
    const response = await httpClient.post('', { query, variables });
    return response.data.data.insert_mo_cash_in.returning[0];
  } catch (error) {
    console.error('Error creating mo_cash_in:', error);
    throw error;
  }
}

export { insertMoCashIn };
