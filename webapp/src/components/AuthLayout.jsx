import React from 'react';

import styles from "./AuthLayout.module.css";

function AuthLayout({ children, title }) {
    return (
        <div className={styles.loginPage}>
            <div className={styles.loginContainer}>
                <div className={styles.loginBox}>

                    <div className={styles.logoContainer}>
                        <img src="/logo.jpg" alt="Logo" className={styles.logo} />
                    </div>

                    <div className={styles.loginHeader}>
                        <h1>{title}</h1>
                    </div>

                    <div className={styles.loginForm}>
                        {children}
                    </div>

                    <div className={styles.loginFooter}>
                        © 2026 MANAKLAY BEACH PARK AND RESORT
                    </div>

                </div>
            </div>
        </div>
    );
}

export default AuthLayout;