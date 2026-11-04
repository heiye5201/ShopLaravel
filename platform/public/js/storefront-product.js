import { applicableStyle, mountAdvancedOffers } from "./advanced-offers.js";

(() => {
    const root = document.querySelector("[data-product-detail]");
    if (!root) return;
    const image = root.querySelector("[data-product-image]");
    const placeholder = root.querySelector("[data-image-placeholder]");
    if (image) {
        const loaded = () => {
            placeholder.hidden = true;
            image.hidden = false;
        };
        const failed = () => {
            image.hidden = true;
            placeholder.hidden = false;
        };
        image.addEventListener("load", loaded);
        image.addEventListener("error", failed);
        if (image.complete) image.naturalWidth > 0 ? loaded() : failed();
    }
    const buttons = [...root.querySelectorAll("[data-variant-id]")];
    const variants = buttons.map((button) => ({
        id: button.dataset.variantId,
        title: button.dataset.title,
        cents: Math.round(Number(button.dataset.price) * 100),
        available: !button.disabled,
    }));
    const available = variants.filter(
        (variant) => variant.available && Number.isFinite(variant.cents),
    );
    const quantity = root.querySelector("[data-quantity]");
    const minus = root.querySelector("[data-quantity-minus]");
    const plus = root.querySelector("[data-quantity-plus]");
    const advancedRoot = root.querySelector("[data-advanced-offers]");
    let styles = [];
    try {
        styles = JSON.parse(
            root.querySelector("[data-advanced-styles]")?.textContent || "[]",
        );
    } catch {}
    let advancedInstance = null;
    let activeStyleId = null;
    if (!available.length) {
        return;
    }
    const money = new Intl.NumberFormat("zh-CN", {
        style: "currency",
        currency: "CNY",
    });
    let selectedId = available[0].id;
    let items = [selectedId];
    const byId = new Map(available.map((variant) => [variant.id, variant]));
    function render() {
        buttons.forEach((button) =>
            button.setAttribute(
                "aria-pressed",
                String(button.dataset.variantId === selectedId),
            ),
        );
        root.querySelector("[data-unit-price]").textContent = money.format(
            byId.get(selectedId).cents / 100,
        );
        const style = applicableStyle(styles, selectedId);
        if (style) {
            advancedRoot.hidden = false;
            root.querySelector(".product-quantity").hidden =
                style.design.hide_quantity;
            if (activeStyleId !== style.id) {
                advancedInstance?.destroy();
                activeStyleId = style.id;
                advancedInstance = mountAdvancedOffers(advancedRoot, style, {
                    variants,
                    selectedId,
                    quantity: items.length,
                    image: image && !image.hidden ? image.src : "",
                    onSelection(selection) {
                        items = selection.items;
                        quantity.disabled = false;
                        quantity.value = selection.quantity;
                        minus.disabled = selection.first;
                        plus.disabled = selection.last;
                    },
                });
            } else advancedInstance.setVariant(selectedId);
            return;
        }
        advancedInstance?.destroy();
        advancedInstance = null;
        activeStyleId = null;
        advancedRoot.hidden = true;
        root.querySelector(".product-quantity").hidden = false;
        quantity.disabled = false;
        quantity.value = items.length;
        minus.disabled = items.length <= 1;
        plus.disabled = items.length >= 99;
    }
    function setQuantity(value) {
        if (advancedInstance) {
            advancedInstance.setQuantity(value);
            return;
        }
        const next = Math.min(99, Math.max(1, Math.trunc(Number(value)) || 1));
        items = Array.from(
            { length: next },
            (_, index) => items[index] || selectedId,
        );
        render();
    }
    buttons.forEach((button) =>
        button.addEventListener("click", () => {
            if (!byId.has(button.dataset.variantId)) return;
            selectedId = button.dataset.variantId;
            items = items.map(() => selectedId);
            render();
        }),
    );
    minus.addEventListener("click", () => setQuantity(items.length - 1));
    plus.addEventListener("click", () => setQuantity(items.length + 1));
    quantity.addEventListener("change", () => setQuantity(quantity.value));
    quantity.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            setQuantity(quantity.value);
        }
    });
    render();
})();
