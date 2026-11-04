<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";
import { api, errorText } from "../api";
const menus = ref([]);
const loading = ref(false);
const ready = ref(false);
const saving = ref(false);
const error = ref("");
const notice = ref("");
const editing = ref(false);
const form = ref({});
const search = ref("");
const candidates = ref([]);
const searchType = ref("product");
const searching = ref(false);
const searchError = ref("");
let version = 0;
let disposed = false;
let nextKey = 0;
async function load() {
    loading.value = true;
    error.value = "";
    try { const result = await api("/menus"); if (!disposed) { menus.value = result.data; ready.value = true; } }
    catch (cause) { if (!disposed) error.value = errorText(cause); }
    finally { if (!disposed) loading.value = false; }
}
function edit(menu = null) {
    error.value = ""; notice.value = "";
    form.value = menu ? { ...menu, items: menu.items.map(item => ({ ...item, key: ++nextKey })) } : { name: "", is_primary: !menus.value.some(item => item.is_primary), items: [] };
    editing.value = true;
    candidates.value = []; search.value = ""; searchError.value = ""; version++; searching.value = false;
}
function close() { editing.value = false; version++; }
function add(type = "custom", target = null) {
    if (form.value.items.length >= 100) return;
    form.value.items.push({ key: ++nextKey, title: target?.title || target?.name || "", type, target_id: target?.id || null, url: type === "custom" ? "/" : null });
}
function move(index, offset) {
    const items = form.value.items;
    [items[index], items[index + offset]] = [items[index + offset], items[index]];
}
async function find() {
    const current = ++version;
    searching.value = true; searchError.value = ""; candidates.value = [];
    try {
        const result = await api(`/${searchType.value === 'product' ? 'products' : 'categories'}?${new URLSearchParams({ search: search.value, per_page: 20 })}`);
        if (current === version && !disposed) candidates.value = result.data;
    } catch (cause) { if (current === version && !disposed) searchError.value = errorText(cause); }
    finally { if (current === version && !disposed) searching.value = false; }
}
async function save() {
    if (saving.value) return;
    saving.value = true; error.value = ""; notice.value = "";
    try {
        const body = { name: form.value.name.trim(), is_primary: form.value.is_primary, items: form.value.items.map(({ title, type, target_id, url }) => ({ title: title.trim(), type, target_id, url })) };
        const result = await api(form.value.id ? `/menus/${form.value.id}` : "/menus", { method: form.value.id ? "PATCH" : "POST", body });
        if (disposed) return;
        const menu = result.data;
        menus.value = menus.value.filter(item => item.id !== menu.id).map(item => menu.is_primary ? { ...item, is_primary: false } : item).concat(menu).sort((a, b) => a.id - b.id);
        close(); notice.value = menu.is_primary ? "菜单已保存，主题导航已同步。" : "菜单已保存。";
    } catch (cause) { if (!disposed) error.value = errorText(cause); }
    finally { if (!disposed) saving.value = false; }
}
async function remove(menu) {
    if (saving.value || !window.confirm(`确定删除菜单“${menu.name}”？${menu.is_primary ? '主题将恢复默认导航。' : ''}`)) return;
    saving.value = true; error.value = ""; notice.value = "";
    try {
        await api(`/menus/${menu.id}`, { method: "DELETE" });
        if (!disposed) { menus.value = menus.value.filter(item => item.id !== menu.id); notice.value = menu.is_primary ? "菜单已删除，主题已恢复默认导航。" : "菜单已删除。"; }
    } catch (cause) { if (!disposed) error.value = errorText(cause); }
    finally { if (!disposed) saving.value = false; }
}
onMounted(load);
onBeforeUnmount(() => { disposed = true; version++; });
</script>

<template>
    <div class="page-heading"><div><p class="eyebrow">SHOP</p><h1>商城导航</h1><p class="muted">管理菜单链接与顺序，将一组菜单设为主题导航，保存后立即生效。</p></div><button v-if="!editing" class="button primary" :disabled="!ready || loading || saving" @click="edit()">创建菜单</button></div>
    <p v-if="error" class="alert error" role="alert">{{ error }} <button v-if="!editing" class="text-link" @click="load">重新加载</button></p>
    <p v-if="notice" class="alert success" role="status">{{ notice }}</p>
    <p v-if="loading" class="loading-line" role="status">正在加载菜单…</p>
    <section v-if="editing" class="panel product-create-panel">
        <form class="editor-form" @submit.prevent="save"><fieldset class="menu-fields" :disabled="saving"><div class="editor-body">
            <h2>{{ form.id ? '编辑菜单' : '创建菜单' }}</h2>
            <label>菜单名称<input v-model="form.name" required maxlength="100" /></label>
            <label class="checkbox-label"><input v-model="form.is_primary" type="checkbox" />用作主题导航</label>
            <small class="muted">启用后替换当前主题导航；未指定主题菜单时使用默认导航。空菜单会隐藏导航链接。</small>
            <h3>菜单项（{{ form.items.length }}/100）</h3>
            <p v-if="!form.items.length" class="muted">添加商品、分类或自定义链接。</p>
            <div v-for="(item, index) in form.items" :key="item.key" class="menu-item" :data-testid="'menu-item'">
                <label>链接名称<input v-model="item.title" required maxlength="100" /></label>
                <label v-if="item.type === 'custom'">链接地址<input v-model="item.url" required maxlength="2048" placeholder="/liquid/theme-demo 或 https://example.com" /></label>
                <p v-else class="muted">{{ item.type === 'product' ? '商品' : '分类' }} #{{ item.target_id }} <small>链接随关联数据生成；商品下架或目标删除后不显示在主题导航。</small></p>
                <div class="row-actions"><button type="button" class="button secondary small" :aria-label="`上移第 ${index + 1} 项`" :disabled="index === 0" @click="move(index, -1)">上移</button><button type="button" class="button secondary small" :aria-label="`下移第 ${index + 1} 项`" :disabled="index === form.items.length - 1" @click="move(index, 1)">下移</button><button type="button" class="text-link" :aria-label="`移除第 ${index + 1} 项`" @click="form.items.splice(index, 1)">移除</button></div>
            </div>
            <button type="button" class="button secondary" :disabled="form.items.length >= 100" @click="add()">添加自定义链接</button>
            <h3>选择商品或分类</h3>
            <div class="menu-search"><select v-model="searchType" aria-label="链接类型" @change="version++; candidates = []; searching = false; searchError = ''"><option value="product">商品</option><option value="category">分类</option></select><input v-model="search" aria-label="搜索链接目标" maxlength="100" placeholder="输入名称搜索…" @keydown.enter.prevent="find" /><button type="button" class="button secondary" :disabled="searching" @click="find">搜索</button></div>
            <p v-if="searching" role="status">正在搜索…</p><p v-if="searchError" role="alert" class="field-error">{{ searchError }}</p>
            <div v-for="target in candidates" :key="target.id" class="menu-candidate"><span>{{ target.title || target.name }}</span><button type="button" class="text-link" :disabled="form.items.length >= 100" @click="add(searchType, target)">添加 {{ target.title || target.name }}</button></div>
            <small class="muted">每次最多显示 20 项，可通过名称继续搜索。</small>
        </div><div class="editor-footer"><button type="button" class="button secondary" @click="close">取消</button><button class="button primary">{{ saving ? '保存中…' : '保存菜单' }}</button></div></fieldset></form>
    </section>
    <section v-else-if="ready" class="panel">
        <div v-if="!menus.length" class="empty-state"><h2>暂无菜单</h2><p>创建菜单，设置主题导航链接。</p></div>
        <div v-else class="table-scroll"><table><thead><tr><th>菜单名称</th><th>菜单项</th><th>使用位置</th><th>操作</th></tr></thead><tbody><tr v-for="menu in menus" :key="menu.id"><td>{{ menu.name }}</td><td>{{ menu.items.length }}</td><td>{{ menu.is_primary ? '主题导航' : '未使用' }}</td><td><div class="row-actions"><button class="text-link" :disabled="saving" @click="edit(menu)">编辑 {{ menu.name }}</button><button class="text-link" :disabled="saving" @click="remove(menu)">删除 {{ menu.name }}</button></div></td></tr></tbody></table></div>
    </section>
</template>

<style scoped>
.menu-fields { border: 0; padding: 0; margin: 0; min-width: 0; }
.menu-item { display: grid; gap: 12px; border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 16px; }
.menu-search { display: flex; flex-wrap: wrap; gap: 10px; }
.menu-search input { flex: 1; min-width: 160px; }
.menu-candidate { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; }
</style>
