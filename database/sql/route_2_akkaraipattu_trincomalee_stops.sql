-- EastBus route_id = 2 example stop list.
-- IMPORTANT: distance_from_origin_km values below are placeholder/demo values.
-- Replace them with your operator-approved road distances before production use.

DELETE FROM route_stops WHERE route_id = 2;

INSERT INTO route_stops
(route_id, name, stop_order, latitude, longitude, distance_from_origin_km, booking_radius_km, boarding_allowed, dropoff_allowed, created_at, updated_at)
VALUES
(2, 'Akkaraipattu', 1, 7.2167, 81.8500, 0.00, 0, 1, 1, NOW(), NOW()),
(2, 'Addalaichenai', 2, 7.2540, 81.8560, 7.00, 0, 1, 1, NOW(), NOW()),
(2, 'Palamunai', 3, 7.2830, 81.8470, 12.00, 0, 1, 1, NOW(), NOW()),
(2, 'Oluvil', 4, 7.2825, 81.8614, 17.00, 0, 1, 1, NOW(), NOW()),
(2, 'Nintavur', 5, 7.3500, 81.8500, 27.00, 0, 1, 1, NOW(), NOW()),
(2, 'Karaitivu', 6, 7.3830, 81.8380, 34.00, 0, 1, 1, NOW(), NOW()),
(2, 'Kalmunai', 7, 7.4090, 81.8340, 40.00, 0, 1, 1, NOW(), NOW()),
(2, 'Kaluwanchikudy', 8, 7.5167, 81.7833, 56.00, 0, 1, 1, NOW(), NOW()),
(2, 'Kattankudy', 9, 7.6750, 81.7300, 78.00, 0, 1, 1, NOW(), NOW()),
(2, 'Batticaloa', 10, 7.7170, 81.7000, 84.00, 0, 1, 1, NOW(), NOW()),
(2, 'Eravur', 11, 7.7680, 81.6030, 97.00, 0, 1, 1, NOW(), NOW()),
(2, 'Chenkalady', 12, 7.7850, 81.5920, 101.00, 0, 1, 1, NOW(), NOW()),
(2, 'Valaichchenai', 13, 8.0000, 81.5333, 130.00, 0, 1, 1, NOW(), NOW()),
(2, 'Ottamavadi', 14, 7.9940, 81.5220, 133.00, 0, 1, 1, NOW(), NOW()),
(2, 'Thirikkonamadu', 15, 8.0550, 81.4200, 150.00, 0, 1, 1, NOW(), NOW()),
(2, 'Navalady', 16, 8.0900, 81.3900, 156.00, 0, 1, 1, NOW(), NOW()),
(2, 'Vakarai', 17, 8.1333, 81.4333, 164.00, 0, 1, 1, NOW(), NOW()),
(2, 'Kathiraveli', 18, 8.1880, 81.4140, 174.00, 0, 1, 1, NOW(), NOW()),
(2, 'Verugal', 19, 8.2830, 81.3500, 190.00, 0, 1, 1, NOW(), NOW()),
(2, 'Serunuwara', 20, 8.3580, 81.3200, 202.00, 0, 1, 1, NOW(), NOW()),
(2, 'Kilivetti', 21, 8.4050, 81.2900, 211.00, 0, 1, 1, NOW(), NOW()),
(2, 'Mutur', 22, 8.4500, 81.2670, 221.00, 0, 1, 1, NOW(), NOW()),
(2, 'Kinniya', 23, 8.4970, 81.1870, 235.00, 0, 1, 1, NOW(), NOW()),
(2, 'China Bay', 24, 8.5530, 81.1810, 246.00, 0, 1, 1, NOW(), NOW()),
(2, 'Trincomalee', 25, 8.5874, 81.2152, 252.00, 0, 1, 1, NOW(), NOW());
