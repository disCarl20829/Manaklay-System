import React from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";

import 'bootstrap/dist/css/bootstrap.min.css';

//import ProtectedRoute from "./ProtectedRoute.jsx"

import Login from "./pages/login.jsx";
import Signup from "./pages/signup.jsx";

function App() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/" element={<Login />} />
                <Route path="/signup" element={<Signup />} />
                {/*<Route path="/register" element={<Register />} />
                <Route element={<ProtectedRoute />}>
                </Route>*/}
            </Routes>
        </BrowserRouter>
    );
}

export default App;