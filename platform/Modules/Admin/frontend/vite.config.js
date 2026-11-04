import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

export default defineConfig(({ mode, command }) => {
    const env = loadEnv(mode, process.cwd(), "");
    return {
        plugins: [vue()],
        resolve: {
            alias: {
                "@storefront": fileURLToPath(
                    new URL("../../../public/js", import.meta.url),
                ),
                "@storefront-styles": fileURLToPath(
                    new URL("../../../public/css", import.meta.url),
                ),
            },
        },
        base: command === "build" ? "/admin-app/" : "/admin/",
        build: {
            outDir: "../../../public/admin-app",
            emptyOutDir: true,
        },
        server: {
            port: 5174,
            strictPort: true,
            proxy: {
                "/storage": { target: env.API_PROXY_TARGET || "http://shop_jwj.test", changeOrigin: true },
                "/api": {
                    target: env.API_PROXY_TARGET || "http://shop_jwj.test",
                    changeOrigin: true,
                    cookieDomainRewrite: "",
                },
            },
        },
    };
});
