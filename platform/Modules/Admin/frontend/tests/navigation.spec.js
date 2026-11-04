import { test, expect } from "@playwright/test";
import { defaultNavigation } from "../src/navigation.js";
const clone = value => JSON.parse(JSON.stringify(value));
async function setup(page) {
    let groups = clone(defaultNavigation);
    let reject = false;
    await page.route("**/api/v1/admin/**", async route => {
        const request = route.request();
        const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
        const reply = (data, status = 200) => route.fulfill({ status, contentType: "application/json", body: JSON.stringify(data) });
        if (path === "/me") return reply({ data: { id: 1, name: "管理员", email: "admin@example.com" } });
        if (path === "/csrf") return reply({ token: "csrf" });
        if (path === "/navigation") {
            if (request.method() === "PUT") {
                if (reject) return reply({ errors: { groups: ["暂时无法保存"] } }, 422);
                groups = request.postDataJSON().groups;
            }
            return reply({ data: groups, defaults: clone(defaultNavigation) });
        }
        return reply({ data: [] });
    });
    return { reject: value => { reject = value; } };
}
async function openNav(page) {
    const button = page.getByRole("button", { name: "打开导航" });
    if (await button.isVisible()) await button.click();
}

test("后台栏目默认纳入管理，保存名称、顺序、显示状态立即更新左侧并持久化", async ({ page }) => {
    await setup(page);
    await page.goto("/admin/shop/menus");
    await expect(page.getByRole("tab", { name: "后台导航" })).toHaveAttribute("aria-selected", "true");
    await expect(page.getByLabel("栏目名称 /products", { exact: true })).toHaveValue("商品管理");
    await expect(page.getByLabel("显示子栏目 /shop/menus", { exact: true })).toBeDisabled();
    await page.getByLabel("栏目名称 /products", { exact: true }).fill("商品中心");
    await page.getByLabel("子栏目名称 /products/categories", { exact: true }).fill("分类中心");
    await page.getByRole("button", { name: "上移子栏目 /products/categories", exact: true }).click();
    await page.getByLabel("显示栏目 /customers", { exact: true }).uncheck();
    await page.getByRole("button", { name: "上移栏目 /marketing", exact: true }).click();
    await page.getByRole("button", { name: "保存后台导航" }).click();
    await expect(page.getByRole("status")).toHaveText("后台导航已保存，左侧栏目已同步。");
    await openNav(page);
    const nav = page.getByRole("navigation", { name: "后台栏目" });
    await expect(nav.getByRole("button", { name: "商品中心", exact: true })).toBeVisible();
    await expect(nav.getByRole("link", { name: "顾客管理", exact: true })).toHaveCount(0);
    await expect(nav.locator("#navigation-products .nav-child")).toHaveText(["商品列表", "分类中心", "库存列表"]);
    const titles = await nav.locator(".nav-group").nth(1).locator(":scope > .nav-item").allTextContents();
    expect(titles.map(value => value.trim())).toEqual(["商品中心", "订单模块", "店铺管理", "AI 营销", "高级样式"]);
    await page.reload();
    await expect(page.getByLabel("栏目名称 /products", { exact: true })).toHaveValue("商品中心");
    await expect(page.getByLabel("显示栏目 /customers", { exact: true })).not.toBeChecked();
    await page.getByRole("button", { name: "恢复默认" }).click();
    await page.getByRole("button", { name: "保存后台导航" }).click();
    await expect(page.getByRole("status")).toHaveText("后台导航已保存，左侧栏目已同步。");
    await openNav(page);
    await expect(nav.getByRole("button", { name: "商品管理", exact: true })).toBeVisible();
    await expect(nav.getByRole("link", { name: "顾客管理", exact: true })).toBeVisible();
});

test("后台导航保存失败保留草稿且不改变左侧", async ({ page }) => {
    const state = await setup(page);
    await page.goto("/admin/shop/menus");
    await page.getByLabel("栏目名称 /products", { exact: true }).fill("待保存名称");
    state.reject(true);
    await page.getByRole("button", { name: "保存后台导航" }).click();
    await expect(page.getByRole("alert")).toHaveText("暂时无法保存");
    await expect(page.getByLabel("栏目名称 /products", { exact: true })).toHaveValue("待保存名称");
    await openNav(page);
    await expect(page.getByRole("navigation", { name: "后台栏目" }).getByRole("button", { name: "商品管理", exact: true })).toBeVisible();
});
