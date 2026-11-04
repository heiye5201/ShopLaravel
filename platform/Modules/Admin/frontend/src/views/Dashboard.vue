<script setup>
import { onMounted, ref } from "vue";
import { auth } from "../auth";
import { api } from "../api";
import Icon from "../components/Icon.vue";

const stats = ref(null);
const loading = ref(true);
const error = ref("");
const date = new Intl.DateTimeFormat("zh-CN", {
    year: "numeric",
    month: "long",
    day: "numeric",
    weekday: "long",
}).format(new Date());
async function load() {
    loading.value = true;
    error.value = "";
    try {
        stats.value = (await api("/dashboard")).data;
    } catch (cause) {
        error.value = cause.message;
    } finally {
        loading.value = false;
    }
}
onMounted(load);
const money = (value) =>
    Number(value).toLocaleString("zh-CN", {
        style: "currency",
        currency: "CNY",
    });
</script>

<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">OVERVIEW</p>
            <h1>工作台</h1>
            <p class="muted">
                欢迎回来，{{ auth.user.name }}。看看商城的最新状态。
            </p>
        </div>
        <div class="heading-actions">
            <span class="date-label">{{ date }}</span
            ><button
                class="button secondary small"
                :disabled="loading"
                @click="load"
            >
                <Icon name="refresh" :size="16" />刷新
            </button>
        </div>
    </div>
    <div v-if="error" class="alert error" role="alert">
        {{ error }} <button @click="load">重试</button>
    </div>
    <section class="welcome-banner">
        <div>
            <span class="banner-tag"
                ><Icon name="spark" :size="15" />你的商城运营起点</span
            >
            <h2>好商品，值得被更多人看见。</h2>
            <p>管理商品，完善内容，为下一次营销做好准备。</p>
            <RouterLink to="/products" class="button white"
                >管理商品<Icon name="arrow" :size="17"
            /></RouterLink>
        </div>
        <div class="banner-art" aria-hidden="true">
            <span class="art-orbit orbit-one"></span
            ><span class="art-orbit orbit-two"></span>
            <div class="art-box"><Icon name="box" :size="70" /></div>
            <span class="art-spark"><Icon name="spark" :size="25" /></span
            ><span class="art-check"><Icon name="check" :size="20" /></span>
        </div>
    </section>
    <div class="stats-grid">
        <article
            v-for="item in [
                {
                    key: 'products',
                    label: '商品总数',
                    icon: 'box',
                    help: '商城中的全部商品',
                    color: 'purple',
                },
                {
                    key: 'available',
                    label: '已上架商品',
                    icon: 'check',
                    help: '当前可售的商品',
                    color: 'green',
                },
                {
                    key: 'unavailable',
                    label: '未上架商品',
                    icon: 'edit',
                    help: '待完善或暂停售卖',
                    color: 'orange',
                },
            ]"
            :key="item.key"
            class="stat-card"
        >
            <div class="stat-label">
                {{ item.label
                }}<span class="stat-icon" :class="item.color"
                    ><Icon :name="item.icon"
                /></span>
            </div>
            <strong>{{ loading ? "—" : (stats?.[item.key] ?? "—") }}</strong
            ><small>{{ item.help }}</small>
        </article>
    </div>
    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>最近添加的商品</h2>
                    <p>从商品内容开始，打磨每一个细节。</p>
                </div>
                <RouterLink to="/products" class="text-link"
                    >查看全部<Icon name="arrow" :size="16"
                /></RouterLink>
            </div>
            <div v-if="loading" class="empty-state compact">正在读取商品…</div>
            <div
                v-else-if="!stats?.recent_products.length"
                class="empty-state compact"
            >
                <Icon name="box" :size="36" />
                <h3>还没有商品</h3>
                <p>添加第一个商品，开始搭建你的商城。</p>
                <RouterLink to="/products/create" class="button primary small"
                    >添加商品</RouterLink
                >
            </div>
            <div v-else class="recent-list">
                <div
                    v-for="product in stats.recent_products"
                    :key="product.id"
                    class="recent-product"
                >
                    <span class="product-thumb"
                        ><Icon name="box" :size="21"
                    /></span>
                    <div>
                        <strong>{{ product.title }}</strong
                        ><small>{{ product.slug }}</small>
                    </div>
                    <span
                        class="status"
                        :class="
                            product.is_available ? 'available' : 'unavailable'
                        "
                        >{{ product.is_available ? "已上架" : "未上架" }}</span
                    ><strong class="recent-price">{{
                        money(product.price)
                    }}</strong>
                </div>
            </div>
        </section>
        <section class="panel quick-panel">
            <div class="panel-heading">
                <div>
                    <h2>快捷入口</h2>
                    <p>常用操作，触手可及。</p>
                </div>
            </div>
            <RouterLink to="/products/create" class="quick-item"
                ><span class="quick-icon purple"><Icon name="plus" /></span>
                <div>
                    <strong>添加商品</strong><small>让新商品加入你的商城</small>
                </div>
                <Icon name="chevron" :size="17" /></RouterLink
            ><RouterLink to="/marketing" class="quick-item"
                ><span class="quick-icon orange"><Icon name="spark" /></span>
                <div>
                    <strong>AI 营销活动</strong
                    ><small>创建商品推荐与营销文案</small>
                </div>
                <Icon name="chevron" :size="17" /></RouterLink
            ><a
                href="/liquid/theme-demo"
                target="_blank"
                rel="noopener"
                class="quick-item"
                ><span class="quick-icon green"><Icon name="external" /></span>
                <div>
                    <strong>预览商城</strong><small>查看顾客眼中的商城</small>
                </div>
                <Icon name="chevron" :size="17"
            /></a>
        </section>
    </div>
</template>
