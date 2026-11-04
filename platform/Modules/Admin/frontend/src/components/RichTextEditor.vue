<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from "vue";
import $ from "../summernote";
import "summernote/dist/summernote-lite.js";
import "summernote/dist/lang/summernote-zh-CN.js";
import "summernote/dist/summernote-lite.css";
import { api, errorText } from "../api";

const props = defineProps({ modelValue: { type: String, default: "" }, disabled: Boolean });
const emit = defineEmits(["update:modelValue"]);
const editor = ref(null);
const error = ref("");
const uploading = ref(false);
let instance;
let disposed = false;
function sync() {
    if (!instance) return;
    const body = instance.next(".note-editor").find(".note-editable")[0];
    const empty = !body.textContent.trim() && !body.querySelector("img, table, hr");
    emit("update:modelValue", empty ? "" : instance.summernote("code"));
}
async function uploadImages(files) {
    if (props.disabled || uploading.value) return;
    uploading.value = true;
    error.value = "";
    instance.summernote("editor.saveRange");
    try {
        for (const file of files) {
            if (!["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type) || file.size > 2 * 1024 * 1024) {
                throw new Error("请选择不超过 2 MB 的 JPG、PNG、WebP 或 GIF 图片。");
            }
            const body = new FormData();
            body.append("image", file);
            const result = await api("/product-images", { method: "POST", body });
            if (disposed) return;
            instance.summernote("editor.restoreRange");
            instance.summernote("insertImage", result.data.url, file.name);
        }
    } catch (cause) {
        if (!disposed) error.value = errorText(cause);
    } finally {
        uploading.value = false;
    }
}
onMounted(() => {
    instance = $(editor.value);
    instance.summernote({
        lang: "zh-CN",
        tooltip: true,
        styleWithCSS: true,
        height: 320,
        minHeight: 220,
        placeholder: "填写商品详情…",
        dialogsInBody: true,
        disableDragAndDrop: true,
        styleTags: ["p", "blockquote", "h2", "h3", "h4"],
        toolbar: [
            ["style", ["style"]],
            ["font", ["bold", "italic", "underline", "clear"]],
            ["size", ["fontsize", "color"]],
            ["para", ["ul", "ol", "paragraph"]],
            ["table", ["table"]],
            ["insert", ["link", "picture", "hr"]],
            ["history", ["undo", "redo"]],
        ],
        callbacks: { onChange: sync, onImageUpload: uploadImages },
    });
    instance.summernote("code", props.modelValue || "");
    instance.next(".note-editor").find(".note-editable").attr({ role: "textbox", "aria-label": "商品详情", "aria-multiline": "true" });
    if (props.disabled) instance.summernote("disable");
});
watch(() => props.modelValue, (value) => {
    if (instance && instance.summernote("code") !== (value || "") && !(instance.summernote("isEmpty") && !value)) {
        instance.summernote("code", value || "");
    }
});
watch(() => props.disabled, (value) => {
    if (instance) instance.summernote(value ? "disable" : "enable");
});
onBeforeUnmount(() => {
    disposed = true;
    instance?.summernote("destroy");
    instance = null;
});
defineExpose({ validate: () => {
    if (!uploading.value) return true;
    error.value = "图片正在上传，请完成后再保存。";
    return false;
} });
</script>

<template>
    <div class="rich-text-field">
        <span class="rich-text-label">商品详情</span>
        <div ref="editor"></div>
        <p v-if="uploading" role="status" class="muted">图片上传中…</p>
        <p v-if="error" role="alert" class="field-error">{{ error }}</p>
    </div>
</template>
