<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'EastBus.lk')</title>

    https://eastbus-backend-production.up.railway.app/css/eastbus.css

    @stack('head')
</head>

<body>
    <div class="app">

        <aside class="sidebar">
            <div class="brand">
                EastBus.lk

                <small>
                    {{ auth()->user()->role === 'admin'
                        ? 'ADMIN PANEL'
                        : 'OPERATOR PORTAL' }}
                </small>
            </div>

            <nav class="nav">

                @if(auth()->user()->role === 'admin')

                    <a href="{{ route('admin.dashboard') }}">
                        ▦ Dashboard
                    </a>

                    <a href="{{ route('admin.operators') }}">
                        🏢 Bus Operators
                    </a>

                    <a href="{{ route('admin.fixed-schedules.index') }}">
                        🚌 Fixed Bus Schedules
                    </a>
                    <a href="{{ route('admin.passengers') }}">
                        👥 Passengers
                    </a>

                    <a href="{{ route('admin.buses') }}">
                        🚌 Buses
                    </a>

                    <a href="{{ route('admin.trips') }}">
                        🗓 Trips & Schedule
                    </a>

                    <a href="{{ route('admin.bookings') }}">
                        🎟 Bookings
                    </a>

                    <a href="{{ route('admin.tracking') }}">
                        📍 Live Tracking
                    </a>

                    <a href="{{ route('admin.payments') }}">
                        💳 Payments
                    </a>

                    <a href="{{ route('admin.reports') }}">
                        📈 Reports
                    </a>

                    <a href="{{ route('admin.notifications') }}">
                        🔔 Notifications
                    </a>

                    <a href="{{ route('admin.alerts') }}">
                        🚨 Emergency Alerts
                    </a>

                    <a href="{{ route('admin.logs') }}">
                        🧾 System Logs
                    </a>

                    <a href="{{ route('admin.settings') }}">
                        ⚙ Settings
                    </a>

                @else

                    <a href="{{ route('operator.dashboard') }}">
                        ▦ Dashboard
                    </a>

                    <a href="{{ route('operator.buses') }}">
                        🚌 My Buses
                    </a>

                    <a href="{{ route('operator.staff') }}">
                        👨‍✈️ Drivers & Conductors
                    </a>

                    <a href="{{ route('operator.routes.index') }}">
                        📍 Routes & Stops
                    </a>

                    <a href="{{ route('operator.trips') }}">
                        🗓 Trips & Schedule
                    </a>

                    <a href="{{ route('operator.bookings') }}">
                        🎟 Bookings
                    </a>

                    <a href="{{ route('operator.tracking') }}">
                        🛰 Live Tracking
                    </a>

                    <a href="{{ route('operator.payments') }}">
                        💳 Payments
                    </a>

                    <a href="{{ route('operator.reports') }}">
                        📈 Reports
                    </a>

                    <a href="{{ route('operator.notifications') }}">
                        🔔 Notifications
                    </a>

                    <a href="{{ route('operator.alerts') }}">
                        🚨 Emergency Alerts
                    </a>

                    <a href="{{ route('operator.profile') }}">
                        🏢 Company Profile
                    </a>

                @endif

            </nav>
        </aside>

        <main class="main">

            <header class="topbar">
                <div>
                    <b>@yield('header', 'Dashboard')</b>
                </div>

                <div>
                    {{ auth()->user()->name }}

                    &nbsp;

                    <form
                        class="inline"
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            class="btn btn-sm"
                            type="submit"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </header>

            <section class="content">

                @if(session('success'))
                    <div class="flash success">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="flash error">
                        @foreach($errors->all() as $error)
                            <div>
                                {{ $error }}
                            </div>
                        @endforeach
                    </div>
                @endif

                @yield('content')

            </section>

        </main>

    </div>

    @stack('scripts')
</body>
</html>