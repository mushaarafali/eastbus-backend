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
</head>

<body class="auth-page">

    <div class="auth-card">

        <div class="auth-logo">
            EastBus.lk
        </div>

        <p
            class="muted"
            style="text-align: center;"
        >
            Admin & Bus Operator Portal
        </p>

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
                    Email
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                >
            </div>

            <label>
                <input
                    type="checkbox"
                    name="remember"
                >
                Remember me
            </label>

            <button
                type="submit"
                class="btn btn-primary"
                style="width: 100%; margin-top: 16px;"
            >
                Login
            </button>
        </form>

        <hr
            style="
                border: 0;
                border-top: 1px solid #e5ebf4;
                margin: 22px 0;
            "
        >

        <p style="text-align: center;">
            Bus company?

            <a
                href="{{ route('operator.register') }}"
                style="
                    color: #0b4edb;
                    font-weight: 700;
                "
            >
                Register Operator
            </a>
        </p>

    </div>

</body>

</html>