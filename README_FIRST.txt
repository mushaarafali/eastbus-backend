EastBus Admin Fixed Schedule Module

Admin manages information-only bus timetables.

Install:
1. Copy migration, controllers and Blade files.
2. Add web routes INSIDE existing admin route group.
3. Add API routes to routes/api.php.
4. Add sidebar:
   <a href="{{ route('admin.fixed-schedules.index') }}">🚌 Fixed Bus Schedules</a>
5. Run:
   php artisan migrate
   php artisan optimize:clear
6. Verify:
   php artisan route:list --path=fixed-schedules

Passenger API:
GET /api/passenger/fixed-schedules/search?origin=Kalmunai&destination=Batticaloa%20Bus%20Stand
GET /api/passenger/fixed-schedules/{id}

Rules:
- Timetable only
- No booking/payment/QR/tracking
- Up to 3 contact numbers
- Starting + return schedule
- Arrival/departure times
- Publish/unpublish and active/inactive
- HEMA EXPRESS example SQL included
