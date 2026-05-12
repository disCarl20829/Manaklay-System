// src/config/api.js
import axios from "axios";

const api = axios.create({
  //baseURL: import.meta.env.VITE_API_BASE_URL,
  baseURL: "http://192.168.254.100:3000" || "http://localhost:3000",
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export default api;