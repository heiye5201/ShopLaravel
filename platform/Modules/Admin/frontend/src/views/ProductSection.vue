<script setup>
import { useRoute } from "vue-router";
import Icon from "../components/Icon.vue";
const route = useRoute();
</script>

<template>
    <div class="page-heading">
        <div><p class="eyebrow">CATALOG</p><h1>{{ route.meta.title }}</h1></div>
    </div>
    <section class="panel empty-state">
        <span class="empty-icon"><Icon name="box" :size="34" /></span>
        <h2>功能待接入</h2>
        <p>{{ route.meta.description }}</p>
        <RouterLink class="button secondary" to="/products">返回商品列表</RouterLink>
    </section>
</template>
