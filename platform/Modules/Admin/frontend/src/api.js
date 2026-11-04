let csrfToken = null;

export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export async function api(path, options = {}) {
    const method = options.method || "GET";
    if (method !== "GET" && !csrfToken) {
        const result = await api("/csrf");
        csrfToken = result.token;
    }
    const multipart = options.body instanceof FormData;
    let response;
    try {
        response = await fetch(`/api/v1/admin${path}`, {
            method,
            credentials: "include",
            cache: "no-store",
            headers: {
                Accept: "application/json",
                ...(options.body && !multipart ? { "Content-Type": "application/json" } : {}),
                ...(method !== "GET" ? { "X-CSRF-TOKEN": csrfToken } : {}),
            },
            ...(options.body ? { body: multipart ? options.body : JSON.stringify(options.body) } : {}),
        });
    } catch {
        throw new ApiError("无法连接服务器，请检查网络后重试。", 0);
    }
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 419) csrfToken = null;
        if (response.status === 401)
            window.dispatchEvent(new Event("admin:unauthorized"));
        const message =
            response.status === 419
                ? "页面已过期，请重新提交。"
                : response.status === 429
                  ? "操作太频繁，请稍后再试。"
                  : path.startsWith("/marketing/") &&
                      [502, 503].includes(response.status)
                    ? data.message || "AI 服务暂时不可用，请稍后重试。"
                    : response.status >= 500
                      ? "服务器暂时不可用，请稍后重试。"
                      : data.message || "操作失败，请重试。";
        throw new ApiError(message, response.status, data.errors);
    }
    // Login, logout, and profile updates can rotate the session and its CSRF token.
    if (["/login", "/logout", "/profile"].includes(path)) csrfToken = null;
    return data;
}

export function errorText(error) {
    return (
        Object.values(error.errors || {})
            .flat()
            .join(" ") || error.message
    );
}
