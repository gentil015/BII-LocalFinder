-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2026 at 09:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bii_localfinder`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL COMMENT 'login, logout, settings_change, etc.',
  `activity_details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ml_predictions_log`
--

CREATE TABLE `ml_predictions_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) NOT NULL,
  `predicted_score` decimal(8,6) NOT NULL,
  `actual_outcome` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider_id` (`provider_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` text DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_users`
--

CREATE TABLE `blocked_users` (
  `id` int(11) NOT NULL,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_description` text NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `provider_share_id` int(11) DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `booking_lead_time` varchar(255) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `ai_generated` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `provider_id`, `location`, `service_id`, `service_description`, `amount`, `payment_status`, `preferred_date`, `preferred_time`, `status`, `provider_share_id`, `cancellation_reason`, `cancelled_at`, `created_at`, `updated_at`, `booking_lead_time`, `responded_at`, `ai_generated`) VALUES
(1, 11, 12, NULL, NULL, 'I need someone who can clean my window house', 0.00, 'pending', '2025-11-29', NULL, 'completed', NULL, NULL, NULL, '2025-11-27 15:47:00', '2025-12-15 17:31:10', NULL, NULL, 0),
(2, 11, 12, NULL, NULL, 'I&#039;m interested in the &quot;Cleaning windows&quot; service.', 0.00, 'pending', '2025-12-04', '05:04:00', 'completed', NULL, NULL, NULL, '2025-11-28 08:59:20', '2025-12-15 17:31:10', NULL, NULL, 0),
(3, 11, 16, NULL, NULL, 'I&#039;m interested in the &quot;Wedding Drive&quot; service.', 0.00, 'pending', '2025-12-07', '04:40:00', 'completed', NULL, NULL, NULL, '2025-11-29 12:34:40', '2025-12-15 17:31:10', NULL, NULL, 0),
(4, 31, 12, NULL, NULL, 'I need some on who can clean my window.', 0.00, 'pending', '2025-12-04', '10:05:00', 'completed', NULL, NULL, NULL, '2025-12-01 18:02:17', '2025-12-15 17:31:10', NULL, NULL, 0),
(9, 11, 12, 'Kayonza', NULL, 'Need a house cleaner in kayonza next Monday for deep cleaning', 0.00, 'pending', '2025-12-08', '10:00:00', 'cancelled', NULL, NULL, NULL, '2025-12-03 06:29:21', '2025-12-15 17:31:10', NULL, NULL, 1),
(10, 11, 12, 'Kayonza', NULL, 'Need a house cleaner in kayonza next Monday for deep cleaning', 0.00, 'pending', '2025-12-08', '10:00:00', 'cancelled', NULL, NULL, NULL, '2025-12-03 06:31:37', '2025-12-15 17:36:18', NULL, NULL, 1),
(11, 11, 12, 'Kayonza', NULL, 'Need a house cleaner in kayonza next Monday for deep cleaning', 0.00, 'pending', '2025-12-08', '10:00:00', 'cancelled', NULL, 'too expensive', '2026-03-21 11:06:11', '2025-12-03 06:33:06', '2026-03-21 18:06:11', NULL, NULL, 1),
(12, 11, 15, 'Not specified', NULL, 'I need somw one to fix my toilet pipe', 0.00, 'pending', '2025-12-04', '10:00:00', 'pending', NULL, NULL, NULL, '2025-12-03 07:45:20', '2025-12-15 17:31:10', NULL, NULL, 1),
(13, 11, 15, 'Not specified', NULL, 'I need somw one to fix my toilet pipe', 0.00, 'pending', '2025-12-04', '10:00:00', 'pending', NULL, NULL, NULL, '2025-12-03 07:47:34', '2025-12-15 17:31:10', NULL, NULL, 1),
(14, 11, 16, 'Not specified', NULL, 'help me to find driver my balance is 2000 per hour', 0.00, 'pending', '2025-12-04', '10:00:00', 'pending', NULL, NULL, NULL, '2025-12-03 09:31:50', '2025-12-15 17:31:10', NULL, NULL, 1),
(15, 11, 16, 'Rubavu', NULL, 'I need driver who drive me to kigali from rubavu just now', 0.00, 'pending', '2025-12-03', '10:00:00', 'pending', NULL, NULL, NULL, '2025-12-03 11:20:37', '2025-12-15 17:31:10', NULL, NULL, 1),
(16, 31, 12, 'Not specified', NULL, 'I need driver urgent', 0.00, 'pending', '2025-12-04', '20:00:00', 'pending', NULL, NULL, NULL, '2025-12-04 18:48:59', '2025-12-15 17:31:10', NULL, NULL, 1),
(17, 31, 12, 'Not specified', NULL, 'I need driver', 0.00, 'pending', '2025-12-06', '10:00:00', 'pending', NULL, NULL, NULL, '2025-12-05 09:30:12', '2025-12-15 17:31:10', NULL, NULL, 1),
(18, 31, 12, 'Huye', NULL, 'I need driver just now I am located in huye', 0.00, 'pending', '2025-12-05', '10:00:00', 'pending', NULL, NULL, NULL, '2025-12-05 10:15:48', '2025-12-15 17:31:10', NULL, NULL, 1),
(19, 31, 12, NULL, 47, 'I need dri', 20000.00, 'pending', '2025-12-25', '09:38:00', 'completed', NULL, NULL, NULL, '2025-12-13 17:38:09', '2025-12-15 17:31:07', NULL, NULL, 0),
(20, 32, 12, NULL, 47, 'I need the driver who will drive me in every movement I made', NULL, 'pending', '2025-12-25', '00:00:00', 'confirmed', NULL, NULL, NULL, '2025-12-18 07:53:53', '2025-12-20 16:05:55', NULL, NULL, 0),
(22, 33, 12, NULL, 47, 'I need driver who drive my child on school', NULL, 'completed', '2025-12-26', '09:30:00', 'completed', NULL, NULL, NULL, '2025-12-18 08:30:37', '2025-12-18 08:35:04', NULL, NULL, 0),
(24, 32, 14, NULL, NULL, 'I need provider', NULL, 'pending', '2025-12-25', '00:00:00', 'pending', NULL, NULL, NULL, '2025-12-18 13:31:10', '2025-12-18 13:31:10', NULL, NULL, 0),
(25, 32, 12, NULL, 47, 'I need the assitant driver', NULL, 'pending', '2025-12-31', '00:00:00', 'cancelled', NULL, NULL, NULL, '2025-12-26 12:02:25', '2025-12-26 12:26:27', NULL, NULL, 0),
(27, 32, 12, NULL, 48, 'Price negotiation offer', NULL, 'pending', '2025-12-26', NULL, '', NULL, NULL, NULL, '2025-12-26 12:18:08', '2025-12-26 12:18:08', NULL, NULL, 0),
(28, 32, 12, NULL, 48, 'Price negotiation offer', NULL, 'pending', '2025-12-26', NULL, '', NULL, NULL, NULL, '2025-12-26 12:22:22', '2025-12-26 12:22:22', NULL, NULL, 0),
(30, 32, 12, NULL, 48, 'Price negotiation offer', NULL, 'pending', '2025-12-26', NULL, '', NULL, NULL, NULL, '2025-12-26 12:25:38', '2025-12-26 12:25:38', NULL, NULL, 0),
(31, 32, 12, NULL, 48, 'Price negotiation offer', NULL, 'pending', '2025-12-27', NULL, '', NULL, NULL, NULL, '2025-12-27 08:46:25', '2025-12-27 08:46:25', NULL, NULL, 0),
(32, 32, 12, NULL, 48, 'for me I live nearest of airport', NULL, 'pending', '2025-12-27', NULL, '', NULL, NULL, NULL, '2025-12-27 15:59:26', '2025-12-27 15:59:26', NULL, NULL, 0),
(33, 32, 12, NULL, 48, 'Price negotiation offer', 40000.00, 'completed', '2026-01-15', NULL, 'completed', NULL, NULL, NULL, '2026-01-15 20:36:50', '2026-01-15 20:41:04', NULL, '2026-01-15 12:39:32', 0),
(34, 32, 12, NULL, 47, 'I need driver who will drive in all week travel.', NULL, 'pending', '2026-03-17', '10:00:00', 'pending', NULL, NULL, NULL, '2026-02-28 17:54:54', '2026-02-28 17:54:54', NULL, NULL, 0),
(35, 32, 12, '', 47, 'I need the some one who can drive in my weddings', NULL, 'pending', '2026-03-19', '09:00:00', 'confirmed', NULL, NULL, NULL, '2026-03-09 20:53:27', '2026-03-09 20:57:25', NULL, NULL, 0),
(36, 32, 12, '', 47, 'Come early', NULL, 'pending', '2026-03-27', '14:00:00', 'pending', NULL, NULL, NULL, '2026-03-09 21:27:06', '2026-03-09 21:27:06', NULL, NULL, 0),
(37, 32, 12, '', 48, ';hluh/.hkl/jiof;shvjsf;jbhvsdfjlbvl;shvbsjl/sjbhvsl/gvbaql/', NULL, 'pending', '2026-03-21', '14:00:00', 'cancelled', NULL, NULL, NULL, '2026-03-10 20:41:06', '2026-03-12 18:52:45', NULL, NULL, 0),
(38, 32, 12, '', 48, 'I need someone to fix my lick pop', 60000.00, 'pending', '2026-03-26', '10:30:00', 'pending', NULL, NULL, NULL, '2026-03-20 16:16:56', '2026-03-20 16:16:56', NULL, NULL, 0),
(39, 31, 12, '', 48, 'jhrdstgretgzserysxg', 78943.00, 'pending', '2026-03-29', '10:30:00', 'pending', NULL, NULL, NULL, '2026-03-21 12:07:42', '2026-03-21 12:07:42', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `booking_notifications`
--

CREATE TABLE `booking_notifications` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `recipient_type` enum('client','provider') DEFAULT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `notification_type` enum('email','sms') DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_notifications`
--

INSERT INTO `booking_notifications` (`id`, `booking_id`, `recipient_type`, `recipient_id`, `notification_type`, `sent_at`) VALUES
(1, 11, NULL, 26, '', '2025-12-02 22:33:09'),
(2, 13, NULL, 29, '', '2025-12-02 23:47:41'),
(3, 14, NULL, 30, '', '2025-12-03 01:31:54'),
(4, 15, NULL, 30, '', '2025-12-03 03:20:41'),
(5, 16, NULL, 26, '', '2025-12-04 10:49:08'),
(6, 17, NULL, 26, '', '2025-12-05 01:30:17'),
(7, 18, NULL, 26, '', '2025-12-05 02:15:53');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `keywords` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_premium` tinyint(1) DEFAULT 0,
  `monthly_fee` decimal(10,2) DEFAULT 0.00,
  `is_ai_enabled` tinyint(1) DEFAULT 0,
  `ai_keywords` text DEFAULT NULL,
  `payment_types` varchar(255) DEFAULT NULL,
  `default_payment_type` varchar(50) DEFAULT 'per_service'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `keywords`, `icon`, `description`, `is_active`, `is_premium`, `monthly_fee`, `is_ai_enabled`, `ai_keywords`, `payment_types`, `default_payment_type`) VALUES
(1, 'Electricians', 'electricity,electrical,wiring,lights,lighting,power,circuit,breaker,outlet,switch,socket', 'fa-bolt', 'Electrical installation and repairs', 1, 0, 0.00, 1, 'electrician, electricity, electric, power, wiring, wire, circuit, socket, outlet, switch, fuse, breaker, distribution board, db box, meter, transformer, voltage, current, overload, short circuit, tripping, light, lighting, bulb, lamp, chandelier, install light, repair light, electrical installation, electrical repair, rewiring, connect, disconnect, sparks, shock, no power, power cut, blackout, burnt smell, loose connection, extension, electrical fault, electrical maintenance', NULL, 'per_service'),
(2, 'Plumbers', 'plumbing,pipe,leak,water,tap,faucet,toilet,drain,shower,sink,bathroom,kitchen', 'fa-wrench', 'Plumbing services and installations', 1, 0, 0.00, 1, 'plumber, plumbing, pipe, pipes, water pipe, leakage, leak, leaking, burst pipe, tap, faucet, sink, toilet, WC, shower, bathtub, drainage, drain, blocked drain, clogged, sewage, sewer, septic, flushing problem, water tank, tank installation, water heater, geyser, boiler, low pressure, no water, plumbing repair, pipe installation, fix tap, fix toilet, bathroom repair, kitchen plumbing, plumber service', NULL, 'per_service'),
(3, 'Cleaner', 'cleaning,clean,housekeeping,maid,window,windows,mop,floor,dust,vacuum,tidy,organize', 'fa-broom', 'Cleaning services for homes and offices', 1, 0, 0.00, 1, '', NULL, 'per_service'),
(4, 'Mechanics', 'mechanic,car,vehicle,repair,engine,brake,tire,wheel,oil change,maintenance,garage,automotive', 'fa-car', 'Vehicle repair and maintenance', 1, 0, 0.00, 1, 'mechanic, car repair, auto repair, vehicle repair, garage, engine, motor, transmission, gearbox, clutch, brakes, brake pads, tires, wheels, wheel balance, alignment, suspension, shock absorber, radiator, overheating, oil change, service, maintenance, diagnose, check engine, engine light, battery, dead battery, jumpstart, starter, alternator, fuel pump, exhaust, noise, vibration, leaking, smoke, breakdown, tow, towing, motorcycle repair, moto repair, spark plug, filter, service car, fix car', NULL, 'per_service'),
(5, 'Carpenters', 'carpenter,wood,woodwork,furniture,table,chair,cabinet,door,window,windows,shelf,repair', 'fa-hammer', 'Woodwork and furniture', 1, 0, 0.00, 1, 'carpenter, wood, timber, lumber, build, repair, fix, install, assemble, carve, cut, sand, polish, measure, design, door, window, cabinet, wardrobe, table, chair, bed, shelf, cupboard, furniture, desk, drawer, frame, ceiling boards, wooden floor, broken, loose, cracked, damaged, rotten wood, not closing, squeaking, unstable, custom furniture, woodwork, furniture installation, furniture assembly, carpentry work, wood repair, joinery', NULL, 'per_service'),
(6, 'Painter', 'painter,paint,wall,ceiling,brush,roller,spray,color,decorate,renovate,interior,exterior', 'fa-paint-roller', 'House and office painting', 1, 0, 0.00, 1, 'painter, painting, paint, wall paint, house painting, room painting, exterior painting, interior painting, brush, roller, spray paint, color change, repaint, wall decoration, wallpaper, wall finish, ceiling paint, wall cracks, patching, sanding, primer, undercoat, paint job, paint repair, peeling paint, faded paint, mural, decoration, varnish, coating, paint maintenance', NULL, 'per_service'),
(7, 'Gardeners', 'gardener,garden,landscape,lawn,mow,trim,plant,flower,tree,weed,irrigation,soil', 'fa-leaf', 'Gardening and landscaping', 1, 0, 0.00, 1, 'gardener, gardening, garden, yard, lawn, grass, plants, flowers, trees, shrubs, hedge, trimming, pruning, cutting, mowing, watering, planting, weeding, landscaping, landscape, soil, fertilizer, compost, garden cleaning, lawn care, garden maintenance, tree removal, hedge cutting, bush trimming, irrigation, sprinkler, outdoor maintenance, backyard, front yard, tree', NULL, 'per_service'),
(8, 'Construction', 'construction,build,builder,contractor,renovation,remodel,foundation,roof,wall,structure', 'fa-hard-hat', 'Building and construction work', 1, 0, 0.00, 1, 'construction, builder, building, contractor, site, foundation, cement, concrete, bricks, blocks, stones, sand, gravel, steel, iron bars, rebar, roofing, roof, tiles, ceiling, plaster, masonry, wall, floor, paving, renovation, remodeling, extension, structure, trench, excavation, digging, measuring, leveling, plan, blueprint, architect, engineer, project, scaffolding, waterproofing, finishing, drywall, partition, staircase, gutter, drainage, construction repair, construction work, house construction, maintenance', NULL, 'per_service'),
(9, 'Drivers', 'driver,drive,transport,car,vehicle,chauffeur,ride,taxi,delivery,move,relocation', 'fa-id-card', 'Drive people', 1, 0, 0.00, 1, 'driver, drive, transport, car, vehicle, taxi, cab, ride, pickup, drop, dropoff, delivery, chauffeur, personal driver, private driver, motorcycle, moto, boda, truck, bus, van, minibus, transport service, moving, relocation, travel, trip, long distance, short distance, airport pickup, airport drop, carry, take me, take us, lift, drive me, car hire, ride service, transport assistance', NULL, 'per_service'),
(10, 'Barber', NULL, 'fa-solid fa-scissors', 'Cutting the hair', 1, 1, 8000.00, 1, 'hair,cut,shave,shaving', NULL, 'per_service'),
(13, 'Welders', NULL, 'fa-hammer', '', 1, 0, 0.00, 1, 'welder, welding, metal, steel, iron, gate repair, steel gate, metal gate, window grill, grill repair, metal fabrication, metal fixing, broken metal, weld, welding machine, metal frame, steel door repair, iron sheet welding, metal joint, metal crack, weld broken parts', NULL, 'per_service'),
(14, 'Mason', NULL, 'fa-hard-hat', 'Skilled masonry and building services including bricklaying, concrete work, wall construction, house extensions, renovations, floor leveling, plastering, tiling, paving, and general structural repairs for homes and commercial buildings.', 1, 0, 0.00, 1, 'mason, builder, masonry, construction, bricklayer, bricks, cement, concrete, blockwork, plaster, wall building, house construction, renovation, remodeling, extension, floor leveling, tiling, paving, foundation, structure, pillar, slab work, staircase construction, demolition, construction repair,mason, builder, construction, cement, concrete, bricks, blocks, plaster, wall, wall building, wall repair, broken wall, house extension, foundation, slab, construction repair, tiling, paving, floor repair, floor installation, cement work, concrete work, brick work, block laying, renovate, fix wall, construction worker, site work', NULL, 'per_service'),
(15, 'Roofer', NULL, 'fa-house-damage', 'Professional roofing services including installation, repair, waterproofing, replacement of roofing sheets or tiles, fixing leaks, sealing, gutter repair, and full roof maintenance for residential and commercial buildings.', 1, 0, 0.00, 1, 'roofer, roofing, roof repair, roof installation, iron sheets, roof tiles, roofing sheets, leaking roof, rain leak, roof maintenance, ceiling damage, roof replacement, waterproofing, roof inspection, roof fixing, roof renovation, roof sealing, gutter repair, roof structure,roofer, roofing, roof, leak, leaking roof, rain leak, iron sheet roof, tile roof, roof repair, roof installation, broken roof, damaged roof, ceiling leak, waterproofing, roof sealing, gutter repair, roof tiles, roofing sheets, roof replacement, roof maintenance, roof fix', NULL, 'per_service'),
(16, 'Tailor / Fashion Designer', NULL, 'fa-scissors', 'Professional tailoring and fashion design services including sewing, clothing repairs, adjustments, custom dressmaking, suit creation, uniform production, embroidery, and fabric-based garment design for men, women, and children.', 1, 0, 0.00, 1, 'tailor, tailoring, sewing, stitching, clothes repair, dressmaking, fashion designer, custom clothes, measurements, hemming, fabric, suit making, dress design, clothes adjustment, alterations, uniform making, embroidery, sewing service, garment repair, fashion design service,tailor, sewing, stitch, stitching, fabric, clothes repair, fix clothes, adjust clothes, resize clothes, dressmaking, make dress, suit design, suit making, clothes alteration, repair torn clothes, school uniform, custom clothes, fashion designer, design outfit, sewing machine', NULL, 'per_service');

-- --------------------------------------------------------

--
-- Table structure for table `click_logs`
--

CREATE TABLE `click_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(255) NOT NULL,
  `target_type` varchar(255) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `page_url` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `click_logs`
--

INSERT INTO `click_logs` (`id`, `user_id`, `event_type`, `target_type`, `target_id`, `page_url`, `metadata`, `ip_address`, `user_agent`, `session_id`, `created_at`) VALUES
(1, NULL, 'click_test', 'test_target', 123, 'http://localhost/Bii_localFinder/client/providers.php', '{\\', '::1', 'curl/8.13.0', 'eijbjpp4qvs055jae4cm09fsu7', '2026-03-21 17:18:57');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) NOT NULL,
  `complaint_type` varchar(50) NOT NULL,
  `description` longtext NOT NULL,
  `priority_level` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved') DEFAULT 'open',
  `anonymous_report` tinyint(1) DEFAULT 0,
  `user_feedback` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `assigned_admin_id`, `user_id`, `provider_id`, `complaint_type`, `description`, `priority_level`, `status`, `anonymous_report`, `user_feedback`, `created_at`, `updated_at`) VALUES
(2, NULL, 11, 12, 'property_damage', 'this provide he was broked my windows', 'medium', 'open', 0, 'Please help me', '2025-11-28 10:35:53', '2025-11-28 10:39:17'),
(3, NULL, 11, 12, 'property_damage', 'this provide he was broked my windows', 'medium', 'open', 0, NULL, '2025-11-28 10:38:08', '2025-11-29 06:58:57'),
(4, 19, 30, 12, 'fraud', 'jgvkblhuiohp;jihp', 'medium', 'resolved', 0, NULL, '2025-11-29 12:48:21', '2025-11-29 18:46:43'),
(5, NULL, 11, 15, 'pricing', 'thid client request me over price than we deal', 'medium', 'open', 0, NULL, '2025-12-01 19:26:09', '2025-12-01 19:26:09'),
(6, NULL, 33, 12, 'professional_behavior', 'Gentil stolen my phone', 'high', 'open', 0, NULL, '2025-12-18 08:40:39', '2025-12-18 08:40:39');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_attachments`
--

CREATE TABLE `complaint_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_logs`
--

CREATE TABLE `complaint_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_logs`
--

INSERT INTO `complaint_logs` (`id`, `complaint_id`, `action`, `details`, `admin_id`, `created_at`) VALUES
(1, 4, 'assignment', 'Assigned to admin ID: 19', 19, '2025-11-29 12:50:15');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_notes`
--

CREATE TABLE `complaint_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `note` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_responses`
--

CREATE TABLE `complaint_responses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `complaint_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint_responses`
--

INSERT INTO `complaint_responses` (`id`, `complaint_id`, `admin_id`, `message`, `created_at`, `updated_at`) VALUES
(1, 3, 19, 'Dear user we are apologies you for this problem caused by provider you can tell us the provider name.', '2025-11-29 06:58:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `name`, `code`, `created_at`) VALUES
(1, 'Gasabo', 'GAS', '2025-11-24 18:24:48'),
(2, 'Kicukiro', 'KIC', '2025-11-24 18:24:48'),
(3, 'Nyarugenge', 'NYA', '2025-11-24 18:24:48'),
(4, 'Bugesera', 'BUG', '2025-11-24 18:24:48'),
(5, 'Gatsibo', 'GAT', '2025-11-24 18:24:48'),
(6, 'Kayonza', 'KAY', '2025-11-24 18:24:48'),
(7, 'Kirehe', 'KIR', '2025-11-24 18:24:48'),
(8, 'Ngoma', 'NGO', '2025-11-24 18:24:48'),
(9, 'Nyagatare', 'NYG', '2025-11-24 18:24:48'),
(10, 'Rwamagana', 'RWA', '2025-11-24 18:24:48'),
(11, 'Burera', 'BUR', '2025-11-24 18:24:48'),
(12, 'Gakenke', 'GAK', '2025-11-24 18:24:48'),
(13, 'Gicumbi', 'GIC', '2025-11-24 18:24:48'),
(14, 'Musanze', 'MUS', '2025-11-24 18:24:48'),
(15, 'Rulindo', 'RUL', '2025-11-24 18:24:48'),
(16, 'Gisagara', 'GIS', '2025-11-24 18:24:48'),
(17, 'Huye', 'HUY', '2025-11-24 18:24:48'),
(18, 'Kamonyi', 'KAM', '2025-11-24 18:24:48'),
(19, 'Muhanga', 'MUH', '2025-11-24 18:24:48'),
(20, 'Nyamagabe', 'NYM', '2025-11-24 18:24:48'),
(21, 'Nyanza', 'NYN', '2025-11-24 18:24:48'),
(22, 'Nyaruguru', 'NYR', '2025-11-24 18:24:48'),
(23, 'Ruhango', 'RUH', '2025-11-24 18:24:48'),
(24, 'Karongi', 'KAR', '2025-11-24 18:24:48'),
(25, 'Ngororero', 'NGO', '2025-11-24 18:24:48'),
(26, 'Nyabihu', 'NYB', '2025-11-24 18:24:48'),
(27, 'Nyamasheke', 'NYM', '2025-11-24 18:24:48'),
(28, 'Rubavu', 'RUB', '2025-11-24 18:24:48'),
(29, 'Rusizi', 'RUS', '2025-11-24 18:24:48'),
(30, 'Rutsiro', 'RUT', '2025-11-24 18:24:48'),
(31, 'Gasabo', 'GAS', '2025-11-24 18:28:16'),
(32, 'Kicukiro', 'KIC', '2025-11-24 18:28:16'),
(33, 'Nyarugenge', 'NYA', '2025-11-24 18:28:16'),
(34, 'Bugesera', 'BUG', '2025-11-24 18:28:16'),
(35, 'Gatsibo', 'GAT', '2025-11-24 18:28:16'),
(36, 'Kayonza', 'KAY', '2025-11-24 18:28:16'),
(37, 'Kirehe', 'KIR', '2025-11-24 18:28:16'),
(38, 'Ngoma', 'NGO', '2025-11-24 18:28:16'),
(39, 'Nyagatare', 'NYG', '2025-11-24 18:28:16'),
(40, 'Rwamagana', 'RWA', '2025-11-24 18:28:16'),
(41, 'Burera', 'BUR', '2025-11-24 18:28:16'),
(42, 'Gakenke', 'GAK', '2025-11-24 18:28:16'),
(43, 'Gicumbi', 'GIC', '2025-11-24 18:28:16'),
(44, 'Musanze', 'MUS', '2025-11-24 18:28:16'),
(45, 'Rulindo', 'RUL', '2025-11-24 18:28:16'),
(46, 'Gisagara', 'GIS', '2025-11-24 18:28:16'),
(47, 'Huye', 'HUY', '2025-11-24 18:28:16'),
(48, 'Kamonyi', 'KAM', '2025-11-24 18:28:16'),
(49, 'Muhanga', 'MUH', '2025-11-24 18:28:16'),
(50, 'Nyamagabe', 'NYM', '2025-11-24 18:28:16'),
(51, 'Nyanza', 'NYN', '2025-11-24 18:28:16'),
(52, 'Nyaruguru', 'NYR', '2025-11-24 18:28:16'),
(53, 'Ruhango', 'RUH', '2025-11-24 18:28:16'),
(54, 'Karongi', 'KAR', '2025-11-24 18:28:16'),
(55, 'Ngororero', 'NGO', '2025-11-24 18:28:16'),
(56, 'Nyabihu', 'NYB', '2025-11-24 18:28:16'),
(57, 'Nyamasheke', 'NYM', '2025-11-24 18:28:16'),
(58, 'Rubavu', 'RUB', '2025-11-24 18:28:16'),
(59, 'Rusizi', 'RUS', '2025-11-24 18:28:16'),
(60, 'Rutsiro', 'RUT', '2025-11-24 18:28:16');

-- --------------------------------------------------------

--
-- Table structure for table `event_logs`
--

CREATE TABLE `event_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_logs`
--

INSERT INTO `event_logs` (`id`, `user_id`, `session_id`, `event_type`, `entity_type`, `entity_id`, `metadata`, `created_at`) VALUES
(1, 1, 'ol336p01r2rm7457edndjiefg0', 'test_event', 'test_entity', 123, '{\"test\":\"data\"}', '2026-03-21 18:58:54'),
(2, 1, 'um0v6o41g5qrd4kctu2k6228h0', 'test_event', 'test_entity', 123, '{\"test\":\"data\"}', '2026-03-21 18:59:06'),
(3, 1, 'um0v6o41g5qrd4kctu2k6228h0', 'search', 'search', NULL, '{\"search_query\":\"test search\",\"search_type\":\"provider\",\"filters\":{\"location\":\"Kigali\"},\"results_count\":5}', '2026-03-21 18:59:06'),
(4, 1, 'um0v6o41g5qrd4kctu2k6228h0', 'provider_view', 'provider', 456, '{\"action\":\"view\",\"provider_id\":456,\"source\":\"test\"}', '2026-03-21 18:59:07');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `client_id`, `provider_id`, `created_at`) VALUES
(2, 11, 12, '2025-11-27 20:30:55'),
(3, 11, 6, '2025-11-27 20:40:22'),
(4, 11, 4, '2025-11-27 20:40:36'),
(5, 31, 16, '2025-12-11 19:33:20');

-- --------------------------------------------------------

--
-- Table structure for table `finalized_service_prices`
--

CREATE TABLE `finalized_service_prices` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `finalized_price` decimal(10,2) NOT NULL COMMENT 'Final agreed price',
  `negotiation_rounds` int(11) DEFAULT 1 COMMENT 'Number of rounds it took',
  `client_final_offer_id` int(11) DEFAULT NULL,
  `provider_final_counteroffer_id` int(11) DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `finalized_service_prices`
--

INSERT INTO `finalized_service_prices` (`id`, `booking_id`, `service_id`, `client_id`, `provider_id`, `finalized_price`, `negotiation_rounds`, `client_final_offer_id`, `provider_final_counteroffer_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 31, 48, 32, 26, 55000.00, 2, NULL, 2, 'active', '2025-12-27 08:49:03', '2025-12-27 08:50:23'),
(5, 32, 48, 32, 26, 50000.00, 2, NULL, 3, 'active', '2025-12-27 16:01:56', '2025-12-27 16:01:56'),
(6, 33, 48, 32, 26, 40000.00, 1, 6, NULL, 'active', '2026-01-15 20:38:08', '2026-01-15 20:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `google_calendar_tokens`
--

CREATE TABLE `google_calendar_tokens` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `access_token` longtext NOT NULL,
  `refresh_token` longtext DEFAULT NULL,
  `expires_in` int(11) DEFAULT NULL,
  `expires_at` int(11) DEFAULT NULL,
  `token_type` varchar(50) DEFAULT 'Bearer',
  `scope` longtext DEFAULT NULL,
  `authenticated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `location_coordinates`
--

CREATE TABLE `location_coordinates` (
  `id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `district` varchar(50) DEFAULT NULL,
  `sector` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `location_coordinates`
--

INSERT INTO `location_coordinates` (`id`, `location_name`, `district`, `sector`, `latitude`, `longitude`, `created_at`) VALUES
(1, 'Gasabo', 'Gasabo', NULL, -1.94850000, 30.12340000, '2025-12-05 10:07:53'),
(2, 'Kicukiro', 'Kicukiro', NULL, -1.96550000, 30.03450000, '2025-12-05 10:07:53'),
(3, 'Nyarugenge', 'Nyarugenge', NULL, -1.95080000, 30.05570000, '2025-12-05 10:07:53'),
(4, 'Kimironko', 'Gasabo', 'Kimironko', -1.94520000, 30.15670000, '2025-12-05 10:07:53'),
(5, 'Remera', 'Gasabo', 'Remera', -1.93890000, 30.09450000, '2025-12-05 10:07:53'),
(6, 'Rusororo', 'Gasabo', 'Rusororo', -1.93660000, 30.18650000, '2025-12-05 10:07:53'),
(7, 'Kacyiru', 'Gasabo', 'Kacyiru', -1.95780000, 30.10890000, '2025-12-05 10:07:53'),
(8, 'Gisozi', 'Gasabo', 'Gisozi', -1.94340000, 30.08760000, '2025-12-05 10:07:53'),
(9, 'Nyarutarama', 'Gasabo', 'Nyarutarama', -1.96120000, 30.12340000, '2025-12-05 10:07:53'),
(10, 'Kanombe', 'Gasabo', 'Kanombe', -1.96980000, 30.14400000, '2025-12-05 10:07:53'),
(11, 'Gikondo', 'Kicukiro', 'Gikondo', -1.96780000, 30.01230000, '2025-12-05 10:07:53'),
(12, 'Kagarama', 'Kicukiro', 'Kagarama', -1.97250000, 30.07110000, '2025-12-05 10:07:53'),
(13, 'Nyarugunga', 'Kicukiro', 'Nyarugunga', -1.97890000, 30.02340000, '2025-12-05 10:07:53'),
(14, 'Gahanga', 'Kicukiro', 'Gahanga', -1.98450000, 30.04560000, '2025-12-05 10:07:53'),
(15, 'Kigarama', 'Kicukiro', 'Kigarama', -1.97340000, 30.09410000, '2025-12-05 10:07:53'),
(16, 'Nyamirambo', 'Nyarugenge', 'Nyamirambo', -1.96010000, 30.02340000, '2025-12-05 10:07:53'),
(17, 'Muhima', 'Nyarugenge', 'Muhima', -1.95450000, 30.04890000, '2025-12-05 10:07:53'),
(18, 'Kimisagara', 'Nyarugenge', 'Kimisagara', -1.96340000, 30.03450000, '2025-12-05 10:07:53'),
(19, 'Rwezamenyo', 'Nyarugenge', 'Rwezamenyo', -1.95100000, 30.03420000, '2025-12-05 10:07:53'),
(20, 'Musanze', 'Musanze', NULL, -1.49770000, 29.63710000, '2025-12-05 10:07:53'),
(21, 'Muhoza', 'Musanze', 'Muhoza', -1.50510000, 29.63000000, '2025-12-05 10:07:53'),
(22, 'Cyuve', 'Musanze', 'Cyuve', -1.49220000, 29.65330000, '2025-12-05 10:07:53'),
(23, 'Busogo', 'Musanze', 'Busogo', -1.45630000, 29.63600000, '2025-12-05 10:07:53'),
(24, 'Burera', 'Burera', NULL, -1.47000000, 29.85000000, '2025-12-05 10:07:53'),
(25, 'Cyanika', 'Burera', 'Cyanika', -1.44300000, 29.81900000, '2025-12-05 10:07:53'),
(26, 'Ruhunde', 'Burera', 'Ruhunde', -1.49000000, 29.86000000, '2025-12-05 10:07:53'),
(27, 'Gakenke', 'Gakenke', NULL, -1.69300000, 29.78300000, '2025-12-05 10:07:53'),
(28, 'Gakenke Town', 'Gakenke', 'Gakenke', -1.69350000, 29.78000000, '2025-12-05 10:07:53'),
(29, 'Rulindo', 'Rulindo', NULL, -1.68000000, 30.06000000, '2025-12-05 10:07:53'),
(30, 'Kinihira', 'Rulindo', 'Kinihira', -1.64300000, 30.06400000, '2025-12-05 10:07:53'),
(31, 'Rubavu', 'Rubavu', NULL, -2.05970000, 29.25540000, '2025-12-05 10:07:53'),
(32, 'Gisenyi', 'Rubavu', 'Gisenyi', -2.06390000, 29.25340000, '2025-12-05 10:07:53'),
(33, 'Kanama', 'Rubavu', 'Kanama', -2.05120000, 29.30780000, '2025-12-05 10:07:53'),
(34, 'Karongi', 'Karongi', NULL, -2.06410000, 29.25490000, '2025-12-05 10:07:53'),
(35, 'Kibuye', 'Karongi', 'Kibuye', -2.06600000, 29.25830000, '2025-12-05 10:07:53'),
(36, 'Bwishyura', 'Karongi', 'Bwishyura', -2.06010000, 29.34700000, '2025-12-05 10:07:53'),
(37, 'Rutsiro', 'Rutsiro', NULL, -1.86300000, 29.30780000, '2025-12-05 10:07:53'),
(38, 'Gihango', 'Rutsiro', 'Gihango', -1.85800000, 29.31520000, '2025-12-05 10:07:53'),
(39, 'Rusizi', 'Rusizi', NULL, -2.50660000, 29.03280000, '2025-12-05 10:07:53'),
(40, 'Cyangugu', 'Rusizi', 'Cyangugu', -2.49110000, 29.25110000, '2025-12-05 10:07:53'),
(41, 'Kamembe', 'Rusizi', 'Kamembe', -2.47000000, 28.90000000, '2025-12-05 10:07:53'),
(42, 'Huye', 'Huye', NULL, -2.60470000, 29.74060000, '2025-12-05 10:07:53'),
(43, 'Ngoma - Huye Town', 'Huye', 'Ngoma', -2.60300000, 29.73900000, '2025-12-05 10:07:53'),
(44, 'Tumba', 'Huye', 'Tumba', -2.62010000, 29.73020000, '2025-12-05 10:07:53'),
(45, 'Muhanga', 'Muhanga', NULL, -2.00600000, 30.47010000, '2025-12-05 10:07:53'),
(46, 'Gitarama', 'Muhanga', 'Gitarama', -2.00820000, 30.47560000, '2025-12-05 10:07:53'),
(47, 'Nyanza', 'Nyanza', NULL, -2.42990000, 29.93870000, '2025-12-05 10:07:53'),
(48, 'Busasamana', 'Nyanza', 'Busasamana', -2.45000000, 29.92000000, '2025-12-05 10:07:53'),
(49, 'Gisagara', 'Gisagara', NULL, -2.62000000, 29.87000000, '2025-12-05 10:07:53'),
(50, 'Save', 'Gisagara', 'Save', -2.63000000, 29.88200000, '2025-12-05 10:07:53'),
(51, 'Nyaruguru', 'Nyaruguru', NULL, -2.64000000, 29.57000000, '2025-12-05 10:07:53'),
(52, 'Kibeho', 'Nyaruguru', 'Kibeho', -2.61000000, 29.60000000, '2025-12-05 10:07:53'),
(53, 'Ruhango', 'Ruhango', NULL, -2.23800000, 29.78010000, '2025-12-05 10:07:53'),
(54, 'Byimana', 'Ruhango', 'Byimana', -2.21000000, 29.77000000, '2025-12-05 10:07:53'),
(55, 'Rwamagana', 'Rwamagana', NULL, -2.14040000, 30.46530000, '2025-12-05 10:07:53'),
(56, 'Nyagasambu', 'Rwamagana', 'Nyagasambu', -2.15500000, 30.48200000, '2025-12-05 10:07:53'),
(57, 'Ngoma', 'Ngoma', NULL, -2.20800000, 30.53000000, '2025-12-05 10:07:53'),
(58, 'Kibungo', 'Ngoma', 'Kibungo', -2.15910000, 30.54230000, '2025-12-05 10:07:53'),
(59, 'Bugesera', 'Bugesera', NULL, -2.35500000, 30.15000000, '2025-12-05 10:07:53'),
(60, 'Nyamata', 'Bugesera', 'Nyamata', -2.20400000, 30.33000000, '2025-12-05 10:07:53'),
(61, 'Kayonza', 'Kayonza', NULL, -1.85200000, 30.66700000, '2025-12-05 10:07:53'),
(62, 'Mukarange', 'Kayonza', 'Mukarange', -1.88000000, 30.65000000, '2025-12-05 10:07:53'),
(63, 'Kirehe', 'Kirehe', NULL, -2.26000000, 30.71500000, '2025-12-05 10:07:53'),
(64, 'Nyamugari', 'Kirehe', 'Nyamugari', -2.36000000, 30.78000000, '2025-12-05 10:07:53'),
(65, 'Gatsibo', 'Gatsibo', NULL, -1.76400000, 30.46000000, '2025-12-05 10:07:53'),
(66, 'Kabarore', 'Gatsibo', 'Kabarore', -1.76390000, 30.45990000, '2025-12-05 10:07:53'),
(67, 'Nyagatare', 'Nyagatare', NULL, -1.29700000, 30.35000000, '2025-12-05 10:07:53'),
(68, 'Matimba', 'Nyagatare', 'Matimba', -1.42800000, 30.32000000, '2025-12-05 10:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `login_security`
--

CREATE TABLE `login_security` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_timestamp` datetime DEFAULT current_timestamp(),
  `status` enum('success','failed','locked') DEFAULT 'failed',
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `is_verified` int(11) DEFAULT 0,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_security`
--

INSERT INTO `login_security` (`id`, `user_id`, `email`, `ip_address`, `user_agent`, `login_timestamp`, `status`, `device_fingerprint`, `is_verified`, `verification_code`, `verification_expires`, `created_at`) VALUES
(1, 32, 'tuyizereaimely@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 08:20:00', 'failed', 'ced0ac43311e4bee51f1b5feaa5d8cac721d96cb402e02314d37f387ee796cdc', 0, NULL, NULL, '2026-03-02 08:20:00'),
(2, 32, 'tuyizereaimely@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 08:20:08', 'locked', 'ced0ac43311e4bee51f1b5feaa5d8cac721d96cb402e02314d37f387ee796cdc', 0, '484278', '2026-03-02 08:35:08', '2026-03-02 08:20:08'),
(3, 32, 'tuyizereaimely@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 08:21:06', 'locked', 'ced0ac43311e4bee51f1b5feaa5d8cac721d96cb402e02314d37f387ee796cdc', 0, '777955', '2026-03-02 08:36:06', '2026-03-02 08:21:06');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachment_path` varchar(255) DEFAULT NULL,
  `message_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `audio_duration` int(11) DEFAULT NULL,
  `attachment_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`, `attachment_path`, `message_type`, `file_path`, `file_size`, `audio_duration`, `attachment_type`) VALUES
(5, 11, 15, 'Booking auto-init test message 3', 0, '2026-03-10 20:33:48', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 32, 26, 'New booking created: #BK-2026-00037', 1, '2026-03-10 20:41:12', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 32, 26, 'hi', 1, '2026-03-10 20:41:26', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 32, 26, 'hi', 1, '2026-03-10 20:42:46', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 26, 32, 'how can I assist you', 1, '2026-03-10 20:46:28', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 32, 26, '', 1, '2026-03-12 22:02:53', 'uploads/chat/chat_69b3380c7318d6.96921994.jpg', NULL, NULL, NULL, NULL, NULL),
(11, 32, 26, '', 1, '2026-03-12 22:03:00', 'uploads/chat/chat_69b33813aa5781.52594177.jpg', NULL, NULL, NULL, NULL, NULL),
(12, 32, 26, '', 1, '2026-03-14 18:10:09', NULL, 'audio', 'uploads/chat/voice_69b5a480edff44.10800927.webm', 98828, 6, NULL),
(13, 32, 26, 'New booking created: #BK-2026-00038', 1, '2026-03-20 16:17:13', NULL, NULL, NULL, NULL, NULL, NULL),
(14, 32, 26, '', 1, '2026-03-20 16:18:30', NULL, 'audio', 'uploads/chat/voice_69bd735662fd31.25491916.webm', 258218, 16, NULL),
(15, 26, 32, '', 1, '2026-03-20 20:06:32', NULL, 'audio', 'uploads/chat/voice_69bda8c898cef8.77249720.webm', 94964, 6, NULL),
(16, 26, 32, '{&quot;service_name&quot;:&quot;Personal Driver (Daily Transport)&quot;,&quot;description&quot;:&quot;Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.&quot;,&quot;price&quot;:&quot;20000.00&quot;,&quot;service_id&quot;:47}', 1, '2026-03-20 22:16:51', NULL, 'service', NULL, NULL, NULL, NULL),
(17, 26, 32, '{&quot;service_name&quot;:&quot;Personal Driver (Daily Transport)&quot;,&quot;description&quot;:&quot;Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.&quot;,&quot;price&quot;:&quot;20000.00&quot;,&quot;service_id&quot;:47}', 1, '2026-03-20 22:47:28', NULL, 'service', NULL, NULL, NULL, NULL),
(18, 26, 32, '{&quot;service_name&quot;:&quot;Personal Driver (Daily Transport)&quot;,&quot;description&quot;:&quot;Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.&quot;,&quot;price&quot;:&quot;20000.00&quot;,&quot;service_id&quot;:47}', 1, '2026-03-21 07:50:27', NULL, 'service', NULL, NULL, NULL, NULL),
(19, 26, 32, '{&quot;service_name&quot;:&quot;Airport Pickup &amp;amp;amp; Drop-off Driver&quot;,&quot;description&quot;:&quot;Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.&quot;,&quot;price&quot;:&quot;15000.00&quot;,&quot;min_price&quot;:&quot;40000.00&quot;,&quot;max_price&quot;:&quot;80000.00&quot;,&quot;negotiable&quot;:true,&quot;service_id&quot;:48}', 1, '2026-03-21 08:27:17', NULL, 'service_offer', NULL, NULL, NULL, NULL),
(20, 26, 32, '{&quot;service_name&quot;:&quot;Airport Pickup &amp;amp;amp; Drop-off Driver&quot;,&quot;description&quot;:&quot;Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.&quot;,&quot;price&quot;:&quot;15000.00&quot;,&quot;min_price&quot;:&quot;40000.00&quot;,&quot;max_price&quot;:&quot;80000.00&quot;,&quot;negotiable&quot;:true,&quot;service_id&quot;:48}', 1, '2026-03-21 08:29:23', NULL, 'service_offer', NULL, NULL, NULL, NULL),
(21, 26, 32, '{&quot;service_name&quot;:&quot;Airport Pickup &amp;amp;amp; Drop-off Driver&quot;,&quot;description&quot;:&quot;Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.&quot;,&quot;price&quot;:&quot;15000.00&quot;,&quot;min_price&quot;:&quot;40000.00&quot;,&quot;max_price&quot;:&quot;80000.00&quot;,&quot;negotiable&quot;:true,&quot;service_id&quot;:48}', 1, '2026-03-21 08:33:35', NULL, 'service_offer', NULL, NULL, NULL, NULL),
(22, 26, 32, '{&quot;service_name&quot;:&quot;Airport Pickup &amp;amp;amp; Drop-off Driver&quot;,&quot;description&quot;:&quot;Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.&quot;,&quot;price&quot;:&quot;15000.00&quot;,&quot;min_price&quot;:&quot;40000.00&quot;,&quot;max_price&quot;:&quot;80000.00&quot;,&quot;negotiable&quot;:true,&quot;service_id&quot;:48}', 1, '2026-03-21 08:34:36', NULL, 'service_offer', NULL, NULL, NULL, NULL),
(23, 26, 32, '{&quot;service_name&quot;:&quot;Airport Pickup &amp;amp;amp; Drop-off Driver&quot;,&quot;description&quot;:&quot;Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.&quot;,&quot;price&quot;:&quot;15000.00&quot;,&quot;min_price&quot;:&quot;40000.00&quot;,&quot;max_price&quot;:&quot;80000.00&quot;,&quot;negotiable&quot;:true,&quot;service_id&quot;:48}', 1, '2026-03-21 08:42:59', NULL, 'service_offer', NULL, NULL, NULL, NULL),
(24, 31, 26, 'New booking created: #BK-2026-00039', 1, '2026-03-21 12:07:49', NULL, NULL, NULL, NULL, NULL, NULL),
(25, 31, 26, 'hi', 1, '2026-03-21 12:07:58', NULL, NULL, NULL, NULL, NULL, NULL),
(26, 31, 26, 'hi', 1, '2026-03-21 12:08:03', NULL, NULL, NULL, NULL, NULL, NULL),
(27, 31, 26, 'hi', 1, '2026-03-21 12:08:10', NULL, NULL, NULL, NULL, NULL, NULL),
(28, 31, 26, '', 1, '2026-03-21 12:08:54', NULL, 'audio', 'uploads/chat/voice_69be8a5679bb76.08719683.webm', 266912, 17, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ml_interactions`
--

CREATE TABLE `ml_interactions` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `views` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `messages` int(11) DEFAULT 0,
  `rating` float DEFAULT 0,
  `price` float DEFAULT 0,
  `avg_response_time` float DEFAULT 0,
  `hired` tinyint(4) DEFAULT 0,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ml_interactions`
--

INSERT INTO `ml_interactions` (`id`, `provider_id`, `views`, `clicks`, `messages`, `rating`, `price`, `avg_response_time`, `hired`, `recorded_at`) VALUES
(1, 4, 1, 0, 1, 0, 0, 24, 0, '2026-03-22 18:59:48'),
(2, 6, 0, 0, 0, 0, 4000, 24, 0, '2026-03-22 18:59:48'),
(3, 12, 1, 0, 13, 3.5, 17500, 0, 1, '2026-03-22 18:59:48'),
(4, 13, 0, 0, 0, 0, 4000, 24, 0, '2026-03-22 18:59:48'),
(5, 14, 0, 0, 0, 0, 0, 24, 0, '2026-03-22 18:59:48'),
(6, 15, 0, 0, 0, 0, 0, 24, 0, '2026-03-22 18:59:48'),
(7, 16, 0, 0, 0, 2, 0, 24, 1, '2026-03-22 18:59:48');

-- --------------------------------------------------------

--
-- Table structure for table `muted_chats`
--

CREATE TABLE `muted_chats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `muted_user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `negotiation_history`
--

CREATE TABLE `negotiation_history` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `offer_id` int(11) DEFAULT NULL,
  `counteroffer_id` int(11) DEFAULT NULL,
  `action_type` enum('offer_created','offer_accepted','offer_rejected','offer_expired','counteroffer_created','counteroffer_accepted','counteroffer_rejected','counteroffer_expired','final_agreement') DEFAULT 'offer_created',
  `price_offered` decimal(10,2) DEFAULT NULL,
  `actor_id` int(11) NOT NULL COMMENT 'User who took the action',
  `actor_type` enum('client','provider') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `negotiation_history`
--

INSERT INTO `negotiation_history` (`id`, `booking_id`, `offer_id`, `counteroffer_id`, `action_type`, `price_offered`, `actor_id`, `actor_type`, `notes`, `created_at`) VALUES
(1, 30, 3, NULL, 'offer_created', 49809.00, 32, 'client', 'Client created initial offer', '2025-12-26 12:25:38'),
(2, 31, 4, NULL, 'offer_created', 50000.00, 32, 'client', 'Client created initial offer', '2025-12-27 08:46:25'),
(3, 32, 5, NULL, 'offer_created', 42000.00, 32, 'client', 'Client created initial offer', '2025-12-27 15:59:26'),
(4, 33, 6, NULL, 'offer_created', 40000.00, 32, 'client', 'Client created initial offer', '2026-01-15 20:36:50'),
(5, 33, 6, NULL, 'offer_accepted', 40000.00, 26, 'provider', 'Offer accepted by provider - Booking confirmed', '2026-01-15 20:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Provider ID receiving the notification',
  `notification_type` enum('booking','offer','favorite','service_update','service_added','profile_view','review','complaint','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'Booking ID, Service ID, User ID, etc.',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'booking, service, user, offer, etc.',
  `icon` varchar(50) DEFAULT NULL,
  `icon_color` varchar(20) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional JSON data for notification details' CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `action_url` varchar(500) DEFAULT NULL,
  `action_label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('client','provider','admin') NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_audience` varchar(50) NOT NULL,
  `sent_via` enum('email','sms','in_app') DEFAULT 'email',
  `status` enum('sent','failed','pending') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `user_id`, `user_type`, `notification_type`, `subject`, `message`, `target_audience`, `sent_via`, `status`, `created_at`) VALUES
(31, NULL, 'admin', 'announcement', 'Provider Response to Your Review - BII GlobalFinder', '\r\n                        <p>Hello David Gakuba,</p>\r\n                        <p>The service provider has responded to your review:</p>\r\n                        <div style=\'background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;\'>\r\n       ', 'broadcast', 'email', 'sent', '2025-12-01 18:30:25'),
(32, 26, 'provider', 'account_approved', 'Your BII LocalFinder Account Has Been Approved', 'Congratulations! Your provider account has been approved. You can now start receiving booking requests.', 'individual', 'email', 'sent', '2026-03-20 07:26:07');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_notifications` tinyint(1) DEFAULT 1,
  `offer_notifications` tinyint(1) DEFAULT 1,
  `favorite_notifications` tinyint(1) DEFAULT 1,
  `service_notifications` tinyint(1) DEFAULT 1,
  `review_notifications` tinyint(1) DEFAULT 1,
  `complaint_notifications` tinyint(1) DEFAULT 1,
  `system_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `push_notifications` tinyint(1) DEFAULT 0,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `notification_digest_frequency` enum('instant','daily','weekly','never') DEFAULT 'instant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_read_status`
--

CREATE TABLE `notification_read_status` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `template_type` enum('provider','client','system','booking') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_templates`
--

INSERT INTO `notification_templates` (`id`, `name`, `subject`, `message`, `template_type`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Provider Account Approved', 'Your BII LocalFinder Account Has Been Approved', 'Congratulations! Your provider account has been approved and is now active. You can start receiving booking requests from clients immediately.', 'provider', 1, '2025-11-24 21:14:40', '2025-11-24 21:14:40'),
(2, 'Provider Account Rejected', 'Your BII LocalFinder Application Status', 'Thank you for your interest in joining BII LocalFinder. After reviewing your application, we regret to inform you that we are unable to approve your account at this time.', 'provider', 1, '2025-11-24 21:14:40', '2025-11-24 21:14:40'),
(3, 'Welcome Client', 'Welcome to BII LocalFinder!', 'Thank you for joining BII LocalFinder! We are excited to help you find trusted service providers in your area. Start exploring services today!', 'client', 1, '2025-11-24 21:14:40', '2025-11-24 21:14:40'),
(4, 'System Maintenance', 'Scheduled System Maintenance', 'We will be performing scheduled maintenance on our platform to improve your experience. The system may be temporarily unavailable during this period.', 'system', 1, '2025-11-24 21:14:40', '2025-11-24 21:14:40'),
(5, 'Booking Confirmation', 'Your Booking Has Been Confirmed', 'Great news! Your service booking has been confirmed. Your service provider will contact you shortly to finalize the details.', 'booking', 1, '2025-11-24 21:14:40', '2025-11-24 21:14:40');

-- --------------------------------------------------------

--
-- Table structure for table `page_sessions`
--

CREATE TABLE `page_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) NOT NULL,
  `page_url` varchar(500) NOT NULL,
  `time_spent_seconds` int(11) DEFAULT 0,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `page_sessions`
--

INSERT INTO `page_sessions` (`id`, `user_id`, `session_id`, `page_url`, `time_spent_seconds`, `start_time`, `end_time`, `ip_address`, `user_agent`) VALUES
(1, NULL, 'oqhh8pnr4cdb2mia47dlirkgak', 'http://localhost/Bii_localFinder/client/provider-profile.php?id=1', 0, '2026-03-21 09:23:09', NULL, '::1', ''),
(2, 31, 'ilmr4ar9i6mct4c4frii76qv8i', 'http://localhost/bii_localfinder/client/dashboard.php', 11, '2026-03-21 12:06:21', '2026-03-21 12:06:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(3, 31, 'ilmr4ar9i6mct4c4frii76qv8i', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 12, '2026-03-21 12:06:35', '2026-03-21 12:06:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(4, 31, 'ilmr4ar9i6mct4c4frii76qv8i', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 53, '2026-03-21 12:06:49', '2026-03-21 12:07:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(5, 26, 'dl9r9hno05scfq9ng7sgoqc088', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-03-21 12:09:29', '2026-03-21 12:09:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(6, NULL, 'rdbt1fes6gmhgp8oeh6mlp4mf6', 'http://localhost/Bii_localFinder/test', 120, '2026-03-21 17:00:00', '2026-03-21 17:02:00', '::1', 'curl/8.13.0'),
(7, 32, 'u43vhpe7e0hteh6bfkm90itahp', 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 1, '2026-03-22 00:35:37', '2026-03-22 00:35:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(8, 32, 'u43vhpe7e0hteh6bfkm90itahp', 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 1, '2026-03-22 00:35:43', '2026-03-22 00:35:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(9, 32, 'u43vhpe7e0hteh6bfkm90itahp', 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 5, '2026-03-22 01:04:13', '2026-03-22 01:04:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(10, 32, 'u43vhpe7e0hteh6bfkm90itahp', 'http://localhost/bii_localfinder/client/dashboard.php', 57, '2026-03-22 01:04:20', '2026-03-22 01:05:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(11, 32, 'u43vhpe7e0hteh6bfkm90itahp', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-22 01:05:22', '2026-03-22 01:05:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(12, 32, 'u43vhpe7e0hteh6bfkm90itahp', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-22 01:07:20', '2026-03-22 01:07:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(13, NULL, '28kddg8591lkbal1vcr5l8rl62', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-22 02:06:01', '2026-03-22 02:06:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(14, NULL, '28kddg8591lkbal1vcr5l8rl62', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-22 02:06:13', '2026-03-22 02:06:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(15, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 11, '2026-03-23 02:20:23', '2026-03-23 02:20:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(16, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-03-23 02:20:37', '2026-03-23 02:20:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(17, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-03-23 02:20:52', '2026-03-23 02:21:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(18, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 13, '2026-03-23 02:21:03', '2026-03-23 02:21:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(19, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 18, '2026-03-23 02:21:20', '2026-03-23 02:21:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(20, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-23 02:21:44', '2026-03-23 02:21:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(21, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-23 02:25:37', '2026-03-23 02:25:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(22, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-03-23 02:31:19', '2026-03-23 02:31:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(23, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-23 02:35:30', '2026-03-23 02:35:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(24, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-23 02:36:49', '2026-03-23 02:36:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(25, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 6, '2026-03-23 02:55:19', '2026-03-23 02:55:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(26, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 1, '2026-03-23 02:56:06', '2026-03-23 02:56:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(27, 32, 'njhbol875q9jr1jfugj0jmqkuq', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-23 03:21:12', '2026-03-23 03:21:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(28, 32, 'njhbol875q9jr1jfugj0jmqkuq', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-23 03:21:22', '2026-03-23 03:21:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(29, 31, 'bai41pkbe9q21qug3el2u0upmd', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-23 03:29:38', '2026-03-23 03:29:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(30, 32, '2r0bi1mqb2nc7rlimn4m398t29', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-03-26 21:15:29', '2026-03-26 21:15:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(31, 32, '2r0bi1mqb2nc7rlimn4m398t29', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-03-26 21:15:40', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(32, 31, 'k97tjgp3ln17469fr7kl7h2jfm', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-26 21:21:43', '2026-03-26 21:21:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(33, 32, 'gfk1hlbjtps0d7n6mho2qn679l', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-03-27 14:19:47', '2026-03-27 14:19:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(34, 32, 'gfk1hlbjtps0d7n6mho2qn679l', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-03-27 14:56:24', '2026-03-27 14:56:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(35, 32, 'gfk1hlbjtps0d7n6mho2qn679l', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-03-27 14:56:24', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(36, NULL, '9d9fg0ohe3mttjq2a9kqpuiksh', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-03-27 15:26:09', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(37, NULL, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 13, '2026-03-27 15:28:58', '2026-03-27 15:29:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(38, NULL, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-27 15:29:13', '2026-03-27 15:29:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(39, NULL, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-27 15:29:19', '2026-03-27 15:29:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(40, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-27 15:37:23', '2026-03-27 15:37:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(41, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-27 15:37:27', '2026-03-27 15:37:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(42, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-27 15:37:36', '2026-03-27 15:37:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(43, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-27 15:37:39', '2026-03-27 15:37:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(44, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-27 15:37:47', '2026-03-27 15:37:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(45, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-03-27 15:37:49', '2026-03-27 15:37:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(46, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 17, '2026-03-27 15:38:05', '2026-03-27 15:38:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `page_views`
--

CREATE TABLE `page_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `page_url` varchar(500) NOT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `page_views`
--

INSERT INTO `page_views` (`id`, `user_id`, `page_url`, `page_title`, `referrer`, `user_agent`, `ip_address`, `session_id`, `viewed_at`) VALUES
(1, NULL, 'http://localhost/test', 'Test Page', '', '', '::1', '5fim7vmeq2uvir8cvbm1686jkq', '2026-03-21 09:19:59'),
(2, NULL, 'http://localhost/Bii_localFinder/client/providers.php', 'Find Service Providers - BII LocalFinder', 'http://localhost/Bii_localFinder/index.php', '', '::1', 'p2spdspsk9npt97fsgnqrnmcu4', '2026-03-21 09:23:09'),
(3, 31, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ilmr4ar9i6mct4c4frii76qv8i', '2026-03-21 12:06:20'),
(4, 31, 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Dushime Gentil - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ilmr4ar9i6mct4c4frii76qv8i', '2026-03-21 12:06:34'),
(5, 31, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ilmr4ar9i6mct4c4frii76qv8i', '2026-03-21 12:06:49'),
(6, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'dl9r9hno05scfq9ng7sgoqc088', '2026-03-21 12:09:28'),
(7, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 12:45:55'),
(8, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 12:46:45'),
(9, 32, 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 12:46:53'),
(10, 32, 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 13:11:41'),
(11, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 13:11:45'),
(12, 32, 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 13:11:52'),
(13, 32, 'http://localhost/bii_localfinder/client/providers.php?section=offers', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vg8gjlt7cfq6q3ttt7c2ikb9jr', '2026-03-21 13:11:55'),
(14, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:22:00'),
(15, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:22:04'),
(16, 32, 'http://localhost/bii_localfinder/client/providers.php?availability=available', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:22:25'),
(17, 32, 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?availability=available', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:22:28'),
(18, 32, 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:22:55'),
(19, 32, 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:35:43'),
(20, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 18:04:20'),
(21, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=carpenter&location=&category=', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 18:05:22'),
(22, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:20:23'),
(23, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:20:38'),
(24, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:21:03'),
(25, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:21:21'),
(26, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:31:21'),
(27, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:35:31'),
(28, 32, 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Find Service Providers - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ftb7r35i9ll2em0i8hj6h943ho', '2026-03-22 19:55:14'),
(29, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'njhbol875q9jr1jfugj0jmqkuq', '2026-03-22 20:21:12'),
(30, 31, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'bai41pkbe9q21qug3el2u0upmd', '2026-03-22 20:29:38'),
(31, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2r0bi1mqb2nc7rlimn4m398t29', '2026-03-26 14:15:16'),
(32, 31, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'k97tjgp3ln17469fr7kl7h2jfm', '2026-03-26 14:21:43'),
(33, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gfk1hlbjtps0d7n6mho2qn679l', '2026-03-27 07:19:47'),
(34, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gfk1hlbjtps0d7n6mho2qn679l', '2026-03-27 07:56:29'),
(35, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'puh57rk2g1r9ko38jf8dqfjtq3', '2026-03-27 08:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `method_name` varchar(100) DEFAULT NULL,
  `account_type` enum('bank','mobile_money','paypal') DEFAULT NULL,
  `account_details` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payout_history`
--

CREATE TABLE `payout_history` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portfolio_images`
--

CREATE TABLE `portfolio_images` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `portfolio_images`
--

INSERT INTO `portfolio_images` (`id`, `provider_id`, `image_path`, `title`, `description`, `display_order`, `is_active`, `uploaded_at`) VALUES
(1, 12, 'portfolio_12_1765747095_0.jpeg', 'Taking the mr david to airport', '', 0, 1, '2025-12-14 21:18:15');

-- --------------------------------------------------------

--
-- Table structure for table `portfolio_videos`
--

CREATE TABLE `portfolio_videos` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `video_path` varchar(255) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_availability`
--

CREATE TABLE `provider_availability` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_availability_patterns`
--

CREATE TABLE `provider_availability_patterns` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '1=Monday, 7=Sunday',
  `hour_of_day` tinyint(4) NOT NULL COMMENT '0-23',
  `booking_count` int(11) DEFAULT 0,
  `response_count` int(11) DEFAULT 0,
  `avg_response_time_minutes` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_categories`
--

CREATE TABLE `provider_categories` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_categories`
--

INSERT INTO `provider_categories` (`id`, `provider_id`, `category_id`, `created_at`) VALUES
(4, 6, 4, '2025-12-13 09:48:22'),
(11, 12, 9, '2025-12-27 13:31:31');

-- --------------------------------------------------------

--
-- Table structure for table `provider_documents`
--

CREATE TABLE `provider_documents` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_payment_methods`
--

CREATE TABLE `provider_payment_methods` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `method_type` enum('mobile_money','bank_account','cash') NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_payment_methods`
--

INSERT INTO `provider_payment_methods` (`id`, `provider_id`, `method_type`, `account_name`, `account_number`, `bank_name`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 12, 'mobile_money', 'Dushime Gentil', '07889799765', '', 0, 1, '2025-12-30 21:02:54', '2025-12-30 21:02:54');

-- --------------------------------------------------------

--
-- Table structure for table `provider_performance`
--

CREATE TABLE `provider_performance` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `total_bookings` int(11) DEFAULT 0,
  `completed_bookings` int(11) DEFAULT 0,
  `cancelled_bookings` int(11) DEFAULT 0,
  `avg_response_time_hours` decimal(5,2) DEFAULT NULL,
  `cancellation_rate` decimal(5,2) DEFAULT 0.00,
  `on_time_completion_rate` decimal(5,2) DEFAULT 0.00,
  `client_satisfaction_score` decimal(5,2) DEFAULT 0.00,
  `availability_score` decimal(5,2) DEFAULT 0.00,
  `overall_performance_score` decimal(5,2) DEFAULT 0.00,
  `performance_grade` enum('excellent','good','average','needs_improvement') DEFAULT 'average',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_performance`
--

INSERT INTO `provider_performance` (`id`, `provider_id`, `period_start`, `period_end`, `avg_rating`, `total_reviews`, `total_bookings`, `completed_bookings`, `cancelled_bookings`, `avg_response_time_hours`, `cancellation_rate`, `on_time_completion_rate`, `client_satisfaction_score`, `availability_score`, `overall_performance_score`, `performance_grade`, `created_at`, `updated_at`) VALUES
(1, 4, '2026-02-19', '2026-03-21', 0.00, 0, 0, 0, 0, NULL, 0.00, 0.00, 0.00, 100.00, 30.00, 'needs_improvement', '2026-03-21 19:18:41', '2026-03-21 19:18:41'),
(2, 6, '2026-02-19', '2026-03-21', 0.00, 0, 0, 0, 0, NULL, 0.00, 0.00, 0.00, 100.00, 30.00, 'needs_improvement', '2026-03-21 19:18:41', '2026-03-21 19:18:41'),
(3, 12, '2026-02-19', '2026-03-21', 3.50, 2, 6, 0, 1, NULL, 16.67, 0.00, 3.50, 100.00, 51.33, 'average', '2026-03-21 19:18:41', '2026-03-21 19:18:41'),
(4, 13, '2026-02-19', '2026-03-21', 0.00, 0, 0, 0, 0, NULL, 0.00, 0.00, 0.00, 100.00, 30.00, 'needs_improvement', '2026-03-21 19:18:41', '2026-03-21 19:18:41'),
(5, 14, '2026-02-19', '2026-03-21', 0.00, 0, 0, 0, 0, NULL, 0.00, 0.00, 0.00, 100.00, 30.00, 'needs_improvement', '2026-03-21 19:18:41', '2026-03-21 19:18:41'),
(6, 15, '2026-02-19', '2026-03-21', 0.00, 0, 0, 0, 0, NULL, 0.00, 0.00, 0.00, 100.00, 30.00, 'needs_improvement', '2026-03-21 19:18:41', '2026-03-21 19:18:41'),
(7, 16, '2026-02-19', '2026-03-21', 2.00, 1, 0, 0, 0, NULL, 0.00, 0.00, 2.00, 0.00, 36.00, 'needs_improvement', '2026-03-21 19:18:41', '2026-03-21 19:18:41');

-- --------------------------------------------------------

--
-- Table structure for table `provider_services`
--

CREATE TABLE `provider_services` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` int(11) NOT NULL DEFAULT 60,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_type` enum('fixed_price','hourly_rate','per_job_estimate','per_day','per_service','base_price') NOT NULL DEFAULT 'fixed_price',
  `min_price` decimal(10,2) DEFAULT NULL COMMENT 'Minimum negotiable price',
  `max_price` decimal(10,2) DEFAULT NULL COMMENT 'Maximum negotiable price',
  `negotiable` tinyint(1) DEFAULT 0 COMMENT 'Is this service price negotiable?',
  `base_price` decimal(10,2) DEFAULT NULL COMMENT 'Base price for negotiation reference',
  `optional_extras` text DEFAULT NULL COMMENT 'Json encoded optional extras for the service',
  `availability_days` text DEFAULT NULL COMMENT 'Comma-separated weekdays available for this service',
  `time_slots` text DEFAULT NULL COMMENT 'JSON encoded time slots for the service',
  `booking_mode` enum('request_approval','instant') NOT NULL DEFAULT 'request_approval',
  `service_status` enum('draft','published','paused') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `provider_services`
--

INSERT INTO `provider_services` (`id`, `provider_id`, `category_id`, `name`, `is_available`, `is_featured`, `price`, `description`, `duration`, `created_at`, `updated_at`, `payment_type`, `min_price`, `max_price`, `negotiable`, `base_price`) VALUES
(27, 13, 5, 'Make a windows', 1, 0, 4000.00, 'To made the window is based on the size but by default the price is 4000', 195, '2025-11-28 09:47:04', '2025-11-28 09:49:28', 'per_service', NULL, NULL, 0, NULL),
(47, 12, 9, 'Personal Driver (Daily Transport)', 1, 0, 20000.00, 'Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.', 285, '2025-12-04 19:02:14', '2025-12-18 14:42:16', 'per_day', NULL, NULL, 0, NULL),
(48, 12, 9, 'Airport Pickup &amp;amp; Drop-off Driver', 1, 0, 15000.00, 'Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.', 90, '2025-12-04 19:06:47', '2025-12-26 11:49:08', 'per_service', 40000.00, 80000.00, 1, NULL),
(49, 6, 4, 'Kumena amavuta', 1, 0, 4000.00, 'tumwejifbhysifheriheriherithiet4iwt', 60, '2025-12-13 17:50:08', '2025-12-13 17:50:08', 'per_service', NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `provider_service_areas`
--

CREATE TABLE `provider_service_areas` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius_km` decimal(5,2) DEFAULT 5.00,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_service_areas`
--

INSERT INTO `provider_service_areas` (`id`, `provider_id`, `area_name`, `latitude`, `longitude`, `radius_km`, `is_primary`, `created_at`) VALUES
(1, 12, 'Gisenyi', -1.58128100, 29.51751700, 10.00, 1, '2026-03-12 20:32:52');

-- --------------------------------------------------------

--
-- Table structure for table `provider_settings`
--

CREATE TABLE `provider_settings` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_settings`
--

INSERT INTO `provider_settings` (`id`, `provider_id`, `setting_key`, `setting_value`, `created_at`) VALUES
(1, 12, 'security_enable_2fa', '0', '2025-12-15 20:05:15'),
(2, 12, 'security_login_alerts', '1', '2025-12-15 20:05:15'),
(3, 12, 'security_emergency_contact', '', '2025-12-15 20:05:15'),
(4, 12, 'security_panic_button_enabled', '1', '2025-12-15 20:05:15'),
(5, 12, 'security_report_abusive_clients', '1', '2025-12-15 20:05:15'),
(6, 12, 'security_job_cancellation_protection', '1', '2025-12-15 20:05:15'),
(7, 12, 'security_session_timeout', '90', '2025-12-15 20:05:15'),
(15, 12, 'visibility_show_phone', '1', '2025-12-30 20:57:51'),
(16, 12, 'visibility_show_whatsapp', '1', '2025-12-30 20:57:51'),
(17, 12, 'visibility_show_exact_location', '0', '2025-12-30 20:57:51'),
(18, 12, 'visibility_profile_public', '1', '2025-12-30 20:57:51'),
(19, 12, 'visibility_appear_in_search', '1', '2025-12-30 20:57:51'),
(20, 12, 'visibility_appear_available', '1', '2025-12-30 20:57:51'),
(21, 12, 'visibility_emergency_service', '1', '2025-12-30 20:57:51'),
(22, 12, 'visibility_night_service', '0', '2025-12-30 20:57:52'),
(23, 12, 'visibility_weekend_service', '1', '2025-12-30 20:57:52'),
(24, 12, 'visibility_badge_verified', '1', '2025-12-30 20:57:52'),
(25, 12, 'visibility_badge_top_rated', '1', '2025-12-30 20:57:52'),
(26, 12, 'visibility_badge_fast_responder', '1', '2025-12-30 20:57:52'),
(27, 12, 'payment_payment_methods', 'cash,mobile_money', '2025-12-30 21:02:54'),
(28, 12, 'payment_accept_cash', '0', '2025-12-30 21:02:54'),
(29, 12, 'payment_accept_mobile_money', '0', '2025-12-30 21:02:54'),
(30, 12, 'payment_accept_wallet', '0', '2025-12-30 21:02:54'),
(31, 12, 'payment_pay_after_service', '0', '2025-12-30 21:02:54'),
(32, 12, 'payment_commission_transparency', '1', '2025-12-30 21:02:54'),
(33, 12, 'communication_preferred_language', 'en', '2025-12-30 22:14:32'),
(60, 12, 'location_travel_fee_per_km', '0', '2026-03-12 20:32:52'),
(61, 12, 'location_max_travel_distance', '16', '2026-03-12 20:32:52'),
(62, 12, 'location_map_accuracy', 'approximate', '2026-03-12 20:32:52'),
(63, 12, 'location_service_radius', '10', '2026-03-12 20:32:52'),
(64, 12, 'location_multiple_areas', '0', '2026-03-12 20:32:52');

-- --------------------------------------------------------

--
-- Table structure for table `provider_shares`
--

CREATE TABLE `provider_shares` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `shared_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_shares`
--

INSERT INTO `provider_shares` (`id`, `provider_id`, `user_id`, `platform`, `shared_at`) VALUES
(1, 13, 31, 'WhatsApp', '2025-12-18 07:15:56'),
(2, 13, 31, 'Facebook', '2025-12-18 07:16:05'),
(3, 13, 31, 'email', '2025-12-18 07:17:43'),
(4, 13, 31, 'email', '2025-12-18 07:17:47'),
(5, 13, 31, 'email', '2025-12-18 07:17:52'),
(6, 13, 31, 'email', '2025-12-18 07:17:57'),
(7, 13, 31, 'email', '2025-12-18 07:18:02'),
(8, 14, 31, 'email', '2025-12-18 07:27:40'),
(9, 14, 31, 'email', '2025-12-18 07:27:44'),
(10, 14, 31, 'email', '2025-12-18 07:27:49'),
(11, 14, 31, 'QR Code', '2025-12-18 07:31:15'),
(12, 12, 31, 'QR Code', '2025-12-18 07:32:11'),
(13, 12, 31, 'Facebook', '2025-12-18 07:32:28'),
(14, 12, 31, 'Facebook', '2025-12-18 07:34:23'),
(15, 13, 31, 'Link Copy', '2025-12-18 07:42:44'),
(16, 12, 32, 'QR Code', '2025-12-18 13:25:25'),
(17, 15, 32, 'Twitter', '2025-12-24 07:46:07'),
(18, 15, 32, 'Facebook', '2025-12-27 16:35:48'),
(19, 12, 32, 'profile_view', '2026-03-22 19:26:43');

-- --------------------------------------------------------

--
-- Table structure for table `provider_social_links`
--

CREATE TABLE `provider_social_links` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_time_off`
--

CREATE TABLE `provider_time_off` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_views`
--

CREATE TABLE `provider_views` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_views`
--

INSERT INTO `provider_views` (`id`, `provider_id`, `user_id`, `viewed_at`) VALUES
(2, 12, NULL, '2026-03-21 09:25:41'),
(4, 4, NULL, '2026-03-21 17:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `is_reviewed` tinyint(1) DEFAULT 0,
  `comment` text DEFAULT NULL,
  `provider_response` text DEFAULT NULL,
  `response_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `client_id`, `provider_id`, `booking_id`, `rating`, `is_reviewed`, `comment`, `provider_response`, `response_date`, `created_at`, `updated_at`) VALUES
(5, 11, 12, 1, 5, 0, 'this provider is very smart and amazing, he was cleaned whole house windows in 30 minutes only.', 'and I thank you also for all your work', '2025-11-28 00:25:46', '2025-11-27 16:22:28', '2025-11-28 08:25:46'),
(6, 11, 16, 3, 2, 0, 'fuck fuck fuck', NULL, NULL, '2025-11-29 12:38:21', '2025-11-29 12:39:56'),
(8, 33, 12, 22, 2, 0, 'Gentil is a liar', NULL, NULL, '2025-12-18 08:36:58', '2025-12-18 08:38:13');

-- --------------------------------------------------------

--
-- Table structure for table `search_logs`
--

CREATE TABLE `search_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `search_query` text NOT NULL,
  `search_type` enum('providers','services','general') DEFAULT 'general',
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `results_count` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `searched_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_logs`
--

INSERT INTO `search_logs` (`id`, `user_id`, `search_query`, `search_type`, `filters`, `results_count`, `ip_address`, `user_agent`, `session_id`, `searched_at`) VALUES
(1, NULL, 'electrician in kigali', 'providers', '{\"location\": \"Kigali\"}', 5, '::1', '', 'o9k1d84a5f2b8835sp49r1gl0i', '2026-03-21 09:23:09'),
(2, 32, 'carpenter', 'providers', '{}', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'u43vhpe7e0hteh6bfkm90itahp', '2026-03-21 17:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `service_counteroffers`
--

CREATE TABLE `service_counteroffers` (
  `id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `proposed_price` decimal(10,2) NOT NULL COMMENT 'Counter-offered price by provider',
  `status` enum('pending','accepted','rejected','expired') DEFAULT 'pending',
  `round_number` int(11) DEFAULT 1 COMMENT 'Negotiation round (1-3)',
  `expires_at` datetime NOT NULL COMMENT 'Counter-offer expires',
  `responded_at` datetime DEFAULT NULL,
  `response_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_counteroffers`
--

INSERT INTO `service_counteroffers` (`id`, `offer_id`, `service_id`, `provider_id`, `client_id`, `proposed_price`, `status`, `round_number`, `expires_at`, `responded_at`, `response_notes`, `created_at`, `updated_at`) VALUES
(1, 3, 48, 26, 32, 50000.00, 'accepted', 1, '2025-12-29 13:37:47', NULL, 'this maximum price', '2025-12-26 12:37:47', '2025-12-27 08:29:25'),
(2, 4, 48, 26, 32, 55000.00, 'accepted', 1, '2025-12-30 09:47:13', NULL, '', '2025-12-27 08:47:13', '2025-12-27 08:49:03'),
(3, 5, 48, 26, 32, 50000.00, 'accepted', 1, '2025-12-30 17:00:46', NULL, 'If you live nearest you has to pay 50000 frw', '2025-12-27 16:00:46', '2025-12-27 16:01:56');

-- --------------------------------------------------------

--
-- Table structure for table `service_offers`
--

CREATE TABLE `service_offers` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `offered_price` decimal(10,2) NOT NULL COMMENT 'Initial price offered by client',
  `status` enum('pending','accepted','rejected','expired','withdrawn') DEFAULT 'pending',
  `round_number` int(11) DEFAULT 1 COMMENT 'Negotiation round (1-3)',
  `expires_at` datetime NOT NULL COMMENT 'Offer expires at this time',
  `responded_at` datetime DEFAULT NULL,
  `response_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_offers`
--

INSERT INTO `service_offers` (`id`, `booking_id`, `service_id`, `client_id`, `provider_id`, `offered_price`, `status`, `round_number`, `expires_at`, `responded_at`, `response_notes`, `created_at`, `updated_at`) VALUES
(3, 30, 48, 32, 26, 49809.00, 'accepted', 1, '2025-12-26 13:55:38', NULL, NULL, '2025-12-26 12:25:38', '2025-12-27 08:29:25'),
(4, 31, 48, 32, 26, 50000.00, 'accepted', 1, '2025-12-27 10:16:25', NULL, NULL, '2025-12-27 08:46:25', '2025-12-27 08:49:03'),
(5, 32, 48, 32, 26, 42000.00, 'accepted', 1, '2025-12-27 17:29:26', NULL, NULL, '2025-12-27 15:59:26', '2025-12-27 16:01:56'),
(6, 33, 48, 32, 26, 40000.00, 'accepted', 1, '2026-01-15 22:06:50', '2026-01-15 12:39:32', NULL, '2026-01-15 20:36:50', '2026-01-15 20:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `service_providers`
--

CREATE TABLE `service_providers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `profession` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `location` varchar(100) NOT NULL,
  `district` varchar(50) DEFAULT NULL,
  `sector` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `availability` enum('available','busy','unavailable') DEFAULT 'available',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','suspended','archived') NOT NULL DEFAULT 'active',
  `is_banned` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `featured_until` datetime DEFAULT NULL,
  `search_boost` int(11) DEFAULT 0,
  `verification_level` enum('none','verified','gold','premium') DEFAULT 'none',
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `total_jobs` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ban_reason` text DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `working_days` varchar(50) DEFAULT '1,2,3,4,5',
  `working_hours_start` time DEFAULT '08:00:00',
  `working_hours_end` time DEFAULT '17:00:00',
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `slot_duration` int(11) DEFAULT 30,
  `buffer_time` int(11) DEFAULT 15,
  `max_daily_bookings` int(11) DEFAULT 8,
  `booking_lead_time` int(11) DEFAULT 24,
  `cancellation_cutoff` int(11) DEFAULT 12,
  `portfolio_enabled` tinyint(1) DEFAULT 1,
  `max_portfolio_images` int(11) DEFAULT 6,
  `website` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `youtube` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(255) DEFAULT NULL,
  `tiktok` varchar(255) DEFAULT NULL,
  `other_social` varchar(255) DEFAULT NULL,
  `other_social_label` varchar(100) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 10.00 COMMENT 'Commission rate percentage for this provider',
  `subscription_plan` enum('free','basic','premium') DEFAULT 'free' COMMENT 'Provider subscription plan',
  `can_receive_jobs` tinyint(1) DEFAULT 1 COMMENT 'Whether provider can receive new jobs'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `service_providers`
--

INSERT INTO `service_providers` (`id`, `user_id`, `is_verified`, `profession`, `bio`, `experience_years`, `location`, `district`, `sector`, `latitude`, `longitude`, `availability`, `hourly_rate`, `is_active`, `status`, `is_banned`, `is_featured`, `featured_until`, `search_boost`, `verification_level`, `average_rating`, `total_reviews`, `total_jobs`, `created_at`, `updated_at`, `ban_reason`, `is_premium`, `working_days`, `working_hours_start`, `working_hours_end`, `break_start`, `break_end`, `slot_duration`, `buffer_time`, `max_daily_bookings`, `booking_lead_time`, `cancellation_cutoff`, `portfolio_enabled`, `max_portfolio_images`, `website`, `facebook`, `twitter`, `instagram`, `linkedin`, `youtube`, `whatsapp`, `tiktok`, `other_social`, `other_social_label`, `commission_rate`, `subscription_plan`, `can_receive_jobs`) VALUES
(4, 15, 0, 'Plumber', 'I am proffesional plumber who has the certificate and I was teacher in the Hope international school', 3, 'Rubavu', 'Rusizi', '', NULL, NULL, '', 4000.00, 1, 'active', 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-24 12:48:56', NULL, NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1),
(6, 18, 0, 'Mechanic', 'I am studied Automobile engneering 4 a years in USA, I has the experience and large team we commit together. Professional Mechanic, reliable, professional.', 4, 'Musanze', 'Musanze', 'Ruhengeri', NULL, NULL, 'available', 0.00, 1, 'active', 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-24 12:48:56', '2025-12-13 09:48:22', NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1),
(12, 26, 0, 'Driver', 'I am the proffesional Professional Mason, skilled, licensed. with 4 years of experience, reliable, certified. Professional Driver.', 4, 'Huye', 'Kayonza', 'cyamata', NULL, NULL, 'available', 4500.00, 1, 'active', 0, 1, '2026-01-09 08:23:00', 80, '', 3.50, 2, 5, '2025-11-27 07:28:01', '2026-03-19 23:56:04', NULL, 0, '1,2,3,4,5,6,7', '07:00:00', '14:00:00', '00:00:00', '00:00:00', 30, 15, 3, 24, 12, 1, 6, '', 'https://web.facebook.com/biicrow', '', 'https://www.instagram.com/gentil015/', '', '', '+250795946213', '', '', '', 10.00, 'free', 1),
(13, 27, 0, 'Carpenter', '', 1, 'Muhanga', 'Muhanga', 'bisizi', NULL, NULL, 'available', 2000.00, 1, 'active', 0, 0, '2025-11-30 13:21:00', 0, 'none', 0.00, 0, 0, '2025-11-27 12:51:23', '2025-11-28 01:44:53', NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1),
(14, 28, 0, 'Driver', '', NULL, 'Nyabihu', 'Gicumbi', 'bisigo', NULL, NULL, 'available', NULL, 1, 'active', 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-27 12:53:01', '2025-11-27 12:55:54', NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1),
(15, 29, 0, 'Plumber', '', NULL, 'Bugesera', 'Bugesera', 'hugwe', NULL, NULL, 'available', NULL, 1, 'active', 0, 1, NULL, 0, 'none', 0.00, 0, 0, '2025-11-27 12:54:38', '2025-12-13 10:38:21', NULL, 0, '1,2,3,4,5,6,7', '08:00:00', '17:00:00', '00:00:00', '00:00:00', 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1),
(16, 30, 0, 'Driver', 'I am the driver and I has the experience. reliable, professional, skilled. certified, licensed.', 3, 'Rubavu', 'Rubavu', 'Kanama', NULL, NULL, 'available', 3000.00, 1, 'active', 0, 0, NULL, 0, 'none', 2.00, 1, 1, '2025-11-29 04:26:23', '2025-12-18 06:11:53', NULL, 0, '', '08:00:00', '17:00:00', '00:00:00', '00:00:00', 30, 15, 5, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(40) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `last_activity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'platform_name', 'BII LocalFinder', '2025-11-24 18:24:23', '2025-12-14 20:07:28'),
(2, 'contact_email', 'info@biilocalfinder.com', '2025-11-24 18:24:23', '2025-11-24 18:24:23'),
(3, 'copyright_text', 'c 2024 BII LocalFinder. All rights reserved.', '2025-11-24 18:24:23', '2025-11-25 18:51:13'),
(4, 'platform_description', 'Connecting skilled professionals with clients across Rwanda', '2025-11-24 18:24:23', '2025-11-25 18:51:13'),
(5, 'client_registration', '1', '2025-11-24 18:24:23', '2025-11-24 18:24:23'),
(6, 'provider_registration', '1', '2025-11-24 18:24:23', '2025-11-24 18:24:23'),
(7, 'email_verification', '1', '2025-11-24 18:24:23', '2025-11-29 12:23:52'),
(8, 'phone_verification', '0', '2025-11-24 18:24:23', '2025-11-24 18:24:23'),
(9, 'min_password_length', '8', '2025-11-24 18:24:23', '2025-11-29 12:23:52'),
(10, 'require_special_chars', '0', '2025-11-24 18:24:23', '2025-11-24 18:24:23'),
(11, 'contact_phone', '+250 788 123 456', '2025-11-24 18:27:54', '2025-11-24 18:27:54'),
(12, 'maintenance_mode', '0', '2025-11-24 18:27:54', '2026-03-03 19:05:31'),
(23, 'timezone', 'Africa/Kigali', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(24, 'provider_verification_required', '1', '2025-11-24 18:37:15', '2025-12-18 06:31:10'),
(25, 'session_timeout', '60', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(26, 'max_pending_time', '15', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(27, 'auto_assign_providers', '0', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(28, 'allow_booking_editing', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(29, 'allow_provider_rejection', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(30, 'auto_cancel_unconfirmed', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(31, 'require_rating_after_completion', '0', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(32, 'max_cancellations_per_month', '3', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(33, 'enable_email_notifications', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(34, 'enable_sms_notifications', '0', '2025-11-24 18:37:15', '2026-03-20 07:12:00'),
(35, 'smtp_host', 'smtp.gmail.com', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(36, 'smtp_port', '587', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(37, 'enable_commission', '0', '2025-11-24 18:37:15', '2025-12-17 16:51:51'),
(38, 'commission_rate', '10', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(39, 'enable_subscriptions', '0', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(40, 'basic_subscription_price', '5000', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(41, 'premium_subscription_price', '15000', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(42, 'featured_listing_price', '10000', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(43, 'verification_fee', '2000', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(44, 'enable_payouts', '0', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(45, 'payment_gateway', 'mtn', '2025-11-24 18:37:15', '2025-12-15 17:15:04'),
(46, 'allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(47, 'max_file_size', '10', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(48, 'enable_2fa_admin', '0', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(49, 'enable_2fa', '0', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(50, 'auto_backup', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(51, 'backup_frequency', 'daily', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(52, 'cookie_consent', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(53, 'debug_mode', '1', '2025-11-24 18:37:15', '2025-11-25 21:50:29'),
(54, 'api_rate_limit', '60', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(55, 'cache_duration', '30', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(56, 'allow_account_deletion', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(57, 'archive_deleted_accounts', '1', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(58, 'data_retention_days', '30', '2025-11-24 18:37:15', '2025-11-24 18:37:15'),
(88, 'platform_logo', 'default-logo.png', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(89, 'smtp_username', 'biilocalfinder@gmail.com', '2025-11-24 21:12:40', '2026-03-20 07:12:00'),
(90, 'smtp_encryption', 'tls', '2025-11-24 21:12:40', '2025-11-25 21:54:04'),
(91, 'sms_provider', 'twilio', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(92, 'sms_api_key', '', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(93, 'cron_auto_cleanup', '1', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(94, 'cron_notifications', '1', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(95, 'payment_webhook', '', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(96, 'sms_webhook', '', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(97, 'email_webhook', '', '2025-11-24 21:12:40', '2025-11-24 21:12:40'),
(181, 'smtp_password', 'mxuc mgaj ykyg nrwv', '2025-11-25 21:46:48', '2026-03-20 07:12:00'),
(292, 'sms_api_url', '', '2026-03-20 06:56:56', '2026-03-20 06:56:56');

-- --------------------------------------------------------

--
-- Table structure for table `toxic_content_logs`
--

CREATE TABLE `toxic_content_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content_type` enum('complaint','emergency','review','message') DEFAULT NULL,
  `toxicity_score` float DEFAULT NULL,
  `original_text` text DEFAULT NULL,
  `flagged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('payment','withdrawal','refund','commission') NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('client','provider','admin') DEFAULT 'client',
  `is_verified` tinyint(1) DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','suspended','archived') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `verification_token` varchar(100) DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `login_notifications` tinyint(1) DEFAULT 1,
  `delete_requested_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `user_type`, `is_verified`, `email_verified`, `phone_verified`, `is_active`, `status`, `last_login`, `password_changed_at`, `deactivated_at`, `deleted_at`, `verification_token`, `reset_token`, `reset_token_expiry`, `profile_image`, `created_at`, `updated_at`, `otp_code`, `otp_expires_at`, `two_factor_enabled`, `login_notifications`, `delete_requested_at`) VALUES
(1, 'Admin', 'admin@biilocalfinder.com', '0788000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1, 1, 1, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-05 09:33:23', '2025-11-24 18:46:59', NULL, NULL, 0, 1, NULL),
(11, 'Gitego', 'corneillemugisha@gmail.com', '+250788700443', '$2y$10$OK1Yyz/U54DWQSYO2P761ei6kVVAzc/QKp1KlXJ2VdM8jUx8bq9D6', 'client', 1, 1, 1, 1, 'active', '2025-12-03 11:46:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-05 13:16:37', '2025-12-03 19:46:15', NULL, NULL, 0, 1, NULL),
(15, 'Djaziri', 'mggitego@gmail.com', '+25079593232', '$2y$10$8nrTCmHjF25..daDIfb.cePf6Phf9.YorB4E3DZi6VLv6xR0u.eoO', 'provider', 1, 1, 1, 1, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'profile_15_1762418046.jpg', '2025-11-06 07:44:17', '2025-11-24 18:46:59', NULL, NULL, 0, 1, NULL),
(18, 'Abigael', 'mwizaabigael@gmail.com', '+250795946213', '$2y$10$NBBUjzA1WnzMGWFO/tsJteLdgaV/4ljhQGAD2Q7B7rdOG6pc7T3Nm', 'provider', 1, 1, 1, 1, 'active', '2025-12-13 09:46:18', NULL, NULL, NULL, NULL, NULL, NULL, 'profile_18_1765648102.png', '2025-11-06 08:17:25', '2025-12-17 17:29:41', '489883', '2025-12-17 18:59:41', 0, 1, NULL),
(19, 'Administrator', 'admin@localfinder.com', '0712345678', '$2y$10$yC0Gk5aPWJo.JPq2n8Mnle5iQA1y56vrxtSPIu2Tr0U.SmvFiWozu', 'admin', 1, 1, 1, 1, 'active', '2026-03-20 02:43:02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-24 20:58:48', '2026-03-20 09:43:02', NULL, NULL, 0, 1, NULL),
(26, 'Dushime Gentil', 'dushimegentil0@gmail.com', '+25075946213', '$2y$10$FeiPY8QgezUyKPFClesEe.Q3290JvNWaIvw85HJsVpsulrer7khw6', 'provider', 1, 0, 0, 1, 'active', '2026-03-21 05:09:27', '2025-12-15 12:05:15', NULL, NULL, NULL, NULL, NULL, 'profile_26_1764790963.jpg', '2025-11-27 15:28:01', '2026-03-21 12:09:27', NULL, NULL, 0, 1, NULL),
(27, 'Ngabo Aime', 'ngaboaime@gmail.com', '0795930482', '$2y$10$VONKiH6iHMhgXBeweXGJHeFxSLySEXQmb11y9DpKzUDEjXVBdJPKe', 'provider', 1, 0, 0, 1, 'active', '2025-11-28 01:42:45', NULL, NULL, NULL, NULL, NULL, NULL, 'profile_27_1764277379.jpg', '2025-11-27 20:51:23', '2025-11-28 09:44:53', '726497', '2025-11-27 22:01:23', 0, 1, NULL),
(28, 'Kevin Mugisha', 'mugishakevin@gmail.com', '+2507948927349', '$2y$10$bAKfPqcZvcM7Emu1bsRWk.blYvvysRjEr9F9l91XSjogXnqAAdED2', 'provider', 1, 0, 0, 1, 'active', '2025-11-27 13:47:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 20:53:01', '2025-11-27 21:47:24', '984236', '2025-11-27 22:03:01', 0, 1, NULL),
(29, 'Adrien migabo', 'adrienmigabo@gmail.com', '0783937989', '$2y$10$pUikfrD3cFMUw9EnKirTjuh/BwqOIAkb1H60OgGtLi7Zq8dJZGmh6', 'provider', 1, 0, 0, 1, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 20:54:38', '2025-11-27 20:55:49', '933410', '2025-11-27 22:04:38', 0, 1, NULL),
(30, 'Kevin mugisha', 'kevinmugisha354@gmail.com', '+250795946213', '$2y$10$bES98RTdKieFyNNMRrcqwegwg3/B31YRg/swjsuVBx57MLEui7/ou', 'provider', 1, 0, 0, 1, 'active', '2025-12-17 23:59:58', NULL, NULL, NULL, NULL, NULL, NULL, 'profile_30_1764621887.jpg', '2025-11-29 12:26:23', '2025-12-18 07:59:58', NULL, NULL, 0, 1, NULL),
(31, 'David Gakuba', 'technogystore@gmail.com', '+250795946213', '$2y$10$2/9aBz95VgDXg4LUsk98lO08Zco7BAMPZEDReOOqch4zucF8x7cre', 'client', 1, 0, 0, 1, 'active', '2026-03-26 07:21:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-01 17:57:23', '2026-03-26 14:21:43', NULL, NULL, 0, 1, NULL),
(32, 'Mukundwa Aime', 'tuyizereaimely@gmail.com', '+250795946213', '$2y$10$8bK7Xp71icAO5YjNxSR/XeKb5tlHjnbIRT2uWC2Dv3VuMwj/RGoQO', 'client', 1, 0, 0, 1, 'active', '2026-03-27 01:33:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-17 17:22:25', '2026-03-27 08:33:05', NULL, NULL, 0, 1, NULL),
(33, 'ELie', 'biitechnology0@gmail.com', '+250795946213', '$2y$10$2JXLkcGSiQBTYvu8hU7CMepoP3wDzFMDqsM1tanSsfgojK54eAPVm', 'client', 0, 0, 0, 1, 'active', '2025-12-18 00:36:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-18 08:23:26', '2025-12-18 08:46:53', NULL, NULL, 0, 1, NULL),
(34, 'Test User', 'test@example.com', '', '\\/IGYLd.LQ5J4PGS', 'client', 1, 0, 0, 1, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-21 17:39:39', '2026-03-21 17:51:25', NULL, NULL, 0, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activities`
--

INSERT INTO `user_activities` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 11, 'privacy_update', 'User updated privacy settings', NULL, NULL, '2025-11-24 19:14:17'),
(2, 11, 'review_created', 'Submitted review for provider #12', NULL, NULL, '2025-11-27 16:22:28'),
(3, 11, 'review_updated', 'Updated review for provider #12', NULL, NULL, '2025-11-27 16:34:11'),
(4, 28, 'privacy_update', 'User updated privacy settings', NULL, NULL, '2025-11-27 21:32:24'),
(5, 28, 'privacy_update', 'User updated privacy settings', NULL, NULL, '2025-11-27 21:48:24'),
(6, 11, 'review_created', 'Submitted review for provider #16', NULL, NULL, '2025-11-29 12:38:21'),
(7, 11, 'review_updated', 'Updated review for provider #16', NULL, NULL, '2025-11-29 12:39:56'),
(8, 31, 'review_created', 'Submitted review for provider #12', NULL, NULL, '2025-12-01 18:21:35'),
(9, 31, 'review_updated', 'Updated review for provider #12', NULL, NULL, '2025-12-11 19:36:04'),
(10, 31, 'review_updated', 'Updated review for provider #12', NULL, NULL, '2025-12-13 17:41:43'),
(11, 31, 'review_deleted', 'Deleted review for provider #12', NULL, NULL, '2025-12-18 07:46:02'),
(12, 33, 'review_created', 'Submitted review for provider #12', NULL, NULL, '2025-12-18 08:36:58'),
(13, 33, 'review_updated', 'Updated review for provider #12', NULL, NULL, '2025-12-18 08:38:13');

-- --------------------------------------------------------

--
-- Table structure for table `user_logout_logs`
--

CREATE TABLE `user_logout_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('client','provider','admin','unknown') DEFAULT 'unknown',
  `logout_time` datetime NOT NULL,
  `session_duration` int(11) DEFAULT 0 COMMENT 'Session duration in seconds',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_logout_logs`
--

INSERT INTO `user_logout_logs` (`id`, `user_id`, `user_type`, `logout_time`, `session_duration`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 26, 'unknown', '2025-12-16 09:23:57', 1155, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-16 17:23:57'),
(2, 19, 'unknown', '2025-12-16 23:04:21', 134, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 07:04:21'),
(3, 31, 'unknown', '2025-12-17 07:40:04', 1005, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 15:40:04'),
(4, 26, 'unknown', '2025-12-17 07:48:55', 518, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 15:48:55'),
(5, 19, 'unknown', '2025-12-17 07:49:32', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 15:49:32'),
(6, 26, 'unknown', '2025-12-17 08:05:34', 943, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 16:05:34'),
(7, 31, 'unknown', '2025-12-17 08:12:22', 384, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 16:12:22'),
(8, 31, 'unknown', '2025-12-17 08:21:36', 531, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 16:21:36'),
(9, 19, 'unknown', '2025-12-17 08:29:12', 436, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 16:29:12'),
(10, 31, 'unknown', '2025-12-17 08:48:29', 1141, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 16:48:29'),
(11, 19, 'unknown', '2025-12-17 09:06:42', 1044, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 17:06:42'),
(12, 32, 'unknown', '2025-12-17 09:28:36', 281, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 17:28:36'),
(13, 31, 'unknown', '2025-12-17 09:40:32', 360, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 17:40:32'),
(14, 31, 'unknown', '2025-12-17 09:41:45', 52, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 17:41:45'),
(15, 26, 'unknown', '2025-12-17 11:21:10', 144, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-17 19:21:10'),
(16, 26, 'unknown', '2025-12-17 22:10:16', 72, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 06:10:16'),
(17, 31, 'unknown', '2025-12-17 22:11:04', 35, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 06:11:04'),
(18, 26, 'unknown', '2025-12-17 22:15:06', 228, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 06:15:06'),
(19, 19, 'unknown', '2025-12-17 22:16:47', 74, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 06:16:47'),
(20, 19, 'unknown', '2025-12-17 22:52:04', 2022, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 06:52:04'),
(21, 26, 'unknown', '2025-12-17 23:06:09', 234, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:06:09'),
(22, 31, 'unknown', '2025-12-17 23:09:48', 91, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:09:48'),
(23, 31, 'unknown', '2025-12-17 23:43:16', 1983, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:43:16'),
(24, 31, 'unknown', '2025-12-17 23:48:37', 238, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:48:37'),
(25, 19, 'unknown', '2025-12-17 23:49:16', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:49:16'),
(26, 32, 'unknown', '2025-12-17 23:52:04', 149, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:52:04'),
(27, 26, 'unknown', '2025-12-17 23:52:35', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:52:35'),
(28, 32, 'unknown', '2025-12-17 23:59:11', 373, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 07:59:11'),
(29, 30, 'unknown', '2025-12-18 00:00:54', 56, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:00:54'),
(30, 32, 'unknown', '2025-12-18 00:06:33', 326, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:06:33'),
(31, 26, 'unknown', '2025-12-18 00:18:19', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:18:19'),
(32, 33, 'unknown', '2025-12-18 00:31:10', 378, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:31:10'),
(33, 26, 'unknown', '2025-12-18 00:35:44', 238, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:35:44'),
(34, 33, 'unknown', '2025-12-18 00:41:04', 303, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:41:04'),
(35, 19, 'unknown', '2025-12-18 00:47:02', 339, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 08:47:02'),
(36, 26, 'unknown', '2025-12-18 05:24:14', 223, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 13:24:14'),
(37, 32, 'unknown', '2025-12-18 05:31:32', 416, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 13:31:32'),
(38, 26, 'unknown', '2025-12-18 05:48:49', 1026, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 13:48:49'),
(39, 32, 'unknown', '2025-12-18 06:03:14', 851, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 14:03:14'),
(40, 19, 'unknown', '2025-12-18 06:14:20', 649, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 14:14:20'),
(41, 26, 'unknown', '2025-12-18 07:00:53', 2084, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-18 15:00:53'),
(42, 26, 'unknown', '2025-12-19 22:46:10', 162, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-20 06:46:10'),
(43, 26, 'unknown', '2025-12-20 00:39:14', 109, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-20 08:39:14'),
(44, 32, 'unknown', '2025-12-20 08:05:18', 158, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-20 16:05:18'),
(45, 26, 'unknown', '2025-12-20 08:19:16', 825, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-20 16:19:16'),
(46, 19, 'unknown', '2025-12-20 09:48:47', 50, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-20 17:48:47'),
(47, 26, 'unknown', '2025-12-21 07:32:31', 447, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-21 15:32:31'),
(48, 32, 'unknown', '2025-12-21 07:53:02', 1216, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-21 15:53:02'),
(49, 26, 'unknown', '2025-12-23 11:01:45', 310, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-23 19:01:45'),
(50, 32, 'unknown', '2025-12-23 23:47:36', 229, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-24 07:47:36'),
(51, 26, 'unknown', '2025-12-24 00:27:00', 2122, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-24 08:27:00'),
(52, 26, 'unknown', '2025-12-24 11:10:11', 136, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-24 19:10:11'),
(53, 32, 'unknown', '2025-12-26 03:17:34', 1957, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:17:34'),
(54, 26, 'unknown', '2025-12-26 03:27:07', 563, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:27:07'),
(55, 32, 'unknown', '2025-12-26 03:31:29', 250, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:31:29'),
(56, 26, 'unknown', '2025-12-26 03:35:51', 252, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:35:51'),
(57, 32, 'unknown', '2025-12-26 03:40:44', 107, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:40:44'),
(58, 26, 'unknown', '2025-12-26 03:49:11', 491, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:49:11'),
(59, 32, 'unknown', '2025-12-26 03:55:41', 332, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:55:41'),
(60, 26, 'unknown', '2025-12-26 03:59:24', 213, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 11:59:24'),
(61, 32, 'unknown', '2025-12-26 04:06:47', 432, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 12:06:47'),
(62, 32, 'unknown', '2025-12-26 04:25:50', 1125, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 12:25:50'),
(63, 26, 'unknown', '2025-12-26 04:38:06', 716, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 12:38:06'),
(64, 26, 'unknown', '2025-12-27 00:25:17', 2340, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 08:25:17'),
(65, 32, 'unknown', '2025-12-27 00:29:32', 230, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 08:29:32'),
(66, 26, 'unknown', '2025-12-27 00:32:04', 140, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 08:32:04'),
(67, 32, 'unknown', '2025-12-27 00:46:29', 855, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 08:46:29'),
(68, 26, 'unknown', '2025-12-27 00:47:15', 34, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 08:47:15'),
(69, 26, 'unknown', '2025-12-27 00:47:37', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 08:47:37'),
(70, 32, 'unknown', '2025-12-27 01:06:32', 1117, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 09:06:32'),
(71, 26, 'unknown', '2025-12-27 01:25:21', 1118, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 09:25:21'),
(72, 32, 'unknown', '2025-12-27 01:27:29', 116, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 09:27:29'),
(73, 32, 'unknown', '2025-12-27 01:30:05', 33, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 09:30:05'),
(74, 26, 'unknown', '2025-12-27 01:55:35', 273, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 09:55:35'),
(75, 26, 'unknown', '2025-12-27 02:25:42', 49, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 10:25:42'),
(76, 32, 'unknown', '2025-12-27 02:49:11', 1386, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 10:49:11'),
(77, 26, 'unknown', '2025-12-27 04:04:24', 45, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 12:04:24'),
(78, 32, 'unknown', '2025-12-27 04:18:20', 821, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 12:18:20'),
(79, 32, 'unknown', '2025-12-27 04:24:01', 268, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 12:24:01'),
(80, 26, 'unknown', '2025-12-27 04:38:13', 836, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 12:38:13'),
(81, 26, 'unknown', '2025-12-27 04:41:36', 191, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 12:41:36'),
(82, 26, 'unknown', '2025-12-27 05:08:26', 1596, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 13:08:26'),
(83, 32, 'unknown', '2025-12-27 05:29:56', 1268, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 13:29:56'),
(84, 26, 'unknown', '2025-12-27 05:54:12', 1444, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 13:54:12'),
(85, 32, 'unknown', '2025-12-27 05:56:25', 114, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 13:56:25'),
(86, 32, 'unknown', '2025-12-27 07:59:32', 108, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 15:59:32'),
(87, 26, 'unknown', '2025-12-27 08:00:49', 68, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 16:00:49'),
(88, 32, 'unknown', '2025-12-27 08:02:17', 77, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 16:02:17'),
(89, 26, 'unknown', '2025-12-27 08:33:47', 1872, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 16:33:47'),
(90, 32, 'unknown', '2025-12-27 08:37:22', 196, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 16:37:22'),
(91, 26, 'unknown', '2025-12-27 13:01:17', 1712, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 21:01:17'),
(92, 32, 'unknown', '2025-12-27 13:07:14', 337, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 21:07:14'),
(93, 26, 'unknown', '2025-12-27 13:31:47', 1461, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 21:31:47'),
(94, 32, 'unknown', '2025-12-27 13:37:32', 330, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 21:37:32'),
(95, 26, 'unknown', '2025-12-27 13:39:26', 102, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 21:39:26'),
(96, 32, 'unknown', '2025-12-27 13:39:58', 17, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-27 21:39:58'),
(97, 26, 'unknown', '2025-12-29 12:55:50', 1173, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-29 20:55:50'),
(98, 26, 'unknown', '2025-12-30 12:57:55', 390, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 20:57:55'),
(99, 32, 'unknown', '2025-12-30 12:58:55', 49, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 20:58:55'),
(100, 26, 'unknown', '2025-12-30 13:33:41', 2045, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 21:33:41'),
(101, 26, 'unknown', '2025-12-30 14:29:30', 3297, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 22:29:30'),
(102, 26, 'unknown', '2025-12-30 15:14:50', 2708, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-30 23:14:50'),
(103, 26, 'unknown', '2026-01-01 11:01:15', 173, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-01 19:01:15'),
(104, 32, 'unknown', '2026-01-01 12:04:00', 1686, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-01 20:04:00'),
(105, 26, 'unknown', '2026-01-03 01:07:32', 105, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-03 09:07:32'),
(106, 26, 'unknown', '2026-01-11 10:24:18', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 18:24:18'),
(107, 32, 'unknown', '2026-01-11 12:49:29', 2407, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-11 20:49:29'),
(108, 26, 'unknown', '2026-01-12 07:42:05', 743, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 15:42:05'),
(109, 26, 'unknown', '2026-01-12 09:03:27', 83, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 17:03:27'),
(110, 32, 'unknown', '2026-01-12 09:11:18', 381, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 17:11:18'),
(111, 26, 'unknown', '2026-01-15 12:17:27', 420, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-15 20:17:27'),
(112, 32, 'unknown', '2026-01-15 12:37:03', 406, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-15 20:37:03'),
(113, 26, 'unknown', '2026-01-15 12:41:14', 223, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-15 20:41:14'),
(114, 32, 'unknown', '2026-01-15 13:02:23', 1252, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-15 21:02:23'),
(115, 26, 'unknown', '2026-02-24 14:03:13', 123, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-02-24 22:03:13'),
(116, 26, 'unknown', '2026-02-25 13:15:58', 827, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 21:15:58'),
(117, 19, 'unknown', '2026-02-25 13:20:44', 259, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 21:20:44'),
(118, 32, 'unknown', '2026-02-25 13:34:25', 807, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 21:34:25'),
(119, 32, 'unknown', '2026-02-25 13:48:04', 40, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 21:48:04'),
(120, 26, 'unknown', '2026-02-25 14:05:34', 1025, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 22:05:34'),
(121, 32, 'unknown', '2026-02-25 14:12:01', 373, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 22:12:01'),
(122, 26, 'unknown', '2026-02-25 14:15:10', 178, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 22:15:10'),
(123, 32, 'unknown', '2026-02-25 14:15:54', 28, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 22:15:54'),
(124, 26, 'unknown', '2026-02-25 14:17:09', 44, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 22:17:09'),
(125, 26, 'unknown', '2026-02-28 08:09:24', 1174, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:09:24'),
(126, 32, 'unknown', '2026-02-28 08:10:41', 66, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:10:41'),
(127, 32, 'unknown', '2026-02-28 08:16:06', 287, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:16:06'),
(128, 26, 'unknown', '2026-02-28 08:18:44', 132, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:18:44'),
(129, 32, 'unknown', '2026-02-28 08:47:18', 1692, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:47:18'),
(130, 26, 'unknown', '2026-02-28 09:12:58', 1527, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 17:12:58'),
(131, 26, 'unknown', '2026-02-28 09:13:17', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 17:13:17'),
(132, 32, 'unknown', '2026-02-28 09:56:21', 2555, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 17:56:21'),
(133, 26, 'unknown', '2026-03-01 11:38:56', 2469, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 19:38:56'),
(134, 26, 'unknown', '2026-03-02 07:57:47', 909, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 15:57:47'),
(135, 32, 'unknown', '2026-03-02 11:09:13', 1400, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 19:09:13'),
(136, 26, 'unknown', '2026-03-02 11:52:49', 2603, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 19:52:49'),
(137, 26, 'unknown', '2026-03-02 11:52:49', 2603, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 19:52:49'),
(138, 26, 'unknown', '2026-03-03 10:31:42', 544, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 18:31:42'),
(139, 19, 'unknown', '2026-03-03 10:35:27', 182, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 18:35:27'),
(140, 32, 'unknown', '2026-03-03 10:58:52', 1371, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 18:58:52'),
(141, 32, 'unknown', '2026-03-03 11:03:59', 92, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:03:59'),
(142, 19, 'unknown', '2026-03-03 11:05:41', 45, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:05:41'),
(143, 32, 'unknown', '2026-03-03 11:08:02', 127, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:08:02'),
(144, 32, 'unknown', '2026-03-03 11:32:18', 886, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:32:18'),
(145, 26, 'unknown', '2026-03-03 11:33:14', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:33:14'),
(146, 32, 'unknown', '2026-03-05 11:09:22', 1705, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 19:09:22'),
(147, 32, 'unknown', '2026-03-05 11:10:29', 17, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 19:10:29'),
(148, 32, 'unknown', '2026-03-05 11:14:51', 118, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 19:14:51'),
(149, 32, 'unknown', '2026-03-05 11:22:26', 43, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 19:22:26'),
(150, 32, 'unknown', '2026-03-05 12:12:47', 2951, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:12:47'),
(151, 19, 'unknown', '2026-03-05 12:16:00', 173, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:16:00'),
(152, 32, 'unknown', '2026-03-05 12:16:41', 28, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:16:41'),
(153, 19, 'unknown', '2026-03-05 12:17:37', 42, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:17:37'),
(154, 32, 'unknown', '2026-03-05 12:18:08', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:18:08'),
(155, 19, 'unknown', '2026-03-05 12:24:57', 395, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:24:57'),
(156, 32, 'unknown', '2026-03-05 12:55:51', 1842, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:55:51'),
(157, 26, 'unknown', '2026-03-06 10:41:06', 669, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 18:41:06'),
(158, 32, 'unknown', '2026-03-09 13:56:33', 454, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 20:56:33'),
(159, 26, 'unknown', '2026-03-09 13:57:47', 60, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 20:57:47'),
(160, 32, 'unknown', '2026-03-10 13:45:29', 1307, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:45:29'),
(161, 26, 'unknown', '2026-03-10 13:48:21', 151, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 20:48:21'),
(162, 32, 'unknown', '2026-03-10 14:48:15', 3581, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 21:48:15'),
(163, 26, 'unknown', '2026-03-10 14:50:24', 103, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 21:50:24'),
(164, 32, 'unknown', '2026-03-11 12:37:08', 2538, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 19:37:08'),
(165, 26, 'unknown', '2026-03-11 13:03:56', 341, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 20:03:56'),
(166, 26, 'unknown', '2026-03-12 12:11:21', 1216, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 19:11:21'),
(167, 19, 'unknown', '2026-03-12 12:26:08', 869, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 19:26:08'),
(168, 32, 'unknown', '2026-03-12 12:57:42', 1854, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 19:57:42'),
(169, 32, 'unknown', '2026-03-12 13:15:25', 999, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 20:15:25'),
(170, 26, 'unknown', '2026-03-12 13:34:15', 1118, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 20:34:15'),
(171, 26, 'unknown', '2026-03-12 13:36:24', 104, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 20:36:24'),
(172, 32, 'unknown', '2026-03-12 14:07:38', 1848, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 21:07:38'),
(173, 32, 'unknown', '2026-03-12 15:03:09', 3173, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 22:03:09'),
(174, 26, 'unknown', '2026-03-12 15:06:30', 134, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 22:06:30'),
(175, 32, 'unknown', '2026-03-12 15:32:22', 94, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 22:32:22'),
(176, 26, 'unknown', '2026-03-12 15:37:52', 308, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 22:37:52'),
(177, 32, 'unknown', '2026-03-13 11:16:39', 3556, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 18:16:39'),
(178, 32, 'unknown', '2026-03-14 11:10:28', 2126, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 18:10:28'),
(179, 26, 'unknown', '2026-03-19 12:32:12', 767, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 19:32:12'),
(180, 19, 'unknown', '2026-03-19 23:27:17', 2038, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 06:27:17'),
(181, 26, 'unknown', '2026-03-19 23:51:33', 1408, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 06:51:33'),
(182, 19, 'unknown', '2026-03-20 01:40:48', 2161, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 08:40:48'),
(183, 19, 'unknown', '2026-03-20 02:03:37', 1265, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 09:03:37'),
(184, 19, 'unknown', '2026-03-20 02:07:19', 210, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 09:07:19'),
(185, 32, 'unknown', '2026-03-20 08:53:53', 554, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 15:53:53'),
(186, 32, 'unknown', '2026-03-20 09:28:42', 1064, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:28:42'),
(187, 26, 'unknown', '2026-03-20 09:30:28', 94, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:30:28'),
(188, 32, 'unknown', '2026-03-20 09:33:13', 109, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:33:13'),
(189, 26, 'unknown', '2026-03-20 09:34:26', 47, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:34:26'),
(190, 32, 'unknown', '2026-03-20 09:50:29', 954, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:50:29'),
(191, 26, 'unknown', '2026-03-20 11:30:04', 118, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:30:04'),
(192, 32, 'unknown', '2026-03-20 12:00:14', 1799, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 19:00:14'),
(193, 26, 'unknown', '2026-03-20 12:40:44', 2417, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 19:40:44'),
(194, 32, 'unknown', '2026-03-20 12:59:12', 1096, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 19:59:12'),
(195, 32, 'unknown', '2026-03-20 14:08:00', 276, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 21:08:00'),
(196, 32, 'unknown', '2026-03-20 14:12:57', 119, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 21:12:57'),
(197, 26, 'unknown', '2026-03-20 14:27:26', 553, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 21:27:26'),
(198, 26, 'unknown', '2026-03-20 14:37:35', 399, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 21:37:35'),
(199, 26, 'unknown', '2026-03-20 14:45:16', 20, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 21:45:16'),
(200, 26, 'unknown', '2026-03-20 14:53:22', 311, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 21:53:22'),
(201, 26, 'unknown', '2026-03-20 15:08:05', 191, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 22:08:05'),
(202, 32, 'unknown', '2026-03-20 15:16:18', 466, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 22:16:18'),
(203, 26, 'unknown', '2026-03-20 15:17:20', 49, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 22:17:20'),
(204, 32, 'unknown', '2026-03-20 15:45:38', 1681, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 22:45:38'),
(205, 26, 'unknown', '2026-03-20 15:47:49', 48, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 22:47:49'),
(206, 32, 'unknown', '2026-03-20 15:53:28', 324, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 22:53:28'),
(207, 26, 'unknown', '2026-03-21 00:21:41', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 07:21:41'),
(208, 32, 'unknown', '2026-03-21 00:35:36', 822, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 07:35:36'),
(209, 26, 'unknown', '2026-03-21 00:50:40', 517, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 07:50:40'),
(210, 32, 'unknown', '2026-03-21 00:54:13', 186, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 07:54:13'),
(211, 26, 'unknown', '2026-03-21 01:43:26', 2917, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 08:43:26'),
(212, 31, 'unknown', '2026-03-21 05:09:14', 176, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 12:09:14'),
(213, 26, 'unknown', '2026-03-21 05:27:44', 1097, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 12:27:44'),
(214, 32, 'unknown', '2026-03-22 13:29:19', 487, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 20:29:19'),
(215, 32, 'unknown', '2026-03-26 07:21:24', 379, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(11) NOT NULL,
  `user_avg_price` decimal(10,2) DEFAULT 0.00,
  `user_avg_response_time` float DEFAULT 24,
  `user_total_bookings` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `user_avg_price`, `user_avg_response_time`, `user_total_bookings`, `updated_at`) VALUES
(11, 0.00, 24, 3, '2026-03-27 08:27:00'),
(31, 10000.00, 24, 2, '2026-03-27 08:27:00'),
(32, 40000.00, 16, 3, '2026-03-27 08:27:00'),
(33, 0.00, 24, 1, '2026-03-27 08:27:00'),
(34, 0.00, 24, 0, '2026-03-27 08:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `device` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_id`, `device`, `ip_address`, `user_agent`, `login_time`, `logout_time`, `is_active`) VALUES
(1, 32, 'ftb7r35i9ll2em0i8hj6h943ho', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 19:20:21', NULL, 1),
(2, 32, 'njhbol875q9jr1jfugj0jmqkuq', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 20:21:12', '2026-03-22 20:29:19', 0),
(3, 31, 'bai41pkbe9q21qug3el2u0upmd', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 20:29:37', NULL, 1),
(4, 32, '2r0bi1mqb2nc7rlimn4m398t29', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:15:05', '2026-03-26 14:21:24', 0),
(5, 31, 'k97tjgp3ln17469fr7kl7h2jfm', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:21:43', NULL, 1),
(6, 32, 'gfk1hlbjtps0d7n6mho2qn679l', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:19:34', NULL, 1),
(7, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:33:05', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `setting_key`, `setting_value`, `status`, `created_at`, `updated_at`) VALUES
(1, 11, 'profile_visibility', 'public', 'active', '2025-11-24 19:11:49', '2025-11-24 19:11:49'),
(2, 11, 'show_contact_info', '1', 'active', '2025-11-24 19:11:49', '2025-11-24 19:11:49'),
(3, 11, 'data_sharing', '0', 'active', '2025-11-24 19:11:49', '2025-11-24 19:11:49'),
(4, 11, 'cookie_consent', '1', 'active', '2025-11-24 19:11:49', '2025-11-24 19:11:49'),
(5, 11, 'search_visibility', '1', 'active', '2025-11-24 19:11:49', '2025-11-24 19:11:49'),
(11, 28, 'profile_visibility', 'members', 'active', '2025-11-27 21:32:24', '2025-11-27 21:32:24'),
(12, 28, 'show_contact_info', '1', 'active', '2025-11-27 21:32:24', '2025-11-27 21:32:24'),
(13, 28, 'data_sharing', '0', 'active', '2025-11-27 21:32:24', '2025-11-27 21:32:24'),
(14, 28, 'cookie_consent', '1', 'active', '2025-11-27 21:32:24', '2025-11-27 21:32:24'),
(15, 28, 'search_visibility', '1', 'active', '2025-11-27 21:32:24', '2025-11-27 21:32:24');

-- --------------------------------------------------------

--
-- Table structure for table `verification_documents`
--

CREATE TABLE `verification_documents` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_documents`
--

INSERT INTO `verification_documents` (`id`, `provider_id`, `document_type`, `document_path`, `status`, `uploaded_at`, `reviewed_at`, `reviewer_id`, `notes`) VALUES
(1, 12, 'national_id', 'verification_12_national_id_1765829230.pdf', 'approved', '2025-12-15 20:07:10', '2025-12-16 16:54:20', 19, NULL),
(2, 12, 'certificate', 'verification_12_certificate_1765829230.pdf', 'pending', '2025-12-15 20:07:10', '2025-12-17 07:04:13', 19, NULL),
(3, 12, 'selfie', 'selfie_12_1765904601.jpeg', 'approved', '2025-12-16 17:03:21', '2026-03-20 06:56:04', 19, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_log_user_id` (`user_id`),
  ADD KEY `idx_activity_log_type` (`activity_type`),
  ADD KEY `idx_activity_log_created_at` (`created_at`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`),
  ADD KEY `blocked_by` (`blocked_by`),
  ADD KEY `idx_blocked_ips_ip` (`ip_address`);

--
-- Indexes for table `blocked_users`
--
ALTER TABLE `blocked_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_blocker_blocked` (`blocker_id`,`blocked_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_service_id` (`service_id`);

--
-- Indexes for table `booking_notifications`
--
ALTER TABLE `booking_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categories_active` (`is_active`),
  ADD KEY `idx_categories_premium` (`is_premium`),
  ADD KEY `idx_ai_enabled` (`is_ai_enabled`);

--
-- Indexes for table `click_logs`
--
ALTER TABLE `click_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `status` (`status`),
  ADD KEY `priority_level` (`priority_level`),
  ADD KEY `fk_complaints_admin` (`assigned_admin_id`);

--
-- Indexes for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_complaint` (`complaint_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `complaint_logs`
--
ALTER TABLE `complaint_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_complaint` (`complaint_id`),
  ADD KEY `idx_admin` (`admin_id`);

--
-- Indexes for table `complaint_notes`
--
ALTER TABLE `complaint_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_complaint` (`complaint_id`),
  ADD KEY `idx_admin` (`admin_id`);

--
-- Indexes for table `complaint_responses`
--
ALTER TABLE `complaint_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_complaint` (`complaint_id`),
  ADD KEY `idx_admin` (`admin_id`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_logs`
--
ALTER TABLE `event_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_logs_user_id` (`user_id`),
  ADD KEY `idx_event_logs_session_id` (`session_id`),
  ADD KEY `idx_event_logs_event_type` (`event_type`),
  ADD KEY `idx_event_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_event_logs_created_at` (`created_at`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`client_id`,`provider_id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `finalized_service_prices`
--
ALTER TABLE `finalized_service_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `google_calendar_tokens`
--
ALTER TABLE `google_calendar_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider_id` (`provider_id`);

--
-- Indexes for table `location_coordinates`
--
ALTER TABLE `location_coordinates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_name` (`location_name`),
  ADD KEY `idx_location_name` (`location_name`),
  ADD KEY `idx_district` (`district`),
  ADD KEY `idx_sector` (`sector`);

--
-- Indexes for table `login_security`
--
ALTER TABLE `login_security`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_messages_sender_id` (`sender_id`),
  ADD KEY `idx_messages_receiver_id` (`receiver_id`),
  ADD KEY `idx_messages_created_at` (`created_at`),
  ADD KEY `idx_messages_is_read` (`is_read`);

--
-- Indexes for table `ml_interactions`
--
ALTER TABLE `ml_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `recorded_at` (`recorded_at`);

--
-- Indexes for table `muted_chats`
--
ALTER TABLE `muted_chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_muted` (`user_id`,`muted_user_id`);

--
-- Indexes for table `negotiation_history`
--
ALTER TABLE `negotiation_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `offer_id` (`offer_id`),
  ADD KEY `counteroffer_id` (`counteroffer_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_idx` (`user_id`),
  ADD KEY `notification_type_idx` (`notification_type`),
  ADD KEY `is_read_idx` (`is_read`),
  ADD KEY `created_at_idx` (`created_at`),
  ADD KEY `priority_idx` (`priority`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_notifications_related` (`related_id`,`related_type`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_logs_user_id` (`user_id`),
  ADD KEY `idx_notification_logs_type` (`notification_type`),
  ADD KEY `idx_notification_logs_created_at` (`created_at`),
  ADD KEY `idx_notification_logs_status` (`status`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_unique` (`user_id`);

--
-- Indexes for table `notification_read_status`
--
ALTER TABLE `notification_read_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_user_unique` (`notification_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_templates_type` (`template_type`),
  ADD KEY `idx_templates_active` (`is_active`);

--
-- Indexes for table `page_sessions`
--
ALTER TABLE `page_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`,`page_url`),
  ADD KEY `user_id` (`user_id`,`start_time`);

--
-- Indexes for table `page_views`
--
ALTER TABLE `page_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_url` (`page_url`,`viewed_at`),
  ADD KEY `user_id` (`user_id`,`viewed_at`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `payout_history`
--
ALTER TABLE `payout_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `payment_method_id` (`payment_method_id`);

--
-- Indexes for table `portfolio_images`
--
ALTER TABLE `portfolio_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `portfolio_videos`
--
ALTER TABLE `portfolio_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `provider_availability`
--
ALTER TABLE `provider_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_provider_date` (`provider_id`,`date`);

--
-- Indexes for table `provider_availability_patterns`
--
ALTER TABLE `provider_availability_patterns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_provider_day_hour` (`provider_id`,`day_of_week`,`hour_of_day`);

--
-- Indexes for table `provider_categories`
--
ALTER TABLE `provider_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_provider_category` (`provider_id`,`category_id`),
  ADD KEY `fk_pc_category` (`category_id`);

--
-- Indexes for table `provider_documents`
--
ALTER TABLE `provider_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider_documents_provider` (`provider_id`),
  ADD KEY `idx_provider_documents_type` (`document_type`);

--
-- Indexes for table `provider_payment_methods`
--
ALTER TABLE `provider_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `provider_performance`
--
ALTER TABLE `provider_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_provider_period` (`provider_id`,`period_start`,`period_end`);

--
-- Indexes for table `provider_services`
--
ALTER TABLE `provider_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_ps_provider_id` (`provider_id`),
  ADD KEY `idx_ps_category_id` (`category_id`),
  ADD KEY `idx_payment_type` (`payment_type`);

--
-- Indexes for table `provider_service_areas`
--
ALTER TABLE `provider_service_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `provider_settings`
--
ALTER TABLE `provider_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_provider_setting` (`provider_id`,`setting_key`);

--
-- Indexes for table `provider_shares`
--
ALTER TABLE `provider_shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`,`shared_at`);

--
-- Indexes for table `provider_social_links`
--
ALTER TABLE `provider_social_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider` (`provider_id`);

--
-- Indexes for table `provider_time_off`
--
ALTER TABLE `provider_time_off`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `provider_views`
--
ALTER TABLE `provider_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`,`viewed_at`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_user_id` (`reported_user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_reviews_provider_id` (`provider_id`),
  ADD KEY `idx_reviews_client_id` (`client_id`),
  ADD KEY `idx_reviews_rating` (`rating`),
  ADD KEY `idx_reviews_created_at` (`created_at`),
  ADD KEY `idx_reviews_booking_id` (`booking_id`);

--
-- Indexes for table `search_logs`
--
ALTER TABLE `search_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `search_type` (`search_type`,`searched_at`),
  ADD KEY `user_id` (`user_id`,`searched_at`);

--
-- Indexes for table `service_counteroffers`
--
ALTER TABLE `service_counteroffers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_offer_id` (`offer_id`);

--
-- Indexes for table `service_offers`
--
ALTER TABLE `service_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_client_id` (`client_id`);

--
-- Indexes for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_service_providers_user_id` (`user_id`),
  ADD KEY `idx_service_providers_location` (`location`),
  ADD KEY `idx_service_providers_district` (`district`),
  ADD KEY `idx_sp_user_id` (`user_id`),
  ADD KEY `idx_sp_active` (`is_active`),
  ADD KEY `idx_sp_banned` (`is_banned`),
  ADD KEY `idx_sp_featured` (`is_featured`),
  ADD KEY `idx_sp_verification` (`verification_level`),
  ADD KEY `idx_sp_availability` (`availability`),
  ADD KEY `idx_sp_location` (`location`),
  ADD KEY `idx_sp_district` (`district`),
  ADD KEY `idx_sp_profession` (`profession`),
  ADD KEY `idx_sp_sector` (`sector`),
  ADD KEY `idx_sp_rating` (`average_rating`),
  ADD KEY `idx_sp_status` (`status`),
  ADD KEY `idx_sp_is_active` (`is_active`),
  ADD KEY `idx_providers_ai` (`profession`,`location`,`average_rating`,`is_featured`),
  ADD KEY `idx_coordinates` (`latitude`,`longitude`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `toxic_content_logs`
--
ALTER TABLE `toxic_content_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_phone` (`phone`),
  ADD KEY `idx_users_otp` (`otp_code`),
  ADD KEY `idx_users_verified` (`is_verified`),
  ADD KEY `idx_users_type` (`user_type`),
  ADD KEY `idx_users_email_verified` (`email_verified`),
  ADD KEY `idx_users_phone_verified` (`phone_verified`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_is_active` (`is_active`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_activities_user_id` (`user_id`),
  ADD KEY `idx_user_activities_type` (`activity_type`),
  ADD KEY `idx_user_activities_created_at` (`created_at`);

--
-- Indexes for table `user_logout_logs`
--
ALTER TABLE `user_logout_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_logout_time` (`logout_time`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_user_total_bookings` (`user_total_bookings`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_setting` (`user_id`,`setting_key`),
  ADD KEY `idx_user_settings_user_id` (`user_id`),
  ADD KEY `idx_user_settings_key` (`setting_key`);

--
-- Indexes for table `verification_documents`
--
ALTER TABLE `verification_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_users`
--
ALTER TABLE `blocked_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `booking_notifications`
--
ALTER TABLE `booking_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `click_logs`
--
ALTER TABLE `click_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_logs`
--
ALTER TABLE `complaint_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaint_notes`
--
ALTER TABLE `complaint_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_responses`
--
ALTER TABLE `complaint_responses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `event_logs`
--
ALTER TABLE `event_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `finalized_service_prices`
--
ALTER TABLE `finalized_service_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `google_calendar_tokens`
--
ALTER TABLE `google_calendar_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `location_coordinates`
--
ALTER TABLE `location_coordinates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `login_security`
--
ALTER TABLE `login_security`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `ml_interactions`
--
ALTER TABLE `ml_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `muted_chats`
--
ALTER TABLE `muted_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `negotiation_history`
--
ALTER TABLE `negotiation_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_read_status`
--
ALTER TABLE `notification_read_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `page_sessions`
--
ALTER TABLE `page_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `page_views`
--
ALTER TABLE `page_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payout_history`
--
ALTER TABLE `payout_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portfolio_images`
--
ALTER TABLE `portfolio_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `portfolio_videos`
--
ALTER TABLE `portfolio_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_availability`
--
ALTER TABLE `provider_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_availability_patterns`
--
ALTER TABLE `provider_availability_patterns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_categories`
--
ALTER TABLE `provider_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `provider_documents`
--
ALTER TABLE `provider_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_payment_methods`
--
ALTER TABLE `provider_payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `provider_performance`
--
ALTER TABLE `provider_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `provider_services`
--
ALTER TABLE `provider_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `provider_service_areas`
--
ALTER TABLE `provider_service_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `provider_settings`
--
ALTER TABLE `provider_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `provider_shares`
--
ALTER TABLE `provider_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `provider_social_links`
--
ALTER TABLE `provider_social_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_time_off`
--
ALTER TABLE `provider_time_off`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `provider_views`
--
ALTER TABLE `provider_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `search_logs`
--
ALTER TABLE `search_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `service_counteroffers`
--
ALTER TABLE `service_counteroffers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `service_offers`
--
ALTER TABLE `service_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_providers`
--
ALTER TABLE `service_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=303;

--
-- AUTO_INCREMENT for table `toxic_content_logs`
--
ALTER TABLE `toxic_content_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_logout_logs`
--
ALTER TABLE `user_logout_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `verification_documents`
--
ALTER TABLE `verification_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `admin_activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD CONSTRAINT `blocked_ips_ibfk_1` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_service` FOREIGN KEY (`service_id`) REFERENCES `provider_services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `click_logs`
--
ALTER TABLE `click_logs`
  ADD CONSTRAINT `click_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_complaints_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD CONSTRAINT `fk_ca_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_logs`
--
ALTER TABLE `complaint_logs`
  ADD CONSTRAINT `fk_cl_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_notes`
--
ALTER TABLE `complaint_notes`
  ADD CONSTRAINT `fk_cn_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_responses`
--
ALTER TABLE `complaint_responses`
  ADD CONSTRAINT `fk_cr_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `finalized_service_prices`
--
ALTER TABLE `finalized_service_prices`
  ADD CONSTRAINT `finalized_service_prices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `finalized_service_prices_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `provider_services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `finalized_service_prices_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `finalized_service_prices_ibfk_4` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `google_calendar_tokens`
--
ALTER TABLE `google_calendar_tokens`
  ADD CONSTRAINT `google_calendar_tokens_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_security`
--
ALTER TABLE `login_security`
  ADD CONSTRAINT `login_security_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `negotiation_history`
--
ALTER TABLE `negotiation_history`
  ADD CONSTRAINT `negotiation_history_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `negotiation_history_ibfk_2` FOREIGN KEY (`offer_id`) REFERENCES `service_offers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `negotiation_history_ibfk_3` FOREIGN KEY (`counteroffer_id`) REFERENCES `service_counteroffers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_read_status`
--
ALTER TABLE `notification_read_status`
  ADD CONSTRAINT `notification_read_status_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_read_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `page_sessions`
--
ALTER TABLE `page_sessions`
  ADD CONSTRAINT `page_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `page_views`
--
ALTER TABLE `page_views`
  ADD CONSTRAINT `page_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`);

--
-- Constraints for table `payout_history`
--
ALTER TABLE `payout_history`
  ADD CONSTRAINT `payout_history_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`),
  ADD CONSTRAINT `payout_history_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`);

--
-- Constraints for table `portfolio_images`
--
ALTER TABLE `portfolio_images`
  ADD CONSTRAINT `portfolio_images_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `portfolio_videos`
--
ALTER TABLE `portfolio_videos`
  ADD CONSTRAINT `fk_portfolio_videos_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `provider_availability`
--
ALTER TABLE `provider_availability`
  ADD CONSTRAINT `provider_availability_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_availability_patterns`
--
ALTER TABLE `provider_availability_patterns`
  ADD CONSTRAINT `provider_availability_patterns_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_categories`
--
ALTER TABLE `provider_categories`
  ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pc_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_documents`
--
ALTER TABLE `provider_documents`
  ADD CONSTRAINT `provider_documents_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_payment_methods`
--
ALTER TABLE `provider_payment_methods`
  ADD CONSTRAINT `provider_payment_methods_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_performance`
--
ALTER TABLE `provider_performance`
  ADD CONSTRAINT `provider_performance_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_services`
--
ALTER TABLE `provider_services`
  ADD CONSTRAINT `provider_services_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provider_services_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_service_areas`
--
ALTER TABLE `provider_service_areas`
  ADD CONSTRAINT `provider_service_areas_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_settings`
--
ALTER TABLE `provider_settings`
  ADD CONSTRAINT `provider_settings_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_shares`
--
ALTER TABLE `provider_shares`
  ADD CONSTRAINT `provider_shares_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provider_shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `provider_social_links`
--
ALTER TABLE `provider_social_links`
  ADD CONSTRAINT `provider_social_links_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_time_off`
--
ALTER TABLE `provider_time_off`
  ADD CONSTRAINT `provider_time_off_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `provider_views`
--
ALTER TABLE `provider_views`
  ADD CONSTRAINT `provider_views_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provider_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_booking_id` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `search_logs`
--
ALTER TABLE `search_logs`
  ADD CONSTRAINT `search_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_counteroffers`
--
ALTER TABLE `service_counteroffers`
  ADD CONSTRAINT `service_counteroffers_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `service_offers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_counteroffers_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `provider_services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_counteroffers_ibfk_3` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_counteroffers_ibfk_4` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_offers`
--
ALTER TABLE `service_offers`
  ADD CONSTRAINT `service_offers_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_offers_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `provider_services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_offers_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_offers_ibfk_4` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD CONSTRAINT `service_providers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `toxic_content_logs`
--
ALTER TABLE `toxic_content_logs`
  ADD CONSTRAINT `toxic_content_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_logout_logs`
--
ALTER TABLE `user_logout_logs`
  ADD CONSTRAINT `user_logout_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_documents`
--
ALTER TABLE `verification_documents`
  ADD CONSTRAINT `verification_documents_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
