import {httpClient} from '../../utils/camundaClient.js';
import {insertRefill} from '../../graphql/mutation/insertRefill.js';
import {getCurrentDateTimeString} from '../../utils/time.js';
async function processOrder(Invoice, courier_name,date ) {
    try {
    	const waktu = getCurrentDateTimeString();
        const businessKey = Invoice + ':' + courier_name + ':' + waktu;
        console.log('busines: ', businessKey)
        const nama = "StartNewInstanceInvoices";
        const data = {
            messageName: nama,
            businessKey: businessKey,
            processVariables: {
                invoice: { value: Invoice, type: 'String' },
                courier_name: { value: courier_name, type: 'String' },
            }
        };
        const response = await httpClient.post('/message', data);
	return response.data;
    } catch (error) {
        console.error('Error processing order:', error);
        throw error;
    }   
}
async function processRefill(skuToko, gudang, partGudang,destination_location_id, quantity_request) {
    if (!skuToko || !gudang || !partGudang || !quantity_request || !destination_location_id) {
        throw new Error('All parameters are required');
    }
    const location_gudang = `GUDANG ${gudang}`;
    try {
        const businessKey = location_gudang + ':' + skuToko + ':' + 'summary';
        const data = {
            businessKey: businessKey,
            variables: {
                refill_operasional: { value: 'rekomendasi', type: 'String' },
            }
        };
        const response = await httpClient.post('/process-definition/key/Mirorim_Operasional.Refill/start', data);
        try {
            const proc_inst_id = response.data.id;
            await insertRefill(proc_inst_id, skuToko, partGudang, destination_location_id,quantity_request)
        } catch (error) {
            throw new Error(`Error in Mirorim_Operasional.Refill: ${error.message}`);
        }
    } catch (error) {
        console.error('Error processing refill:', error);
        throw error;
    }   
}
export { processOrder, processRefill };
