import { test, expect } from "@playwright/test";

async function setup(page) {
    const products = [
        {
            id: 1,
            title: "日常音箱",
            slug: "daily-speaker",
            price: "199.00",
            is_available: true,
            tags: [],
        },
    ];
    let campaign = null;
    const state = {
        configured: true,
        failGeneration: false,
        generationRequests: 0,
    };
    await page.route("**/api/v1/admin/**", async (route) => {
        const request = route.request();
        const path = new URL(request.url()).pathname.replace(
            "/api/v1/admin",
            "",
        );
        const method = request.method();
        const reply = (body, status = 200) =>
            route.fulfill({
                status,
                contentType: "application/json",
                body: JSON.stringify(body),
            });
        if (path === "/me")
            return reply({
                data: { id: 1, name: "管理员", email: "owner@example.com" },
            });
        if (path === "/csrf") return reply({ token: "csrf" });
        if (path === "/products") return reply({ data: products });
        if (path === "/marketing/config")
            return reply({
                data: {
                    configured: state.configured,
                    model: state.configured ? "test-model" : null,
                },
            });
        if (path === "/marketing/generate") {
            state.generationRequests++;
            expect(request.postDataJSON().product_ids).toEqual([1]);
            if (state.failGeneration)
                return reply(
                    {
                        message:
                            "AI 服务连接失败或超时，草稿未被修改，请稍后重试。",
                    },
                    502,
                );
            return reply({
                data: {
                    name: "日常好物",
                    headline: "给生活一点好声音",
                    body: "认识适合日常的音箱。",
                    cta: "探索商品",
                },
            });
        }
        if (path === "/marketing/campaigns" && method === "GET")
            return reply({
                data: campaign ? [campaign] : [],
                meta: {
                    total: campaign ? 1 : 0,
                    current_page: 1,
                    last_page: 1,
                },
            });
        if (path === "/marketing/campaigns" && method === "POST") {
            campaign = {
                ...request.postDataJSON(),
                id: 1,
                status: "draft",
                products,
            };
            return reply({ data: campaign }, 201);
        }
        if (path === "/marketing/campaigns/1/status") {
            campaign.status = request.postDataJSON().status;
            return reply({ data: campaign });
        }
        if (path === "/marketing/campaigns/1") {
            campaign = { ...campaign, ...request.postDataJSON() };
            return reply({ data: campaign });
        }
        return reply({ message: "Not found" }, 404);
    });
    return state;
}

async function create(page) {
    await page
        .getByRole("button", { name: "创建活动", exact: true })
        .first()
        .click();
    const dialog = page.getByRole("dialog", { name: "创建活动" });
    await dialog.getByRole("checkbox", { name: /日常音箱/ }).check();
    return dialog;
}

test("AI 生成只填入草稿，预览样式后手动保存、发布、暂停和编辑", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    const state = await setup(page);
    await page.goto("/admin/marketing");
    await expect(
        page.getByText("AI 文案生成已就绪", { exact: true }),
    ).toBeVisible();
    const dialog = await create(page);
    await dialog.getByRole("button", { name: "AI 生成文案" }).click();
    await expect(dialog.getByLabel("活动名称", { exact: true })).toHaveValue(
        "日常好物",
    );
    await expect(dialog.getByRole("status")).toContainText("不会自动发布");
    await dialog.getByLabel("配色样式").selectOption("mint");
    await expect(dialog.locator(".campaign-preview")).toHaveClass(/mint/);
    await expect(
        dialog.getByRole("heading", { name: "给生活一点好声音" }),
    ).toBeVisible();
    await dialog.getByRole("button", { name: "保存活动" }).click();
    await expect(dialog).not.toBeVisible();
    const card = page.locator(".campaign-card");
    await expect(card.locator(".status")).toHaveText("草稿");
    await card.getByRole("button", { name: "预览", exact: true }).click();
    const preview = page.getByRole("dialog", { name: "活动预览" });
    await expect(
        preview.getByRole("link", { name: "探索商品" }),
    ).toHaveAttribute("href", /slug=daily-speaker/);
    await preview.getByRole("button", { name: "关闭活动预览" }).click();
    await card.getByRole("button", { name: "发布活动" }).click();
    await expect(card.locator(".status")).toHaveText("已发布");
    await expect(
        card.getByRole("button", { name: "编辑", exact: true }),
    ).toBeDisabled();
    await card.getByRole("button", { name: "暂停活动" }).click();
    await expect(card.locator(".status")).toHaveText("已暂停");
    await card.getByRole("button", { name: "编辑", exact: true }).click();
    const edit = page.getByRole("dialog", { name: "编辑活动" });
    await edit.getByLabel("展示标题").fill("更新后的推荐");
    await edit.getByRole("button", { name: "保存活动" }).click();
    await expect(
        card.getByRole("heading", { name: "更新后的推荐" }),
    ).toBeVisible();
    expect(state.generationRequests).toBe(1);
    expect(errors).toEqual([]);
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});

test("生成失败保留已编辑文案；未配置服务时支持手动建活动", async ({ page }) => {
    const state = await setup(page);
    state.failGeneration = true;
    await page.goto("/admin/marketing");
    const dialog = await create(page);
    await dialog.getByLabel("活动名称", { exact: true }).fill("手动活动");
    await dialog.getByLabel("展示标题").fill("保留我的标题");
    await dialog.getByLabel("推荐文案", { exact: true }).fill("保留我的内容");
    await dialog.getByRole("button", { name: "AI 生成文案" }).click();
    await expect(dialog.getByRole("alert")).toContainText("草稿未被修改");
    await expect(dialog.getByLabel("展示标题")).toHaveValue("保留我的标题");
    await dialog.getByRole("button", { name: "取消", exact: true }).click();
    state.configured = false;
    await page.reload();
    await expect(
        page.getByText("AI 服务尚未配置", { exact: true }),
    ).toBeVisible();
    const manual = await create(page);
    await expect(
        manual.getByRole("button", { name: "AI 生成文案" }),
    ).toBeDisabled();
    await manual.getByLabel("活动名称", { exact: true }).fill("手动活动");
    await manual.getByLabel("展示标题").fill("手动标题");
    await manual
        .getByLabel("推荐文案", { exact: true })
        .fill("手动填写商品介绍。");
    await manual.getByRole("button", { name: "保存活动" }).click();
    await expect(
        page
            .locator(".campaign-card")
            .getByRole("heading", { name: "手动活动" }),
    ).toBeVisible();
});
