<script setup>
import { reactive, ref } from "vue";
import { auth } from "../auth";
import { api, errorText } from "../api";
import Icon from "../components/Icon.vue";

const form = reactive({
    name: auth.user.name,
    email: auth.user.email,
    current_password: "",
    password: "",
    password_confirmation: "",
});
const busy = ref(false);
const error = ref("");
const notice = ref("");
async function save() {
    busy.value = true;
    error.value = "";
    notice.value = "";
    try {
        const { data } = await api("/profile", { method: "PATCH", body: form });
        auth.user = data;
        form.current_password = "";
        form.password = "";
        form.password_confirmation = "";
        notice.value = "账号信息已保存。";
    } catch (cause) {
        error.value = errorText(cause);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">ACCOUNT</p>
            <h1>账号设置</h1>
            <p class="muted">管理你的个人信息与登录密码。</p>
        </div>
    </div>
    <div class="settings-grid">
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>基本信息与安全</h2>
                    <p>保存更改前，需要验证当前密码。</p>
                </div>
                <Icon name="lock" />
            </div>
            <form class="settings-form" @submit.prevent="save">
                <div v-if="error" class="alert error" role="alert">
                    {{ error }}
                </div>
                <div v-if="notice" class="alert success" role="status">
                    {{ notice }}
                </div>
                <div class="form-grid">
                    <label
                        >姓名<input
                            v-model="form.name"
                            required
                            maxlength="255"
                            autocomplete="name" /></label
                    ><label
                        >邮箱<input
                            v-model="form.email"
                            required
                            type="email"
                            maxlength="255"
                            autocomplete="username"
                    /></label>
                </div>
                <hr />
                <label
                    >当前密码 <span class="required">*</span
                    ><input
                        v-model="form.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="请输入当前登录密码"
                /></label>
                <div class="form-grid">
                    <label
                        >新密码<input
                            v-model="form.password"
                            type="password"
                            minlength="8"
                            maxlength="255"
                            autocomplete="new-password"
                            placeholder="不修改请留空" /></label
                    ><label
                        >确认新密码<input
                            v-model="form.password_confirmation"
                            type="password"
                            :required="!!form.password"
                            minlength="8"
                            maxlength="255"
                            autocomplete="new-password"
                            placeholder="再次输入新密码"
                    /></label>
                </div>
                <button class="button primary" :disabled="busy">
                    {{ busy ? "保存中…" : "保存更改" }}
                </button>
            </form>
        </section>
        <aside class="panel account-card">
            <span class="large-avatar">{{
                auth.user.name.slice(0, 1).toUpperCase()
            }}</span>
            <h2>{{ auth.user.name }}</h2>
            <p class="muted">{{ auth.user.email }}</p>
            <span class="pill">后台管理员</span>
            <hr />
            <p>
                此账号用于管理商城商品与运营。请妥善保管登录信息，并定期更新密码。
            </p>
        </aside>
    </div>
</template>
