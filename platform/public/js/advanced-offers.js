export const palettes = {
    violet: {
        label: "紫色",
        text: "#712695",
        surface: "#ffffff",
        active_surface: "#f3eaf8",
        border: "#dedede",
        active_border: "#742d99",
    },
    red: {
        label: "红色",
        text: "#b12a32",
        surface: "#ffffff",
        active_surface: "#fff1f1",
        border: "#e7d2d2",
        active_border: "#b12a32",
    },
    orange: {
        label: "橙色",
        text: "#b66525",
        surface: "#ffffff",
        active_surface: "#fff5e9",
        border: "#eadbca",
        active_border: "#d78332",
    },
    black: {
        label: "黑白",
        text: "#252b35",
        surface: "#ffffff",
        active_surface: "#f1f2f4",
        border: "#d6d8df",
        active_border: "#252b35",
    },
    brown: {
        label: "棕色",
        text: "#795656",
        surface: "#ffffff",
        active_surface: "#f6eeee",
        border: "#e5d6d6",
        active_border: "#795656",
    },
    mint: {
        label: "薄荷",
        text: "#22635c",
        surface: "#ffffff",
        active_surface: "#eaf8f5",
        border: "#cde2de",
        active_border: "#368b7d",
    },
};
export const badgeLabels = {
    circle: "圆形徽章",
    hexagon: "六边形徽章",
    star: "星芒徽章",
    wave: "波浪徽章",
    sunburst: "锯齿星章",
    bubble: "气泡徽章",
    rectangle: "圆角矩形标签",
    ribbon: "丝带标签",
    capsule: "胶囊标签",
    flame: "火焰标签",
    arrow: "箭头标签",
    ticket: "票券标签",
    speed: "速度标签",
    "soft-burst": "柔和爆炸徽章",
    "sharp-burst": "尖刺爆炸徽章",
    scallop: "波浪星标徽章",
    oval: "椭圆星标徽章",
    fire: "火爆促销徽章",
};
export function templateText(text, quantity, savings = null) {
    const source = String(text || "");
    if (savings == null && /\{\{\s*\$?saveValue\s*\}\}/.test(source)) return "";
    return source.replace(/\{\{\s*\$?(buyValue|saveValue)\s*\}\}/g, (_, key) =>
        key === "buyValue" ? String(quantity) : String(savings ?? ""),
    );
}
export function applicableStyle(styles, variantId) {
    return (
        styles.find(
            (style) =>
                style.scope !== "variants" ||
                style.variant_ids.map(String).includes(String(variantId)),
        ) || null
    );
}
const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
};
let groupId = 0;
export function mountAdvancedOffers(
    root,
    config,
    { variants, selectedId, quantity = 1, image = "", onSelection = () => {} },
) {
    config = {
        ...config,
        tiers: (config.tiers || []).slice(0, 10).map((tier) => ({
            ...tier,
            quantity: Math.min(
                99,
                Math.max(1, Math.trunc(Number(tier.quantity)) || 1),
            ),
        })),
    };
    if (!config.tiers.length) return null;
    const available = variants.filter(
        (v) => v.available && Number.isFinite(v.cents),
    );
    root.replaceChildren();
    if (!available.length) {
        root.textContent = "该商品暂无可选规格";
        return null;
    }
    const design = config.design;
    const colors =
        design.palette === "custom"
            ? design.colors
            : palettes[design.palette] || palettes.violet;
    root.className = "advanced-offers";
    Object.keys(palettes.violet)
        .filter((key) => key !== "label")
        .forEach((key) => {
            const color = /^#[0-9a-f]{6}$/i.test(colors[key])
                ? colors[key]
                : palettes.violet[key];
            root.style.setProperty(`--ao-${key.replaceAll("_", "-")}`, color);
        });
    root.style.setProperty(
        "--ao-radius",
        `${Math.min(40, Math.max(0, Number(design.radius) || 0))}px`,
    );
    const byId = new Map(available.map((v) => [String(v.id), v]));
    let currentId = byId.has(String(selectedId))
        ? String(selectedId)
        : String(available[0].id);
    const nearest = (value) =>
        config.tiers.reduce(
            (best, tier, index) =>
                Math.abs(tier.quantity - value) <
                Math.abs(config.tiers[best].quantity - value)
                    ? index
                    : best,
            0,
        );
    let active = nearest(quantity);
    const clampQuantity = (value) =>
        Math.min(99, Math.max(1, Math.trunc(Number(value)) || 1));
    let items = Array(clampQuantity(quantity)).fill(currentId);
    const radioName = `advanced-offer-${++groupId}`;
    const currency = new Intl.NumberFormat("zh-CN", {
        style: "currency",
        currency: "CNY",
    });
    const amount = (ids) =>
        currency.format(
            ids.reduce((sum, id) => sum + byId.get(String(id)).cents, 0) / 100,
        );
    function resize(count = config.tiers[active].quantity) {
        items = Array.from({ length: clampQuantity(count) }, (_, index) =>
            byId.has(String(items[index])) ? String(items[index]) : currentId,
        );
    }
    function render() {
        root.replaceChildren();
        const heading = element(
            "div",
            "ao-heading",
            design.title_mode === "custom" ? design.title : "选购组合",
        );
        root.append(heading);
        config.tiers.forEach((tier, index) => {
            const selected = index === active;
            const card = element(
                "section",
                `ao-card${selected ? " is-selected" : ""}${selected && design.marquee ? " ao-marquee" : ""}`,
            );
            card.dataset.tierIndex = index;
            const head = element("div", "ao-card-header");
            const label = element("label", "ao-choice");
            const radio = document.createElement("input");
            radio.type = "radio";
            radio.name = radioName;
            radio.value = String(index);
            radio.checked = selected;
            radio.setAttribute(
                "aria-label",
                `选择层级 ${index + 1}：${tier.quantity} 件`,
            );
            radio.addEventListener("change", () => {
                active = index;
                resize();
                render();
            });
            const copy = element("div", "ao-choice-copy");
            const titleRow = element("div", "ao-title-row");
            titleRow.append(
                element(
                    "strong",
                    "ao-title",
                    templateText(tier.title, tier.quantity) ||
                        `Buy ${tier.quantity}`,
                ),
            );
            if (tier.label)
                titleRow.append(
                    element(
                        "span",
                        "ao-label",
                        templateText(tier.label, tier.quantity),
                    ),
                );
            copy.append(titleRow);
            if (tier.subtitle)
                copy.append(
                    element(
                        "p",
                        "ao-subtitle",
                        templateText(tier.subtitle, tier.quantity),
                    ),
                );
            label.append(radio, copy);
            const total = element(
                "span",
                "ao-total",
                amount(
                    selected
                        ? items
                        : Array(Number(tier.quantity)).fill(currentId),
                ),
            );
            total.setAttribute("aria-label", "层级商品合计");
            head.append(label, total);
            card.append(head);
            const badgeText =
                design.badge_mode === "fixed"
                    ? design.badge_text
                    : templateText(tier.badge_text, tier.quantity);
            if (badgeText) {
                const shape = Object.hasOwn(badgeLabels, design.badge_shape)
                    ? design.badge_shape
                    : "rectangle";
                const badge = element(
                    "span",
                    `ao-badge ao-badge-${shape}${design.badge_animation ? " ao-badge-animated" : ""}${design.badge_tilt ? " ao-badge-tilt" : ""}`,
                    badgeText,
                );
                card.append(badge);
            }
            if (selected) {
                const fields = element("div", "ao-items");
                items.forEach((id, unit) => {
                    const row = element("label", "ao-item");
                    row.append(
                        element("span", "ao-item-number", `#${unit + 1}`),
                    );
                    if (design.thumbnails) {
                        const thumb = element("span", "ao-thumbnail");
                        if (image) {
                            const img = document.createElement("img");
                            img.src = image;
                            img.alt = "";
                            img.loading = "lazy";
                            img.addEventListener("error", () => {
                                img.remove();
                                thumb.textContent = "◇";
                            });
                            thumb.append(img);
                        } else thumb.textContent = "◇";
                        row.append(thumb);
                    }
                    const select = document.createElement("select");
                    select.setAttribute(
                        "aria-label",
                        `组合第 ${unit + 1} 件款式`,
                    );
                    available.forEach((variant) => {
                        const option = document.createElement("option");
                        option.value = String(variant.id);
                        option.textContent = variant.title;
                        select.append(option);
                    });
                    select.value = String(id);
                    select.addEventListener("change", () => {
                        items[unit] = select.value;
                        total.textContent = amount(items);
                        notify();
                    });
                    row.append(select);
                    fields.append(row);
                });
                card.append(fields);
            }
            root.append(card);
        });
        root.append(
            element("p", "ao-note", "商品金额合计，以实际结算优惠为准。"),
        );
        notify();
    }
    function notify() {
        onSelection({
            items: [...items],
            quantity: items.length,
            tierIndex: active,
            first: items.length <= 1,
            last: items.length >= 99,
        });
    }
    render();
    return {
        setVariant(id) {
            if (!byId.has(String(id))) return;
            currentId = String(id);
            items = items.map(() => currentId);
            render();
        },
        setQuantity(value) {
            const count = clampQuantity(value);
            active = nearest(count);
            resize(count);
            render();
        },
        step(direction) {
            active = Math.min(
                config.tiers.length - 1,
                Math.max(0, active + direction),
            );
            resize();
            render();
        },
        destroy() {
            root.replaceChildren();
            root.removeAttribute("style");
            root.className = "";
        },
    };
}
