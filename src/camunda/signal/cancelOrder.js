import axios from 'axios';

async function cancelOrder(invoice) {
  try {
    const body = {
      name: `cancel${invoice}`
    };

    const response = await axios.post(
      `${process.env.ENDPOINT_CAMUNDA}/signal`,
      body,
      {
        headers: {
          'Content-Type': 'application/json'
        }
      }
    );

    return response;
  } catch (error) {
    console.error('Error cancelOrder:', error.response?.data || error.message);
    throw error;
  }
}

export { cancelOrder };
