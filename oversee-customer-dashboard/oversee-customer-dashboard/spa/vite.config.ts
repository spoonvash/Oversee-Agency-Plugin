import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";

// The SPA bundle is enqueued by the Oversee Customer Dashboard plugin via
// OCD_Assets. The output is hashed and dropped into ../assets/build, where
// PHP picks the latest manifest and emits <script type="module"> for it.
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "src"),
        },
    },
    build: {
        outDir: path.resolve(__dirname, "../assets/build"),
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: path.resolve(__dirname, "src/main.tsx"),
        },
    },
});
