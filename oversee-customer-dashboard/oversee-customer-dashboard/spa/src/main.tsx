import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

if (!window.location.hash) {
  window.location.hash = "#/";
}

const mount =
  document.getElementById("oversee-dashboard-root") ||
  document.getElementById("root");

if (mount) {
  createRoot(mount).render(<App />);
}
