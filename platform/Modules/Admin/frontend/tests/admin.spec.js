import { test, expect } from "@playwright/test";
import { defaultNavigation } from "../src/navigation.js";

async function mockApi(page, initiallyLoggedIn = false) {
    let loggedIn = initiallyLoggedIn;
    let variantId = 100;
    let user = { id: 1, name: "商城管理员", email: "owner@example.com" };
    const products = [
        {
            id: 1,
            title: "Aurora 无线音箱",
            slug: "aurora-speaker",
            price: "199.00",
            description: "为日常生活带来好声音。",
            tags: ["音频", "无线"],
            is_available: true,
            variants: [
                {
                    id: 11,
                    product_id: 1,
                    title: "默认规格",
                    price: "199.00",
                    is_available: true,
                    position: 0,
                },
            ],
        },
    ];
    const requests = [];
    await page.route("**/api/v1/admin/**", async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const path = url.pathname.replace("/api/v1/admin", "");
        const method = request.method();
        requests.push({
            path,
            method,
            headers: request.headers(),
            body: request.postDataJSON(),
        });
        const reply = (body, status = 200) =>
            route.fulfill({
                status,
                contentType: "application/json",
                body: JSON.stringify(body),
            });
        if (path === "/csrf") return reply({ token: "test-csrf-token" });
        if (path === "/login") {
            if (request.postDataJSON().password !== "valid-password") {
                return reply(
                    {
                        message: "邮箱或密码错误。",
                        errors: { email: ["邮箱或密码错误。"] },
                    },
                    422,
                );
            }
            loggedIn = true;
            return reply({ data: user });
        }
        if (!loggedIn) return reply({ message: "Unauthenticated." }, 401);
        if (path === "/me") return reply({ data: user });
        if (path === "/navigation") return reply({ data: defaultNavigation, defaults: defaultNavigation });
        if (path === "/marketing/config")
            return reply({ data: { configured: false, model: null } });
        if (path === "/marketing/campaigns")
            return reply({
                data: [],
                meta: { current_page: 1, last_page: 1, total: 0 },
            });
        if (path === "/logout") {
            loggedIn = false;
            return reply({ message: "已退出登录" });
        }
        if (path === "/dashboard")
            return reply({
                data: {
                    products: products.length,
                    available: products.filter((p) => p.is_available).length,
                    unavailable: products.filter((p) => !p.is_available).length,
                    recent_products: products,
                },
            });
        if (path === "/profile") {
            user = {
                ...user,
                name: request.postDataJSON().name,
                email: request.postDataJSON().email,
            };
            return reply({ data: user });
        }
        if (path === "/products" && method === "POST") {
            products.unshift({
                ...request.postDataJSON(),
                id: products.length + 1,
                variants: (request.postDataJSON().variants || []).map(
                    (variant) => ({ ...variant, id: ++variantId }),
                ),
            });
            return reply({ data: products[0] }, 201);
        }
        if (path.startsWith("/products/")) {
            const index = products.findIndex(
                (p) => p.id === Number(path.split("/").at(-1)),
            );
            const body = request.postDataJSON();
            products[index] = {
                ...products[index],
                ...body,
                ...(body.variants
                    ? {
                          variants: body.variants.map((variant) => ({
                              ...variant,
                              id: variant.id || ++variantId,
                          })),
                      }
                    : {}),
            };
            return reply({ data: products[index] });
        }
        if (path === "/products") {
            const search = url.searchParams.get("search") || "";
            const status = url.searchParams.get("status");
            const data = products.filter(
                (p) =>
                    (p.title.includes(search) || p.slug.includes(search)) &&
                    (!status || p.is_available === (status === "available")),
            );
            return reply({
                data,
                meta: {
                    current_page: 1,
                    last_page: 1,
                    total: data.length,
                    per_page: 10,
                },
            });
        }
        return reply({ message: "Not found" }, 404);
    });
    return requests;
}

async function navigate(page, title) {
    const mobileMenu = page.getByRole("button", { name: "打开导航" });
    if (await mobileMenu.isVisible()) await mobileMenu.click();
    await page
        .getByRole("navigation", { name: "后台栏目" })
        .getByRole("link", { name: title })
        .click();
}

test("登录保护、错误提示、登录后返回目标栏目，退出后禁止继续访问", async ({
    page,
}) => {
    const requests = await mockApi(page);
    await page.goto("/admin/products");
    await expect(page).toHaveURL(/\/admin\/login\?redirect=/);
    await page
        .getByRole("textbox", { name: "登录邮箱" })
        .fill("owner@example.com");
    await page.getByLabel("登录密码", { exact: true }).fill("wrong-password");
    await page.getByRole("button", { name: "登录工作空间" }).click();
    await expect(page.getByRole("alert")).toContainText("邮箱或密码错误");
    await page.getByLabel("登录密码", { exact: true }).fill("valid-password");
    await page.getByRole("button", { name: "登录工作空间" }).click();
    await expect(page.getByRole("heading", { name: "商品管理" })).toBeVisible();
    const login = requests.find((r) => r.path === "/login");
    expect(login.headers["x-csrf-token"]).toBe("test-csrf-token");
    await page.getByRole("button", { name: "退出登录" }).click();
    await expect(page).toHaveURL(/\/admin\/login/);
    await page.goto("/admin/settings");
    await expect(page).toHaveURL(/\/admin\/login/);
});

test("商品新增、搜索、编辑与上下架可完成，栏目与窄屏导航可用", async ({
    page,
}, testInfo) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    const requests = await mockApi(page, true);
    await page.goto("/admin/");
    await expect(
        page.getByRole("heading", { name: "工作台", exact: true }),
    ).toBeVisible();
    await navigate(page, "商品列表");
    await page
        .getByRole("button", { name: "添加商品", exact: true })
        .first()
        .click();
    await expect(page).toHaveURL(/\/admin\/products\/create$/);
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await expect(page.getByRole("heading", { name: "添加商品" })).toBeVisible();
    await page.reload();
    await page.getByLabel("商品名称").fill("测试帆布袋");
    await page.getByLabel("商品标识").fill("canvas-bag");
    await page.getByLabel("售价（元）").fill("89.50");
    await page.getByLabel("商品描述").fill("轻便日常帆布袋");
    await page.getByLabel("商品标签", { exact: true }).fill("日常");
    await page.getByLabel("商品标签", { exact: true }).press("Enter");
    await expect(page.getByRole("button", { name: "移除商品标签 日常", exact: true })).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/products\/create$/);
    await page.getByLabel("商品标签", { exact: true }).fill("日常, 环保");
    await page.getByLabel("商品标签", { exact: true }).press("Enter");
    await expect(page.getByRole("button", { name: "移除商品标签 日常", exact: true })).toHaveCount(1);
    await page.getByRole("button", { name: "保存商品" }).click();
    await expect(page).toHaveURL(/\/admin\/products$/);
    const dialog = page.locator(".product-create-panel");
    await expect(
        page.getByRole("status").filter({ hasText: "商品已添加" }),
    ).toBeVisible();
    const row = page.getByRole("row").filter({ hasText: "测试帆布袋" });
    await expect(row).toContainText("89.50");
    await row.getByRole("button", { name: "上架", exact: true }).click();
    await expect(row).toContainText("已上架");
    await row.getByRole("link", { name: "测试帆布袋", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/products\/\d+\/edit/);
    await expect(page.getByRole("dialog")).toHaveCount(0);
    await page.reload();
    await expect(dialog.getByLabel("商品名称")).toHaveValue("测试帆布袋");
    await expect(page.getByRole("button", { name: "移除商品标签 日常", exact: true })).toBeVisible();
    await page.getByRole("button", { name: "移除商品标签 环保", exact: true }).click();
    await page.getByLabel("商品标签", { exact: true }).fill("轻便");
    await dialog.getByLabel("商品名称").fill("日常帆布袋");
    await dialog.getByRole("button", { name: "保存商品" }).click();
    await expect(
        page.getByRole("row").filter({ hasText: "日常帆布袋" }),
    ).toBeVisible();
    expect(requests.filter((r) => r.method === "PATCH" && r.body?.tags).at(-1).body.tags).toEqual(["日常", "轻便"]);
    await page.getByRole("textbox", { name: "搜索商品" }).fill("不存在的商品");
    await page.getByRole("button", { name: "查询", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "没有找到匹配的商品" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "清除筛选" }).click();
    await expect(
        page.getByRole("row").filter({ hasText: "日常帆布袋" }),
    ).toBeVisible();
    await navigate(page, "AI 营销");
    await expect(
        page.getByText("AI 服务尚未配置", { exact: true }),
    ).toBeVisible();
    await navigate(page, "账号设置");
    await page.getByRole("textbox", { name: "姓名" }).fill("新的管理员");
    await page.getByLabel("当前密码").fill("valid-password");
    await page.getByRole("button", { name: "保存更改" }).click();
    await expect(page.getByRole("status")).toHaveText("账号信息已保存。");
    await expect(page.locator(".user-link")).toContainText("新的管理员");
    expect(
        requests.some((r) => r.path === "/profile" && r.method === "PATCH"),
    ).toBe(true);
    expect(errors).toEqual([]);
    await navigate(page, "工作台");
    await page.screenshot({
        path: testInfo.outputPath("dashboard.png"),
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});

test("登录过期回到登录页，接口失败可重试，未知页面有返回入口", async ({
    page,
}) => {
    await mockApi(page, true);
    await page.goto("/admin/products");
    await expect(page.getByRole("heading", { name: "商品管理" })).toBeVisible();
    await page.route("**/api/v1/admin/products?**", (route) =>
        route.fulfill({
            status: 500,
            contentType: "application/json",
            body: "{}",
        }),
    );
    await page.getByRole("button", { name: "刷新商品" }).click();
    await expect(page.getByRole("alert")).toContainText("服务器暂时不可用");
    await page.route("**/api/v1/admin/products?**", (route) =>
        route.fulfill({
            status: 401,
            contentType: "application/json",
            body: "{}",
        }),
    );
    await page.getByRole("button", { name: "重试", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/login/);
    await page.reload();
    await page.goto("/admin/missing");
    await expect(
        page.getByRole("heading", { name: "这个页面不存在" }),
    ).toBeVisible();
    await page.getByRole("link", { name: "返回工作台" }).click();
    await expect(
        page.getByRole("heading", { name: "工作台", exact: true }),
    ).toBeVisible();
});

test("规格组合表格支持创建、批量设置、排序、编辑回填和移除", async ({ page }, testInfo) => {
    const requests = await mockApi(page, true);
    await page.goto("/admin/products/create");
    await page.getByLabel("商品名称").fill("多规格音箱");
    await page.getByLabel("商品标识").fill("matrix-speaker");
    await page.getByLabel("售价（元）").fill("199");
    await page.getByLabel("商品描述").fill("多规格商品");
    await page.getByRole("button", { name: "添加规格", exact: true }).click();
    await page.getByLabel("规格名称 1", { exact: true }).fill("color");
    await page.getByLabel("规格值 1", { exact: true }).fill("白色,红色");
    await page.getByLabel("规格值 1", { exact: true }).press("Enter");
    await page.getByRole("button", { name: "添加规格", exact: true }).click();
    await page.getByLabel("规格名称 2", { exact: true }).fill("size");
    await page.getByLabel("规格值 2", { exact: true }).fill("M,L,X");
    await page.getByLabel("规格值 2", { exact: true }).press("Enter");
    await expect(page.locator(".sku-table tbody tr")).toHaveCount(6);
    const combinations = await page.locator(".sku-table tbody tr").evaluateAll((rows) => rows.map((row) => [row.cells[3].textContent.trim(), row.cells[4].textContent.trim()]));
    expect(combinations).toEqual([["白色", "M"], ["白色", "L"], ["白色", "X"], ["红色", "M"], ["红色", "L"], ["红色", "X"]]);
    await page.getByLabel("规格售价 1", { exact: true }).fill("209.50");
    await page.getByLabel("SKU 1", { exact: true }).fill("WHITE-M");
    await page.getByLabel("规格原价 1", { exact: true }).fill("299");
    await page.getByLabel("规格 1 库存跟踪", { exact: true }).check();
    await page.getByLabel("快速选择 color", { exact: true }).selectOption("红色");
    await expect(page.getByText("已选 3 / 6 项")).toBeVisible();
    await page.getByLabel("批量售价", { exact: true }).fill("219");
    await page.getByLabel("批量库存策略").selectOption("on");
    await page.getByRole("button", { name: "应用到选中项" }).click();
    await expect(page.getByLabel("规格售价 4", { exact: true })).toHaveValue("219");
    await page.getByRole("button", { name: "规格排序", exact: true }).click();
    await page.getByRole("button", { name: "上移规格 2", exact: true }).click();
    await expect(page.getByLabel("规格名称 1", { exact: true })).toHaveValue("size");
    await expect(page.getByLabel("SKU 1", { exact: true })).toHaveValue("WHITE-M");
    await page.getByRole("button", { name: "完成排序", exact: true }).click();
    await expect(page.getByLabel("规格售价 1", { exact: true })).toHaveValue("209.5");
    await page.screenshot({ path: testInfo.outputPath("product-sku-editor.png"), fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByRole("button", { name: "保存商品", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/products$/);
    const created = requests.find((request) => request.path === "/products" && request.method === "POST");
    expect(created.body.variant_options).toEqual([{ name: "size", values: ["M", "L", "X"] }, { name: "color", values: ["白色", "红色"] }]);
    expect(created.body.variants[0]).toMatchObject({ sku: "WHITE-M", price: 209.5, compare_at_price: 299, track_inventory: true, option_values: ["M", "白色"] });
    const row = page.getByRole("row").filter({ hasText: "多规格音箱" });
    await row.getByRole("button", { name: "编辑", exact: true }).click();
    const dialog = page.locator(".product-create-panel");
    await expect(dialog.getByLabel("SKU 1", { exact: true })).toHaveValue("WHITE-M");
    await expect(dialog.getByLabel("规格 1 库存跟踪", { exact: true })).toBeChecked();
    await expect(dialog.locator(".sku-table tbody tr")).toHaveCount(6);
    await dialog.getByRole("button", { name: "移除规格值 L", exact: true }).click();
    await dialog.getByRole("button", { name: "移除规格值 X", exact: true }).click();
    await expect(dialog.locator(".sku-table tbody tr")).toHaveCount(2);
    await expect(dialog.getByLabel("SKU 1", { exact: true })).toHaveValue("WHITE-M");
    await dialog.getByRole("button", { name: "保存商品", exact: true }).click();
    await expect(dialog).not.toBeVisible();
    const updated = requests.filter((request) => request.method === "PATCH" && request.body?.variants).at(-1);
    expect(updated.body.variants).toHaveLength(2);
    expect(updated.body.variants[0].id).toBeTruthy();
    await row.getByRole("button", { name: "编辑", exact: true }).click();
    await expect(dialog.locator(".sku-table tbody tr")).toHaveCount(2);
    await expect(dialog.getByLabel("规格原价 1", { exact: true })).toHaveValue("299");
});

test("旧商品规格可转换且保存校验不会丢失表单", async ({ page }) => {
    const requests = await mockApi(page, true);
    await page.goto("/admin/products");
    await page.getByRole("button", { name: "编辑", exact: true }).click();
    const dialog = page.locator(".product-create-panel");
    await expect(dialog.getByLabel("规格售价 1", { exact: true })).toHaveValue("199.00");
    await dialog.getByRole("button", { name: "添加规格", exact: true }).click();
    await dialog.getByRole("button", { name: "保存商品", exact: true }).click();
    await expect(dialog.getByRole("alert")).toContainText("请填写规格名称");
    expect(requests.filter((request) => request.method === "PATCH")).toHaveLength(0);
    await dialog.getByLabel("规格名称 1", { exact: true }).fill("color");
    await dialog.getByLabel("规格值 1", { exact: true }).fill("白色");
    await dialog.getByLabel("规格值 1", { exact: true }).press("Enter");
    await dialog.getByLabel("规格售价 1", { exact: true }).fill("209.50");
    await dialog.getByRole("button", { name: "保存商品", exact: true }).click();
    await expect(dialog).not.toBeVisible();
    const updated = requests.find((request) => request.method === "PATCH");
    expect(updated.body.variants[0]).toMatchObject({ id: 11, option_values: ["白色"], price: 209.5 });
});

test('商品富文本详情可编辑、排版、保存并回填', async ({ page }) => {
    const requests = await mockApi(page, true);
    await page.route("**/api/v1/admin/product-images", route => route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ data: { url: "/storage/products/detail.png" } }) }));
    await page.route("**/storage/products/detail.png", route => route.fulfill({ contentType: "image/png", body: Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=", "base64") }));
    await page.goto('/admin/products');
    const row = page.getByRole('row').filter({hasText: 'Aurora 无线音箱'});
    await row.getByRole('button', {name: '编辑', exact: true}).click();
    const content = page.getByRole('textbox', {name: '商品详情', exact: true});
    await expect(page.locator('.note-editor')).toBeVisible();
    await content.fill('清晰好声音');
    await content.press('Home');
    await page.getByRole('button', { name: '样式', exact: true }).click();
    await page.locator('.note-dropdown-menu [data-value="h2"]').click();
    await expect(content.locator('h2')).toHaveText('清晰好声音');
    await page.getByRole('button', { name: '图片', exact: true }).click();
    await page.locator('.note-image-input').setInputFiles({ name: "detail.png", mimeType: "image/png", buffer: Buffer.from("image") });
    await expect(content.locator('img')).toHaveAttribute('src', '/storage/products/detail.png');
    await page.getByRole('button', {name: '保存商品', exact: true}).click();
    await expect(page).toHaveURL(/\/admin\/products$/);
    const update = requests.find(r => r.path === '/products/1' && r.body?.content?.includes('清晰好声音'));
    expect(update.body.content).toMatch(/<h2(?:\s[^>]*)?>/);
    expect(update.body.content).toContain('/storage/products/detail.png');
    await row.getByRole('button', {name: '编辑', exact: true}).click();
    await expect(content.locator('h2')).toContainText('清晰好声音');
    await expect(content.locator('img')).toHaveCount(1);
    await content.fill('');
    await page.getByRole('button', {name: '保存商品', exact: true}).click();
    await expect(page).toHaveURL(/\/admin\/products$/);
    expect(requests.filter(r=>r.path === '/products/1' && r.method === 'PATCH').at(-1).body.content).toBeNull();
});


test("规格图片支持上传、失败保留原图并可重试", async ({ page }) => {
    await mockApi(page, true);
    let fail = false;
    await page.route("**/api/v1/admin/product-images", async (route) => {
        expect(route.request().headers()["content-type"]).toContain("multipart/form-data; boundary=");
        expect(route.request().headers()["x-csrf-token"]).toBe("test-csrf-token");
        await route.fulfill({ status: fail ? 422 : 201, contentType: "application/json", body: JSON.stringify(fail ? { errors: { image: ["图片上传失败"] } } : { data: { url: "/storage/products/uploaded.png" } }) });
    });
    await page.goto("/admin/products/create");
    await expect(page.getByPlaceholder("图片地址", { exact: true })).toHaveCount(0);
    const file = { name: "image.png", mimeType: "image/png", buffer: Buffer.from("image") };
    await page.getByLabel("上传规格图片 1", { exact: true }).setInputFiles(file);
    await expect(page.locator(".sku-image img").first()).toHaveAttribute("src", "/storage/products/uploaded.png");
    fail = true;
    await page.getByLabel("上传规格图片 1", { exact: true }).setInputFiles(file);
    await expect(page.getByRole("alert")).toContainText("图片上传失败");
    await expect(page.locator(".sku-image img").first()).toHaveAttribute("src", "/storage/products/uploaded.png");
    fail = false;
    await page.getByLabel("上传规格图片 1", { exact: true }).setInputFiles(file);
    await expect(page.getByRole("alert")).toHaveCount(0);
    await page.getByLabel("上传规格图片 1", { exact: true }).setInputFiles({ name: "file.txt", mimeType: "text/plain", buffer: Buffer.from("invalid") });
    await expect(page.getByRole("alert")).toContainText("请选择不超过 2 MB");
});


test("商品管理子栏目支持折叠、导航、刷新和准确选中", async ({ page }) => {
    await mockApi(page, true);
    await page.goto("/admin/products");
    const nav = page.getByRole("navigation", { name: "后台栏目" });
    if (await page.getByRole("button", { name: "打开导航" }).isVisible()) await page.getByRole("button", { name: "打开导航" }).click();
    const parent = nav.getByRole("button", { name: "商品管理" });
    await expect(parent).toHaveAttribute("aria-expanded", "true");
    await parent.click();
    await expect(nav.getByRole("link", { name: "商品列表", exact: true })).not.toBeVisible();
    await parent.click();
    await nav.getByRole("link", { name: "库存列表", exact: true }).click();
    await expect(page.getByRole("heading", { name: /^库存列表/ })).toBeVisible();
    await page.reload();
    if (await page.getByRole("button", { name: "打开导航" }).isVisible()) await page.getByRole("button", { name: "打开导航" }).click();
    await expect(nav.getByRole("link", { name: "库存列表", exact: true })).toHaveClass(/selected/);
    await expect(nav.getByRole("link", { name: "商品列表", exact: true })).not.toHaveClass(/selected/);
    await nav.getByRole("link", { name: "商品分类", exact: true }).click();
    await expect(page.getByRole("heading", { name: "商品分类", exact: true })).toBeVisible();
    await navigate(page, "商品列表");
    await expect(page).toHaveURL(/\/admin\/products$/);
});

test("库存列表显示商品名称和规格，支持政策修改、增减库存与失败提示", async ({ page }) => {
    await mockApi(page, true);
    const item = { id: 11, product_id: 1, product_title: "Aurora 无线音箱", title: "白色 / M", sku: "WHITE-M", inventory_quantity: 10, continue_selling: false, track_inventory: true };
    let conflict = false;
    await page.route("**/api/v1/admin/inventory**", async route => {
        if (route.request().method() === "PATCH") {
            const body = route.request().postDataJSON();
            if (conflict) return route.fulfill({ status: 409, contentType: "application/json", body: JSON.stringify({ message: "库存已发生变化，请刷新后重试。" }) });
            if (body.adjustment !== undefined) {
                expect(body.expected_quantity).toBe(item.inventory_quantity);
                item.inventory_quantity += body.adjustment;
            }
            if (body.continue_selling !== undefined) item.continue_selling = body.continue_selling;
            return route.fulfill({ contentType: "application/json", body: JSON.stringify({ data: item }) });
        }
        const search = new URL(route.request().url()).searchParams.get("search");
        const data = search && !`${item.product_title} ${item.title} ${item.sku}`.includes(search) ? [] : [item];
        return route.fulfill({ contentType: "application/json", body: JSON.stringify({ data, meta: { current_page: 1, last_page: 1, total: data.length } }) });
    });
    await page.goto("/admin/products/inventory");
    await expect(page.getByRole("link", { name: "Aurora 无线音箱 - 白色 / M", exact: true })).toBeVisible();
    for (const name of ["商品规格", "售罄政策", "SKU", "库存数量", "调整库存"]) await expect(page.getByRole("columnheader", { name, exact: true })).toBeVisible();
    await page.getByLabel("调整库存 11", { exact: true }).fill("5");
    await page.getByRole("button", { name: "调整", exact: true }).click();
    await expect(page.getByTestId("quantity-11")).toHaveText("15");
    await page.getByLabel("调整库存 11", { exact: true }).fill("-3");
    await page.getByRole("button", { name: "调整", exact: true }).click();
    await expect(page.getByTestId("quantity-11")).toHaveText("12");
    await page.getByLabel("售罄政策 11", { exact: true }).selectOption("continue");
    await expect(page.getByRole("status")).toContainText("售罄政策已更新");
    await page.reload();
    await expect(page.getByTestId("quantity-11")).toHaveText("12");
    await expect(page.getByLabel("售罄政策 11", { exact: true })).toHaveValue("continue");
    await page.getByLabel("调整库存 11", { exact: true }).fill("-13");
    await page.getByRole("button", { name: "调整", exact: true }).click();
    await expect(page.getByRole("alert")).toContainText("调整后的库存数量");
    conflict = true;
    await page.getByLabel("调整库存 11", { exact: true }).fill("1");
    await page.getByRole("button", { name: "调整", exact: true }).click();
    await expect(page.getByRole("alert")).toContainText("库存已发生变化");
    await expect(page.getByTestId("quantity-11")).toHaveText("12");
    await expect(page.getByLabel("调整库存 11", { exact: true })).toHaveValue("1");
    await page.getByLabel("搜索库存").fill("不存在");
    await page.getByRole("button", { name: "查询", exact: true }).click();
    await expect(page.getByRole("heading", { name: "暂无商品规格" })).toBeVisible();
});


test("商品分类支持创建、编辑、自动分类和置顶", async ({ page }) => {
    await mockApi(page, true);
    const categories = [{ id: 1, name: "自动音频", type: "automatic", match_tag: "音频", is_pinned: false, products_count: 1, available_products_count: 1, created_at: "2026-11-02T10:00:00+08:00", updated_at: "2026-11-02T10:00:00+08:00", product_ids: [] }];
    const changes = [];
    let failPin = false;
    await page.route("**/api/v1/admin/categories**", async route => {
        const url = new URL(route.request().url());
        const id = Number(url.pathname.split("/")[5]);
        const body = route.request().postDataJSON();
        const reply = (data, status = 200) => route.fulfill({ status, contentType: "application/json", body: JSON.stringify(data) });
        if (route.request().method() === "POST" || route.request().method() === "PATCH") {
            changes.push(body);
            if (url.pathname.endsWith("/pin")) {
                if (failPin) return reply({ message: "置顶失败，请重试。" }, 500);
                categories.find(item => item.id === id).is_pinned = body.is_pinned;
            } else {
                let item = categories.find(item => item.id === id);
                if (!item) { item = { id: categories.length + 1, is_pinned: false, created_at: "2026-11-02T11:00:00+08:00" }; categories.push(item); }
                Object.assign(item, body, { updated_at: "2026-11-02T12:00:00+08:00", products_count: body.type === "automatic" ? 1 : body.product_ids.length, available_products_count: body.type === "automatic" ? 1 : body.product_ids.length });
                return reply({ data: item }, route.request().method() === "POST" ? 201 : 200);
            }
            return reply({ data: categories.find(item => item.id === id) });
        }
        if (id) {
            const item = categories.find(item => item.id === id);
            return reply({ data: { ...item, products: (item.product_ids || []).map(id => ({ id, title: "Aurora 无线音箱" })) } });
        }
        const data = categories.filter(item => (!url.searchParams.get("search") || item.name.includes(url.searchParams.get("search"))) && (!url.searchParams.get("type") || item.type === url.searchParams.get("type"))).sort((a, b) => Number(b.is_pinned) - Number(a.is_pinned) || b.id - a.id);
        return reply({ data, meta: { current_page: 1, last_page: 1, total: data.length } });
    });
    await page.goto("/admin/products/categories");
    for (const name of ["分类名称", "已上架商品数", "商品数", "分类类型", "最后更新时间", "创建时间", "操作"]) await expect(page.getByRole("columnheader", { name, exact: true })).toBeVisible();
    await page.getByRole("link", { name: "创建分类", exact: true }).click();
    await expect(page.getByRole("dialog")).toHaveCount(0);
    await page.getByLabel("分类名称").fill("精选商品");
    await page.getByRole("checkbox", { name: "Aurora 无线音箱", exact: true }).check();
    await page.getByRole("button", { name: "保存分类", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/products\/categories$/);
    const row = page.getByRole("row").filter({ hasText: "精选商品" });
    await expect(row).toContainText("手动分类");
    expect(changes[0].product_ids).toEqual([1]);
    await row.getByRole("link", { name: "编辑", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/products\/categories\/\d+\/edit$/);
    await page.reload();
    await expect(page.getByLabel("分类名称")).toHaveValue("精选商品");
    await expect(page.getByRole("button", { name: "移除分类商品 Aurora 无线音箱", exact: true })).toBeVisible();
    await page.getByLabel("分类名称").fill("自动精选");
    await page.getByLabel("分类类型", { exact: true }).selectOption("automatic");
    await page.getByLabel("匹配商品标签").fill("无线");
    await page.getByRole("button", { name: "保存分类", exact: true }).click();
    await expect(page.getByRole("row").filter({ hasText: "自动精选" })).toContainText("自动分类");
    expect(changes.at(-1)).toEqual({ name: "自动精选", type: "automatic", match_tag: "无线" });
    const automatic = page.getByRole("row").filter({ hasText: "自动音频" });
    await automatic.getByRole("button", { name: "置顶", exact: true }).click();
    await expect(page.locator("tbody tr").first()).toContainText("自动音频");
    await page.reload();
    await expect(automatic.getByRole("button", { name: "取消置顶", exact: true })).toBeVisible();
    failPin = true;
    await automatic.getByRole("button", { name: "取消置顶", exact: true }).click();
    await expect(page.getByRole("alert")).toBeVisible();
    await expect(automatic.getByRole("button", { name: "取消置顶", exact: true })).toBeEnabled();
    failPin = false;
    await automatic.getByRole("button", { name: "取消置顶", exact: true }).click();
    await expect(automatic.getByRole("button", { name: "置顶", exact: true })).toBeVisible();
    await page.getByLabel("搜索分类", { exact: true }).fill("不存在");
    await page.getByRole("button", { name: "查询", exact: true }).click();
    await expect(page.getByRole("heading", { name: "没有找到匹配的分类" })).toBeVisible();
});


test("订单模块支持独立折叠、页面跳转和刷新选中", async ({ page }) => {
    await mockApi(page, true);
    await page.goto("/admin/orders");
    await expect(page.getByRole("heading", { name: "所有订单", exact: true })).toBeVisible();
    const nav = page.getByRole("navigation", { name: "后台栏目" });
    const openNav = async () => { await expect(page.locator(".topbar")).toBeVisible(); const button = page.getByRole("button", { name: "打开导航" }); if (await button.isVisible()) await button.click(); };
    await openNav();
    const parent = nav.getByRole("button", { name: "订单模块", exact: true });
    await expect(parent).toHaveAttribute("aria-expanded", "true");
    await parent.click();
    await expect(nav.getByRole("link", { name: "所有订单", exact: true })).not.toBeVisible();
    await expect(nav.getByRole("link", { name: "商品列表", exact: true })).toBeVisible();
    await parent.click();
    await nav.getByRole("link", { name: "弃单管理", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/abandoned$/);
    await expect(page.getByRole("heading", { name: "弃单管理", exact: true })).toBeVisible();
    await page.reload();
    await openNav();
    await expect(nav.getByRole("link", { name: "弃单管理", exact: true })).toHaveAttribute("aria-current", "page");
    await expect(nav.getByRole("link", { name: "所有订单", exact: true })).not.toHaveClass(/selected/);
    await expect(nav.getByRole("link", { name: "商品列表", exact: true })).not.toHaveClass(/selected/);
    await nav.getByRole("link", { name: "所有订单", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/orders$/);
});


test("顾客管理栏目支持跳转、刷新和选中", async ({ page }) => {
    await mockApi(page, true);
    await page.goto("/admin/");
    await navigate(page, "顾客管理");
    await expect(page).toHaveURL(/\/admin\/customers$/);
    await expect(page.getByRole("heading", { name: "顾客管理", exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByRole("heading", { name: "顾客管理", exact: true })).toBeVisible();
    const mobileMenu = page.getByRole("button", { name: "打开导航" });
    if (await mobileMenu.isVisible()) await mobileMenu.click();
    const nav = page.getByRole("navigation", { name: "后台栏目" });
    await expect(nav.getByRole("link", { name: "顾客管理", exact: true })).toHaveClass(/selected/);
    await expect(nav.getByRole("link", { name: "所有订单", exact: true })).not.toHaveClass(/selected/);
    await nav.getByRole("link", { name: "工作台", exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/?$/);
});


test("店铺管理支持折叠、三个栏目跳转及刷新选中", async ({ page }) => {
    await mockApi(page, true);
    await page.goto("/admin/shop");
    await expect(page).toHaveURL(/\/admin\/shop\/pages$/);
    const nav = page.getByRole("navigation", { name: "后台栏目" });
    const openNav = async () => { await expect(page.locator(".topbar")).toBeVisible(); const button = page.getByRole("button", { name: "打开导航" }); if (await button.isVisible()) await button.click(); };
    await openNav();
    const parent = nav.getByRole("button", { name: "店铺管理", exact: true });
    await expect(parent).toHaveAttribute("aria-expanded", "true");
    await parent.click();
    await expect(nav.getByRole("link", { name: "页面管理", exact: true })).not.toBeVisible();
    await expect(nav.getByRole("link", { name: "商品列表", exact: true })).toBeVisible();
    await parent.click();
    for (const [title, path] of [["菜单栏", "menus"], ["主页SEO", "seo"], ["页面管理", "pages"]]) {
        await nav.getByRole("link", { name: title, exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`/admin/shop/${path}$`));
        await expect(page.getByRole("heading", { name: title, exact: true })).toBeVisible();
        await page.reload();
        await openNav();
        await expect(nav.getByRole("link", { name: title, exact: true })).toHaveAttribute("aria-current", "page");
        await expect(nav.locator(".nav-child.selected")).toHaveCount(1);
    }
});
