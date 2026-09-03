<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'EastBus.lk' }}</title>
</head>

<body
    style="
        margin: 0;
        background: #f3f6fb;
        font-family: Arial, sans-serif;
        color: #172033;
    "
>
    <div
        style="
            max-width: 620px;
            margin: 28px auto;
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(10, 30, 82, 0.10);
        "
    >
        <!-- Header -->
        <div
            style="
                background: #064BD8;
                padding: 28px;
                text-align: center;
            "
        >
            <div
                style="
                    font-size: 30px;
                    font-weight: 900;
                    color: #ffffff;
                "
            >
                EastBus.lk
            </div>

            <div
                style="
                    color: #dce8ff;
                    font-size: 13px;
                    margin-top: 5px;
                "
            >
                Eastern Bus Kingdom • Smart Travel
            </div>
        </div>

        <!-- Email Content -->
        <div style="padding: 30px;">
            @if(!empty($badge))
                <div
                    style="
                        display: inline-block;
                        background: #eaf1ff;
                        color: #064BD8;
                        border-radius: 20px;
                        padding: 7px 12px;
                        font-size: 11px;
                        font-weight: 800;
                        letter-spacing: 0.6px;
                    "
                >
                    {{ $badge }}
                </div>
            @endif

            <h2
                style="
                    color: #0A1E52;
                    margin: 16px 0 10px;
                "
            >
                {{ $title }}
            </h2>

            <p
                style="
                    line-height: 1.7;
                    color: #4b5563;
                "
            >
                {{ $message }}
            </p>

            @if(!empty($details))
                <div
                    style="
                        margin: 24px 0;
                        border: 1px solid #e4eaf4;
                        border-radius: 12px;
                        overflow: hidden;
                    "
                >
                    @foreach($details as $label => $value)
                        <div
                            style="
                                padding: 12px 15px;
                                border-bottom: 1px solid #eef2f7;
                            "
                        >
                            <span
                                style="
                                    color: #64748b;
                                    font-size: 12px;
                                "
                            >
                                {{ $label }}
                            </span>

                            <div
                                style="
                                    font-weight: 700;
                                    color: #172033;
                                    margin-top: 4px;
                                    word-break: break-word;
                                "
                            >
                                {{ $value }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <p
                style="
                    font-size: 12px;
                    color: #8792a5;
                    line-height: 1.6;
                "
            >
                This is an automated message from EastBus.lk.
                Please do not share OTPs, passwords or QR ticket codes
                with unknown persons.
            </p>
        </div>

        <!-- Footer -->
        <div
            style="
                background: #0A1E52;
                color: #cbd7ef;
                text-align: center;
                padding: 18px;
                font-size: 12px;
            "
        >
            © {{ date('Y') }} EastBus.lk • Eastern Bus Kingdom
        </div>
    </div>
</body>
</html>