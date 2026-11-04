<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";
import { api, errorText } from "../api";
import { setNavigation } from "../navigation";
const groups = ref([]);
const defaults = ref([]);
const loading = ref(false);
const ready = ref(false);
const saving = ref(false);
const error = ref("");
const notice = ref("");
let disposed = false;
const clone = value => JSON.parse(JSON.stringify(value));
const locked = path => ["/shop", "/shop/menus"].includes(path);
function move(items, index, direction) { [items[index], items[index + direction]] = [items[index + direction], items[index]]; notice.value = ""; }
async function load() {
    loading.value = true; error.value = "";
    try {
        const result = await api("/navigation");
        if (disposed) return;
        if (!Array.isArray(result.data) || result.data.length !== 3 || !Array.isArray(result.defaults)) throw new Error("后台导航配置加载失败，请重试。");
        groups.value = clone(result.data); defaults.value = clone(result.defaults); ready.value = true;
    } catch (cause) { if (!disposed) error.value = errorText(cause); }
    finally { if (!disposed) loading.value = false; }
}
async function save() {
    if (saving.value || !ready.value) return;
    saving.value = true; error.value = ""; notice.value = "";
    try {
        const result = await api("/navigation", { method: "PUT", body: { groups: groups.value } });
        if (disposed) return;
        groups.value = clone(result.data); setNavigation(clone(result.data));
        notice.value = "后台导航已保存，左侧栏目已同步。";
    } catch (cause) { if (!disposed) error.value = errorText(cause); }
    finally { if (!disposed) saving.value = false; }
}
function reset() { groups.value = clone(defaults.value); notice.value = "已恢复默认配置，保存后生效。"; }
onMounted(load);
onBeforeUnmount(() => { disposed = true; });
</script>

<template>
    <div class="page-heading"><div><p class="eyebrow">SHOP</p><h1>菜单栏</h1><p class="muted">管理后台左侧的现有栏目，可修改名称、排序及显示状态，保存后对所有后台账号生效。</p></div></div>
    <p v-if="error" class="alert error" role="alert">{{ error }} <button v-if="!ready" class="text-link" @click="load">重新加载</button></p>
    <p v-if="notice" class="alert success" role="status">{{ notice }}</p>
    <p v-if="loading" class="loading-line" role="status">正在加载后台栏目…</p>
    <form v-if="ready" @submit.prevent="save"><fieldset class="navigation-fields" :disabled="saving">
        <section v-for="(group, groupIndex) in groups" :key="group.id" class="panel navigation-group" :data-testid="`navigation-group-${group.id}`">
            <div class="navigation-row"><label>分组名称<input v-model="group.label" required maxlength="100" :aria-label="`分组名称 ${group.id}`" /></label><div class="row-actions"><button class="button secondary small" type="button" :disabled="groupIndex === 0" :aria-label="`上移分组 ${group.id}`" @click="move(groups, groupIndex, -1)">上移分组</button><button class="button secondary small" type="button" :disabled="groupIndex === groups.length - 1" :aria-label="`下移分组 ${group.id}`" @click="move(groups, groupIndex, 1)">下移分组</button></div></div>
            <div v-for="(item, index) in group.items" :key="item.path" class="navigation-item" :data-testid="`navigation-item-${item.path}`">
                <div class="navigation-row"><label>栏目名称<input v-model="item.title" required maxlength="100" :aria-label="`栏目名称 ${item.path}`" /><small class="muted">{{ item.path }}</small></label><label class="checkbox-label"><input v-model="item.enabled" type="checkbox" :disabled="locked(item.path)" :aria-label="`显示栏目 ${item.path}`" />显示</label><div class="row-actions"><button class="button secondary small" type="button" :disabled="index === 0" :aria-label="`上移栏目 ${item.path}`" @click="move(group.items, index, -1)">上移</button><button class="button secondary small" type="button" :disabled="index === group.items.length - 1" :aria-label="`下移栏目 ${item.path}`" @click="move(group.items, index, 1)">下移</button></div></div>
                <div v-for="(child, childIndex) in item.children || []" :key="child.path" class="navigation-row navigation-child" :data-testid="`navigation-child-${child.path}`"><label>子栏目名称<input v-model="child.title" required maxlength="100" :aria-label="`子栏目名称 ${child.path}`" /><small class="muted">{{ child.path }}</small></label><label class="checkbox-label"><input v-model="child.enabled" type="checkbox" :disabled="locked(child.path)" :aria-label="`显示子栏目 ${child.path}`" />显示</label><div class="row-actions"><button class="button secondary small" type="button" :disabled="childIndex === 0" :aria-label="`上移子栏目 ${child.path}`" @click="move(item.children, childIndex, -1)">上移</button><button class="button secondary small" type="button" :disabled="childIndex === item.children.length - 1" :aria-label="`下移子栏目 ${child.path}`" @click="move(item.children, childIndex, 1)">下移</button></div></div>
            </div>
        </section>
        <p class="muted">关闭父栏目会同时隐藏其子栏目。店铺管理及菜单栏入口保持可见，方便继续管理；隐藏栏目不会限制账号访问权限。</p>
        <div class="navigation-footer"><button type="button" class="button secondary" @click="reset">恢复默认</button><button class="button primary">{{ saving ? '保存中…' : '保存后台导航' }}</button></div>
    </fieldset></form>
</template>

<style scoped>
.navigation-fields { border: 0; padding: 0; margin: 0; min-width: 0; }
.navigation-group { padding: 24px; margin-bottom: 20px; }
.navigation-item { border-top: 1px solid #e2e8f0; margin-top: 18px; padding-top: 18px; }
.navigation-row { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
.navigation-row > label:first-child { display: grid; gap: 6px; flex: 1; min-width: 160px; }
.navigation-child { margin-top: 16px; margin-left: 24px; }
.navigation-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; }
@media (max-width: 600px) { .navigation-group { padding: 16px; } .navigation-child { margin-left: 12px; } }
</style>
