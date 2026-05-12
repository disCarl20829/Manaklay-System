import api from "../config/api";

export const login = (data) => {
    return api.post("/api/login", data);
};

export const signup = (data) => {
    return api.post("/api/signup", data);
}

export const getUser = () => {
    const user = localStorage.getItem("user");
    return user ? JSON.parse(user) : null;
}

export const logout = () => {
    if (localStorage.getItem("user") && localStorage.getItem("token")) {
        localStorage.removeItem("token");
        localStorage.removeItem("user");
    }
}