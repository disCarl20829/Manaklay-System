import React from "react";
import { useNavigate, Link } from "react-router-dom";

import AuthLayout from "../components/AuthLayout";
import InputField from "../components/InputField";

import styles from "../components/AuthLayout.module.css";

import api from "../../config/api";
import { signup } from "../../config/auth_service";

function SignUp() {
  const navigate = useNavigate();

  const [formData, setFormData] = React.useState({
    user_name: "",
    user_email: "",
    user_password: "",
    confirm_password: ""
  });

  const handleChange = (key, value) => {
    setFormData((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (formData.user_password !== formData.confirm_password) {
      alert("Passwords do not match");
      return;
    }

    try {
      const res = await signup(formData);

      if (res.data.success) {
        alert("Signup successful! Please log in.");

        navigate("/");
      } else {
        alert(res.data.message);
      }

    } catch (err) {
      console.error(err);

      const message =
        err.response?.data?.message || "Signup failed";

      alert(message);
    }
  };

  return (
    <AuthLayout title="Create Account">

      <form onSubmit={handleSubmit} className={styles.form}>

        <InputField
          icon="person"
          type="text"
          placeholder="Username"
          id="user_name"
          value={formData.user_name}
          onChange={(e) => handleChange("user_name", e.target.value)}
        />

        <InputField
          icon="person"
          type="text"
          placeholder="Full Name"
          id="full_name"
          value={formData.full_name}
          onChange={(e) => handleChange("full_name", e.target.value)}
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

export default SignUp;