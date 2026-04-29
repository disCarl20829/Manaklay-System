import React from "react";
import { useNavigate, Link } from "react-router-dom";

import AuthLayout from "../components/AuthLayout";
import InputField from "../components/InputField";

import styles from "../components/AuthLayout.module.css";

//import api from "../config/api";

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