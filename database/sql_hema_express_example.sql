INSERT INTO fixed_services
(bus_name,bus_number,origin,destination,contact_number_1,contact_number_2,contact_number_3,is_published,is_active,created_at,updated_at)
VALUES
('HEMA EXPRESS','NB 2886','Pottuvil','Batticaloa Bus Stand','0757424579','0772854602','0754698568',1,1,NOW(),NOW());

SET @service_id = LAST_INSERT_ID();

INSERT INTO fixed_service_stops
(fixed_service_id,direction,stop_name,stop_order,arrival_time,departure_time,created_at,updated_at)
VALUES
(@service_id,'starting','Pottuvil',1,NULL,'06:00:00',NOW(),NOW()),
(@service_id,'starting','Akkaraipattu',2,'07:10:00','07:20:00',NOW(),NOW()),
(@service_id,'starting','Kalmunai',3,'08:05:00','08:05:00',NOW(),NOW()),
(@service_id,'starting','Kaluwanchikudy',4,'08:25:00','08:25:00',NOW(),NOW()),
(@service_id,'starting','Kattankudy',5,'08:50:00','08:50:00',NOW(),NOW()),
(@service_id,'starting','Batticaloa Hospital',6,'09:00:00','09:00:00',NOW(),NOW()),
(@service_id,'starting','Batticaloa Bus Stand',7,'09:05:00',NULL,NOW(),NOW()),
(@service_id,'return','Batticaloa Bus Stand',1,NULL,'12:15:00',NOW(),NOW()),
(@service_id,'return','Kattankudy',2,'12:30:00','12:30:00',NOW(),NOW()),
(@service_id,'return','Kaluwanchikudy',3,'13:15:00','13:15:00',NOW(),NOW()),
(@service_id,'return','Kalmunai',4,'13:35:00','13:50:00',NOW(),NOW()),
(@service_id,'return','Akkaraipattu',5,'14:40:00','15:00:00',NOW(),NOW()),
(@service_id,'return','Pottuvil',6,'16:15:00',NULL,NOW(),NOW());
