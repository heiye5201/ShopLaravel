<script setup>
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { api, errorText } from "../api";
import Icon from "../components/Icon.vue";
const route = useRoute();
const rows = ref([]),
    loading = ref(false),
    error = ref(""),
    search = ref(""),
    changing = ref(null);
const pagination = ref({ total: 0, current_page: 1, last_page: 1 });
const scopes = {
    all: "全部商品",
    products: "指定商品",
    variants: "指定商品规格",
    exclude: "排除部分商品",
};
async function load(page = 1) {
    loading.value = true;
    error.value = "";
    try {
        const result = await api(
            `/advanced-styles?page=${page}&search=${encodeURIComponent(search.value)}`,
        );
        rows.value = result.data;
        pagination.value = result;
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        loading.value = false;
    }
}
async function toggle(row) {
    changing.value = row.id;
    try {
        await api(`/advanced-styles/${row.id}/enabled`, {
            method: "PATCH",
            body: { enabled: !row.enabled },
        });
        await load(pagination.value.current_page);
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        changing.value = null;
    }
}
onMounted(() => load());
</script>
<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">OFFER DESIGN</p>
            <h1>
                高级样式 <span class="count-badge">{{ pagination.total }}</span>
            </h1>
            <p class="muted">设计商品详情的数量层级、选购组合与优惠徽章。</p>
        </div>
        <RouterLink to="/advanced-styles/new" class="button primary"
            ><Icon name="plus" :size="18" />创建高级样式</RouterLink
        >
    </div>
    <div v-if="route.query.saved" class="alert success" role="status">
        样式已保存，启用后会应用到关联商品的详情页。
    </div>
    <div v-if="error" class="alert error" role="alert">
        {{ error }}<button @click="load()">重试</button>
    </div>
    <div class="advanced-info-note">
        同一商品或规格匹配多条启用样式时，优先展示最近更新的样式。没有匹配的启用样式时不展示选购组合。
    </div>
    <section class="panel">
        <form class="table-toolbar" @submit.prevent="load()">
            <div class="search-input">
                <Icon name="search" :size="18" /><input
                    v-model="search"
                    aria-label="搜索高级样式"
                    placeholder="搜索活动名称…"
                    maxlength="100"
                />
            </div>
            <button class="button secondary" :disabled="loading">查询</button>
        </form>
        <div v-if="loading" class="loading-line">正在加载高级样式…</div>
        <div v-else-if="!rows.length" class="empty-state">
            <span class="empty-icon"><Icon name="grid" :size="32" /></span>
            <h3>创建第一套高级样式</h3>
            <p>从数量层级到配色与徽章，打造清晰的选购体验。</p>
            <RouterLink to="/advanced-styles/new" class="button primary"
                >创建高级样式</RouterLink
            >
        </div>
        <div v-else class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>活动名称</th>
                        <th>适用范围</th>
                        <th>层级</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id">
                        <td>
                            <strong>{{ row.name }}</strong>
                        </td>
                        <td>{{ scopes[row.scope] }}</td>
                        <td>{{ row.tiers.length }} 个层级</td>
                        <td>
                            <span
                                class="status"
                                :class="
                                    row.enabled ? 'available' : 'unavailable'
                                "
                                >{{ row.enabled ? "已启用" : "未启用" }}</span
                            >
                        </td>
                        <td>
                            <div class="row-actions">
                                <RouterLink
                                    :to="`/advanced-styles/${row.id}/edit`"
                                    class="text-link"
                                    >编辑</RouterLink
                                ><button
                                    class="text-link neutral"
                                    :disabled="changing !== null"
                                    @click="toggle(row)"
                                >
                                    {{
                                        changing === row.id
                                            ? "处理中…"
                                            : row.enabled
                                              ? "停用"
                                              : "启用"
                                    }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <span>共 {{ pagination.total }} 条</span>
            <div>
                <button
                    class="button secondary small"
                    :disabled="loading || pagination.current_page <= 1"
                    @click="load(pagination.current_page - 1)"
                >
                    上一页</button
                ><span
                    >{{ pagination.current_page }} /
                    {{ pagination.last_page }}</span
                ><button
                    class="button secondary small"
                    :disabled="
                        loading ||
                        pagination.current_page >= pagination.last_page
                    "
                    @click="load(pagination.current_page + 1)"
                >
                    下一页
                </button>
            </div>
        </div>
    </section>
</template>
