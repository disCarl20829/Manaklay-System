import React from "react";
import { useNavigate, Link } from "react-router-dom";

import AuthLayout from "../components/AuthLayout";
import InputField from "../components/InputField";

import styles from "../components/AuthLayout.module.css";

import api from "../../config/api";
import { login } from "../../config/auth_service";

function SignIn() {
  const navigate = useNavigate();

  const [formData, setFormData] = React.useState({
    user_input: "",
    user_password: "",
  });

  const handleChange = (key, value) => {
    setFormData((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    try {
      const res = await login(formData);

      if (res.data.success) {
        localStorage.setItem("user", JSON.stringify(res.data.data.user));
        localStorage.setItem("token", res.data.data.token);

        navigate("/dashboard");
      } else {
        alert(res.data.message);
      }

    } catch (err) {
      console.error(err);

      const message =
        err.response?.data?.message || "Login failed";

      alert(message);
    }
  };

  return (
    <AuthLayout title="Log In">

      <form onSubmit={handleSubmit} className={styles.form}>

        <InputField
          icon="person"
          type="text"
          placeholder="Username or Email"
          id="user_input"
          value={formData.user_input}
          onChange={(e) => handleChange("user_input", e.target.value)}
        />

        <InputField
          icon="lock"
          type="password"
          placeholder="Password"
          id="user_password"
          isPassword={true}
          value={formData.user_password}
          onChange={(e) => handleChange("user_password", e.target.value)}
        />

        <button type="submit" className={styles.button}>
          Log In
        </button>

      </form>

      <div className={styles.text}>
        Don't have an account?{" "}
        <Link to="/signup">Sign up</Link>
      </div>

    </AuthLayout>
  );
}

export default SignIn;