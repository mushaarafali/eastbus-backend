# EastBus 20 km Route-Area Upgrade

Implemented backend rules:
- Passenger location autocomplete: `GET /api/passenger/locations?search=Kal`
- Operator route stops have coordinates and default 20 km booking radius.
- Passenger trip search matches selected towns to boarding/drop-off route stops within 20 km using Haversine distance.
- Boarding stop order must be before drop-off stop order.
- Only published, scheduled, future trips are returned/bookable.
- Same-day trips whose departure time has passed are rejected.
- Operator cannot publish a past/started trip.
- Staff Start Trip closes booking immediately and freezes a seat-status snapshot.
- Active/completed trip seat endpoint is view-only through `booking_available=false` and uses the frozen snapshot.
- A/C bus seat count: 49/51/53. Normal and Semi-Luxury: 54.

## After replacing project
Run:
```
php artisan optimize:clear
php artisan migrate
```

## Locations
A location requires name + latitude + longitude. Adding an operator route stop with coordinates automatically inserts that stop into the location search table. For truly *all Sri Lankan towns*, import a complete Sri Lanka gazetteer into `locations` (name, district, province, latitude, longitude). The code is ready for that dataset; this ZIP does not invent coordinates for towns that were not present in the supplied project.

## Passenger Flutter contract
Search query: `origin`, `destination`, `date`.
Location suggestions: `/passenger/locations?search=...`.
Booking payload accepts either `travellers` or `passengers` arrays; each row needs `seat_number`, `name`, `nic`.
