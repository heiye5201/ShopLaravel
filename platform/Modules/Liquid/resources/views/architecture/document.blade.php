<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Liquid Lab</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #07111f;
            color: #dce8f8;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { min-height: 100vh; margin: 0; background: radial-gradient(circle at 12% 0, rgba(45, 212, 191, .14), transparent 36rem), #07111f; }
        .topbar { position: sticky; z-index: 10; top: 0; border-bottom: 1px solid rgba(49, 80, 110, .72); background: rgba(7, 17, 31, .9); backdrop-filter: blur(18px); }
        .topbar__inner { display: flex; align-items: center; justify-content: space-between; width: min(980px, calc(100% - 32px)); min-height: 64px; margin: 0 auto; gap: 20px; }
        .home { color: #99f6e4; font-weight: 900; letter-spacing: .06em; text-decoration: none; }
        .kind { color: #7890aa; font-size: .84rem; }
        article { width: min(980px, calc(100% - 32px)); margin: 0 auto; padding: 62px 0 96px; }
        h1, h2, h3, h4 { color: #f2f7ff; line-height: 1.25; scroll-margin-top: 84px; }
        h1 { margin: 0 0 28px; font-size: clamp(2.5rem, 6vw, 4.8rem); letter-spacing: -.04em; }
        h2 { margin: 58px 0 20px; padding-bottom: 10px; border-bottom: 1px solid #29425f; font-size: 1.9rem; }
        h3 { margin: 38px 0 16px; color: #bff9ee; font-size: 1.35rem; }
        h4 { margin-top: 30px; }
        p, li { color: #adbed2; font-size: 1rem; line-height: 1.85; }
        strong { color: #edf7ff; }
        a { color: #67e8f9; text-decoration-color: rgba(103, 232, 249, .4); text-underline-offset: 3px; }
        blockquote { margin: 24px 0; padding: 14px 20px; border-left: 4px solid #5eead4; border-radius: 0 12px 12px 0; background: rgba(20, 56, 85, .55); }
        blockquote p { margin: 5px 0; color: #c8d9ea; }
        pre { overflow-x: auto; margin: 22px 0; padding: 20px; border: 1px solid #203852; border-radius: 14px; background: #040a12; color: #bfdbfe; line-height: 1.65; }
        code { padding: .15em .35em; border-radius: 5px; background: #10233a; color: #a5f3fc; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9em; }
        pre code { padding: 0; background: transparent; color: inherit; font-size: .88rem; }
        table { display: block; overflow-x: auto; width: 100%; margin: 24px 0; border-collapse: collapse; }
        th, td { min-width: 130px; padding: 12px 14px; border: 1px solid #29425f; color: #adbed2; text-align: left; vertical-align: top; }
        th { background: #10243b; color: #e6f2ff; }
        hr { margin: 48px 0; border: 0; border-top: 1px solid #29425f; }

        @media (max-width: 680px) {
            .topbar__inner { align-items: start; flex-direction: column; padding: 13px 0; }
            article { padding-top: 42px; }
            h2 { margin-top: 44px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="topbar__inner">
        <a class="home" href="{{ route('liquid.home') }}">← Liquid Lab 首页</a>
        <span class="kind">Architecture Document</span>
    </div>
</header>

<article>
    {!! $content !!}
</article>
</body>
</html>
