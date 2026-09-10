<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liquid Lab</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #07111f;
            color: #e8f0ff;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at 12% 0, rgba(45, 212, 191, .2), transparent 34rem),
                radial-gradient(circle at 92% 12%, rgba(96, 165, 250, .18), transparent 32rem),
                #07111f;
        }

        main { width: min(1100px, calc(100% - 32px)); margin: 0 auto; padding: 72px 0 88px; }
        .eyebrow { color: #5eead4; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }
        h1 { max-width: 760px; margin: 14px 0 18px; font-size: clamp(3rem, 9vw, 7rem); line-height: .9; }
        .lead { max-width: 680px; margin: 0; color: #9eb0c7; font-size: 1.12rem; line-height: 1.75; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 52px; }
        .card { display: flex; min-height: 240px; padding: 26px; border: 1px solid #29425f; border-radius: 22px; background: rgba(10, 25, 44, .88); color: inherit; text-decoration: none; transition: border-color .2s ease, transform .2s ease, background .2s ease; }
        .card:hover { border-color: #5eead4; background: rgba(17, 43, 66, .95); transform: translateY(-3px); }
        .card-inner { display: flex; flex: 1; flex-direction: column; }
        .number { color: #5eead4; font-size: .82rem; font-weight: 900; letter-spacing: .12em; }
        .card h2 { margin: 20px 0 10px; font-size: 1.6rem; }
        .card p { margin: 0; color: #8fa2ba; line-height: 1.65; }
        .open { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 24px; color: #bfdbfe; font-size: .9rem; font-weight: 700; }
        .open::after { color: #5eead4; content: "→"; font-size: 1.25rem; }
        footer { margin-top: 34px; color: #62758d; font-size: .88rem; }

        @media (max-width: 700px) {
            main { padding-top: 48px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <div class="eyebrow">Laravel × Liquid</div>
        <h1>Liquid Lab</h1>
        <p class="lead">
            从安全的数据暴露，到控制流、自定义数据转换，再到可复用的完整主题结构。
            选择一个 Demo 查看实际代码的渲染结果。
        </p>
    </header>

    <section class="grid" aria-label="Liquid Demo 列表">
        <a class="card" href="{{ route('liquid.demo') }}">
            <div class="card-inner">
                <span class="number">01 · DROP</span>
                <h2>向模板暴露数据</h2>
                <p>使用 ProductDrop 控制 Liquid 可以读取的商品和款式字段。</p>
                <span class="open">打开 Drop Demo</span>
            </div>
        </a>

        <a class="card" href="{{ route('liquid.tags-demo') }}">
            <div class="card-inner">
                <span class="number">02 · TAGS</span>
                <h2>组织模板控制流</h2>
                <p>演示 if、case、unless、for、cycle，以及自定义块标签。</p>
                <span class="open">打开 Tags Demo</span>
            </div>
        </a>

        <a class="card" href="{{ route('liquid.filters-demo') }}">
            <div class="card-inner">
                <span class="number">03 · FILTERS</span>
                <h2>转换展示数据</h2>
                <p>实现金额、时间、图片 URL 和国际化翻译 Filter。</p>
                <span class="open">打开 Filters Demo</span>
            </div>
        </a>

        <a class="card" href="{{ route('liquid.theme-demo') }}">
            <div class="card-inner">
                <span class="number">04 · THEME</span>
                <h2>组织和复用主题</h2>
                <p>通过 Layout、Template、Partial 和 Snippet 构建多页面主题。</p>
                <span class="open">打开 Theme Demo</span>
            </div>
        </a>
    </section>

    <footer>
        主题详情页也可以直接访问：
        <a href="{{ route('liquid.theme-demo.product') }}">{{ route('liquid.theme-demo.product') }}</a>
    </footer>
</main>
</body>
</html>
