import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { App } from "./App";
import "./styles.css";

const mount = document.getElementById("oversee-dashboard-root");
if (mount) {
    createRoot(mount).render(
        <StrictMode>
            <BrowserRouter basename="/dashboard">
                <App />
            </BrowserRouter>
        </StrictMode>
    );
}
