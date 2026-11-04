const http = require("node:http");
http.createServer((req, res) => {
    const reply = (status, data) => {
        res.writeHead(status, { "Content-Type": "application/json" });
        res.end(JSON.stringify(data));
    };
    if (req.method !== "POST" || req.url !== "/v1/chat/completions")
        return reply(501, { error: { message: "Endpoint not modeled" } });
    let body = "";
    req.on("data", (chunk) => {
        body += chunk;
    });
    req.on("end", () => {
        let data;
        try {
            data = JSON.parse(body);
        } catch {
            return reply(400, { error: { message: "Invalid JSON" } });
        }
        if (
            data.thinking?.type !== "disabled" ||
            data.max_tokens !== 1500 ||
            !data.model ||
            !Array.isArray(data.messages) ||
            data.response_format?.type !== "json_object"
        )
            return reply(400, {
                error: { message: "Invalid completion request" },
            });
        const content = data.messages.at(-1)?.content || "";
        if (content.includes("simulate:balance")) return reply(402, { error: { message: "Insufficient Balance" } });
        if (content.includes("simulate:unavailable"))
            return reply(503, { error: { message: "Provider unavailable" } });
        if (content.includes("simulate:rate-limit"))
            return reply(429, { error: { message: "Rate limited" } });
        if (content.includes("simulate:timeout"))
            return setTimeout(
                () => reply(503, { error: { message: "Slow upstream" } }),
                2500,
            );
        const output = content.includes("simulate:invalid")
            ? "{invalid"
            : JSON.stringify({
                  name: "日常好物推荐",
                  headline: "发现适合你的生活好物",
                  body: "看看精选商品的特点，为日常增添新的选择。",
                  cta: "查看商品",
              });
        return reply(200, {
            id: "simulation-completion",
            object: "chat.completion",
            choices: [
                {
                    index: 0,
                    message: { role: "assistant", content: output },
                    finish_reason: "stop",
                },
            ],
        });
    });
}).listen(process.env.PORT);
