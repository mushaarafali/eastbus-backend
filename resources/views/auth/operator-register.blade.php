<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="stylesheet"
        href="/css/eastbus.css"
    >

    <title>Operator Registration</title>

    <style>
        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            width: 100%;
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: 0;
            background: transparent;
            color: #667085;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            border-radius: 8px;
        }

        .password-toggle:hover {
            background: #f2f6fc;
            color: #0b4edb;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
        }
    </style>
</head>

<body class="auth-page">

    <div
        class="auth-card"
        style="width: min(650px, 94vw);"
    >

        <div class="auth-logo">
            EastBus.lk
        </div>

        <h2>
            Bus Operator Registration
        </h2>

        <p class="muted">
            Account remains inactive until approved by the EastBus administrator.
        </p>

        @if($errors->any())
            <div class="flash error">
                @foreach($errors->all() as $e)
                    <div>
                        {{ $e }}
                    </div>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('operator.register.post') }}"
        >
            @csrf

            <div class="form-grid">

                <div class="form-group">
                    <label>Bus Company Name</label>

                    <input
                        type="text"
                        name="company_name"
                        value="{{ old('company_name') }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Owner Full Name</label>

                    <input
                        type="text"
                        name="owner_name"
                        value="{{ old('owner_name') }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Email</label>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Phone</label>

                    <input
                        type="text"
                        name="phone"
                        value="{{ old('phone') }}"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label>Address</label>

                    <input
                        type="text"
                        name="address"
                        value="{{ old('address') }}"
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        Password
                    </label>

                    <div class="password-wrap">

                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword(
                                'password',
                                'passwordEyeOpen',
                                'passwordEyeClosed'
                            )"
                            aria-label="Show password"
                        >

                            <svg
                                id="passwordEyeOpen"
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
                                id="passwordEyeClosed"
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

                <div class="form-group">
                    <label for="password_confirmation">
                        Confirm Password
                    </label>

                    <div class="password-wrap">

                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword(
                                'password_confirmation',
                                'confirmEyeOpen',
                                'confirmEyeClosed'
                            )"
                            aria-label="Show confirm password"
                        >

                            <svg
                                id="confirmEyeOpen"
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
                                id="confirmEyeClosed"
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

            </div>

            <button
                type="submit"
                class="btn btn-primary"
                style="width: 100%;"
            >
                Submit Registration
            </button>

        </form>

        <p style="text-align: center;">
            <a href="{{ route('login') }}">
                Back to login
            </a>
        </p>

    </div>

    <script>
        function togglePassword(
            inputId,
            openEyeId,
            closedEyeId
        ) {
            const input = document.getElementById(inputId);
            const openEye = document.getElementById(openEyeId);
            const closedEye = document.getElementById(closedEyeId);

            const hidden = input.type === 'password';

            input.type = hidden
                ? 'text'
                : 'password';

            openEye.style.display = hidden
                ? 'none'
                : 'block';

            closedEye.style.display = hidden
                ? 'block'
                : 'none';
        }
    </script>

</body>

</html>