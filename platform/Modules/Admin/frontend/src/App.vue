<script setup>
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { auth } from "./auth";
import { api } from "./api";
import Icon from "./components/Icon.vue";
import { visibleNavigation as groups, navigation, loadNavigation, resetNavigation } from "./navigation";

const route = useRoute();
const router = useRouter();
const mobileOpen = ref(false);
const loggingOut = ref(false);
const sectionsOpen = ref({ "/products": true, "/orders": true, "/shop": true });
const logoutError = ref("");
watch(() => auth.user?.id, id => { resetNavigation(); if (id) loadNavigation(); }, { immediate: true });
function isChildSelected(path, parent) {
    if (path === parent.path) {
        return (route.path === path || route.path.startsWith(path + "/")) && !parent.children.some(child => child.path !== path && (route.path === child.path || route.path.startsWith(child.path + "/")));
    }
    return route.path === path || route.path.startsWith(path + "/");
}
watch(
    () => route.path,
    () => {
        mobileOpen.value = false;
        for (const path of Object.keys(sectionsOpen.value)) {
            if (route.path === path || route.path.startsWith(path + "/")) sectionsOpen.value[path] = true;
        }
        logoutError.value = "";
    },
);
async function logout() {
    loggingOut.value = true;
    logoutError.value = "";
    try {
        await api("/logout", { method: "POST" });
        auth.user = null;
        await router.replace("/login");
    } catch (error) {
        logoutError.value = error.message;
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <RouterView v-if="route.meta.public" />
    <div v-else-if="auth.user && route.matched.length" class="app-shell">
        <button
            v-if="mobileOpen"
            class="sidebar-overlay"
            aria-label="关闭导航"
            @click="mobileOpen = false"
        ></button>
        <aside class="sidebar" :class="{ open: mobileOpen }">
            <RouterLink to="/" class="brand"
                ><span class="brand-symbol"><Icon name="box" :size="23" /></span
                ><span
                    >Shop Studio<small>商城管理工作空间</small></span
                ></RouterLink
            >
            <nav aria-label="后台栏目">
                <div
                    v-for="group in groups"
                    :key="group.id"
                    class="nav-group"
                >
                    <p>{{ group.label }}</p>
                    <template v-for="item in group.items" :key="item.path">
                        <template v-if="item.children">
                            <button class="nav-item nav-parent" :class="{ 'section-active': route.path === item.path || route.path.startsWith(item.path + '/') }" :aria-expanded="sectionsOpen[item.path]" :aria-controls="`navigation-${item.path.slice(1)}`" @click="sectionsOpen[item.path] = !sectionsOpen[item.path]">
                                <Icon :name="item.icon" /><span>{{ item.title }}</span><Icon name="chevron" :size="14" class="nav-chevron" :class="{ expanded: sectionsOpen[item.path] }" />
                            </button>
                            <div v-show="sectionsOpen[item.path]" :id="`navigation-${item.path.slice(1)}`" class="nav-children">
                                <RouterLink v-for="child in item.children" :key="child.path" :to="child.path" class="nav-item nav-child" :class="{ selected: isChildSelected(child.path, item) }" :aria-current="(isChildSelected(child.path, item)) ? 'page' : undefined">
                                    <span>{{ child.title }}</span>
                                </RouterLink>
                            </div>
                        </template>
                        <RouterLink v-else :to="item.path" class="nav-item" :class="{ selected: route.path === item.path || route.path.startsWith(item.path + '/') }">
                            <Icon :name="item.icon" /><span>{{ item.title }}</span>
                        </RouterLink>
                    </template>
                </div>
            </nav>
            <div class="sidebar-bottom">
                <a
                    href="/liquid/theme-demo"
                    target="_blank"
                    rel="noopener"
                    class="visit-store"
                    ><Icon name="external" :size="17" />访问商城<Icon
                        name="arrow"
                        :size="16"
                /></a>
            </div>
        </aside>
        <div class="main-shell">
            <header class="topbar">
                <div class="breadcrumb">
                    <button
                        class="icon-button mobile-menu"
                        aria-label="打开导航"
                        @click="mobileOpen = true"
                    >
                        <Icon name="menu" /></button
                    ><span>管理后台</span
                    ><Icon name="chevron" :size="14" /><strong>{{
                        route.meta.title
                    }}</strong>
                </div>
                <div class="topbar-right">
                    <span class="workspace-label"
                        ><i class="online-dot"></i> 管理工作空间</span
                    ><RouterLink to="/settings" class="user-link"
                        ><span class="user-avatar">{{
                            auth.user.name.slice(0, 1).toUpperCase()
                        }}</span
                        ><span>{{ auth.user.name }}</span></RouterLink
                    ><button
                        class="icon-button"
                        :disabled="loggingOut"
                        aria-label="退出登录"
                        title="退出登录"
                        @click="logout"
                    >
                        <Icon name="logout" :size="19" />
                    </button>
                </div>
            </header>
            <main class="main-content">
                <p v-if="navigation.error" class="alert error" role="alert">{{ navigation.error }} <button class="text-link" @click="loadNavigation">重试</button></p>
                <div v-if="logoutError" class="alert error" role="alert">
                    {{ logoutError }} <button @click="logout">重试退出</button>
                </div>
                <RouterView :key="route.path" />
            </main>
            <footer class="app-footer">
                <span>Shop Studio</span><span>把时间留给更好的经营。</span>
            </footer>
        </div>
    </div>
</template>
