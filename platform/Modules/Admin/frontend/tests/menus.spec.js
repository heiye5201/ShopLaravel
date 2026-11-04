import { test, expect } from "@playwright/test";

async function setup(page) {
    let menus = [];
    let failSave = false;
    const writes = [];
    await page.route("**/api/v1/admin/**", async route => {
        const request = route.request();
        const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
        const method = request.method();
        const reply = (data, status = 200) => route.fulfill({ status, contentType: "application/json", body: JSON.stringify(data) });
        if (path === "/me") return reply({ data: { id: 1, name: "管理员", email: "admin@example.com" } });
        if (path === "/csrf") return reply({ token: "csrf" });
        if (path === "/products") return reply({ data: [{ id: 11, title: "音箱" }] });
        if (path === "/categories") return reply({ data: [{ id: 22, name: "新品分类" }] });
        if (path === "/menus" && method === "GET") return reply({ data: menus });
        if (path.startsWith("/menus")) {
            if (method === "DELETE") { menus = []; return reply({ data: null }); }
            const body = request.postDataJSON();
            writes.push(body);
            if (failSave) return reply({ message: "验证失败", errors: { url: ["链接格式错误"] } }, 422);
            const menu = { ...body, id: 1 };
            menus = [menu];
            return reply({ data: menu }, method === "POST" ? 201 : 200);
        }
        return reply({ data: [] });
    });
    return { writes, fail: value => { failSave = value; } };
}

test("创建菜单、选择商品分类、排序、编辑和删除", async ({ page }) => {
    const state = await setup(page);
    await page.goto("/admin/shop/menus");
    await page.getByRole("tab", { name: "商城导航" }).click();
    await page.getByRole("button", { name: "创建菜单", exact: true }).click();
    await page.getByLabel("菜单名称").fill("主导航");
    await page.getByRole("button", { name: "添加自定义链接" }).click();
    await page.getByLabel("链接名称").fill("品牌故事");
    await page.getByLabel("链接地址").fill("https://example.com/about");
    await page.getByRole("button", { name: "搜索", exact: true }).click();
    await page.getByRole("button", { name: "添加 音箱" }).click();
    await page.getByLabel("链接类型").selectOption("category");
    await page.getByRole("button", { name: "搜索", exact: true }).click();
    await page.getByRole("button", { name: "添加 新品分类" }).click();
    await page.getByRole("button", { name: "上移第 3 项" }).click();
    await page.getByRole("button", { name: "保存菜单", exact: true }).click();
    await expect(page.getByRole("status")).toHaveText("菜单已保存，主题导航已同步。");
    expect(state.writes[0].items.map(item => item.title)).toEqual(["品牌故事", "新品分类", "音箱"]);
    expect(state.writes[0].items[1].target_id).toBe(22);
    expect(state.writes[0].items[2].target_id).toBe(11);
    await page.reload();
    await page.getByRole("tab", { name: "商城导航" }).click();
    await page.getByRole("button", { name: "编辑 主导航" }).click();
    await expect(page.getByTestId("menu-item")).toHaveCount(3);
    await page.getByRole("button", { name: "移除第 2 项" }).click();
    await page.getByLabel("菜单名称").fill("新导航");
    await page.getByRole("button", { name: "保存菜单", exact: true }).click();
    await expect(page.getByRole("button", { name: "编辑 新导航" })).toBeVisible();
    expect(state.writes[1].items.map(item => item.title)).toEqual(["品牌故事", "音箱"]);
    page.once("dialog", dialog => dialog.accept());
    await page.getByRole("button", { name: "删除 新导航" }).click();
    await expect(page.getByRole("heading", { name: "暂无菜单" })).toBeVisible();
    await expect(page.getByRole("status")).toContainText("主题已恢复默认导航");
});

test("保存失败保留编辑数据，可重试", async ({ page }) => {
    const state = await setup(page);
    await page.goto("/admin/shop/menus");
    await page.getByRole("tab", { name: "商城导航" }).click();
    await page.getByRole("button", { name: "创建菜单", exact: true }).click();
    await page.getByLabel("菜单名称").fill("重试菜单");
    state.fail(true);
    await page.getByRole("button", { name: "保存菜单", exact: true }).click();
    await expect(page.getByRole("alert")).toHaveText("链接格式错误");
    await expect(page.getByLabel("菜单名称")).toHaveValue("重试菜单");
    state.fail(false);
    await page.getByRole("button", { name: "保存菜单", exact: true }).click();
    await expect(page.getByRole("button", { name: "编辑 重试菜单" })).toBeVisible();
});
