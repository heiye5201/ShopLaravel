<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../api";
import RichTextEditor from "../components/RichTextEditor.vue";
import Icon from "../components/Icon.vue";
import ProductVariantsEditor from "../components/ProductVariantsEditor.vue";

const route = useRoute();
const router = useRouter();
const creating = computed(() => route.path === "/products/create");
const editing = computed(() => Boolean(route.params.id));
const editorPage = computed(() => creating.value || editing.value);
const products = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const search = ref("");
const status = ref("");
const loading = ref(false);
const error = ref("");
const notice = ref("");
const variantEditor = ref(null);
const contentEditor = ref(null);
const variantEditorKey = ref(0);
const editingId = ref(null);
const saving = ref(false);
const formError = ref("");
const fieldErrors = ref({});
const toggling = ref(null);
const tagDraft = ref("");
const tagError = ref("");
let variantKey = 0;
const newVariant = (price = "", title = "", isAvailable = true) => ({
    title,
    price,
    is_available: isAvailable,
    _key: ++variantKey,
    followProductPrice: true,
    sku: "",
    image_path: "",
    compare_at_price: "",
    track_inventory: false,
    option_values: [],
});
const defaults = () => ({
    title: "",
    slug: "",
    price: "",
    description: "",
    content: "",
    image_path: "",
    badge: "",
    is_available: false,
    tags: [],
    variant_options: [],
    variants: [newVariant("", "默认规格")],
});
const form = reactive(defaults());
watch(
    () => form.price,
    (value) => {
        form.variants.forEach((variant) => {
            if (variant.followProductPrice) variant.price = value;
        });
    },
);
let requestVersion = 0;
const money = (value) =>
    Number(value).toLocaleString("zh-CN", {
        style: "currency",
        currency: "CNY",
    });

async function load(page = 1) {
    const version = ++requestVersion;
    loading.value = true;
    error.value = "";
    try {
        const params = new URLSearchParams({
            page,
            per_page: 10,
            search: search.value,
            status: status.value,
        });
        const result = await api(`/products?${params}`);
        if (version === requestVersion) {
            products.value = result.data;
            meta.value = result.meta;
        }
    } catch (cause) {
        if (version === requestVersion) error.value = cause.message;
    } finally {
        if (version === requestVersion) loading.value = false;
    }
}
function populateEditor(product = null) {
    tagDraft.value = "";
    tagError.value = "";
    editingId.value = product?.id ?? null;
    Object.assign(
        form,
        product
            ? {
                  ...product,
                  variant_options: product.variant_options || [],
                  content: product.content || "",
                  tags: [...(product.tags || [])],
                  variants: product.variants?.length
                      ? product.variants.map((variant) => ({
                            ...variant,
                            sku: variant.sku || "",
                            image_path: variant.image_path || "",
                            compare_at_price: variant.compare_at_price ?? "",
                            track_inventory: variant.track_inventory || false,
                            option_values: variant.option_values || [],
                            _key: ++variantKey,
                            followProductPrice: false,
                        }))
                      : [
                            newVariant(
                                product.price,
                                "默认规格",
                                product.is_available,
                            ),
                        ],
              }
            : defaults(),
    );
    if (!form.variant_options.length && (form.variants.length > 1 || form.variants[0].title !== "默认规格")) {
        const values = [];
        form.variants.forEach((variant, index) => {
            const base = variant.title.slice(0, 40);
            let value = base;
            let suffix = index + 1;
            while (values.includes(value)) value = `${base} (${suffix++})`;
            values.push(value);
            variant.option_values = [value];
        });
        form.variant_options = [{ name: "规格", values }];
    }
    variantEditorKey.value++;
    formError.value = "";
    fieldErrors.value = {};
}
function closeEditor() {
    if (!saving.value) router.push("/products");
}
async function loadEditor() {
    loading.value = true;
    try {
        let page = 1;
        let result;
        do {
            result = await api(`/products?${new URLSearchParams({ page, per_page: 100, search: route.query.slug || "" })}`);
            const product = result.data.find((item) => String(item.id) === route.params.id);
            if (product) {
                populateEditor(product);
                return;
            }
            page++;
        } while (page <= result.meta.last_page);
        formError.value = "商品不存在或已被删除，请返回商品列表。";
    } catch (cause) {
        formError.value = errorText(cause);
    } finally {
        loading.value = false;
    }
}
function addTags(event) {
    if (event?.isComposing || event?.keyCode === 229) return;
    event?.preventDefault();
    const tags = tagDraft.value.split(/[,，\n]/).map((tag) => tag.trim()).filter(Boolean);
    if (tags.some((tag) => [...tag].length > 50)) {
        tagError.value = "每个标签最多 50 字。";
        return false;
    }
    form.tags = [...new Set([...form.tags, ...tags])];
    tagDraft.value = "";
    tagError.value = "";
    return true;
}
async function save() {
    if (!addTags()) return;
    if (!contentEditor.value?.validate() || !variantEditor.value?.validate()) return;
    saving.value = true;
    formError.value = "";
    fieldErrors.value = {};
    try {
        const body = {
            title: form.title.trim(),
            slug: form.slug.trim(),
            price: form.price,
            description: form.description,
            content: form.content || null,
            image_path: form.image_path || null,
            badge: form.badge || null,
            variant_options: form.variant_options,
            variants: form.variants.map((variant, position) => ({
                ...(variant.id ? { id: variant.id } : {}),
                title: variant.title.trim(),
                sku: variant.sku?.trim() || null,
                image_path: variant.image_path || null,
                compare_at_price: variant.compare_at_price === "" ? null : variant.compare_at_price,
                track_inventory: !!variant.track_inventory,
                option_values: variant.option_values || [],
                price: variant.price,
                is_available: variant.is_available,
                position,
            })),
            is_available: form.is_available,
            tags: [...form.tags],
        };
        await api(
            editingId.value ? `/products/${editingId.value}` : "/products",
            { method: editingId.value ? "PATCH" : "POST", body },
        );
        await router.push({ path: "/products", query: { [editingId.value ? "updated" : "created"]: "1" } });
    } catch (cause) {
        formError.value = errorText(cause);
        fieldErrors.value = cause.errors || {};
    } finally {
        saving.value = false;
    }
}
async function toggle(product) {
    toggling.value = product.id;
    notice.value = "";
    try {
        await api(`/products/${product.id}`, {
            method: "PATCH",
            body: { is_available: !product.is_available },
        });
        notice.value = product.is_available ? "商品已下架。" : "商品已上架。";
        await load(meta.value.current_page);
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        toggling.value = null;
    }
}
onMounted(async () => {
    if (creating.value) return;
    if (editing.value) return loadEditor();
    if (route.query.create) {
        await router.replace("/products/create");
        return;
    }
    if (route.query.created || route.query.updated) {
        notice.value = route.query.updated ? "商品已更新。" : "商品已添加。";
        router.replace("/products");
    }
    await load();
});
onUnmounted(() => {
    requestVersion++;
});
</script>

<template>
    <template v-if="!editorPage">
        <div class="page-heading">
            <div>
                <p class="eyebrow">CATALOG</p>
                <h1>
                    商品管理 <span class="count-badge">{{ meta.total }}</span>
                </h1>
                <p class="muted">管理商品信息，让每一件好商品准备就绪。</p>
            </div>
            <button class="button primary" @click="router.push('/products/create')">
                <Icon name="plus" :size="18" />添加商品
            </button>
        </div>
        <div v-if="notice" class="alert success" role="status">
            {{ notice
            }}<button aria-label="关闭提示" @click="notice = ''">
                <Icon name="close" :size="16" />
            </button>
        </div>
        <div v-if="error" class="alert error" role="alert">
            {{ error }}<button @click="load(meta.current_page)">重试</button>
        </div>
        <section class="panel product-panel">
            <form class="table-toolbar" @submit.prevent="load()">
                <div class="search-input">
                    <Icon name="search" :size="18" /><input
                        v-model="search"
                        aria-label="搜索商品"
                        placeholder="搜索商品名称或标识…"
                        maxlength="100"
                    />
                </div>
                <select v-model="status" aria-label="筛选商品状态" @change="load()">
                    <option value="">全部状态</option>
                    <option value="available">已上架</option>
                    <option value="unavailable">未上架</option></select
                ><button class="button secondary" :disabled="loading">查询</button
                ><button
                    type="button"
                    class="icon-button"
                    :disabled="loading"
                    aria-label="刷新商品"
                    @click="load(meta.current_page)"
                >
                    <Icon name="refresh" :size="18" />
                </button>
            </form>
            <div v-if="loading" class="loading-line" role="status">
                正在加载商品…
            </div>
            <div v-if="!loading && !products.length && !error" class="empty-state">
                <span class="empty-icon"
                    ><Icon :name="search || status ? 'search' : 'box'" :size="34"
                /></span>
                <h3>
                    {{
                        search || status
                            ? "没有找到匹配的商品"
                            : "开始添加你的第一个商品"
                    }}
                </h3>
                <p>
                    {{
                        search || status
                            ? "试试调整关键词或状态筛选。"
                            : "完善商品信息，让商城从这里开始。"
                    }}
                </p>
                <button
                    v-if="!search && !status"
                    class="button primary"
                    @click="router.push('/products/create')"
                >
                    <Icon name="plus" :size="18" />添加商品</button
                ><button
                    v-else
                    class="button secondary"
                    @click="
                        search = '';
                        status = '';
                        load();
                    "
                >
                    清除筛选
                </button>
            </div>
            <div v-else class="table-scroll">
                <table :aria-busy="loading">
                    <thead>
                        <tr>
                            <th>商品信息</th>
                            <th>售价</th>
                            <th>标签</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id">
                            <td>
                                <div class="product-cell">
                                    <span class="product-thumb"
                                        ><Icon name="box" :size="23"
                                    /></span>
                                    <div>
                                        <RouterLink class="product-title-link" :to="{ path: `/products/${product.id}/edit`, query: { slug: product.slug } }">{{ product.title }}</RouterLink
                                        ><small>{{ product.slug }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="price-cell">{{ money(product.price) }}</td>
                            <td>
                                <div class="tag-list">
                                    <span
                                        v-for="tag in (product.tags || []).slice(
                                            0,
                                            2,
                                        )"
                                        :key="tag"
                                        class="tag"
                                        >{{ tag }}</span
                                    ><span
                                        v-if="!product.tags?.length"
                                        class="muted"
                                        >—</span
                                    >
                                </div>
                            </td>
                            <td>
                                <span
                                    class="status"
                                    :class="
                                        product.is_available
                                            ? 'available'
                                            : 'unavailable'
                                    "
                                    ><i></i
                                    >{{
                                        product.is_available ? "已上架" : "未上架"
                                    }}</span
                                >
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a
                                        class="text-link"
                                        :href="`/liquid/theme-demo/product?slug=${encodeURIComponent(product.slug)}`"
                                        target="_blank"
                                        rel="noopener"
                                        >详情</a
                                    >
                                    <button
                                        class="text-link"
                                        @click="router.push({ path: `/products/${product.id}/edit`, query: { slug: product.slug } })"
                                    >
                                        编辑</button
                                    ><button
                                        class="text-link neutral"
                                        :disabled="toggling !== null || loading"
                                        @click="toggle(product)"
                                    >
                                        {{
                                            toggling === product.id
                                                ? "处理中…"
                                                : product.is_available
                                                  ? "下架"
                                                  : "上架"
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-pagination">
                <span>共 {{ meta.total }} 件商品</span>
                <div>
                    <button
                        class="button secondary small"
                        :disabled="loading || meta.current_page <= 1"
                        @click="load(meta.current_page - 1)"
                    >
                        上一页</button
                    ><span>{{ meta.current_page }} / {{ meta.last_page }}</span
                    ><button
                        class="button secondary small"
                        :disabled="loading || meta.current_page >= meta.last_page"
                        @click="load(meta.current_page + 1)"
                    >
                        下一页
                    </button>
                </div>
            </div>
        </section>
        <p class="table-footnote">
            <Icon
                name="lock"
                :size="14"
            />商品更改会同步到商城，请在上架前确认内容。
        </p>
    </template>
    <div v-else class="page-heading">
        <div>
            <p class="eyebrow">CATALOG</p>
            <h1 id="editor-title">{{ editing ? "编辑商品" : "添加商品" }}</h1>
            <p class="muted">完善商品信息、详情与规格，准备上架。</p>
        </div>
        <button class="button secondary" :disabled="saving" @click="closeEditor">返回商品列表</button>
    </div>
    <section v-if="editorPage" class="panel product-create-panel" aria-labelledby="editor-title">
        <p v-if="loading" class="loading-line" role="status">正在加载商品…</p>
        <form v-else class="editor-form" @submit.prevent="save">
            <div class="editor-body">
                <div v-if="formError" class="alert error" role="alert">
                    {{ formError }}
                </div>
                <label
                    >商品名称 <span class="required">*</span
                    ><input
                        v-model="form.title"
                        required
                        maxlength="255"
                        placeholder="例如：便携无线音箱"
                        :aria-invalid="!!fieldErrors.title"
                /></label>
                <div class="form-grid">
                    <label
                        >商品标识 <span class="required">*</span
                        ><input
                            v-model="form.slug"
                            required
                            maxlength="255"
                            pattern="[A-Za-z0-9_-]+"
                            placeholder="portable-speaker"
                            :aria-invalid="!!fieldErrors.slug"
                        /><small>英文字母、数字、短横线或下划线</small></label
                    ><label
                        >售价（元） <span class="required">*</span
                        ><input
                            v-model="form.price"
                            required
                            type="number"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            placeholder="0.00"
                            :aria-invalid="!!fieldErrors.price"
                    /></label>
                </div>
                <label
                    >商品描述 <span class="required">*</span
                    ><textarea
                        v-model="form.description"
                        required
                        rows="4"
                        placeholder="介绍商品特点、用途与细节…"
                        :aria-invalid="!!fieldErrors.description"
                    ></textarea></label
                ><label
                    >图片地址<input
                        v-model="form.image_path"
                        maxlength="2048"
                        placeholder="/images/product.svg 或 https://…"
                /></label>
                <div class="form-grid">
                    <label
                        >展示徽标<input
                            v-model="form.badge"
                            maxlength="100"
                            placeholder="例如：新品" /></label
                    >
                    <div>
                        <label for="product-tags">商品标签</label>
                        <div class="option-values product-tag-input">
                            <span v-for="tag in form.tags" :key="tag" class="option-chip">
                                {{ tag }}
                                <button type="button" :aria-label="`移除商品标签 ${tag}`" :disabled="saving" @click="form.tags = form.tags.filter((item) => item !== tag)"><Icon name="close" :size="13" /></button>
                            </span>
                            <input id="product-tags" v-model="tagDraft" placeholder="输入标签，按回车添加" :disabled="saving" :aria-invalid="!!tagError" @keydown.enter="addTags" @blur="addTags()" />
                        </div>
                        <small class="muted">按回车添加，也可用逗号批量添加，每个不超过 50 字。</small>
                        <p v-if="tagError" class="field-error" role="alert">{{ tagError }}</p>
                    </div>
                </div>
                <ProductVariantsEditor
                    :key="variantEditorKey"
                    ref="variantEditor"
                    v-model:options="form.variant_options"
                    v-model:variants="form.variants"
                    :price="form.price"
                    :slug="form.slug"
                    :disabled="saving"
                    :errors="fieldErrors"
                />
                <RichTextEditor ref="contentEditor" v-model="form.content" :disabled="saving" />
                <p v-if="fieldErrors.content" class="field-error">{{ fieldErrors.content[0] }}</p>
                <label class="checkbox-label"
                    ><input v-model="form.is_available" type="checkbox" /><span
                        >保存后上架商品<small
                            >勾选后，商品将标记为可售。</small
                        ></span
                    ></label
                >
            </div>
            <div class="editor-footer">
                <button
                    class="button secondary"
                    type="button"
                    :disabled="saving"
                    @click="closeEditor"
                >
                    取消</button
                ><button class="button primary" :disabled="saving || (editing && !editingId)">
                    {{ saving ? "保存中…" : "保存商品" }}
                </button>
            </div>
        </form>
    </section>
</template>
