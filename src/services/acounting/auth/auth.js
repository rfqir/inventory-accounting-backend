import axios from 'axios';
let token = null;
let organization_id = null;

async function login() {
  try {
    const crediential = process.env.CREDIENTIAL_ACCOUNTING;
    const password = process.env.PASSWORD_ACCOUNTING;
    const res = await axios.post(process.env.ACCOUNTING + '/auth/login', {
      crediential,
      password
    });

    // Simpan token dan orgId
    token = res.data.token; // asumsikan response seperti { token: "abc", orgId: "123" }
    organization_id = res.data.tenant.organization_id;

    console.log('Login berhasil');
    return { token, organization_id };
  } catch (err) {
    console.error('Login gagal:', err.message);
    throw err;
  }
}

function getToken() {    
  return token;
}

function getOrgId() {
  return organization_id;
}

export { login, getToken, getOrgId };
