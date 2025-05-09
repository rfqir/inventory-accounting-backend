// graphql/client.js
import axios from 'axios';

const httpClient = axios.create({
  baseURL: process.env.HASURA || 'http://localhost:4000/graphql',
  headers: {
    'Content-Type': 'application/json',
  },
});

export default httpClient;
