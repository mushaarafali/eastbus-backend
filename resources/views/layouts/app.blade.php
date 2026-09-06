<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        @yield('title', 'EastBus.lk')
    </title>

    <link
        rel="stylesheet"
        href="/css/eastbus.css"
    >

    @stack('head')
</head>

<body>

    <div class="app">

        {{-- ============================================================
             SIDEBAR
        ============================================================ --}}

        <aside class="sidebar">

            <div class="brand">

                EastBus.lk

                <small>
                    {{
                        auth()->user()->role === 'admin'
                            ? 'ADMIN PANEL'
                            : 'OPERATOR PORTAL'
                    }}
                </small>

            </div>

            <nav class="nav">

                {{-- ====================================================
                     ADMIN MENU
                ==================================================== --}}

                @if(auth()->user()->role === 'admin')

                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="{{ request()->routeIs('admin.dashboard')
                            ? 'active'
                            : '' }}"
                    >
                        ▦ Dashboard
                    </a>

                    <a
                        href="{{ route('admin.operators') }}"
                        class="{{ request()->routeIs('admin.operators*')
                            ? 'active'
                            : '' }}"
                    >
                        🏢 Bus Operators
                    </a>

                    {{-- Master Route Management --}}

                    <a
                        href="{{ route('admin.routes.index') }}"
                        class="{{ request()->routeIs('admin.routes.*')
                            ? 'active'
                            : '' }}"
                    >
                        🛣 Master Routes
                    </a>

                    <a
                        href="{{ route('admin.fixed-schedules.index') }}"
                        class="{{ request()->routeIs('admin.fixed-schedules.*')
                            ? 'active'
                            : '' }}"
                    >
                        🚌 Fixed Bus Schedules
                    </a>

                    <a
                        href="{{ route('admin.passengers') }}"
                        class="{{ request()->routeIs('admin.passengers*')
                            ? 'active'
                            : '' }}"
                    >
                        👥 Passengers
                    </a>

                    <a
                        href="{{ route('admin.buses') }}"
                        class="{{ request()->routeIs('admin.buses*')
                            ? 'active'
                            : '' }}"
                    >
                        🚌 Buses
                    </a>

                    <a
                        href="{{ route('admin.trips') }}"
                        class="{{ request()->routeIs('admin.trips*')
                            ? 'active'
                            : '' }}"
                    >
                        🗓 Trips & Schedule
                    </a>

                    <a
                        href="{{ route('admin.bookings') }}"
                        class="{{ request()->routeIs('admin.bookings*')
                            ? 'active'
                            : '' }}"
                    >
                        🎟 Bookings
                    </a>

                    <a
                        href="{{ route('admin.tracking') }}"
                        class="{{ request()->routeIs('admin.tracking*')
                            ? 'active'
                            : '' }}"
                    >
                        📍 Live Tracking
                    </a>

                    <a
                        href="{{ route('admin.payments') }}"
                        class="{{ request()->routeIs('admin.payments*')
                            ? 'active'
                            : '' }}"
                    >
                        💳 Payments
                    </a>

                    <a
                        href="{{ route('admin.reports') }}"
                        class="{{ request()->routeIs('admin.reports*')
                            ? 'active'
                            : '' }}"
                    >
                        📈 Reports
                    </a>

                    <a
                        href="{{ route('admin.notifications') }}"
                        class="{{ request()->routeIs('admin.notifications*')
                            ? 'active'
                            : '' }}"
                    >
                        🔔 Notifications
                    </a>

                    <a
                        href="{{ route('admin.alerts') }}"
                        class="{{ request()->routeIs('admin.alerts*')
                            ? 'active'
                            : '' }}"
                    >
                        🚨 Emergency Alerts
                    </a>

                    <a
                        href="{{ route('admin.logs') }}"
                        class="{{ request()->routeIs('admin.logs*')
                            ? 'active'
                            : '' }}"
                    >
                        🧾 System Logs
                    </a>

                    <a
                        href="{{ route('admin.settings') }}"
                        class="{{ request()->routeIs('admin.settings*')
                            ? 'active'
                            : '' }}"
                    >
                        ⚙ Settings
                    </a>

                {{-- ====================================================
                     OPERATOR MENU
                ==================================================== --}}

                @else

                    <a
                        href="{{ route('operator.dashboard') }}"
                        class="{{ request()->routeIs('operator.dashboard')
                            ? 'active'
                            : '' }}"
                    >
                        ▦ Dashboard
                    </a>

                    <a
                        href="{{ route('operator.buses') }}"
                        class="{{ request()->routeIs('operator.buses*')
                            ? 'active'
                            : '' }}"
                    >
                        🚌 My Buses
                    </a>

                    <a
                        href="{{ route('operator.staff') }}"
                        class="{{ request()->routeIs('operator.staff*')
                            ? 'active'
                            : '' }}"
                    >
                        👨‍✈️ Drivers & Conductors
                    </a>

                    {{-- Operator selects Master Route and manages service --}}

                    <a
                        href="{{ route('operator.routes.index') }}"
                        class="{{ request()->routeIs('operator.routes.*')
                            ? 'active'
                            : '' }}"
                    >
                        🛣 Bus Route Services
                    </a>

                    <a
                        href="{{ route('operator.trips') }}"
                        class="{{ request()->routeIs('operator.trips*')
                            ? 'active'
                            : '' }}"
                    >
                        🗓 Trips & Schedule
                    </a>

                    <a
                        href="{{ route('operator.bookings') }}"
                        class="{{ request()->routeIs('operator.bookings*')
                            ? 'active'
                            : '' }}"
                    >
                        🎟 Bookings
                    </a>

                    <a
                        href="{{ route('operator.tracking') }}"
                        class="{{ request()->routeIs('operator.tracking*')
                            ? 'active'
                            : '' }}"
                    >
                        🛰 Live Tracking
                    </a>

                    <a
                        href="{{ route('operator.payments') }}"
                        class="{{ request()->routeIs('operator.payments*')
                            ? 'active'
                            : '' }}"
                    >
                        💳 Payments
                    </a>

                    <a
                        href="{{ route('operator.reports') }}"
                        class="{{ request()->routeIs('operator.reports*')
                            ? 'active'
                            : '' }}"
                    >
                        📈 Reports
                    </a>

                    <a
                        href="{{ route('operator.notifications') }}"
                        class="{{ request()->routeIs('operator.notifications*')
                            ? 'active'
                            : '' }}"
                    >
                        🔔 Notifications
                    </a>

                    <a
                        href="{{ route('operator.alerts') }}"
                        class="{{ request()->routeIs('operator.alerts*')
                            ? 'active'
                            : '' }}"
                    >
                        🚨 Emergency Alerts
                    </a>

                    <a
                        href="{{ route('operator.profile') }}"
                        class="{{ request()->routeIs('operator.profile*')
                            ? 'active'
                            : '' }}"
                    >
                        🏢 Company Profile
                    </a>

                @endif

            </nav>

        </aside>


        {{-- ============================================================
             MAIN CONTENT
        ============================================================ --}}

        <main class="main">

            {{-- ========================================================
                 TOP BAR
            ======================================================== --}}

            <header class="topbar">

                <div>

                    <b>
                        @yield('header', 'Dashboard')
                    </b>

                </div>


                <div
                    style="
                        display:flex;
                        align-items:center;
                        gap:12px;
                    "
                >

                    <span>
                        {{ auth()->user()->name }}
                    </span>

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


            {{-- ========================================================
                 CONTENT
            ======================================================== --}}

            <section class="content">

                {{-- Success Message --}}

                @if(session('success'))

                    <div class="flash success">
                        {{ session('success') }}
                    </div>

                @endif


                {{-- Error Message --}}

                @if(session('error'))

                    <div class="flash error">
                        {{ session('error') }}
                    </div>

                @endif


                {{-- Validation Errors --}}

                @if($errors->any())

                    <div class="flash error">

                        @foreach($errors->all() as $error)

                            <div>
                                {{ $error }}
                            </div>

                        @endforeach

                    </div>

                @endif


                {{-- Page Content --}}

                @yield('content')

            </section>

        </main>

    </div>


    {{-- ================================================================
         PAGE SCRIPTS
    ================================================================ --}}

    @stack('scripts')

</body>

</html>