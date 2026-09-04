<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>EastBus Login</title>

    <link
        rel="stylesheet"
        href="/css/eastbus.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body.auth-page {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(14, 88, 220, 0.14),
                    transparent 34%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(15, 42, 91, 0.12),
                    transparent 32%
                ),
                linear-gradient(
                    135deg,
                    #f8fbff 0%,
                    #eef4ff 50%,
                    #f7f9fd 100%
                );
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
            color: #172033;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 460px;
        }

        .auth-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(202, 215, 238, 0.75);
            border-radius: 24px;
            padding: 34px 32px 30px;
            box-shadow:
                0 25px 70px rgba(26, 54, 107, 0.15),
                0 8px 25px rgba(26, 54, 107, 0.08);
            backdrop-filter: blur(14px);
        }

        .logo-area {
            text-align: center;
            margin-bottom: 26px;
        }

        .logo-area img {
            width: 150px;
            max-width: 70%;
            height: auto;
            object-fit: contain;
            display: inline-block;
        }

        .portal-title {
            margin: 14px 0 5px;
            color: #0a1e52;
            font-size: 24px;
            font-weight: 800;
        }

        .portal-subtitle {
            margin: 0;
            color: #75819a;
            font-size: 14px;
            line-height: 1.5;
        }

        .flash {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
        }

        .flash.success {
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .flash.error {
            background: #fff1f1;
            border: 1px solid #fecaca;
            color: #b42318;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        .form-control {
            width: 100%;
            min-height: 50px;
            padding: 12px 14px;
            border: 1px solid #d7dfec;
            border-radius: 12px;
            background: #ffffff;
            color: #172033;
            font-size: 15px;
            outline: none;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .form-control:focus {
            border-color: #175cd3;
            box-shadow: 0 0 0 4px rgba(23, 92, 211, 0.10);
            background: #ffffff;
        }

        .password-input {
            padding-right: 52px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #667085;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
        }

        .password-toggle:hover {
            background: #f2f6fc;
            color: #175cd3;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 2px;
            margin-bottom: 20px;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475467;
            font-size: 13px;
            cursor: pointer;
        }

        .remember-label input {
            width: 16px;
            height: 16px;
            accent-color: #175cd3;
        }

        .login-btn {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(
                135deg,
                #064bd8,
                #123b96
            );
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition:
                transform 0.15s ease,
                box-shadow 0.15s ease,
                opacity 0.15s ease;
            box-shadow: 0 10px 24px rgba(6, 75, 216, 0.23);
        }

        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(6, 75, 216, 0.28);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 25px 0 20px;
            color: #98a2b3;
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e4e9f1;
        }

        .register-box {
            text-align: center;
            color: #667085;
            font-size: 14px;
        }

        .register-box a {
            color: #064bd8;
            font-weight: 800;
            text-decoration: none;
        }

        .register-box a:hover {
            text-decoration: underline;
        }

        .footer-note {
            margin-top: 22px;
            text-align: center;
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.5;
        }

        @media (max-width: 520px) {
            body.auth-page {
                padding: 16px;
            }

            .auth-card {
                padding: 28px 22px 24px;
                border-radius: 20px;
            }

            .logo-area img {
                width: 135px;
            }

            .portal-title {
                font-size: 22px;
            }
        }
    </style>
</head>

<body class="auth-page">

    <div class="auth-wrapper">

        <div class="auth-card">

            <div class="logo-area">

                <img
                    src="{{ asset('images/east bus logo.png') }}"
                    alt="EastBus.lk Logo"
                >

                <h1 class="portal-title">
                    Welcome Back
                </h1>

                <p class="portal-subtitle">
                    Admin & Bus Operator Portal
                </p>

            </div>

            @if(session('success'))
                <div class="flash success">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="flash error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('login.post') }}"
            >
                @csrf

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <div class="input-wrap">

                        <input
                            id="email"
                            class="form-control"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                            autofocus
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrap">

                        <input
                            id="password"
                            class="form-control password-input"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            id="togglePassword"
                            class="password-toggle"
                            type="button"
                            aria-label="Show password"
                        >

                            <svg
                                id="eyeOpen"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>

                            <svg
                                id="eyeClosed"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                style="display: none;"
                            >
                                <path d="M3 3l18 18"/>
                                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                                <path d="M9.9 4.2A10.6 10.6 0 0 1 12 4c6.5 0 10 8 10 8a18.8 18.8 0 0 1-2.1 3.2"/>
                                <path d="M6.6 6.6C3.7 8.5 2 12 2 12s3.5 8 10 8a10.9 10.9 0 0 0 4.2-.8"/>
                            </svg>

                        </button>

                    </div>

                </div>

                <div class="remember-row">

                    <label class="remember-label">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        Remember me

                    </label>

                </div>

                <button
                    type="submit"
                    class="login-btn"
                >
                    Login to EastBus
                </button>

            </form>

            <div class="divider">
                NEW BUS OPERATOR
            </div>

            <div class="register-box">

                Want to register your bus company?

                <a href="{{ route('operator.register') }}">
                    Register Operator
                </a>

            </div>

            <div class="footer-note">
                Secure access to EastBus.lk administration and bus operator services.
            </div>

        </div>

    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const eyeOpen = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        togglePassword.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden
                ? 'text'
                : 'password';

            eyeOpen.style.display = isHidden
                ? 'none'
                : 'block';

            eyeClosed.style.display = isHidden
                ? 'block'
                : 'none';

            togglePassword.setAttribute(
                'aria-label',
                isHidden
                    ? 'Hide password'
                    : 'Show password'
            );
        });
    </script>

</body>

</html>