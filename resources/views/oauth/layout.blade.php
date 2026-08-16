<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Кинокот')</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f4f4f5;
            --card: #ffffff;
            --text: #18181b;
            --muted: #71717a;
            --line: #e4e4e7;
            --accent: #7c3aed;
            --accent-text: #ffffff;
            --danger: #b91c1c;
            --danger-bg: #fef2f2;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #09090b;
                --card: #18181b;
                --text: #fafafa;
                --muted: #a1a1aa;
                --line: #27272a;
                --accent: #a78bfa;
                --accent-text: #1e1b4b;
                --danger: #fca5a5;
                --danger-bg: #2a1215;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--bg);
            color: var(--text);
            font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 28px;
        }

        h1 { margin: 0 0 4px; font-size: 20px; }

        p.lead { margin: 0 0 20px; color: var(--muted); font-size: 14px; }

        label { display: block; margin-bottom: 14px; font-size: 13px; color: var(--muted); }

        input[type=email], input[type=password] {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 10px 12px;
            font-size: 15px;
            color: var(--text);
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

        button {
            width: 100%;
            padding: 11px 14px;
            font: inherit;
            font-weight: 600;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
        }

        button.primary { background: var(--accent); color: var(--accent-text); }

        button.ghost { background: transparent; color: var(--text); border-color: var(--line); }

        .row { display: flex; gap: 10px; }

        .row > form { flex: 1; }

        .errors {
            margin: 0 0 16px;
            padding: 10px 12px;
            font-size: 14px;
            color: var(--danger);
            background: var(--danger-bg);
            border-radius: 8px;
        }

        .scopes {
            margin: 0 0 22px;
            padding: 14px 16px;
            list-style: none;
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            font-size: 14px;
        }

        .scopes li + li { margin-top: 8px; }

        .muted { color: var(--muted); font-size: 13px; }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 18px 0;
            color: var(--muted);
            font-size: 12px;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        a.button-link {
            display: block;
            padding: 11px 14px;
            text-align: center;
            font-weight: 600;
            text-decoration: none;
            color: var(--text);
            border: 1px solid var(--line);
            border-radius: 8px;
        }
    </style>
</head>
<body>
<main class="card">
    @yield('content')
</main>
</body>
</html>
