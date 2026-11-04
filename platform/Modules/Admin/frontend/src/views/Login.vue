<script setup>
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../api";
import { auth } from "../auth";
import Icon from "../components/Icon.vue";

const router = useRouter();
const route = useRoute();
const form = reactive({ email: "", password: "" });
const busy = ref(false);
const showPassword = ref(false);
const error = ref(
    route.query.connection ? "暂时无法连接服务器，请检查服务是否已启动。" : "",
);
async function login() {
    busy.value = true;
    error.value = "";
    try {
        const { data } = await api("/login", { method: "POST", body: form });
        auth.user = data;
        auth.checked = true;
        const redirect =
            typeof route.query.redirect === "string"
                ? route.query.redirect
                : "/";
        await router.replace(
            redirect.startsWith("/") &&
                !redirect.startsWith("//") &&
                !redirect.startsWith("/login")
                ? redirect
                : "/",
        );
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="login-page">
        <section class="login-story">
            <a class="brand" href="/admin"
                ><span class="brand-symbol"><Icon name="box" :size="23" /></span
                ><span>Shop Studio<small>商城管理工作空间</small></span></a
            >
            <div class="login-story-main">
                <span class="eyebrow light">YOUR STORE, YOUR NEXT CHAPTER</span>
                <h1>每一份好生意，<br />从这里开始。</h1>
                <p>
                    商品、运营、营销灵感。<br />在一个清晰的工作空间里，让商城有序成长。
                </p>
                <div class="story-visual" aria-hidden="true">
                    <div class="visual-heading">
                        <span class="tiny-icon"
                            ><Icon name="grid" :size="18" /></span
                        >你的商城，一目了然<span class="visual-dots">···</span>
                    </div>
                    <div class="visual-bars">
                        <i
                            v-for="(height, index) in [
                                32, 48, 38, 65, 52, 78, 63, 90, 76, 100,
                            ]"
                            :key="index"
                            :style="{ height: height + '%' }"
                        ></i>
                    </div>
                    <div class="visual-bottom">
                        <span><i class="online-dot"></i> 专注每一次成长</span
                        ><Icon name="spark" />
                    </div>
                </div>
                <div class="story-tags">
                    <span><Icon name="check" :size="16" />商品集中管理</span
                    ><span><Icon name="check" :size="16" />运营轻松开始</span>
                </div>
            </div>
            <small class="login-story-footer"
                >SHOP STUDIO · BUILT FOR YOUR BUSINESS</small
            >
        </section>
        <section class="login-form-section">
            <div class="login-form-wrap">
                <span class="pill"
                    ><i class="online-dot"></i> 商城管理后台</span
                >
                <h2>欢迎回来</h2>
                <p class="muted">登录你的账号，开启今天的经营。</p>
                <div v-if="error" class="alert error" role="alert">
                    {{ error }}
                </div>
                <form @submit.prevent="login">
                    <label
                        >登录邮箱
                        <div class="input-icon">
                            <Icon name="mail" :size="19" /><input
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                placeholder="请输入管理员邮箱"
                                required
                                maxlength="255"
                            /></div></label
                    ><label
                        >登录密码
                        <div class="input-icon">
                            <Icon name="lock" :size="19" /><input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="请输入密码"
                                required
                            /><button
                                type="button"
                                class="icon-button"
                                :aria-label="
                                    showPassword ? '隐藏密码' : '显示密码'
                                "
                                :aria-pressed="showPassword"
                                @click="showPassword = !showPassword"
                            >
                                <Icon name="eye" :size="18" />
                            </button></div></label
                    ><button
                        class="button primary login-submit"
                        :disabled="busy"
                    >
                        {{ busy ? "正在登录…" : "登录工作空间"
                        }}<Icon name="arrow" :size="18" />
                    </button>
                </form>
                <div class="login-help">
                    <Icon name="lock" :size="15" /><span
                        >账号由管理员创建。如需开通或重置密码，请联系管理员。</span
                    >
                </div>
            </div>
            <span class="login-copyright"
                >© {{ new Date().getFullYear() }} Shop Studio</span
            >
        </section>
    </div>
</template>
