<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { mountAdvancedOffers } from "@storefront/advanced-offers.js";
const props = defineProps({ style: Object, product: Object, mobile: Boolean });
const mount = ref(null);
let instance;
const sample = {
    title: "示例商品",
    price: "32.00",
    image_path: null,
    variants: [
        { id: -1, title: "白色", price: "32.00", is_available: true },
        { id: -2, title: "黑色", price: "36.00", is_available: true },
    ],
};
const product = computed(() =>
    props.product?.variants?.length ? props.product : sample,
);
const money = (value) =>
    Number(value).toLocaleString("zh-CN", {
        style: "currency",
        currency: "CNY",
    });
async function render() {
    await nextTick();
    if (!mount.value || !props.style?.tiers?.length) return;
    instance?.destroy();
    const variants = product.value.variants.map((v) => ({
        id: String(v.id),
        title: v.title,
        cents: Math.round(Number(v.price) * 100),
        available: v.is_available,
    }));
    instance = mountAdvancedOffers(mount.value, props.style, {
        variants,
        selectedId: variants.find((v) => v.available)?.id,
        quantity: props.style.tiers[0].quantity,
        image: product.value.image_path || "",
    });
}
watch(() => [props.style, props.product], render, {
    deep: true,
    immediate: true,
});
onBeforeUnmount(() => instance?.destroy());
</script>
<template>
    <div class="advanced-preview" :class="{ mobile }">
        <div class="preview-browser"><i></i><i></i><i></i></div>
        <div class="advanced-preview-page">
            <div class="advanced-preview-image" aria-hidden="true">
                <svg viewBox="0 0 100 100">
                    <rect x="15" y="22" width="70" height="60" rx="4" />
                    <circle cx="65" cy="40" r="7" />
                    <path d="m20 72 22-22 15 15 10-9 14 16" />
                </svg>
            </div>
            <div class="advanced-preview-info">
                <h3>{{ product.title }}</h3>
                <strong class="preview-product-price">{{
                    money(product.price)
                }}</strong
                ><span class="preview-spec-label">款式</span>
                <div class="preview-specs">
                    <span
                        v-for="variant in product.variants.slice(0, 4)"
                        :key="variant.id"
                        >{{ variant.title }}</span
                    >
                </div>
                <div
                    v-if="!style.design.hide_quantity"
                    class="preview-quantity"
                >
                    数量 <span>− &nbsp; 1 &nbsp; ＋</span>
                </div>
                <div ref="mount"></div>
                <button class="preview-cart" disabled>
                    加入购物车 · 暂未开放
                </button>
            </div>
        </div>
    </div>
    <p class="preview-disclaimer">
        {{ product === sample ? "示例数据" : "基于所选商品" }} ·
        预览仅展示样式与商品合计，优惠需由实际规则提供。
    </p>
</template>
