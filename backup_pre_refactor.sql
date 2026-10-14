-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: Sistem-Manajemen-Laundry
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laundry-cache-1574bddb75c78a6fd2251d61e2993b5146201319','i:1;',1776655936),('laundry-cache-1574bddb75c78a6fd2251d61e2993b5146201319:timer','i:1776655936;',1776655936),('laundry-cache-356a192b7913b04c54574d18c28d46e6395428ab','i:1;',1776063032),('laundry-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer','i:1776063032;',1776063032),('laundry-cache-fadlanfirdaus220@gmail.com|127.0.0.1','i:1;',1776815647),('laundry-cache-fadlanfirdaus220@gmail.com|127.0.0.1:timer','i:1776815647;',1776815647),('laundry-cache-nurakbarsulaiman44@gmail.com|127.0.0.1','i:1;',1791711280),('laundry-cache-nurakbarsulaiman44@gmail.com|127.0.0.1:timer','i:1791711280;',1791711280);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coverage_areas`
--

DROP TABLE IF EXISTS `coverage_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coverage_areas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coverage_areas`
--

LOCK TABLES `coverage_areas` WRITE;
/*!40000 ALTER TABLE `coverage_areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `coverage_areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deliveries`
--

DROP TABLE IF EXISTS `deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `courier_id` bigint unsigned DEFAULT NULL,
  `external_courier_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_courier_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('dijemput','diantar','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dijemput',
  `type` enum('pickup','delivery') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pickup',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `proof_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_lat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_lng` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deliveries_order_id_foreign` (`order_id`),
  KEY `deliveries_courier_id_foreign` (`courier_id`),
  CONSTRAINT `deliveries_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deliveries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deliveries`
--

LOCK TABLES `deliveries` WRITE;
/*!40000 ALTER TABLE `deliveries` DISABLE KEYS */;
INSERT INTO `deliveries` VALUES (1,2,13,NULL,NULL,'dijemput','pickup','2026-04-13 07:21:00','fedsadfsdfdsffdfs',NULL,NULL,NULL,'2026-04-12 23:21:43','2026-04-12 23:21:43'),(2,7,1,NULL,NULL,'selesai','pickup','2026-04-23 00:00:00','sfdsdf',NULL,NULL,NULL,'2026-04-21 23:15:45','2026-04-21 23:18:17'),(3,7,NULL,NULL,NULL,'diantar','delivery',NULL,NULL,NULL,NULL,NULL,'2026-04-21 23:15:45','2026-04-21 23:15:45'),(4,8,1,NULL,NULL,'selesai','pickup','2026-04-23 05:00:00',NULL,NULL,NULL,NULL,'2026-04-21 23:16:00','2026-04-21 23:19:00'),(5,9,1,NULL,NULL,'selesai','pickup','2026-04-24 02:00:00','oke','deliveries/IYdWE9U60Z7TJde1dCqLxpP8wfZs2jXQnyqT4GWB.jpg',NULL,NULL,'2026-04-21 23:41:34','2026-04-21 23:43:28'),(6,9,NULL,NULL,NULL,'diantar','delivery',NULL,NULL,NULL,NULL,NULL,'2026-04-21 23:41:34','2026-04-21 23:41:34');
/*!40000 ALTER TABLE `deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_04_09_022422_create_orders_table',2),(5,'2026_04_09_024846_create_order_items_table',2),(6,'2026_04_09_025753_create_services_table',3),(7,'2026_04_09_025955_add_service_id_foreign_to_orders_table',4),(8,'2026_04_09_030109_create_order_processes_table',5),(9,'2026_04_09_030435_create_deliveries_table',6),(10,'2026_04_09_030717_create_payments_table',7),(11,'2026_04_09_030942_create_user_addresses_table',8),(12,'2026_04_13_051054_add_fields_to_services_table',9),(13,'2026_04_13_052546_add_fields_to_deliveries_table',10),(14,'2026_04_14_061510_add_weight_to_orders_table',11),(15,'2026_04_13_081636_create_coverage_areas_table',12),(16,'2026_04_13_082938_create_outlet_locations_table',12),(17,'2026_04_13_083911_add_landing_page_fields_to_services_table',12),(18,'2026_04_18_065802_create_banners_table',12),(19,'2026_04_18_154309_create_reviews_table',12),(20,'2026_04_18_161709_change_icon_to_image_in_services_table',12),(21,'2026_04_18_162149_change_icon_to_image_in_services_table',12),(22,'2026_04_20_055434_add_external_courier_fields_to_deliveries_table',13),(23,'2026_04_20_061101_update_orders_status_enum_add_dijemput',13),(24,'2026_04_20_062826_add_proof_image_to_deliveries_table',13),(25,'2026_04_20_133402_add_notes_to_orders_table',13);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,7,'Estimasi Berat: <3 - Setrika [+ Express (24 Jam)]',4,6000.00,'2026-04-21 23:15:45','2026-04-21 23:18:17'),(2,8,'Estimasi Berat: 5-10 - Cuci Kiloan',4,5000.00,'2026-04-21 23:16:00','2026-04-21 23:19:00'),(3,9,'Estimasi Berat: 3-5 - Cuci Kiloan [+ Express (24 Jam)]',5,7500.00,'2026-04-21 23:41:34','2026-04-21 23:43:28');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_processes`
--

DROP TABLE IF EXISTS `order_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_processes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `process` enum('cuci','kering','setrika') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_processes_order_id_foreign` (`order_id`),
  KEY `order_processes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `order_processes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_processes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_processes`
--

LOCK TABLES `order_processes` WRITE;
/*!40000 ALTER TABLE `order_processes` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_processes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `weight` decimal(8,2) NOT NULL DEFAULT '1.00',
  `status` enum('pending','dijemput','diproses','selesai','diantar') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `total_price` decimal(10,2) NOT NULL,
  `pickup_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_user_id_foreign` (`user_id`),
  KEY `orders_service_id_foreign` (`service_id`),
  CONSTRAINT `orders_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (2,3,1,1.00,'diproses',5000.00,'fgdh','fdsghj',NULL,'2026-04-12 22:47:00','2026-04-12 22:47:00'),(3,8,1,123.00,'pending',615000.00,'jsajd','ajsdj',NULL,'2026-04-13 22:46:41','2026-04-13 22:46:41'),(4,12,1,10.00,'pending',50000.00,'jsajd','Ambil Mandiri di Toko',NULL,'2026-04-13 22:46:58','2026-04-13 22:46:58'),(5,12,1,10.00,'selesai',50000.00,'Bawa Mandiri ke Toko','asjdj',NULL,'2026-04-13 22:47:29','2026-04-13 22:48:54'),(7,17,2,1.00,'diproses',34000.00,'jln.jln','jln.jln','asdsdasd','2026-04-21 23:15:45','2026-04-21 23:18:17'),(8,17,1,1.00,'diproses',25000.00,'jln.jln','jln.jln',NULL,'2026-04-21 23:16:00','2026-04-21 23:19:00'),(9,17,1,1.00,'diproses',47500.00,'jln.jln','jln.jln',NULL,'2026-04-21 23:41:34','2026-04-21 23:43:28');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outlet_locations`
--

DROP TABLE IF EXISTS `outlet_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outlet_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outlet_locations`
--

LOCK TABLES `outlet_locations` WRITE;
/*!40000 ALTER TABLE `outlet_locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `outlet_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','transfer','e-wallet') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_order_id_foreign` (`order_id`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,7,0.00,'cash','pending',NULL,'2026-04-21 23:15:45','2026-04-21 23:15:45'),(2,8,0.00,'cash','pending',NULL,'2026-04-21 23:16:00','2026-04-21 23:16:00'),(3,9,0.00,'transfer','pending',NULL,'2026-04-21 23:41:34','2026-04-21 23:41:34');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_order_id_unique` (`order_id`),
  KEY `reviews_user_id_foreign` (`user_id`),
  KEY `reviews_service_id_foreign` (`service_id`),
  CONSTRAINT `reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Kiloan',
  `price` decimal(10,2) NOT NULL,
  `estimate` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1-2 Hari',
  `status` enum('tersedia','sibuk','tidak_tersedia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tersedia',
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `features` json DEFAULT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '/kg',
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'Cuci Kiloan','Kiloan',5000.00,'1-2 Hari','sibuk','Layanan cuci reguler kiloan',NULL,'2026-04-08 18:59:48','2026-04-12 22:44:57',NULL,'/kg',NULL),(2,'Setrika','Kiloan',4000.00,'1-2 Hari','tersedia','Layanan khusus setrika pakaian',NULL,'2026-04-08 18:59:48','2026-04-08 18:59:48',NULL,'/kg',NULL),(3,'Express','Kiloan',10000.00,'1-2 Hari','tersedia','Layanan cuci kilat selesai 1 hari',NULL,'2026-04-08 18:59:48','2026-04-08 18:59:48',NULL,'/kg',NULL);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('m2clssPKuAItI84QBMuJjveHGVhPJZm9MRnby70k',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJTdk1aMFN1ZnNZMGpNaFBxR0tNVmhyaWpyVU95ZFFVMjF5S1JrMTFjIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1791790352),('mcKs73O2WaPTzs5bJjXogc81kOn7yI1fEwE9owHs',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 OPR/136.0.0.0','eyJfdG9rZW4iOiJXbTVtRk94VHRIQWJjeW9NcFhYNUMzNEl1bEdhTFJkVnlXa3lIanJBIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1791716311),('WJGVoDZqsKWmyH5wAN3LRypA9FPPASZasIgB5o6f',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiI5c3h5T2NWRmN1dTdWNll5Mk5sUVh5WDR2b3NobTBFUVVXWkw2aGlBIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1791714600),('YEHwIecRYFLyEtvYCpyqUvZYrCIXShHPYa3qFMTL',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9444','eyJfdG9rZW4iOiJpdFo5RlBUY2xSNXBuR2dOMnlvWTdieVRKaTFQems3cHhIY0wwR2ZJIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1791714317),('zKzLQjvzxErhx6y2pnW5or0YaLKTHgICB8noSPxd',19,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiIzcFo0Tmd6eEpvTzJ0RktQZGRZNDUya3hrWkU4eDhpeEtidkxaTG5CIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MTksIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMCIsInJvdXRlIjoiaG9tZSJ9fQ==',1791717344);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_addresses_user_id_foreign` (`user_id`),
  CONSTRAINT `user_addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_addresses`
--

LOCK TABLES `user_addresses` WRITE;
/*!40000 ALTER TABLE `user_addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `role` enum('pelanggan','admin','operator','kurir') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pelanggan',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Fattah','fattah@gmail.com','2026-04-13 07:03:31','$2y$12$Js47JJ3i05kAxk7izY64NOs8VeD8u78CFtH7I27PlHiCnAlHzCVwm','08129123','jl.jl','kurir','8uTVrxPJRqwZAoIv0ZYT8WBP73vD2kDGj1lnmT6nZQJ3FopzdVeXtMh9tPbt','2026-04-08 19:18:32','2026-04-08 19:18:32'),(3,'Ahmad Fauzi','ahmad1@gmail.com',NULL,'$2y$10$examplehash','081234567001','Jl. Mawar No.1','pelanggan',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(4,'Siti Nurhaliza','siti2@gmail.com',NULL,'$2y$10$examplehash','081234567002','Jl. Melati No.2','admin',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(5,'Budi Santoso','budi3@gmail.com',NULL,'$2y$10$examplehash','081234567003','Jl. Kenanga No.3','operator',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(6,'Dewi Lestari','dewi4@gmail.com',NULL,'$2y$10$examplehash','081234567004','Jl. Anggrek No.4','kurir',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(7,'Rizky Pratama','rizky5@gmail.com',NULL,'$2y$10$examplehash','081234567005','Jl. Dahlia No.5','pelanggan',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(8,'Putri Ayu','putri6@gmail.com',NULL,'$2y$10$examplehash','081234567006','Jl. Flamboyan No.6','pelanggan',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(9,'Andi Saputra','andi7@gmail.com',NULL,'$2y$10$examplehash','081234567007','Jl. Cempaka No.7','operator',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(10,'Maya Sari','maya8@gmail.com',NULL,'$2y$10$examplehash','081234567008','Jl. Teratai No.8','kurir',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(11,'Fajar Hidayat','fajar9@gmail.com',NULL,'$2y$10$examplehash','081234567009','Jl. Bougenville No.9','admin',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(12,'Intan Permata','intan10@gmail.com',NULL,'$2y$10$examplehash','081234567010','Jl. Sakura No.10','pelanggan',NULL,'2026-04-13 05:44:14','2026-04-13 05:44:14'),(13,'kevin','kevin@gmail.com',NULL,'$2y$12$JSKLak24GxlQOp6t0UULhO0LuVCr2jsIDB9mU7ph7EID/B7fggyHa','081253723415','jln.padat karya gang sempaja depan mr diy, dekat nya ada alfamart','kurir',NULL,'2026-04-12 21:45:55','2026-04-12 21:45:55'),(14,'Fattah','glipxse@gmail.com',NULL,'$2y$12$DI2r07Xcz3zPwKOSuFb89.7Ko7NiAn.KllKh/KcaDAibeomZVa8Xe','0812312312312',NULL,'pelanggan',NULL,'2026-04-12 22:52:55','2026-04-12 22:52:55'),(15,'Fattah','24_achmadfattah@student.smkti.net',NULL,'$2y$12$5Z9sUM0Re.8mtQJFAcA3pezeGP1Se13rulbGg2mS6J5OojqZMJ8cy','08127371321','jln.jln','pelanggan',NULL,'2026-04-12 22:54:08','2026-04-12 22:54:08'),(16,'kacungfadlan','iyain@gmail.com',NULL,'$2y$12$76xX5xIfVENURf6w8.lQdOszr6w3AUy1fV1eWY9z5kJy5a8vqQS2.','0812636132123','jln.jln juanda','pelanggan',NULL,'2026-04-19 19:28:06','2026-04-19 19:28:06'),(17,'Fattah','yasudah@gmail.com','2026-04-01 07:14:49','$2y$12$yLds2FMfPe8azboRsdOKk.nrJ.dMyZ8kcGywqGuefQ1dvzVdAbkxS','08317647813','jln.jln','pelanggan',NULL,'2026-04-21 22:44:44','2026-04-21 23:32:36'),(18,'reyfan','reyfan@gmail.com','2026-04-01 07:17:00','$2y$12$ZT7tiNerAVw3AqnG.uhC3Of.wteBhfzPyn6tTrMAWjZQ/9UEM6QBO','08123123','jadslsasd','operator',NULL,'2026-04-21 23:16:42','2026-04-21 23:16:42'),(19,'tom','suki@gmail.com',NULL,'$2y$12$.QWLIaIlPnxM//BAZecr0eRshoPZMDRpQdrBJshYK3uMIA0haPg/C','+6281254349776','Jln ngawi','pelanggan',NULL,'2026-10-11 01:35:08','2026-10-11 01:35:08');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-12 16:26:38
