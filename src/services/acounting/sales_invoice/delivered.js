import axios from "axios";

async function delivered(id) {
const url = process.env.ACCOUNTING || 'http://localhost:8000/api'
  try {
    const response = await axios.post(
      `${url}/sales/invoices/${id}/deliver`,
      {}, // body kosong
      {
        headers: {
          "x-access-token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MywiZXhwIjoxNzU3OTQ0MTQ0LjkyMywiaWF0IjoxNzUyNzYwMTQ0fQ.pTUDzdwo5KoXabYS1jB4tQPtZyQBWIfwu8e02q1S9bk",
          "organization-id": "405ope1md3wigc5"
        }
      }
    );
    return response;
  } catch (error) {
    console.error('Delivered error:', error.response?.data || error.message);
    throw error;
  }
}

export { delivered };

