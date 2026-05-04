import React, { useState } from 'react';
import { NavLink } from 'react-router-dom';

import styles from "./Sidebar.module.css";

function Sidebar() {
    return (
        <aside className={styles.sidebar}>
            <div className={styles.header}>
                <div className={styles.logo}>
                    <img src="/logo.jpg" alt="Logo" />
                </div>
                <h3>Manaklay</h3>
                <p>Accounting and Inventory</p>
            </div>

            <nav className={styles.nav}>
                <NavLink to="/dashboard" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-speedometer2"></i>
                    Dashboard
                </NavLink>
                <NavLink to="/products" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-box"></i>
                    Products
                </NavLink>
                <NavLink to="/inventory" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-clipboard-data"></i>
                    Inventory
                </NavLink>
                <NavLink to="/orders" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-cart"></i>
                    Orders
                </NavLink>
                <NavLink to="/expenses" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                    }>
                    <i className="bi bi-currency-dollar"></i>
                    Expenses
                </NavLink>
                <NavLink to="/reports" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-bar-chart"></i>
                    Reports
                </NavLink>
                <NavLink to="/settings" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-gear"></i>
                    Settings
                </NavLink>
                <NavLink to="/logout" className={({ isActive }) =>
                    `${styles.link} ${isActive ? styles.active : ''}`
                }>
                    <i className="bi bi-box-arrow-right"></i>
                    Logout
                </NavLink>
            </nav>

            <div>
                <p>Logged in as:</p>
                <strong>{JSON.parse(localStorage.getItem("user"))?.username || "Unknown User"}</strong>
            </div>
        </aside>
    );
}

export default Sidebar;