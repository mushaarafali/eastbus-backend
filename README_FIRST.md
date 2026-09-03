# EastBus Laravel Backend – Business Rules/API Upgrade

This ZIP is a **drop-in upgrade bundle for your existing `eastbus_admin_operator` project**.
It is not a blank Laravel project and does not include `vendor/`, `.env`, secrets, or your database.

## BACK UP FIRST

Before replacing anything:

1. Copy `C:\xampp\htdocs\eastbus_admin_operator` to a backup folder.
2. Export `eastbus_db` from phpMyAdmin.
3. Do not delete your current `.env`.

## Files to replace/add

Copy the contents of this ZIP into:

`C:\xampp\htdocs\eastbus_admin_operator`

Allow replacement for matching files.

Included:

- `app/Http/Controllers/Api/PassengerBookingController.php`
- `app/Http/Controllers/Api/PassengerProfileController.php`
- `app/Http/Controllers/Api/TicketValidationController.php`
- `app/Http/Controllers/OperatorController.php`
- `routes/api.php`
- `database/migrations/2026_09_01_000001_add_eastbus_business_rule_integrity.php`
- `PASSENGER_AUTH_PATCH_NOTE.md`

## Business rules added

- Past travel dates rejected by Laravel.
- From and To cannot be the same.
- Only published + scheduled + future departures are bookable.
- Booking after departure is rejected.
- Maximum 6 seats per booking.
- Disabled/booked seats are rejected.
- Each selected seat requires its own Passenger Name + NIC.
- Sri Lankan NIC validation.
- Passenger phone uses `+94XXXXXXXXX`.
- 10-minute temporary seat hold before payment.
- Booking starts as `pending`.
- Card payment only.
- Exactly 16-digit card number.
- CVV and expiry validation.
- Full card number and CVV are **never stored**.
- Safe payment metadata / transaction reference / last 4 digits can be stored.
- Successful payment changes booking to `confirmed`.
- Unique QR token generated after successful payment.
- QR is validated against the correct trip.
- QR cannot be reused after check-in.
- Live tracking requires a paid + confirmed booking and an active trip.
- Passenger profile API changes only name/phone; verified email is not editable.
- Staff Login ID generation is fixed so deleted/old IDs do not cause `EBK-DRV-0001` duplicates.
- Operator cannot create a scheduled trip in the past.

## Existing passenger OTP/authentication

Your current PassengerAuthController is intentionally NOT replaced because its OTP, SMTP,
forgot-password and passenger-token schema are already connected to your current database.

Read `PASSENGER_AUTH_PATCH_NOTE.md` and confirm:
- registration phone validates `+94XXXXXXXXX`
- unverified email cannot login

## Install

Open PowerShell:

```powershell
cd C:\xampp\htdocs\eastbus_admin_operator

php artisan optimize:clear
php artisan migrate
php artisan route:list --path=passenger
php artisan route:list --path=staff
```

Then run:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

## New Passenger App request format

### Search trips

`GET /api/passenger/trips/search`

Query:

```text
origin=Kalmunai
destination=Colombo
date=2026-09-02
```

### Create booking

`POST /api/passenger/bookings`

```json
{
  "trip_id": 1,
  "seat_numbers": ["S1", "S2"],
  "phone": "+94712345678",
  "travellers": [
    {
      "seat_number": "S1",
      "name": "Passenger One",
      "nic": "200012345678"
    },
    {
      "seat_number": "S2",
      "name": "Passenger Two",
      "nic": "200112345678"
    }
  ]
}
```

### Card payment

`POST /api/passenger/bookings/{bookingId}/payment`

```json
{
  "method": "card",
  "card_number": "4111111111111111",
  "card_holder_name": "TEST PASSENGER",
  "cvv": "123",
  "expiry_month": 12,
  "expiry_year": 2030
}
```

This is a coursework/demo validation flow. It is not a real PCI payment gateway.

### Ticket

`GET /api/passenger/bookings/{bookingId}/ticket`

QR content should use the returned `qr_token`.

### Tracking

`GET /api/passenger/bookings/{bookingId}/tracking`

Tracking becomes available only when the trip status is `active`.

## Trip Management App QR requests

### Verify

`POST /api/staff/tickets/verify`

```json
{
  "qr_token": "QR_TOKEN_FROM_TICKET",
  "trip_id": 1
}
```

### Check-in

`POST /api/staff/tickets/check-in`

Use the same request body.

## Important after backend replacement

The Passenger Flutter App currently needs to be updated to send:
- `travellers[]` with one Name + NIC per selected seat
- only `card` payment
- card fields shown above

The Trip Management App scanner also needs to send:
- `qr_token`
- `trip_id`

Do the backend first, run migrations successfully, then update both Flutter apps.
