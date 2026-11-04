import { reactive } from "vue";
import { api } from "./api";

export const auth = reactive({ user: null, checked: false });
let pending;

export async function checkSession() {
    if (auth.checked) return;
    if (!pending) {
        pending = api("/me")
            .then(({ data }) => {
                auth.user = data;
                auth.checked = true;
            })
            .catch((error) => {
                if (error.status !== 401) throw error;
                auth.user = null;
                auth.checked = true;
            })
            .finally(() => {
                pending = null;
            });
    }
    return pending;
}
