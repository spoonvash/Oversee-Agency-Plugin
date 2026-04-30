import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";
import { resolveBootRoute } from "./lib/boot";

// Decide the initial hash route from the WordPress runtime config (window.OCD_CONFIG)
// and the SPA mount node's data-ocd-role attribute BEFORE React mounts. This is
// what stops production /dashboard/ from flashing the marketing role picker.
const mount =
  document.getElementById("oversee-dashboard-root") ||
  document.getElementById("root");

const initialHash = resolveBootRoute({
  config: typeof window !== "undefined" ? window.OCD_CONFIG : undefined,
  mountRole: mount?.getAttribute("data-ocd-role") ?? null,
  search: typeof window !== "undefined" ? window.location.search : "",
  currentHash: typeof window !== "undefined" ? window.location.hash : "",
});

if (typeof window !== "undefined" && initialHash && window.location.hash !== initialHash) {
  // Use replaceState so back-button doesn't return to an empty / role picker.
  const url = `${window.location.pathname}${window.location.search}${initialHash}`;
  window.history.replaceState(null, "", url);
}

if (mount) {
  createRoot(mount).render(<App />);
}
