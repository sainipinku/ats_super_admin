<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Workspace' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @include('scramble::dev-tools', ['renderer' => 'scalar'])
    <style>
        :root {
            --postman-orange: #FF6C37;
            --postman-orange-hover: #e05b29;
            --postman-dark: #1e1e1e;
            --postman-sidebar: #181818;
            --postman-border: #2d2d2d;
            --postman-text: #e6e6e6;
            --postman-muted: #8c8c8c;
            --scalar-font: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --scalar-font-code: 'JetBrains Mono', monospace;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #121212;
            color: var(--postman-text);
            font-family: var(--scalar-font);
            -webkit-font-smoothing: antialiased;
        }

        /* Top Postman Header Bar */
        .postman-top-bar {
            background: linear-gradient(90deg, #181818 0%, #1f1f1f 100%);
            border-bottom: 1px solid var(--postman-border);
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.35);
        }

        .postman-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }

        .postman-logo-icon {
            width: 32px;
            height: 32px;
            background: var(--postman-orange);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            color: #fff;
            box-shadow: 0 2px 8px rgba(255, 108, 55, 0.4);
        }

        .postman-title-group h1 {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.01em;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .postman-badge {
            background: rgba(255, 108, 55, 0.15);
            color: var(--postman-orange);
            border: 1px solid rgba(255, 108, 55, 0.35);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .postman-subtitle {
            font-size: 11px;
            color: var(--postman-muted);
            margin: 2px 0 0 0;
        }

        .postman-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .postman-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }

        .postman-btn-orange {
            background: var(--postman-orange);
            color: #ffffff;
            border: 1px solid transparent;
        }
        .postman-btn-orange:hover {
            background: var(--postman-orange-hover);
            box-shadow: 0 2px 8px rgba(255, 108, 55, 0.35);
        }

        .postman-btn-dark {
            background: #262626;
            color: #cccccc;
            border: 1px solid #383838;
        }
        .postman-btn-dark:hover {
            background: #303030;
            color: #ffffff;
            border-color: #4a4a4a;
        }

        /* Postman Interactive Style Tweaks for Scalar */
        .scalar-app {
            --scalar-color-primary: #FF6C37 !important;
            --scalar-background-1: #121212 !important;
            --scalar-background-2: #181818 !important;
            --scalar-background-3: #222222 !important;
            --scalar-border-color: #2b2b2b !important;
            --scalar-sidebar-background: #151515 !important;
            --scalar-button-1: #FF6C37 !important;
            --scalar-button-1-hover: #e05b29 !important;
        }

        /* Method badge styling reminiscent of Postman */
        .get { color: #0cbb52 !important; }
        .post { color: #FF6C37 !important; }
        .put { color: #097bed !important; }
        .delete { color: #eb2013 !important; }
        .patch { color: #a54ef7 !important; }
    </style>
</head>
<body class="dark-mode">

<header class="postman-top-bar">
    <div class="postman-brand">
        <div class="postman-logo-icon">P</div>
        <div class="postman-title-group">
            <h1>CadMax API Workspace <span class="postman-badge">Postman Style</span></h1>
            <p class="postman-subtitle">Interactive REST Client • Test live requests with Auth Bearer token</p>
        </div>
    </div>
    <div class="postman-actions">
        <a href="{{ url('/docs/api.json') }}" target="_blank" download="cadmax_api.json" class="postman-btn postman-btn-dark" title="Download OpenAPI JSON to import into Postman app">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export to Postman App
        </a>
        <a href="javascript:void(0)" onclick="promptAuthToken()" class="postman-btn postman-btn-orange">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Set Bearer Token
        </a>
    </div>
</header>

<div id="app"></div>

<script src="{{ $config->renderer()->get('cdn', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference') }}"></script>
<script>
    const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
    const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
    const getCookieValue = (key) => {
        const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
        return cookie?.split("=")[1];
    };

    let activeBearerToken = localStorage.getItem('cadmax_api_bearer_token') || '';

    function promptAuthToken() {
        const token = prompt('Enter your Member or Admin Bearer Token for live API testing:', activeBearerToken);
        if (token !== null) {
            activeBearerToken = token.trim();
            localStorage.setItem('cadmax_api_bearer_token', activeBearerToken);
            if (activeBearerToken) {
                alert('Bearer token saved! Live test requests in the right Postman runner pane will automatically include your Authorization header.');
            } else {
                alert('Bearer token cleared.');
            }
        }
    }

    const extraConfig = {!! json_encode($config->renderer()->all(except: ['cdn', 'credentials', 'layout', 'darkMode', 'theme'])) !!};

    Scalar.createApiReference('#app', {
        content: {!! json_encode($spec) !!},
        layout: 'modern',
        theme: 'default',
        darkMode: true,
        showSidebar: true,
        searchHotKey: 'k',
        withDefaultFonts: false,
        defaultHttpClient: {
            targetKey: 'shell',
            clientKey: 'curl'
        },
        hiddenClients: [],
        ...extraConfig,
        onBeforeRequest: ({ requestBuilder }) => {
            const csrf = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrf) {
                requestBuilder.headers.set(CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrf));
            }
            if (activeBearerToken) {
                requestBuilder.headers.set('Authorization', 'Bearer ' + activeBearerToken);
            }
        },
        customFetch: (input, init) => {
            return window.fetch(input, {
                ...init,
                credentials: {!! json_encode($config->renderer()->get('credentials', 'include')) !!}
            });
        }
    });
</script>
</body>
</html>
