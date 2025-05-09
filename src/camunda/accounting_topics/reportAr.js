// src/camunda/accounting/reportAr.js
import { Client, logger } from 'camunda-external-task-client-js';


function getCurrentTimestamp() {
  return new Date().toLocaleString();
}

export function startCamundaWorker() {
  const config = {
    baseUrl: process.env.ENDPOINT_CAMUNDA,
    use: logger,
  };

  const client = new Client(config);

  client.subscribe("reportAr", async ({ task, taskService }) => {
    try {
      const itemCode = task.variables.get("itemCode");
      const customerName = task.variables.get("customerName");
      const invoice = task.variables.get("invoice");
      const invoiceDate = task.variables.get("invoiceDate");
      const resi = task.variables.get("resi");
      const quantity = task.variables.get("quantity");
      const sellPrice = task.variables.get("sellPrice");

      const responseItemCode = await getItems(itemCode);
      const responseGetCustomer = await getCustomer(customerName);
      const responseCreateInvoice = await createInvoice(responseGetCustomer.id, invoiceDate, invoice, resi, responseItemCode.id, quantity,sellPrice);
      await taskService.complete(task, newvar);
    } catch (err) {
      console.error("Terjadi kesalahan:", err.message);
    }
  }, {
    workerId: "cek-ip",
    lockDuration: 100000,
  });
}
