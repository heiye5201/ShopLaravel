<script setup>
import { ref } from "vue";
import SidebarNavigation from "../components/SidebarNavigation.vue";
import StorefrontMenus from "./StorefrontMenus.vue";
const tab = ref("sidebar");
</script>

<template>
    <div class="menu-tabs" role="tablist" aria-label="菜单管理范围">
        <button id="sidebar-tab" class="button" :class="tab === 'sidebar' ? 'primary' : 'secondary'" role="tab" :aria-selected="tab === 'sidebar'" aria-controls="sidebar-panel" @click="tab = 'sidebar'">后台导航</button>
        <button id="storefront-tab" class="button" :class="tab === 'storefront' ? 'primary' : 'secondary'" role="tab" :aria-selected="tab === 'storefront'" aria-controls="storefront-panel" @click="tab = 'storefront'">商城导航</button>
    </div>
    <div v-show="tab === 'sidebar'" id="sidebar-panel" role="tabpanel" aria-labelledby="sidebar-tab"><SidebarNavigation /></div>
    <div v-if="tab === 'storefront'" id="storefront-panel" role="tabpanel" aria-labelledby="storefront-tab"><StorefrontMenus /></div>
</template>

<style scoped>
.menu-tabs { display: flex; gap: 10px; margin-bottom: 24px; }
</style>
