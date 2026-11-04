<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { api, errorText } from "../api";
import Icon from "../components/Icon.vue";
import CampaignPreview from "../components/CampaignPreview.vue";

const campaigns = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const config = ref(null);
const loading = ref(false);
const error = ref("");
const notice = ref("");
const search = ref("");
const status = ref("");
const changing = ref(null);
const editor = ref(null);
const previewDialog = ref(null);
const preview = ref(null);
const editingId = ref(null);
const saving = ref(false);
const generating = ref(false);
const formError = ref("");
const formNotice = ref("");
const goal = ref("向顾客介绍商品的特点，推荐适合日常使用的好物。");
const productSearch = ref("");
const productChoices = ref([]);
const productCache = reactive({});
const productLoading = ref(false);
const defaults = () => ({
    name: "",
    headline: "",
    body: "",
    cta: "查看商品",
    theme: "lavender",
    position: "all",
    product_ids: [],
    starts_at: "",
    ends_at: "",
});
const form = reactive(defaults());
const selectedProducts = computed(() =>
    form.product_ids.map((id) => productCache[id]).filter(Boolean),
);
const stateLabels = { draft: "草稿", published: "已发布", paused: "已暂停" };
const positions = { home: "商城首页", product: "商品详情", all: "首页与详情" };
let opener;
let productRequest = 0;
let listRequest = 0;

async function load(page = 1) {
    const version = ++listRequest;
    loading.value = true;
    error.value = "";
    try {
        const params = new URLSearchParams({
            page,
            search: search.value,
            status: status.value,
        });
        const result = await api(`/marketing/campaigns?${params}`);
        if (version === listRequest) {
            campaigns.value = result.data;
            meta.value = result.meta;
        }
    } catch (cause) {
        if (version === listRequest) error.value = errorText(cause);
    } finally {
        if (version === listRequest) loading.value = false;
    }
}
async function loadConfig() {
    try {
        config.value = (await api("/marketing/config")).data;
    } catch (cause) {
        error.value = errorText(cause);
    }
}
async function findProducts() {
    const version = ++productRequest;
    productLoading.value = true;
    try {
        const { data } = await api(
            `/products?per_page=20&search=${encodeURIComponent(productSearch.value)}`,
        );
        if (version !== productRequest) return;
        productChoices.value = data;
        data.forEach((product) => {
            productCache[product.id] = product;
        });
    } catch (cause) {
        formError.value = errorText(cause);
    } finally {
        if (version === productRequest) productLoading.value = false;
    }
}
function localDate(value) {
    if (!value) return "";
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
}
async function openEditor(campaign = null) {
    opener = document.activeElement;
    editingId.value = campaign?.id ?? null;
    formError.value = "";
    formNotice.value = "";
    productSearch.value = "";
    productChoices.value = [];
    (campaign?.products || []).forEach((product) => {
        productCache[product.id] = product;
    });
    Object.assign(
        form,
        campaign
            ? {
                  ...campaign,
                  product_ids: [...campaign.product_ids],
                  starts_at: localDate(campaign.starts_at),
                  ends_at: localDate(campaign.ends_at),
              }
            : defaults(),
    );
    editor.value.showModal();
    await nextTick();
    editor.value.querySelector("input")?.focus();
    await findProducts();
}
function closeEditor() {
    if (saving.value || generating.value) return;
    editor.value.close();
    opener?.focus();
}
async function generate() {
    formError.value = "";
    formNotice.value = "";
    if (!form.product_ids.length) {
        formError.value = "请先选择 1 至 6 件已上架商品。";
        return;
    }
    if (!goal.value.trim()) {
        formError.value = "请填写本次活动的运营目标。";
        return;
    }
    generating.value = true;
    try {
        const { data } = await api("/marketing/generate", {
            method: "POST",
            body: { product_ids: form.product_ids, goal: goal.value },
        });
        Object.assign(form, {
            name: data.name,
            headline: data.headline,
            body: data.body,
            cta: data.cta,
        });
        formNotice.value =
            "AI 文案已填入。请核对商品事实并编辑后保存，活动不会自动发布。";
    } catch (cause) {
        formError.value = errorText(cause);
    } finally {
        generating.value = false;
    }
}
async function save() {
    saving.value = true;
    formError.value = "";
    try {
        const body = {
            name: form.name,
            headline: form.headline,
            body: form.body,
            cta: form.cta,
            theme: form.theme,
            position: form.position,
            product_ids: form.product_ids,
            starts_at: form.starts_at
                ? new Date(form.starts_at).toISOString()
                : null,
            ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null,
        };
        await api(
            editingId.value
                ? `/marketing/campaigns/${editingId.value}`
                : "/marketing/campaigns",
            { method: editingId.value ? "PATCH" : "POST", body },
        );
        saving.value = false;
        closeEditor();
        notice.value = "活动已保存。确认预览后，可在活动卡片上发布。";
        await load(editingId.value ? meta.value.current_page : 1);
    } catch (cause) {
        formError.value = errorText(cause);
    } finally {
        saving.value = false;
    }
}
async function changeStatus(campaign) {
    changing.value = campaign.id;
    error.value = "";
    notice.value = "";
    try {
        const target = campaign.status === "published" ? "paused" : "published";
        await api(`/marketing/campaigns/${campaign.id}/status`, {
            method: "PATCH",
            body: { status: target },
        });
        notice.value =
            target === "published"
                ? "活动已发布，将在设定时间内展示于指定商城页面。"
                : "活动已暂停，商城将停止展示。";
        await load(meta.value.current_page);
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        changing.value = null;
    }
}
function showPreview(campaign) {
    preview.value = campaign;
    previewDialog.value.showModal();
}
const formatDate = (value) =>
    value ? new Date(value).toLocaleString("zh-CN") : "";
function schedule(campaign) {
    return `${campaign.starts_at ? formatDate(campaign.starts_at) : "立即开始"} → ${campaign.ends_at ? formatDate(campaign.ends_at) : "长期展示"}`;
}
onMounted(() => {
    load();
    loadConfig();
});
</script>

<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">GROWTH STUDIO</p>
            <h1>
                AI 营销 <span class="count-badge">{{ meta.total }} 个活动</span>
            </h1>
            <p class="muted">
                从商品推荐出发，生成文案、预览样式，再发布到商城。
            </p>
        </div>
        <button class="button primary" @click="openEditor()">
            <Icon name="plus" :size="18" />创建活动
        </button>
    </div>
    <div v-if="error" class="alert error" role="alert">
        {{ error
        }}<button
            @click="
                load(meta.current_page);
                loadConfig();
            "
        >
            重试
        </button>
    </div>
    <div v-if="notice" class="alert success" role="status">
        {{ notice
        }}<button aria-label="关闭提示" @click="notice = ''">
            <Icon name="close" :size="16" />
        </button>
    </div>
    <section class="marketing-status">
        <span class="quick-icon purple"><Icon name="spark" /></span>
        <div>
            <strong>{{
                config?.configured
                    ? "AI 文案生成已就绪"
                    : config
                      ? "AI 服务尚未配置"
                      : "正在检查 AI 服务…"
            }}</strong>
            <p>
                {{
                    config?.configured
                        ? `当前模型：${config.model}。生成结果可编辑，保存后由你决定何时发布。`
                        : "可以先手动创建活动；配置模型服务后，即可根据商品资料生成文案。"
                }}
            </p>
        </div>
        <a
            href="/liquid/theme-demo"
            target="_blank"
            rel="noopener"
            class="button secondary small"
            ><Icon name="external" :size="15" />查看商城</a
        >
    </section>
    <div class="marketing-workflow">
        <span><b>01</b>选择商品</span><Icon name="chevron" :size="14" /><span
            ><b>02</b>生成与编辑</span
        ><Icon name="chevron" :size="14" /><span><b>03</b>预览与发布</span>
    </div>
    <section class="panel campaign-list-panel">
        <form class="table-toolbar" @submit.prevent="load()">
            <div class="search-input">
                <Icon name="search" :size="18" /><input
                    v-model="search"
                    aria-label="搜索活动"
                    maxlength="100"
                    placeholder="搜索活动名称…"
                />
            </div>
            <select v-model="status" aria-label="筛选活动状态" @change="load()">
                <option value="">全部状态</option>
                <option value="draft">草稿</option>
                <option value="published">已发布</option>
                <option value="paused">已暂停</option></select
            ><button class="button secondary" :disabled="loading">查询</button
            ><button
                class="icon-button"
                type="button"
                aria-label="刷新活动"
                :disabled="loading"
                @click="load(meta.current_page)"
            >
                <Icon name="refresh" :size="18" />
            </button>
        </form>
        <div v-if="loading" class="loading-line" role="status">
            正在加载活动…
        </div>
        <div v-else-if="!campaigns.length && !error" class="empty-state">
            <span class="empty-icon"><Icon name="spark" :size="34" /></span>
            <h3>
                {{
                    search || status
                        ? "没有匹配的活动"
                        : "把好商品变成一次好推荐"
                }}
            </h3>
            <p>
                {{
                    search || status
                        ? "调整筛选条件再试试。"
                        : "选择商品，创建你的第一个推荐活动。"
                }}
            </p>
            <button class="button primary" @click="openEditor()">
                创建活动
            </button>
        </div>
        <div v-else class="campaign-cards">
            <article
                v-for="campaign in campaigns"
                :key="campaign.id"
                class="campaign-card"
            >
                <div class="campaign-card-top">
                    <div>
                        <h2>{{ campaign.name }}</h2>
                        <p>
                            {{ positions[campaign.position] }} ·
                            {{ campaign.product_ids.length }} 件商品
                        </p>
                    </div>
                    <span
                        class="status"
                        :class="
                            campaign.status === 'published'
                                ? 'available'
                                : 'unavailable'
                        "
                        >{{ stateLabels[campaign.status] }}</span
                    >
                </div>
                <div class="campaign-card-copy" :class="campaign.theme">
                    <span>精选推荐</span>
                    <h3>{{ campaign.headline }}</h3>
                    <p>{{ campaign.body }}</p>
                </div>
                <p class="campaign-schedule">{{ schedule(campaign) }}</p>
                <div class="campaign-card-actions">
                    <button class="text-link" @click="showPreview(campaign)">
                        预览</button
                    ><button
                        class="text-link"
                        :disabled="
                            campaign.status === 'published' || changing !== null
                        "
                        :title="
                            campaign.status === 'published'
                                ? '先暂停活动，再修改内容'
                                : '编辑活动'
                        "
                        @click="openEditor(campaign)"
                    >
                        编辑</button
                    ><button
                        class="button small"
                        :class="
                            campaign.status === 'published'
                                ? 'secondary'
                                : 'primary'
                        "
                        :disabled="changing !== null"
                        @click="changeStatus(campaign)"
                    >
                        {{
                            changing === campaign.id
                                ? "处理中…"
                                : campaign.status === "published"
                                  ? "暂停活动"
                                  : "发布活动"
                        }}
                    </button>
                </div>
            </article>
        </div>
        <div class="table-pagination">
            <span>共 {{ meta.total }} 个活动</span>
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
        当前支持商品推荐展示，不改变商品售价。已发布活动需先暂停才能编辑；商城每页展示最新的
        3 个有效活动。
    </p>
    <dialog
        ref="editor"
        class="editor-dialog campaign-editor"
        aria-labelledby="campaign-editor-title"
        @cancel.prevent="closeEditor()"
    >
        <form class="editor-form" @submit.prevent="save">
            <div class="editor-heading">
                <div>
                    <p class="eyebrow">CAMPAIGN STUDIO</p>
                    <h2 id="campaign-editor-title">
                        {{ editingId ? "编辑活动" : "创建活动" }}
                    </h2>
                </div>
                <button
                    class="icon-button"
                    type="button"
                    :disabled="saving || generating"
                    aria-label="关闭活动编辑"
                    @click="closeEditor"
                >
                    <Icon name="close" />
                </button>
            </div>
            <div class="editor-body campaign-editor-body">
                <div class="campaign-fields">
                    <div v-if="formError" class="alert error" role="alert">
                        {{ formError }}
                    </div>
                    <div v-if="formNotice" class="alert success" role="status">
                        {{ formNotice }}
                    </div>
                    <fieldset :disabled="saving || generating">
                        <legend>1. 选择推荐商品（最多 6 件）</legend>
                        <div class="product-picker-search">
                            <input
                                v-model="productSearch"
                                aria-label="搜索推荐商品"
                                placeholder="输入商品名称或标识"
                                maxlength="100"
                                @keydown.enter.prevent="findProducts"
                            /><button
                                class="button secondary small"
                                type="button"
                                :disabled="productLoading"
                                @click="findProducts"
                            >
                                搜索
                            </button>
                        </div>
                        <p v-if="productLoading" class="muted">正在读取商品…</p>
                        <div class="product-picker">
                            <label
                                v-for="product in productChoices"
                                :key="product.id"
                                class="product-option"
                                ><input
                                    v-model="form.product_ids"
                                    type="checkbox"
                                    :value="product.id"
                                    :disabled="
                                        !form.product_ids.includes(
                                            product.id,
                                        ) && form.product_ids.length >= 6
                                    "
                                /><span
                                    >{{ product.title
                                    }}<small>{{
                                        product.is_available
                                            ? "已上架"
                                            : "未上架，发布前需上架"
                                    }}</small></span
                                ></label
                            >
                        </div>
                        <p
                            v-if="!productLoading && !productChoices.length"
                            class="muted"
                        >
                            暂无匹配商品，请先在商品管理中添加商品。
                        </p>
                        <div class="selected-products">
                            <span
                                v-for="product in selectedProducts"
                                :key="product.id"
                                class="tag"
                                >{{ product.title
                                }}<button
                                    type="button"
                                    :aria-label="`移除 ${product.title}`"
                                    @click="
                                        form.product_ids =
                                            form.product_ids.filter(
                                                (id) => id !== product.id,
                                            )
                                    "
                                >
                                    ×
                                </button></span
                            >
                        </div>
                        <p class="muted">
                            已选 {{ form.product_ids.length }} / 6 件；发布和 AI
                            生成需使用已上架商品。
                        </p>
                    </fieldset>
                    <fieldset :disabled="saving || generating">
                        <legend>2. AI 生成或手动编辑文案</legend>
                        <label
                            >运营目标<textarea
                                v-model="goal"
                                maxlength="1000"
                                rows="2"
                                placeholder="希望向谁推荐？重点介绍什么？"
                            /></label
                        ><button
                            class="button secondary generate-button"
                            type="button"
                            :disabled="
                                !config?.configured || !form.product_ids.length
                            "
                            @click="generate"
                        >
                            <Icon name="spark" :size="17" />{{
                                generating ? "正在生成，请稍候…" : "AI 生成文案"
                            }}
                        </button>
                        <p v-if="!config?.configured" class="muted">
                            AI
                            尚未配置，请联系管理员配置模型服务。仍可手动填写以下内容。
                        </p>
                        <label
                            >活动名称<input
                                v-model="form.name"
                                required
                                maxlength="100"
                                placeholder="用于后台识别活动" /></label
                        ><label
                            >展示标题<input
                                v-model="form.headline"
                                required
                                maxlength="100"
                                placeholder="给商品一个吸引人的推荐标题" /></label
                        ><label
                            >推荐文案<textarea
                                v-model="form.body"
                                required
                                maxlength="1000"
                                rows="3"
                                placeholder="介绍商品特点，请勿承诺尚未实现的优惠。"
                            /></label
                        ><label
                            >按钮文字<input
                                v-model="form.cta"
                                required
                                maxlength="30"
                        /></label>
                    </fieldset>
                    <fieldset :disabled="saving || generating">
                        <legend>3. 样式与展示规则</legend>
                        <div class="form-grid">
                            <label
                                >配色样式<select v-model="form.theme">
                                    <option value="lavender">柔和紫</option>
                                    <option value="mint">薄荷绿</option>
                                    <option value="midnight">深夜蓝</option>
                                </select></label
                            ><label
                                >展示页面<select v-model="form.position">
                                    <option value="all">首页与商品详情</option>
                                    <option value="home">商城首页</option>
                                    <option value="product">商品详情页</option>
                                </select></label
                            >
                        </div>
                        <div class="form-grid">
                            <label
                                >开始时间<input
                                    v-model="form.starts_at"
                                    type="datetime-local"
                                /><small>留空则发布后立即开始</small></label
                            ><label
                                >结束时间<input
                                    v-model="form.ends_at"
                                    type="datetime-local"
                                    :min="form.starts_at || undefined"
                                /><small
                                    >留空则长期展示，时间按当前设备时区</small
                                ></label
                            >
                        </div>
                    </fieldset>
                </div>
                <aside class="campaign-live-preview">
                    <div>
                        <p class="eyebrow">LIVE PREVIEW</p>
                        <h3>实时预览</h3>
                        <p class="muted">当前内容保存后才会生效。</p>
                    </div>
                    <CampaignPreview
                        :campaign="form"
                        :products="selectedProducts"
                    />
                    <p class="muted">
                        商品下架后会自动从活动中隐藏。商品详情页不会推荐当前正在浏览的商品。
                    </p>
                </aside>
            </div>
            <div class="editor-footer">
                <button
                    class="button secondary"
                    type="button"
                    :disabled="saving || generating"
                    @click="closeEditor"
                >
                    取消</button
                ><button
                    class="button primary"
                    :disabled="saving || generating || !form.product_ids.length"
                >
                    {{ saving ? "保存中…" : "保存活动" }}
                </button>
            </div>
        </form>
    </dialog>
    <dialog
        ref="previewDialog"
        class="editor-dialog campaign-preview-dialog"
        aria-labelledby="preview-title"
    >
        <div class="editor-heading">
            <h2 id="preview-title">活动预览</h2>
            <button
                class="icon-button"
                type="button"
                aria-label="关闭活动预览"
                @click="previewDialog.close()"
            >
                <Icon name="close" />
            </button>
        </div>
        <div v-if="preview" class="editor-body">
            <CampaignPreview :campaign="preview" :products="preview.products" />
            <p class="muted">
                {{ positions[preview.position] }} · {{ schedule(preview) }}
            </p>
        </div>
    </dialog>
</template>
