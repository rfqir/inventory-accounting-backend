import axios from 'axios';

let token = null;

async function login() {
  try {
    const username = process.env.CREDIENTIAL_INVENTORY;
    const password = process.env.PASSWORD_INVENTORY;
    const res = await axios.post(process.env.INVENTORY + '/auth/login/', {
      username,
      password
    });

    // Ambil langsung res.data.key
    token = res.data.key;

    console.log('Token disimpan:', token);
    return token;
  } catch (err) {
    console.error(process.env.INVENTORY);
    
    console.error('Login gagal:', err.message);
    throw err;
  }
}

function getToken() {
  return token;
}

export { login, getToken };
