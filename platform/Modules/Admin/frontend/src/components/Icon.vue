<script setup>
defineProps({ name: String, size: { type: Number, default: 20 } });
const paths = {
    store: "M3 10h18l-2-7H5l-2 7z M3 10v2a3 3 0 0 0 6 0 M9 10v2a3 3 0 0 0 6 0 M15 10v2a3 3 0 0 0 6 0 M5 15v6h14v-6 M10 21v-5h4v5",
    customers: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M17 4a4 4 0 0 1 0 7 M22 21v-2a4 4 0 0 0-3-3.87",
    orders: "M6 3h12v18l-3-2-3 2-3-2-3 2V3z M9 7h6 M9 11h6 M9 15h3",
    trash: "M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7",
    image: "M3 3h18v18H3z M3 16l6-6 12 11 M15 7h.01",
    grid: "M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z",
    box: "m12 3 9 5-9 5-9-5 9-5z M3 8v9l9 5 9-5V8 M12 13v9 M7 5.8l9 5",
    spark: "m12 3 2.8 6.2L21 12l-6.2 2.8L12 21l-2.8-6.2L3 12l6.2-2.8L12 3z M20 2v4 M18 4h4",
    settings:
        "M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1 1-3z",
    arrow: "M5 12h14 M13 6l6 6-6 6",
    chevron: "m9 6 6 6-6 6",
    plus: "M12 5v14 M5 12h14",
    search: "M21 21l-5-5 M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14",
    logout: "M9 3H4v18h5 M10 12h11 M16 7l5 5-5 5",
    external: "M14 3h7v7 M21 3l-9 9 M10 3H3v18h18v-7",
    menu: "M4 6h16 M4 12h16 M4 18h16",
    close: "m6 6 12 12 M6 18 18 6",
    refresh: "M20 8a9 9 0 1 0 1 7 M20 3v5h-5",
    check: "m5 12 4 4L19 6",
    lock: "M5 10h14v11H5z M8 10V6a4 4 0 0 1 8 0v4",
    mail: "M3 5h18v14H3z m0 0 9 7 9-7",
    edit: "m15 4 5 5 M4 20l5-1L21 7l-4-4L5 15l-1 5z",
    eye: "M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6",
};
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="paths[name] || paths.box" />
    </svg>
</template>
