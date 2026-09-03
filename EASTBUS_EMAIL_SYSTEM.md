# EastBus Email Notification System

Implemented branded blue/white HTML email infrastructure in `App\Services\EastBusMailService`.

## Connected automatic triggers
- Passenger registration OTP / resend OTP
- Welcome email after successful email verification
- Password reset OTP
- Password changed security confirmation
- Security alert on the 3rd failed passenger login attempt within 30 minutes (includes time/IP/device)
- Passenger activated/disabled by Admin
- Operator activated/disabled by Admin
- New operator registration alert to active Admin users
- Driver/Conductor account-created email when a staff email is supplied
- Paid booking confirmation + E-ticket details + QR ticket token
- New paid booking alert to Operator
- Booking cancellation / refund-status message

## Reusable service methods ready for remaining event triggers
`EastBusMailService::send()` can be called for trip reminders, delays, trip cancellation, emergency notices, refunds, feedback requests, loyalty offers, revenue summaries and other system alerts.

## Mail configuration
Configure production SMTP values in Railway/hosting environment variables. Do not commit real Gmail app passwords.

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_google_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email@gmail.com
MAIL_FROM_NAME="EastBus.lk"

Then run:

php artisan optimize:clear

## QR note
The email includes the exact `ticket_token` / QR payload used by the Passenger App. The Passenger App can render this payload with `qr_flutter`. This avoids adding a new server-side QR package to the Laravel project.
