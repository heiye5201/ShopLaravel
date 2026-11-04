<script setup>
import { computed, ref, watch } from "vue";
import Icon from "./Icon.vue";
import { api, errorText } from "../api";

const props = defineProps({ disabled: Boolean, price: [String, Number], slug: String, errors: { type: Object, default: () => ({}) } });
const options = defineModel("options", { default: () => [] });
const variants = defineModel("variants", { default: () => [] });
let nextKey = 0;
const groups = ref(options.value.map((option) => ({ ...option, values: [...option.values], draft: "", key: ++nextKey })));
const message = ref("");
const sorting = ref(false);
const uploading = ref(new Set());
const uploadErrors = ref({});
const selected = ref([]);
const matchMode = ref("include");
const filters = ref({});
const batchPrice = ref("");
const batchOriginal = ref("");
const batchInventory = ref("");
const allSelected = computed(() => variants.value.length > 0 && variants.value.every((row) => selected.value.includes(row._key)));
const combinationCount = computed(() => groups.value.reduce((count, group) => count * group.values.length, 1));
let previousGroups = groups.value.map((group) => ({ key: group.key, values: [...group.values] }));
const freshRow = () => ({ title: "默认规格", price: props.price, is_available: true, sku: "", image_path: "", compare_at_price: "", track_inventory: false, option_values: [], followProductPrice: true, _key: `new-${++nextKey}` });

function sync() {
    message.value = "";
    options.value = groups.value.map(({ name, values }) => ({ name: name.trim(), values: [...values] }));
    if (groups.value.some((group) => !group.values.length)) return;
    if (combinationCount.value > 100) {
        message.value = "规格组合超过 100 个，请减少规格值。";
        return;
    }
    const combinations = groups.value.reduce((rows, group) => rows.flatMap((row) => group.values.map((value) => [...row, value])), [[]]);
    const oldRows = variants.value;
    const used = new Set();
    variants.value = combinations.map((values) => {
        const previous = oldRows.find((row) => !used.has(row._key) && groups.value.every((group, index) => {
            const oldIndex = previousGroups.findIndex((old) => old.key === group.key);
            return oldIndex === -1 || row.option_values?.[oldIndex] === values[index];
        }));
        if (previous) used.add(previous._key);
        return { ...(previous || freshRow()), title: values.length ? values.join(" / ") : "默认规格", option_values: values };
    });
    previousGroups = groups.value.map((group) => ({ key: group.key, values: [...group.values] }));
    selected.value = selected.value.filter((key) => variants.value.some((row) => row._key === key));
}
function addGroup() {
    if (groups.value.length >= 3) return;
    groups.value.push({ name: "", values: [], draft: "", key: ++nextKey });
    sync();
}
function addValues(group) {
    const values = group.draft.split(/[,，\n]/).map((value) => value.trim()).filter(Boolean);
    if (values.some((value) => value.length > 50)) {
        message.value = "每个规格值最多 50 字。";
        return;
    }
    group.values = [...new Set([...group.values, ...values])];
    group.draft = "";
    sync();
}
function removeValue(group, index) {
    group.values.splice(index, 1);
    sync();
}
function removeGroup(index) {
    groups.value.splice(index, 1);
    filters.value = {};
    sync();
}
function moveGroup(index, offset) {
    const target = index + offset;
    if (target < 0 || target >= groups.value.length) return;
    const [group] = groups.value.splice(index, 1);
    groups.value.splice(target, 0, group);
    filters.value = {};
    sync();
}
function moveValue(group, index, offset) {
    const target = index + offset;
    if (target < 0 || target >= group.values.length) return;
    const [value] = group.values.splice(index, 1);
    group.values.splice(target, 0, value);
    sync();
}
function selectAll() { selected.value = variants.value.map((row) => row._key); }
function quickSelect() {
    const entries = Object.entries(filters.value).filter(([, value]) => typeof value === "string" && value !== "");
    if (!entries.length) return;
    selected.value = variants.value.filter((row) => {
        const matches = entries.every(([index, value]) => row.option_values?.[Number(index)] === value);
        return matchMode.value === "include" ? matches : !matches;
    }).map((row) => row._key);
}
function autoSku(row, index) {
    const base = (props.slug || "SKU").trim().slice(0, 85);
    let suffix = index + 1;
    let value;
    do { value = `${base}-${String(suffix++).padStart(3, "0")}`; }
    while (variants.value.some((other) => other !== row && other.sku === value));
    row.sku = value;
}
function applyBatch() {
    for (const row of variants.value) {
        if (!selected.value.includes(row._key)) continue;
        if (batchPrice.value !== "") { row.price = batchPrice.value; row.followProductPrice = false; }
        if (batchOriginal.value !== "") row.compare_at_price = batchOriginal.value;
        if (batchInventory.value !== "") row.track_inventory = batchInventory.value === "on";
    }
}
async function uploadImage(row, event) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    const key = row._key;
    delete uploadErrors.value[key];
    if (!["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type) || file.size > 2 * 1024 * 1024) {
        uploadErrors.value[key] = "请选择不超过 2 MB 的 JPG、PNG、WebP 或 GIF 图片。";
        return;
    }
    uploading.value.add(key);
    try {
        const body = new FormData();
        body.append("image", file);
        const result = await api("/product-images", { method: "POST", body });
        const current = variants.value.find((item) => item._key === key);
        if (current) current.image_path = result.data.url;
    } catch (cause) {
        uploadErrors.value[key] = errorText(cause);
    } finally {
        uploading.value.delete(key);
    }
}
function validate() {
    if (uploading.value.size) {
        message.value = "图片正在上传，请完成后再保存。";
        return false;
    }
    for (const group of groups.value) {
        if (group.draft.trim()) {
            addValues(group);
            if (group.draft.trim()) return false;
        }
    }
    sync();
    if (groups.value.some((group) => !group.name.trim() || !group.values.length)) message.value = "请填写规格名称，并为每组添加至少一个规格值。";
    else if (new Set(groups.value.map((group) => group.name.trim())).size !== groups.value.length) message.value = "规格名称不能重复。";
    else if (groups.value.some((group) => group.name.trim().length > 50)) message.value = "规格名称最多 50 字。";
    if (message.value) return false;
    variants.value.forEach((row, index) => { if (!row.sku?.trim()) autoSku(row, index); });
    return true;
}
watch(matchMode, quickSelect);
defineExpose({ validate });
</script>

<template>
    <fieldset class="sku-editor" :disabled="disabled">
        <div class="sku-heading">
            <h3>商品规格设置</h3>
            <button type="button" class="text-link" :aria-pressed="sorting" @click="sorting = !sorting">{{ sorting ? '完成排序' : '规格排序' }}</button>
        </div>
        <p class="muted sku-hint">添加颜色、尺寸等规格，自动生成组合。最多 3 组规格、100 个 SKU。</p>
        <div class="option-groups">
            <div v-for="(group, index) in groups" :key="group.key" class="option-group">
                <input v-model="group.name" :aria-label="`规格名称 ${index + 1}`" placeholder="规格名称，如 color" maxlength="50" @change="sync" />
                <div class="option-values">
                    <span v-for="(value, valueIndex) in group.values" :key="value" class="option-chip">
                        {{ value }}
                        <template v-if="sorting">
                            <button type="button" :aria-label="`前移 ${value}`" :disabled="valueIndex === 0" @click="moveValue(group, valueIndex, -1)">←</button>
                            <button type="button" :aria-label="`后移 ${value}`" :disabled="valueIndex === group.values.length - 1" @click="moveValue(group, valueIndex, 1)">→</button>
                        </template>
                        <button v-else type="button" :aria-label="`移除规格值 ${value}`" @click="removeValue(group, valueIndex)"><Icon name="close" :size="13" /></button>
                    </span>
                    <input v-model="group.draft" :aria-label="`规格值 ${index + 1}`" placeholder="输入规格值，按回车添加" @keydown.enter.prevent="addValues(group)" @keydown.,.prevent="addValues(group)" @blur="addValues(group)" />
                </div>
                <div class="row-actions">
                    <template v-if="sorting">
                        <button type="button" class="text-link" :disabled="index === 0" :aria-label="`上移规格 ${index + 1}`" @click="moveGroup(index, -1)">上移</button>
                        <button type="button" class="text-link" :disabled="index === groups.length - 1" :aria-label="`下移规格 ${index + 1}`" @click="moveGroup(index, 1)">下移</button>
                    </template>
                    <button type="button" class="icon-button" :aria-label="`删除规格 ${index + 1}`" @click="removeGroup(index)"><Icon name="trash" :size="18" /></button>
                </div>
            </div>
        </div>
        <button type="button" class="button primary small" :disabled="groups.length >= 3" @click="addGroup"><Icon name="plus" :size="16" />添加规格</button>
        <div v-if="!groups.length" class="muted sku-hint">未设置规格时，使用一条默认 SKU。</div>
        <p v-if="message" class="field-error" role="alert">{{ message }}</p>
        <div class="sku-selection">
            <strong>快速选择：</strong>
            <select v-model="matchMode" aria-label="规格选择方式"><option value="include">包含</option><option value="exclude">不包含</option></select>
            <button type="button" class="text-link" @click="selectAll">全部</button>
            <button type="button" class="text-link" @click="selected = []">清空</button>
            <select v-for="(group, index) in groups" :key="group.key" v-model="filters[index]" :aria-label="`快速选择 ${group.name || index + 1}`" @change="quickSelect">
                <option :value="undefined">{{ group.name || '未命名规格' }}</option>
                <option value="">不限</option>
                <option v-for="value in group.values" :key="value" :value="value">{{ value }}</option>
            </select>
            <span class="muted">已选 {{ selected.length }} / {{ variants.length }} 项</span>
        </div>
        <div v-if="selected.length" class="sku-batch">
            <span>批量设置</span>
            <input v-model="batchPrice" aria-label="批量售价" type="number" min="0" max="99999999.99" step="0.01" placeholder="售价（元）" />
            <input v-model="batchOriginal" aria-label="批量原价" type="number" min="0" max="99999999.99" step="0.01" placeholder="原价（元）" />
            <select v-model="batchInventory" aria-label="批量库存策略"><option value="">库存策略</option><option value="on">开启库存跟踪</option><option value="off">关闭库存跟踪</option></select>
            <button type="button" class="button secondary small" @click="applyBatch">应用到选中项</button>
        </div>
        <div class="sku-table-scroll">
            <table class="sku-table">
                <thead><tr>
                    <th><input type="checkbox" aria-label="选择全部 SKU" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" @change="$event.target.checked ? selectAll() : selected = []" /></th>
                    <th>图片</th><th><span class="required">*</span> SKU</th>
                    <th v-for="group in groups" :key="group.key">{{ group.name || '规格值' }}</th>
                    <th v-if="!groups.length">规格</th>
                    <th><span class="required">*</span> 售价</th><th>原价</th><th>库存策略</th><th>可售</th>
                </tr></thead>
                <tbody>
                    <tr v-for="(row, index) in variants" :key="row._key" :class="{ 'sku-selected': selected.includes(row._key) }">
                        <td><input v-model="selected" type="checkbox" :value="row._key" :aria-label="`选择 SKU ${index + 1}`" /></td>
                        <td><div class="sku-image-cell">
                            <label class="sku-image sku-image-upload" :title="uploading.has(row._key) ? '上传中…' : '选择并上传图片'">
                                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" :aria-label="`上传规格图片 ${index + 1}`" :disabled="uploading.has(row._key)" @change="uploadImage(row, $event)" />
                                <img v-if="row.image_path" :src="row.image_path" :alt="row.title" @error="$event.target.style.visibility = 'hidden'" @load="$event.target.style.visibility = 'visible'" /><Icon v-else name="image" :size="20" /></label>
                        </div><small v-if="uploading.has(row._key)" role="status">上传中…</small><small v-if="uploadErrors[row._key]" class="field-error" role="alert">{{ uploadErrors[row._key] }}</small></td>
                        <td><div class="sku-code"><input v-model="row.sku" :aria-label="`SKU ${index + 1}`" maxlength="100" placeholder="留空自动生成" :aria-invalid="!!errors[`variants.${index}.sku`]" /><button type="button" :aria-label="`自动生成 SKU ${index + 1}`" @click="autoSku(row, index)">自动</button></div></td>
                        <td v-for="(value, valueIndex) in row.option_values" :key="valueIndex">{{ value }}</td>
                        <td v-if="!groups.length">{{ row.title }}</td>
                        <td><div class="sku-money"><span>¥</span><input v-model="row.price" :aria-label="`规格售价 ${index + 1}`" required type="number" min="0" max="99999999.99" step="0.01" :aria-invalid="!!errors[`variants.${index}.price`]" @input="row.followProductPrice = false" /></div></td>
                        <td><div class="sku-money"><span>¥</span><input v-model="row.compare_at_price" :aria-label="`规格原价 ${index + 1}`" type="number" min="0" max="99999999.99" step="0.01" :aria-invalid="!!errors[`variants.${index}.compare_at_price`]" /></div></td>
                        <td><label class="sku-check"><input v-model="row.track_inventory" type="checkbox" :aria-label="`规格 ${index + 1} 库存跟踪`" />开启库存跟踪</label></td>
                        <td><input v-model="row.is_available" type="checkbox" :aria-label="`规格 ${index + 1} 可售`" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="muted sku-hint">点击图片框上传 JPG、PNG、WebP 或 GIF（最大 2 MB）；原价可留空。调整规格值会更新组合，保存后删除已移除的组合。</p>
    </fieldset>
</template>
