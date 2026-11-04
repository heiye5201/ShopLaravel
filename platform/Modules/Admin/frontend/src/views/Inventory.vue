<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";
import { api, errorText } from "../api";
import Icon from "../components/Icon.vue";

const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const search = ref("");
const loading = ref(false);
const saving = ref(new Set());
const adjustments = ref({});
const rowErrors = ref({});
const error = ref("");
const notice = ref("");
let version = 0;
async function load(page = 1) {
    if (saving.value.size) return;
    const current = ++version;
    loading.value = true;
    error.value = "";
    try {
        const result = await api(`/inventory?${new URLSearchParams({ page, per_page: 10, search: search.value })}`);
        if (current !== version) return;
        rows.value = result.data;
        meta.value = result.meta;
        adjustments.value = {};
        rowErrors.value = {};
    } catch (cause) {
        if (current === version) error.value = errorText(cause);
    } finally {
        if (current === version) loading.value = false;
    }
}
async function update(row, body, select) {
    if (saving.value.has(row.id) || loading.value) return;
    saving.value.add(row.id);
    rowErrors.value[row.id] = "";
    notice.value = "";
    try {
        const result = await api(`/inventory/${row.id}`, { method: "PATCH", body });
        rows.value = rows.value.map((item) => item.id === row.id ? result.data : item);
        if (body.adjustment !== undefined) adjustments.value[row.id] = "";
        notice.value = `${row.product_title} - ${row.title}：${body.adjustment !== undefined ? "库存已调整" : "售罄政策已更新"}。`;
    } catch (cause) {
        rowErrors.value[row.id] = errorText(cause);
        if (select) select.value = row.continue_selling ? "continue" : "deny";
    } finally {
        saving.value.delete(row.id);
    }
}
function adjust(row) {
    const draft = adjustments.value[row.id];
    const value = Number(draft);
    if (draft === undefined || String(draft).trim() === "" || !Number.isInteger(value) || value === 0 || Math.abs(value) > 2147483647) {
        rowErrors.value[row.id] = "请输入非零整数，正数增加、负数减少。";
        return;
    }
    if (row.inventory_quantity + value < 0 || row.inventory_quantity + value > 2147483647) {
        rowErrors.value[row.id] = "调整后的库存数量须在 0 到 2147483647 之间。";
        return;
    }
    update(row, { adjustment: value, expected_quantity: row.inventory_quantity });
}
onMounted(() => load());
onBeforeUnmount(() => version++);
</script>

<template>
    <div class="page-heading">
        <div><p class="eyebrow">CATALOG</p><h1>库存列表 <span class="count-badge">{{ meta.total }}</span></h1><p class="muted">按商品规格管理库存数量与售罄政策。</p></div>
    </div>
    <div v-if="error" class="alert error" role="alert">{{ error }} <button class="text-link" @click="load(meta.current_page)">重试</button></div>
    <div v-if="notice" class="alert success" role="status">{{ notice }}</div>
    <section class="panel">
        <form class="table-toolbar" @submit.prevent="load()">
            <div class="search-input"><Icon name="search" :size="18" /><input v-model="search" aria-label="搜索库存" placeholder="搜索商品名称、规格或 SKU…" maxlength="100" /></div>
            <button class="button secondary" :disabled="loading || saving.size > 0">查询</button>
            <button type="button" class="icon-button" aria-label="刷新库存" :disabled="loading || saving.size > 0" @click="load(meta.current_page)"><Icon name="refresh" :size="18" /></button>
        </form>
        <p v-if="loading" class="loading-line" role="status">正在加载库存…</p>
        <div v-if="!loading && !rows.length && !error" class="empty-state"><Icon name="box" :size="34" /><h2>暂无商品规格</h2><p>添加商品后，可在这里管理各规格的库存。</p></div>
        <div v-else class="table-scroll">
            <table :aria-busy="loading" class="inventory-table">
                <thead><tr><th>商品规格</th><th>售罄政策</th><th>SKU</th><th>库存数量</th><th>调整库存</th></tr></thead>
                <tbody><tr v-for="row in rows" :key="row.id">
                    <td><RouterLink class="product-title-link" :to="`/products/${row.product_id}/edit`">{{ row.product_title }} - {{ row.title }}</RouterLink></td>
                    <td><select :value="row.continue_selling ? 'continue' : 'deny'" :aria-label="`售罄政策 ${row.id}`" :disabled="loading || saving.has(row.id)" @change="update(row, { continue_selling: $event.target.value === 'continue' }, $event.target)"><option value="deny">售罄停止销售</option><option value="continue">售罄继续销售</option></select></td>
                    <td>{{ row.sku || '—' }}</td>
                    <td><strong :data-testid="`quantity-${row.id}`">{{ row.inventory_quantity }}</strong><small v-if="!row.track_inventory" class="muted inventory-note">未开启库存跟踪</small></td>
                    <td><form class="inventory-adjust" @submit.prevent="adjust(row)"><input v-model="adjustments[row.id]" type="number" step="1" min="-2147483647" max="2147483647" :aria-label="`调整库存 ${row.id}`" placeholder="输入增减数量" :disabled="loading || saving.has(row.id)" /><button class="button secondary small" :disabled="loading || saving.has(row.id)">{{ saving.has(row.id) ? '保存中…' : '调整' }}</button></form><p v-if="rowErrors[row.id]" class="field-error" role="alert">{{ rowErrors[row.id] }}</p></td>
                </tr></tbody>
            </table>
        </div>
        <div class="table-pagination"><span>共 {{ meta.total }} 个规格</span><div><button class="button secondary small" :disabled="loading || saving.size > 0 || meta.current_page <= 1" @click="load(meta.current_page - 1)">上一页</button><span>{{ meta.current_page }} / {{ meta.last_page }}</span><button class="button secondary small" :disabled="loading || saving.size > 0 || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">下一页</button></div></div>
    </section>
    <p class="table-footnote">调整库存时，正数增加、负数减少；库存数量不能小于 0。售罄政策适用于开启库存跟踪的规格。</p>
</template>
