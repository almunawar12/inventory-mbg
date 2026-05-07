<x-guest-layout title="Login">
<style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'DM Sans', sans-serif;
        background: #0a1410;
    }

    .login-wrap {
        display: flex;
        min-height: 100vh;
    }

    /* ── LEFT PANEL ── */
    .panel-left {
        position: relative;
        width: 52%;
        background: #0a1410;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 3rem;
        overflow: hidden;
    }

    /* Dot grid */
    .panel-left::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(circle, rgba(245,158,11,0.18) 1px, transparent 1px);
        background-size: 28px 28px;
        z-index: 0;
    }

    /* Large blurred glow */
    .panel-left::after {
        content: '';
        position: absolute;
        width: 500px;
        height: 500px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, transparent 70%);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 0;
    }

    .panel-left > * { position: relative; z-index: 1; }

    .brand-top {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .brand-top svg {
        width: 36px;
        height: 36px;
        color: #f59e0b;
    }

    .brand-name {
        font-family: 'Syne', sans-serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: #f0ebe0;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .panel-center {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 3rem 0;
    }

    .hero-label {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.25em;
        text-transform: uppercase;
        color: #f59e0b;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .hero-label::before {
        content: '';
        display: inline-block;
        width: 32px;
        height: 1px;
        background: #f59e0b;
    }

    .hero-title {
        font-family: 'Syne', sans-serif;
        font-size: clamp(2.5rem, 4vw, 3.75rem);
        font-weight: 800;
        line-height: 1.08;
        color: #f0ebe0;
        margin: 0 0 1.5rem;
        letter-spacing: -0.02em;
    }

    .hero-title em {
        font-style: normal;
        color: #f59e0b;
    }

    .hero-desc {
        font-size: 0.925rem;
        color: rgba(240,235,224,0.5);
        line-height: 1.7;
        max-width: 380px;
        font-weight: 300;
    }

    .panel-stats {
        display: flex;
        gap: 2.5rem;
    }

    .stat-item {}

    .stat-num {
        font-family: 'Syne', sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: #f59e0b;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.72rem;
        color: rgba(240,235,224,0.4);
        margin-top: 0.25rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        font-weight: 400;
    }

    /* Decorative ring */
    .deco-ring {
        position: absolute;
        bottom: -120px;
        right: -120px;
        width: 420px;
        height: 420px;
        border-radius: 50%;
        border: 1px solid rgba(245,158,11,0.12);
        z-index: 0;
    }

    .deco-ring-2 {
        position: absolute;
        bottom: -80px;
        right: -80px;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        border: 1px solid rgba(245,158,11,0.08);
        z-index: 0;
    }

    /* ── RIGHT PANEL ── */
    .panel-right {
        width: 48%;
        background: #faf9f7;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        position: relative;
    }

    .panel-right::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 1px;
        height: 100%;
        background: linear-gradient(to bottom, transparent, rgba(245,158,11,0.3) 30%, rgba(245,158,11,0.3) 70%, transparent);
    }

    .form-box {
        width: 100%;
        max-width: 380px;
        animation: fadeUp 0.6s ease both;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .form-header {
        margin-bottom: 2.5rem;
    }

    .form-eyebrow {
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: #f59e0b;
        margin-bottom: 0.75rem;
    }

    .form-title {
        font-family: 'Syne', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: #0a1410;
        margin: 0 0 0.5rem;
        letter-spacing: -0.02em;
        line-height: 1.15;
    }

    .form-subtitle {
        font-size: 0.875rem;
        color: #888;
        font-weight: 300;
    }

    /* Form fields */
    .field-group {
        margin-bottom: 1.25rem;
    }

    .field-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #444;
        margin-bottom: 0.5rem;
    }

    .field-input {
        width: 100%;
        height: 48px;
        border: 1.5px solid #e5e1d8;
        border-radius: 8px;
        padding: 0 1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.925rem;
        color: #0a1410;
        background: #fff;
        transition: border-color 0.2s, box-shadow 0.2s;
        outline: none;
    }

    .field-input:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245,158,11,0.12);
    }

    .field-input::placeholder {
        color: #bbb;
    }

    .field-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .forgot-link {
        font-size: 0.75rem;
        color: #f59e0b;
        text-decoration: none;
        font-weight: 500;
        transition: opacity 0.2s;
    }

    .forgot-link:hover { opacity: 0.7; }

    .remember-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.75rem;
    }

    .remember-row input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: #f59e0b;
        border-radius: 3px;
    }

    .remember-row label {
        font-size: 0.825rem;
        color: #777;
        cursor: pointer;
    }

    .btn-login {
        width: 100%;
        height: 50px;
        background: #0a1410;
        color: #f0ebe0;
        border: none;
        border-radius: 8px;
        font-family: 'Syne', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        cursor: pointer;
        transition: background 0.2s, transform 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        position: relative;
        overflow: hidden;
    }

    .btn-login::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(245,158,11,0.15) 0%, transparent 60%);
        pointer-events: none;
    }

    .btn-login:hover {
        background: #162820;
        transform: translateY(-1px);
    }

    .btn-login:active { transform: translateY(0); }

    .btn-login:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .form-footer {
        margin-top: 1.5rem;
        text-align: center;
        font-size: 0.825rem;
        color: #999;
    }

    .form-footer a {
        color: #0a1410;
        font-weight: 500;
        text-decoration: none;
        border-bottom: 1px solid #0a1410;
        padding-bottom: 1px;
        transition: color 0.2s, border-color 0.2s;
    }

    .form-footer a:hover {
        color: #f59e0b;
        border-color: #f59e0b;
    }

    .error-msg {
        font-size: 0.78rem;
        color: #dc2626;
        margin-top: 0.4rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .session-status {
        background: rgba(245,158,11,0.1);
        border: 1px solid rgba(245,158,11,0.3);
        border-radius: 6px;
        padding: 0.75rem 1rem;
        font-size: 0.825rem;
        color: #92400e;
        margin-bottom: 1.5rem;
    }

    /* Mobile */
    @media (max-width: 768px) {
        .login-wrap { flex-direction: column; }
        .panel-left {
            width: 100%;
            min-height: 240px;
            padding: 2rem;
        }
        .panel-center { padding: 1.5rem 0; }
        .panel-right {
            width: 100%;
            padding: 2rem 1.5rem;
        }
        .panel-right::before { display: none; }
        .hero-title { font-size: 2rem; }
        .panel-stats { display: none; }
    }
</style>

<div class="login-wrap">

    {{-- ── LEFT BRAND PANEL ── --}}
    <div class="panel-left">
        <div class="deco-ring"></div>
        <div class="deco-ring-2"></div>

        {{-- Logo --}}
        <div class="brand-top">
            <x-application-logo />
            <span class="brand-name">MBG Inventory</span>
        </div>

        {{-- Hero --}}
        <div class="panel-center">
            <div class="hero-label">Business Intelligence</div>
            <h1 class="hero-title">
                Kelola inventaris<br>
                dengan <em>presisi</em><br>
                dan kontrol penuh.
            </h1>
            <p class="hero-desc">
                Platform manajemen stok, penjualan, dan pembelian yang dirancang untuk bisnis modern.
            </p>
        </div>

        {{-- Stats --}}
        <div class="panel-stats">
            <div class="stat-item">
                <div class="stat-num">Real-time</div>
                <div class="stat-label">Stock Monitor</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">POS</div>
                <div class="stat-label">Point of Sale</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">Report</div>
                <div class="stat-label">Analytics</div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT FORM PANEL ── --}}
    <div class="panel-right">
        <div class="form-box">

            {{-- Session status --}}
            @if (session('status'))
                <div class="session-status">{{ session('status') }}</div>
            @endif

            <div class="form-header">
                <div class="form-eyebrow">Selamat datang kembali</div>
                <h2 class="form-title">Masuk ke akun Anda</h2>
                <p class="form-subtitle">Masukkan kredensial Anda untuk melanjutkan</p>
            </div>

            <form method="POST" action="{{ route('login') }}" x-data="{ loading: false }" @submit="loading = true">
                @csrf

                {{-- Username --}}
                <div class="field-group">
                    <label class="field-label" for="username">Username</label>
                    <input
                        id="username"
                        class="field-input"
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="Masukkan username"
                    />
                    @error('username')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="field-group">
                    <div class="field-row">
                        <label class="field-label" style="margin-bottom:0" for="password">Password</label>
                        @if (Route::has('password.request'))
                            <a class="forgot-link" href="{{ route('password.request') }}">Lupa password?</a>
                        @endif
                    </div>
                    <input
                        id="password"
                        class="field-input"
                        style="margin-top:0.5rem"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    @error('password')
                        <div class="error-msg">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="remember-row">
                    <input id="remember_me" type="checkbox" name="remember">
                    <label for="remember_me">Ingat saya di perangkat ini</label>
                </div>

                {{-- Submit --}}
                <button class="btn-login" type="submit" :disabled="loading">
                    <svg x-show="loading" style="display:none;" class="animate-spin" width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-show="!loading">Masuk</span>
                    <span x-show="loading" style="display:none;">Memproses...</span>
                </button>

                <div class="form-footer">
                    Belum punya akun?
                    <a href="{{ route('register') }}">Daftar sekarang</a>
                </div>
            </form>
        </div>
    </div>

</div>
</x-guest-layout>
