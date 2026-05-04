import React from "react";
import { useNavigate, Link } from "react-router-dom";

import Sidebar from "../components/Sidebar";

import "../styles/pages.css";

function dashboard() {

  return (
    <div className="layout">
      <Sidebar />

      <div className="mainContent">
        <header>
          <h1>
            <i className="bi bi-speedometer2"></i>
            Dashboard
          </h1>

          <div className="right">
            <span className="bi bi-calendar"></span>
          </div>
        </header>
      </div>
    </div>
  );
}

export default dashboard;