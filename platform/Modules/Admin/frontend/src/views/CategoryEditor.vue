<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../api";
import Icon from "../components/Icon.vue";
const route = useRoute();
const router = useRouter();
const creating = computed(() => !route.params.categoryId);
const form = reactive({ name: "", type: "manual", match_tag: "" });
const selected = ref([]);
const candidates = ref([]);
const search = ref("");
const loading = ref(!creating.value);
const loaded = ref(creating.value);
const searching = ref(false);
const saving = ref(false);
const error = ref("");
const searchError = ref("");
let version = 0;
let disposed = false;
async function findProducts() {
    const current = ++version;
    searching.value = true;
    searchError.value = "";
    try {
        const result = await api(`/products?${new URLSearchParams({ search: search.value, per_page: 20 })}`);
        if (current === version) candidates.value = result.data;
    } catch (cause) { if (current === version) searchError.value = errorText(cause); }
    finally { if (current === version) searching.value = false; }
}
function toggle(product, checked) {
    if (checked && !selected.value.some(item => item.id === product.id)) selected.value.push({ id: product.id, title: product.title });
    else if (!checked) selected.value = selected.value.filter(item => item.id !== product.id);
}
async function save() {
    if (!loaded.value || saving.value) return;
    saving.value = true;
    error.value = "";
    try {
        const body = { name: form.name.trim(), type: form.type, ...(form.type === 'manual' ? { product_ids: selected.value.map(item => item.id) } : { match_tag: form.match_tag.trim() }) };
        await api(creating.value ? "/categories" : `/categories/${route.params.categoryId}`, { method: creating.value ? "POST" : "PATCH", body });
        await router.push({ path: "/products/categories", query: { saved: "1" } });
    } catch (cause) { error.value = errorText(cause); }
    finally { saving.value = false; }
}
onMounted(async () => {
    if (!creating.value) {
        try {
            const result = await api(`/categories/${route.params.categoryId}`);
            if (disposed) return;
            Object.assign(form, { name: result.data.name, type: result.data.type, match_tag: result.data.match_tag || "" });
            selected.value = result.data.products || [];
            loaded.value = true;
        } catch (cause) { if (!disposed) error.value = errorText(cause); }
        finally { loading.value = false; }
    }
    if (!disposed && loaded.value) findProducts();
});
onBeforeUnmount(() => { disposed = true; version++; });
</script>

<template>
    <div class="page-heading"><div><p class="eyebrow">CATALOG</p><h1>{{ creating ? '创建分类' : '编辑分类' }}</h1></div><button class="button secondary" :disabled="saving" @click="router.push('/products/categories')">返回分类列表</button></div>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <p v-if="loading" class="loading-line" role="status">正在加载分类…</p>
    <section v-else-if="loaded" class="panel product-create-panel"><form class="editor-form" @submit.prevent="save"><fieldset class="category-fields" :disabled="saving"><div class="editor-body">
        <label>分类名称 <span class="required">*</span><input v-model="form.name" required maxlength="100" placeholder="例如：新品推荐" /></label>
        <label>分类类型<select v-model="form.type" aria-label="分类类型"><option value="manual">手动分类</option><option value="automatic">自动分类</option></select></label>
        <template v-if="form.type === 'automatic'"><label>匹配商品标签 <span class="required">*</span><input v-model="form.match_tag" required maxlength="50" placeholder="例如：新品" /><small>自动归入包含此完整标签的商品，商品标签变更后分类结果同步更新。</small></label></template>
        <template v-else>
            <div><strong>分类商品（{{ selected.length }}）</strong><div class="tag-list category-selected"><span v-for="product in selected" :key="product.id" class="option-chip">{{ product.title }}<button type="button" :aria-label="`移除分类商品 ${product.title}`" @click="toggle(product, false)"><Icon name="close" :size="13" /></button></span><p v-if="!selected.length" class="muted">尚未选择商品。</p></div></div>
            <div class="category-product-search"><input v-model="search" aria-label="搜索分类商品" maxlength="100" placeholder="搜索商品名称或标识…" @keydown.enter.prevent="findProducts" /><button type="button" class="button secondary" :disabled="searching" @click="findProducts">搜索商品</button></div>
            <p v-if="searching" role="status" class="muted">正在搜索商品…</p><p v-if="searchError" role="alert" class="field-error">{{ searchError }}</p>
            <div class="category-product-options"><label v-for="product in candidates" :key="product.id" class="checkbox-label"><input type="checkbox" :checked="selected.some(item => item.id === product.id)" @change="toggle(product, $event.target.checked)" /><span>{{ product.title }}</span></label><p v-if="!searching && !candidates.length" class="muted">没有找到商品。</p></div>
            <small class="muted">每次显示最多 20 个匹配商品，可搜索并继续添加，最多选择 1000 个商品。</small>
        </template>
    </div><div class="editor-footer"><button type="button" class="button secondary" @click="router.push('/products/categories')">取消</button><button class="button primary" :disabled="saving">{{ saving ? '保存中…' : '保存分类' }}</button></div></fieldset></form></section>
</template>
