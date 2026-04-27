import { useEffect, useState, useCallback } from "react";
import { apiGet, apiPost } from "./api";

// Dark mode state. Source of truth is `oversee_dark_mode` user meta on the
// server; we read it once on mount, mirror it onto <html class="oversee-dark">,
// and POST every change back so the preference round-trips.

export function useDarkMode() {
    const [dark, setDark] = useState<boolean>(() =>
        typeof document !== "undefined" && document.documentElement.classList.contains("oversee-dark")
    );

    useEffect(() => {
        let active = true;
        apiGet<{ dark_mode: boolean }>("me/preferences")
            .then((res) => {
                if (!active) return;
                setDark(res.dark_mode);
                applyClass(res.dark_mode);
            })
            .catch(() => {
                /* leave whatever the server-rendered class set */
            });
        return () => {
            active = false;
        };
    }, []);

    const toggle = useCallback(async () => {
        const next = !dark;
        setDark(next);
        applyClass(next);
        try {
            await apiPost("me/preferences", { dark_mode: next });
        } catch {
            // Roll back on failure so the UI doesn't drift from the server.
            setDark(!next);
            applyClass(!next);
        }
    }, [dark]);

    return { dark, toggle };
}

function applyClass(dark: boolean) {
    if (typeof document === "undefined") return;
    document.documentElement.classList.toggle("oversee-dark", dark);
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", dark ? "#09090b" : "#ffffff");
}
