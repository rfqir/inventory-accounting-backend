import httpClient from '../../../utils/httpClient.js';

async function getCustomer(customerName) {
    try {
        // Mencari customer berdasarkan nama
        const response = await httpClient.get(`/customers?search_keyword=${customerName}`);
        const customer = response.data.customers.find(c => c.display_name === customerName);
        console.log('hai customer: '+ customerName);
        
        if (customer) {
            return customer.id;
        } else {
            // Jika customer tidak ditemukan, buat customer baru
            const data = {
                customer_type: "individual",
                display_name: customerName
            };

            const responsePost = await httpClient.post('/customers', data);  // Gunakan URL relatif

            return responsePost.data.id;
        }
    } catch (error) {
        console.error('Terjadi kesalahan saat mengambil atau membuat customer:', error);
        throw error;  // Melemparkan error agar bisa ditangani di tempat lain
    }
}

export { getCustomer };
