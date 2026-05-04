import api from "../config/api";

export const login = (data) => {
    return api.post("/api/login", data);
};

export const signup = (data) => {
    return api.post("/api/signup", data);
}