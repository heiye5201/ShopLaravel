import { computed, reactive } from "vue";
import { api } from "./api";

export const defaultNavigation = [
    {
        "label": "概览",
        "items": [
            {
                "path": "/",
                "title": "工作台",
                "icon": "grid",
                "enabled": true
            }
        ],
        "id": "overview"
    },
    {
        "label": "商城运营",
        "items": [
            {
                "path": "/products",
                "title": "商品管理",
                "icon": "box",
                "children": [
                    {
                        "path": "/products",
                        "title": "商品列表",
                        "enabled": true
                    },
                    {
                        "path": "/products/inventory",
                        "title": "库存列表",
                        "enabled": true
                    },
                    {
                        "path": "/products/categories",
                        "title": "商品分类",
                        "enabled": true
                    }
                ],
                "enabled": true
            },
            {
                "path": "/orders",
                "title": "订单模块",
                "icon": "orders",
                "children": [
                    {
                        "path": "/orders",
                        "title": "所有订单",
                        "enabled": true
                    },
                    {
                        "path": "/orders/abandoned",
                        "title": "弃单管理",
                        "enabled": true
                    }
                ],
                "enabled": true
            },
            {
                "path": "/customers",
                "title": "顾客管理",
                "icon": "customers",
                "enabled": true
            },
            {
                "path": "/shop",
                "title": "店铺管理",
                "icon": "store",
                "children": [
                    {
                        "path": "/shop/pages",
                        "title": "页面管理",
                        "enabled": true
                    },
                    {
                        "path": "/shop/menus",
                        "title": "菜单栏",
                        "enabled": true
                    },
                    {
                        "path": "/shop/seo",
                        "title": "主页SEO",
                        "enabled": true
                    }
                ],
                "enabled": true
            },
            {
                "path": "/advanced-styles",
                "title": "高级样式",
                "icon": "grid",
                "enabled": true
            },
            {
                "path": "/marketing",
                "title": "AI 营销",
                "icon": "spark",
                "enabled": true
            }
        ],
        "id": "operations"
    },
    {
        "label": "管理",
        "items": [
            {
                "path": "/settings",
                "title": "账号设置",
                "icon": "settings",
                "enabled": true
            }
        ],
        "id": "management"
    }
];
export const navigation = reactive({ groups: structuredClone(defaultNavigation), error: "" });
export const visibleNavigation = computed(() => navigation.groups.map(group => ({ ...group,
    items: group.items.filter(item => item.enabled).map(item => item.children ? { ...item, children: item.children.filter(child => child.enabled) } : item)
        .filter(item => !item.children || item.children.length),
})).filter(group => group.items.length));
let generation = 0;
export async function loadNavigation() {
    const current = ++generation;
    try {
        const result = await api("/navigation");
        if (current === generation && Array.isArray(result.data) && result.data.length) { navigation.groups = result.data; navigation.error = ""; }
    } catch { if (current === generation) navigation.error = "后台导航加载失败，暂时使用现有导航。"; }
}
export function resetNavigation() { generation++; navigation.groups = structuredClone(defaultNavigation); navigation.error = ""; }
export function setNavigation(groups) { generation++; navigation.groups = groups; navigation.error = ""; }
