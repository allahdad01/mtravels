-- PHP MySQL Backup
-- Generated: 2025-11-03 09:14:19
-- Host: localhost
-- Database: travelagency_saas
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_log_user_id_index` (`user_id`),
  KEY `activity_log_table_name_index` (`table_name`),
  KEY `activity_log_action_index` (`action`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_activity_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2081 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1925','1','1','add','main_account','13','[]','{"name":"SELF BANK (SAFE)","account_type":"internal","bank_account_number":null,"bank_name":null,"usd_balance":"0","afs_balance":"0","status":"active","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:13:51');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1926','1','1','add','main_account','14','[]','{"name":"AZIZI BANK","account_type":"bank","bank_account_number":null,"bank_name":"AZIZI","usd_balance":"0","afs_balance":"0","status":"active","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:15:17');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1927','1','1','add','main_account','15','[]','{"name":"AZIZI BANK","account_type":"bank","bank_account_number":null,"bank_name":"AZIZI","usd_balance":"0","afs_balance":"0","status":"active","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:15:32');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1928','1','1','add','main_account','16','[]','{"name":"AFGHAN UNITED BANK","account_type":"bank","bank_account_number":"23451534","bank_name":"AUB","usd_balance":"0","afs_balance":"0","status":"active","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:21:17');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1929','1','1','fund','main_account','13','{"account_id":"13","usd_balance":"0.000"}','{"account_id":"13","usd_balance":4008,"amount":4008,"currency":"USD","description":"Account funded by Sabaoon. Remarks: test. Receipt: 1"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:22:26');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1930','1','1','fund','main_account','13','{"account_id":"13","afs_balance":"0.000"}','{"account_id":"13","afs_balance":202360,"amount":202360,"currency":"AFS","description":"Account funded by Sabaoon. Remarks: test. Receipt: 2"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:22:51');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1931','1','1','fund','main_account','13','{"account_id":"13","euro_balance":"0.000"}','{"account_id":"13","euro_balance":680,"amount":680,"currency":"EUR","description":"Account funded by Sabaoon. Remarks: test. Receipt: 3"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:23:12');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1932','1','1','fund','main_account','13','{"account_id":"13","darham_balance":"1010.000"}','{"account_id":"13","darham_balance":1515,"amount":505,"currency":"DARHAM","description":"Account funded by Sabaoon. Remarks: test. Receipt: 4"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:26:39');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1933','1','1','delete','main_account_transactions','1195','{"main_account_id":13,"transaction_id":1195,"amount":"505.000","currency":"DARHAM","type":"credit","created_at":"2025-10-15 15:26:39"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:26:52');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1934','1','1','delete','main_account_transactions','1194','{"main_account_id":13,"transaction_id":1194,"amount":"505.000","currency":"DARHAM","type":"credit","created_at":"2025-10-15 15:23:40"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:26:59');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1935','1','1','add','suppliers','31','[]','{"name":"KamAir","contact_person":"Wali","phone":"0777305730","email":"admin@abc-construction.com","address":"wazir","currency":"USD","balance":421.49,"supplier_type":"External"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 15:41:34');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1936','1','1','add','suppliers','32','[]','{"name":"NSTTRIP","contact_person":"SABAOON","phone":"0777305730","email":"admin@abc-construction.com","address":"nsst","currency":"USD","balance":446.62,"supplier_type":"External"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 16:27:18');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1937','1','1','add','suppliers','33','[]','{"name":"Ariana","contact_person":"SABAOON","phone":"0777305730","email":"admin@abc-construction.com","address":"nsst","currency":"AFS","balance":47468,"supplier_type":"External"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 16:29:01');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1938','1','1','add','suppliers','34','[]','{"name":"Yasin","contact_person":"Wali","phone":"0777305730","email":"admin@abc-construction.com","address":"yasin","currency":"USD","balance":406,"supplier_type":"External"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 16:35:10');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1939','1','1','add','ticket_bookings','333','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"5DYFCD","origin":" KBL","destination":"HAS","airline":"FZ","departure_date":"2025-10-23","total_base":357.03,"total_sold":375,"total_discount":0,"total_profit":17.970000000000027,"currency":"USD","supplier_id":32,"supplier_name":"NSTTRIP","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 16:39:28');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1940','1','1','add','ticket_bookings','334','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"86S99L","origin":" KBL","destination":"ELQ","airline":"FZ","departure_date":"2025-10-23","total_base":320.03,"total_sold":330,"total_discount":0,"total_profit":9.970000000000027,"currency":"USD","supplier_id":32,"supplier_name":"NSTTRIP","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 16:42:13');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1941','1','1','add','main_account_transactions','1196','[]','{"booking_id":334,"payment_date":"2025-10-15 16:42:13","description":"PAID BY MR MATIULLAH 330*72.12=23800","amount":23800,"currency":"AFS","exchange_rate":72.12,"main_account_id":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-15 16:43:22');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1942','1','1','add','ticket_bookings','335','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"T6EXHA","origin":" KBL","destination":"ELQ","airline":"FZ","departure_date":"2025-10-18","total_base":320.03,"total_sold":330,"total_discount":0,"total_profit":9.970000000000027,"currency":"USD","supplier_id":32,"supplier_name":"NSTTRIP","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:22:03');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1943','1','1','delete','ticket_bookings','335','{"ticket_id":335,"client_id":19,"supplier_id":32,"paid_to_id":13,"ticket_currency":"USD"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:29:19');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1944','1','1','delete','ticket_bookings','334','{"ticket_id":334,"client_id":19,"supplier_id":32,"paid_to_id":13,"ticket_currency":"USD"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:29:22');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1945','1','1','delete','ticket_bookings','333','{"ticket_id":333,"client_id":19,"supplier_id":32,"paid_to_id":13,"ticket_currency":"USD"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:29:25');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1946','1','1','add','ticket_bookings','336','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"5DYFCD","origin":" KBL","destination":"HAS","airline":"FZ","departure_date":"2025-10-18","total_base":357.03,"total_sold":375,"total_discount":0,"total_profit":17.970000000000027,"currency":"USD","supplier_id":32,"supplier_name":"NSTTRIP","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:31:45');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1947','1','1','add','ticket_bookings','337','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"86S99L","origin":" KBL","destination":"ELQ","airline":"FZ","departure_date":"2025-10-25","total_base":320.03,"total_sold":330,"total_discount":0,"total_profit":9.970000000000027,"currency":"USD","supplier_id":32,"supplier_name":"NSTTRIP","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:33:05');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1948','1','1','add','ticket_bookings','338','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"T6EXHA","origin":" KBL","destination":"ELQ","airline":"FZ","departure_date":"2025-10-25","total_base":320.03,"total_sold":330,"total_discount":0,"total_profit":9.970000000000027,"currency":"USD","supplier_id":32,"supplier_name":"NSTTRIP","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:34:32');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1949','1','1','add','main_account_transactions','1197','[]','{"booking_id":337,"payment_date":"2025-10-16 11:34:32","description":"PAID BY MR MATIULLAH 330*72.12=23800","amount":23800,"currency":"AFS","exchange_rate":72.12,"main_account_id":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:36:02');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1950','1','1','add','expense_categories','21','[]','{"name":"OFFICE EXPS"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:39:45');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1951','1','1','delete','expense_categories','21','{"category_id":"21","name":"OFFICE EXPS"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:39:50');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1952','1','1','add','expense_categories','22','[]','{"name":"self expenses"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:40:13');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1953','1','1','add','expenses','214','[]','{"category_id":"22","date":"2025-10-16","description":"HOME EPENSES CAR OIL AND GYM FEESS","amount":"7300.00000","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"","receipt_file":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:42:29');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1954','1','1','add','expenses','215','[]','{"category_id":"19","date":"2025-10-16","description":"FOR KITCHEN CREATING AND PARTITION IN OFFICE","amount":"5050","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"","receipt_file":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:45:00');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1955','1','1','fund','suppliers','32','{"supplier_id":32,"supplier_balance":"163.590","main_account_id":13,"main_account_balance":4008}','{"supplier_id":32,"supplier_balance":1163.59,"main_account_id":13,"main_account_balance":3008,"amount":1000,"currency":"USD","remarks":"","receipt_number":"101"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:50:03');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1956','1','1','add','main_account_transactions','1201','[]','{"booking_id":338,"payment_date":"2025-10-16 11:54:49","description":"CASH paid by mr matiullah","amount":20550,"currency":"AFS","exchange_rate":72.8,"main_account_id":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','2025-10-16 11:55:23');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1957','1','1','add','clients','22','[]','{"name":"MINA Khan","email":"mina@yahoo.com","client_type":"regular","phone":"0777305730","usd_balance":"0.00","afs_balance":"0.00","address":"adfads"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 15:59:38');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1958','1','1','add','suppliers','35','[]','{"name":"ALAIN T","contact_person":"Wali","phone":"0777305730","email":"admin@abc-construction.com","address":"adfa","currency":"USD","balance":-5622,"supplier_type":"External"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:01:23');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1959','1','1','add','ticket_bookings','339','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"181QHU","origin":" KBL","destination":"JED","airline":"KM","departure_date":"2025-10-17","total_base":403,"total_sold":431,"total_discount":0,"total_profit":28,"currency":"USD","supplier_id":35,"supplier_name":"ALAIN T","client_id":22,"client_name":"MINA Khan","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:05:11');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1960','1','1','update','client_transactions','568','{"transaction_id":"568","transaction_type":"client","amount":431,"type":"credit","date":"2025-10-16 16:17:31"}','{"amount":431,"type":"credit","date":"2025-10-16T11:47","receipt":"217687","description":"Client: MINA Khan, Account funded by Sabaoon. Remarks: test"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:22:19');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1961','1','1','update','supplier_transactions','911','{"transaction_id":"911","transaction_type":"supplier","amount":1000,"type":"Credit","date":"2025-10-16 11:50:03"}','{"amount":1000,"type":"credit","date":"2025-10-16T07:20","receipt":"101","description":"Supplier: NSTTRIP, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: "}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:22:50');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1962','1','1','update','supplier_transactions','911','{"transaction_id":"911","transaction_type":"supplier","amount":1000,"type":"Credit","date":"2025-10-16 11:50:03"}','{"amount":1000,"type":"credit","date":"2025-10-16T07:20","receipt":"101","description":"Supplier: NSTTRIP, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: "}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:23:50');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1963','1','1','delete','supplier_transactions','911','{"supplier_id":32,"transaction_id":911,"amount":"1000.000","type":"credit","transaction_date":"2025-10-16 11:50:03","balance":"-2836.410"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:24:03');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1964','1','1','fund','suppliers','32','{"supplier_id":32,"supplier_balance":"163.590","main_account_id":13,"main_account_balance":4439}','{"supplier_id":32,"supplier_balance":1163.59,"main_account_id":13,"main_account_balance":3439,"amount":1000,"currency":"USD","remarks":"fafsd","receipt_number":"2452345"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:27:05');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1965','1','1','delete','supplier_transactions','913','{"supplier_id":32,"transaction_id":913,"amount":"1000.000","type":"credit","transaction_date":"2025-10-16 16:27:05","balance":"1163.590"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:27:30');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1966','1','1','delete','main_account_transactions','1202','{"main_account_id":13,"transaction_id":1202,"amount":"431.000","currency":"USD","type":"credit","created_at":"2025-10-16 16:17:31"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:35:26');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1967','1','1','fund','suppliers','32','{"supplier_id":32,"supplier_balance":"163.590","main_account_id":13,"main_account_balance":4008}','{"supplier_id":32,"supplier_balance":1163.59,"main_account_id":13,"main_account_balance":3008,"amount":1000,"currency":"USD","remarks":"asdf","receipt_number":"2452345"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:38:19');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1968','1','1','delete','supplier_transactions','914','{"supplier_id":32,"transaction_id":914,"amount":"1000.000","type":"credit","transaction_date":"2025-10-16 16:38:19","balance":"1163.590"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:38:47');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1969','1','1','bonus','suppliers','31','{"supplier_id":"31","supplier_balance":"421.490"}','{"supplier_id":"31","supplier_balance":521.49,"amount":"100","currency":"USD","remarks":"adfasd","receipt_number":"2452345"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:44:27');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1970','1','1','delete','supplier_transactions','915','{"supplier_id":31,"transaction_id":915,"amount":"100.000","type":"credit","transaction_date":"2025-10-16 16:44:27","balance":"521.490"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:44:48');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1971','1','1','fund','suppliers','31','{"supplier_id":31,"supplier_balance":"421.490","main_account_id":13,"main_account_balance":4008}','{"supplier_id":31,"supplier_balance":321.49,"main_account_id":13,"main_account_balance":3908,"amount":100,"currency":"USD","remarks":"tet","receipt_number":"2452345"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:47:17');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1972','1','1','delete','supplier_transactions','916','{"supplier_id":31,"transaction_id":916,"amount":"100.000","type":"debit","transaction_date":"2025-10-16 16:47:17","balance":"321.490"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 16:58:40');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1973','1','1','fund','suppliers','31','{"supplier_id":31,"supplier_balance":"421.490","main_account_id":13,"main_account_balance":4108}','{"supplier_id":31,"supplier_balance":321.49,"main_account_id":13,"main_account_balance":4008,"amount":100,"currency":"USD","remarks":"dsafasdf","receipt_number":"217687"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 17:04:22');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1974','1','1','delete','supplier_transactions','917','{"supplier_id":31,"transaction_id":917,"amount":"100.000","type":"debit","transaction_date":"2025-10-16 17:04:22","balance":"321.490"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-16 17:04:46');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1975','1','1','update','main_account_transactions','1190','{"transaction_id":"1190","transaction_type":"main","amount":4008,"type":"credit","date":"2025-10-15 15:22:26"}','{"amount":4008,"type":"credit","date":"2025-10-15T10:52","receipt":"1","description":"Account funded by Sabaoon. Remarks: test. Receipt: 1"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 08:50:58');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1976','1','1','update','0','336','{"ticket_id":336,"supplier":"32","sold_to":19,"price":"357.030","sold":"375.000","currency":"USD"}','{"supplier":31,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"MUHAMMAD RAUF","pnr":"5DYFCD","phone":"0788808299","origin":" KBL","destination":"HAS","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-18","return_date":"","price":357.03,"sold":375,"profit":17.97,"currency":"USD","description":"test","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:00:02');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1977','1','1','update','0','337','{"ticket_id":337,"supplier":"32","sold_to":19,"price":"320.030","sold":"330.000","currency":"USD"}','{"supplier":32,"sold_to":18,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"KHASTA GUL","pnr":"86S99L","phone":"0771781576","origin":" KBL","destination":"ELQ","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-25","return_date":"","price":320.03,"sold":330,"profit":9.97,"currency":"USD","description":"Sale for ticket: KHASTA GUL ( KBL to ELQ)","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:02:06');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1978','1','1','update','0','337','{"ticket_id":337,"supplier":"32","sold_to":18,"price":"320.030","sold":"330.000","currency":"USD"}','{"supplier":32,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"KHASTA GUL","pnr":"86S99L","phone":"0771781576","origin":" KBL","destination":"ELQ","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-25","return_date":"","price":320.03,"sold":330,"profit":9.97,"currency":"USD","description":"Sale for ticket: KHASTA GUL ( KBL to ELQ)","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:03:16');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1979','1','1','update','0','1197','{"transaction_id":"1197","ticket_id":"337","amount":23800,"description":"PAID BY MR MATIULLAH 330*72.12=23800","created_at":"2025-10-16 11:34:32","type":"credit","currency":"AFS","exchange_rate":null}','{"amount":23800,"description":"PAID BY MR MATIULLAH 330*72.12=23800","created_at":"2025-10-16 11:34:32","exchange_rate":72.11}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:15:38');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1980','1','1','add','ticket_reservations','18','{}','{"passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","origin":"MZR","destination":"FRA","airline":"EI","departure_date":"2025-10-23","base":100,"sold":120,"profit":20,"currency":"USD","supplier":32,"supplier_name":"NSTTRIP","sold_to":19,"client_name":"Walking Customers"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:53:08');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1981','1','1','update','0','18','{"ticket_id":18,"supplier":"32","sold_to":19,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":35,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","phone":"0777305730","origin":"MZR","destination":"FRA","return_origin":"","return_destination":"","airline":"EI","issue_date":"2025-10-18","departure_date":"2025-10-23","return_date":"","price":100,"sold":120,"profit":20,"currency":"USD","description":"Purchase for ticket: BAKHTIAR STANIKZAI (MZR to FRA)","paid_to":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:57:14');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1982','1','1','update','0','18','{"ticket_id":18,"supplier":"35","sold_to":19,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":35,"sold_to":18,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","phone":"0777305730","origin":"MZR","destination":"FRA","return_origin":"","return_destination":"","airline":"EI","issue_date":"2025-10-18","departure_date":"2025-10-23","return_date":"","price":100,"sold":120,"profit":20,"currency":"USD","description":"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)","paid_to":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 09:58:44');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1983','1','1','update','0','18','{"ticket_id":18,"supplier":"35","sold_to":18,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":35,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","phone":"0777305730","origin":"MZR","destination":"FRA","return_origin":"","return_destination":"","airline":"EI","issue_date":"2025-10-18","departure_date":"2025-10-23","return_date":"","price":100,"sold":120,"profit":20,"currency":"USD","description":"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)","paid_to":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:04:35');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1984','1','1','update','0','18','{"ticket_id":18,"supplier":"35","sold_to":19,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":35,"sold_to":18,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","phone":"0777305730","origin":"MZR","destination":"FRA","return_origin":"","return_destination":"","airline":"EI","issue_date":"2025-10-18","departure_date":"2025-10-23","return_date":"","price":100,"sold":120,"profit":20,"currency":"USD","description":"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)","paid_to":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:04:55');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1985','1','1','update','0','337','{"ticket_id":337,"supplier":"32","sold_to":19,"price":"320.030","sold":"330.000","currency":"USD"}','{"supplier":32,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"KHASTA GUL","pnr":"86S99L","phone":"0771781576","origin":" KBL","destination":"ELQ","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-25","return_date":"","price":330.03,"sold":340,"profit":9.97,"currency":"USD","description":"Sale for ticket: KHASTA GUL ( KBL to ELQ)","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:14:03');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1986','1','1','update','0','18','{"ticket_id":18,"supplier":"35","sold_to":18,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":35,"sold_to":18,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","phone":"0777305730","origin":"MZR","destination":"FRA","return_origin":"","return_destination":"","airline":"EI","issue_date":"2025-10-18","departure_date":"2025-10-23","return_date":"","price":110,"sold":120,"profit":10,"currency":"USD","description":"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)","paid_to":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:16:57');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1987','1','1','update','0','18','{"ticket_id":18,"supplier":"35","sold_to":18,"price":"110.000","sold":"120.000","currency":"USD"}','{"supplier":35,"sold_to":18,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"BAKHTIAR STANIKZAI","pnr":"188JZ0","phone":"0777305730","origin":"MZR","destination":"FRA","return_origin":"","return_destination":"","airline":"EI","issue_date":"2025-10-18","departure_date":"2025-10-23","return_date":"","price":110,"sold":130,"profit":20,"currency":"USD","description":"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)","paid_to":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:17:40');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1988','1','1','add','refunded_tickets','99','{"ticket_id":339,"passenger_name":"HAJI MUSA GUL MINAH KHAN","pnr":"181QHU","base":403,"sold":431,"supplier_penalty":20,"service_penalty":30,"currency":"USD","status":"Refunded","description":"test","calculation_method":"base"}','{}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:19:21');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1989','1','1','add','refunded_tickets','100','{"ticket_id":338,"passenger_name":"BAIKHT BAIDAR BAKHT JEHAN","pnr":"T6EXHA","base":320.03,"sold":330,"supplier_penalty":20,"service_penalty":30,"currency":"USD","status":"Refunded","description":"test","calculation_method":"base"}','{}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:20:52');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1990','1','1','add','date_change_tickets','49','{"ticket_id":"337","passenger_name":"KHASTA GUL","pnr":"86S99L","base":330.03,"sold":340,"supplier_penalty":20,"service_penalty":30,"currency":"USD","status":"Date Changed"}','{}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:21:55');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1991','1','1','add','refunded_tickets','101','{"ticket_id":336,"passenger_name":"MUHAMMAD RAUF","pnr":"5DYFCD","base":357.03,"sold":375,"supplier_penalty":20,"service_penalty":30,"currency":"USD","status":"pending","description":"test","calculation_method":"sold"}','{}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:23:58');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1992','1','1','add','date_change_tickets','50','{"ticket_id":"336","passenger_name":"MUHAMMAD RAUF","pnr":"5DYFCD","base":357.03,"sold":375,"supplier_penalty":10,"service_penalty":20,"currency":"USD","status":"Date Changed"}','{}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:24:26');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1993','1','1','create','ticket_weights','16',NULL,'{"ticket_id":337,"weight":20,"base_price":30,"sold_price":40,"profit":10,"remarks":"fasdf","supplier_name":"NSTTRIP","client_name":"Walking Customers","currency":"USD"}','::1','0','2025-10-18 10:24:51');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1994','1','1','create','ticket_weights','17',NULL,'{"ticket_id":336,"weight":20,"base_price":10,"sold_price":20,"profit":10,"remarks":"sdfas","supplier_name":"KamAir","client_name":"Walking Customers","currency":"USD"}','::1','0','2025-10-18 10:25:22');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1995','1','1','add','hotel_bookings','47','[]','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","supplier_id":32,"sold_to":"19","base_amount":10,"sold_amount":20,"profit":10,"currency":"USD","paid_to":"13"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:26:12');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1996','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"10.000","sold_amount":"20.000","currency":"USD","supplier_id":32,"sold_to":"19"}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":20,"profit":5,"currency":"USD","supplier_id":"32","sold_to":"19","paid_to":"13","remarks":"sadf"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:29:32');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1997','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"15.000","sold_amount":"20.000","currency":"USD","supplier_id":32,"sold_to":"19"}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":20,"profit":5,"currency":"USD","supplier_id":"35","sold_to":"19","paid_to":"13","remarks":"sadf"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:41:59');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1998','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"15.000","sold_amount":"20.000","currency":"USD","supplier_id":35,"sold_to":"19","receipt":""}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":20,"profit":5,"currency":"USD","supplier_id":"35","sold_to":"19","paid_to":"13","remarks":"sadf","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 10:58:03');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('1999','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"15.000","sold_amount":"20.000","currency":"USD","supplier_id":35,"sold_to":"19","receipt":""}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":20,"profit":5,"currency":"USD","supplier_id":"32","sold_to":"19","paid_to":"13","remarks":"sadf","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 11:02:11');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2000','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"15.000","sold_amount":"20.000","currency":"USD","supplier_id":32,"sold_to":"19","receipt":""}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":20,"profit":5,"currency":"USD","supplier_id":"32","sold_to":"18","paid_to":"13","remarks":"sadf","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 11:05:52');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2001','1','1','transfer','main_account_transactions',NULL,'{}','{"from_account_id":"13","from_account_name":"SELF BANK (SAFE)","from_currency":"USD","to_account_id":"13","to_account_name":"SELF BANK (SAFE)","to_currency":"AFS","amount":20,"converted_amount":1400,"exchange_rate":70,"description":"trdt"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:13:04');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2002','1','1','transfer','main_account_transactions',NULL,'{}','{"from_account_id":"13","from_account_name":"SELF BANK (SAFE)","from_currency":"USD","to_account_id":"14","to_account_name":"AZIZI BANK","to_currency":"USD","amount":100,"converted_amount":100,"exchange_rate":1,"description":"teatr"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:13:54');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2003','1','1','add','visa_applications','60','[]','{"supplier":32,"sold_to":"19","paid_to":"13","applicant_name":"BAKHTIAR STANIKZAI","passport_number":"P8798765","visa_type":"Tourist","base":100,"sold":120,"profit":20,"currency":"USD"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:49:18');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2004','1','1','update','visa_applications','60','{"id":60,"supplier":32,"sold_to":19,"base":"100.000","sold":"120.000","currency":"USD"}','{"id":60,"supplier":32,"sold_to":19,"title":"Mr","gender":"Male","applicant_name":"BAKHTIAR STANIKZAI","passport_number":"P8798765","country":"Pakistan","visa_type":"Tourist","receive_date":"2025-10-18","applied_date":"2025-10-25","issued_date":"","base":110,"sold":120,"profit":10,"currency":"USD","status":"Pending","remarks":"adf","phone":"0777555594"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:50:48');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2005','1','1','update','visa_applications','60','{"id":60,"supplier":32,"sold_to":19,"base":"110.000","sold":"120.000","currency":"USD"}','{"id":60,"supplier":35,"sold_to":19,"title":"Mr","gender":"Male","applicant_name":"BAKHTIAR STANIKZAI","passport_number":"P8798765","country":"Pakistan","visa_type":"Tourist","receive_date":"2025-10-18","applied_date":"2025-10-25","issued_date":"","base":110,"sold":120,"profit":10,"currency":"USD","status":"Pending","remarks":"adf","phone":"0777555594"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:52:00');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2006','1','1','update','visa_applications','60','{"id":60,"supplier":35,"sold_to":19,"base":"110.000","sold":"120.000","currency":"USD"}','{"id":60,"supplier":35,"sold_to":18,"title":"Mr","gender":"Male","applicant_name":"BAKHTIAR STANIKZAI","passport_number":"P8798765","country":"Pakistan","visa_type":"Tourist","receive_date":"2025-10-18","applied_date":"2025-10-25","issued_date":"","base":110,"sold":120,"profit":10,"currency":"USD","status":"Pending","remarks":"adf","phone":"0777555594"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:53:33');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2007','1','1','update','visa_applications','60','{"id":60,"supplier":35,"sold_to":18,"base":"110.000","sold":"120.000","currency":"USD"}','{"id":60,"supplier":35,"sold_to":18,"title":"Mr","gender":"Male","applicant_name":"BAKHTIAR STANIKZAI","passport_number":"P8798765","country":"Pakistan","visa_type":"Tourist","receive_date":"2025-10-18","applied_date":"2025-10-25","issued_date":"","base":110,"sold":130,"profit":20,"currency":"USD","status":"Pending","remarks":"adf","phone":"0777555594"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:55:17');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2008','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"15.000","sold_amount":"20.000","currency":"USD","supplier_id":32,"sold_to":"18","receipt":""}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":25,"profit":10,"currency":"USD","supplier_id":"32","sold_to":"18","paid_to":"13","remarks":"sadf","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 13:55:52');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2009','1','1','add','umrah_bookings','65','[]','{"family_id":"15","sold_to":"19","paid_to":"13","name":"Matiullah","passport_number":"P07592390","flight_date":"","return_date":"","room_type":"Shared","total_base_price":100,"total_sold_price":120,"total_profit":20,"services":[{"service_type":"all","supplier_id":"32","currency":"USD","base_price":"100","sold_price":"120","profit":20}],"remarks":"Base amount of 100 USD deducted for umrah all.","discount":"0"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:03:41');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2010','1','1','delete','umrah_bookings','65','{"booking_id":65,"client_id":19,"services":[{"service_id":25,"supplier_id":32,"service_type":"all","base_price":"100.000","sold_price":"120.000","profit":"20.000","currency":"USD","supplier_type":"External"}],"paid_to":13,"currency":"USD","client_type":"agency","total_base_price":"100.000","total_sold_price":"120.000","total_profit":"20.000"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:06:21');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2011','1','1','add','umrah_bookings','66','[]','{"family_id":"15","sold_to":"19","paid_to":"13","name":"Matiullah","passport_number":"P03241263","flight_date":"","return_date":"","room_type":"Shared","total_base_price":100,"total_sold_price":120,"total_profit":20,"services":[{"service_type":"all","supplier_id":"32","currency":"USD","base_price":"100","sold_price":"120","profit":20}],"remarks":"Base amount of 100 USD deducted for umrah all.","discount":"0"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:08:20');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2012','1','1','update_umrah_member','umrah_bookings','66','{"sold_to":19,"family_id":15,"paid_to":13,"entry_date":"2025-10-18","name":"Matiullah","dob":"2025-10-25","passport_number":"P03241263","id_type":"ID Original + Passport Original","flight_date":"0000-00-00","return_date":"0000-00-00","duration":"21 Days","room_type":"Shared","price":"100.000","sold_price":"120.000","profit":"20.000","received_bank_payment":"0.000","bank_receipt_number":"","paid":"0.000","due":"120.000","discount":"0.000"}','{"booking_id":66,"family_id":15,"suppliers":{"1":{"service_type":"all","supplier_id":"35","currency":"USD","base_price":"100.000","sold_price":"120.000","profit":"20.00"}},"sold_to":19,"paid_to":13,"entry_date":"2025-10-18","name":"Matiullah","dob":"2025-10-25","passport_number":"P03241263","id_type":"ID Original + Passport Original","flight_date":null,"return_date":null,"duration":"21 Days","room_type":"Shared","total_base_price":100,"total_sold_price":120,"total_profit":20,"received_bank_payment":null,"bank_receipt_number":null,"paid":null,"due":120,"gender":"Male","passport_expiry":"2026-04-25","remarks":"yrsd","relation":"Uncle","g_name":"ESMAT ULLAH","father_name":"FAIZ MOHAMMAD","discount":0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:18:40');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2013','1','1','update_umrah_member','umrah_bookings','66','{"sold_to":19,"family_id":15,"paid_to":13,"entry_date":"2025-10-18","name":"Matiullah","dob":"2025-10-25","passport_number":"P03241263","id_type":"ID Original + Passport Original","flight_date":null,"return_date":null,"duration":"21 Days","room_type":"Shared","price":"100.000","sold_price":"120.000","profit":"20.000","received_bank_payment":"0.000","bank_receipt_number":"","paid":"0.000","due":"120.000","discount":"0.000"}','{"booking_id":66,"family_id":15,"suppliers":{"1":{"service_type":"all","supplier_id":"35","currency":"USD","base_price":"100.000","sold_price":"120.000","profit":"20.00"}},"sold_to":18,"paid_to":13,"entry_date":"2025-10-18","name":"Matiullah","dob":"2025-10-25","passport_number":"P03241263","id_type":"ID Original + Passport Original","flight_date":null,"return_date":null,"duration":"21 Days","room_type":"Shared","total_base_price":100,"total_sold_price":120,"total_profit":20,"received_bank_payment":null,"bank_receipt_number":null,"paid":null,"due":120,"gender":"Male","passport_expiry":"2026-04-25","remarks":"yrsd","relation":"Uncle","g_name":"ESMAT ULLAH","father_name":"FAIZ MOHAMMAD","discount":0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:33:15');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2014','1','1','update_umrah_member','umrah_bookings','66','{"sold_to":18,"family_id":15,"paid_to":13,"entry_date":"2025-10-18","name":"Matiullah","dob":"2025-10-25","passport_number":"P03241263","id_type":"ID Original + Passport Original","flight_date":null,"return_date":null,"duration":"21 Days","room_type":"Shared","price":"100.000","sold_price":"120.000","profit":"20.000","received_bank_payment":"0.000","bank_receipt_number":"","paid":"0.000","due":"120.000","discount":"0.000"}','{"booking_id":66,"family_id":15,"suppliers":{"1":{"service_type":"all","supplier_id":"35","currency":"USD","base_price":"100.000","sold_price":"130.000","profit":"30.00"}},"sold_to":18,"paid_to":13,"entry_date":"2025-10-18","name":"Matiullah","dob":"2025-10-25","passport_number":"P03241263","id_type":"ID Original + Passport Original","flight_date":null,"return_date":null,"duration":"21 Days","room_type":"Shared","total_base_price":100,"total_sold_price":130,"total_profit":30,"received_bank_payment":null,"bank_receipt_number":null,"paid":null,"due":130,"gender":"Male","passport_expiry":"2026-04-25","remarks":"yrsd","relation":"Uncle","g_name":"ESMAT ULLAH","father_name":"FAIZ MOHAMMAD","discount":0}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:33:59');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2015','1','1','update','0','15','[]','{"family_id":"15","head_of_family":"HAMEED","contact":"0786011115","address":"Jada-e-Maiwand","package_type":"Full Package","location":"Madina and Makkah","tazmin":"Done","visa_status":"Applied"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:41:52');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2016','1','1','add','additional_payments','50',NULL,'{"id":50,"payment_type":"Vacine","description":"test","base_amount":10,"profit":5,"sold_amount":15,"currency":"USD","main_account_id":13,"supplier_id":null,"is_from_supplier":1,"client_id":null,"is_for_client":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:59:18');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2017','1','1','add','additional_payments','51',NULL,'{"id":51,"payment_type":"Vacine","description":"test","base_amount":10,"profit":5,"sold_amount":15,"currency":"USD","main_account_id":13,"supplier_id":32,"is_from_supplier":1,"client_id":19,"is_for_client":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 14:59:46');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2018','1','1','add','main_account_transactions','1211','[]','{"main_account_id":13,"amount":10,"currency":"USD","description":"test","payment_id":50,"balance":3998,"exchange_rate":0,"payment_datetime":"2025-10-18 15:24:32","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 15:24:44');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2019','1','1','update','main_account_transactions','1211','{"transaction_id":1211,"payment_id":50,"amount":"10.000","description":"test","created_at":"2025-10-18 15:24:32","receipt":""}','{"amount":10,"description":"test","created_at":"2025-10-18 15:24:32","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 15:31:58');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2020','1','1','update','main_account_transactions','1211','{"transaction_id":1211,"payment_id":50,"amount":"10.000","description":"test","created_at":"2025-10-18 15:24:32","receipt":""}','{"amount":12,"description":"test","created_at":"2025-10-18 15:24:32","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 15:32:07');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2021','1','1','update','0','35','{"transaction_id":"35","debtor_id":"34","amount":200,"description":"test","created_at":"2025-10-18 16:25:36","transaction_type":"credit","currency":"USD"}','{"amount":300,"description":"test","created_at":"2025-10-18 16:25:00"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-18 16:26:25');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2022','1','1','0','creditor_transactions','16','{"transaction_id":16,"creditor_id":12,"amount":100,"description":"tet","created_at":"2025-10-18 16:37:48","reference_number":"1212121","currency":"USD"}','{"amount":200,"description":"tet","created_at":"2025-10-18 16:37:48","reference_number":"1212121"}','::1','0','2025-10-18 16:40:44');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2023','1','1','update','0','1218','{"transaction_id":1218,"customer_id":5,"amount":100,"description":"","created_at":"2025-10-20 08:46:18","type":"credit","currency":"USD"}','{"amount":200,"description":"trt","created_at":"2025-10-20 04:22:57"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 08:52:57');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2024','1','1','delete','sarafi_transactions','39','{"transaction_id":39,"amount":200,"currency":"USD","customer_id":5,"customer_name":"Matiullah","main_account_id":13,"created_at":"2025-10-20 08:46:18"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:11:11');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2025','1','1','delete','main_account_transactions','1211','{"main_account_id":13,"transaction_id":1211,"payment_id":50,"amount":"12.000","currency":"USD","type":"credit","created_at":"2025-10-18 15:24:32"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:14:48');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2026','1','1','delete','main_account_transactions','1209','{"main_account_id":13,"transaction_id":1209,"amount":"100.000","currency":"USD","type":"debit","created_at":"2025-10-18 13:13:54"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:16:21');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2027','1','1','delete','main_account_transactions','1208','{"main_account_id":13,"transaction_id":1208,"amount":"1400.000","currency":"AFS","type":"credit","created_at":"2025-10-18 13:13:04"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:16:25');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2028','1','1','delete','main_account_transactions','1207','{"main_account_id":13,"transaction_id":1207,"amount":"20.000","currency":"USD","type":"debit","created_at":"2025-10-18 13:13:04"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:16:33');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2029','1','1','add','main_account_transactions','1219','[]','{"main_account_id":13,"amount":10,"currency":"USD","description":"fdsaf","payment_id":50,"balance":1930,"exchange_rate":0,"payment_datetime":"2025-10-20 09:16:56","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:17:03');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2030','1','1','delete','main_account_transactions','1219','{"main_account_id":13,"transaction_id":1219,"payment_id":50,"amount":"10.000","currency":"USD","type":"credit","created_at":"2025-10-20 09:16:56"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:17:25');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2031','1','1','add','main_account_transactions','1220','[]','{"main_account_id":13,"amount":10,"currency":"USD","description":"test","payment_id":50,"balance":1940,"exchange_rate":0,"payment_datetime":"2025-10-20 09:18:50","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:18:56');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2032','1','1','delete','main_account_transactions','1220','{"main_account_id":13,"transaction_id":1220,"payment_id":50,"amount":"10.000","currency":"USD","type":"credit","created_at":"2025-10-20 09:18:50"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:19:12');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2033','1','1','add','main_account_transactions','1221','[]','{"main_account_id":13,"amount":10,"currency":"USD","description":"test","payment_id":50,"balance":4118,"exchange_rate":0,"payment_datetime":"2025-10-20 09:47:55","tenant_id":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:48:00');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2034','1','1','update','0','39','{"transaction_id":"39","debtor_id":"36","amount":100,"description":"test","created_at":"2025-10-20 09:49:40","transaction_type":"credit","currency":"USD"}','{"amount":200,"description":"test","created_at":"2025-10-20 09:49:00"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 09:50:04');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2035','1','1','0','creditor_transactions','17','{"transaction_id":17,"creditor_id":13,"amount":100,"description":"tert","created_at":"2025-10-20 09:51:22","reference_number":"1212121","currency":"USD"}','{"amount":200,"description":"tert","created_at":"2025-10-20 09:51:22","reference_number":"1212121"}','::1','0','2025-10-20 09:51:43');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2036','1','1','0','creditor_transactions','17','{"transaction_id":17,"creditor_id":13,"amount":200,"description":"tert","created_at":"2025-10-20 09:51:22","reference_number":"1212121","currency":"USD"}','{"amount":300,"description":"tert","created_at":"2025-10-20 09:51:22","reference_number":"1212121"}','::1','0','2025-10-20 10:02:25');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2037','1','1','update','0','39','{"transaction_id":"39","debtor_id":"36","amount":200,"description":"test","created_at":"2025-10-20 09:49:00","transaction_type":"credit","currency":"USD"}','{"amount":300,"description":"test","created_at":"2025-10-20 09:49:00"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 10:04:37');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2038','1','1','delete','sarafi_transactions','41','{"transaction_id":41,"amount":50,"currency":"USD","customer_id":5,"customer_name":"Matiullah","main_account_id":13,"created_at":"2025-10-20 09:52:36"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 10:29:24');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2039','1','1','delete','sarafi_transactions','43','{"transaction_id":43,"amount":50,"currency":"USD","customer_id":5,"customer_name":"Matiullah","main_account_id":13,"created_at":"2025-10-20 10:12:45"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 10:29:57');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2040','1','1','delete','sarafi_transactions','45','{"transaction_id":45,"amount":50,"currency":"USD","customer_id":5,"customer_name":"Matiullah","main_account_id":13,"created_at":"2025-10-20 10:23:19"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 10:30:25');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2041','1','1','delete','sarafi_transactions','40','{"transaction_id":40,"amount":100,"currency":"USD","customer_id":5,"customer_name":"Matiullah","main_account_id":13,"created_at":"2025-10-20 09:52:08"}','[]','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 10:30:52');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2042','1','1','add','jv_payments','5','{}','{"jv_payment_id":"5","jv_name":"Client-Supplier Payment","client_id":18,"client_name":"DR SAHIBs","supplier_id":35,"supplier_name":"ALAIN T","amount":1000,"supplier_amount":1000,"currency":"USD","supplier_currency":"USD","exchange_rate":1,"receipt":"1212121","remarks":"test"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 14:05:33');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2043','1','1','update','jv_payments','5','{"id":5,"tenant_id":1,"jv_name":"Client-Supplier Payment","exchange_rate":"1.00000","total_amount":"1000.000","currency":"USD","receipt":"1212121","remarks":"test","client_id":18,"supplier_id":35,"created_by":1,"created_at":"2025-10-20 14:05:33","updated_at":"2025-10-20 14:05:33"}','{"jv_name":"Client-Supplier Payment","currency":"USD","total_amount":"1100.000","exchange_rate":"1.00000","receipt":"1212121","remarks":"test","client_id":18,"supplier_id":35,"client_name":"DR SAHIBs","supplier_name":"ALAIN T"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 14:43:06');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2044','1','1','fund','suppliers','35','{"supplier_id":35,"supplier_balance":"-4056.000","main_account_id":13,"main_account_balance":4518}','{"supplier_id":35,"supplier_balance":-3956,"main_account_id":13,"main_account_balance":4418,"amount":100,"currency":"USD","remarks":"test","receipt_number":"2452345"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 14:44:17');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2045','1','1','update','jv_payments','5','{"id":5,"tenant_id":1,"jv_name":"Client-Supplier Payment","exchange_rate":"1.00000","total_amount":"1100.000","currency":"USD","receipt":"1212121","remarks":"test","client_id":18,"supplier_id":35,"created_by":1,"created_at":"2025-10-20 14:05:33","updated_at":"2025-10-20 14:43:06"}','{"jv_name":"Client-Supplier Payment","currency":"USD","total_amount":"1200.000","exchange_rate":"1.00000","receipt":"1212121","remarks":"test","client_id":18,"supplier_id":35,"client_name":"DR SAHIBs","supplier_name":"ALAIN T"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 14:45:12');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2046','1','1','delete','jv_payments','5','{"jv_payment_id":5,"client_id":18,"client_name":"DR SAHIBs","supplier_id":35,"supplier_name":"ALAIN T","currency":"USD","total_amount":"1200.000","exchange_rate":"1.00000"}','{}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-20 14:47:38');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2047','1','1','update','users','1','[]','{"name":"Sabaoon","email":"almuqadas_travel@yahoo.com","phone":"0786011115","address":"kabul, jada-ee-mewand"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 12:53:48');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2048','1','1','update','users','1','[]','{"name":"Sabaoon","email":"almuqadas_travel@yahoo.com","phone":"0786011115","address":"kabul, jada-ee-mewand"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 13:20:50');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2049','1','1','update','users','1','[]','{"name":"Sabaoon","email":"almuqadas_travel@yahoo.com","phone":"0786011115","address":"kabul, jada-ee-mewand"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 13:23:02');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2050','1','1','update','users','1','[]','{"name":"Sabaoon","email":"almuqadas_travel@yahoo.com","phone":"0786011115","address":"kabul, jada-ee-mewand"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 13:25:21');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2051','1','1','update','users','1','[]','{"name":"Sabaoon","email":"almuqadas_travel@yahoo.com","phone":"0786011115","address":"kabul, jada-ee-mewand","profile_pic":"68f74bc0ade30_White and Blue Modern Travel Poster.png"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 13:30:48');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2052','1','1','update','users','1','[]','{"name":"SABAOON CAR REPAIR","email":"almuqadas_travel@yahoo.com","phone":"0786011115","address":"kabul, jada-ee-mewand"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 13:51:33');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2053','1','1','password_change','users','1','{"password":"(old password)"}','{"password":"(new password)"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-21 13:58:23');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2054','1','1','update','0','337','{"ticket_id":337,"supplier":"32","sold_to":19,"price":"330.030","sold":"340.000","currency":"USD"}','{"supplier":32,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"KHASTA GUL","pnr":"86S99L","phone":"0771781576","origin":" KBL","destination":"ELQ","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-25","return_date":"","price":335.03,"sold":340,"profit":4.97,"currency":"USD","description":"Sale for ticket: KHASTA GUL ( KBL to ELQ)","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-25 08:58:04');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2055','1','1','update','0','337','{"ticket_id":337,"supplier":"32","sold_to":19,"price":"335.030","sold":"340.000","currency":"USD"}','{"supplier":32,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"KHASTA GUL","pnr":"86S99L","phone":"0771781576","origin":" KBL","destination":"ELQ","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-25","return_date":"","price":335.03,"sold":350,"profit":14.97,"currency":"USD","description":"Sale for ticket: KHASTA GUL ( KBL to ELQ)","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-25 08:59:16');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2056','1','1','update','0','337','{"ticket_id":337,"supplier":"32","sold_to":19,"price":"335.030","sold":"350.000","currency":"USD"}','{"supplier":32,"sold_to":18,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"KHASTA GUL","pnr":"86S99L","phone":"0771781576","origin":" KBL","destination":"ELQ","return_origin":"","return_destination":"","airline":"FZ","issue_date":"2025-10-16","departure_date":"2025-10-25","return_date":"","price":335.03,"sold":350,"profit":14.97,"currency":"USD","description":"Sale for ticket: KHASTA GUL ( KBL to ELQ)","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-25 09:00:33');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2057','1','1','add','main_account_transactions','1243','[]','{"booking_id":101,"payment_date":"2025-10-27 15:37:25","description":"test","amount":10,"currency":"USD","exchange_rate":0,"client_type":"agency","main_account_id":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:37:56');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2058','1','1','add','main_account_transactions','1244','[]','{"booking_id":49,"payment_date":"2025-10-27 15:38:18","description":"tets","amount":10,"currency":"USD","exchange_rate":0,"main_account_id":13}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:38:26');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2059','1','1','create','main_account_transactions','1245',NULL,'{"weight_id":17,"amount":10,"currency":"USD","exchange_rate":null,"transaction_date":"2025-10-27 15:40","remarks":"test","main_account_id":13,"balance":4528}','::1','0','2025-10-27 15:40:15');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2060','1','1','update','hotel_bookings','47','{"booking_id":47,"title":"","first_name":"","last_name":"","base_amount":"15.000","sold_amount":"25.000","currency":"USD","supplier_id":32,"sold_to":"18","receipt":""}','{"title":"Mr","first_name":"EDRISS ","last_name":"NOOR","gender":"Male","contact_no":"766666232","check_in_date":"2025-10-18","check_out_date":"2025-10-24","accommodation_details":"sfdsa","base_amount":15,"sold_amount":25,"profit":10,"currency":"USD","supplier_id":"32","sold_to":"19","paid_to":"13","remarks":"sadf","receipt":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:44:07');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2061','1','1','add','main_account_transactions','1246','[]','{"booking_id":47,"payment_date":null,"description":"test","amount":10,"currency":"USD","exchange_rate":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:44:18');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2062','1','1','add','main_account_transactions','1247','[]','{"refund_id":15,"payment_date":"2025-10-27 15:46:36","description":"Refund payment for Hotel Booking #47 - Mr EDRISS  NOOR","amount":25,"currency":"USD","client_type":"agency","main_account_id":13,"first_name":"EDRISS ","last_name":"NOOR","order_id":"1089734"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:46:48');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2063','1','1','add','umrah_transactions','96','[]','{"umrah_booking_id":66,"transaction_to":"Internal Account","payment_amount":10,"payment_currency":"USD","payment_description":"test","payment_date":"2025-10-27","receipt_number":""}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:47:28');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2064','1','1','update','visa_applications','60','{"id":60,"supplier":35,"sold_to":18,"base":"110.000","sold":"130.000","currency":"USD"}','{"id":60,"supplier":35,"sold_to":19,"title":"Mr","gender":"Male","applicant_name":"BAKHTIAR STANIKZAI","passport_number":"P8798765","country":"Pakistan","visa_type":"Tourist","receive_date":"2025-10-18","applied_date":"2025-10-25","issued_date":"","base":110,"sold":130,"profit":20,"currency":"USD","status":"Pending","remarks":"adf","phone":"0777555594"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-27 15:51:27');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2065','1','1','add','suppliers','36','[]','{"name":"Matiullah","contact_person":"NAVEED RASHIQ","phone":"0777305730","email":"admin@abc-construction.com","address":"test","currency":"USD","balance":0,"supplier_type":"Internal"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-28 09:23:28');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2066','1','1','add','ticket_bookings','340','{}','{"multiple_passengers":true,"passenger_count":1,"pnr":"86S99L","origin":"Kabul","destination":"JED","airline":"A3","departure_date":"2025-10-31","total_base":100,"total_sold":120,"total_discount":0,"total_profit":20,"currency":"USD","supplier_id":36,"supplier_name":"Matiullah","client_id":19,"client_name":"Walking Customers","trip_type":"one_way"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-28 09:25:33');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2067','1','1','update','0','340','{"ticket_id":340,"supplier":"36","sold_to":19,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":32,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"guli","pnr":"86S99L","phone":"0771781576","origin":"Kabul","destination":"JED","return_origin":"","return_destination":"","airline":"A3","issue_date":"2025-10-28","departure_date":"2025-10-31","return_date":"","price":100,"sold":120,"profit":20,"currency":"USD","description":"test","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-28 09:26:10');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2068','1','1','update','0','340','{"ticket_id":340,"supplier":"32","sold_to":19,"price":"100.000","sold":"120.000","currency":"USD"}','{"supplier":32,"sold_to":19,"trip_type":"one_way","title":"Mr","gender":"Male","passenger_name":"guli","pnr":"86S99L","phone":"0771781576","origin":"Kabul","destination":"JED","return_origin":"","return_destination":"","airline":"A3","issue_date":"2025-10-28","departure_date":"2025-10-29","return_date":"","price":100,"sold":120,"profit":20,"currency":"USD","description":"test","paid_to":13,"market_exchange_rate":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-29 15:23:46');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2069','1','1','add_employee','users','18','null','{"name":"Matiullah","email":"matiullahr970@gmail.com","phone":"0777305730","role":"finance","hire_date":"2025-10-29","address":"test"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-29 16:28:44');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2070','1','18','update','users','18','[]','{"name":"Matiullah","email":"matiullahr970@gmail.com","phone":"0777305730","address":"test","profile_pic":"690307f9d794a_umrah.png"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-30 11:08:49');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2071','1','1','add','expenses','216','[]','{"category_id":"19","date":"2025-11-01","description":"this is for test","amount":"100","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"31531","receipt_file":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:03:34');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2072','1','1','update','expenses','216','{"expense_id":"216","previous_values":{"amount":"100.000","currency":"AFS","main_account_id":13,"allocation_id":null,"receipt_file":null}}','{"category_id":"19","date":"2025-11-01","description":"this is for test","amount":"200.000","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"31531","receipt_file":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:04:11');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2073','1','1','update','0','1046','{"notification_id":1046,"previous_status":"Read"}','{"status":"read"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:10:40');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2074','1','1','update','0','1047','{"notification_id":1047,"previous_status":"Read"}','{"status":"read"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:10:42');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2075','1','1','update','expenses','216','{"expense_id":"216","previous_values":{"amount":"200.000","currency":"AFS","main_account_id":13,"allocation_id":null,"receipt_file":null}}','{"category_id":"19","date":"2025-11-01","description":"this is for test","amount":"300.000","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"31531","receipt_file":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:10:58');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2076','1','1','update','0','1048','{"notification_id":1048,"previous_status":"Read"}','{"status":"read"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:13:43');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2077','1','1','update','expenses','216','{"expense_id":"216","previous_values":{"amount":"300.000","currency":"AFS","main_account_id":13,"allocation_id":null,"receipt_file":null}}','{"category_id":"19","date":"2025-11-01","description":"this is for test","amount":"400.000","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"31531","receipt_file":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:13:56');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2078','1','1','add','expenses','217','[]','{"category_id":"19","date":"2025-11-01","description":"this is for test","amount":"100","currency":"AFS","main_account_id":"13","allocation_id":null,"receipt_number":"adfasdf","receipt_file":"receipt_6905ac54c7348.png"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 11:14:36');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2079','1','1','update_employee','users','6','{"name":"Idrees","email":"idress@gmail.com","phone":"0777555594","role":"umrah","hire_date":"2025-04-09","address":"Jada-e-Maiwand","profile_pic":"67f60cc0da261.jpg"}','{"name":"Idrees","email":"idress@gmail.com","phone":"0777555594","role":"sales","hire_date":"2025-04-09","address":"Jada-e-Maiwand","profile_pic":"67f60cc0da261.jpg"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 14:23:56');
INSERT INTO `activity_log` (`id`,`tenant_id`,`user_id`,`action`,`table_name`,`record_id`,`old_values`,`new_values`,`ip_address`,`user_agent`,`created_at`) VALUES ('2080','1','6','update','users','6','[]','{"name":"Idrees","email":"idress@gmail.com","phone":"0777555594","address":"Jada-e-Maiwand","profile_pic":"6905da1a0e1d6_WhatsApp Image 2025-10-27 at 11.31.26 AM.jpeg"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-11-01 14:29:54');

DROP TABLE IF EXISTS `additional_payments`;
CREATE TABLE `additional_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `payment_type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `base_amount` decimal(15,2) NOT NULL,
  `sold_amount` decimal(15,2) NOT NULL,
  `profit` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `is_from_supplier` tinyint(1) DEFAULT 0,
  `client_id` int(11) DEFAULT NULL,
  `is_for_client` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `additional_payments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_additional_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `additional_payments` (`id`,`tenant_id`,`payment_type`,`description`,`base_amount`,`sold_amount`,`profit`,`currency`,`main_account_id`,`created_by`,`created_at`,`updated_at`,`receipt`,`supplier_id`,`is_from_supplier`,`client_id`,`is_for_client`) VALUES ('50','1','Vacine','test','10.00','15.00','5.00','USD','13','1','2025-10-18 14:59:18','2025-10-18 14:59:18',NULL,NULL,'1',NULL,'1');
INSERT INTO `additional_payments` (`id`,`tenant_id`,`payment_type`,`description`,`base_amount`,`sold_amount`,`profit`,`currency`,`main_account_id`,`created_by`,`created_at`,`updated_at`,`receipt`,`supplier_id`,`is_from_supplier`,`client_id`,`is_for_client`) VALUES ('51','1','Vacine','test','10.00','15.00','5.00','USD','13','1','2025-10-18 14:59:46','2025-10-18 14:59:46',NULL,'32','1','19','1');

DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `purchase_date` date NOT NULL,
  `purchase_value` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `status` enum('active','inactive','maintenance','sold','disposed') NOT NULL DEFAULT 'active',
  `assigned_to` varchar(255) DEFAULT NULL,
  `condition_state` varchar(100) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_assets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('1','1','create_tenant','tenant','1','{"tenant_name": "Travel Agency Alpha"}','192.168.1.1','2025-08-24 00:00:01');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('2','1','update_subscription','subscription','1','{"plan_id": "basic", "status": "active"}','192.168.1.1','2025-08-24 00:00:02');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('3','1','update_platform_setting','platform_setting','1','{"key": "default_currency", "value": "USD"}','192.168.1.1','2025-08-24 00:00:03');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('4','2','view_usage_report','tenant','2','{"metric_type": "api_calls", "date": "2025-08-23"}','192.168.1.2','2025-08-24 00:00:04');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('5','14','delete_tenant','tenant','4','{"name":"Suspended Tours Delta"}','::1','2025-08-26 15:42:34');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('6','14','create_tenant','tenant','6','{"name":"KamAir","subdomain":"mtravels","identifier":"travelalmuqadas","plan":"basic"}','::1','2025-08-26 16:09:54');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('7','14','delete_tenant','tenant','6','{"name":"KamAir"}','::1','2025-08-26 16:11:22');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('8','14','update_platform_settings','platform_setting','0','{"agency_name":"MTravels","default_currency":"AFN","support_email":"allahdadmuhammadi01@gmail.com","api_enabled":"true","max_users_per_tenant":"20","logo_updated":true}','::1','2025-08-26 17:02:02');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('9','14','update_platform_settings','platform_setting','0','{"agency_name":"MTravels","default_currency":"AFN","support_email":"allahdadmuhammadi01@gmail.com","api_enabled":"true","max_users_per_tenant":"20","logo_updated":true}','::1','2025-08-26 17:07:54');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('10','14','update_platform_settings','platform_setting','0','{"agency_name":"MTravels","default_currency":"AFN","support_email":"allahdadmuhammadi01@gmail.com","api_enabled":"true","max_users_per_tenant":"20","logo_updated":true}','::1','2025-08-26 17:10:47');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('11','14','extend_subscription','subscription','1','Extended subscription by 1 months','','2025-08-27 17:16:29');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('12','14','extend_subscription','subscription','1','Extended subscription by 3 months','','2025-08-27 17:18:27');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('13','14','update_plan','plan','0','{"old_name":"basic","new_name":"basic","description":"Basic plan with access to ticket-related tasks only","price":"200","max_users":"20","trial_days":"10","status":"active"}','::1','2025-08-28 11:56:25');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('14','14','update_plan','plan','0','{"old_name":"basic","new_name":"basic","description":"Basic plan with access to ticket-related tasks only","price":"200.00","max_users":"20","trial_days":"10","status":"active"}','::1','2025-08-28 14:50:33');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('15','14','update_subscription','subscription','5','{"tenant_id":5,"plan_id":"2","status":"pending","billing_cycle":"monthly"}','::1','2025-08-30 12:09:31');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('16','14','update_plan','plan','0','{"old_name":"basic","new_name":"basic","description":"Basic plan with access to ticket-related tasks only","price":"200.00","max_users":"20","trial_days":"10","status":"active"}','::1','2025-08-30 12:10:19');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('17','14','update_tenant','tenant','5','{"tenant_id":5,"name":"New Ventures Epsilon","subdomain":"epsilon","status":"active"}','::1','2025-08-30 12:13:52');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('18','14','update_platform_settings','platform_setting','0','{"agency_name":"MTravels","default_currency":"AFN","support_email":"allahdadmuhammadi01@gmail.com","api_enabled":"false","max_users_per_tenant":"20","logo_updated":false}','::1','2025-08-30 12:26:11');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('19','14','update_tenant','tenant','1','{"tenant_id":1,"name":"Travel Agency Alpha","subdomain":"alpha","status":"active"}','::1','2025-09-01 16:55:47');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('20','14','update_tenant','tenant','2','{"tenant_id":2,"name":"Global Tours Beta","subdomain":"beta","status":"active"}','::1','2025-09-01 16:55:54');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('21','14','update_tenant','tenant','5','{"tenant_id":5,"name":"New Ventures Epsilon","subdomain":"epsilon","status":"active"}','::1','2025-09-01 16:56:00');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('22','14','update_subscription','subscription','1','{"tenant_id":1,"plan_id":"2","status":"active","billing_cycle":"monthly"}','::1','2025-09-01 17:16:02');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('23','14','update_subscription','subscription','1','{"tenant_id":1,"plan_id":"3","status":"active","billing_cycle":"monthly"}','::1','2025-09-01 17:16:15');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('24','14','update_plan','plan','0','{"old_name":"enterprise","new_name":"enterprise","description":"Enterprise plan with all Pro features plus Umrah management","price":"0.00","max_users":"0","trial_days":"0","status":"active"}','::1','2025-09-03 10:45:43');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('25','14','update_subscription','subscription','2','{"tenant_id":2,"plan_id":"3","status":"active","billing_cycle":"yearly"}','::1','2025-09-04 17:02:25');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('26','14','update_platform_settings','platform_setting','0','{"platform_name":"MTravels","support_email":"allahdadmuhammadi01@gmail.com","contact_email":"allahdadmuhammadi01@gmail.com","website_url":"https:\\/\\/construct360.com","contact_phone":"0780310431","support_phone":"+93780310431","contact_address":"Kabul,Afghanistan","contact_facebook":"https:\\/\\/mtravels.com","contact_twitter":"https:\\/\\/mtravels.com","contact_linkedin":"https:\\/\\/mtravels.com","contact_instagram":"https:\\/\\/mtravels.com","default_currency":"AFN","max_users_per_tenant":"20","api_enabled":"false","logo_updated":false,"favicon_updated":false}','::1','2025-09-09 10:31:54');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('27','14','update_platform_settings','platform_setting','0','{"platform_name":"MTravels","support_email":"allahdadmuhammadi01@gmail.com","contact_email":"allahdadmuhammadi01@gmail.com","website_url":"https:\\/\\/mtravels.com","contact_phone":"0780310431","support_phone":"+93780310431","contact_address":"Kabul,Afghanistan","contact_facebook":"https:\\/\\/mtravels.com","contact_twitter":"https:\\/\\/mtravels.com","contact_linkedin":"https:\\/\\/mtravels.com","contact_instagram":"https:\\/\\/mtravels.com","default_currency":"AFN","max_users_per_tenant":"20","api_enabled":"false","logo_updated":false,"favicon_updated":false}','::1','2025-09-09 10:32:09');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('28','14','update_platform_settings','platform_setting','0','{"platform_name":"MTravels","support_email":"allahdadmuhammadi01@gmail.com","contact_email":"allahdadmuhammadi01@gmail.com","website_url":"https:\\/\\/mtravels.com","contact_phone":"0780310431","support_phone":"+93780310431","contact_address":"Kabul,Afghanistan","contact_facebook":"https:\\/\\/mtravels.com","contact_twitter":"https:\\/\\/mtravels.com","contact_linkedin":"https:\\/\\/mtravels.com","contact_instagram":"https:\\/\\/mtravels.com","default_currency":"AFN","max_users_per_tenant":"20","api_enabled":"false","logo_updated":false,"favicon_updated":false}','::1','2025-09-09 10:32:23');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('29','14','update_platform_settings','platform_setting','0','{"platform_name":"MTravels","support_email":"allahdadmuhammadi01@gmail.com","contact_email":"allahdadmuhammadi01@gmail.com","website_url":"https:\\/\\/mtravels.com","contact_phone":"0780310431","support_phone":"+93780310431","contact_address":"Kabul,Afghanistan","contact_facebook":"https:\\/\\/mtravels.com","contact_twitter":"https:\\/\\/mtravels.com","contact_linkedin":"https:\\/\\/mtravels.com","contact_instagram":"https:\\/\\/mtravels.com","default_currency":"AFN","max_users_per_tenant":"20","api_enabled":"false","logo_updated":false,"favicon_updated":false}','::1','2025-09-09 10:35:17');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('30','14','update_platform_settings','platform_setting','0','{"platform_name":"MTravels","support_email":"allahdadmuhammadi01@gmail.com","contact_email":"allahdadmuhammadi01@gmail.com","website_url":"https:\\/\\/mtravels.com","contact_phone":"0780310431","support_phone":"+93780310431","contact_address":"Kabul,Afghanistan","contact_facebook":"https:\\/\\/mtravels.com","contact_twitter":"https:\\/\\/mtravels.com","contact_linkedin":"https:\\/\\/mtravels.com","contact_instagram":"https:\\/\\/mtravels.com","default_currency":"AFN","max_users_per_tenant":"20","api_enabled":"false","logo_updated":false,"favicon_updated":false}','::1','2025-09-09 10:37:01');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('31','14','delete_tenant','tenant','3','{"name":"Elite Pilgrimages Gamma"}','::1','2025-09-09 11:22:26');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('32','14','delete_tenant','tenant','5','{"name":"New Ventures Epsilon"}','::1','2025-09-09 11:22:30');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('33','14','update_subscription','subscription','1','{"tenant_id":1,"plan_id":"3","status":"active","billing_cycle":"monthly"}','::1','2025-09-17 09:00:47');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('34','14','update_subscription','subscription','2','{"tenant_id":2,"plan_id":"3","status":"active","billing_cycle":"yearly"}','::1','2025-09-17 10:12:36');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('35','14','update_subscription','subscription','2','{"tenant_id":2,"plan_id":"3","status":"active","billing_cycle":"yearly"}','::1','2025-09-17 11:10:29');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('36','14','update_subscription','subscription','1','{"subscription_id":1,"plan_id":"3","status":"active","billing_cycle":"monthly"}','::1','2025-09-18 13:49:08');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('37','14','update_subscription','subscription','1','{"subscription_id":1,"plan_id":"3","status":"active","billing_cycle":"monthly"}','::1','2025-09-18 14:34:53');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('38','14','update_platform_settings','platform_setting','0','{"platform_name":"MTravels","support_email":"allahdadmuhammadi01@gmail.com","contact_email":"allahdadmuhammadi01@gmail.com","website_url":"https:\\/\\/mtravels.com","contact_phone":"0780310431","support_phone":"+93780310431","contact_address":"Kabul,Afghanistan","contact_facebook":"https:\\/\\/mtravels.com","contact_twitter":"https:\\/\\/mtravels.com","contact_linkedin":"https:\\/\\/mtravels.com","contact_instagram":"https:\\/\\/mtravels.com","default_currency":"AFN","max_users_per_tenant":"20","api_enabled":"false","logo_updated":true,"favicon_updated":false}','::1','2025-10-02 16:22:07');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('39','14','update_demo_request_status','demo_request','1','{"new_status":"contacted"}','::1','2025-10-04 10:36:40');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('40','14','update_subscription','subscription','1','{"subscription_id":1,"plan_id":"3","status":"active","billing_cycle":"monthly"}','::1','2025-10-06 10:59:38');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES ('41','14','update_testimonial','testimonials','4','Updated testimonial for Ahmad Rahimi','','2025-10-13 14:46:57');

DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `excerpt` mediumtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_blog_posts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `budget_allocations`;
CREATE TABLE `budget_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `allocated_amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `allocation_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `main_account_id` (`main_account_id`),
  KEY `category_id` (`category_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `budget_allocations_ibfk_1` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_allocations_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_allocations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(50) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `tenant_id_from` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seen_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_room_time` (`room_id`,`created_at`),
  KEY `idx_to_user` (`to_user_id`),
  KEY `fk_cm_from_user` (`from_user_id`),
  CONSTRAINT `fk_cm_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('1','u-1-6','1','6','1','hi','2025-09-03 15:27:21','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('2','u-1-6','6','1','1','heloo','2025-09-03 15:29:46','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('3','u-1-6','1','6','1','hiy','2025-09-03 15:41:09','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('4','u-1-6','1','6','1','hi','2025-09-03 16:52:06','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('5','u-1-6','6','1','1','hi','2025-09-03 17:12:15','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('6','u-1-6','1','6','1','{"type":"file","name":"01.pdf","size":368254,"mimeType":"application\\/pdf","filePath":"file_68b83d78d693d_01.pdf"}','2025-09-03 17:37:04','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('7','u-1-6','1','6','1','adf','2025-09-03 17:41:37','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('8','u-1-6','1','6','1','{"type":"file","name":"01.pdf","size":368254,"mimeType":"application\\/pdf","filePath":"file_68b83e8e935f5_01.pdf"}','2025-09-03 17:41:42','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('9','u-1-6','1','6','1','adf','2025-09-03 17:42:17','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('10','u-1-6','1','6','1','{"type":"file","name":"Blue Modern Travel Poster Portrait.png","size":13431638,"mimeType":"image\\/png","filePath":"file_68b83eb814e5a_Blue_Modern_Travel_Poster_Portrait.png"}','2025-09-03 17:42:24','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('11','u-1-6','1','6','1','adf','2025-09-04 08:12:12','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('12','u-1-6','6','1','1','{"type":"file","name":"01.pdf","size":368254,"mimeType":"application\\/pdf","filePath":"file_68b90ab369be6_01.pdf"}','2025-09-04 08:12:43','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('13','u-1-6','1','6','1','{"type":"file","name":"Blue and White Grunge Travel and Tourism Instagram Post.png","size":1030459,"mimeType":"image\\/png","filePath":"file_68b967379e0fe_Blue_and_White_Grunge_Travel_and_Tourism_Instagram_Post.png"}','2025-09-04 14:47:27','2025-09-04 15:08:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('14','u-1-6','6','1','1','{"type":"file","name":"Blue and White Modern Travel Instagram Post.png","size":1363535,"mimeType":"image\\/png","filePath":"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post.png"}','2025-09-04 14:56:39','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('15','u-1-6','6','1','1','hi','2025-09-04 15:51:34','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('16','u-1-6','6','1','1','{"type":"file","name":"01.pdf","size":368254,"mimeType":"application\\/pdf","filePath":"file_68b97885ca8a6_01.pdf"}','2025-09-04 16:01:17','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('17','u-1-6','6','1','1','{"type":"file","name":"01.pdf","size":368254,"mimeType":"application\\/pdf","filePath":"file_68b97b27811c2_01.pdf"}','2025-09-04 16:12:31','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('18','u-1-6','6','1','1','fa','2025-09-04 16:12:42','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('19','u-1-6','6','1','1','{"type":"file","name":"03.pdf","size":356854,"mimeType":"application\\/pdf","filePath":"file_68b97b39d3e1d_03.pdf"}','2025-09-04 16:12:49','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('20','u-1-6','6','1','1','{"type":"file","name":"\\u0631\\u0633\\u06cc\\u062f \\u0628\\u0627\\u0646\\u06a9\\u06cc - Al Moqadas.pdf","size":196990,"mimeType":"application\\/pdf","filePath":"file_68b97b6e505d6_____________________-_Al_Moqadas.pdf"}','2025-09-04 16:13:42','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('21','u-1-6','6','1','1','{"type":"file","name":"Blue Modern Travel Poster Portrait.png","size":13431638,"mimeType":"image\\/png","filePath":"file_68b97b73d9136_Blue_Modern_Travel_Poster_Portrait.png"}','2025-09-04 16:13:47','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('22','u-1-6','6','1','1','{"type":"file","name":"03.pdf","size":356854,"mimeType":"application\\/pdf","filePath":"file_68b97c5c366e6_03.pdf"}','2025-09-04 16:17:40','2025-09-04 16:31:07');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('23','u-1-6','6','1','1','{"type":"file","name":"Yellow And Blue Modern Open Trip To England Instagram Post.png","size":722868,"mimeType":"image\\/png","filePath":"file_68b97fad61f31_Yellow_And_Blue_Modern_Open_Trip_To_England_Instagram_Post.png"}','2025-09-04 16:31:49','2025-09-04 16:32:45');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('24','u-1-6','6','1','1','{"type":"file","name":"aisalmot_travelagency (14).sql","size":2133029,"mimeType":"text\\/plain","filePath":"file_68b97fcfb0ed6_aisalmot_travelagency__14_.sql"}','2025-09-04 16:32:23','2025-09-04 16:32:45');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('25','u-1-6','6','1','1','{"type":"file","name":"03.pdf","size":356854,"mimeType":"application\\/pdf","filePath":"file_68b98187cb4ec_03.pdf"}','2025-09-04 16:39:43','2025-09-04 16:47:06');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('26','u-1-6','6','1','1','{"type":"file","name":"voice-1756988605345.webm","size":122843,"mimeType":"video\\/webm","filePath":"file_68b984bd583ae_voice-1756988605345.webm"}','2025-09-04 16:53:25','2025-09-04 16:58:57');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('27','u-1-7','1','7','1','hello','2025-09-04 17:28:00','2025-09-04 17:28:13');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('28','u-1-6','1','6','1','heloo','2025-09-08 12:57:42','2025-09-08 12:59:06');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('30','u-1-7','1','7','1','{"type":"reply","replyTo":"27","replyText":"hello","content":"fadf"}','2025-09-08 14:49:26','2025-09-09 11:39:30');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('31','u-1-6','1','6','1','hello','2025-09-08 14:53:11','2025-09-08 16:22:31');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('32','u-1-7','1','7','1','hi','2025-09-08 14:59:45','2025-09-09 11:39:30');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('34','u-1-6','1','6','1','{"type":"reply","replyTo":"18","replyText":"fa","content":"hi"}','2025-09-08 15:06:34','2025-09-08 16:22:31');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('35','u-1-7','1','7','1','{"type":"file","name":"Yellow And Blue Modern Open Trip To England Instagram Post.png","size":722868,"mimeType":"image\\/png","filePath":"file_68beb2d9b6d9b_Yellow_And_Blue_Modern_Open_Trip_To_England_Instagram_Post.png"}','2025-09-08 15:11:29','2025-09-09 11:39:30');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('36','u-1-6','1','6','1','{"type":"reply","replyTo":"23","replyText":"📷 Photo","content":"good"}','2025-09-08 15:11:54','2025-09-08 16:22:31');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('37','u-1-6','1','6','1','{"type":"reply","replyTo":"34","replyText":"hi","content":"helo"}','2025-09-08 15:23:42','2025-09-08 16:22:31');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('38','u-1-7','1','7','1','helo','2025-09-08 15:30:34','2025-09-09 11:39:30');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('39','u-1-6','1','6','1','hi','2025-10-28 09:04:25','2025-11-01 14:26:32');
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('40','u-1-7','1','7','1','hi','2025-10-29 14:46:34',NULL);
INSERT INTO `chat_messages` (`id`,`room_id`,`from_user_id`,`to_user_id`,`tenant_id_from`,`content`,`created_at`,`seen_at`) VALUES ('41','u-1-18','18','1','1','hi','2025-10-30 11:02:06','2025-11-01 11:02:40');

DROP TABLE IF EXISTS `client_transactions`;
CREATE TABLE `client_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','additional_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','umrah_date_change') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `client_transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_client_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=588 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('561','1','19','debit','375.000','-375.000','USD','Ticket booked for Mr MUHAMMAD RAUF with PNR: 5DYFCD from  KBL to HAS.','2025-10-16 11:31:45','ticket_sale','336','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('562','1','18','debit','330.000','-330.000','USD','Ticket booked for Mr KHASTA GUL with PNR: 86S99L from  KBL to ELQ. (Transferred from client 19)','2025-10-16 11:33:05','ticket_sale','337','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('563','1','19','debit','330.000','330.000','USD','Ticket booked for Mr BAIKHT BAIDAR BAKHT JEHAN with PNR: T6EXHA from  KBL to ELQ.','2025-10-16 11:34:32','ticket_sale','338','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('564','1','22','debit','431.000','-431.000','USD','Ticket booked for Mr HAJI MUSA GUL MINAH KHAN with PNR: 181QHU from  KBL to JED.','2025-10-16 16:05:11','ticket_sale','339','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('568','1','22','credit','431.000','0.000','USD','Client: MINA Khan, Account funded by Sabaoon. Remarks: test','2025-10-16 16:17:31','fund','1','217687','1.00000');
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('569','1','18','debit','330.000','-3826.300','USD','Sale for ticket: KHASTA GUL ( KBL to ELQ) (Transferred from client 18) (Transferred from client 19)','2025-10-18 09:02:06','ticket_sale','337','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('570','1','19','debit','120.000','540.000','USD','Ticket reservation for passenger BAKHTIAR STANIKZAI','2025-10-18 09:53:08','ticket_reserve','18','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('571','1','19','debit','120.000','-3616.300','USD','Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA) (Transferred from client 18)','2025-10-18 09:58:44','ticket_reserve','18','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('572','1','18','debit','130.000','-4286.300','USD','Updated: Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)','2025-10-18 10:04:55','ticket_reserve','18','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('573','1','22','credit','353.000','353.000','USD','Refund for ticket HAJI MUSA GUL MINAH KHAN.','2025-10-18 10:19:21','ticket_refund','99','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('574','1','19','debit','40.000','620.000','USD','Weight transaction: 20kg at 40 USD.','2025-10-18 10:24:51','weight_sale','16','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('575','1','19','debit','20.000','640.000','USD','Weight transaction: 20kg at 20 USD.','2025-10-18 10:25:22','weight_sale','17','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('576','1','19','debit','25.000','-4311.300','USD','Updated: Sale for hotel booking: EDRISS  NOOR (Check-in: 2025-10-18) (Transferred from client 18)','2025-10-18 11:05:52','hotel','47','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('577','1','19','debit','120.000','660.000','USD','Visa booking for BAKHTIAR STANIKZAI','2025-10-18 13:49:18','visa_sale','60','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('580','1','19','debit','0.000','660.000','USD','Client was debited 0 for umrah booking for Matiullah','2025-10-18 14:08:20','umrah','66','0',NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('581','1','18','debit','-130.000','-4416.300','USD','Updated: Sale for member: Matiullah (Passport: P03241263)','2025-10-18 14:33:15','umrah','66',NULL,NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('582','1','19','debit','15.000','645.000','USD','Additional payment: Vacine - test','2025-10-18 14:59:46','additional_payment','51',NULL,NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('584','1','18','credit','100.000','-4316.300','USD','Client: DR SAHIBs, Account funded by Sabaoon. Remarks: ','2025-10-20 14:44:54','fund','1','2452345','1.00000');
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('585','1','18','debit','350.000','-5326.300','USD','Sale for ticket: KHASTA GUL ( KBL to ELQ)','2025-10-25 09:00:33','ticket_sale','337',NULL,NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('586','1','19','credit','25.000','0.000','USD','Refund for hotel booking #47 - test','2025-10-27 15:46:33','hotel_refund','15',NULL,NULL);
INSERT INTO `client_transactions` (`id`,`tenant_id`,`client_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('587','1','19','debit','120.000','540.000','USD','Ticket booked for Mr guli with PNR: 86S99L from Kabul to JED.','2025-10-28 09:25:33','ticket_sale','340',NULL,NULL);

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `image` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `usd_balance` decimal(10,3) DEFAULT 0.000,
  `afs_balance` decimal(10,3) DEFAULT 0.000,
  `address` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `client_type` enum('regular','agency') DEFAULT 'regular',
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_email` (`tenant_id`,`email`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_clients_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clients` (`id`,`tenant_id`,`image`,`name`,`email`,`password_hash`,`phone`,`usd_balance`,`afs_balance`,`address`,`created_at`,`updated_at`,`status`,`client_type`,`totp_enabled`) VALUES ('18','1','','DR SAHIBs','admin@abc-construction.com','$2y$10$wNU7te9YSBzmM86Q3Z9D6eLp2SloDOyIz29TUmz/JDc9ZJMwT2EG.','0777305730','-5326.300','0.000','Jada-e-Maiwand','2025-09-01 15:14:22','2025-10-27 15:51:27','active','regular','0');
INSERT INTO `clients` (`id`,`tenant_id`,`image`,`name`,`email`,`password_hash`,`phone`,`usd_balance`,`afs_balance`,`address`,`created_at`,`updated_at`,`status`,`client_type`,`totp_enabled`) VALUES ('19','1','690854d3a4ecb_Blue and White Simple Furniture Doubled Sided Poster.png','Walking Customer','almuqadas_travel@yahoo.com','$2y$10$RCql5Ieq91Jkd8FaykSbKOxX/QNNJ9LD4jKJORz6Zkw9rlalh0qVS','0777305730','660.000','0.000','Jada-e-Maiwand','2025-09-07 13:12:27','2025-11-03 11:38:03','active','agency','0');
INSERT INTO `clients` (`id`,`tenant_id`,`image`,`name`,`email`,`password_hash`,`phone`,`usd_balance`,`afs_balance`,`address`,`created_at`,`updated_at`,`status`,`client_type`,`totp_enabled`) VALUES ('20','2','','DR SAHIB','DRal@GMAIL.COM','$2y$10$AgeoMovkpOzvOgWKT7b5tegnAHDaUcLYwr3SJ80aDsw0o20llzJrm','0777305730','0.000','0.000','Jada-e-Maiwand','2025-09-10 16:18:39','2025-09-10 17:41:15','active','regular','0');
INSERT INTO `clients` (`id`,`tenant_id`,`image`,`name`,`email`,`password_hash`,`phone`,`usd_balance`,`afs_balance`,`address`,`created_at`,`updated_at`,`status`,`client_type`,`totp_enabled`) VALUES ('21','2','','walkings','esmati@gmail.com','$2y$10$JWMeEbMm9oA4FxG9DhK6.uMNh.NLazftrApSj/oG3KPJcM1MikAJK','0777305730','0.000','0.000','Jada-e-Maiwand','2025-09-10 17:01:12','2025-09-10 17:01:12','active','agency','0');
INSERT INTO `clients` (`id`,`tenant_id`,`image`,`name`,`email`,`password_hash`,`phone`,`usd_balance`,`afs_balance`,`address`,`created_at`,`updated_at`,`status`,`client_type`,`totp_enabled`) VALUES ('22','1','','MINA Khan','mina@yahoo.com','$2y$10$E70pRj3P2NYVedD5bA1UgeOB0srLqm.0nFlsDsGmhIB5BsKDl/qha','0777305730','353.000','0.000','adfads','2025-10-16 15:59:38','2025-10-18 10:19:21','active','regular','0');

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `created_at` (`created_at`),
  KEY `status` (`status`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `creditor_transactions`;
CREATE TABLE `creditor_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `creditor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creditor_id` (`creditor_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `creditor_transactions_ibfk_1` FOREIGN KEY (`creditor_id`) REFERENCES `creditors` (`id`),
  CONSTRAINT `fk_creditor_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `creditor_transactions` (`id`,`tenant_id`,`creditor_id`,`amount`,`currency`,`transaction_type`,`description`,`payment_date`,`reference_number`,`created_at`) VALUES ('19','1','15','20.00','USD','debit','tete','2025-10-27','1212121','2025-10-27 16:30:06');

DROP TABLE IF EXISTS `creditors`;
CREATE TABLE `creditors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_creditors_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `creditors` (`id`,`tenant_id`,`name`,`email`,`phone`,`address`,`balance`,`currency`,`status`,`created_at`) VALUES ('15','1','Matiullah','admin@abc-construction.com','0777305730','test','80.00','USD','active','2025-10-27 16:29:51');

DROP TABLE IF EXISTS `customer_wallets`;
CREATE TABLE `customer_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_unique_customer_currency` (`tenant_id`,`customer_id`,`currency`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `customer_wallets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_customer_wallets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customer_wallets` (`id`,`tenant_id`,`customer_id`,`currency`,`balance`,`created_at`,`updated_at`) VALUES ('12','1','5','USD','400.00','2025-10-20 08:46:18','2025-10-20 10:32:22');

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`,`tenant_id`,`name`,`email`,`phone`,`address`,`status`,`created_at`,`updated_at`) VALUES ('5','1','Matiullah','admin@abc-construction.com','0777305730','6345','active','2025-10-20 08:45:45','2025-10-20 08:45:45');

DROP TABLE IF EXISTS `date_change_tickets`;
CREATE TABLE `date_change_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(255) NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `airline` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `base` decimal(10,3) NOT NULL,
  `supplier_penalty` decimal(10,3) NOT NULL,
  `service_penalty` decimal(10,3) NOT NULL,
  `status` enum('Refunded','Pending','Declined') NOT NULL,
  `receipt` int(11) NOT NULL,
  `remarks` mediumtext NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_date_change_tickets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `date_change_tickets` (`id`,`tenant_id`,`ticket_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`sold`,`base`,`supplier_penalty`,`service_penalty`,`status`,`receipt`,`remarks`,`created_by`,`created_at`,`updated_at`) VALUES ('49','1','337','32','19','13','Mr','KHASTA GUL','86S99L',' KBL','ELQ','0771781576','FZ','Male','2025-10-16','2025-10-18','USD','340.000','330.030','20.000','30.000','','0','Sale for ticket: KHASTA GUL ( KBL to ELQ)','1','2025-10-18 10:21:55','2025-10-18 10:21:55');
INSERT INTO `date_change_tickets` (`id`,`tenant_id`,`ticket_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`sold`,`base`,`supplier_penalty`,`service_penalty`,`status`,`receipt`,`remarks`,`created_by`,`created_at`,`updated_at`) VALUES ('50','1','336','31','19','13','Mr','MUHAMMAD RAUF','5DYFCD',' KBL','HAS','0788808299','FZ','Male','2025-10-16','2025-10-18','USD','375.000','357.030','10.000','20.000','','0','asf','1','2025-10-18 10:24:26','2025-10-18 10:24:26');

DROP TABLE IF EXISTS `date_change_umrah`;
CREATE TABLE `date_change_umrah` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `umrah_booking_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `supplier` int(11) DEFAULT NULL,
  `sold_to` int(11) DEFAULT NULL,
  `paid_to` int(11) DEFAULT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `old_flight_date` date DEFAULT NULL,
  `new_flight_date` date DEFAULT NULL,
  `old_return_date` date DEFAULT NULL,
  `new_return_date` date DEFAULT NULL,
  `old_duration` varchar(50) DEFAULT NULL,
  `new_duration` varchar(50) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT 0.00,
  `new_price` decimal(10,2) DEFAULT 0.00,
  `price_difference` decimal(10,2) DEFAULT 0.00,
  `supplier_penalty` decimal(10,2) DEFAULT 0.00,
  `service_penalty` decimal(10,2) DEFAULT 0.00,
  `total_penalty` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `umrah_booking_id` (`umrah_booking_id`),
  KEY `family_id` (`family_id`),
  KEY `supplier` (`supplier`),
  KEY `sold_to` (`sold_to`),
  KEY `paid_to` (`paid_to`),
  KEY `tenant_id` (`tenant_id`),
  KEY `status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_date_change_umrah_booking` FOREIGN KEY (`umrah_booking_id`) REFERENCES `umrah_bookings` (`booking_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_date_change_umrah_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`family_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_date_change_umrah_paid_to` FOREIGN KEY (`paid_to`) REFERENCES `main_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_date_change_umrah_sold_to` FOREIGN KEY (`sold_to`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_date_change_umrah_supplier` FOREIGN KEY (`supplier`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_date_change_umrah_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `deals`;
CREATE TABLE `deals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) DEFAULT NULL,
  `discount` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_deals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `debt_records`;
CREATE TABLE `debt_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('active','paid','overdue') DEFAULT 'active',
  `notes` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `debt_records_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_debt_records_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `debtor_transactions`;
CREATE TABLE `debtor_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `debtor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `debtor_id` (`debtor_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `debtor_transactions_ibfk_1` FOREIGN KEY (`debtor_id`) REFERENCES `debtors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_debtor_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debtor_transactions` (`id`,`tenant_id`,`debtor_id`,`amount`,`currency`,`transaction_type`,`description`,`reference_number`,`payment_date`,`created_at`) VALUES ('45','1','40','100.00','USD','debit','Initial debt balance for Matiullah','DEBT-20251027115204-40','2025-10-27','2025-10-27 16:22:04');

DROP TABLE IF EXISTS `debtors`;
CREATE TABLE `debtors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `status` enum('active','paid','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `main_account_id` int(11) DEFAULT NULL,
  `agreement_terms` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_debtors_main_account` (`main_account_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_debtors_main_account` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_debtors_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `debtors` (`id`,`tenant_id`,`name`,`email`,`phone`,`address`,`balance`,`currency`,`status`,`created_at`,`updated_at`,`main_account_id`,`agreement_terms`) VALUES ('40','1','Matiullah','admin@abc-construction.com','0777305730','test','100.00','USD','active','2025-10-27 16:22:04','2025-10-27 16:22:04','13','');

DROP TABLE IF EXISTS `demo_requests`;
CREATE TABLE `demo_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company_size` varchar(20) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','contacted','scheduled','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `demo_requests` (`id`,`name`,`email`,`company`,`phone`,`company_size`,`preferred_date`,`preferred_time`,`message`,`status`,`created_at`,`updated_at`) VALUES ('1','KamAir','almuqadas_travel@yahoo.com','MTech','0777305730','11-50','2025-10-04','14:00:00','gsfdgsd','contacted','2025-10-04 10:15:57','2025-10-04 10:36:40');

DROP TABLE IF EXISTS `destinations`;
CREATE TABLE `destinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_description` mediumtext DEFAULT NULL,
  `long_description` mediumtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_destinations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `employee_terminations`;
CREATE TABLE `employee_terminations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `terminated_by` int(11) NOT NULL,
  `termination_reason` text NOT NULL,
  `termination_date` datetime NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `terminated_by` (`terminated_by`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_termination_date` (`termination_date`),
  CONSTRAINT `fk_employee_terminations_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_terminations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_terminations_terminator` FOREIGN KEY (`terminated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `exchange_rates`;
CREATE TABLE `exchange_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL,
  `from_currency` varchar(10) NOT NULL,
  `to_currency` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_currency_pair` (`from_currency`,`to_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `exchange_transactions`;
CREATE TABLE `exchange_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `from_amount` decimal(15,2) NOT NULL,
  `from_currency` varchar(10) NOT NULL,
  `to_amount` decimal(15,2) NOT NULL,
  `to_currency` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `profit_amount` decimal(15,2) DEFAULT NULL,
  `profit_currency` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `exchange_transactions_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sarafi_transactions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_expense_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expense_categories` (`id`,`tenant_id`,`name`,`created_at`) VALUES ('19','1','OFFICE EXPS','2025-09-01 15:47:05');
INSERT INTO `expense_categories` (`id`,`tenant_id`,`name`,`created_at`) VALUES ('22','1','self expenses','2025-10-16 11:40:13');

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `main_account_id` int(20) NOT NULL,
  `date` date NOT NULL,
  `description` mediumtext NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allocation_id` int(11) DEFAULT NULL,
  `receipt_file` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_expenses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expenses` (`id`,`tenant_id`,`category_id`,`main_account_id`,`date`,`description`,`amount`,`currency`,`created_at`,`allocation_id`,`receipt_file`) VALUES ('214','1','22','13','2025-10-16','HOME EPENSES CAR OIL AND GYM FEESS','7300.000','AFS','2025-10-16 11:42:29',NULL,NULL);
INSERT INTO `expenses` (`id`,`tenant_id`,`category_id`,`main_account_id`,`date`,`description`,`amount`,`currency`,`created_at`,`allocation_id`,`receipt_file`) VALUES ('215','1','19','13','2025-10-16','FOR KITCHEN CREATING AND PARTITION IN OFFICE','5050.000','AFS','2025-10-16 11:45:00',NULL,NULL);
INSERT INTO `expenses` (`id`,`tenant_id`,`category_id`,`main_account_id`,`date`,`description`,`amount`,`currency`,`created_at`,`allocation_id`,`receipt_file`) VALUES ('216','1','19','13','2025-11-01','this is for test','400.000','AFS','2025-11-01 11:03:34',NULL,NULL);
INSERT INTO `expenses` (`id`,`tenant_id`,`category_id`,`main_account_id`,`date`,`description`,`amount`,`currency`,`created_at`,`allocation_id`,`receipt_file`) VALUES ('217','1','19','13','2025-11-01','this is for test','100.000','AFS','2025-11-01 11:14:36',NULL,'receipt_6905ac54c7348.png');

DROP TABLE IF EXISTS `families`;
CREATE TABLE `families` (
  `family_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `head_of_family` varchar(100) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `province` varchar(50) NOT NULL,
  `district` varchar(50) NOT NULL,
  `total_members` int(11) DEFAULT NULL,
  `package_type` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `tazmin` varchar(50) DEFAULT NULL,
  `visa_status` enum('Applied','Issued','Not Applied') NOT NULL DEFAULT 'Applied',
  `total_price` decimal(10,2) DEFAULT NULL,
  `total_paid` decimal(10,2) DEFAULT NULL,
  `total_paid_to_bank` decimal(10,2) DEFAULT NULL,
  `total_due` decimal(10,2) DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`family_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_families_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `families` (`family_id`,`tenant_id`,`head_of_family`,`contact`,`address`,`province`,`district`,`total_members`,`package_type`,`location`,`tazmin`,`visa_status`,`total_price`,`total_paid`,`total_paid_to_bank`,`total_due`,`created_by`,`created_at`,`updated_at`) VALUES ('15','1','HAMEED','0786011115','Jada-e-Maiwand','Kabul','dehsabz','1','Full Package','Madina and Makkah','Done','Applied','130.00','10.00','0.00','120.00','0','2025-10-18 14:02:44','2025-10-27 15:47:28');

DROP TABLE IF EXISTS `family_cancellations`;
CREATE TABLE `family_cancellations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_family_cancellations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `funding_transactions`;
CREATE TABLE `funding_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `funded_by` int(10) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_funding_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `funding_transactions_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `general_ledger`;
CREATE TABLE `general_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `account_type` enum('asset','liability','income','expense') NOT NULL,
  `entry_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_general_ledger_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `general_ledger_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sarafi_transactions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `general_ledger` (`id`,`tenant_id`,`transaction_id`,`account_type`,`entry_type`,`amount`,`currency`,`balance`,`created_at`) VALUES ('3','1',NULL,'income','credit','5.00','USD','5.00','2025-09-01 11:21:45');
INSERT INTO `general_ledger` (`id`,`tenant_id`,`transaction_id`,`account_type`,`entry_type`,`amount`,`currency`,`balance`,`created_at`) VALUES ('4','1',NULL,'income','credit','5.00','USD','10.00','2025-09-01 13:08:24');
INSERT INTO `general_ledger` (`id`,`tenant_id`,`transaction_id`,`account_type`,`entry_type`,`amount`,`currency`,`balance`,`created_at`) VALUES ('5','2','34','asset','credit','1000.00','USD','1000.00','2025-09-10 16:32:43');
INSERT INTO `general_ledger` (`id`,`tenant_id`,`transaction_id`,`account_type`,`entry_type`,`amount`,`currency`,`balance`,`created_at`) VALUES ('6','1',NULL,'income','credit','2.00','USD','12.00','2025-09-22 16:10:32');

DROP TABLE IF EXISTS `hawala_transfers`;
CREATE TABLE `hawala_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `sender_transaction_id` int(11) DEFAULT NULL,
  `receiver_transaction_id` int(11) DEFAULT NULL,
  `secret_code` varchar(50) DEFAULT NULL,
  `commission_amount` decimal(15,2) DEFAULT NULL,
  `commission_currency` varchar(10) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_transaction_id` (`sender_transaction_id`),
  KEY `receiver_transaction_id` (`receiver_transaction_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hawala_transfers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hawala_transfers_ibfk_1` FOREIGN KEY (`sender_transaction_id`) REFERENCES `sarafi_transactions` (`id`),
  CONSTRAINT `hawala_transfers_ibfk_2` FOREIGN KEY (`receiver_transaction_id`) REFERENCES `sarafi_transactions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `hotel_bookings`;
CREATE TABLE `hotel_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` enum('Mr','Mrs','Child') DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `order_id` varchar(50) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `accommodation_details` mediumtext DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `sold_to` varchar(100) DEFAULT NULL,
  `paid_to` int(100) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `base_amount` decimal(10,3) DEFAULT NULL,
  `sold_amount` decimal(10,3) DEFAULT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `currency` enum('USD','AFS') DEFAULT NULL,
  `remarks` mediumtext DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','refunded') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_order_id` (`tenant_id`,`order_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hotel_bookings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hotel_bookings` (`id`,`tenant_id`,`title`,`first_name`,`last_name`,`gender`,`order_id`,`check_in_date`,`check_out_date`,`accommodation_details`,`issue_date`,`supplier_id`,`sold_to`,`paid_to`,`contact_no`,`base_amount`,`sold_amount`,`profit`,`currency`,`remarks`,`receipt`,`created_by`,`created_at`,`updated_at`,`status`) VALUES ('47','1','Mr','EDRISS ','NOOR','Male','1089734','2025-10-18','2025-10-24','sfdsa','2025-10-18','32','19','13','766666232','15.000','25.000','0.000','USD','sadf','','1','2025-10-18 10:26:12','2025-10-27 15:46:33','refunded');

DROP TABLE IF EXISTS `hotel_refunds`;
CREATE TABLE `hotel_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `refund_type` enum('full','partial') NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `processed_by` (`processed_by`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hotel_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_refunds_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_refunds_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hotel_refunds` (`id`,`tenant_id`,`booking_id`,`refund_type`,`refund_amount`,`reason`,`currency`,`exchange_rate`,`processed`,`processed_by`,`transaction_id`,`created_at`,`updated_at`) VALUES ('15','1','47','full','25.00','test','USD','1.0000','0',NULL,NULL,'2025-10-27 15:46:33','2025-10-27 15:46:33');

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `type` enum('ticket','refund_ticket','date_change_ticket','visa') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `main_account_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `invoice_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_invoice_number` (`tenant_id`,`invoice_number`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `jv_payments`;
CREATE TABLE `jv_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `jv_name` varchar(255) NOT NULL,
  `exchange_rate` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `total_amount` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `receipt` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_jv_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jv_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `jv_transactions`;
CREATE TABLE `jv_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `jv_payment_id` int(11) NOT NULL,
  `transaction_type` varchar(100) NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `description` text DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `jv_transactions_ibfk_1` (`jv_payment_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_jv_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jv_transactions_ibfk_1` FOREIGN KEY (`jv_payment_id`) REFERENCES `jv_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `time` datetime NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('login','logout') DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_login_history_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `login_history` (`id`,`tenant_id`,`user_id`,`action`,`action_time`) VALUES ('116','1','1','logout','2025-10-21 13:58:31');
INSERT INTO `login_history` (`id`,`tenant_id`,`user_id`,`action`,`action_time`) VALUES ('117','1','1','logout','2025-10-29 16:29:03');
INSERT INTO `login_history` (`id`,`tenant_id`,`user_id`,`action`,`action_time`) VALUES ('118','1','1','logout','2025-11-01 14:24:02');

DROP TABLE IF EXISTS `main_account`;
CREATE TABLE `main_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `account_type` enum('internal','bank') NOT NULL DEFAULT 'internal',
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_account_afs_number` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `usd_balance` decimal(15,3) NOT NULL DEFAULT 0.000,
  `afs_balance` decimal(15,3) NOT NULL DEFAULT 0.000,
  `euro_balance` decimal(10,3) NOT NULL,
  `darham_balance` decimal(10,3) NOT NULL,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_main_account_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `main_account` (`id`,`tenant_id`,`name`,`account_type`,`bank_account_number`,`bank_account_afs_number`,`bank_name`,`usd_balance`,`afs_balance`,`euro_balance`,`darham_balance`,`last_updated`,`status`) VALUES ('13','1','SELF BANK (SAFE)','internal',NULL,NULL,NULL,'4513.000','233838.000','680.000','505.000','2025-11-01 11:14:36','active');
INSERT INTO `main_account` (`id`,`tenant_id`,`name`,`account_type`,`bank_account_number`,`bank_account_afs_number`,`bank_name`,`usd_balance`,`afs_balance`,`euro_balance`,`darham_balance`,`last_updated`,`status`) VALUES ('14','1','AZIZI BANK','bank',NULL,'143241234','AZIZI','100.000','0.000','0.000','0.000','2025-10-18 13:13:54','active');
INSERT INTO `main_account` (`id`,`tenant_id`,`name`,`account_type`,`bank_account_number`,`bank_account_afs_number`,`bank_name`,`usd_balance`,`afs_balance`,`euro_balance`,`darham_balance`,`last_updated`,`status`) VALUES ('16','1','AFGHAN UNITED BANK','bank','23451534','3254524afs453','AUB','0.000','0.000','0.000','0.000','2025-10-15 15:21:17','active');

DROP TABLE IF EXISTS `main_account_transactions`;
CREATE TABLE `main_account_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS','EUR','DARHAM') NOT NULL,
  `description` mediumtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','expense','debtor','supplier_fund','client_fund','budget_allocation','ticket_reserve','transfer','additional_payment','creditor','jv_payment','salary_payment','visa_refund','hotel_refund','deposit_sarafi','hawala_sarafi','withdrawal_sarafi','umrah_refund','weight','supplier_fund_withdrawal') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`main_account_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_main_account_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1190','1','13','credit','4008.000','4008.000','USD','Account funded by Sabaoon. Remarks: test. Receipt: 1','2025-10-15 15:22:26','fund','1','1',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1191','1','13','credit','202360.000','202360.000','AFS','Account funded by Sabaoon. Remarks: test. Receipt: 2','2025-10-15 15:22:51','fund','1','2',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1192','1','13','credit','680.000','680.000','EUR','Account funded by Sabaoon. Remarks: test. Receipt: 3','2025-10-15 15:23:12','fund','1','3',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1193','1','13','credit','505.000','505.000','DARHAM','Account funded by Sabaoon. Remarks: test. Receipt: 4','2025-10-15 15:23:29','fund','1','4',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1197','1','13','credit','23800.000','226160.000','AFS','PAID BY MR MATIULLAH 330*72.12=23800','2025-10-16 11:34:32','ticket_sale','337',NULL,'72.11000');
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1198','1','13','debit','7300.000','218860.000','AFS','HOME EPENSES CAR OIL AND GYM FEESS','2025-10-16 11:42:29','expense','214','',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1199','1','13','debit','5050.000','213810.000','AFS','FOR KITCHEN CREATING AND PARTITION IN OFFICE','2025-10-16 11:45:00','expense','215','',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1201','1','13','credit','20550.000','234360.000','AFS','CASH paid by mr matiullah','2025-10-16 11:54:49','ticket_sale','338',NULL,'72.80000');
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1205','1','13','credit','100.000','4108.000','USD','Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: tet','2025-10-16 16:47:17','supplier_fund_withdrawal','916','2452345',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1210','1','14','credit','100.000','100.000','USD','teatr','2025-10-18 13:13:54','transfer','13',NULL,NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1221','1','13','credit','10.000','4118.000','USD','test','2025-10-20 09:47:55','additional_payment','50',NULL,'0.00000');
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1230','1','13','credit','100.000','4218.000','USD','','2025-10-20 10:12:33','deposit_sarafi','42','DEP68f5cbbe4c5e7',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1234','1','13','credit','100.000','4318.000','USD','','2025-10-20 10:23:07','deposit_sarafi','44','DEP68f5ce3620d86',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1237','1','13','credit','100.000','4418.000','USD','','2025-10-20 10:27:43','deposit_sarafi','46','DEP68f5cf4cee5da',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1240','1','13','credit','100.000','4518.000','USD','','2025-10-20 10:32:22','deposit_sarafi','47','DEP68f5d063b5ce6',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1241','1','13','debit','100.000','4418.000','USD','Supplier: ALAIN T, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: test','2025-10-20 14:44:17','supplier_fund','936','2452345',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1242','1','13','credit','100.000','4518.000','USD','Client: DR SAHIBs, Received 100 USD for client account funding, processed by: Sabaoon, Remarks: ','2025-10-20 14:44:54','client_fund','584','2452345',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1243','1','13','debit','10.000','4508.000','USD','test','2025-10-27 15:37:25','ticket_refund','101',NULL,'0.00000');
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1244','1','13','credit','10.000','4518.000','USD','tets','2025-10-27 15:38:18','date_change','49',NULL,'0.00000');
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1245','1','13','credit','10.000','4528.000','USD','test','2025-10-27 15:40:00','weight','17',NULL,NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1246','1','13','credit','10.000','4538.000','USD','test','2025-10-27 15:44:18','hotel','47',NULL,NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1247','1','13','debit','25.000','4513.000','USD','Refund payment for Hotel Booking #47 - Mr EDRISS  NOOR','2025-10-27 15:46:36','hotel_refund','15',NULL,NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1248','1','13','credit','10.000','4523.000','USD','test','2025-10-27 15:47:28','umrah','96','',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1249','1','13','credit','10.000','4533.000','USD','test','2025-10-27 15:51:27','visa_sale','60',NULL,'0.00000');
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1250','1','13','debit','100.000','4433.000','USD','Initial debt balance for Matiullah','2025-10-27 16:22:04','debtor','45','DEBT-20251027115204-40',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1251','1','13','credit','100.000','4533.000','USD','Initial credit balance for creditor: Matiullah','2025-10-27 16:29:51','creditor','15',NULL,NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1252','1','13','debit','20.000','4513.000','USD','tete','2025-10-27 16:30:06','creditor','19',NULL,NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1253','1','13','debit','400.000','233938.000','AFS','this is for test','2025-11-01 11:03:34','expense','216','31531',NULL);
INSERT INTO `main_account_transactions` (`id`,`tenant_id`,`main_account_id`,`type`,`amount`,`balance`,`currency`,`description`,`created_at`,`transaction_of`,`reference_id`,`receipt`,`exchange_rate`) VALUES ('1254','1','13','debit','100.000','233838.000','AFS','this is for test','2025-11-01 11:14:36','expense','217','adfasdf',NULL);

DROP TABLE IF EXISTS `maktobs`;
CREATE TABLE `maktobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `maktob_number` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `maktob_date` date NOT NULL,
  `sender_id` int(11) NOT NULL,
  `status` enum('draft','sent') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(10) DEFAULT 'english',
  `file_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_maktobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maktobs_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `message_reactions`;
CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`emoji`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `message_reactions` (`id`,`message_id`,`user_id`,`emoji`,`tenant_id`,`created_at`) VALUES ('1','28','1','👍','1','2025-10-28 16:48:22');
INSERT INTO `message_reactions` (`id`,`message_id`,`user_id`,`emoji`,`tenant_id`,`created_at`) VALUES ('4','26','1','❤️','1','2025-10-28 17:00:02');
INSERT INTO `message_reactions` (`id`,`message_id`,`user_id`,`emoji`,`tenant_id`,`created_at`) VALUES ('6','26','1','😂','1','2025-10-29 14:53:02');
INSERT INTO `message_reactions` (`id`,`message_id`,`user_id`,`emoji`,`tenant_id`,`created_at`) VALUES ('7','25','1','👍','1','2025-10-29 14:53:06');

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_type` enum('all','users','clients','individual') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_table` enum('users','clients') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_messages_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `transaction_type` enum('visa','supplier','ticket_date_change','ticket_refund','umrah','hotel','hotel_refund','ticket_sale','ticket_reserve','additional_payment','debtor','creditor','deposit_sarafi','hawala_sarafi','withdrawal_sarafi','supplier_fund','client_fund','weight','expense','expense_update','expense_delete','supplier_bonus','umrah_refund','hotel_refund','visa_refund','supplier_fund_withdrawal','mtravels') NOT NULL DEFAULT 'supplier',
  `message` mediumtext NOT NULL,
  `recipient_role` enum('Admin','Sales','Finance') NOT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1050 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1002','1','1196','ticket_sale','New payment received for ticket booking #86S99L - Mr KHASTA GUL: Amount AFS 23800.00','Admin','Unread','2025-10-15 16:43:22');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1003','1','1197','ticket_sale','New payment received for ticket booking #86S99L - Mr KHASTA GUL: Amount AFS 23800.00','Admin','Unread','2025-10-16 11:36:02');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1004','1','1198','expense','New expense added for category self expenses: Amount AFS 7300.00 - HOME EPENSES CAR OIL AND GYM FEESS','Admin','Unread','2025-10-16 11:42:29');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1005','1','1199','expense','New expense added for category OFFICE EXPS: Amount AFS 5050.00 - FOR KITCHEN CREATING AND PARTITION IN OFFICE','Admin','Unread','2025-10-16 11:45:00');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1006','1','911','supplier_fund','Supplier: NSTTRIP, Funded 1000 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: ','Admin','Unread','2025-10-16 11:50:03');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1007','1','1201','ticket_sale','New payment received for ticket booking #T6EXHA - Mr BAIKHT BAIDAR BAKHT JEHAN: Amount AFS 20550.00','Admin','Unread','2025-10-16 11:55:23');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1008','1','568','client_fund','Client: MINA Khan, Paid 431 USD for client account funding, processed by: Sabaoon, Remarks: test','Admin','Unread','2025-10-16 16:17:31');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1009','1','913','supplier_fund','Supplier: NSTTRIP, Funded 1000 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: fafsd','Admin','Unread','2025-10-16 16:27:05');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1010','1','914','supplier_fund','Supplier: NSTTRIP, Funded 1000 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: asdf','Admin','Unread','2025-10-16 16:38:19');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1011','1','915','supplier_bonus','Bonus of 100 USD added to supplier: KamAir, processed by: Sabaoon, Remarks: adfasd','Admin','','2025-10-16 16:44:27');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1012','1','916','supplier_fund_withdrawal','Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: tet','Admin','Unread','2025-10-16 16:47:17');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1013','1','917','supplier_fund_withdrawal','Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: dsafasdf','Admin','Unread','2025-10-16 17:04:22');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1014','1','1211','additional_payment','New additional payment received: Amount USD 10.00 - test','Admin','Unread','2025-10-18 15:24:44');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1015','1','1213','debtor','Payment received from debtor: Amount USD 200.00 - test','Admin','Unread','2025-10-18 16:25:36');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1016','1','1215','creditor','Payment made to creditor:  - Amount USD 100.00','Admin','Unread','2025-10-18 16:37:48');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1017','1','1217','debtor','Payment received from debtor: Amount USD 200.00 - adfs','Admin','Unread','2025-10-20 08:26:04');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1018','1','1218','deposit_sarafi','New deposit from Matiullah: USD 100 - Reference: DEP68f5b77209901','Admin','Unread','2025-10-20 08:46:18');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1019','1','1219','additional_payment','New additional payment received: Amount USD 10.00 - fdsaf','Admin','Unread','2025-10-20 09:17:03');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1020','1','1220','additional_payment','New additional payment received: Amount USD 10.00 - test','Admin','Unread','2025-10-20 09:18:56');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1021','1','1221','additional_payment','New additional payment received: Amount USD 10.00 - test','Admin','Unread','2025-10-20 09:48:00');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1022','1','1223','debtor','Payment received from debtor: Amount USD 100.00 - test','Admin','Unread','2025-10-20 09:49:40');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1023','1','1225','creditor','Payment made to creditor:  - Amount USD 100.00','Admin','Unread','2025-10-20 09:51:22');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1024','1','1226','deposit_sarafi','New deposit from Matiullah: USD 100 - Reference: DEP68f5c6f3bbf75','Admin','Unread','2025-10-20 09:52:08');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1025','1','1227','withdrawal_sarafi','New withdrawal by Matiullah: USD 50 - Reference: WDR68f5c701054ce','Admin','Unread','2025-10-20 09:52:36');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1026','1','1229','debtor','Payment received from debtor: Amount USD 100.00 - test','Admin','Unread','2025-10-20 10:12:02');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1027','1','1230','deposit_sarafi','New deposit from Matiullah: USD 100 - Reference: DEP68f5cbbe4c5e7','Admin','Unread','2025-10-20 10:12:33');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1028','1','1231','withdrawal_sarafi','New withdrawal by Matiullah: USD 50 - Reference: WDR68f5cbca1089e','Admin','Unread','2025-10-20 10:12:45');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1029','1','1233','debtor','Payment received from debtor: Amount USD 100.00 - test','Admin','Unread','2025-10-20 10:22:39');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1030','1','1234','deposit_sarafi','New deposit from Matiullah: USD 100 - Reference: DEP68f5ce3620d86','Admin','Unread','2025-10-20 10:23:07');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1031','1','1235','withdrawal_sarafi','New withdrawal by Matiullah: USD 50 - Reference: WDR68f5ce43b09bc','Admin','Unread','2025-10-20 10:23:19');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1032','1','1237','deposit_sarafi','New deposit from Matiullah: USD 100 - Reference: DEP68f5cf4cee5da','Admin','Unread','2025-10-20 10:27:43');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1033','1','1239','creditor','Payment made to creditor:  - Amount USD 100.00','Admin','Unread','2025-10-20 10:32:08');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1034','1','1240','deposit_sarafi','New deposit from Matiullah: USD 100 - Reference: DEP68f5d063b5ce6','Admin','Unread','2025-10-20 10:32:22');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1035','1','936','supplier_fund','Supplier: ALAIN T, Funded 100 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: test','Admin','Unread','2025-10-20 14:44:17');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1036','1','584','client_fund','Client: DR SAHIBs, Paid 100 USD for client account funding, processed by: Sabaoon, Remarks: ','Admin','Unread','2025-10-20 14:44:54');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1037','1','1243','ticket_refund','Refund payment for Agency client ticket #5DYFCD - Mr MUHAMMAD RAUF: Amount USD 10.00','Admin','Unread','2025-10-27 15:37:56');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1038','1','1244','ticket_date_change','New payment received for date change #86S99L - Mr KHASTA GUL: Amount USD 10.00','Admin','Unread','2025-10-27 15:38:26');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1039','1','1245','weight','New payment received for weight charge #5DYFCD - Mr MUHAMMAD RAUF: Amount USD 10.00','Admin','Unread','2025-10-27 15:40:15');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1040','1','1246','hotel','New payment received for hotel booking #1089734 - Mr EDRISS  NOOR: Amount USD 10.00','Admin','Unread','2025-10-27 15:44:18');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1041','1','1247','hotel_refund','Hotel refund payment for Agency client - EDRISS  (NOOR) Amount USD 25.00','Admin','Unread','2025-10-27 15:46:48');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1042','1','96','umrah','Customer: Matiullah has paid: 10 USD to Internal Account processed by SABAOON CAR REPAIR for the Umrah booking.','Admin','Unread','2025-10-27 15:47:28');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1043','1','1249','visa','New payment received for visa application #60 - BAKHTIAR STANIKZAI: Amount USD 10.00','Admin','Unread','2025-10-27 15:51:37');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1044','1','1252','creditor','Payment made to creditor:  - Amount USD 20.00','Admin','Unread','2025-10-27 16:30:06');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1045','1','1253','expense','New expense added for category OFFICE EXPS: Amount AFS 100.00 - this is for test','Admin','Unread','2025-11-01 11:03:34');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1046','1','1253','expense_update','Expense updated for category OFFICE EXPS: Amount AFS 200.00 - this is for test','Admin','Read','2025-11-01 11:04:11');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1047','1','1253','expense','New expense added for category OFFICE EXPS: Amount AFS 200.00 - this is for test','Admin','Read','2025-11-01 11:04:11');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1048','1','1253','expense','New expense added for category OFFICE EXPS: Amount AFS 300.00 - this is for test','Admin','Read','2025-11-01 11:10:58');
INSERT INTO `notifications` (`id`,`tenant_id`,`transaction_id`,`transaction_type`,`message`,`recipient_role`,`status`,`created_at`) VALUES ('1049','1','1254','expense','New expense added for category OFFICE EXPS: Amount AFS 100.00 - this is for test','Admin','Unread','2025-11-01 11:14:36');

DROP TABLE IF EXISTS `payment_sessions`;
CREATE TABLE `payment_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'AFN',
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_subscription_tenant` (`subscription_id`,`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `payroll_details`;
CREATE TABLE `payroll_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `bonus` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_deduction` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(15,2) NOT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_user` (`payroll_id`,`user_id`),
  UNIQUE KEY `tenant_payroll_user` (`tenant_id`,`payroll_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_payroll_details_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_details_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_details_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payroll_records`;
CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `pay_period` varchar(20) NOT NULL COMMENT 'Format: YYYY-MM',
  `generated_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `status` enum('draft','processed','paid') NOT NULL DEFAULT 'draft',
  `generated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_pay_period` (`tenant_id`,`pay_period`,`currency`),
  KEY `generated_by` (`generated_by`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_payroll_records_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `performance_reviews`;
CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_date` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `overall_rating` decimal(3,2) DEFAULT NULL COMMENT 'Rating from 1.00 to 5.00',
  `comments` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `areas_for_improvement` text DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_period` (`period_start`,`period_end`),
  KEY `fk_performance_reviews_tenant` (`tenant_id`),
  CONSTRAINT `fk_performance_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_performance_reviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_performance_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`features`)),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users` int(11) NOT NULL DEFAULT 0,
  `trial_days` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plans` (`id`,`name`,`description`,`features`,`price`,`max_users`,`trial_days`,`status`,`created_at`,`updated_at`) VALUES ('1','basic','Basic plan with access to ticket-related tasks only','[
    "ticket_bookings",
    "ticket_reservations",
    "refunded_tickets",
    "date_change_tickets",
    "ticket_weights"
  ]','200.00','20','10','active','2025-08-23 15:58:15','2025-08-30 12:10:19');
INSERT INTO `plans` (`id`,`name`,`description`,`features`,`price`,`max_users`,`trial_days`,`status`,`created_at`,`updated_at`) VALUES ('2','pro','Pro plan with ticket-related tasks, visa-related tasks, and inter-tenant chat','[
    "ticket_bookings",
    "ticket_reservations",
    "refunded_tickets",
    "date_change_tickets",
    "ticket_weights",
    "visa_applications",
    "visa_refunds",
    "visa_transactions",
    "inter_tenant_chat"
  ]','0.00','0','0','active','2025-08-23 15:58:15','2025-08-23 15:58:15');
INSERT INTO `plans` (`id`,`name`,`description`,`features`,`price`,`max_users`,`trial_days`,`status`,`created_at`,`updated_at`) VALUES ('3','enterprise','Enterprise plan with all Pro features plus Umrah management','[
    "ticket_bookings",
    "ticket_reservations", 
    "refunded_tickets",
    "date_change_tickets",
    "ticket_weights",
    "hotel_bookings",
    "hotel_refunds",
    "visa_applications",
    "visa_refunds",
    "visa_transactions", 
    "inter_tenant_chat",
    "umrah_bookings",
    "umrah_refunds",
    "debtors",
    "creditors",
    "sarafi",
    "salary",
    "additional_payments",
    "jv_payments",
    "manage_maktobs",
    "assets",
    "financial_statements",
    "expense_management"
]','0.00','0','0','active','2025-08-23 15:58:15','2025-09-03 10:45:43');

DROP TABLE IF EXISTS `platform_settings`;
CREATE TABLE `platform_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('1','primary_color','#b7c5f0','string',NULL,'2025-08-04 11:04:16','2025-08-04 11:04:16');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('2','secondary_color','#858796','string',NULL,'2025-08-04 11:04:16','2025-08-04 11:04:16');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('3','accent_color','#1cc88a','string',NULL,'2025-08-04 11:04:16','2025-08-04 11:04:16');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('4','sidebar_style','compact','string',NULL,'2025-08-04 11:04:16','2025-08-12 12:29:01');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('5','theme_mode','light','string',NULL,'2025-08-04 11:04:16','2025-08-12 11:44:38');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('21','platform_name','MTravels','string',NULL,'2025-08-12 11:34:53','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('22','platform_description','Professional travel agency management platform designed to optimize workflows, enhance customer service, and drive business growth through comprehensive automation and intelligent insights.','string',NULL,'2025-08-12 11:34:53','2025-10-29 13:01:35');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('23','contact_email','allahdadmuhammadi01@gmail.com','string',NULL,'2025-08-12 11:34:53','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('24','support_phone','+93780310431','string',NULL,'2025-08-12 11:34:53','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('25','website_url','https://mtravels.com','string',NULL,'2025-08-12 11:34:53','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('31','platform_logo','logo_1759405927_68de67675a380.png','string',NULL,'2025-08-12 11:43:25','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('42','platform_favicon','logo_1756212047_68adab4f626b7.png','string',NULL,'2025-08-12 11:55:25','2025-08-27 16:31:37');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('43','contact_address','Kabul,Afghanistan','string',NULL,'2025-08-12 12:01:12','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('44','contact_phone','0780310431','string',NULL,'2025-08-12 12:01:12','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('46','contact_website','https://construct360.com','string',NULL,'2025-08-12 12:01:12','2025-08-13 03:47:29');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('47','contact_facebook','https://mtravels.com','string',NULL,'2025-08-12 12:01:12','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('48','contact_twitter','https://mtravels.com','string',NULL,'2025-08-12 12:01:12','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('49','contact_linkedin','https://mtravels.com','string',NULL,'2025-08-12 12:01:12','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('50','contact_instagram','https://mtravels.com','string',NULL,'2025-08-12 12:01:12','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('84','email_notifications','1','string',NULL,'2025-08-13 06:16:05','2025-08-13 06:16:05');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('85','sms_notifications','0','string',NULL,'2025-08-13 06:16:05','2025-08-13 06:16:05');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('86','push_notifications','1','string',NULL,'2025-08-13 06:16:05','2025-08-13 06:16:05');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('87','notification_sound','1','string',NULL,'2025-08-13 06:16:05','2025-08-13 06:16:05');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('88','vapid_subject','mailto:allahdadmuhammadi01@gmail.com','string',NULL,'2025-08-13 10:10:16','2025-08-13 10:10:16');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('89','vapid_public_key','BPrcke06b6zEa_k2loCLVatIG83YOjNByloONBeeFzC4c4tlwW4ww3a9JoX5dp58dLekgAoOn2FtS9sPZKxbjjA','string',NULL,'2025-08-13 10:10:16','2025-08-13 10:10:16');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('90','vapid_private_key','XvR9FAF5YCNFbtJcLkJkBkQIYZ6bULJiGoPRsjV56UI','string',NULL,'2025-08-13 10:10:16','2025-08-13 10:10:16');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('91','agency_name','MTravels','string','Platform agency name','2025-08-26 17:02:02','2025-08-30 12:26:11');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('92','default_currency','AFN','string','Default currency for new tenants','2025-08-26 17:02:02','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('93','support_email','allahdadmuhammadi01@gmail.com','string','Contact email for platform support','2025-08-26 17:02:02','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('94','api_enabled','false','boolean','Whether API access is enabled globally','2025-08-26 17:02:02','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('95','max_users_per_tenant','20','integer','Maximum users allowed per tenant on basic plan','2025-08-26 17:02:02','2025-10-02 16:22:07');
INSERT INTO `platform_settings` (`id`,`key`,`value`,`type`,`description`,`created_at`,`updated_at`) VALUES ('96','logo','logo_1756211874_68adaaa2805b8.png','string','Platform logo file name','2025-08-26 17:02:02','2025-08-26 17:07:54');

DROP TABLE IF EXISTS `refunded_tickets`;
CREATE TABLE `refunded_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(255) NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `airline` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `base` decimal(10,3) NOT NULL,
  `supplier_penalty` decimal(10,3) NOT NULL,
  `service_penalty` decimal(10,3) NOT NULL,
  `refund_to_passenger` decimal(10,3) NOT NULL,
  `status` enum('Refunded','Paid','Declined') NOT NULL,
  `receipt` varchar(100) NOT NULL,
  `remarks` mediumtext NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `calculation_method` varchar(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_refunded_tickets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `refunded_tickets` (`id`,`tenant_id`,`ticket_id`,`paid_to`,`sold_to`,`supplier`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`sold`,`base`,`supplier_penalty`,`service_penalty`,`refund_to_passenger`,`status`,`receipt`,`remarks`,`created_by`,`created_at`,`updated_at`,`calculation_method`) VALUES ('99','1','339','13','22','35','Mr','HAJI MUSA GUL MINAH KHAN','181QHU',' KBL','JED','0773513830','KM','Male','2025-10-16','2025-10-17','USD','431.000','403.000','20.000','30.000','353.000','Refunded','','test','1','2025-10-18 10:19:21','2025-10-18 10:19:21','base');
INSERT INTO `refunded_tickets` (`id`,`tenant_id`,`ticket_id`,`paid_to`,`sold_to`,`supplier`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`sold`,`base`,`supplier_penalty`,`service_penalty`,`refund_to_passenger`,`status`,`receipt`,`remarks`,`created_by`,`created_at`,`updated_at`,`calculation_method`) VALUES ('100','1','338','13','19','32','Mr','BAIKHT BAIDAR BAKHT JEHAN','T6EXHA',' KBL','ELQ','0766202122','FZ','Male','2025-10-16','2025-10-25','USD','330.000','320.030','20.000','30.000','270.030','Refunded','','test','1','2025-10-18 10:20:52','2025-10-18 10:20:52','base');
INSERT INTO `refunded_tickets` (`id`,`tenant_id`,`ticket_id`,`paid_to`,`sold_to`,`supplier`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`sold`,`base`,`supplier_penalty`,`service_penalty`,`refund_to_passenger`,`status`,`receipt`,`remarks`,`created_by`,`created_at`,`updated_at`,`calculation_method`) VALUES ('101','1','336','13','19','31','Mr','MUHAMMAD RAUF','5DYFCD',' KBL','HAS','0788808299','FZ','Male','2025-10-16','2025-10-18','USD','375.000','357.030','20.000','30.000','325.000','','','test','1','2025-10-18 10:23:58','2025-10-18 10:23:58','sold');

DROP TABLE IF EXISTS `salary_adjustments`;
CREATE TABLE `salary_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `adjustment_type` enum('increment','decrement') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL COMMENT 'If adjustment is percentage based',
  `effective_date` date NOT NULL,
  `previous_salary` decimal(15,2) NOT NULL,
  `new_salary` decimal(15,2) NOT NULL,
  `reason` mediumtext NOT NULL,
  `approved_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `approved_by` (`approved_by`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_salary_adjustments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_adjustments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_adjustments_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `salary_adjustments` (`id`,`tenant_id`,`user_id`,`adjustment_type`,`amount`,`percentage`,`effective_date`,`previous_salary`,`new_salary`,`reason`,`approved_by`,`created_at`) VALUES ('2','1','6','increment','1000.00','0.00','2025-10-20','10000.00','11000.00','test','1','2025-10-20 09:13:01');

DROP TABLE IF EXISTS `salary_advances`;
CREATE TABLE `salary_advances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `advance_date` date NOT NULL,
  `repayment_status` enum('pending','partially_paid','paid') NOT NULL DEFAULT 'pending',
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` mediumtext DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `main_account_id` (`main_account_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_salary_advances_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_advances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_advances_ibfk_2` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `salary_bonuses`;
CREATE TABLE `salary_bonuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` mediumtext NOT NULL,
  `bonus_date` date NOT NULL,
  `type` enum('performance','holiday','other') NOT NULL DEFAULT 'other',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_salary_bonuses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_bonuses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `salary_bonuses` (`id`,`tenant_id`,`user_id`,`amount`,`description`,`bonus_date`,`type`,`created_by`,`created_at`) VALUES ('2','1','6','1000.00','test','2025-10-20','performance','1','2025-10-20 09:13:46');

DROP TABLE IF EXISTS `salary_deductions`;
CREATE TABLE `salary_deductions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` mediumtext NOT NULL,
  `deduction_date` date NOT NULL,
  `type` enum('absence','penalty','tax','other') NOT NULL DEFAULT 'other',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_salary_deductions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_deductions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `salary_management`;
CREATE TABLE `salary_management` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `base_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` enum('USD','AFS') NOT NULL DEFAULT 'USD',
  `joining_date` date NOT NULL,
  `payment_day` int(2) NOT NULL DEFAULT 1 COMMENT 'Day of month when salary is paid',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_user_id` (`tenant_id`,`user_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_salary_management_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_management_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `salary_management` (`id`,`tenant_id`,`user_id`,`base_salary`,`currency`,`joining_date`,`payment_day`,`status`,`created_at`,`updated_at`) VALUES ('9','1','6','11000.00','AFS','2025-10-20','28','active','2025-10-20 09:12:34','2025-10-20 09:13:01');

DROP TABLE IF EXISTS `salary_payments`;
CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `payment_date` date NOT NULL,
  `payment_for_month` date NOT NULL COMMENT 'First day of the month this payment is for',
  `payment_type` enum('regular','bonus','advance','other') NOT NULL DEFAULT 'regular',
  `description` mediumtext DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `main_account_id` (`main_account_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_salary_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_payments_ibfk_2` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `sarafi_transactions`;
CREATE TABLE `sarafi_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `type` enum('deposit','withdrawal','hawala_send','hawala_receive','exchange','adjustment') NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `notes` mediumtext DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sarafi_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sarafi_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sarafi_transactions` (`id`,`tenant_id`,`customer_id`,`amount`,`currency`,`type`,`status`,`notes`,`reference_number`,`receipt_path`,`created_at`,`updated_at`) VALUES ('42','1','5','100.00','USD','deposit','completed','','DEP68f5cbbe4c5e7',NULL,'2025-10-20 10:12:33','2025-10-20 10:12:33');
INSERT INTO `sarafi_transactions` (`id`,`tenant_id`,`customer_id`,`amount`,`currency`,`type`,`status`,`notes`,`reference_number`,`receipt_path`,`created_at`,`updated_at`) VALUES ('44','1','5','100.00','USD','deposit','completed','','DEP68f5ce3620d86',NULL,'2025-10-20 10:23:07','2025-10-20 10:23:07');
INSERT INTO `sarafi_transactions` (`id`,`tenant_id`,`customer_id`,`amount`,`currency`,`type`,`status`,`notes`,`reference_number`,`receipt_path`,`created_at`,`updated_at`) VALUES ('46','1','5','100.00','USD','deposit','completed','','DEP68f5cf4cee5da',NULL,'2025-10-20 10:27:43','2025-10-20 10:27:43');
INSERT INTO `sarafi_transactions` (`id`,`tenant_id`,`customer_id`,`amount`,`currency`,`type`,`status`,`notes`,`reference_number`,`receipt_path`,`created_at`,`updated_at`) VALUES ('47','1','5','100.00','USD','deposit','completed','','DEP68f5d063b5ce6',NULL,'2025-10-20 10:32:22','2025-10-20 10:32:22');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `agency_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `address` mediumtext NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`,`tenant_id`,`agency_name`,`title`,`phone`,`email`,`address`,`logo`,`created_at`,`updated_at`) VALUES ('1','1','Al Moqadas','Al Moqadas Travel Agency','0786011115','almuqadas_travel@yahoo.com','Jada-e-Maiwand, KABUL , AFGHANISTAN','logo.png','2025-01-18 09:13:58','2025-08-26 12:19:36');
INSERT INTO `settings` (`id`,`tenant_id`,`agency_name`,`title`,`phone`,`email`,`address`,`logo`,`created_at`,`updated_at`) VALUES ('2','2','Al Wali','Al Wali Travel','0786011115','alwali@gmail.com','kabul','file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png','2025-09-04 17:04:02','2025-09-07 13:49:45');

DROP TABLE IF EXISTS `subscription_payments`;
CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `fk_subscription_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `tenant_subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscription_payments_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('1','1','0.00','AFS','2025-09-17','Cash','34213','2452345','adfaf','14','2025-09-17 09:16:57','2025-09-17 09:16:57');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('2','1','0.00','AFS','2025-09-17','Cash','34213','2452345','adfaf','14','2025-09-17 09:17:33','2025-09-17 09:17:33');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('3','2','1000.00','AFS','2025-09-17','Cash','gdfg','2452345','agff','14','2025-09-17 11:17:33','2025-09-17 11:17:33');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('4','1','0.00','AFS','2025-09-17','Cash','sgf','2452345','sfgsdf','14','2025-09-17 11:20:55','2025-09-17 11:20:55');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('5','1','50.00','AFN','2025-09-18','Hesabpay',NULL,'hesabpay_68cbd15976adc',NULL,'1','2025-09-18 14:01:05','2025-09-18 14:01:05');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('6','1','50.00','AFN','2025-09-18','Hesabpay',NULL,'hesabpay_68cbd15d22905',NULL,'1','2025-09-18 14:01:09','2025-09-18 14:01:09');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('7','1','10.00','AFN','2025-10-20','Hesabpay',NULL,NULL,NULL,'1','2025-10-20 11:01:16','2025-10-20 11:01:16');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('8','1','10.00','AFN','2025-10-20','Hesabpay',NULL,NULL,NULL,'1','2025-10-20 11:35:01','2025-10-20 11:35:01');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('9','1','10.00','AFN','2025-10-20','Hesabpay',NULL,NULL,NULL,'1','2025-10-20 11:38:18','2025-10-20 11:38:18');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('10','1','10.00','AFN','2025-10-20','Hesabpay',NULL,NULL,NULL,'1','2025-10-20 11:45:26','2025-10-20 11:45:26');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('11','1','10.00','AFN','2025-10-20','HesabPay',NULL,NULL,NULL,'1','2025-10-20 12:15:49','2025-10-20 12:15:49');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('12','1','10.00','AFN','2025-10-20','HesabPay',NULL,NULL,NULL,'1','2025-10-20 12:26:50','2025-10-20 12:26:50');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('13','1','10.00','AFN','2025-10-23','HesabPay',NULL,NULL,NULL,'1','2025-10-23 11:16:01','2025-10-23 11:16:01');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('14','1','10.00','AFN','2025-10-23','HesabPay','0313220001761203998',NULL,NULL,'1','2025-10-23 11:50:11','2025-10-23 11:50:11');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('15','1','10.00','AFN','2025-10-23','HesabPay','0078794001761204236','0078794001761204236',NULL,'1','2025-10-23 11:54:07','2025-10-23 11:54:07');
INSERT INTO `subscription_payments` (`id`,`subscription_id`,`amount`,`currency`,`payment_date`,`payment_method`,`transaction_id`,`receipt_number`,`notes`,`processed_by`,`created_at`,`updated_at`) VALUES ('16','1','10.00','AFN','2025-10-29','HesabPay','0184819001761720728','0184819001761720728',NULL,'1','2025-10-29 11:22:21','2025-10-29 11:22:21');

DROP TABLE IF EXISTS `supplier_transactions`;
CREATE TABLE `supplier_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `reference_id` int(100) NOT NULL,
  `transaction_type` enum('Debit','Credit') NOT NULL,
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','supplier_bonus','fund_withdrawal','umrah_date_change') NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `remarks` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_supplier_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=939 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('908','1','31','336','Debit','ticket_sale','357.030','64.460','Base amount of 357.03 USD deducted for ticket booking for Mr MUHAMMAD RAUF with PNR: 5DYFCD. (Transferred from supplier 32)','2025-10-18 09:00:02','2025-10-18 09:00:02','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('909','1','32','337','Debit','ticket_sale','335.030','825.650','Updated: Updated: Base amount of 320.03 USD deducted for ticket booking for Mr KHASTA GUL with PNR: 86S99L.','2025-10-16 11:33:05','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('910','1','32','338','Debit','ticket_sale','320.030','505.620','Base amount of 320.03 USD deducted for ticket booking for Mr BAIKHT BAIDAR BAKHT JEHAN with PNR: T6EXHA.','2025-10-16 11:34:32','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('912','1','35','339','Debit','ticket_sale','403.000','-5219.000','Base amount of 403 USD deducted for ticket booking for Mr HAJI MUSA GUL MINAH KHAN with PNR: 181QHU.','2025-10-16 16:05:11','2025-10-16 16:05:11','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('918','1','32','18','','ticket_reserve','100.000','405.620','Base amount of 100 USD deducted for ticket reservation. (Supplier changed)','2025-10-18 09:53:08','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('919','1','35','18','Debit','ticket_reserve','110.000','-5329.000','Updated: Purchase for ticket: BAKHTIAR STANIKZAI (MZR to FRA)','2025-10-18 09:57:14','2025-10-18 10:16:57','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('920','1','35','99','Credit','ticket_refund','383.000','-4946.000','Refund for ticket HAJI MUSA GUL MINAH KHAN added to account.','2025-10-18 10:19:21','2025-10-18 10:19:21','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('921','1','32','100','Credit','ticket_refund','300.030','805.650','Refund for ticket BAIKHT BAIDAR BAKHT JEHAN added to account.','2025-10-18 10:20:52','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('922','1','32','49','Debit','date_change','20.000','785.650','Penalty for ticket Name KHASTA GUL date change deducted from account','2025-10-18 10:21:55','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('923','1','31','101','Credit','ticket_refund','337.030','401.490','Refund for ticket MUHAMMAD RAUF added to account.','2025-10-18 10:23:58','2025-10-18 10:23:58','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('924','1','31','50','Debit','date_change','10.000','391.490','Penalty for ticket Name MUHAMMAD RAUF date change deducted from account','2025-10-18 10:24:26','2025-10-18 10:24:26','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('925','1','32','16','Debit','weight_sale','30.000','755.650','Base amount of 30 USD deducted for weight transaction.','2025-10-18 10:24:51','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('926','1','31','17','Debit','weight_sale','10.000','381.490','Base amount of 10 USD deducted for weight transaction.','2025-10-18 10:25:22','2025-10-18 10:25:22','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('927','1','32','47','Debit','hotel','15.000','740.650','Updated: Hotel booking for Mr EDRISS  NOOR','2025-10-18 10:26:12','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('928','1','32','47','Debit','hotel','15.000','695.650','Purchase for hotel booking: EDRISS  NOOR (Check-in: 2025-10-18)','2025-10-18 11:02:11','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('930','1','35','60','Debit','visa_sale','110.000','-5056.000','Purchase for visa: BAKHTIAR STANIKZAI (Passport: P8798765)','2025-10-18 13:52:00','2025-10-18 13:52:00','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('933','1','35','66','Debit','umrah','100.000','-5156.000','Purchase for all: Matiullah (Passport: P03241263)','2025-10-18 14:18:40','2025-10-18 14:18:40','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('934','1','32','51','Debit','additional_payment','10.000','685.650','test','2025-10-18 14:59:46','2025-10-25 08:58:04','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('936','1','35','1','Credit','fund','100.000','-5056.000','Supplier: ALAIN T, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: test','2025-10-20 14:44:17','2025-10-20 14:47:38','2452345');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('937','1','32','15','Credit','hotel_refund','15.000','700.650','Refund for hotel booking #47 - test','2025-10-27 15:46:33','2025-10-27 15:46:33','');
INSERT INTO `supplier_transactions` (`id`,`tenant_id`,`supplier_id`,`reference_id`,`transaction_type`,`transaction_of`,`amount`,`balance`,`remarks`,`transaction_date`,`updated_at`,`receipt`) VALUES ('938','1','32','340','Debit','ticket_sale','100.000','600.650','Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: 86S99L. (Transferred from supplier 36)','2025-10-28 09:26:10','2025-10-28 09:26:10','');

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `supplier_type` enum('Internal','External') NOT NULL DEFAULT 'External',
  `phone` varchar(15) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `balance` decimal(10,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_suppliers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`id`,`tenant_id`,`name`,`contact_person`,`supplier_type`,`phone`,`email`,`address`,`currency`,`balance`,`created_at`,`updated_at`,`status`) VALUES ('31','1','KamAir','Wali','External','0777305730','admin@abc-construction.com','wazir','USD','381.490','2025-10-15 15:41:34','2025-10-18 10:25:22','active');
INSERT INTO `suppliers` (`id`,`tenant_id`,`name`,`contact_person`,`supplier_type`,`phone`,`email`,`address`,`currency`,`balance`,`created_at`,`updated_at`,`status`) VALUES ('32','1','NSTTRIP','SABAOON','External','0777305730','admin@abc-construction.com','nsst','USD','600.650','2025-10-15 16:27:18','2025-10-28 09:26:10','active');
INSERT INTO `suppliers` (`id`,`tenant_id`,`name`,`contact_person`,`supplier_type`,`phone`,`email`,`address`,`currency`,`balance`,`created_at`,`updated_at`,`status`) VALUES ('33','1','Ariana','SABAOON','External','0777305730','admin@abc-construction.com','nsst','AFS','47468.000','2025-10-15 16:29:01','2025-10-15 16:29:01','active');
INSERT INTO `suppliers` (`id`,`tenant_id`,`name`,`contact_person`,`supplier_type`,`phone`,`email`,`address`,`currency`,`balance`,`created_at`,`updated_at`,`status`) VALUES ('34','1','Yasin','Wali','External','0777305730','admin@abc-construction.com','yasin','USD','406.000','2025-10-15 16:35:10','2025-10-15 16:35:10','active');
INSERT INTO `suppliers` (`id`,`tenant_id`,`name`,`contact_person`,`supplier_type`,`phone`,`email`,`address`,`currency`,`balance`,`created_at`,`updated_at`,`status`) VALUES ('35','1','ALAIN T','Wali','External','0777305730','admin@abc-construction.com','adfa','USD','-5056.000','2025-10-16 16:01:23','2025-10-20 14:47:38','active');
INSERT INTO `suppliers` (`id`,`tenant_id`,`name`,`contact_person`,`supplier_type`,`phone`,`email`,`address`,`currency`,`balance`,`created_at`,`updated_at`,`status`) VALUES ('36','1','Matiullah','NAVEED RASHIQ','Internal','0777305730','admin@abc-construction.com','test','USD','0.000','2025-10-28 09:23:28','2025-10-28 09:23:28','active');

DROP TABLE IF EXISTS `tenant_peering`;
CREATE TABLE `tenant_peering` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `status` enum('approved','pending','blocked') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_peer_unique` (`tenant_id`,`peer_tenant_id`),
  KEY `fk_tp_tenant` (`tenant_id`),
  KEY `fk_tp_peer` (`peer_tenant_id`),
  CONSTRAINT `fk_tp_peer` FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tenant_peering` (`id`,`tenant_id`,`peer_tenant_id`,`status`,`created_at`) VALUES ('3','2','1','approved','2025-09-08 12:43:20');

DROP TABLE IF EXISTS `tenant_subscriptions`;
CREATE TABLE `tenant_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan_id` varchar(50) NOT NULL,
  `status` enum('active','pending','cancelled','expired') NOT NULL DEFAULT 'pending',
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` timestamp NULL DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `last_payment_date` timestamp NULL DEFAULT NULL,
  `next_billing_date` timestamp NULL DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_plan_unique` (`tenant_id`,`plan_id`,`start_date`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tenant_subscriptions` (`id`,`tenant_id`,`plan_id`,`status`,`billing_cycle`,`start_date`,`end_date`,`amount`,`currency`,`payment_method`,`last_payment_date`,`next_billing_date`,`transaction_id`,`created_at`,`updated_at`) VALUES ('1','1','3','active','monthly','2025-07-24 00:00:00',NULL,'10.00','AFN','stripe','2025-10-29 00:00:00','2026-06-20 00:00:00','txn_123456789','2025-07-24 00:00:00','2025-10-29 11:22:21');
INSERT INTO `tenant_subscriptions` (`id`,`tenant_id`,`plan_id`,`status`,`billing_cycle`,`start_date`,`end_date`,`amount`,`currency`,`payment_method`,`last_payment_date`,`next_billing_date`,`transaction_id`,`created_at`,`updated_at`) VALUES ('2','2','3','active','yearly','2025-08-01 00:00:00',NULL,'0.00','USD','paypal','2025-09-17 00:00:00','2026-09-17 00:00:00','txn_987654321','2025-08-01 00:00:00','2025-09-17 11:17:33');
INSERT INTO `tenant_subscriptions` (`id`,`tenant_id`,`plan_id`,`status`,`billing_cycle`,`start_date`,`end_date`,`amount`,`currency`,`payment_method`,`last_payment_date`,`next_billing_date`,`transaction_id`,`created_at`,`updated_at`) VALUES ('3','3','3','active','quarterly','2025-06-01 00:00:00',NULL,'999.99','USD','stripe','2025-06-01 00:00:00','2025-09-01 00:00:00','txn_456789123','2025-06-01 00:00:00','2025-08-28 16:42:19');
INSERT INTO `tenant_subscriptions` (`id`,`tenant_id`,`plan_id`,`status`,`billing_cycle`,`start_date`,`end_date`,`amount`,`currency`,`payment_method`,`last_payment_date`,`next_billing_date`,`transaction_id`,`created_at`,`updated_at`) VALUES ('4','4','1','expired','monthly','2025-01-01 00:00:00','2025-02-01 00:00:00','49.99','USD','stripe','2025-01-01 00:00:00',NULL,'txn_111222333','2025-01-01 00:00:00','2025-08-28 16:42:23');
INSERT INTO `tenant_subscriptions` (`id`,`tenant_id`,`plan_id`,`status`,`billing_cycle`,`start_date`,`end_date`,`amount`,`currency`,`payment_method`,`last_payment_date`,`next_billing_date`,`transaction_id`,`created_at`,`updated_at`) VALUES ('5','5','2','pending','monthly','2025-08-20 00:00:00',NULL,'99.99','USD','Cash',NULL,'0000-00-00 00:00:00',NULL,'2025-08-20 00:00:00','2025-08-30 12:09:31');

DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subdomain` varchar(100) DEFAULT NULL,
  `identifier` varchar(100) NOT NULL,
  `status` enum('active','suspended','trial','deleted') NOT NULL DEFAULT 'trial',
  `plan` enum('basic','pro','enterprise') DEFAULT 'basic',
  `billing_email` varchar(255) DEFAULT NULL,
  `chat_max_file_bytes` int(11) NOT NULL DEFAULT 26214400,
  `chat_allowed_mime_prefixes` varchar(500) DEFAULT 'image/,video/,audio/,application/pdf,text/',
  `chat_default_auto_download` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('current','warning','overdue','suspended') NOT NULL DEFAULT 'current',
  `payment_due_date` date DEFAULT NULL,
  `last_payment_date` date DEFAULT NULL,
  `payment_warning_sent` tinyint(1) NOT NULL DEFAULT 0,
  `last_warning_sent` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tenants` (`id`,`name`,`subdomain`,`identifier`,`status`,`plan`,`billing_email`,`chat_max_file_bytes`,`chat_allowed_mime_prefixes`,`chat_default_auto_download`,`payment_status`,`payment_due_date`,`last_payment_date`,`payment_warning_sent`,`last_warning_sent`,`created_at`,`updated_at`,`deleted_at`) VALUES ('1','Al Moqadas Travel Agency ','alpha','tenant-alpha-001','active','enterprise','billing@alpha.com','26214400','image/,video/,audio/,application/pdf,text/','0','current','2025-10-17','2025-09-17','0',NULL,'2025-07-01 00:00:00','2025-09-17 11:20:55',NULL);
INSERT INTO `tenants` (`id`,`name`,`subdomain`,`identifier`,`status`,`plan`,`billing_email`,`chat_max_file_bytes`,`chat_allowed_mime_prefixes`,`chat_default_auto_download`,`payment_status`,`payment_due_date`,`last_payment_date`,`payment_warning_sent`,`last_warning_sent`,`created_at`,`updated_at`,`deleted_at`) VALUES ('2','Al Wali','beta','tenant-beta-002','active','enterprise','billing@beta.com','26214400','image/,video/,audio/,application/pdf,text/','0','current','2026-09-17','2025-09-17','0','2025-09-17 11:09:30','2025-07-15 00:00:00','2025-09-17 11:17:33',NULL);
INSERT INTO `tenants` (`id`,`name`,`subdomain`,`identifier`,`status`,`plan`,`billing_email`,`chat_max_file_bytes`,`chat_allowed_mime_prefixes`,`chat_default_auto_download`,`payment_status`,`payment_due_date`,`last_payment_date`,`payment_warning_sent`,`last_warning_sent`,`created_at`,`updated_at`,`deleted_at`) VALUES ('3','Elite Pilgrimages Gamma','gamma','tenant-gamma-003','deleted','enterprise','billing@gamma.com','26214400','image/,video/,audio/,application/pdf,text/','0','current',NULL,NULL,'0',NULL,'2025-06-01 00:00:00','2025-09-09 11:22:26','2025-09-09 11:22:26');
INSERT INTO `tenants` (`id`,`name`,`subdomain`,`identifier`,`status`,`plan`,`billing_email`,`chat_max_file_bytes`,`chat_allowed_mime_prefixes`,`chat_default_auto_download`,`payment_status`,`payment_due_date`,`last_payment_date`,`payment_warning_sent`,`last_warning_sent`,`created_at`,`updated_at`,`deleted_at`) VALUES ('4','Suspended Tours Delta','delta','tenant-delta-004','deleted','basic','billing@delta.com','26214400','image/,video/,audio/,application/pdf,text/','0','current',NULL,NULL,'0',NULL,'2025-01-01 00:00:00','2025-08-26 15:42:34','2025-08-26 15:42:34');
INSERT INTO `tenants` (`id`,`name`,`subdomain`,`identifier`,`status`,`plan`,`billing_email`,`chat_max_file_bytes`,`chat_allowed_mime_prefixes`,`chat_default_auto_download`,`payment_status`,`payment_due_date`,`last_payment_date`,`payment_warning_sent`,`last_warning_sent`,`created_at`,`updated_at`,`deleted_at`) VALUES ('5','New Ventures Epsilon','epsilon','tenant-epsilon-005','deleted','enterprise','billing@epsilon.com','26214400','image/,video/,audio/,application/pdf,text/','0','current',NULL,NULL,'0',NULL,'2025-08-20 00:00:00','2025-09-09 11:22:30','2025-09-09 11:22:30');
INSERT INTO `tenants` (`id`,`name`,`subdomain`,`identifier`,`status`,`plan`,`billing_email`,`chat_max_file_bytes`,`chat_allowed_mime_prefixes`,`chat_default_auto_download`,`payment_status`,`payment_due_date`,`last_payment_date`,`payment_warning_sent`,`last_warning_sent`,`created_at`,`updated_at`,`deleted_at`) VALUES ('6','KamAir','mtravels','travelalmuqadas','deleted','basic','RAHIMI107@GAMIL.COM','26214400','image/,video/,audio/,application/pdf,text/','0','current',NULL,NULL,'0',NULL,'2025-08-26 16:09:54','2025-08-26 16:11:22','2025-08-26 16:11:22');

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `testimonial` mediumtext DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_testimonials_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `testimonials` (`id`,`tenant_id`,`name`,`photo`,`testimonial`,`destination`,`rating`,`active`,`created_at`,`updated_at`) VALUES ('4','1','Ahmad Rahimi','uploads/testimonials/testimonial_68ecd199e9b8c.png','MTravels has completely transformed our travel agency operations. The flight booking system is incredibly efficient and our customer satisfaction has increased by 40%.','Dubai','5','1','2025-09-09 08:34:20','2025-10-13 14:46:57');
INSERT INTO `testimonials` (`id`,`tenant_id`,`name`,`photo`,`testimonial`,`destination`,`rating`,`active`,`created_at`,`updated_at`) VALUES ('5','1','Fawad Hassan','','The visa processing feature is a game-changer. What used to take days now takes hours. Our clients love the transparency and speed of the process.','Turkey','5','1','2025-09-09 08:34:20','2025-09-09 08:34:20');
INSERT INTO `testimonials` (`id`,`tenant_id`,`name`,`photo`,`testimonial`,`destination`,`rating`,`active`,`created_at`,`updated_at`) VALUES ('6','1','Mohammad Ali','','Outstanding hotel booking system with real-time availability. The integration with major hotel chains has made our job so much easier.','Malaysia','5','1','2025-09-09 08:34:20','2025-09-09 08:34:20');
INSERT INTO `testimonials` (`id`,`tenant_id`,`name`,`photo`,`testimonial`,`destination`,`rating`,`active`,`created_at`,`updated_at`) VALUES ('7','1','Ali Khan','','The financial management tools are excellent. Multi-currency support and automated invoicing have streamlined our accounting processes significantly.','UAE','4','1','2025-09-09 08:34:20','2025-09-09 08:34:20');
INSERT INTO `testimonials` (`id`,`tenant_id`,`name`,`photo`,`testimonial`,`destination`,`rating`,`active`,`created_at`,`updated_at`) VALUES ('8','1','Omar Farooq','','Customer management has never been easier. The CRM features help us personalize experiences and track customer preferences effectively.','Saudi Arabia','5','1','2025-09-09 08:34:20','2025-09-09 08:34:20');
INSERT INTO `testimonials` (`id`,`tenant_id`,`name`,`photo`,`testimonial`,`destination`,`rating`,`active`,`created_at`,`updated_at`) VALUES ('9','1','Zakir Ahmad','','The analytics dashboard provides valuable insights into our business performance. We can now make data-driven decisions to grow our agency.','Pakistan','4','1','2025-09-09 08:34:20','2025-09-09 08:34:20');

DROP TABLE IF EXISTS `ticket_bookings`;
CREATE TABLE `ticket_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_booking_id` int(11) DEFAULT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(10) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `title` varchar(10) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(100) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `airline` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` varchar(10) NOT NULL,
  `price` decimal(10,3) NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `discount` decimal(10,3) DEFAULT NULL,
  `profit` decimal(10,3) NOT NULL,
  `status` enum('Borrowed','Paid','Date Changed','Refunded','Booked') DEFAULT 'Booked',
  `receipt` varchar(10) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_type` enum('one_way','round_trip') NOT NULL DEFAULT 'one_way',
  `return_date` date DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ticket_bookings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=341 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ticket_bookings` (`id`,`tenant_id`,`group_booking_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`price`,`sold`,`discount`,`profit`,`status`,`receipt`,`description`,`created_at`,`updated_at`,`trip_type`,`return_date`,`return_origin`,`return_destination`,`created_by`) VALUES ('336','1',NULL,'31','19','13','Mr','MUHAMMAD RAUF','5DYFCD',' KBL','HAS','0788808299','FZ','Male','2025-10-16','2025-10-18','USD','357.030','375.000','0.000','17.970','Date Changed','','test','2025-10-16 11:31:45','2025-10-18 10:24:26','one_way','0000-00-00','','','1');
INSERT INTO `ticket_bookings` (`id`,`tenant_id`,`group_booking_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`price`,`sold`,`discount`,`profit`,`status`,`receipt`,`description`,`created_at`,`updated_at`,`trip_type`,`return_date`,`return_origin`,`return_destination`,`created_by`) VALUES ('337','1',NULL,'32','18','13','Mr','KHASTA GUL','86S99L',' KBL','ELQ','0771781576','FZ','Male','2025-10-16','2025-10-25','USD','335.030','350.000','0.000','14.970','Date Changed','','Sale for ticket: KHASTA GUL ( KBL to ELQ)','2025-10-16 11:33:05','2025-10-25 09:00:33','one_way','0000-00-00','','','1');
INSERT INTO `ticket_bookings` (`id`,`tenant_id`,`group_booking_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`price`,`sold`,`discount`,`profit`,`status`,`receipt`,`description`,`created_at`,`updated_at`,`trip_type`,`return_date`,`return_origin`,`return_destination`,`created_by`) VALUES ('338','1',NULL,'32','19','13','Mr','BAIKHT BAIDAR BAKHT JEHAN','T6EXHA',' KBL','ELQ','0766202122','FZ','Male','2025-10-16','2025-10-25','USD','320.030','330.000','0.000','9.970','Refunded','','test','2025-10-16 11:34:32','2025-10-18 10:20:52','one_way','0000-00-00',NULL,'','1');
INSERT INTO `ticket_bookings` (`id`,`tenant_id`,`group_booking_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`price`,`sold`,`discount`,`profit`,`status`,`receipt`,`description`,`created_at`,`updated_at`,`trip_type`,`return_date`,`return_origin`,`return_destination`,`created_by`) VALUES ('339','1',NULL,'35','22','13','Mr','HAJI MUSA GUL MINAH KHAN','181QHU',' KBL','JED','0773513830','KM','Male','2025-10-16','2025-10-17','USD','403.000','431.000','0.000','28.000','Refunded','','test','2025-10-16 16:05:11','2025-10-18 10:19:21','one_way','0000-00-00',NULL,'','1');
INSERT INTO `ticket_bookings` (`id`,`tenant_id`,`group_booking_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`price`,`sold`,`discount`,`profit`,`status`,`receipt`,`description`,`created_at`,`updated_at`,`trip_type`,`return_date`,`return_origin`,`return_destination`,`created_by`) VALUES ('340','1',NULL,'32','19','13','Mr','guli','86S99L','Kabul','JED','0771781576','A3','Male','2025-10-28','2025-10-29','USD','100.000','120.000','0.000','20.000','Booked','','test','2025-10-28 09:25:33','2025-10-29 15:23:46','one_way','0000-00-00','','','1');

DROP TABLE IF EXISTS `ticket_reservations`;
CREATE TABLE `ticket_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(10) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `title` varchar(10) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(100) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `airline` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` varchar(10) NOT NULL,
  `price` decimal(10,3) NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `profit` decimal(10,3) NOT NULL,
  `status` enum('Reserved','Paid','Date Changed','Refunded') DEFAULT 'Reserved',
  `receipt` varchar(10) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_type` enum('one_way','round_trip') NOT NULL DEFAULT 'one_way',
  `return_date` date DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ticket_reservations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ticket_reservations` (`id`,`tenant_id`,`supplier`,`sold_to`,`paid_to`,`title`,`passenger_name`,`pnr`,`origin`,`destination`,`phone`,`airline`,`gender`,`issue_date`,`departure_date`,`currency`,`price`,`sold`,`profit`,`status`,`receipt`,`description`,`created_at`,`updated_at`,`trip_type`,`return_date`,`return_origin`,`return_destination`,`created_by`) VALUES ('18','1','35','18','13','Mr','BAKHTIAR STANIKZAI','188JZ0','MZR','FRA','0777305730','EI','Male','2025-10-18','2025-10-23','USD','110.000','130.000','20.000','Reserved','','Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)','2025-10-18 09:53:08','2025-10-18 10:17:40','one_way','0000-00-00','','','1');

DROP TABLE IF EXISTS `ticket_weights`;
CREATE TABLE `ticket_weights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `weight` decimal(10,2) NOT NULL COMMENT 'Weight in kilograms',
  `base_price` decimal(10,2) NOT NULL,
  `sold_price` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ticket_weights_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ticket_weights` (`id`,`tenant_id`,`ticket_id`,`weight`,`base_price`,`sold_price`,`profit`,`remarks`,`created_by`,`created_at`,`updated_at`) VALUES ('16','1','337','20.00','30.00','40.00','10.00','fasdf','0','2025-10-18 10:24:51','2025-10-18 10:24:51');
INSERT INTO `ticket_weights` (`id`,`tenant_id`,`ticket_id`,`weight`,`base_price`,`sold_price`,`profit`,`remarks`,`created_by`,`created_at`,`updated_at`) VALUES ('17','1','336','20.00','10.00','20.00','10.00','sdfas','0','2025-10-18 10:25:22','2025-10-18 10:25:22');

DROP TABLE IF EXISTS `totp_recovery_codes`;
CREATE TABLE `totp_recovery_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('staff','client') NOT NULL,
  `recovery_code` varchar(20) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`user_type`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_totp_recovery_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('49','1','1','staff','DTCY-YDG6-DGSM-AGGP','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('50','1','1','staff','M79U-Q12B-IZ54-PZGI','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('51','1','1','staff','SPNY-YYX8-69Q1-RTRQ','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('52','1','1','staff','6DSM-M6TI-R9I6-N30U','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('53','1','1','staff','MFKH-PFN9-XVZR-MQ1E','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('54','1','1','staff','HE8P-ZN3E-UBYN-N8ZC','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('55','1','1','staff','WLPA-GMV5-K3PR-LYO3','0','2025-11-01 11:58:54',NULL);
INSERT INTO `totp_recovery_codes` (`id`,`tenant_id`,`user_id`,`user_type`,`recovery_code`,`is_used`,`created_at`,`used_at`) VALUES ('56','1','1','staff','HM4G-NPIV-B28F-8DFW','0','2025-11-01 11:58:54',NULL);

DROP TABLE IF EXISTS `totp_secrets`;
CREATE TABLE `totp_secrets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('staff','client') NOT NULL,
  `secret` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_user_unique` (`tenant_id`,`user_id`,`user_type`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_totp_secrets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `totp_secrets` (`id`,`tenant_id`,`user_id`,`user_type`,`secret`,`is_enabled`,`created_at`,`last_used`) VALUES ('11','1','1','staff','F4P6P43CGPWGHI34YM6BTKDKE56HFS45XEE2PSZNDLT65E33XEQS6GUVSTZZLHOPVTMNAKN3FQHOS56GMOUM4M5QQFRCI23DXIV3KWI','0','2025-11-01 11:58:54',NULL);

DROP TABLE IF EXISTS `umrah_agreements`;
CREATE TABLE `umrah_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_umrah_agreements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `umrah_booking_services`;
CREATE TABLE `umrah_booking_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_type` enum('ticket','visa','hotel','transport','all') NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `base_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `sold_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `profit` decimal(10,3) NOT NULL DEFAULT 0.000,
  `currency` enum('USD','AFS') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `service_type` (`service_type`),
  CONSTRAINT `fk_ub_services_booking` FOREIGN KEY (`booking_id`) REFERENCES `umrah_bookings` (`booking_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_services_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_ub_services_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `umrah_booking_services` (`id`,`tenant_id`,`booking_id`,`service_type`,`supplier_id`,`base_price`,`sold_price`,`profit`,`currency`,`created_at`,`updated_at`) VALUES ('34','1','66','all','35','100.000','130.000','30.000','USD','2025-10-18 14:33:59','2025-10-18 14:33:59');

DROP TABLE IF EXISTS `umrah_bookings`;
CREATE TABLE `umrah_bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `family_id` int(11) DEFAULT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `fname` varchar(50) NOT NULL,
  `gfname` varchar(50) NOT NULL,
  `relation` varchar(50) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL COMMENT 'Gender of pilgrim (Male/Female)',
  `passport_number` varchar(20) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL COMMENT 'Passport expiry date - must be valid for at least 6 months',
  `id_type` varchar(50) DEFAULT NULL,
  `flight_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `duration` varchar(11) DEFAULT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `price` decimal(10,3) DEFAULT NULL,
  `sold_price` decimal(10,3) DEFAULT NULL,
  `discount` decimal(10,3) NOT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `received_bank_payment` decimal(10,3) DEFAULT NULL,
  `bank_receipt_number` varchar(50) DEFAULT NULL,
  `paid` decimal(10,3) DEFAULT NULL,
  `due` decimal(10,3) DEFAULT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` varchar(100) NOT NULL,
  `status` enum('active','refunded') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`booking_id`),
  KEY `family_id` (`family_id`),
  KEY `idx_passport_expiry` (`passport_expiry`),
  KEY `idx_gender` (`gender`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_umrah_bookings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `umrah_bookings_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`family_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `umrah_bookings` (`booking_id`,`tenant_id`,`family_id`,`sold_to`,`paid_to`,`entry_date`,`name`,`fname`,`gfname`,`relation`,`dob`,`gender`,`passport_number`,`passport_expiry`,`id_type`,`flight_date`,`return_date`,`duration`,`room_type`,`price`,`sold_price`,`discount`,`profit`,`received_bank_payment`,`bank_receipt_number`,`paid`,`due`,`currency`,`created_by`,`created_at`,`updated_at`,`remarks`,`status`) VALUES ('66','1','15','18','13','2025-10-18','Matiullah','FAIZ MOHAMMAD','ESMAT ULLAH','Uncle','2025-10-25','Male','P03241263','2026-04-25','ID Original + Passport Original',NULL,NULL,'21 Days','Shared','100.000','130.000','0.000','30.000','0.000','','10.000','120.000','USD','1','2025-10-18 14:08:20','2025-10-27 15:47:28','yrsd','active');

DROP TABLE IF EXISTS `umrah_refunds`;
CREATE TABLE `umrah_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `refund_type` enum('full','partial') NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `processed_by` (`processed_by`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_umrah_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `umrah_transactions`;
CREATE TABLE `umrah_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `umrah_booking_id` int(11) NOT NULL,
  `transaction_type` enum('Debit','Credit','','') NOT NULL,
  `transaction_to` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_description` varchar(255) DEFAULT NULL,
  `payment_amount` decimal(10,3) NOT NULL,
  `receipt` varchar(10) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `exchange_rate` decimal(10,3) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `visa_id` (`umrah_booking_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_umrah_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `umrah_transactions` (`id`,`tenant_id`,`umrah_booking_id`,`transaction_type`,`transaction_to`,`payment_date`,`payment_description`,`payment_amount`,`receipt`,`currency`,`exchange_rate`,`created_at`) VALUES ('96','1','66','Credit','Internal Account','2025-10-27','test','10.000','','USD',NULL,'2025-10-27 15:47:28');

DROP TABLE IF EXISTS `user_agreements`;
CREATE TABLE `user_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `agreement_type` enum('employment','confidentiality','performance') NOT NULL,
  `position` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_agreements_user_id` (`user_id`),
  KEY `idx_user_agreements_created_by` (`created_by`),
  KEY `idx_user_agreements_created_at` (`created_at`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_user_agreements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_blocks`;
CREATE TABLE `user_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blocked_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`tenant_id`,`user_id`,`blocked_user_id`),
  KEY `fk_ub_user` (`user_id`),
  KEY `fk_ub_blocked` (`blocked_user_id`),
  CONSTRAINT `fk_ub_blocked` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_documents`;
CREATE TABLE `user_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `uploaded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_user_documents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_mutes`;
CREATE TABLE `user_mutes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `muted_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mute` (`tenant_id`,`user_id`,`muted_user_id`),
  KEY `fk_um_user` (`user_id`),
  KEY `fk_um_muted` (`muted_user_id`),
  CONSTRAINT `fk_um_muted` FOREIGN KEY (`muted_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_um_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_um_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'assets/images/user/avatar-2.jpg',
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `fired` tinyint(1) DEFAULT 0,
  `fired_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_email` (`tenant_id`,`email`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`,`tenant_id`,`name`,`email`,`password`,`created_at`,`role`,`phone`,`address`,`hire_date`,`profile_pic`,`totp_enabled`,`fired`,`fired_at`,`updated_at`,`deleted_at`) VALUES ('1','1','SABAOON CAR REPAIR','almuqadas_travel@yahoo.com','$2y$10$McogSKPqbHafcuzb.fOiLexQrCt2CR.EUCDNxT3vD/SNDC.P2fWA2','2024-12-24 13:11:23','admin','0786011115','kabul, jada-ee-mewand','2024-12-04','68f74bc0ade30_White and Blue Modern Travel Poster.png','0','0',NULL,NULL,NULL);
INSERT INTO `users` (`id`,`tenant_id`,`name`,`email`,`password`,`created_at`,`role`,`phone`,`address`,`hire_date`,`profile_pic`,`totp_enabled`,`fired`,`fired_at`,`updated_at`,`deleted_at`) VALUES ('6','1','Idrees','idress@gmail.com','$2y$10$LJc9TC9ekIpXNSag3kIWu.d45oVeJLbp9zP0a1a04b6lZOo/CltH6','2025-04-09 10:29:29','sales','0777555594','Jada-e-Maiwand','2025-04-09','6905da1a0e1d6_WhatsApp Image 2025-10-27 at 11.31.26 AM.jpeg','0','0','2025-10-20 09:12:42',NULL,NULL);
INSERT INTO `users` (`id`,`tenant_id`,`name`,`email`,`password`,`created_at`,`role`,`phone`,`address`,`hire_date`,`profile_pic`,`totp_enabled`,`fired`,`fired_at`,`updated_at`,`deleted_at`) VALUES ('7','2','Matiullah Rahimi','mati@gmail.com','$2y$10$Yr61g65gW/w8UjFfG3kLmephVrFKf2m6zGqMrTdgvmPcy8pg/Bv/e','2025-04-09 10:30:25','admin','0777555594','Jada-e-Maiwand','2025-04-09','68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png','0','0','2025-09-01 13:22:36',NULL,NULL);
INSERT INTO `users` (`id`,`tenant_id`,`name`,`email`,`password`,`created_at`,`role`,`phone`,`address`,`hire_date`,`profile_pic`,`totp_enabled`,`fired`,`fired_at`,`updated_at`,`deleted_at`) VALUES ('14',NULL,'ALLAH DAD MUHAMMADI','allahdadmuhammadi01@gmail.com','$2y$10$5BbWc37e43gokcY5etVUauiZZFP/uLeYQrGFJaUkrEGSxvvXnsQnS','2025-05-19 08:31:47','super_admin','0780310431','KABUL AFGHANISTAN','2025-05-19','682aadaaef90b.jpg','0','0',NULL,NULL,NULL);
INSERT INTO `users` (`id`,`tenant_id`,`name`,`email`,`password`,`created_at`,`role`,`phone`,`address`,`hire_date`,`profile_pic`,`totp_enabled`,`fired`,`fired_at`,`updated_at`,`deleted_at`) VALUES ('18','1','Matiullah','matiullahr970@gmail.com','$2y$10$fh.T7oUwq/iPapaztUlf6.XSUBsCbbn2bRtEeHGiPY3Yd.chZ1iEa','2025-10-29 16:28:44','finance','0777305730','test','2025-10-29','690307f9d794a_umrah.png','0','0',NULL,'2025-10-29 16:28:44',NULL);

DROP TABLE IF EXISTS `visa_applications`;
CREATE TABLE `visa_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `supplier` int(11) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `title` enum('Mr','Mrs','Child') NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `applicant_name` varchar(100) NOT NULL,
  `passport_number` varchar(50) NOT NULL,
  `country` varchar(50) NOT NULL,
  `visa_type` varchar(50) NOT NULL,
  `receive_date` date NOT NULL,
  `applied_date` date NOT NULL,
  `issued_date` date DEFAULT NULL,
  `base` decimal(10,3) NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `currency` varchar(10) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `remarks` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_visa_applications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `visa_applications` (`id`,`tenant_id`,`supplier`,`sold_to`,`paid_to`,`phone`,`title`,`gender`,`applicant_name`,`passport_number`,`country`,`visa_type`,`receive_date`,`applied_date`,`issued_date`,`base`,`sold`,`profit`,`currency`,`status`,`remarks`,`created_at`,`updated_at`,`created_by`) VALUES ('60','1','35','19','13','0777555594','Mr','Male','BAKHTIAR STANIKZAI','P8798765','Pakistan','Tourist','2025-10-18','2025-10-25','0000-00-00','110.000','130.000','20.000','USD','Pending','adf','2025-10-18 13:49:18','2025-10-27 15:51:27','1');

DROP TABLE IF EXISTS `visa_refunds`;
CREATE TABLE `visa_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `visa_id` int(11) NOT NULL,
  `refund_type` varchar(50) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` mediumtext NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `refund_date` datetime DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `visa_id` (`visa_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_visa_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visa_refunds_ibfk_1` FOREIGN KEY (`visa_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


