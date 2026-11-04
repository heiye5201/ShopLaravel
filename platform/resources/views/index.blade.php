<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ShopLaravel · 项目首页</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5f7fb; color: #18243b; font-family: system-ui, sans-serif; }
        main { max-width: 1040px; margin: auto; padding: 80px 24px; }
        .brand { color: #5264d9; font-weight: 700; letter-spacing: .08em; }
        h1 { margin: 20px 0 16px; font-size: clamp(32px, 5vw, 52px); }
        p { color: #637087; line-height: 1.8; }
        nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 40px; }
        a { display: block; padding: 28px; border: 1px solid #dfe5ef; border-radius: 16px; background: white; color: inherit; text-decoration: none; }
        a:hover { border-color: #5264d9; }
        a:focus-visible { outline: 3px solid #5264d9; outline-offset: 4px; }
        h2 { margin: 0 0 12px; font-size: 22px; }
        a p { margin: 0 0 24px; }
        .enter { color: #5264d9; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <div class="brand">ShopLaravel</div>
        <h1>项目首页</h1>
        <p>从这里进入管理后台、浏览店铺演示，或探索 Liquid 模板示例。</p>
        <nav aria-label="项目入口">
            <a href="{{ url('/admin') }}">
                <h2>管理后台</h2>
                <p>管理商品、营销活动与店铺配置。</p>
                <span class="enter">进入后台 →</span>
            </a>
            <a href="{{ route('liquid.theme-demo') }}">
                <h2>店铺演示</h2>
                <p>浏览基于 Liquid 模板渲染的店铺页面。</p>
                <span class="enter">浏览店铺 →</span>
            </a>
            <a href="{{ route('liquid.home') }}">
                <h2>Liquid Lab</h2>
                <p>查看模板语法、标签、过滤器与架构文档。</p>
                <span class="enter">查看示例 →</span>
            </a>
        </nav>
    </main>
</body>
</html>
