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
                    <label>Password</label>

                    <input
                        type="password"
                        name="password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>

                    <input
                        type="password"
                        name="password_confirmation"
                        required
                    >
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

</body>

</html>