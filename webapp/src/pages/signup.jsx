import React from "react";
import { useNavigate, Link } from "react-router-dom";

import AuthLayout from "../components/AuthLayout";
import InputField from "../components/InputField";

import styles from "../components/AuthLayout.module.css";

//import api from "../config/api";

function SignIn() {
  const navigate = useNavigate();

  const [formData, setFormData] = React.useState({
    user_name: "",
    user_email: "",
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
      const res = await api.post("/login", formData);

      // example redirect after login
      if (res.data.success) {
        navigate("/dashboard");
      }
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <AuthLayout title="Create Account">

      <form onSubmit={handleSubmit} className={styles.form}>

        <InputField
          icon="person"
          type="text"
          placeholder="Username"
          id="user_input"
          value={formData.user_name}
          onChange={(e) => handleChange("user_name", e.target.value)}
        />

        <InputField
          icon="person"
          type="email"
          placeholder="Email"
          id="user_email"
          value={formData.user_email}
          onChange={(e) => handleChange("user_email", e.target.value)}
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

        <InputField
          icon="lock"
          type="password"
          placeholder="Confirm Password"
          id="confirm_password"
          isPassword={true}
          onChange={(e) => handleChange("confirm_password", e.target.value)}
        />

        <button type="submit" className={styles.button}>
          Sign Up
        </button>

      </form>

      <div className={styles.text}>
        Already have an account?{" "}
        <Link to="/">Log In</Link>
      </div>

    </AuthLayout>
  );
}

export default SignIn;