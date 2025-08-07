import axios from 'axios';

async function uniqSignal(nameUniqSignal, uniq, varname, value) {
  try {
    const body = {
      name: `${nameUniqSignal}${uniq}`,
      variables: {
        [varname]: {
          value: value
        }
      }
    };
    // console.log('Sending unique signal:', body);
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
    console.error('Error uniqSignal:', error.response?.data || error.message);
    throw error;
  }
}

export { uniqSignal };
