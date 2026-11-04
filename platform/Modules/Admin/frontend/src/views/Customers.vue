<script setup>
import Icon from "../components/Icon.vue";
</script>

<template>
    <div class="page-heading"><div><p class="eyebrow">CUSTOMERS</p><h1>顾客管理</h1></div></div>
    <section class="panel empty-state">
        <span class="empty-icon"><Icon name="customers" :size="34" /></span>
        <h2>功能待接入</h2>
        <p>顾客管理功能尚未接入。</p>
        <RouterLink class="button secondary" to="/">返回工作台</RouterLink>
    </section>
</template>
