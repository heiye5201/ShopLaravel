import { test, expect } from "@playwright/test";

test("实际部署可加载 Vue 资源，并通过 Laravel 校验匿名会话", async ({
    page,
}) => {
    test.skip(
        !process.env.ADMIN_LIVE_URL,
        "设置 ADMIN_LIVE_URL 后检查实际部署",
    );
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    const response = await page.goto(
        `${process.env.ADMIN_LIVE_URL}/admin/products`,
    );
    expect(response.status()).toBe(200);
    await expect(page).toHaveURL(/\/admin\/login\?redirect=/);
    await expect(page.getByRole("heading", { name: "欢迎回来" })).toBeVisible();
    const me = await page.request.get(
        `${process.env.ADMIN_LIVE_URL}/api/v1/admin/me`,
        { headers: { Accept: "application/json" } },
    );
    expect(me.status()).toBe(401);
    const csrf = await page.request.get(
        `${process.env.ADMIN_LIVE_URL}/api/v1/admin/csrf`,
    );
    expect(csrf.status()).toBe(200);
    expect((await csrf.json()).token).toBeTruthy();
    expect(errors).toEqual([]);
});

test("商城详情在桌面和手机尺寸展示真实商品款式", async ({ page }, testInfo) => {
    test.skip(
        !process.env.STOREFRONT_PRODUCT_URL,
        "设置 STOREFRONT_PRODUCT_URL 后检查实际商品详情",
    );
    // Isolate the default selector from administrator-created styles.
    await page.route(/http:\/\/shop_jwj\.test\/(js|css)\//, async (route) =>
        route.fulfill({ response: await route.fetch() }),
    );
    await page.route(process.env.STOREFRONT_PRODUCT_URL, async (route) => {
        const response = await route.fetch();
        await route.fulfill({
            response,
            body: (await response.text()).replace(
                /(<script[^>]*data-advanced-styles[^>]*>)[\s\S]*?(<\/script>)/,
                "$1[]$2",
            ),
        });
    });
    const response = await page.goto(process.env.STOREFRONT_PRODUCT_URL);
    expect(response.status()).toBe(200);
    await expect(
        page.getByRole("heading", { name: /款式（\d+）/ }),
    ).toBeVisible();
    expect(await page.locator(".product-variant").count()).toBeGreaterThan(0);
    await expect(page.locator(".product-variant__price").first()).toContainText(
        /\d/,
    );
    const choices = page.locator("[data-variant-id]:not(:disabled)");
    await expect(choices.first()).toHaveAttribute("aria-pressed", "true");
    await expect(page.locator('[data-advanced-offers]')).toBeHidden();
    await expect(page.locator('.purchase-preview')).toHaveCount(0);
    await page.getByRole('button', { name: '增加数量' }).click();
    await page.getByRole('button', { name: '增加数量' }).click();
    await expect(page.getByLabel('购买数量')).toHaveValue('3');
    await page.getByLabel('购买数量').fill('5');
    await page.getByLabel('购买数量').press('Tab');
    await expect(page.getByLabel('购买数量')).toHaveValue('5');
    if ((await choices.count()) > 1) {
        await choices.nth(1).click();
        await expect(choices.nth(1)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByLabel('购买数量')).toHaveValue('5');
    }
    await expect(page.locator('[data-advanced-offers]')).toBeHidden();
    await expect(
        page.getByRole("button", { name: /加入购物车/ }),
    ).toBeDisabled();
    await expect(page.locator(".paypal-button")).toBeDisabled();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath("storefront-variants.png"),
        fullPage: true,
    });
});
