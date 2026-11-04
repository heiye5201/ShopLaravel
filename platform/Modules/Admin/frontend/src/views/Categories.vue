<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../api";
import Icon from "../components/Icon.vue";
const route = useRoute();
const router = useRouter();
const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const search = ref("");
const type = ref("");
const loading = ref(false);
const pinning = ref(null);
const error = ref("");
const notice = ref(route.query.saved ? "分类已保存。" : "");
let version = 0;
const date = value => value ? new Date(value).toLocaleString("zh-CN", { hour12: false }) : "—";
async function load(page = 1) {
    const current = ++version;
    loading.value = true;
    error.value = "";
    try {
        const result = await api(`/categories?${new URLSearchParams({ page, per_page: 10, search: search.value, type: type.value })}`);
        if (version === current) { rows.value = result.data; meta.value = result.meta; }
    } catch (cause) {
        if (version === current) error.value = errorText(cause);
    } finally {
        if (version === current) loading.value = false;
    }
}
async function pin(row) {
    if (pinning.value !== null) return;
    pinning.value = row.id;
    notice.value = "";
    error.value = "";
    try {
        await api(`/categories/${row.id}/pin`, { method: "PATCH", body: { is_pinned: !row.is_pinned } });
        notice.value = row.is_pinned ? "已取消置顶。" : "分类已置顶。";
        await load(1);
    } catch (cause) { error.value = errorText(cause); }
    finally { pinning.value = null; }
}
onMounted(() => { if (route.query.saved) router.replace("/products/categories"); load(); });
onBeforeUnmount(() => version++);
</script>

<template>
    <div class="page-heading"><div><p class="eyebrow">CATALOG</p><h1>商品分类</h1><p class="muted">管理手动分类与自动分类，查看商品数量。</p></div><RouterLink class="button primary" to="/products/categories/create"><Icon name="plus" :size="18" />创建分类</RouterLink></div>
    <p v-if="error" class="alert error" role="alert">{{ error }} <button class="text-link" @click="load(meta.current_page)">重试</button></p>
    <p v-if="notice" class="alert success" role="status">{{ notice }}</p>
    <section class="panel">
        <form class="table-toolbar" @submit.prevent="load()"><div class="search-input"><Icon name="search" :size="18" /><input v-model="search" aria-label="搜索分类" placeholder="搜索分类名称…" maxlength="100" /></div><select v-model="type" aria-label="分类类型筛选" @change="load()"><option value="">全部分类</option><option value="manual">手动分类</option><option value="automatic">自动分类</option></select><button class="button secondary" :disabled="loading || pinning !== null">查询</button><button type="button" class="icon-button" aria-label="刷新分类" :disabled="loading || pinning !== null" @click="load(meta.current_page)"><Icon name="refresh" :size="18" /></button></form>
        <p v-if="loading" class="loading-line" role="status">正在加载分类…</p>
        <div v-if="!loading && !rows.length && !error" class="empty-state"><Icon name="box" :size="34" /><h2>{{ search || type ? '没有找到匹配的分类' : '暂无商品分类' }}</h2><p>创建分类，整理商城商品。</p></div>
        <div v-else class="table-scroll"><table :aria-busy="loading" class="category-table"><thead><tr><th>分类名称</th><th>已上架商品数</th><th>商品数</th><th>分类类型</th><th>最后更新时间</th><th>创建时间</th><th>操作</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td><RouterLink class="product-title-link" :to="`/products/categories/${row.id}/edit`">{{ row.name }}</RouterLink><span v-if="row.is_pinned" class="tag category-pin">置顶</span></td><td>{{ row.available_products_count }}</td><td>{{ row.products_count }}</td><td>{{ row.type === 'manual' ? '手动分类' : '自动分类' }}</td><td><time :datetime="row.updated_at">{{ date(row.updated_at) }}</time></td><td><time :datetime="row.created_at">{{ date(row.created_at) }}</time></td><td><div class="row-actions"><RouterLink class="text-link" :to="`/products/categories/${row.id}/edit`">编辑</RouterLink><button class="text-link" :disabled="loading || pinning !== null" @click="pin(row)">{{ pinning === row.id ? '处理中…' : row.is_pinned ? '取消置顶' : '置顶' }}</button></div></td></tr></tbody></table></div>
        <div class="table-pagination"><span>共 {{ meta.total }} 个分类</span><div><button class="button secondary small" :disabled="loading || pinning !== null || meta.current_page <= 1" @click="load(meta.current_page - 1)">上一页</button><span>{{ meta.current_page }} / {{ meta.last_page }}</span><button class="button secondary small" :disabled="loading || pinning !== null || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">下一页</button></div></div>
    </section>
</template>
