import React from "react";
import { useNavigate, Link } from "react-router-dom";

import Sidebar from "../components/Sidebar";

function products() {

  return (
    <div className="layout">
      <Sidebar />
      <header className="header">
        <h1 className="bi bi-speedometer2">Products</h1>

        <div className="right">
          <span className="bi bi-calendar"></span>
        </div>
      </header>
    </div>
  );
}

export default products;