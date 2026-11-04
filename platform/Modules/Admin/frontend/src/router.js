import { createRouter, createWebHistory } from "vue-router";
import { auth, checkSession } from "./auth";
import Login from "./views/Login.vue";
import Dashboard from "./views/Dashboard.vue";
import Products from "./views/Products.vue";
import Marketing from "./views/Marketing.vue";
import Settings from "./views/Settings.vue";
import NotFound from "./views/NotFound.vue";

export const router = createRouter({
    history: createWebHistory("/admin"),
    routes: [
        {
            path: "/login",
            component: Login,
            meta: { title: "登录", public: true },
        },
        { path: "/", component: Dashboard, meta: { title: "工作台" } },
        { path: "/products", component: Products, meta: { title: "商品列表" } },
        { path: "/products/inventory", component: () => import("./views/Inventory.vue"), meta: { title: "库存列表" } },
        { path: "/products/categories", component: () => import("./views/Categories.vue"), meta: { title: "商品分类" } },
        { path: "/products/categories/create", component: () => import("./views/CategoryEditor.vue"), meta: { title: "创建分类" } },
        { path: "/products/categories/:categoryId/edit", component: () => import("./views/CategoryEditor.vue"), meta: { title: "编辑分类" } },
        { path: "/products/:id/edit", component: Products, meta: { title: "编辑商品" } },
        { path: "/products/create", component: Products, meta: { title: "添加商品" } },
        {
            path: "/advanced-styles",
            component: () => import("./views/AdvancedStyles.vue"),
            meta: { title: "高级样式" },
        },
        {
            path: "/advanced-styles/new",
            component: () => import("./views/AdvancedStyleEditor.vue"),
            meta: { title: "创建高级样式" },
        },
        {
            path: "/advanced-styles/:id/edit",
            component: () => import("./views/AdvancedStyleEditor.vue"),
            meta: { title: "编辑高级样式" },
        },
        {
            path: "/marketing",
            component: Marketing,
            meta: { title: "AI 营销" },
        },
        { path: "/orders", component: () => import("./views/OrderSection.vue"), meta: { title: "所有订单", description: "订单列表功能尚未接入。" } },
        { path: "/orders/abandoned", component: () => import("./views/OrderSection.vue"), meta: { title: "弃单管理", description: "弃单管理功能尚未接入。" } },
        { path: "/customers", component: () => import("./views/Customers.vue"), meta: { title: "顾客管理" } },
        { path: "/shop", redirect: "/shop/pages" },
        { path: "/shop/pages", component: () => import("./views/ShopSection.vue"), meta: { title: "页面管理", description: "页面管理功能尚未接入。" } },
        { path: "/shop/menus", component: () => import("./views/Menus.vue"), meta: { title: "菜单栏" } },
        { path: "/shop/seo", component: () => import("./views/ShopSection.vue"), meta: { title: "主页SEO", description: "主页SEO设置功能尚未接入。" } },
        { path: "/settings", component: Settings, meta: { title: "账号设置" } },
        {
            path: "/:pathMatch(.*)*",
            component: NotFound,
            meta: { title: "页面不存在" },
        },
    ],
});

router.beforeEach(async (to) => {
    document.title = `${to.meta.title} · Shop Studio`;
    try {
        await checkSession();
    } catch {
        if (!to.meta.public)
            return { path: "/login", query: { connection: "failed" } };
    }
    if (!to.meta.public && !auth.user)
        return { path: "/login", query: { redirect: to.fullPath } };
    if (to.path === "/login" && auth.user) return "/";
});

window.addEventListener("admin:unauthorized", () => {
    const hadSession = auth.user !== null;
    auth.user = null;
    if (hadSession && router.currentRoute.value.path !== "/login") {
        const redirect = router.currentRoute.value.fullPath;
        router.replace({ path: "/login", query: { redirect } });
    }
});
