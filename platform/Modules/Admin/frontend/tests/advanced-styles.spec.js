import { test, expect } from "@playwright/test";
import {
    palettes,
    badgeLabels,
} from "../../../../public/js/advanced-offers.js";
const defaults = () => ({
    name: "",
    enabled: false,
    scope: "all",
    product_ids: [],
    variant_ids: [],
    tiers: [
        {
            quantity: 1,
            title: "Buy {{$buyValue}}",
            subtitle: "",
            label: "Popular",
            badge_text: "Save {{$saveValue}}",
        },
    ],
    design: {
        title_mode: "default",
        title: "",
        palette: "violet",
        colors: Object.fromEntries(
            Object.entries(palettes.violet).filter(([k]) => k !== "label"),
        ),
        thumbnails: false,
        marquee: false,
        radius: 4,
        badge_shape: "rectangle",
        badge_mode: "dynamic",
        badge_text: "推荐",
        badge_animation: false,
        badge_tilt: false,
        hide_quantity: false,
    },
});
test("高级样式创建、设计预览、保存回填与停用", async ({ page }, info) => {
    let saved;
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.route("**/api/v1/admin/**", async (route) => {
        const req = route.request(),
            path = new URL(req.url()).pathname.replace("/api/v1/admin", "");
        const reply = (body, status = 200) =>
            route.fulfill({
                status,
                contentType: "application/json",
                body: JSON.stringify(body),
            });
        if (path === "/me")
            return reply({
                data: { id: 1, name: "管理员", email: "test@example.com" },
            });
        if (path === "/csrf") return reply({ token: "fixture" });
        if (path === "/advanced-styles/options")
            return reply({
                data: {
                    defaults: defaults(),
                    badge_shapes: Object.keys(badgeLabels),
                },
            });
        if (path === "/products")
            return reply({
                data: [],
                meta: { total: 0, last_page: 1, current_page: 1 },
            });
        if (path === "/advanced-styles" && req.method() === "POST") {
            saved = { ...req.postDataJSON(), id: 1 };
            return reply({ data: saved }, 201);
        }
        if (path === "/advanced-styles/1/enabled") {
            saved.enabled = req.postDataJSON().enabled;
            return reply({ data: saved });
        }
        if (path === "/advanced-styles/1" && req.method() === "PATCH") {
            saved = { ...saved, ...req.postDataJSON() };
            return reply({ data: saved });
        }
        if (path === "/advanced-styles/1")
            return reply({ data: saved, products: [] });
        if (path === "/advanced-styles")
            return reply({
                data: saved ? [saved] : [],
                total: saved ? 1 : 0,
                last_page: 1,
                current_page: 1,
            });
        return reply({ data: {} });
    });
    await page.goto("/admin/advanced-styles/new");
    await expect(page.getByLabel("活动名称")).toBeVisible();
    await page.getByLabel("活动名称").fill("夏日选购组合");
    await page.getByLabel("展示高级样式", { exact: true }).check();
    await page.getByRole("button", { name: "添加层级", exact: true }).click();
    await page.getByLabel("层级 2 数量", { exact: true }).fill("3");
    await page.getByLabel("自定义标题", { exact: true }).check();
    await page.getByLabel("自定义主标题").fill("多件一起选");
    await page.getByRole("button", { name: "薄荷配色" }).click();
    await page.getByLabel("启用规格缩略图").check();
    await page.getByLabel("固定文本徽章").check();
    await page.getByLabel("固定徽章文本").fill("热卖");
    await page.getByRole("button", { name: "圆形徽章", exact: true }).click();
    const preview = page.locator(".advanced-preview-panel");
    await expect(preview.locator(".ao-heading")).toHaveText("多件一起选");
    await preview.getByRole("radio", { name: "选择层级 2：3 件" }).check();
    await expect(preview.locator(".ao-items select")).toHaveCount(3);
    await expect(preview.locator(".ao-badge-circle").first()).toHaveText(
        "热卖",
    );
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: info.outputPath("advanced-style-editor.png"),
        fullPage: true,
    });
    await page
        .getByRole("button", { name: "保存高级样式", exact: true })
        .click();
    await expect(page).toHaveURL(/advanced-styles\?saved=1/);
    expect(saved.tiers[1].quantity).toBe(3);
    expect(saved.design.palette).toBe("mint");
    await page.getByRole("link", { name: "编辑", exact: true }).click();
    await expect(page.getByLabel("活动名称")).toHaveValue("夏日选购组合");
    await expect(page.getByLabel("层级 2 数量", { exact: true })).toHaveValue(
        "3",
    );
    await page.getByLabel("活动名称").fill("夏日组合新版");
    await page
        .getByRole("button", { name: "保存高级样式", exact: true })
        .click();
    await page.getByRole("button", { name: "停用", exact: true }).click();
    await expect(page.getByText("未启用", { exact: true })).toBeVisible();
    expect(saved.name).toBe("夏日组合新版");
    expect(errors).toEqual([]);
});

test("商城应用高级样式并按真实规格合计金额", async ({ page }) => {
    test.skip(!process.env.STOREFRONT_PRODUCT_URL, "设置实际商品详情地址");
    const config = { ...defaults(), id: 99, enabled: true };
    config.tiers.push({ ...config.tiers[0], quantity: 3 });
    config.design.hide_quantity = true;
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.route(/http:\/\/shop_jwj\.test\/(js|css)\//, async (route) =>
        route.fulfill({ response: await route.fetch() }),
    );
    await page.route(process.env.STOREFRONT_PRODUCT_URL, async (route) => {
        const response = await route.fetch();
        const html = await response.text();
        await route.fulfill({
            response,
            body: html.replace(
                /(<script[^>]*data-advanced-styles[^>]*>)[\s\S]*?(<\/script>)/,
                (_, start, end) => start + JSON.stringify([config]) + end,
            ),
        });
    });
    await page.goto(process.env.STOREFRONT_PRODUCT_URL);
    const root = page.locator("[data-advanced-offers]");
    await expect(root).toBeVisible();
    await expect(root.locator(".ao-card")).toHaveCount(2);
    await root.getByRole("radio", { name: "选择层级 2：3 件" }).check();
    await expect(root.locator("select")).toHaveCount(3);
    await expect(root.locator(".ao-badge")).toHaveCount(0);
    const choices = page.locator("[data-variant-id]:not(:disabled)");
    const price = Number(await choices.first().getAttribute("data-price"));
    await expect(root.locator(".is-selected .ao-total")).toContainText(
        new Intl.NumberFormat("zh-CN", {
            style: "currency",
            currency: "CNY",
        }).format(price * 3),
    );
    if ((await choices.count()) > 1) {
        const id = await choices.nth(1).getAttribute("data-variant-id");
        await root
            .getByLabel("组合第 2 件款式", { exact: true })
            .selectOption(id);
    }
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    expect(errors).toEqual([]);
});

test("实际后台样式允许逐件增减及输入数量", async ({ page }) => {
    test.skip(!process.env.STOREFRONT_PRODUCT_URL, "设置实际商品详情地址");
    await page.route(/http:\/\/shop_jwj\.test\/(js|css)\//, async (route) =>
        route.fulfill({ response: await route.fetch() }),
    );
    await page.goto(process.env.STOREFRONT_PRODUCT_URL);
    const styles = JSON.parse(
        await page.locator("[data-advanced-styles]").textContent(),
    );
    test.skip(!styles.length, "当前商品没有启用的后台样式");
    const root = page.locator("[data-advanced-offers]");
    const input = page.getByRole("spinbutton", { name: "购买数量" });
    await expect(root).toBeVisible();
    await expect(root.locator(".ao-card")).toHaveCount(styles[0].tiers.length);
    await page.getByRole("button", { name: "增加数量", exact: true }).click();
    await expect(input).toHaveValue("2");
    await expect(root.locator("select")).toHaveCount(2);
    await input.fill("5");
    await input.press("Tab");
    await expect(input).toHaveValue("5");
    await expect(root.locator("select")).toHaveCount(5);
    const unit = Number(
        await page
            .locator("[data-variant-id]:not(:disabled)")
            .first()
            .getAttribute("data-price"),
    );
    await expect(root.locator(".is-selected .ao-total")).toContainText(
        new Intl.NumberFormat("zh-CN", {
            style: "currency",
            currency: "CNY",
        }).format(unit * 5),
    );
    await page.getByRole("button", { name: "减少数量", exact: true }).click();
    await expect(input).toHaveValue("4");
    await input.fill("99");
    await input.press("Enter");
    await expect(input).toHaveValue("99");
    await expect(
        page.getByRole("button", { name: "增加数量", exact: true }),
    ).toBeDisabled();
    await input.fill("1");
    await input.press("Tab");
    await expect(input).toHaveValue("1");
    await expect(page.getByRole("button", { name: "减少数量", exact: true })).toBeDisabled();
});
