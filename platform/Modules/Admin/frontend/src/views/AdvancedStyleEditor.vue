<script setup>
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../api";
import { badgeLabels, palettes } from "@storefront/advanced-offers.js";
import AdvancedStylePreview from "../components/AdvancedStylePreview.vue";
import Icon from "../components/Icon.vue";
const route = useRoute(),
    router = useRouter();
const form = reactive({});
const loading = ref(true),
    saving = ref(false),
    error = ref(""),
    ready = ref(false),
    mobile = ref(false),
    showVariables = ref(false),
    pickerOpen = ref(false),
    productSearch = ref(""),
    finding = ref(false),
    lookupError = ref("");
const choices = ref([]),
    defaults = ref(null),
    shapes = ref([]),
    removeIds = ref([]);
const cache = reactive({});
const editing = computed(() => !!route.params.id);
const selected = computed(() => {
    if (!ready.value) return [];
    if (form.scope === "variants")
        return form.variant_ids.map((id) => {
            for (const product of Object.values(cache)) {
                const variant = (product.variants || []).find(
                    (v) => v.id === id,
                );
                if (variant)
                    return {
                        id,
                        title: `${product.title} / ${variant.title}`,
                        slug: product.slug,
                    };
            }
            return { id, title: `已删除的规格 #${id}`, slug: "" };
        });
    return form.product_ids.map((id) => ({
        id,
        title: cache[id]?.title || `已删除的商品 #${id}`,
        slug: cache[id]?.slug || "",
    }));
});
const previewProduct = computed(() =>
    form.scope === "variants"
        ? Object.values(cache).find((product) =>
              product.variants?.some((v) => form.variant_ids.includes(v.id)),
          )
        : cache[form.product_ids?.[0]] || choices.value[0],
);
const clone = (value) => JSON.parse(JSON.stringify(value));
async function load() {
    loading.value = true;
    ready.value = false;
    error.value = "";
    try {
        const [options, products, detail] = await Promise.all([
            api("/advanced-styles/options"),
            api("/products?per_page=20"),
            editing.value
                ? api(`/advanced-styles/${route.params.id}`)
                : Promise.resolve(null),
        ]);
        defaults.value = options.data.defaults;
        shapes.value = options.data.badge_shapes;
        choices.value = products.data;
        [...products.data, ...(detail?.products || [])].forEach((product) => {
            cache[product.id] = product;
        });
        Object.assign(form, clone(detail?.data || defaults.value));
        ready.value = true;
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        loading.value = false;
    }
}
async function findProducts() {
    finding.value = true;
    lookupError.value = "";
    try {
        const { data } = await api(
            `/products?per_page=20&search=${encodeURIComponent(productSearch.value)}`,
        );
        choices.value = data;
        data.forEach((product) => {
            cache[product.id] = product;
        });
    } catch (cause) {
        lookupError.value = errorText(cause);
    } finally {
        finding.value = false;
    }
}
function remove(id) {
    const key = form.scope === "variants" ? "variant_ids" : "product_ids";
    form[key] = form[key].filter((value) => value !== id);
    removeIds.value = removeIds.value.filter((value) => value !== id);
}
function addTier() {
    const last = form.tiers.at(-1);
    if (form.tiers.length < 10 && last.quantity < 99)
        form.tiers.push({
            quantity: Number(last.quantity) + 1,
            title: "Buy {{$buyValue}}",
            subtitle: "",
            label: "",
            badge_text: "Save {{$saveValue}}",
        });
}
function resetColors() {
    form.design.palette = "violet";
    form.design.colors = clone(defaults.value.design.colors);
}
async function save() {
    saving.value = true;
    error.value = "";
    try {
        const body = {
            name: form.name,
            enabled: form.enabled,
            scope: form.scope,
            product_ids: form.product_ids,
            variant_ids: form.variant_ids,
            tiers: form.tiers,
            design: form.design,
        };
        await api(
            editing.value
                ? `/advanced-styles/${route.params.id}`
                : "/advanced-styles",
            { method: editing.value ? "PATCH" : "POST", body },
        );
        await router.push("/advanced-styles?saved=1");
    } catch (cause) {
        error.value = errorText(cause);
        window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
        saving.value = false;
    }
}
watch(() => route.params.id, load, { immediate: true });
watch(
    () => form.scope,
    () => {
        removeIds.value = [];
        pickerOpen.value = false;
    },
);
</script>
<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">ADVANCED OFFER STUDIO</p>
            <h1>{{ editing ? "编辑高级样式" : "创建高级样式" }}</h1>
            <p class="muted">左侧调整表单，右侧实时预览商城效果。</p>
        </div>
        <RouterLink to="/advanced-styles" class="button secondary"
            >返回列表</RouterLink
        >
    </div>
    <div v-if="error" class="alert error" role="alert">
        {{ error }}<button v-if="!ready" @click="load">重试</button>
    </div>
    <div v-if="loading" class="panel empty-state">正在加载样式…</div>
    <template v-if="ready"
        ><div class="advanced-info-note">
            启用并保存后应用于关联商品。当前支持自定义活动；分类和真实优惠活动关联尚未接入。折扣变量需实际优惠结果，不会自动生成优惠或修改售价。
        </div>
        <form class="advanced-editor-layout" @submit.prevent="save">
            <fieldset class="advanced-settings" :disabled="saving">
                <section class="panel design-panel">
                    <div class="design-section-title">
                        <h2>展示高级样式</h2>
                        <label class="toggle-field"
                            ><input
                                v-model="form.enabled"
                                type="checkbox"
                                role="switch"
                                aria-label="展示高级样式"
                            /><span>{{
                                form.enabled ? "启用" : "关闭"
                            }}</span></label
                        >
                    </div>
                    <label
                        >活动名称 <span class="required">*</span
                        ><input
                            v-model="form.name"
                            required
                            maxlength="100"
                            placeholder="例如：梯度选购推荐列表"
                    /></label>
                </section>
                <section class="panel design-panel">
                    <h2>活动类型</h2>
                    <label class="choice-line"
                        ><input
                            type="radio"
                            name="activity-type"
                            disabled
                        />关联优惠活动 <small>待接入优惠引擎</small></label
                    ><label class="choice-line"
                        ><input
                            type="radio"
                            name="activity-type"
                            checked
                        />自定义活动</label
                    >
                </section>
                <section class="panel design-panel">
                    <h2>关联活动商品</h2>
                    <label
                        v-for="option in [
                            { value: 'all', label: '全部商品' },
                            {
                                value: 'category',
                                label: '指定商品分类（分类模块尚未接入）',
                                disabled: true,
                            },
                            { value: 'products', label: '指定商品' },
                            { value: 'variants', label: '指定商品规格' },
                            { value: 'exclude', label: '排除部分商品' },
                        ]"
                        :key="option.value"
                        class="choice-line"
                        ><input
                            v-model="form.scope"
                            type="radio"
                            name="product-scope"
                            :value="option.value"
                            :disabled="option.disabled"
                        />{{ option.label }}</label
                    >
                    <template v-if="form.scope !== 'all'"
                        ><div class="selection-toolbar">
                            <button
                                class="button secondary small"
                                type="button"
                                @click="pickerOpen = !pickerOpen"
                            >
                                {{
                                    pickerOpen
                                        ? "收起选择"
                                        : form.scope === "variants"
                                          ? "添加规格"
                                          : "添加商品"
                                }}</button
                            ><span
                                >已选择 {{ selected.length }}
                                {{
                                    form.scope === "variants"
                                        ? "个规格"
                                        : "件商品"
                                }}</span
                            ><button
                                v-if="removeIds.length"
                                class="text-link"
                                type="button"
                                @click="[...removeIds].forEach(remove)"
                            >
                                移除选中
                            </button>
                        </div>
                        <div v-if="pickerOpen" class="advanced-product-picker">
                            <div class="product-picker-search">
                                <input
                                    v-model="productSearch"
                                    aria-label="搜索关联商品"
                                    placeholder="商品名称或标识"
                                    @keydown.enter.prevent="findProducts"
                                /><button
                                    class="button secondary small"
                                    type="button"
                                    :disabled="finding"
                                    @click="findProducts"
                                >
                                    搜索
                                </button>
                            </div>
                            <p v-if="lookupError" class="alert error">
                                {{ lookupError }}
                            </p>
                            <p v-if="finding" class="muted">正在搜索…</p>
                            <div class="advanced-picker-results">
                                <div
                                    v-for="product in choices"
                                    :key="product.id"
                                >
                                    <label
                                        v-if="form.scope !== 'variants'"
                                        class="choice-line"
                                        ><input
                                            v-model="form.product_ids"
                                            type="checkbox"
                                            :value="product.id"
                                        />{{ product.title }}</label
                                    ><template v-else
                                        ><strong>{{ product.title }}</strong
                                        ><label
                                            v-for="variant in product.variants ||
                                            []"
                                            :key="variant.id"
                                            class="choice-line"
                                            ><input
                                                v-model="form.variant_ids"
                                                type="checkbox"
                                                :value="variant.id"
                                            />{{ variant.title }} · ¥{{
                                                variant.price
                                            }}</label
                                        ><small
                                            v-if="!product.variants?.length"
                                            class="muted"
                                            >该商品暂无规格</small
                                        ></template
                                    >
                                </div>
                                <p v-if="!choices.length" class="muted">
                                    暂无匹配商品
                                </p>
                            </div>
                        </div>
                        <div class="selected-design-products">
                            <div
                                v-for="item in selected"
                                :key="item.id"
                                class="selected-design-product"
                            >
                                <input
                                    v-model="removeIds"
                                    type="checkbox"
                                    :value="item.id"
                                    :aria-label="`选择移除 ${item.title}`"
                                /><span class="product-thumb"
                                    ><Icon name="box" /></span
                                ><strong>{{ item.title }}</strong
                                ><a
                                    v-if="item.slug"
                                    :href="`/liquid/theme-demo/product?slug=${encodeURIComponent(item.slug)}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-link"
                                    >预览</a
                                ><button
                                    class="text-link"
                                    type="button"
                                    :aria-label="`移除 ${item.title}`"
                                    @click="remove(item.id)"
                                >
                                    移除
                                </button>
                            </div>
                        </div></template
                    >
                </section>
                <section class="panel design-panel">
                    <h2>模板选择</h2>
                    <div class="template-choice">
                        <Icon name="grid" :size="31" /><strong>推荐列表</strong
                        ><span>数量层级 + 每件规格选择</span
                        ><Icon name="check" :size="17" />
                    </div>
                </section>
                <section class="panel design-panel">
                    <div class="design-section-title">
                        <h2>活动设计</h2>
                        <button
                            class="text-link"
                            type="button"
                            @click="showVariables = !showVariables"
                        >
                            查看变量
                        </button>
                    </div>
                    <div v-if="showVariables" class="variable-help">
                        <code v-text="'{{$buyValue}}'"></code
                        >：该层级购买数量。<br /><code
                            v-text="'{{$saveValue}}'"
                        ></code
                        >：实际优惠结果；当前未接入优惠引擎，包含此变量的动态徽章将隐藏。
                    </div>
                    <details
                        v-for="(tier, index) in form.tiers"
                        :key="index"
                        class="tier-editor"
                        open
                    >
                        <summary>
                            层级 {{ index + 1 }}
                            <span>{{ tier.quantity }} 件</span>
                        </summary>
                        <div class="tier-fields">
                            <div class="form-grid">
                                <label
                                    >层级 {{ index + 1 }} 标题<input
                                        v-model="tier.title"
                                        required
                                        maxlength="100" /></label
                                ><label
                                    >层级 {{ index + 1 }} 优惠标签<input
                                        v-model="tier.label"
                                        maxlength="50"
                                        placeholder="Popular"
                                /></label>
                            </div>
                            <div class="form-grid">
                                <label
                                    >层级 {{ index + 1 }} 副标题<input
                                        v-model="tier.subtitle"
                                        maxlength="200" /></label
                                ><label
                                    >层级 {{ index + 1 }} 优惠徽章<input
                                        v-model="tier.badge_text"
                                        maxlength="100"
                                        :disabled="
                                            form.design.badge_mode === 'fixed'
                                        "
                                /></label>
                            </div>
                            <div class="tier-quantity-row">
                                <label
                                    >层级 {{ index + 1 }} 数量<input
                                        v-model.number="tier.quantity"
                                        type="number"
                                        :min="
                                            index > 0
                                                ? Number(
                                                      form.tiers[index - 1]
                                                          .quantity,
                                                  ) + 1
                                                : 1
                                        "
                                        :max="
                                            index < form.tiers.length - 1
                                                ? Number(
                                                      form.tiers[index + 1]
                                                          .quantity,
                                                  ) - 1
                                                : 99
                                        "
                                        required
                                        step="1" /></label
                                ><button
                                    class="text-link"
                                    type="button"
                                    :disabled="form.tiers.length <= 1"
                                    :aria-label="`移除层级 ${index + 1}`"
                                    @click="form.tiers.splice(index, 1)"
                                >
                                    移除层级
                                </button>
                            </div>
                        </div>
                    </details>
                    <button
                        class="button secondary small"
                        type="button"
                        :disabled="
                            form.tiers.length >= 10 ||
                            Number(form.tiers.at(-1).quantity) >= 99
                        "
                        @click="addTier"
                    >
                        添加层级
                    </button>
                </section>
                <section class="panel design-panel">
                    <h2>样式设计</h2>
                    <h3>主标题文案</h3>
                    <label class="choice-line"
                        ><input
                            v-model="form.design.title_mode"
                            type="radio"
                            name="main-title"
                            value="default"
                        />默认标题：选购组合</label
                    ><label class="choice-line"
                        ><input
                            v-model="form.design.title_mode"
                            type="radio"
                            name="main-title"
                            value="custom"
                        />自定义标题</label
                    ><input
                        v-model="form.design.title"
                        aria-label="自定义主标题"
                        :required="form.design.title_mode === 'custom'"
                        :disabled="form.design.title_mode !== 'custom'"
                        maxlength="100"
                        placeholder="Buy More Save More!"
                    />
                    <h3>配色</h3>
                    <div class="palette-options">
                        <button
                            v-for="(palette, key) in palettes"
                            :key="key"
                            class="palette-option"
                            :class="{ selected: form.design.palette === key }"
                            :style="{
                                color: palette.text,
                                background: palette.active_surface,
                            }"
                            :aria-label="`${palette.label}配色`"
                            :aria-pressed="form.design.palette === key"
                            type="button"
                            @click="form.design.palette = key"
                        >
                            Aa
                            <i
                                :style="{ background: palette.active_border }"
                            ></i>
                        </button>
                    </div>
                    <label class="choice-line"
                        ><input
                            :checked="form.design.palette === 'custom'"
                            type="checkbox"
                            @change="
                                form.design.palette = $event.target.checked
                                    ? 'custom'
                                    : 'violet'
                            "
                        />自定义颜色<button
                            class="text-link"
                            type="button"
                            @click="resetColors"
                        >
                            重置
                        </button></label
                    >
                    <div
                        v-if="form.design.palette === 'custom'"
                        class="custom-colors"
                    >
                        <label
                            v-for="(label, key) in {
                                text: '文字',
                                surface: '默认背景',
                                active_surface: '选中背景',
                                border: '默认边框',
                                active_border: '选中边框与徽章',
                            }"
                            :key="key"
                            >{{ label
                            }}<span
                                ><input
                                    v-model="form.design.colors[key]"
                                    type="color"
                                    :aria-label="`${label}颜色`" /><input
                                    v-model="form.design.colors[key]"
                                    :aria-label="`${label}色值`"
                                    pattern="#[0-9a-fA-F]{6}"
                                    maxlength="7"
                                    required /></span
                        ></label>
                    </div>
                    <h3>显示规格缩略图</h3>
                    <label class="choice-line"
                        ><input
                            v-model="form.design.thumbnails"
                            type="checkbox"
                        />启用规格缩略图</label
                    >
                    <p class="setting-help">
                        当前规格共用商品图片，缺图时显示占位图标。
                    </p>
                    <h3>跑马灯边框</h3>
                    <label class="choice-line"
                        ><input
                            v-model="form.design.marquee"
                            type="checkbox"
                        />启用跑马灯边框</label
                    >
                    <h3>圆角</h3>
                    <label class="radius-control"
                        ><input
                            v-model.number="form.design.radius"
                            type="number"
                            min="0"
                            max="40"
                            step="1"
                            aria-label="样式圆角"
                            required
                        />px</label
                    >
                    <h3>优惠徽章</h3>
                    <div class="badge-mode">
                        <label
                            ><input
                                v-model="form.design.badge_mode"
                                type="radio"
                                name="badge-mode"
                                value="dynamic"
                            />动态文本徽章</label
                        ><label
                            ><input
                                v-model="form.design.badge_mode"
                                type="radio"
                                name="badge-mode"
                                value="fixed"
                            />固定文本徽章</label
                        >
                    </div>
                    <label v-if="form.design.badge_mode === 'fixed'"
                        >固定徽章文本<input
                            v-model="form.design.badge_text"
                            maxlength="100"
                    /></label>
                    <p class="setting-help">
                        下方为徽章外形示意；实际文本取自层级或固定文本配置。
                    </p>
                    <div class="badge-options">
                        <button
                            v-for="shape in shapes"
                            :key="shape"
                            class="badge-option"
                            :class="{
                                selected: form.design.badge_shape === shape,
                            }"
                            :aria-pressed="form.design.badge_shape === shape"
                            :aria-label="badgeLabels[shape]"
                            type="button"
                            @click="form.design.badge_shape = shape"
                        >
                            <span class="badge-swatch"
                                ><span
                                    class="ao-badge"
                                    :class="`ao-badge-${shape}`"
                                    >HOT</span
                                ></span
                            ><small>{{ badgeLabels[shape] }}</small>
                        </button>
                    </div>
                    <label class="choice-line"
                        ><input
                            v-model="form.design.badge_animation"
                            type="checkbox"
                        />启用优惠徽章动效</label
                    ><label class="choice-line"
                        ><input
                            v-model="form.design.badge_tilt"
                            type="checkbox"
                        />倾斜显示优惠徽章</label
                    >
                    <h3>其他</h3>
                    <label class="choice-line"
                        ><input
                            v-model="form.design.hide_quantity"
                            type="checkbox"
                        />隐藏商品详情页数量选择器</label
                    >
                </section>
                <div class="advanced-save-bar">
                    <RouterLink to="/advanced-styles" class="button secondary"
                        >取消</RouterLink
                    ><button class="button primary" :disabled="saving">
                        {{ saving ? "保存中…" : "保存高级样式" }}
                    </button>
                </div>
            </fieldset>
            <aside class="advanced-preview-panel">
                <div class="panel">
                    <div class="preview-panel-heading">
                        <h2>商城效果预览</h2>
                        <p>表单修改会实时反映到预览。</p>
                        <div class="preview-device-tabs">
                            <button
                                type="button"
                                :class="{ active: !mobile }"
                                @click="mobile = false"
                            >
                                电脑端</button
                            ><button
                                type="button"
                                :class="{ active: mobile }"
                                @click="mobile = true"
                            >
                                手机端
                            </button>
                        </div>
                    </div>
                    <AdvancedStylePreview
                        :style="form"
                        :product="previewProduct"
                        :mobile="mobile"
                    />
                </div>
            </aside></form
    ></template>
</template>
