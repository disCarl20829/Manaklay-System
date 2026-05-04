import React, { useState } from 'react';

import styles from "./InputField.module.css";

function InputField({
    icon,
    type = "text",
    placeholder,
    id,
    isPassword = false,
    value,
    onChange
}) {
    const [showPassword, setShowPassword] = useState(false);

    const inputType = isPassword
        ? (showPassword ? "text" : "password")
        : type;

    return (
        <div className={styles.group}>
            <span className={styles.icon}>
                <i className={`bi bi-${icon}`}></i>
            </span>

            <input
                type={inputType}
                className={styles.input}
                id={id}
                placeholder={placeholder}
                value={value}
                onChange={onChange}
                required
            />

            {isPassword && (
                <span
                    className={styles.icon}
                    onClick={() => setShowPassword(!showPassword)}
                    style={{ cursor: 'pointer' }}
                >
                    <i className={`bi bi-eye${showPassword ? '' : '-slash'}`}></i>
                </span>
            )}
        </div>
    );
}

export default InputField;