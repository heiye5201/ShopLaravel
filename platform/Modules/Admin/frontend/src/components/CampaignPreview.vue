<script setup>
defineProps({ campaign: Object, products: { type: Array, default: () => [] } });
const money = (value) =>
    Number(value).toLocaleString("zh-CN", {
        style: "currency",
        currency: "CNY",
    });
</script>

<template>
    <section
        class="campaign-preview"
        :class="campaign.theme || 'lavender'"
        aria-label="活动样式预览"
    >
        <span class="preview-eyebrow">精选推荐</span>
        <h2>{{ campaign.headline || "在这里写下你的活动标题" }}</h2>
        <p>{{ campaign.body || "添加一段介绍，让顾客发现商品的亮点。" }}</p>
        <div class="preview-products">
            <article v-for="product in products" :key="product.id">
                <strong>{{ product.title }}</strong
                ><span>{{ money(product.price) }}</span
                ><a
                    :href="`/liquid/theme-demo/product?slug=${encodeURIComponent(product.slug)}`"
                    target="_blank"
                    rel="noopener"
                    >{{ campaign.cta || "查看商品" }} →</a
                >
            </article>
            <div v-if="!products.length" class="preview-placeholder">
                选择商品后显示推荐卡片
            </div>
        </div>
    </section>
</template>
