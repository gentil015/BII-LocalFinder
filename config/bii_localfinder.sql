-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 18, 2026 at 06:38 PM
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

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`id`, `admin_id`, `activity_type`, `activity_details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 19, 'login', 'Admin logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 18:32:50'),
(2, 19, 'login', 'Admin logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 18:34:30'),
(3, 19, 'login', 'Admin logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 19:18:34'),
(4, 19, 'login', 'Admin logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 19:22:53'),
(5, 19, 'login', 'Admin logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 20:03:48'),
(6, 19, 'login', 'Admin logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 12:45:37');

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
(38, 32, 12, '', 48, 'I need someone to fix my lick pop', 60000.00, 'pending', '2026-03-26', '10:30:00', 'cancelled', NULL, 'changed mind', '2026-03-30 15:23:57', '2026-03-20 16:16:56', '2026-03-30 22:23:57', NULL, NULL, 0),
(39, 31, 12, '', 48, 'jhrdstgretgzserysxg', 78943.00, 'pending', '2026-03-29', '10:30:00', 'confirmed', NULL, NULL, NULL, '2026-03-21 12:07:42', '2026-04-16 16:02:50', NULL, '2026-04-16 09:02:50', 0),
(40, 32, 12, '', 47, 'I need the wedding driver', NULL, 'pending', '2026-04-24', '07:00:00', 'confirmed', NULL, NULL, NULL, '2026-04-04 00:13:27', '2026-04-16 15:52:16', NULL, '2026-04-16 08:52:16', 0),
(41, 32, 12, 'mahoko/rubavu/west', 47, 'jbcgmnchgjmhcgchggghjmnh', NULL, 'pending', '2026-04-21', '12:00:00', 'pending', NULL, NULL, NULL, '2026-04-16 16:06:29', '2026-04-16 16:06:29', NULL, NULL, 0),
(42, 32, 12, 'mahoko/rubavu/west', 47, 'ifkyutdjyyyhsdhrtfds', 20000.00, 'completed', '2026-04-30', '09:00:00', 'confirmed', NULL, NULL, NULL, '2026-04-16 16:17:07', '2026-04-16 16:19:38', NULL, '2026-04-16 09:18:13', 0),
(43, 32, 12, 'mahoko/rubavu/west', 47, 'tsrhtershersttttttttttttrrrrrrrrrrrs', 20000.00, 'pending', '2026-04-23', '09:00:00', 'confirmed', NULL, NULL, NULL, '2026-04-16 18:02:21', '2026-04-16 18:04:07', NULL, '2026-04-16 11:04:07', 0);

-- --------------------------------------------------------

--
-- Table structure for table `booking_logs`
--

CREATE TABLE `booking_logs` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `source_page_url` text DEFAULT NULL,
  `source_page_title` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, NULL, 'click_test', 'test_target', 123, 'http://localhost/Bii_localFinder/client/providers.php', '{\\', '::1', 'curl/8.13.0', 'eijbjpp4qvs055jae4cm09fsu7', '2026-03-21 17:18:57'),
(2, NULL, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:54:56'),
(3, 36, 'provider_card_view', 'provider', 16, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '5r5op3l1h59nntenhv12r1ug27', '2026-03-30 22:52:25'),
(4, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:42:54'),
(5, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:02:45'),
(6, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:03:46'),
(7, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:11:26'),
(8, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:29:06'),
(9, 32, 'provider_card_view', 'provider', 15, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:31:57'),
(10, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:12:05'),
(11, 32, 'open_service', 'service', 49, 'http://localhost/bii_localfinder/client/services.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:00:45'),
(12, 32, 'open_service', 'service', 49, 'http://localhost/bii_localfinder/client/services.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:00:47'),
(13, 32, 'open_service', 'service', 47, 'http://localhost/bii_localfinder/client/services.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:03'),
(14, 32, 'book_now', 'service', 47, 'http://localhost/bii_localfinder/client/service.php?service_id=47', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:33'),
(15, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:22:52'),
(16, 32, 'open_service', 'service', 50, 'http://localhost/bii_localfinder/client/services.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:16'),
(17, 32, 'book_now', 'service', 50, 'http://localhost/bii_localfinder/client/service.php?service_id=50', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:22'),
(18, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:50:09'),
(19, 32, 'provider_card_view', 'provider', 12, 'http://localhost/bii_localfinder/client/providers.php', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:56:02');

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
(4, 1, 'um0v6o41g5qrd4kctu2k6228h0', 'provider_view', 'provider', 456, '{\"action\":\"view\",\"provider_id\":456,\"source\":\"test\"}', '2026-03-21 18:59:07'),
(5, 32, 'f19a115c76sobv08p923susvhk', 'send_message', 'message', 29, '{\"sender_id\":32,\"receiver_id\":26,\"message_type\":\"text\",\"has_attachment\":false}', '2026-03-29 17:08:29'),
(6, 26, 'i7u6v9cfbgk9pcf885eclrfagu', 'message_read', 'user', 32, '{\"sender_id\":32,\"receiver_id\":26,\"messages_read_count\":1}', '2026-03-29 17:08:56'),
(7, 26, 'i7u6v9cfbgk9pcf885eclrfagu', 'send_message', 'message', 30, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-03-29 17:20:30'),
(8, 32, 'f373nmt3t2j2ffe6tnuaaddra6', 'message_read', 'user', 26, '{\"sender_id\":26,\"receiver_id\":32,\"messages_read_count\":1}', '2026-03-30 07:46:09'),
(9, 26, 'fthopo3t4j9t07d97t70lrfgro', 'send_message', 'message', 31, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-03-30 09:27:49'),
(10, 32, 'l3f99n8edgs58ijnrjs6qprkr4', 'message_read', 'user', 26, '{\"sender_id\":26,\"receiver_id\":32,\"messages_read_count\":1}', '2026-03-30 09:29:05'),
(11, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'send_message', 'message', 32, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"service\",\"has_attachment\":false}', '2026-03-30 10:25:00'),
(12, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'send_message', 'message', 33, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-03-30 10:40:26'),
(13, 32, 'deuqpu4if92v105383qr0lbdkb', 'message_read', 'user', 26, '{\"sender_id\":26,\"receiver_id\":32,\"messages_read_count\":2}', '2026-03-30 20:54:15'),
(14, 32, 'deuqpu4if92v105383qr0lbdkb', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 21:52:21'),
(15, 32, 'deuqpu4if92v105383qr0lbdkb', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 21:53:21'),
(16, 32, 'deuqpu4if92v105383qr0lbdkb', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 21:53:41'),
(17, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 21:55:26'),
(18, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"rating\",\"avail\":\"\"}}', '2026-03-30 21:55:35'),
(19, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"newest\",\"avail\":\"\"}}', '2026-03-30 21:55:47'),
(20, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"price_desc\",\"avail\":\"\"}}', '2026-03-30 21:55:52'),
(21, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 21:56:05'),
(22, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 22:00:17'),
(23, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 22:01:29'),
(24, 32, '6am9smbio683o1jagofv1g3leq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 22:05:28'),
(25, 32, '6am9smbio683o1jagofv1g3leq', 'booking_cancelled', 'booking', 38, '{\"cancellation_reason\":\"changed mind\",\"client_id\":32,\"provider_id\":12}', '2026-03-30 22:23:58'),
(26, 36, '5r5op3l1h59nntenhv12r1ug27', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 22:51:21'),
(27, 36, '5r5op3l1h59nntenhv12r1ug27', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 22:52:17'),
(28, 36, '5r5op3l1h59nntenhv12r1ug27', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-03-30 22:52:49'),
(29, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 09:42:29'),
(30, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 09:43:16'),
(31, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 09:58:32'),
(32, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 09:59:35'),
(33, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 10:03:04'),
(34, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 10:06:22'),
(35, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 10:08:05'),
(36, 32, 'og0tq3ltp69i2uva350kopglds', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 11:10:47'),
(37, 32, 'og0tq3ltp69i2uva350kopglds', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 11:17:14'),
(38, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 14:28:58'),
(39, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 14:30:18'),
(40, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 14:32:14'),
(41, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 14:32:57'),
(42, 26, 'l2smae5oao5rmve5o4o1ip1osn', 'message_read', 'user', 32, '{\"sender_id\":32,\"receiver_id\":26,\"messages_read_count\":1}', '2026-04-01 14:41:07'),
(43, 26, 'l2smae5oao5rmve5o4o1ip1osn', 'send_message', 'message', 36, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"service\",\"has_attachment\":false}', '2026-04-01 14:41:44'),
(44, 32, 'eooaiij09kobtmcev2cu85bsjp', 'message_read', 'user', 26, '{\"sender_id\":26,\"receiver_id\":32,\"messages_read_count\":1}', '2026-04-01 14:42:33'),
(45, 32, 'eooaiij09kobtmcev2cu85bsjp', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 14:42:38'),
(46, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 20:30:45'),
(47, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"system\",\"avail\":\"\"}}', '2026-04-01 20:31:09'),
(48, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 20:31:20'),
(49, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 20:31:22'),
(50, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"system\",\"avail\":\"\"}}', '2026-04-01 20:42:56'),
(51, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 20:43:19'),
(52, 32, 'v5pv03vck8rmfegi4irl0fu505', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 20:43:22'),
(53, 32, '068v7mf0tmt3qick57e5b12bmo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 20:59:14'),
(54, 32, '068v7mf0tmt3qick57e5b12bmo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:00:22'),
(55, 32, '068v7mf0tmt3qick57e5b12bmo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:05:34'),
(56, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:09:26'),
(57, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:09:51'),
(58, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"system\",\"avail\":\"\"}}', '2026-04-01 21:10:09'),
(59, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:10:32'),
(60, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:25:03'),
(61, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:26:33'),
(62, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:27:51'),
(63, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:28:01'),
(64, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:31:58'),
(65, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:34:31'),
(66, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:36:16'),
(67, 32, 'rfebqhsns022kic0k2guolsh6l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:37:58'),
(68, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:42:13'),
(69, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:42:22'),
(70, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:51:04'),
(71, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:51:37'),
(72, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:58:39'),
(73, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 21:59:14'),
(74, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:01:00'),
(75, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:03:08'),
(76, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:03:55'),
(77, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:04:47'),
(78, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:08:59'),
(79, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:09:48'),
(80, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:10:59'),
(81, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:22:13'),
(82, 32, 'qs2u070r6gadetjrfq4ibquch0', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-01 22:23:32'),
(83, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 08:21:29'),
(84, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 08:26:10'),
(85, 32, 'ebja1i9886b9a52bka9grcojon', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 14:16:34'),
(86, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 14:26:35'),
(87, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:13:16'),
(88, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:13:55'),
(89, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:15:39'),
(90, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 15:15:47'),
(91, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:17:20'),
(92, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:17:53'),
(93, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:18:17'),
(94, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:20:04'),
(95, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:20:36'),
(96, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:20:51'),
(97, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:22:12'),
(98, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:25:33'),
(99, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:25:53'),
(100, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 15:26:13'),
(101, 32, '9do680060n5vl4131prcglr2iu', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:27:08'),
(102, 32, '9do680060n5vl4131prcglr2iu', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 15:28:11'),
(103, 32, '9do680060n5vl4131prcglr2iu', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 16:22:10'),
(104, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 16:39:03'),
(105, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 16:39:30'),
(106, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 16:49:56'),
(107, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:10:06'),
(108, 32, '2k354vrl37vnnem4uk8e0u4tua', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:28:22'),
(109, 32, '2k354vrl37vnnem4uk8e0u4tua', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 17:28:55'),
(110, 32, '2k354vrl37vnnem4uk8e0u4tua', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:29:23'),
(111, 32, '2k354vrl37vnnem4uk8e0u4tua', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 17:30:19'),
(112, 32, '2k354vrl37vnnem4uk8e0u4tua', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:30:33'),
(113, 32, '2k354vrl37vnnem4uk8e0u4tua', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:33:17'),
(114, 32, '2k354vrl37vnnem4uk8e0u4tua', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:33:31'),
(115, 32, '2k354vrl37vnnem4uk8e0u4tua', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 17:33:36'),
(116, 32, '2k354vrl37vnnem4uk8e0u4tua', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 17:34:28'),
(117, 32, '2k354vrl37vnnem4uk8e0u4tua', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 17:35:38'),
(118, 32, '56g41ok0ds94ggl18veotq3qvv', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 19:58:10'),
(119, 32, 'bnk1eb554uat135p1n0ok4pn48', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 22:27:44'),
(120, 32, 'bnk1eb554uat135p1n0ok4pn48', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 22:36:29'),
(121, 32, 'bnk1eb554uat135p1n0ok4pn48', 'client_dashboard_view', 'page', 0, '[]', '2026-04-03 22:38:19'),
(122, 32, 'bnk1eb554uat135p1n0ok4pn48', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-03 23:27:23'),
(123, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'client_dashboard_view', 'page', 0, '[]', '2026-04-04 00:11:48'),
(124, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-04 00:11:58'),
(125, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'send_message', 'message', 37, '{\"sender_id\":32,\"receiver_id\":26,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-04 00:13:38'),
(126, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'client_dashboard_view', 'page', 0, '[]', '2026-04-04 00:13:55'),
(127, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-04 00:14:01'),
(128, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'client_dashboard_view', 'page', 0, '[]', '2026-04-07 11:09:20'),
(129, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 11:10:44'),
(130, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'client_dashboard_view', 'page', 0, '[]', '2026-04-07 11:14:24'),
(131, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'client_dashboard_view', 'page', 0, '[]', '2026-04-07 11:14:52'),
(132, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'client_dashboard_view', 'page', 0, '[]', '2026-04-07 11:17:20'),
(133, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 11:19:11'),
(134, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 11:26:15'),
(135, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'client_dashboard_view', 'page', 0, '[]', '2026-04-07 18:14:41'),
(136, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 18:14:53'),
(137, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 18:28:41'),
(138, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'client_dashboard_view', 'page', 0, '[]', '2026-04-07 19:36:26'),
(139, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 19:37:15'),
(140, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 19:45:50'),
(141, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 19:53:13'),
(142, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-07 19:53:41'),
(143, 32, 'pk9orqc955k6g9rjb6she06cve', 'client_dashboard_view', 'page', 0, '[]', '2026-04-09 19:17:06'),
(144, 32, 'pk9orqc955k6g9rjb6she06cve', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-09 19:22:09'),
(145, 32, 'pk9orqc955k6g9rjb6she06cve', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-09 19:39:50'),
(146, 32, 'pk9orqc955k6g9rjb6she06cve', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-09 19:39:56'),
(147, 26, 'djod753d1fekvunm7tlsffmdni', 'message_read', 'user', 32, '{\"sender_id\":32,\"receiver_id\":26,\"messages_read_count\":1}', '2026-04-09 19:50:44'),
(148, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'client_dashboard_view', 'page', 0, '[]', '2026-04-09 21:59:35'),
(149, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-09 21:59:45'),
(150, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-09 21:59:56'),
(151, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-09 22:01:51'),
(152, 32, 'd8c49qhrs9jkricchm6l055sjk', 'client_dashboard_view', 'page', 0, '[]', '2026-04-12 21:22:14'),
(153, 32, 'd8c49qhrs9jkricchm6l055sjk', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-12 21:22:35'),
(154, 32, 'd8c49qhrs9jkricchm6l055sjk', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-12 21:23:00'),
(155, 32, 'd8c49qhrs9jkricchm6l055sjk', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-12 21:26:54'),
(156, 32, 'd8c49qhrs9jkricchm6l055sjk', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-12 21:26:56'),
(157, 32, 'peedm25s306gnq5rngfjm39tt4', 'client_dashboard_view', 'page', 0, '[]', '2026-04-13 17:05:23'),
(158, 32, 'peedm25s306gnq5rngfjm39tt4', 'client_dashboard_view', 'page', 0, '[]', '2026-04-13 17:05:31'),
(159, 32, 'gm6dub1up1jiv6unaf84q07431', 'client_dashboard_view', 'page', 0, '[]', '2026-04-13 17:22:12'),
(160, 32, '65f9oiue9htmo3agh7p5u41t9f', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 16:50:00'),
(161, 32, '65f9oiue9htmo3agh7p5u41t9f', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 16:50:05'),
(162, 32, '65f9oiue9htmo3agh7p5u41t9f', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 16:51:03'),
(163, 32, '65f9oiue9htmo3agh7p5u41t9f', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 16:51:13'),
(164, 32, '65f9oiue9htmo3agh7p5u41t9f', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 16:56:40'),
(165, 32, '3qu5et7nl88dacebdkttj5ddvo', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 18:00:01'),
(166, 32, '3qu5et7nl88dacebdkttj5ddvo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 18:26:57'),
(167, 32, '3qu5et7nl88dacebdkttj5ddvo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 18:27:00'),
(168, 32, '3qu5et7nl88dacebdkttj5ddvo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 18:27:02'),
(169, 32, '3qu5et7nl88dacebdkttj5ddvo', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 18:36:52'),
(170, 32, '8fgq2ategbp4cokr0vv0s6shvm', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 20:40:34'),
(171, 32, '8fgq2ategbp4cokr0vv0s6shvm', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-15 20:40:40'),
(172, 32, 'fd3vbnal20u239voga0sqrecun', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 22:11:56'),
(173, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'client_dashboard_view', 'page', 0, '[]', '2026-04-15 22:53:11'),
(174, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 15:49:17'),
(175, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 15:50:17'),
(176, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-16 15:50:39'),
(177, 26, 'mt5elocjvncva9tbeua6vavsns', 'send_message', 'message', 38, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 15:52:17'),
(178, 26, 'sgrmem3vq0o0c5ni4ge4a9qhcj', 'send_message', 'message', 39, '{\"sender_id\":26,\"receiver_id\":31,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 16:02:51'),
(179, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 16:04:40'),
(180, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 16:05:27'),
(181, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'send_message', 'message', 40, '{\"sender_id\":32,\"receiver_id\":26,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 16:06:33'),
(182, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'message_read', 'user', 26, '{\"sender_id\":26,\"receiver_id\":32,\"messages_read_count\":1}', '2026-04-16 16:06:34'),
(183, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 16:13:08'),
(184, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 16:16:19'),
(185, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'send_message', 'message', 41, '{\"sender_id\":32,\"receiver_id\":26,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 16:17:12'),
(186, 26, 'tvruj65un126san2ju1b7m977e', 'send_message', 'message', 42, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 16:18:13'),
(187, 32, '4kbhjpbnlnedh06t08p0mavle6', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 16:18:55'),
(188, 32, '4kbhjpbnlnedh06t08p0mavle6', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 17:04:23'),
(189, 32, '4kbhjpbnlnedh06t08p0mavle6', 'providers_page_view', 'page', 0, '{\"filters\":{\"search\":\"\",\"category\":\"\",\"location\":\"\",\"sort\":\"ml\",\"avail\":\"\"}}', '2026-04-16 17:04:28'),
(190, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 17:57:13'),
(191, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 18:00:51'),
(192, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'send_message', 'message', 43, '{\"sender_id\":32,\"receiver_id\":26,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 18:02:27'),
(193, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'message_read', 'user', 26, '{\"sender_id\":26,\"receiver_id\":32,\"messages_read_count\":1}', '2026-04-16 18:02:28'),
(194, 26, '0svipsu63ag7g7511qop1qtrrq', 'send_message', 'message', 44, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 18:04:03'),
(195, 26, '0svipsu63ag7g7511qop1qtrrq', 'send_message', 'message', 45, '{\"sender_id\":26,\"receiver_id\":32,\"message_type\":\"text\",\"has_attachment\":false}', '2026-04-16 18:04:07'),
(196, 32, 'tbd1t4a2v01dlj9rlroobljgta', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 18:04:39'),
(197, 32, 'tbd1t4a2v01dlj9rlroobljgta', 'client_dashboard_view', 'page', 0, '[]', '2026-04-16 18:44:28');

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
(5, 31, 16, '2025-12-11 19:33:20'),
(13, 36, 16, '2026-03-30 22:52:12'),
(14, 32, 16, '2026-04-01 09:42:41'),
(16, 32, 15, '2026-04-01 14:32:49');

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
-- Table structure for table `live_locations`
--

CREATE TABLE `live_locations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
-- Table structure for table `location_history`
--

CREATE TABLE `location_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(29, 32, 26, '{&amp;quot;latitude&amp;quot;:-2.0099,&amp;quot;longitude&amp;quot;:30.07,&amp;quot;label&amp;quot;:&amp;quot;Live location shared&amp;quot;}', 1, '2026-03-29 17:08:29', NULL, NULL, NULL, NULL, NULL, NULL),
(30, 26, 32, '{&amp;quot;latitude&amp;quot;:-2.0099,&amp;quot;longitude&amp;quot;:30.07,&amp;quot;label&amp;quot;:&amp;quot;Live location shared&amp;quot;}', 1, '2026-03-29 17:20:30', NULL, NULL, NULL, NULL, NULL, NULL),
(31, 26, 32, '{&amp;quot;latitude&amp;quot;:-1.944,&amp;quot;longitude&amp;quot;:30.062,&amp;quot;label&amp;quot;:&amp;quot;Live location shared&amp;quot;}', 1, '2026-03-30 09:27:49', NULL, NULL, NULL, NULL, NULL, NULL),
(32, 26, 32, '{&quot;service_name&quot;:&quot;Personal Driver (Daily Transport)&quot;,&quot;description&quot;:&quot;Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.&quot;,&quot;price&quot;:&quot;20000.00&quot;,&quot;service_id&quot;:47}', 1, '2026-03-30 10:25:00', NULL, 'service', NULL, NULL, NULL, NULL),
(33, 26, 32, '{&amp;quot;latitude&amp;quot;:-1.944,&amp;quot;longitude&amp;quot;:30.062,&amp;quot;label&amp;quot;:&amp;quot;Live location shared&amp;quot;}', 1, '2026-03-30 10:40:26', NULL, NULL, NULL, NULL, NULL, NULL),
(35, 32, 26, '', 1, '2026-04-01 14:40:05', NULL, 'audio', 'uploads/chat/voice_69cd2e45b46639.78493839.webm', 182870, 12, NULL),
(36, 26, 32, '{&quot;service_name&quot;:&quot;Personal Driver (Daily Transport)&quot;,&quot;description&quot;:&quot;Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.&quot;,&quot;price&quot;:&quot;20000.00&quot;,&quot;service_id&quot;:47}', 1, '2026-04-01 14:41:44', NULL, 'service', NULL, NULL, NULL, NULL),
(37, 32, 26, 'New booking created: #BK-2026-00040', 1, '2026-04-04 00:13:38', NULL, NULL, NULL, NULL, NULL, NULL),
(38, 26, 32, 'Booking #40 status changed to Confirmed', 1, '2026-04-16 15:52:17', NULL, NULL, NULL, NULL, NULL, NULL),
(39, 26, 31, 'Booking #39 status changed to Confirmed', 0, '2026-04-16 16:02:51', NULL, NULL, NULL, NULL, NULL, NULL),
(40, 32, 26, 'New booking created: #BK-2026-00041', 0, '2026-04-16 16:06:33', NULL, NULL, NULL, NULL, NULL, NULL),
(41, 32, 26, 'New booking created: #BK-2026-00042', 0, '2026-04-16 16:17:12', NULL, NULL, NULL, NULL, NULL, NULL),
(42, 26, 32, 'Booking #42 status changed to Confirmed', 1, '2026-04-16 16:18:13', NULL, NULL, NULL, NULL, NULL, NULL),
(43, 32, 26, 'New booking created: #BK-2026-00043', 0, '2026-04-16 18:02:27', NULL, NULL, NULL, NULL, NULL, NULL),
(44, 26, 32, 'Booking #43 status changed to Confirmed', 0, '2026-04-16 18:04:03', NULL, NULL, NULL, NULL, NULL, NULL),
(45, 26, 32, 'Booking #43 status changed to Confirmed', 0, '2026-04-16 18:04:07', NULL, NULL, NULL, NULL, NULL, NULL);

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
-- Table structure for table `ml_predictions_log`
--

CREATE TABLE `ml_predictions_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) NOT NULL,
  `predicted_score` decimal(8,6) NOT NULL,
  `actual_outcome` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Table structure for table `nlu_booking_classifications`
--

CREATE TABLE `nlu_booking_classifications` (
  `id` bigint(20) NOT NULL,
  `description` text NOT NULL,
  `service_category` varchar(100) NOT NULL,
  `confidence` float NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `booking_id` bigint(20) DEFAULT NULL,
  `was_correct` tinyint(1) DEFAULT NULL,
  `was_correct_timestamp` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nlu_classifications`
--

CREATE TABLE `nlu_classifications` (
  `id` bigint(20) NOT NULL,
  `query` text NOT NULL,
  `service_category` varchar(100) NOT NULL,
  `confidence` float NOT NULL,
  `language` varchar(10) DEFAULT 'en',
  `user_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `was_helpful` tinyint(1) DEFAULT NULL,
  `was_helpful_timestamp` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nlu_user_feedback`
--

CREATE TABLE `nlu_user_feedback` (
  `id` bigint(20) NOT NULL,
  `classification_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `original_text` text DEFAULT NULL,
  `predicted_service` varchar(100) DEFAULT NULL,
  `corrected_service` varchar(100) DEFAULT NULL,
  `feedback_type` enum('correct','incorrect','ambiguous') DEFAULT 'incorrect',
  `feedback_text` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(46, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 17, '2026-03-27 15:38:05', '2026-03-27 15:38:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(47, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-27 15:59:44', '2026-03-27 15:59:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(48, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/dashboard.php', 13, '2026-03-27 16:04:42', '2026-03-27 16:04:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(49, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 202, '2026-03-27 16:05:12', '2026-03-27 16:08:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(50, NULL, 'bmek2sg17cgh4spu8fiphsnjnp', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 0, '2026-03-27 17:27:29', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(51, NULL, 'hrhvguihh25na5es79k13h8m8u', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 0, '2026-03-27 17:27:29', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(52, NULL, '1lfvrlp6tfuig4m11ck9lsl1jj', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 5, '2026-03-27 17:29:53', '2026-03-27 17:29:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(53, NULL, '1lfvrlp6tfuig4m11ck9lsl1jj', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 3, '2026-03-27 17:30:07', '2026-03-27 17:30:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(54, 32, 'btnlq8grs8slathsa8e5ks2npu', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-27 20:28:17', '2026-03-27 20:28:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(55, 31, '5uvb7hia0n1kfn1nmmfg8gqs0b', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-03-27 20:32:19', '2026-03-27 20:32:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(56, 31, '5uvb7hia0n1kfn1nmmfg8gqs0b', 'http://localhost/bii_localfinder/client/dashboard.php', 10, '2026-03-27 20:32:36', '2026-03-27 20:32:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(57, 36, 'p0bd2d5720jvd957tr82rrr454', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-03-29 22:33:45', '2026-03-29 22:33:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(58, 32, 'p2ln2p2t74hjah8f5avaqaip98', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-29 22:42:53', '2026-03-29 22:42:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(59, 32, 'p2ln2p2t74hjah8f5avaqaip98', 'http://localhost/bii_localfinder/client/dashboard.php', 14, '2026-03-29 22:56:39', '2026-03-29 22:56:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(60, 26, 'tu4o484g3jr96n61430p31ulu0', 'http://localhost/bii_localfinder/provider/dashboard.php', 5, '2026-03-29 23:07:36', '2026-03-29 23:07:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(61, 32, 'llkdkvu9nfsm89l0sdv5cc9lel', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-03-29 23:12:28', '2026-03-29 23:12:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(62, 26, '0h2l2chrhocadvkns8jjk8pric', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-03-29 23:20:39', '2026-03-29 23:20:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(63, 32, 'fsnc2h6k9s527fa03fo3s6imss', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-29 23:49:07', '2026-03-29 23:49:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(64, 32, 'fsnc2h6k9s527fa03fo3s6imss', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-29 23:54:43', '2026-03-29 23:54:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(65, 26, '5qou2scvj29aa2ptsgflqhrqo5', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-03-29 23:55:19', '2026-03-29 23:55:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(66, 32, 'f19a115c76sobv08p923susvhk', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-29 23:55:48', '2026-03-29 23:55:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(67, 26, 'i7u6v9cfbgk9pcf885eclrfagu', 'http://localhost/bii_localfinder/provider/dashboard.php', 5, '2026-03-30 00:08:47', '2026-03-30 00:08:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(68, 26, 'tc4pffc1p0bcq0smrg4isp6gir', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-03-30 00:20:54', '2026-03-30 00:20:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(69, 26, 'tc4pffc1p0bcq0smrg4isp6gir', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-03-30 01:00:01', '2026-03-30 01:00:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(70, 32, 'f373nmt3t2j2ffe6tnuaaddra6', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-30 14:46:02', '2026-03-30 14:46:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(71, 26, 'fthopo3t4j9t07d97t70lrfgro', 'http://localhost/bii_localfinder/provider/dashboard.php', 13, '2026-03-30 16:05:55', '2026-03-30 16:06:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(72, 26, 'fthopo3t4j9t07d97t70lrfgro', 'http://localhost/bii_localfinder/provider/dashboard.php', 30, '2026-03-30 16:17:31', '2026-03-30 16:18:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(73, 26, 'fthopo3t4j9t07d97t70lrfgro', 'http://localhost/bii_localfinder/provider/dashboard.php', 31, '2026-03-30 16:18:31', '2026-03-30 16:19:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(74, 26, 'fthopo3t4j9t07d97t70lrfgro', 'http://localhost/bii_localfinder/provider/dashboard.php', 0, '2026-03-30 16:19:24', '2026-03-30 16:19:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(75, 26, 'fthopo3t4j9t07d97t70lrfgro', 'http://localhost/bii_localfinder/provider/dashboard.php', 20, '2026-03-30 16:24:35', '2026-03-30 16:24:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(76, 26, 'fthopo3t4j9t07d97t70lrfgro', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-03-30 16:25:11', '2026-03-30 16:25:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(77, 32, 'l3f99n8edgs58ijnrjs6qprkr4', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-30 16:28:59', '2026-03-30 16:29:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(78, 32, 'l3f99n8edgs58ijnrjs6qprkr4', 'http://localhost/bii_localfinder/client/dashboard.php', 15, '2026-03-30 17:05:11', '2026-03-30 17:05:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(79, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-03-30 17:05:53', '2026-03-30 17:05:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(80, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 21, '2026-03-30 17:06:12', '2026-03-30 17:06:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(81, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 10, '2026-03-30 17:13:57', '2026-03-30 17:14:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(82, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/profile.php', 32, '2026-03-30 17:14:10', '2026-03-30 17:14:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(83, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-03-30 17:19:33', '2026-03-30 17:19:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(84, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-03-30 17:39:22', '2026-03-30 17:39:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(85, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-03-30 17:39:31', '2026-03-30 17:39:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(86, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-03-30 17:40:00', '2026-03-30 17:40:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(87, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-31 03:54:10', '2026-03-31 03:54:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(88, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/favorites.php', 3, '2026-03-31 03:59:30', '2026-03-31 03:59:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(89, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/favorites.php', 2, '2026-03-31 03:59:44', '2026-03-31 03:59:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(90, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-31 04:03:57', '2026-03-31 04:03:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(91, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-31 04:05:21', '2026-03-31 04:05:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(92, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-31 04:05:25', '2026-03-31 04:05:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(93, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-31 04:05:36', '2026-03-31 04:05:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(94, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-03-31 04:06:39', '2026-03-31 04:06:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(95, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 5, '2026-03-31 04:35:13', '2026-03-31 04:35:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(96, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php', 10, '2026-03-31 04:35:18', '2026-03-31 04:35:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(97, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php', 29, '2026-03-31 04:35:34', '2026-03-31 04:36:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(98, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-03-31 04:40:04', '2026-03-31 04:40:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(99, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php', 47, '2026-03-31 04:52:23', '2026-03-31 04:53:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(100, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/favorites.php', 4, '2026-03-31 04:53:10', '2026-03-31 04:53:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(101, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-31 04:53:16', '2026-03-31 04:53:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(102, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php', 14, '2026-03-31 04:53:21', '2026-03-31 04:53:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(103, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-31 04:53:36', '2026-03-31 04:53:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(104, 32, 'deuqpu4if92v105383qr0lbdkb', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-03-31 04:53:41', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(105, NULL, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 8, '2026-03-31 04:54:48', '2026-03-31 04:54:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(106, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-03-31 04:55:22', '2026-03-31 04:55:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(107, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 8, '2026-03-31 04:55:26', '2026-03-31 04:55:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(108, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=rating', 11, '2026-03-31 04:55:35', '2026-03-31 04:55:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(109, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=newest', 5, '2026-03-31 04:55:47', '2026-03-31 04:55:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(110, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=price_desc', 10, '2026-03-31 04:55:52', '2026-03-31 04:56:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(111, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 3, '2026-03-31 04:56:06', '2026-03-31 04:56:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(112, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-03-31 04:57:48', '2026-03-31 04:57:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(113, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-03-31 05:00:13', '2026-03-31 05:00:14', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(114, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 22, '2026-03-31 05:00:17', '2026-03-31 05:00:39', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(115, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 3, '2026-03-31 05:00:46', '2026-03-31 05:00:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(116, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 7, '2026-03-31 05:00:54', '2026-03-31 05:01:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(117, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-03-31 05:01:25', '2026-03-31 05:01:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(118, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 61, '2026-03-31 05:01:29', '2026-03-31 05:02:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(119, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-03-31 05:02:31', '2026-03-31 05:02:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(120, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-03-31 05:02:46', '2026-03-31 05:02:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(121, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-03-31 05:03:40', '2026-03-31 05:03:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(122, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-03-31 05:04:25', '2026-03-31 05:04:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(123, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 44, '2026-03-31 05:05:28', '2026-03-31 05:06:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(124, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-03-31 05:06:29', '2026-03-31 05:06:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(125, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-03-31 05:06:30', '2026-03-31 05:06:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(126, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-03-31 05:06:38', '2026-03-31 05:06:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(127, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-03-31 05:07:51', '2026-03-31 05:07:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(128, 32, '6am9smbio683o1jagofv1g3leq', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-03-31 05:22:51', '2026-03-31 05:23:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(129, 26, 'ec0u28fn9dgcb9dgqn12610kht', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-03-31 05:24:49', '2026-03-31 05:24:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(130, 26, 'ec0u28fn9dgcb9dgqn12610kht', 'http://localhost/bii_localfinder/provider/dashboard.php', 18, '2026-03-31 05:25:24', '2026-03-31 05:25:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(131, 26, 'ec0u28fn9dgcb9dgqn12610kht', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-03-31 05:49:52', '2026-03-31 05:49:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(132, 36, '5r5op3l1h59nntenhv12r1ug27', 'http://localhost/bii_localfinder/client/providers.php', 27, '2026-03-31 05:51:21', '2026-03-31 05:51:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(133, 36, '5r5op3l1h59nntenhv12r1ug27', 'http://localhost/bii_localfinder/client/providers.php', 17, '2026-03-31 05:51:56', '2026-03-31 05:52:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(134, 36, '5r5op3l1h59nntenhv12r1ug27', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-03-31 05:52:18', '2026-03-31 05:52:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(135, 36, '5r5op3l1h59nntenhv12r1ug27', 'http://localhost/bii_localfinder/client/booking.php?provider_id=16', 13, '2026-03-31 05:52:31', '2026-03-31 05:52:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(136, 36, '5r5op3l1h59nntenhv12r1ug27', 'http://localhost/bii_localfinder/client/providers.php', 31, '2026-03-31 05:52:49', '2026-03-31 05:53:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(137, 26, 'kef9dtp6r31kosap85lq2i98hs', 'http://localhost/bii_localfinder/provider/dashboard.php', 19, '2026-04-01 03:26:49', '2026-04-01 03:27:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(138, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 24, '2026-04-01 16:42:30', '2026-04-01 16:42:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(139, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 5, '2026-04-01 16:43:02', '2026-04-01 16:43:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(140, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 13, '2026-04-01 16:43:16', '2026-04-01 16:43:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(141, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-01 16:56:53', '2026-04-01 16:56:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(142, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 24, '2026-04-01 16:58:32', '2026-04-01 16:58:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(143, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-01 16:59:09', '2026-04-01 16:59:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(144, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-01 16:59:16', '2026-04-01 16:59:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(145, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-01 16:59:36', '2026-04-01 16:59:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(146, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-01 17:02:39', '2026-04-01 17:02:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(147, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 5, '2026-04-01 17:02:53', '2026-04-01 17:02:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(148, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 28, '2026-04-01 17:03:04', '2026-04-01 17:03:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(149, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-01 17:03:36', '2026-04-01 17:03:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(150, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 75, '2026-04-01 17:06:23', '2026-04-01 17:07:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(151, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-01 17:07:44', '2026-04-01 17:07:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(152, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-01 17:08:05', '2026-04-01 17:08:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(153, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'http://localhost/bii_localfinder/client/providers.php', 10, '2026-04-01 17:11:16', '2026-04-01 17:11:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(154, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 19, '2026-04-01 17:58:24', '2026-04-01 17:58:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(155, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 20, '2026-04-01 17:58:43', '2026-04-01 17:59:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(156, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 5, '2026-04-01 17:59:21', '2026-04-01 17:59:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(157, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-01 17:59:27', '2026-04-01 17:59:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(158, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 2, '2026-04-01 17:59:30', '2026-04-01 17:59:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(159, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 12, '2026-04-01 17:59:35', '2026-04-01 17:59:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(160, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 54, '2026-04-01 18:00:51', '2026-04-01 18:01:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(161, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-01 18:01:46', '2026-04-01 18:01:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(162, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-01 18:01:47', '2026-04-01 18:01:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(163, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-01 18:01:48', '2026-04-01 18:01:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(164, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-01 18:01:48', '2026-04-01 18:01:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(165, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-01 18:01:49', '2026-04-01 18:01:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(166, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-01 18:03:37', '2026-04-01 18:03:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(167, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 6, '2026-04-01 18:03:39', '2026-04-01 18:03:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(168, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-01 18:07:08', '2026-04-01 18:07:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(169, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 4, '2026-04-01 18:07:10', '2026-04-01 18:07:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(170, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 36, '2026-04-01 18:07:20', '2026-04-01 18:07:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(171, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 6, '2026-04-01 18:08:09', '2026-04-01 18:08:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(172, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 65, '2026-04-01 18:09:22', '2026-04-01 18:10:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(173, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-01 18:10:47', '2026-04-01 18:10:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(174, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/providers.php', 30, '2026-04-01 18:17:14', '2026-04-01 18:17:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(175, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-01 18:17:49', '2026-04-01 18:17:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(176, 32, 'og0tq3ltp69i2uva350kopglds', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-01 18:20:03', '2026-04-01 18:20:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(177, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-01 21:28:58', '2026-04-01 21:29:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(178, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 99, '2026-04-01 21:30:18', '2026-04-01 21:31:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(179, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/booking.php?provider_id=15', 8, '2026-04-01 21:32:01', '2026-04-01 21:32:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(180, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 38, '2026-04-01 21:32:15', '2026-04-01 21:32:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(181, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 63, '2026-04-01 21:32:57', '2026-04-01 21:34:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(182, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-01 21:37:35', '2026-04-01 21:37:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(183, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-01 21:37:50', '2026-04-01 21:37:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(184, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'http://localhost/bii_localfinder/client/providers.php', 19, '2026-04-01 21:39:06', '2026-04-01 21:39:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `page_sessions` (`id`, `user_id`, `session_id`, `page_url`, `time_spent_seconds`, `start_time`, `end_time`, `ip_address`, `user_agent`) VALUES
(185, 26, 'l2smae5oao5rmve5o4o1ip1osn', 'http://localhost/bii_localfinder/provider/dashboard.php', 10, '2026-04-01 21:40:52', '2026-04-01 21:41:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(186, 32, 'eooaiij09kobtmcev2cu85bsjp', 'http://localhost/bii_localfinder/client/providers.php', 21, '2026-04-01 21:42:38', '2026-04-01 21:43:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(187, 26, '0sjn7qeue927jn2rj8pektpdgh', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-01 21:43:36', '2026-04-01 21:43:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(188, 26, '0sjn7qeue927jn2rj8pektpdgh', 'http://localhost/bii_localfinder/provider/dashboard.php', 10, '2026-04-01 21:44:02', '2026-04-01 21:44:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(189, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php', 23, '2026-04-02 03:30:46', '2026-04-02 03:31:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(190, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 8, '2026-04-02 03:31:09', '2026-04-02 03:31:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(191, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 4, '2026-04-02 03:31:22', '2026-04-02 03:31:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(192, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 14, '2026-04-02 03:33:03', '2026-04-02 03:33:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(193, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-04-02 03:33:47', '2026-04-02 03:33:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(194, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-04-02 03:33:51', '2026-04-02 03:33:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(195, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 18, '2026-04-02 03:34:36', '2026-04-02 03:34:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(196, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 4, '2026-04-02 03:42:52', '2026-04-02 03:42:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(197, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 20, '2026-04-02 03:42:56', '2026-04-02 03:43:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(198, 32, 'v5pv03vck8rmfegi4irl0fu505', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-04-02 03:44:53', '2026-04-02 03:44:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(199, NULL, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-04-02 03:58:54', '2026-04-02 03:58:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(200, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-02 03:59:14', '2026-04-02 03:59:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(201, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 23, '2026-04-02 03:59:35', '2026-04-02 03:59:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(202, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:00:18', '2026-04-02 04:00:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(203, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 38, '2026-04-02 04:00:22', '2026-04-02 04:01:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(204, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 8, '2026-04-02 04:01:22', '2026-04-02 04:01:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(205, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:04:55', '2026-04-02 04:04:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(206, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-02 04:05:25', '2026-04-02 04:05:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(207, 32, '068v7mf0tmt3qick57e5b12bmo', 'http://localhost/bii_localfinder/client/providers.php', 20, '2026-04-02 04:05:34', '2026-04-02 04:05:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(208, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 22, '2026-04-02 04:09:26', '2026-04-02 04:09:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(209, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 14, '2026-04-02 04:09:51', '2026-04-02 04:10:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(210, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 20, '2026-04-02 04:10:09', '2026-04-02 04:10:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(211, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 4, '2026-04-02 04:10:32', '2026-04-02 04:10:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(212, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 23, '2026-04-02 04:22:06', '2026-04-02 04:22:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(213, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-04-02 04:24:57', '2026-04-02 04:25:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(214, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 12, '2026-04-02 04:25:03', '2026-04-02 04:25:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(215, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-04-02 04:26:28', '2026-04-02 04:26:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(216, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 24, '2026-04-02 04:26:34', '2026-04-02 04:26:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(217, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-04-02 04:27:43', '2026-04-02 04:27:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(218, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 5, '2026-04-02 04:27:51', '2026-04-02 04:27:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(219, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-02 04:28:01', '2026-04-02 04:28:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(220, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:31:51', '2026-04-02 04:31:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(221, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 37, '2026-04-02 04:31:58', '2026-04-02 04:32:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(222, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:32:48', '2026-04-02 04:32:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(223, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-02 04:34:25', '2026-04-02 04:34:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(224, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-02 04:34:31', '2026-04-02 04:34:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(225, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:36:11', '2026-04-02 04:36:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(226, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-02 04:36:16', '2026-04-02 04:36:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(227, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-02 04:37:51', '2026-04-02 04:37:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(228, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-02 04:37:59', '2026-04-02 04:38:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(229, 32, 'rfebqhsns022kic0k2guolsh6l', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:40:49', '2026-04-02 04:40:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(230, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-02 04:42:13', '2026-04-02 04:42:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(231, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 13, '2026-04-02 04:42:22', '2026-04-02 04:42:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(232, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:36', '2026-04-02 04:43:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(233, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:37', '2026-04-02 04:43:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(234, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:38', '2026-04-02 04:43:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(235, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:39', '2026-04-02 04:43:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(236, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:42', '2026-04-02 04:43:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(237, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:43', '2026-04-02 04:43:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(238, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:43', '2026-04-02 04:43:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(239, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:43:47', '2026-04-02 04:43:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(240, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:48', '2026-04-02 04:43:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(241, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:48', '2026-04-02 04:43:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(242, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:43:50', '2026-04-02 04:43:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(243, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-02 04:43:51', '2026-04-02 04:43:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(244, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:05', '2026-04-02 04:44:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(245, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:05', '2026-04-02 04:44:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(246, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:44:06', '2026-04-02 04:44:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(247, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:10', '2026-04-02 04:44:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(248, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:10', '2026-04-02 04:44:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(249, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:12', '2026-04-02 04:44:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(250, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:14', '2026-04-02 04:44:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(251, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:15', '2026-04-02 04:44:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(252, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-02 04:44:15', '2026-04-02 04:44:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(253, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 15, '2026-04-02 04:44:32', '2026-04-02 04:44:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(254, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:45:29', '2026-04-02 04:45:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(255, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:46:19', '2026-04-02 04:46:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(256, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:46:35', '2026-04-02 04:46:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(257, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:49:07', '2026-04-02 04:49:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(258, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 04:49:17', '2026-04-02 04:49:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(259, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:51:01', '2026-04-02 04:51:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(260, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 26, '2026-04-02 04:51:04', '2026-04-02 04:51:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(261, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 69, '2026-04-02 04:51:37', '2026-04-02 04:52:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(262, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-02 04:54:19', '2026-04-02 04:54:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(263, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:58:36', '2026-04-02 04:58:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(264, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-02 04:58:40', '2026-04-02 04:58:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(265, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 04:59:10', '2026-04-02 04:59:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(266, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 12, '2026-04-02 04:59:14', '2026-04-02 04:59:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(267, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 20, '2026-04-02 04:59:36', '2026-04-02 04:59:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(268, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 05:00:55', '2026-04-02 05:00:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(269, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 12, '2026-04-02 05:01:00', '2026-04-02 05:01:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(270, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 21, '2026-04-02 05:01:42', '2026-04-02 05:02:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(271, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 13, '2026-04-02 05:02:12', '2026-04-02 05:02:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(272, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-02 05:03:02', '2026-04-02 05:03:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(273, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-02 05:03:11', '2026-04-02 05:03:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(274, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-02 05:03:48', '2026-04-02 05:03:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(275, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-02 05:03:55', '2026-04-02 05:04:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(276, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 05:04:09', '2026-04-02 05:04:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(277, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-02 05:04:14', '2026-04-02 05:04:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(278, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 05:04:42', '2026-04-02 05:04:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(279, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 27, '2026-04-02 05:04:47', '2026-04-02 05:05:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(280, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-02 05:06:57', '2026-04-02 05:06:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(281, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 05:08:54', '2026-04-02 05:08:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(282, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 8, '2026-04-02 05:08:59', '2026-04-02 05:09:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(283, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-02 05:09:42', '2026-04-02 05:09:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(284, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 15, '2026-04-02 05:09:49', '2026-04-02 05:10:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(285, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-02 05:10:55', '2026-04-02 05:10:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(286, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 103, '2026-04-02 05:11:00', '2026-04-02 05:12:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(287, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 66, '2026-04-02 05:12:59', '2026-04-02 05:14:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(288, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-02 05:14:23', '2026-04-02 05:14:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(289, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-02 05:18:23', '2026-04-02 05:18:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(290, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 61, '2026-04-02 05:22:14', '2026-04-02 05:23:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(291, 32, 'qs2u070r6gadetjrfq4ibquch0', 'http://localhost/bii_localfinder/client/providers.php', 10, '2026-04-02 05:23:32', '2026-04-02 05:23:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(292, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'http://localhost/bii_localfinder/client/providers.php', 150, '2026-04-03 15:21:29', '2026-04-03 15:24:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(293, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-03 15:24:16', '2026-04-03 15:24:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(294, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-03 15:24:53', '2026-04-03 15:24:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(295, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-03 15:25:50', '2026-04-03 15:25:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(296, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-03 15:26:03', '2026-04-03 15:26:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(297, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 1, '2026-04-03 15:26:48', '2026-04-03 15:26:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(298, NULL, 'ebja1i9886b9a52bka9grcojon', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 3, '2026-04-03 21:09:01', '2026-04-03 21:09:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(299, 32, 'ebja1i9886b9a52bka9grcojon', 'http://localhost/bii_localfinder/client/providers.php', 11, '2026-04-03 21:16:34', '2026-04-03 21:16:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(300, 32, 'ebja1i9886b9a52bka9grcojon', 'http://localhost/bii_localfinder/client/providers.php', 32, '2026-04-03 21:17:01', '2026-04-03 21:17:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(301, 32, 'ebja1i9886b9a52bka9grcojon', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-03 21:21:00', '2026-04-03 21:21:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(302, 32, 'ebja1i9886b9a52bka9grcojon', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-03 21:21:21', '2026-04-03 21:21:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(303, 32, 'ebja1i9886b9a52bka9grcojon', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-03 21:26:19', '2026-04-03 21:26:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(304, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 20, '2026-04-03 21:26:36', '2026-04-03 21:26:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(305, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-04-03 21:27:24', '2026-04-03 21:27:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(306, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-04-03 21:27:33', '2026-04-03 21:27:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(307, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-03 21:33:12', '2026-04-03 21:33:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(308, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-03 21:33:24', '2026-04-03 21:33:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(309, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-03 21:39:18', '2026-04-03 21:39:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(310, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-03 22:13:13', '2026-04-03 22:13:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(311, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 36, '2026-04-03 22:13:16', '2026-04-03 22:13:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(312, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 50, '2026-04-03 22:13:56', '2026-04-03 22:14:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(313, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-03 22:15:36', '2026-04-03 22:15:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(314, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-03 22:15:39', '2026-04-03 22:15:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(315, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-03 22:15:48', '2026-04-03 22:15:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(316, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-03 22:17:14', '2026-04-03 22:17:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(317, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 26, '2026-04-03 22:17:20', '2026-04-03 22:17:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(318, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 23, '2026-04-03 22:17:54', '2026-04-03 22:18:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(319, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 51, '2026-04-03 22:18:18', '2026-04-03 22:19:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(320, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-03 22:20:01', '2026-04-03 22:20:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(321, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 11, '2026-04-03 22:20:05', '2026-04-03 22:20:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(322, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-03 22:20:33', '2026-04-03 22:20:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(323, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-03 22:20:36', '2026-04-03 22:20:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(324, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-03 22:20:51', '2026-04-03 22:20:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(325, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 33, '2026-04-03 22:21:30', '2026-04-03 22:22:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(326, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 57, '2026-04-03 22:22:13', '2026-04-03 22:23:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(327, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 113, '2026-04-03 22:23:22', '2026-04-03 22:25:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(328, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 13, '2026-04-03 22:25:17', '2026-04-03 22:25:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(329, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/favorites.php', 2, '2026-04-03 22:25:31', '2026-04-03 22:25:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(330, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 18, '2026-04-03 22:25:34', '2026-04-03 22:25:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(331, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/dashboard.php', 17, '2026-04-03 22:25:53', '2026-04-03 22:26:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(332, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-03 22:26:13', '2026-04-03 22:26:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(333, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-03 22:26:24', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(334, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 29, '2026-04-03 22:27:09', '2026-04-03 22:27:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(335, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-04-03 22:27:43', '2026-04-03 22:27:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(336, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 19, '2026-04-03 22:28:12', '2026-04-03 22:28:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(337, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 26, '2026-04-03 22:29:18', '2026-04-03 22:29:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(338, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 25, '2026-04-03 23:22:11', '2026-04-03 23:22:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(339, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-03 23:25:26', '2026-04-03 23:25:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(340, 32, '9do680060n5vl4131prcglr2iu', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-04-03 23:26:50', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(341, NULL, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-03 23:38:11', '2026-04-03 23:38:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(342, NULL, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-03 23:38:21', '2026-04-03 23:38:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(343, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 23, '2026-04-03 23:39:03', '2026-04-03 23:39:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(344, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-03 23:39:31', '2026-04-03 23:39:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(345, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-03 23:39:38', '2026-04-03 23:39:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(346, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 24, '2026-04-03 23:49:57', '2026-04-03 23:50:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(347, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-03 23:50:27', '2026-04-03 23:50:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(348, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-03 23:51:32', '2026-04-03 23:51:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(349, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-04-03 23:51:38', '2026-04-03 23:51:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(350, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-03 23:56:09', '2026-04-03 23:56:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(351, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-04 00:03:40', '2026-04-04 00:03:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(352, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 20, '2026-04-04 00:03:48', '2026-04-04 00:04:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(353, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 43, '2026-04-04 00:10:06', '2026-04-04 00:10:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(354, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-04 00:11:10', '2026-04-04 00:11:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(355, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-04 00:12:59', '2026-04-04 00:13:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(356, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-04-04 00:13:30', '2026-04-04 00:13:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(357, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-04 00:13:40', '2026-04-04 00:13:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(358, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-04-04 00:14:13', '2026-04-04 00:14:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(359, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 22, '2026-04-04 00:14:39', '2026-04-04 00:15:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(360, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-04 00:16:16', '2026-04-04 00:16:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(361, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 10, '2026-04-04 00:16:25', '2026-04-04 00:16:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(362, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-04 00:16:50', '2026-04-04 00:16:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(363, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-04 00:17:49', '2026-04-04 00:17:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(364, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 22, '2026-04-04 00:18:05', '2026-04-04 00:18:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(365, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-04-04 00:18:32', '2026-04-04 00:18:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(366, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-04 00:18:35', '2026-04-04 00:18:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(367, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-04-04 00:19:29', '2026-04-04 00:19:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(368, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-04 00:20:48', '2026-04-04 00:20:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(369, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-04 00:21:00', '2026-04-04 00:21:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `page_sessions` (`id`, `user_id`, `session_id`, `page_url`, `time_spent_seconds`, `start_time`, `end_time`, `ip_address`, `user_agent`) VALUES
(370, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 00:21:08', '2026-04-04 00:21:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(371, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-04 00:21:17', '2026-04-04 00:21:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(372, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 00:21:27', '2026-04-04 00:21:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(373, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 16, '2026-04-04 00:21:31', '2026-04-04 00:21:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(374, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-04 00:22:00', '2026-04-04 00:22:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(375, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 5, '2026-04-04 00:22:54', '2026-04-04 00:22:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(376, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 5, '2026-04-04 00:23:02', '2026-04-04 00:23:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(377, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-04-04 00:23:11', '2026-04-04 00:23:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(378, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 32, '2026-04-04 00:23:43', '2026-04-04 00:24:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(379, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 19, '2026-04-04 00:24:20', '2026-04-04 00:24:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(380, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 12, '2026-04-04 00:26:06', '2026-04-04 00:26:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(381, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-04 00:26:27', '2026-04-04 00:26:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(382, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 11, '2026-04-04 00:26:40', '2026-04-04 00:26:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(383, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 36, '2026-04-04 00:26:53', '2026-04-04 00:27:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(384, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'http://localhost/bii_localfinder/provider/dashboard.php', 7, '2026-04-04 00:27:42', '2026-04-04 00:27:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(385, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-04 00:28:25', '2026-04-04 00:28:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(386, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 12, '2026-04-04 00:28:37', '2026-04-04 00:28:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(387, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php', 26, '2026-04-04 00:28:56', '2026-04-04 00:29:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(388, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 49, '2026-04-04 00:29:27', '2026-04-04 00:30:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(389, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php', 13, '2026-04-04 00:30:20', '2026-04-04 00:30:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(390, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-04 00:30:34', '2026-04-04 00:30:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(391, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 20, '2026-04-04 00:32:56', '2026-04-04 00:33:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(392, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 13, '2026-04-04 00:33:18', '2026-04-04 00:33:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(393, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-04 00:33:31', '2026-04-04 00:33:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(394, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php', 19, '2026-04-04 00:33:36', '2026-04-04 00:33:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(395, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php', 14, '2026-04-04 00:34:12', '2026-04-04 00:34:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(396, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-04 00:34:29', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(397, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 6, '2026-04-04 00:34:29', '2026-04-04 00:34:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(398, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 0, '2026-04-04 00:34:39', '2026-04-04 00:34:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(399, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 2, '2026-04-04 00:34:42', '2026-04-04 00:34:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(400, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 1, '2026-04-04 00:35:32', '2026-04-04 00:35:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(401, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 3, '2026-04-04 00:35:35', '2026-04-04 00:35:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(402, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-04 00:36:08', '2026-04-04 00:36:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(403, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-04 00:36:25', '2026-04-04 00:36:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(404, 32, '2k354vrl37vnnem4uk8e0u4tua', 'http://localhost/bii_localfinder/client/dashboard.php', 10, '2026-04-04 00:57:36', '2026-04-04 00:57:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(405, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 7, '2026-04-04 00:58:05', '2026-04-04 00:58:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(406, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 0, '2026-04-04 01:00:53', '2026-04-04 01:00:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(407, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-04 01:40:11', '2026-04-04 01:40:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(408, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-04 01:40:23', '2026-04-04 01:40:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(409, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 01:40:35', '2026-04-04 01:40:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(410, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-04-04 01:40:40', '2026-04-04 01:40:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(411, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-04 01:40:47', '2026-04-04 01:40:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(412, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 01:45:16', '2026-04-04 01:45:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(413, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 11, '2026-04-04 01:45:18', '2026-04-04 01:45:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(414, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-04-04 01:46:57', '2026-04-04 01:47:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(415, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 01:48:23', '2026-04-04 01:48:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(416, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 7, '2026-04-04 01:48:35', '2026-04-04 01:48:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(417, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-04 01:49:04', '2026-04-04 01:49:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(418, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 7, '2026-04-04 01:49:07', '2026-04-04 01:49:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(419, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 0, '2026-04-04 01:49:20', '2026-04-04 01:49:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(420, 26, '0aju6nuc67dtukfgfumijsb1e0', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 01:50:32', '2026-04-04 01:50:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(421, NULL, '4heqse9u369lvu2uq1iierug1m', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-04 01:59:29', '2026-04-04 01:59:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(422, 26, '4heqse9u369lvu2uq1iierug1m', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-04 01:59:48', '2026-04-04 01:59:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(423, 26, '4heqse9u369lvu2uq1iierug1m', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-04 02:00:05', '2026-04-04 02:00:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(424, 26, '4heqse9u369lvu2uq1iierug1m', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-04 02:01:12', '2026-04-04 02:01:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(425, 26, '4heqse9u369lvu2uq1iierug1m', 'http://localhost/bii_localfinder/provider/dashboard.php', 19, '2026-04-04 02:06:47', '2026-04-04 02:07:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(426, 26, '4heqse9u369lvu2uq1iierug1m', 'http://localhost/bii_localfinder/provider/dashboard.php', 17, '2026-04-04 02:07:07', '2026-04-04 02:07:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(427, 32, '56g41ok0ds94ggl18veotq3qvv', 'http://localhost/bii_localfinder/client/dashboard.php', 159, '2026-04-04 03:03:29', '2026-04-04 03:06:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(428, NULL, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 171, '2026-04-04 05:24:23', '2026-04-04 05:27:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(429, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 34, '2026-04-04 05:27:45', '2026-04-04 05:28:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(430, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 14, '2026-04-04 05:28:47', '2026-04-04 05:29:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(431, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-04-04 05:29:11', '2026-04-04 05:29:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(432, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-04 05:29:38', '2026-04-04 05:29:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(433, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-04 05:30:31', '2026-04-04 05:30:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(434, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 14, '2026-04-04 05:36:14', '2026-04-04 05:36:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(435, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 54, '2026-04-04 05:36:30', '2026-04-04 05:37:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(436, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-04 05:37:33', '2026-04-04 05:37:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(437, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-04 05:37:43', '2026-04-04 05:37:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(438, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-04 05:38:20', '2026-04-04 05:38:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(439, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-04 06:27:15', '2026-04-04 06:27:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(440, 32, 'bnk1eb554uat135p1n0ok4pn48', 'http://localhost/bii_localfinder/client/providers.php', 8, '2026-04-04 06:27:24', '2026-04-04 06:27:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(441, NULL, '6b6dqoupartopc26ve19dtaljm', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-04 06:29:21', '2026-04-04 06:29:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(442, NULL, '6b6dqoupartopc26ve19dtaljm', 'http://localhost/bii_localfinder/client/providers.php', 10, '2026-04-04 06:30:28', '2026-04-04 06:30:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(443, NULL, '6b6dqoupartopc26ve19dtaljm', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-04 06:49:09', '2026-04-04 06:49:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(444, NULL, '6b6dqoupartopc26ve19dtaljm', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-04 06:49:23', '2026-04-04 06:49:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(445, NULL, 'nopdk2f329sau2j0llm16vivmq', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-04 07:09:08', '2026-04-04 07:09:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(446, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-04 07:11:48', '2026-04-04 07:11:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(447, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-04 07:11:59', '2026-04-04 07:12:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(448, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 64, '2026-04-04 07:12:22', '2026-04-04 07:13:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(449, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-04 07:13:55', '2026-04-04 07:13:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(450, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-04 07:14:01', '2026-04-04 07:14:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(451, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'http://localhost/bii_localfinder/client/providers.php', 22, '2026-04-04 07:14:12', '2026-04-04 07:14:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(452, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/dashboard.php', 11, '2026-04-07 18:10:23', '2026-04-07 18:10:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(453, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-07 18:10:46', '2026-04-07 18:10:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(454, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-07 18:11:07', '2026-04-07 18:11:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(455, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-07 18:11:13', '2026-04-07 18:11:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(456, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 12, '2026-04-07 18:11:17', '2026-04-07 18:11:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(457, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/favorites.php', 6, '2026-04-07 18:13:00', '2026-04-07 18:13:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(458, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/favorites.php', 3, '2026-04-07 18:14:07', '2026-04-07 18:14:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(459, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/favorites.php', 2, '2026-04-07 18:14:17', '2026-04-07 18:14:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(460, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/dashboard.php', 11, '2026-04-07 18:14:57', '2026-04-07 18:15:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(461, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-04-07 18:15:09', '2026-04-07 18:15:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(462, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-07 18:15:19', '2026-04-07 18:15:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(463, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-07 18:15:47', '2026-04-07 18:15:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(464, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/dashboard.php', 29, '2026-04-07 18:17:21', '2026-04-07 18:17:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(465, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 10, '2026-04-07 18:19:20', '2026-04-07 18:19:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(466, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-07 18:21:22', '2026-04-07 18:21:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(467, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 29, '2026-04-07 18:21:42', '2026-04-07 18:22:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(468, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-07 18:22:22', '2026-04-07 18:22:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(469, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 13, '2026-04-07 18:22:36', '2026-04-07 18:22:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(470, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 40, '2026-04-07 18:23:42', '2026-04-07 18:24:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(471, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 58, '2026-04-07 18:25:03', '2026-04-07 18:26:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(472, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-07 18:26:08', '2026-04-07 18:26:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(473, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 13, '2026-04-07 18:26:16', '2026-04-07 18:26:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(474, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-08 01:14:42', '2026-04-08 01:14:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(475, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-08 01:14:53', '2026-04-08 01:14:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(476, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php', 37, '2026-04-08 01:19:06', '2026-04-08 01:19:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(477, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php', 13, '2026-04-08 01:19:51', '2026-04-08 01:20:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(478, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-08 01:28:31', '2026-04-08 01:28:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(479, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 4, '2026-04-08 01:28:48', '2026-04-08 01:28:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(480, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-04-08 01:29:28', '2026-04-08 01:29:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(481, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-04-08 01:46:47', '2026-04-08 01:46:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(482, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 1, '2026-04-08 01:47:01', '2026-04-08 01:47:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(483, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 0, '2026-04-08 01:47:06', '2026-04-08 01:47:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(484, NULL, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 2, '2026-04-08 02:31:21', '2026-04-08 02:31:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(485, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-04-08 02:37:05', '2026-04-08 02:37:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(486, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-08 02:38:45', '2026-04-08 02:38:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(487, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-08 02:44:32', '2026-04-08 02:44:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(488, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-08 02:44:38', '2026-04-08 02:44:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(489, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-08 02:45:34', '2026-04-08 02:45:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(490, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-08 02:45:46', '2026-04-08 02:45:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(491, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-08 02:53:07', '2026-04-08 02:53:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(492, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-08 02:53:14', '2026-04-08 02:53:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(493, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-08 02:53:31', '2026-04-08 02:53:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(494, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-08 02:53:42', '2026-04-08 02:53:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(495, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-08 02:55:12', '2026-04-08 02:55:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(496, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-08 02:56:54', '2026-04-08 02:57:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(497, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 9, '2026-04-08 02:57:03', '2026-04-08 02:57:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(498, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 25, '2026-04-08 02:57:16', '2026-04-08 02:57:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(499, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-08 02:57:44', '2026-04-08 02:57:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(500, NULL, '9r2fucqgkb9ckr9rfnlpmmbsou', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-08 03:47:06', '2026-04-08 03:47:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(501, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/dashboard.php', 25, '2026-04-10 02:17:38', '2026-04-10 02:18:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(502, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-10 02:18:09', '2026-04-10 02:18:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(503, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-04-10 02:18:52', '2026-04-10 02:18:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(504, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 62, '2026-04-10 02:19:36', '2026-04-10 02:20:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(505, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 7, '2026-04-10 02:21:30', '2026-04-10 02:21:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(506, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-10 02:22:09', '2026-04-10 02:22:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(507, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-10 02:22:21', '2026-04-10 02:22:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(508, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-10 02:39:45', '2026-04-10 02:39:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(509, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-10 02:39:50', '2026-04-10 02:39:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(510, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 7, '2026-04-10 02:39:56', '2026-04-10 02:40:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(511, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 32, '2026-04-10 02:40:36', '2026-04-10 02:41:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(512, 32, 'pk9orqc955k6g9rjb6she06cve', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-10 02:48:03', '2026-04-10 02:48:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(513, 26, 'djod753d1fekvunm7tlsffmdni', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-04-10 02:50:38', '2026-04-10 02:50:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(514, 26, 'djod753d1fekvunm7tlsffmdni', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-04-10 03:21:55', '2026-04-10 03:21:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(515, 26, 'djod753d1fekvunm7tlsffmdni', 'http://localhost/bii_localfinder/provider/dashboard.php', 7, '2026-04-10 03:22:16', '2026-04-10 03:22:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(516, 26, 'djod753d1fekvunm7tlsffmdni', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-04-10 03:29:05', '2026-04-10 03:29:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(517, 26, 'sqg3h671o6vudbgtlvuk7em12s', 'http://localhost/bii_localfinder/provider/dashboard.php', 7, '2026-04-10 04:24:14', '2026-04-10 04:24:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(518, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-10 04:59:36', '2026-04-10 04:59:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(519, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/providers.php', 8, '2026-04-10 04:59:46', '2026-04-10 04:59:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(520, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-10 04:59:56', '2026-04-10 04:59:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(521, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/providers.php', 6, '2026-04-10 05:00:27', '2026-04-10 05:00:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(522, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/services.php', 5, '2026-04-10 05:00:34', '2026-04-10 05:00:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(523, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/service.php?service_id=49', 4, '2026-04-10 05:00:45', '2026-04-10 05:00:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(524, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/service.php?service_id=49', 6, '2026-04-10 05:00:52', '2026-04-10 05:00:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(525, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/services.php', 3, '2026-04-10 05:00:59', '2026-04-10 05:01:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(526, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 1, '2026-04-10 05:01:05', '2026-04-10 05:01:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(527, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 24, '2026-04-10 05:01:09', '2026-04-10 05:01:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(528, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 5, '2026-04-10 05:01:34', '2026-04-10 05:01:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(529, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 3, '2026-04-10 05:01:40', '2026-04-10 05:01:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(530, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/services.php', 5, '2026-04-10 05:01:43', '2026-04-10 05:01:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(531, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-10 05:01:51', '2026-04-10 05:01:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(532, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 10, '2026-04-10 15:02:16', '2026-04-10 15:02:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(533, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:12:57', '2026-04-10 15:13:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(534, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:15:57', '2026-04-10 15:16:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(535, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 13, '2026-04-10 15:18:06', '2026-04-10 15:18:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(536, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-10 15:18:33', '2026-04-10 15:18:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(537, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 5, '2026-04-10 15:18:44', '2026-04-10 15:18:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(538, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:19:04', '2026-04-10 15:19:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(539, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-04-10 15:19:07', '2026-04-10 15:19:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(540, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-10 15:19:16', '2026-04-10 15:19:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(541, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 42, '2026-04-10 15:19:41', '2026-04-10 15:20:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(542, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 1, '2026-04-10 15:20:47', '2026-04-10 15:20:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(543, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 8, '2026-04-10 15:20:49', '2026-04-10 15:20:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(544, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 10, '2026-04-10 15:22:46', '2026-04-10 15:22:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(545, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 17, '2026-04-10 15:25:13', '2026-04-10 15:25:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(546, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:25:44', '2026-04-10 15:25:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(547, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 14, '2026-04-10 15:25:47', '2026-04-10 15:26:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(548, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-10 15:27:23', '2026-04-10 15:27:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(549, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:27:26', '2026-04-10 15:27:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(550, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/profile.php', 5, '2026-04-10 15:30:12', '2026-04-10 15:30:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(551, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/profile.php?section=services', 12, '2026-04-10 15:30:18', '2026-04-10 15:30:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(552, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/profile.php?section=portfolio', 15, '2026-04-10 15:30:32', '2026-04-10 15:30:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(553, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 15, '2026-04-10 15:30:49', '2026-04-10 15:31:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `page_sessions` (`id`, `user_id`, `session_id`, `page_url`, `time_spent_seconds`, `start_time`, `end_time`, `ip_address`, `user_agent`) VALUES
(554, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-10 15:35:55', '2026-04-10 15:36:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(555, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 5, '2026-04-10 15:36:49', '2026-04-10 15:36:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(556, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-10 15:38:33', '2026-04-10 15:38:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(557, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-10 15:38:36', '2026-04-10 15:38:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(558, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:41:00', '2026-04-10 15:41:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(559, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 3, '2026-04-10 15:41:36', '2026-04-10 15:41:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(560, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/profile.php', 17, '2026-04-10 15:44:16', '2026-04-10 15:44:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(561, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 12, '2026-04-10 15:44:33', '2026-04-10 15:44:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(562, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 6, '2026-04-10 15:46:52', '2026-04-10 15:46:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(563, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 9, '2026-04-10 15:50:13', '2026-04-10 15:50:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(564, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 4, '2026-04-10 15:51:04', '2026-04-10 15:51:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(565, 26, 'llnbghuftlqkau8c45khqpbiri', 'http://localhost/bii_localfinder/provider/dashboard.php', 2, '2026-04-10 15:53:45', '2026-04-10 15:53:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(566, 26, 'h3nlbpn7dv1q3qslmi2n3ktu7v', 'http://localhost/bii_localfinder/provider/profile.php', 28, '2026-04-10 21:19:25', '2026-04-10 21:19:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(567, 26, 'vqmpucias8mhgc5mv9ij7te0r7', 'http://localhost/bii_localfinder/provider/profile.php', 5, '2026-04-11 14:54:40', '2026-04-11 14:54:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(568, 26, 'vqmpucias8mhgc5mv9ij7te0r7', 'http://localhost/bii_localfinder/provider/profile.php?section=services', 19, '2026-04-11 14:54:47', '2026-04-11 14:55:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(569, 26, 'vqmpucias8mhgc5mv9ij7te0r7', 'http://localhost/bii_localfinder/provider/profile.php?section=services', 2, '2026-04-11 14:55:30', '2026-04-11 14:55:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(570, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/dashboard.php', 18, '2026-04-13 04:22:15', '2026-04-13 04:22:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(571, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/providers.php', 16, '2026-04-13 04:22:35', '2026-04-13 04:22:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(572, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-13 04:23:00', '2026-04-13 04:23:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(573, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/services.php', 10, '2026-04-13 04:23:06', '2026-04-13 04:23:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(574, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/service.php?service_id=50', 5, '2026-04-13 04:23:17', '2026-04-13 04:23:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(575, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=50', 129, '2026-04-13 04:23:23', '2026-04-13 04:25:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(576, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/providers.php', 22, '2026-04-13 04:26:58', '2026-04-13 04:27:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(577, 32, 'd8c49qhrs9jkricchm6l055sjk', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-13 04:27:22', '2026-04-13 04:27:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(578, 32, 'peedm25s306gnq5rngfjm39tt4', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-14 00:05:24', '2026-04-14 00:05:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(579, 32, 'peedm25s306gnq5rngfjm39tt4', 'http://localhost/bii_localfinder/client/dashboard.php', 7, '2026-04-14 00:05:31', '2026-04-14 00:05:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(580, 32, 'gm6dub1up1jiv6unaf84q07431', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-14 00:22:12', '2026-04-14 00:22:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(581, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php', 7, '2026-04-15 01:53:42', '2026-04-15 01:53:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(582, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=requirements', 2, '2026-04-15 01:53:55', '2026-04-15 01:53:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(583, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 20, '2026-04-15 01:54:03', '2026-04-15 01:54:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(584, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 11, '2026-04-15 01:55:51', '2026-04-15 01:56:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(585, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 3, '2026-04-15 01:56:06', '2026-04-15 01:56:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(586, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 2, '2026-04-15 01:56:20', '2026-04-15 01:56:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(587, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 1, '2026-04-15 01:57:34', '2026-04-15 01:57:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(588, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 1, '2026-04-15 01:58:45', '2026-04-15 01:58:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(589, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 1, '2026-04-15 02:24:00', '2026-04-15 02:24:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(590, 26, 'glp04uhvllnjcod20the4huhil', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 6, '2026-04-15 02:27:48', '2026-04-15 02:27:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(591, NULL, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 2, '2026-04-15 02:56:16', '2026-04-15 02:56:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(592, NULL, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 4, '2026-04-15 03:10:09', '2026-04-15 03:10:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(593, NULL, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 1, '2026-04-15 03:10:32', '2026-04-15 03:10:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(594, NULL, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php?section=social', 1, '2026-04-15 03:24:28', '2026-04-15 03:24:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(595, 26, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php', 4, '2026-04-15 04:53:50', '2026-04-15 04:53:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(596, 26, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php', 4, '2026-04-15 05:12:43', '2026-04-15 05:12:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(597, 26, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php', 6, '2026-04-15 05:25:06', '2026-04-15 05:25:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(598, 26, 'c7c3g3a9tuds8j2jtvplig7lh8', 'http://localhost/bii_localfinder/provider/profile.php', 3, '2026-04-15 05:26:02', '2026-04-15 05:26:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(599, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-15 23:50:00', '2026-04-15 23:50:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(600, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-15 23:50:06', '2026-04-15 23:50:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(601, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-15 23:51:04', '2026-04-15 23:51:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(602, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/providers.php', 4, '2026-04-15 23:51:36', '2026-04-15 23:51:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(603, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-15 23:52:04', '2026-04-15 23:52:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(604, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-15 23:52:12', '2026-04-15 23:52:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(605, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/providers.php', 5, '2026-04-15 23:55:57', '2026-04-15 23:56:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(606, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-04-15 23:57:20', '2026-04-15 23:57:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(607, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-15 23:57:43', '2026-04-15 23:57:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(608, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 36, '2026-04-15 23:59:37', '2026-04-16 00:00:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(609, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 14, '2026-04-16 00:00:15', '2026-04-16 00:00:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(610, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-16 00:00:31', '2026-04-16 00:00:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(611, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 6, '2026-04-16 00:11:25', '2026-04-16 00:11:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(612, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-04-16 00:13:09', '2026-04-16 00:13:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(613, 32, '65f9oiue9htmo3agh7p5u41t9f', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-16 00:14:06', '2026-04-16 00:14:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(614, NULL, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/dashboard.php', 27, '2026-04-16 00:58:16', '2026-04-16 00:58:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(615, NULL, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-04-16 00:59:05', '2026-04-16 00:59:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(616, NULL, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/dashboard.php', 3, '2026-04-16 00:59:24', '2026-04-16 00:59:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(617, NULL, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/dashboard.php', 5, '2026-04-16 00:59:40', '2026-04-16 00:59:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(618, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/dashboard.php', 71, '2026-04-16 01:01:41', '2026-04-16 01:02:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(619, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/dashboard.php', 14, '2026-04-16 01:26:40', '2026-04-16 01:26:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(620, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-16 01:27:14', '2026-04-16 01:27:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(621, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 42, '2026-04-16 01:27:54', '2026-04-16 01:28:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(622, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 18, '2026-04-16 01:28:46', '2026-04-16 01:29:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(623, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 319, '2026-04-16 01:29:34', '2026-04-16 01:34:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(624, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-16 01:35:46', '2026-04-16 01:35:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(625, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 2, '2026-04-16 01:36:47', '2026-04-16 01:36:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(626, 32, '3qu5et7nl88dacebdkttj5ddvo', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-16 01:36:52', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(627, NULL, 'icqf9p9jjsq2pnir3vs9ps4js4', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-16 03:16:10', '2026-04-16 03:16:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(628, 32, '8fgq2ategbp4cokr0vv0s6shvm', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-16 03:40:35', '2026-04-16 03:40:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(629, 32, '8fgq2ategbp4cokr0vv0s6shvm', 'http://localhost/bii_localfinder/client/providers.php', 3, '2026-04-16 03:40:40', '2026-04-16 03:40:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(630, 32, 'fd3vbnal20u239voga0sqrecun', 'http://localhost/bii_localfinder/client/dashboard.php', 11, '2026-04-16 05:11:57', '2026-04-16 05:12:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(631, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/dashboard.php', 9, '2026-04-16 05:53:11', '2026-04-16 05:53:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(632, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 60, '2026-04-16 05:53:25', '2026-04-16 05:54:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(633, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 18, '2026-04-16 05:54:25', '2026-04-16 05:54:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(634, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 1, '2026-04-16 05:56:54', '2026-04-16 05:56:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(635, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-16 05:56:55', '2026-04-16 05:56:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(636, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 11, '2026-04-16 05:57:05', '2026-04-16 05:57:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(637, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 9, '2026-04-16 05:58:55', '2026-04-16 05:59:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(638, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 21, '2026-04-16 05:59:05', '2026-04-16 05:59:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(639, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 17, '2026-04-16 05:59:27', '2026-04-16 05:59:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(640, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-16 06:01:27', '2026-04-16 06:01:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(641, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 19, '2026-04-16 06:01:33', '2026-04-16 06:01:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(642, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 9, '2026-04-16 06:02:45', '2026-04-16 06:02:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(643, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 13, '2026-04-16 06:03:24', '2026-04-16 06:03:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(644, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 118, '2026-04-16 06:03:38', '2026-04-16 06:05:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(645, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 26, '2026-04-16 06:05:52', '2026-04-16 06:06:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(646, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 6, '2026-04-16 06:07:20', '2026-04-16 06:07:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(647, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-16 06:15:14', '2026-04-16 06:15:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(648, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-16 06:15:16', '2026-04-16 06:15:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(649, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 27, '2026-04-16 06:15:23', '2026-04-16 06:15:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(650, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 7, '2026-04-16 06:15:50', '2026-04-16 06:15:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(651, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 1, '2026-04-16 06:17:00', '2026-04-16 06:17:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(652, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 6, '2026-04-16 06:18:29', '2026-04-16 06:18:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(653, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 20, '2026-04-16 06:18:36', '2026-04-16 06:18:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(654, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 1, '2026-04-16 06:19:55', '2026-04-16 06:19:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(655, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 5, '2026-04-16 06:21:06', '2026-04-16 06:21:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(656, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 20, '2026-04-16 06:21:11', '2026-04-16 06:21:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(657, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 11, '2026-04-16 06:21:32', '2026-04-16 06:21:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(658, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 10, '2026-04-16 06:21:44', '2026-04-16 06:21:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(659, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 23, '2026-04-16 06:21:55', '2026-04-16 06:22:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(660, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 3, '2026-04-16 06:22:18', '2026-04-16 06:22:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(661, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 5, '2026-04-16 06:22:30', '2026-04-16 06:22:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(662, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 3, '2026-04-16 06:22:36', '2026-04-16 06:22:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(663, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 0, '2026-04-16 06:23:37', '2026-04-16 06:23:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(664, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 14, '2026-04-16 06:25:22', '2026-04-16 06:25:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(665, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 2, '2026-04-16 06:25:37', '2026-04-16 06:25:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(666, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'http://localhost/bii_localfinder/client/dashboard.php', 23, '2026-04-16 22:49:17', '2026-04-16 22:49:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(667, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-16 22:50:18', '2026-04-16 22:50:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(668, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'http://localhost/bii_localfinder/client/dashboard.php', 2, '2026-04-16 22:50:22', '2026-04-16 22:50:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(669, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-16 22:50:41', '2026-04-16 22:50:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(670, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'http://localhost/bii_localfinder/client/providers.php', 0, '2026-04-16 22:51:01', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(671, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-16 23:04:52', '2026-04-16 23:04:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(672, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-16 23:05:30', '2026-04-16 23:05:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(673, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 47, '2026-04-16 23:05:40', '2026-04-16 23:06:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(674, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-16 23:13:09', '2026-04-16 23:13:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(675, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-16 23:16:14', '2026-04-16 23:16:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(676, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'http://localhost/bii_localfinder/client/dashboard.php', 4, '2026-04-16 23:16:19', '2026-04-16 23:16:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(677, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 39, '2026-04-16 23:16:27', '2026-04-16 23:17:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(678, 32, '4kbhjpbnlnedh06t08p0mavle6', 'http://localhost/bii_localfinder/client/dashboard.php', 25, '2026-04-16 23:18:56', '2026-04-16 23:19:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(679, 32, '4kbhjpbnlnedh06t08p0mavle6', 'http://localhost/bii_localfinder/client/dashboard.php', 1, '2026-04-17 00:04:24', '2026-04-17 00:04:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(680, 32, '4kbhjpbnlnedh06t08p0mavle6', 'http://localhost/bii_localfinder/client/providers.php', 32, '2026-04-17 00:04:28', '2026-04-17 00:05:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(681, NULL, 'ubf97sgd2i2unl6637vluq2mbb', 'http://localhost/bii_localfinder/client/providers.php', 1, '2026-04-17 00:57:00', '2026-04-17 00:57:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(682, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'http://localhost/bii_localfinder/client/dashboard.php', 184, '2026-04-17 00:57:13', '2026-04-17 01:00:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(683, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 5, '2026-04-17 01:00:25', '2026-04-17 01:00:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(684, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'http://localhost/bii_localfinder/client/dashboard.php', 8, '2026-04-17 01:00:52', '2026-04-17 01:01:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(685, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 65, '2026-04-17 01:01:15', '2026-04-17 01:02:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(686, 32, 'tbd1t4a2v01dlj9rlroobljgta', 'http://localhost/bii_localfinder/client/dashboard.php', 12, '2026-04-17 01:04:40', '2026-04-17 01:04:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(687, 32, 'tbd1t4a2v01dlj9rlroobljgta', 'http://localhost/bii_localfinder/client/dashboard.php', 0, '2026-04-17 01:44:40', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36');

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
(35, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'puh57rk2g1r9ko38jf8dqfjtq3', '2026-03-27 08:33:10'),
(36, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', '', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'puh57rk2g1r9ko38jf8dqfjtq3', '2026-03-27 09:05:18'),
(37, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'puh57rk2g1r9ko38jf8dqfjtq3', '2026-03-27 09:05:22'),
(38, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'btnlq8grs8slathsa8e5ks2npu', '2026-03-27 13:28:19'),
(39, 31, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5uvb7hia0n1kfn1nmmfg8gqs0b', '2026-03-27 13:32:20'),
(40, 31, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5uvb7hia0n1kfn1nmmfg8gqs0b', '2026-03-27 13:32:37'),
(41, 36, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/verify-otp.php?email=samybruno900%40gmail.com&flow=registration&next=client%2Fdashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'p0bd2d5720jvd957tr82rrr454', '2026-03-29 15:33:44'),
(42, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'p2ln2p2t74hjah8f5avaqaip98', '2026-03-29 15:42:53'),
(43, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'p2ln2p2t74hjah8f5avaqaip98', '2026-03-29 15:56:39'),
(44, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'tu4o484g3jr96n61430p31ulu0', '2026-03-29 16:07:37'),
(45, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llkdkvu9nfsm89l0sdv5cc9lel', '2026-03-29 16:12:29'),
(46, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0h2l2chrhocadvkns8jjk8pric', '2026-03-29 16:20:39'),
(47, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'fsnc2h6k9s527fa03fo3s6imss', '2026-03-29 16:49:07'),
(48, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'fsnc2h6k9s527fa03fo3s6imss', '2026-03-29 16:54:44'),
(49, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5qou2scvj29aa2ptsgflqhrqo5', '2026-03-29 16:55:19'),
(50, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'f19a115c76sobv08p923susvhk', '2026-03-29 16:55:49'),
(51, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'i7u6v9cfbgk9pcf885eclrfagu', '2026-03-29 17:08:48'),
(52, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'tc4pffc1p0bcq0smrg4isp6gir', '2026-03-29 17:20:55'),
(53, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php?with=31', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'tc4pffc1p0bcq0smrg4isp6gir', '2026-03-29 18:00:01'),
(54, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'f373nmt3t2j2ffe6tnuaaddra6', '2026-03-30 07:46:03'),
(55, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'fthopo3t4j9t07d97t70lrfgro', '2026-03-30 09:05:56'),
(56, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php?with=32', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'fthopo3t4j9t07d97t70lrfgro', '2026-03-30 09:17:32'),
(57, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'l3f99n8edgs58ijnrjs6qprkr4', '2026-03-30 09:29:01'),
(58, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'l3f99n8edgs58ijnrjs6qprkr4', '2026-03-30 10:05:12'),
(59, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0vgi472hv6hb6u1erb8dcjfrbd', '2026-03-30 10:05:53'),
(60, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php?with=32', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0vgi472hv6hb6u1erb8dcjfrbd', '2026-03-30 10:13:57'),
(61, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0vgi472hv6hb6u1erb8dcjfrbd', '2026-03-30 10:14:10'),
(62, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php?with=31', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0vgi472hv6hb6u1erb8dcjfrbd', '2026-03-30 10:19:33'),
(63, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0vgi472hv6hb6u1erb8dcjfrbd', '2026-03-30 10:39:23'),
(64, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 20:54:11'),
(65, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 20:59:31'),
(66, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 20:59:31'),
(67, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 20:59:44'),
(68, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 20:59:45'),
(69, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:03:58'),
(70, 32, 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:35:14'),
(71, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:35:18'),
(72, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?query=&location=&category=', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:52:25'),
(73, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:53:11'),
(74, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:53:12'),
(75, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/favorites.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:53:16'),
(76, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:53:22'),
(77, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:53:37'),
(78, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'deuqpu4if92v105383qr0lbdkb', '2026-03-30 21:53:41'),
(79, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:55:22'),
(80, 32, 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:55:26'),
(81, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=rating', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:55:35'),
(82, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=newest', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=rating', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:55:47'),
(83, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=price_desc', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=newest', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:55:52'),
(84, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=price_desc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 21:56:06'),
(85, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=price_desc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 22:00:18'),
(86, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=price_desc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 22:01:30'),
(87, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Client Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 22:02:32'),
(88, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6am9smbio683o1jagofv1g3leq', '2026-03-30 22:05:29'),
(89, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ec0u28fn9dgcb9dgqn12610kht', '2026-03-30 22:24:49'),
(90, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ec0u28fn9dgcb9dgqn12610kht', '2026-03-30 22:25:24'),
(91, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=availability', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ec0u28fn9dgcb9dgqn12610kht', '2026-03-30 22:49:52'),
(92, 36, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5r5op3l1h59nntenhv12r1ug27', '2026-03-30 22:51:22'),
(93, 36, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5r5op3l1h59nntenhv12r1ug27', '2026-03-30 22:52:18'),
(94, 36, 'http://localhost/bii_localfinder/client/booking.php?provider_id=16', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5r5op3l1h59nntenhv12r1ug27', '2026-03-30 22:52:31'),
(95, 36, 'http://localhost/bii_localfinder/client/booking.php?provider_id=16', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5r5op3l1h59nntenhv12r1ug27', '2026-03-30 22:52:32'),
(96, 36, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '5r5op3l1h59nntenhv12r1ug27', '2026-03-30 22:52:49'),
(97, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kef9dtp6r31kosap85lq2i98hs', '2026-03-31 20:26:50'),
(98, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:42:30'),
(99, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:43:03'),
(100, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:43:03'),
(101, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:43:16'),
(102, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:58:32'),
(103, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 09:59:36'),
(104, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:02:53'),
(105, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:02:54'),
(106, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:03:04'),
(107, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:06:23'),
(108, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gq23gp4f7kkprnvvmvsjcuqsk2', '2026-04-01 10:08:06'),
(109, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', '', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:58:25'),
(110, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:58:25'),
(111, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:58:44'),
(112, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:58:44'),
(113, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:59:27'),
(114, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:59:28'),
(115, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', '', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:59:30'),
(116, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:59:31'),
(117, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:59:35'),
(118, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 10:59:36'),
(119, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:03:40'),
(120, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:03:40'),
(121, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:07:11'),
(122, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:07:11'),
(123, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', '', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:07:20'),
(124, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:07:21'),
(125, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', '', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:08:09'),
(126, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:08:09'),
(127, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', '', 'http://localhost/bii_localfinder/client/service.php?service_id=48', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:09:23'),
(128, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=48', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=48', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:09:23'),
(129, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:10:47'),
(130, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'og0tq3ltp69i2uva350kopglds', '2026-04-01 11:17:14'),
(131, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:28:58'),
(132, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:30:18'),
(133, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=15', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:32:01'),
(134, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=15', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:32:01'),
(135, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:32:15'),
(136, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4vm6fkleg3o2ri80bhbp7peq3n', '2026-04-01 14:32:57'),
(137, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'l2smae5oao5rmve5o4o1ip1osn', '2026-04-01 14:40:52'),
(138, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'eooaiij09kobtmcev2cu85bsjp', '2026-04-01 14:42:39'),
(139, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0sjn7qeue927jn2rj8pektpdgh', '2026-04-01 14:43:36'),
(140, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Dashibodi y\'Utanga Serivisi - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=language', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0sjn7qeue927jn2rj8pektpdgh', '2026-04-01 14:44:02'),
(141, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'v5pv03vck8rmfegi4irl0fu505', '2026-04-01 20:30:47'),
(142, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'v5pv03vck8rmfegi4irl0fu505', '2026-04-01 20:31:10'),
(143, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'v5pv03vck8rmfegi4irl0fu505', '2026-04-01 20:31:22'),
(144, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'v5pv03vck8rmfegi4irl0fu505', '2026-04-01 20:42:57'),
(145, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'v5pv03vck8rmfegi4irl0fu505', '2026-04-01 20:43:23'),
(146, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '068v7mf0tmt3qick57e5b12bmo', '2026-04-01 20:59:14'),
(147, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '068v7mf0tmt3qick57e5b12bmo', '2026-04-01 21:00:22');
INSERT INTO `page_views` (`id`, `user_id`, `page_url`, `page_title`, `referrer`, `user_agent`, `ip_address`, `session_id`, `viewed_at`) VALUES
(148, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '068v7mf0tmt3qick57e5b12bmo', '2026-04-01 21:05:34'),
(149, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:09:26'),
(150, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:09:51'),
(151, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:10:09'),
(152, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:10:32'),
(153, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:25:03'),
(154, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:26:35'),
(155, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:27:51'),
(156, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:28:01'),
(157, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:31:58'),
(158, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:34:32'),
(159, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:36:16'),
(160, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rfebqhsns022kic0k2guolsh6l', '2026-04-01 21:37:59'),
(161, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 21:42:13'),
(162, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 21:42:22'),
(163, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 21:51:05'),
(164, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 21:51:37'),
(165, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 21:58:40'),
(166, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 21:59:15'),
(167, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:01:00'),
(168, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:03:12'),
(169, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:03:55'),
(170, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:04:47'),
(171, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:08:59'),
(172, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:09:49'),
(173, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:11:00'),
(174, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:22:14'),
(175, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'qs2u070r6gadetjrfq4ibquch0', '2026-04-01 22:23:32'),
(176, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'el1dtkd40ltmoq49u8cd971h3t', '2026-04-03 08:21:30'),
(177, 32, 'http://localhost/bii_localfinder/client/providers.php?section=top-rated', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'el1dtkd40ltmoq49u8cd971h3t', '2026-04-03 08:26:47'),
(178, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ebja1i9886b9a52bka9grcojon', '2026-04-03 14:16:35'),
(179, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 14:26:36'),
(180, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:13:18'),
(181, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:13:56'),
(182, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:15:41'),
(183, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:15:48'),
(184, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:17:21'),
(185, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:17:54'),
(186, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:18:18'),
(187, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:20:05'),
(188, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:20:37'),
(189, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:20:52'),
(190, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:22:13'),
(191, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:25:31'),
(192, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:25:31'),
(193, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:25:34'),
(194, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:25:53'),
(195, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rd0upg03d6ov3a6v4u8lmn7k5g', '2026-04-03 15:26:13'),
(196, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9do680060n5vl4131prcglr2iu', '2026-04-03 15:27:09'),
(197, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php?view=my-offers', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9do680060n5vl4131prcglr2iu', '2026-04-03 15:28:12'),
(198, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9do680060n5vl4131prcglr2iu', '2026-04-03 16:22:12'),
(199, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'h9cb31qihr6jhahg7k8666ba7l', '2026-04-03 16:39:06'),
(200, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'h9cb31qihr6jhahg7k8666ba7l', '2026-04-03 16:39:33'),
(201, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'h9cb31qihr6jhahg7k8666ba7l', '2026-04-03 16:49:58'),
(202, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'h9cb31qihr6jhahg7k8666ba7l', '2026-04-03 17:10:07'),
(203, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ebj24o9vusejt1gsdg8b5vjve9', '2026-04-03 17:19:31'),
(204, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ebj24o9vusejt1gsdg8b5vjve9', '2026-04-03 17:20:49'),
(205, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'ebj24o9vusejt1gsdg8b5vjve9', '2026-04-03 17:21:32'),
(206, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:28:26'),
(207, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:28:57'),
(208, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:29:28'),
(209, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:30:20'),
(210, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:30:35'),
(211, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:33:18'),
(212, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:33:31'),
(213, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:33:36'),
(214, 32, 'http://localhost/bii_localfinder/client/providers.php?section=offers', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?sort=system', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:34:29'),
(215, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php?section=offers', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '2k354vrl37vnnem4uk8e0u4tua', '2026-04-03 17:36:07'),
(216, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0aju6nuc67dtukfgfumijsb1e0', '2026-04-03 17:58:05'),
(217, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0aju6nuc67dtukfgfumijsb1e0', '2026-04-03 18:40:11'),
(218, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0aju6nuc67dtukfgfumijsb1e0', '2026-04-03 18:45:18'),
(219, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0aju6nuc67dtukfgfumijsb1e0', '2026-04-03 18:48:27'),
(220, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '0aju6nuc67dtukfgfumijsb1e0', '2026-04-03 18:49:08'),
(221, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4heqse9u369lvu2uq1iierug1m', '2026-04-03 18:59:48'),
(222, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '4heqse9u369lvu2uq1iierug1m', '2026-04-03 19:07:08'),
(223, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '56g41ok0ds94ggl18veotq3qvv', '2026-04-03 19:58:57'),
(224, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'bnk1eb554uat135p1n0ok4pn48', '2026-04-03 22:27:45'),
(225, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'bnk1eb554uat135p1n0ok4pn48', '2026-04-03 22:36:31'),
(226, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'bnk1eb554uat135p1n0ok4pn48', '2026-04-03 22:38:20'),
(227, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'bnk1eb554uat135p1n0ok4pn48', '2026-04-03 23:27:26'),
(228, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:11:48'),
(229, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:11:59'),
(230, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:12:22'),
(231, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:12:23'),
(232, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26&booking_id=40', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:13:55'),
(233, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sdkvgh2utp5c0ql5upiec4l4td', '2026-04-04 00:14:01'),
(234, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:09:31'),
(235, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:10:47'),
(236, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:13:01'),
(237, 32, 'http://localhost/bii_localfinder/client/favorites.php', 'My Favorites - BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:13:02'),
(238, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/favorites.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:15:10'),
(239, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:17:21'),
(240, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:19:19'),
(241, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'kpk9s5pnhvkq65ot6vivif9vaq', '2026-04-07 11:26:16'),
(242, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rqqb5ndto5m5uh602ahjpdtsbt', '2026-04-07 18:14:43'),
(243, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rqqb5ndto5m5uh602ahjpdtsbt', '2026-04-07 18:14:53'),
(244, 32, 'http://localhost/bii_localfinder/client/providers.php?sort=ml', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'rqqb5ndto5m5uh602ahjpdtsbt', '2026-04-07 18:28:42'),
(245, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9gp3b3n1cbugnfjti9sg7sqp6j', '2026-04-07 19:36:49'),
(246, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9gp3b3n1cbugnfjti9sg7sqp6j', '2026-04-07 19:37:15'),
(247, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9gp3b3n1cbugnfjti9sg7sqp6j', '2026-04-07 19:45:51'),
(248, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9gp3b3n1cbugnfjti9sg7sqp6j', '2026-04-07 19:53:16'),
(249, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '9gp3b3n1cbugnfjti9sg7sqp6j', '2026-04-07 19:53:45'),
(250, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'pk9orqc955k6g9rjb6she06cve', '2026-04-09 19:17:33'),
(251, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', '', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'pk9orqc955k6g9rjb6she06cve', '2026-04-09 19:19:36'),
(252, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'pk9orqc955k6g9rjb6she06cve', '2026-04-09 19:19:37'),
(253, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'pk9orqc955k6g9rjb6she06cve', '2026-04-09 19:22:09'),
(254, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'pk9orqc955k6g9rjb6she06cve', '2026-04-09 19:39:52'),
(255, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'pk9orqc955k6g9rjb6she06cve', '2026-04-09 19:39:56'),
(256, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'djod753d1fekvunm7tlsffmdni', '2026-04-09 19:50:38'),
(257, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'djod753d1fekvunm7tlsffmdni', '2026-04-09 20:21:55'),
(258, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'sqg3h671o6vudbgtlvuk7em12s', '2026-04-09 21:24:14'),
(259, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 21:59:37'),
(260, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 21:59:47'),
(261, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 21:59:56'),
(262, 32, 'http://localhost/bii_localfinder/client/services.php', 'Browse Services - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:00:34'),
(263, 32, 'http://localhost/bii_localfinder/client/service.php?service_id=49', 'Kumena amavuta — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:00:50'),
(264, 32, 'http://localhost/bii_localfinder/client/services.php', 'Browse Services - BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=49', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:00:59'),
(265, 32, 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Personal Driver (Daily Transport) — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:05'),
(266, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', '', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:34'),
(267, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=47', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:35'),
(268, 32, 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Personal Driver (Daily Transport) — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:40'),
(269, 32, 'http://localhost/bii_localfinder/client/services.php', 'Browse Services - BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:43'),
(270, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', '6674ktb3tjvq1f21r2gvjm0nla', '2026-04-09 22:01:51'),
(271, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:02:17'),
(272, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=visibility', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:15:57'),
(273, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:18:06'),
(274, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:18:33'),
(275, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:19:07'),
(276, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:19:42'),
(277, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:20:49'),
(278, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=communication', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:25:14'),
(279, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=communication', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:25:48'),
(280, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=communication', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:27:26'),
(281, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=notifications', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:30:12'),
(282, 26, 'http://localhost/bii_localfinder/provider/profile.php?section=services', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:30:18'),
(283, 26, 'http://localhost/bii_localfinder/provider/profile.php?section=portfolio', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php?section=services', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:30:32'),
(284, 26, 'http://localhost/bii_localfinder/provider/profile.php?section=social', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php?section=portfolio', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:30:49'),
(285, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:35:55'),
(286, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/schedule.php?tab=availability', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:38:33'),
(287, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:38:36'),
(288, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Dashibodi y\'Utanga Serivisi - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=language', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:41:01'),
(289, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Dashibodi y\'Utanga Serivisi - BII LocalFinder', 'http://localhost/bii_localfinder/provider/reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:41:36'),
(290, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:44:16'),
(291, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:44:33'),
(292, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=language', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:46:52'),
(293, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/reviews.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:50:13'),
(294, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:51:04');
INSERT INTO `page_views` (`id`, `user_id`, `page_url`, `page_title`, `referrer`, `user_agent`, `ip_address`, `session_id`, `viewed_at`) VALUES
(295, 26, 'http://localhost/bii_localfinder/provider/dashboard.php', 'Provider Dashboard - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=ai', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'llnbghuftlqkau8c45khqpbiri', '2026-04-10 08:53:45'),
(296, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/messages.php?with=32', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'h3nlbpn7dv1q3qslmi2n3ktu7v', '2026-04-10 14:19:25'),
(297, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vqmpucias8mhgc5mv9ij7te0r7', '2026-04-11 07:54:45'),
(298, 26, 'http://localhost/bii_localfinder/provider/profile.php?section=services', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'vqmpucias8mhgc5mv9ij7te0r7', '2026-04-11 07:54:47'),
(299, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:22:15'),
(300, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:22:35'),
(301, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:00'),
(302, 32, 'http://localhost/bii_localfinder/client/services.php', 'Browse Services - BII LocalFinder', 'http://localhost/bii_localfinder/client/providers.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:06'),
(303, 32, 'http://localhost/bii_localfinder/client/service.php?service_id=50', 'Traveling with Tourism — BII LocalFinder', 'http://localhost/bii_localfinder/client/services.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:17'),
(304, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=50', '', 'http://localhost/bii_localfinder/client/service.php?service_id=50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:23'),
(305, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12&service_id=50', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/service.php?service_id=50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:23:23'),
(306, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/settings.php?section=profile', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'd8c49qhrs9jkricchm6l055sjk', '2026-04-12 21:26:56'),
(307, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'peedm25s306gnq5rngfjm39tt4', '2026-04-13 17:05:24'),
(308, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'peedm25s306gnq5rngfjm39tt4', '2026-04-13 17:05:32'),
(309, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'gm6dub1up1jiv6unaf84q07431', '2026-04-13 17:22:12'),
(310, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=availability', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'glp04uhvllnjcod20the4huhil', '2026-04-14 18:53:42'),
(311, 26, 'http://localhost/bii_localfinder/provider/profile.php?section=requirements', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'glp04uhvllnjcod20the4huhil', '2026-04-14 18:53:55'),
(312, 26, 'http://localhost/bii_localfinder/provider/profile.php?section=social', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/profile.php?section=requirements', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'glp04uhvllnjcod20the4huhil', '2026-04-14 18:54:03'),
(313, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'c7c3g3a9tuds8j2jtvplig7lh8', '2026-04-14 21:53:51'),
(314, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php?section=ai', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'c7c3g3a9tuds8j2jtvplig7lh8', '2026-04-14 22:12:43'),
(315, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/settings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'c7c3g3a9tuds8j2jtvplig7lh8', '2026-04-14 22:25:06'),
(316, 26, 'http://localhost/bii_localfinder/provider/profile.php', 'Edit Profile - BII LocalFinder', 'http://localhost/bii_localfinder/provider/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'c7c3g3a9tuds8j2jtvplig7lh8', '2026-04-14 22:26:03'),
(317, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:50:01'),
(318, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:50:06'),
(319, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:51:04'),
(320, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:51:17'),
(321, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/messages.php?with=26', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '65f9oiue9htmo3agh7p5u41t9f', '2026-04-15 16:57:05'),
(322, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '3qu5et7nl88dacebdkttj5ddvo', '2026-04-15 18:00:54'),
(323, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '3qu5et7nl88dacebdkttj5ddvo', '2026-04-15 18:27:03'),
(324, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '3qu5et7nl88dacebdkttj5ddvo', '2026-04-15 18:36:52'),
(325, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '8fgq2ategbp4cokr0vv0s6shvm', '2026-04-15 20:40:35'),
(326, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '8fgq2ategbp4cokr0vv0s6shvm', '2026-04-15 20:40:40'),
(327, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'fd3vbnal20u239voga0sqrecun', '2026-04-15 22:11:57'),
(328, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:53:11'),
(329, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:53:25'),
(330, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:53:26'),
(331, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:54:25'),
(332, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:54:26'),
(333, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:59:06'),
(334, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:59:06'),
(335, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:59:28'),
(336, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 22:59:28'),
(337, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:03:38'),
(338, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:03:38'),
(339, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:15:16'),
(340, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:15:16'),
(341, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:15:51'),
(342, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:15:51'),
(343, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:18:37'),
(344, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:18:37'),
(345, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:11'),
(346, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:11'),
(347, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:32'),
(348, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:32'),
(349, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:44'),
(350, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:44'),
(351, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:55'),
(352, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:21:55'),
(353, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:22:18'),
(354, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:22:19'),
(355, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:22:36'),
(356, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:22:36'),
(357, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:25:37'),
(358, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'rj5cd8als5f31l4s2bnv0qgi8d', '2026-04-15 23:25:38'),
(359, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'h566gqvhskkh5vn7rfpqdrci8p', '2026-04-16 15:49:17'),
(360, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'h566gqvhskkh5vn7rfpqdrci8p', '2026-04-16 15:50:19'),
(361, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'h566gqvhskkh5vn7rfpqdrci8p', '2026-04-16 15:50:41'),
(362, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2jpafaabp8t5rtuhvm2ig60p6j', '2026-04-16 16:04:48'),
(363, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2jpafaabp8t5rtuhvm2ig60p6j', '2026-04-16 16:05:30'),
(364, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2jpafaabp8t5rtuhvm2ig60p6j', '2026-04-16 16:05:40'),
(365, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '2jpafaabp8t5rtuhvm2ig60p6j', '2026-04-16 16:05:42'),
(366, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4dgl51l7j84r98mv6bu3ae6hnm', '2026-04-16 16:13:09'),
(367, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4dgl51l7j84r98mv6bu3ae6hnm', '2026-04-16 16:16:19'),
(368, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4dgl51l7j84r98mv6bu3ae6hnm', '2026-04-16 16:16:27'),
(369, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4dgl51l7j84r98mv6bu3ae6hnm', '2026-04-16 16:16:28'),
(370, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4kbhjpbnlnedh06t08p0mavle6', '2026-04-16 16:18:56'),
(371, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4kbhjpbnlnedh06t08p0mavle6', '2026-04-16 17:04:24'),
(372, 32, 'http://localhost/bii_localfinder/client/providers.php', 'Find Providers — BII LocalFinder', 'http://localhost/bii_localfinder/client/dashboard.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', '4kbhjpbnlnedh06t08p0mavle6', '2026-04-16 17:04:29'),
(373, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'ubf97sgd2i2unl6637vluq2mbb', '2026-04-16 17:57:13'),
(374, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'ubf97sgd2i2unl6637vluq2mbb', '2026-04-16 18:00:25'),
(375, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'ubf97sgd2i2unl6637vluq2mbb', '2026-04-16 18:00:26'),
(376, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/my-bookings.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'ubf97sgd2i2unl6637vluq2mbb', '2026-04-16 18:00:52'),
(377, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', '', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'ubf97sgd2i2unl6637vluq2mbb', '2026-04-16 18:01:15'),
(378, 32, 'http://localhost/bii_localfinder/client/booking.php?provider_id=12', 'Book a Service — BII LocalFinder', 'http://localhost/bii_localfinder/client/provider-profile.php?id=12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'ubf97sgd2i2unl6637vluq2mbb', '2026-04-16 18:01:16'),
(379, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/login.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'tbd1t4a2v01dlj9rlroobljgta', '2026-04-16 18:04:40'),
(380, 32, 'http://localhost/bii_localfinder/client/dashboard.php', 'Dashboard — BII LocalFinder', 'http://localhost/bii_localfinder/client/booking-details.php?id=43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '::1', 'tbd1t4a2v01dlj9rlroobljgta', '2026-04-16 18:44:40');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'RWF',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_provider` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','success','failed','refunded') DEFAULT 'pending',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `user_id`, `provider_id`, `amount`, `currency`, `payment_method`, `payment_provider`, `transaction_id`, `status`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 42, 32, 12, 20000.00, 'RWF', NULL, 'fake', 'SIM_1776356378_4837', 'success', '{\"transaction_id\":\"SIM_1776356378_4837\",\"amount\":\"20000.00\",\"currency\":\"RWF\",\"processed_at\":\"2026-04-16 18:19:38\"}', '2026-04-16 16:19:28', '2026-04-16 16:19:38'),
(2, 43, 32, 12, 20000.00, 'RWF', NULL, 'fake', NULL, 'pending', '{\"booking_created_at\":\"2026-04-16 11:02:21\",\"service_description\":\"tsrhtershersttttttttttttrrrrrrrrrrrs\"}', '2026-04-16 18:02:45', '2026-04-16 18:02:45');

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
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `service_limit` int(11) NOT NULL DEFAULT 3,
  `photo_limit` int(11) NOT NULL DEFAULT 3,
  `analytics_level` enum('none','basic','better','advanced') NOT NULL DEFAULT 'basic',
  `ai_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `ai_title_suggestion` tinyint(1) NOT NULL DEFAULT 0,
  `ai_description_generator` tinyint(1) NOT NULL DEFAULT 0,
  `ai_pricing_recommendation` tinyint(1) NOT NULL DEFAULT 0,
  `ranking_boost_days` int(11) NOT NULL DEFAULT 14,
  `priority_ranking` tinyint(1) NOT NULL DEFAULT 0,
  `higher_search_ranking` tinyint(1) NOT NULL DEFAULT 0,
  `verified_badge_request` tinyint(1) NOT NULL DEFAULT 0,
  `faster_payout` tinyint(1) NOT NULL DEFAULT 0,
  `instant_payout_priority` tinyint(1) NOT NULL DEFAULT 0,
  `basic_lead_insight` tinyint(1) NOT NULL DEFAULT 0,
  `customer_repeat_insight` tinyint(1) NOT NULL DEFAULT 0,
  `export_reports` tinyint(1) NOT NULL DEFAULT 0,
  `boost_any_time` tinyint(1) NOT NULL DEFAULT 0,
  `boost_fair_limit` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `price`, `billing_cycle`, `service_limit`, `photo_limit`, `analytics_level`, `ai_enabled`, `ai_title_suggestion`, `ai_description_generator`, `ai_pricing_recommendation`, `ranking_boost_days`, `priority_ranking`, `higher_search_ranking`, `verified_badge_request`, `faster_payout`, `instant_payout_priority`, `basic_lead_insight`, `customer_repeat_insight`, `export_reports`, `boost_any_time`, `boost_fair_limit`, `created_at`, `updated_at`) VALUES
(1, 'Free', 0.00, 'monthly', 3, 3, 'basic', 0, 0, 0, 0, 14, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-04-18 13:52:48', '2026-04-18 13:52:48'),
(2, 'Standard', 4890.00, 'monthly', 15, 7, 'better', 0, 1, 0, 0, 30, 1, 0, 1, 1, 0, 1, 0, 0, 1, 7, '2026-04-18 13:52:48', '2026-04-18 13:52:48'),
(3, 'Pro', 14960.00, 'monthly', 0, 0, 'advanced', 1, 1, 1, 1, 999, 1, 1, 1, 1, 1, 1, 1, 1, 1, 30, '2026-04-18 13:52:48', '2026-04-18 13:52:48');

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
(1, 12, 'mobile_money', 'Dushime Gentil', '07889799765', '', 0, 1, '2025-12-30 21:02:54', '2025-12-30 21:02:54'),
(2, 12, 'mobile_money', 'Dushime Gentil', '07889799765', '', 0, 1, '2026-04-12 21:13:33', '2026-04-12 21:13:33');

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
  `optional_extras` text DEFAULT NULL,
  `availability_days` text DEFAULT NULL COMMENT 'Comma-separated weekdays available for this service',
  `time_slots` text DEFAULT NULL COMMENT 'JSON encoded time slots for the service',
  `booking_mode` enum('request_approval','instant') NOT NULL DEFAULT 'request_approval',
  `service_status` enum('draft','published','paused') NOT NULL DEFAULT 'draft',
  `service_image` varchar(255) DEFAULT NULL COMMENT 'Main service image path',
  `service_images` text DEFAULT NULL COMMENT 'JSON encoded array of additional service image paths',
  `image_alt_text` varchar(255) DEFAULT NULL COMMENT 'Alt text for main service image'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `provider_services`
--

INSERT INTO `provider_services` (`id`, `provider_id`, `category_id`, `name`, `is_available`, `is_featured`, `price`, `description`, `duration`, `created_at`, `updated_at`, `payment_type`, `min_price`, `max_price`, `negotiable`, `base_price`, `optional_extras`, `availability_days`, `time_slots`, `booking_mode`, `service_status`, `service_image`, `service_images`, `image_alt_text`) VALUES
(27, 13, 5, 'Make a windows', 1, 0, 4000.00, 'To made the window is based on the size but by default the price is 4000', 195, '2025-11-28 09:47:04', '2025-11-28 09:49:28', 'per_service', NULL, NULL, 0, NULL, NULL, NULL, NULL, 'request_approval', 'draft', NULL, NULL, NULL),
(47, 12, 9, 'Personal Driver (Daily Transport)', 1, 0, 20000.00, 'Daily personal driving service for errands, work transport, and general movement within the city. Includes safe driving, punctuality, and route planning.', 45, '2025-12-04 19:02:14', '2026-04-10 09:29:04', 'per_day', NULL, NULL, 0, 20000.00, NULL, '1,2,3,4,5', NULL, 'request_approval', 'published', NULL, NULL, NULL),
(48, 12, 9, 'Airport Pickup &amp;amp; Drop-off Driver', 1, 0, 15000.00, 'Professional driver for airport pickups or drop-offs. Includes luggage assistance, time management, and safe travel to/from the airport.', 90, '2025-12-04 19:06:47', '2026-04-03 19:07:42', 'per_service', 40000.00, 80000.00, 1, NULL, NULL, NULL, NULL, 'request_approval', 'published', NULL, NULL, NULL),
(49, 6, 4, 'Kumena amavuta', 1, 0, 4000.00, 'tumwejifbhysifheriheriherithiet4iwt', 60, '2025-12-13 17:50:08', '2025-12-13 17:50:08', 'per_service', NULL, NULL, 0, NULL, NULL, NULL, NULL, 'request_approval', 'draft', NULL, NULL, NULL),
(50, 12, 9, 'Traveling with Tourism', 1, 0, 500000.00, 'I make the travel and visit with tourism in whole country and all National Park and other areas in Rwanda like Lake kivu and other diffrences place', 60, '2026-04-09 20:20:57', '2026-04-09 20:20:57', 'fixed_price', 500000.00, 10000000.00, 1, 500000.00, NULL, '1,2,3,4,5,6', NULL, 'request_approval', 'published', NULL, NULL, NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provider_settings`
--

INSERT INTO `provider_settings` (`id`, `provider_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 12, 'security_enable_2fa', '0', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(2, 12, 'security_login_alerts', '1', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(3, 12, 'security_emergency_contact', '', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(4, 12, 'security_panic_button_enabled', '1', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(5, 12, 'security_report_abusive_clients', '1', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(6, 12, 'security_job_cancellation_protection', '1', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(7, 12, 'security_session_timeout', '90', '2025-12-15 20:05:15', '2026-04-14 22:01:17'),
(15, 12, 'visibility_show_phone', '0', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(16, 12, 'visibility_show_whatsapp', '0', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(17, 12, 'visibility_show_exact_location', '0', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(18, 12, 'visibility_profile_public', '1', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(19, 12, 'visibility_appear_in_search', '1', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(20, 12, 'visibility_appear_available', '1', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(21, 12, 'visibility_emergency_service', '1', '2025-12-30 20:57:51', '2026-04-14 22:01:17'),
(22, 12, 'visibility_night_service', '0', '2025-12-30 20:57:52', '2026-04-14 22:01:17'),
(23, 12, 'visibility_weekend_service', '1', '2025-12-30 20:57:52', '2026-04-14 22:01:17'),
(24, 12, 'visibility_badge_verified', '1', '2025-12-30 20:57:52', '2026-04-14 22:01:17'),
(25, 12, 'visibility_badge_top_rated', '1', '2025-12-30 20:57:52', '2026-04-14 22:01:17'),
(26, 12, 'visibility_badge_fast_responder', '1', '2025-12-30 20:57:52', '2026-04-14 22:01:17'),
(27, 12, 'payment_payment_methods', 'cash,mobile_money', '2025-12-30 21:02:54', '2026-04-14 22:01:17'),
(28, 12, 'payment_accept_cash', '0', '2025-12-30 21:02:54', '2026-04-14 22:01:17'),
(29, 12, 'payment_accept_mobile_money', '0', '2025-12-30 21:02:54', '2026-04-14 22:01:17'),
(30, 12, 'payment_accept_wallet', '0', '2025-12-30 21:02:54', '2026-04-14 22:01:17'),
(31, 12, 'payment_pay_after_service', '0', '2025-12-30 21:02:54', '2026-04-14 22:01:17'),
(32, 12, 'payment_commission_transparency', '1', '2025-12-30 21:02:54', '2026-04-14 22:01:17'),
(33, 12, 'communication_preferred_language', 'en', '2025-12-30 22:14:32', '2026-04-14 22:01:17'),
(60, 12, 'location_travel_fee_per_km', '0', '2026-03-12 20:32:52', '2026-04-14 22:01:17'),
(61, 12, 'location_max_travel_distance', '16', '2026-03-12 20:32:52', '2026-04-14 22:01:17'),
(62, 12, 'location_map_accuracy', 'approximate', '2026-03-12 20:32:52', '2026-04-14 22:01:17'),
(63, 12, 'location_service_radius', '10', '2026-03-12 20:32:52', '2026-04-14 22:01:17'),
(64, 12, 'location_multiple_areas', '0', '2026-03-12 20:32:52', '2026-04-14 22:01:17'),
(69, 12, 'ai_enable_ai_assistant', '1', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(70, 12, 'ai_ai_auto_reply', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(71, 12, 'ai_ai_description_improvement', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(72, 12, 'ai_ai_pricing_suggestions', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(73, 12, 'ai_ai_availability_optimization', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(74, 12, 'ai_ai_fraud_protection', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(75, 12, 'ai_ai_auto_select_service', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(76, 12, 'ai_ai_auto_schedule', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(77, 12, 'ai_ai_auto_quote', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(78, 12, 'ai_smart_booking_by_prompt', '0', '2026-04-10 18:27:21', '2026-04-14 22:01:17'),
(139, 12, 'ai_features_enable_ai_assistant', '1', '2026-04-11 08:44:48', '2026-04-14 22:25:33'),
(140, 12, 'ai_features_ai_auto_reply', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(141, 12, 'ai_features_ai_description_improvement', '1', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(142, 12, 'ai_features_ai_pricing_suggestions', '0', '2026-04-11 08:44:48', '2026-04-14 22:35:17'),
(143, 12, 'ai_features_ai_availability_optimization', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(144, 12, 'ai_features_ai_fraud_protection', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(145, 12, 'ai_features_ai_auto_select_service', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(146, 12, 'ai_features_ai_auto_schedule', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(147, 12, 'ai_features_ai_auto_quote', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(148, 12, 'ai_features_smart_booking_by_prompt', '0', '2026-04-11 08:44:48', '2026-04-14 22:01:17'),
(203, 12, 'notifications_new_booking_email', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(204, 12, 'notifications_new_booking_sms', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(205, 12, 'notifications_new_booking_push', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(206, 12, 'notifications_chat_message_email', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(207, 12, 'notifications_chat_message_sms', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(208, 12, 'notifications_chat_message_push', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(209, 12, 'notifications_payment_received_email', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(210, 12, 'notifications_payment_received_sms', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(211, 12, 'notifications_review_received_email', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(212, 12, 'notifications_review_received_sms', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(213, 12, 'notifications_review_received_push', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(214, 12, 'notifications_system_announcements_email', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(215, 12, 'notifications_system_announcements_sms', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(216, 12, 'notifications_marketing_email', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01'),
(217, 12, 'notifications_marketing_sms', '1', '2026-04-18 12:05:01', '2026-04-18 12:05:01');

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
-- Table structure for table `provider_subscriptions`
--

CREATE TABLE `provider_subscriptions` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled','grace_period') NOT NULL DEFAULT 'active',
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `grace_until` date DEFAULT NULL,
  `last_boost_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `admin_promotion_boost` int(11) NOT NULL DEFAULT 0,
  `admin_priority_level` tinyint(1) NOT NULL DEFAULT 0,
  `admin_score_override` int(11) DEFAULT NULL,
  `admin_ranking_score` int(11) NOT NULL DEFAULT 0,
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
  `can_receive_jobs` tinyint(1) DEFAULT 1 COMMENT 'Whether provider can receive new jobs',
  `avg_response_time_minutes` int(11) DEFAULT NULL,
  `completion_rate` decimal(5,4) DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `system_ranking_score` int(11) NOT NULL DEFAULT 0,
  `final_score` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `service_providers`
--

INSERT INTO `service_providers` (`id`, `user_id`, `is_verified`, `profession`, `bio`, `experience_years`, `location`, `district`, `sector`, `latitude`, `longitude`, `availability`, `hourly_rate`, `is_active`, `status`, `is_banned`, `is_featured`, `featured_until`, `search_boost`, `admin_promotion_boost`, `admin_priority_level`, `admin_score_override`, `admin_ranking_score`, `verification_level`, `average_rating`, `total_reviews`, `total_jobs`, `created_at`, `updated_at`, `ban_reason`, `is_premium`, `working_days`, `working_hours_start`, `working_hours_end`, `break_start`, `break_end`, `slot_duration`, `buffer_time`, `max_daily_bookings`, `booking_lead_time`, `cancellation_cutoff`, `portfolio_enabled`, `max_portfolio_images`, `website`, `facebook`, `twitter`, `instagram`, `linkedin`, `youtube`, `whatsapp`, `tiktok`, `other_social`, `other_social_label`, `commission_rate`, `subscription_plan`, `can_receive_jobs`, `avg_response_time_minutes`, `completion_rate`, `last_active`, `is_online`, `system_ranking_score`, `final_score`) VALUES
(4, 15, 0, 'Plumber', 'I am proffesional plumber who has the certificate and I was teacher in the Hope international school', 3, 'Rubavu', 'Rusizi', '', NULL, NULL, '', 4000.00, 1, 'active', 0, 0, NULL, 0, 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-24 12:48:56', NULL, NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1, NULL, NULL, NULL, 0, 0, 0),
(6, 18, 0, 'Mechanic', 'I am studied Automobile engneering 4 a years in USA, I has the experience and large team we commit together. Professional Mechanic, reliable, professional.', 4, 'Musanze', 'Musanze', 'Ruhengeri', NULL, NULL, 'available', 0.00, 1, 'active', 0, 0, NULL, 0, 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-24 12:48:56', '2025-12-13 09:48:22', NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1, NULL, NULL, NULL, 0, 0, 0),
(12, 26, 0, 'Driver', 'I am the proffesional Professional Mason, skilled, licensed. with 4 years of experience, reliable, certified. Professional Driver.', 4, 'Huye', 'Kayonza', 'cyamata', NULL, NULL, 'available', 4500.00, 1, 'active', 0, 1, '2026-01-09 08:23:00', 95, 0, 0, NULL, 0, '', 3.50, 2, 5, '2025-11-27 07:28:01', '2026-04-01 14:08:43', NULL, 0, '1,2,3,4,5,6,7', '07:00:00', '14:00:00', '00:00:00', '00:00:00', 30, 15, 3, 24, 12, 1, 6, '', 'https://web.facebook.com/biicrow', '', 'https://www.instagram.com/gentil015/', '', '', '+250795946213', '', '', '', 10.00, 'free', 1, NULL, NULL, NULL, 0, 0, 0),
(13, 27, 0, 'Carpenter', '', 1, 'Muhanga', 'Muhanga', 'bisizi', NULL, NULL, 'available', 2000.00, 1, 'active', 0, 0, '2025-11-30 13:21:00', 0, 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-27 12:51:23', '2025-11-28 01:44:53', NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1, NULL, NULL, NULL, 0, 0, 0),
(14, 28, 0, 'Driver', '', NULL, 'Nyabihu', 'Gicumbi', 'bisigo', NULL, NULL, 'available', NULL, 1, 'active', 0, 0, NULL, 0, 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-27 12:53:01', '2025-11-27 12:55:54', NULL, 0, '1,2,3,4,5', '08:00:00', '17:00:00', NULL, NULL, 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1, NULL, NULL, NULL, 0, 0, 0),
(15, 29, 0, 'Plumber', '', NULL, 'Bugesera', 'Bugesera', 'hugwe', NULL, NULL, 'available', NULL, 1, 'active', 0, 1, NULL, 0, 0, 0, NULL, 0, 'none', 0.00, 0, 0, '2025-11-27 12:54:38', '2025-12-13 10:38:21', NULL, 0, '1,2,3,4,5,6,7', '08:00:00', '17:00:00', '00:00:00', '00:00:00', 30, 15, 8, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 'free', 1, NULL, NULL, NULL, 0, 0, 0),
(16, 30, 0, 'Driver', 'I am the driver and I has the experience. reliable, professional, skilled. certified, licensed.', 3, 'Rubavu', 'Rubavu', 'Kanama', NULL, NULL, 'available', 3000.00, 1, 'active', 0, 0, NULL, 0, 0, 0, NULL, 0, 'none', 2.00, 1, 1, '2025-11-29 04:26:23', '2025-12-18 06:11:53', NULL, 0, '', '08:00:00', '17:00:00', '00:00:00', '00:00:00', 30, 15, 5, 24, 12, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10.00, '', 1, NULL, NULL, NULL, 0, 0, 0);

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
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'FRW',
  `payment_method` varchar(50) NOT NULL DEFAULT 'momo',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `provider_id`, `subscription_id`, `amount`, `currency`, `payment_method`, `transaction_ref`, `status`, `created_at`, `updated_at`) VALUES
(1, 26, 2, 4890.00, 'FRW', 'momo', 'SUB_1776517551_8947', 'paid', '2026-04-18 13:05:51', '2026-04-18 13:05:51');

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
(12, 'maintenance_mode', '0', '2025-11-24 18:27:54', '2026-04-11 19:22:57'),
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
(292, 'sms_api_url', '', '2026-03-20 06:56:56', '2026-03-20 06:56:56'),
(305, 'payment_enabled', '1', '2026-04-15 20:07:19', '2026-04-15 20:07:19'),
(306, 'default_gateway', 'fake', '2026-04-15 20:07:19', '2026-04-15 20:07:19'),
(307, 'mtn_api_key', '', '2026-04-15 20:07:19', '2026-04-15 20:07:19'),
(308, 'stripe_api_key', '', '2026-04-15 20:07:19', '2026-04-15 20:07:19'),
(309, 'visa_merchant_id', '', '2026-04-15 20:07:19', '2026-04-15 20:07:19');

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
(19, 'Administrator', 'admin@localfinder.com', '0712345678', '$2y$10$yC0Gk5aPWJo.JPq2n8Mnle5iQA1y56vrxtSPIu2Tr0U.SmvFiWozu', 'admin', 1, 1, 1, 1, 'active', '2026-04-11 09:58:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-24 20:58:48', '2026-04-11 16:58:56', NULL, NULL, 0, 1, NULL),
(26, 'Dushime Gentil', 'dushimegentil0@gmail.com', '+25075946213', '$2y$10$FeiPY8QgezUyKPFClesEe.Q3290JvNWaIvw85HJsVpsulrer7khw6', 'provider', 1, 0, 0, 1, 'active', '2026-04-18 07:04:46', '2025-12-15 12:05:15', NULL, NULL, NULL, NULL, NULL, 'profile_26_1764790963.jpg', '2025-11-27 15:28:01', '2026-04-18 14:04:46', NULL, NULL, 0, 1, NULL),
(27, 'Ngabo Aime', 'ngaboaime@gmail.com', '0795930482', '$2y$10$VONKiH6iHMhgXBeweXGJHeFxSLySEXQmb11y9DpKzUDEjXVBdJPKe', 'provider', 1, 0, 0, 1, 'active', '2025-11-28 01:42:45', NULL, NULL, NULL, NULL, NULL, NULL, 'profile_27_1764277379.jpg', '2025-11-27 20:51:23', '2025-11-28 09:44:53', '726497', '2025-11-27 22:01:23', 0, 1, NULL),
(28, 'Kevin Mugisha', 'mugishakevin@gmail.com', '+2507948927349', '$2y$10$bAKfPqcZvcM7Emu1bsRWk.blYvvysRjEr9F9l91XSjogXnqAAdED2', 'provider', 1, 0, 0, 1, 'active', '2025-11-27 13:47:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 20:53:01', '2025-11-27 21:47:24', '984236', '2025-11-27 22:03:01', 0, 1, NULL),
(29, 'Adrien migabo', 'adrienmigabo@gmail.com', '0783937989', '$2y$10$pUikfrD3cFMUw9EnKirTjuh/BwqOIAkb1H60OgGtLi7Zq8dJZGmh6', 'provider', 1, 0, 0, 1, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-27 20:54:38', '2025-11-27 20:55:49', '933410', '2025-11-27 22:04:38', 0, 1, NULL),
(30, 'Kevin mugisha', 'kevinmugisha354@gmail.com', '+250795946213', '$2y$10$bES98RTdKieFyNNMRrcqwegwg3/B31YRg/swjsuVBx57MLEui7/ou', 'provider', 1, 0, 0, 1, 'active', '2025-12-17 23:59:58', NULL, NULL, NULL, NULL, NULL, NULL, 'profile_30_1764621887.jpg', '2025-11-29 12:26:23', '2025-12-18 07:59:58', NULL, NULL, 0, 1, NULL),
(31, 'David Gakuba', 'technogystore@gmail.com', '+250795946213', '$2y$10$V03uXzM0r2aNr52ZG5CzkO46WpUB7Ng.NPh7Q8zWTikPYsQKZwaTu', 'client', 1, 0, 0, 1, 'active', '2026-03-27 06:32:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-01 17:57:23', '2026-03-27 13:32:18', NULL, NULL, 0, 1, NULL),
(32, 'Mukundwa Aime', 'tuyizereaimely@gmail.com', '+250795946213', '$2y$10$8bK7Xp71icAO5YjNxSR/XeKb5tlHjnbIRT2uWC2Dv3VuMwj/RGoQO', 'client', 1, 0, 0, 1, 'active', '2026-04-16 11:04:39', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-17 17:22:25', '2026-04-16 18:04:39', NULL, NULL, 0, 1, NULL),
(33, 'ELie', 'biitechnology0@gmail.com', '+250795946213', '$2y$10$2JXLkcGSiQBTYvu8hU7CMepoP3wDzFMDqsM1tanSsfgojK54eAPVm', 'client', 0, 0, 0, 1, 'active', '2025-12-18 00:36:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-18 08:23:26', '2025-12-18 08:46:53', NULL, NULL, 0, 1, NULL),
(34, 'Test User', 'test@example.com', '', '\\/IGYLd.LQ5J4PGS', 'client', 1, 0, 0, 1, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-21 17:39:39', '2026-03-21 17:51:25', NULL, NULL, 0, 1, NULL),
(36, 'Samy Bruno', 'samybruno900@gmail.com', '75946213', '$2y$10$8h15CL9mIR59XsUWkzurUeIiLsaHrM7u8A9imZSDfsuuX9SDHeHG6', 'client', 1, 0, 0, 1, 'active', '2026-03-30 15:51:11', NULL, NULL, NULL, NULL, NULL, NULL, 'profile_1774798279_e19897ec7bec.png', '2026-03-29 15:31:19', '2026-03-30 22:51:11', NULL, NULL, 0, 1, NULL);

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
(13, 33, 'review_updated', 'Updated review for provider #12', NULL, NULL, '2025-12-18 08:38:13'),
(14, 32, 'booking_cancelled', 'Cancelled booking #38 - Reason: changed mind', NULL, NULL, '2026-03-30 22:23:58'),
(15, 32, 'payment_attempt', 'Payment successful for payment ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:19:38');

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
(215, 32, 'unknown', '2026-03-26 07:21:24', 379, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:21:24'),
(216, 32, 'unknown', '2026-03-27 06:28:37', 22, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 13:28:37'),
(217, 31, 'unknown', '2026-03-27 06:32:48', 30, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 13:32:48'),
(218, 19, 'unknown', '2026-03-29 08:29:55', 44, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:29:55'),
(219, 36, 'unknown', '2026-03-29 08:42:41', 539, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:42:41'),
(220, 32, 'unknown', '2026-03-29 08:56:57', 844, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:56:57'),
(221, 26, 'unknown', '2026-03-29 09:12:12', 277, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:12:12'),
(222, 32, 'unknown', '2026-03-29 09:20:28', 480, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:20:28'),
(223, 26, 'unknown', '2026-03-29 09:48:49', 1691, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:48:49'),
(224, 32, 'unknown', '2026-03-29 09:55:07', 361, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:55:07'),
(225, 26, 'unknown', '2026-03-29 09:55:37', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:55:37'),
(226, 32, 'unknown', '2026-03-29 10:08:36', 768, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:08:36'),
(227, 26, 'unknown', '2026-03-29 10:20:39', 712, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:20:39'),
(228, 26, 'unknown', '2026-03-30 02:28:22', 1349, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 09:28:22'),
(229, 32, 'unknown', '2026-03-30 03:05:27', 2190, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:05:27'),
(230, 32, 'unknown', '2026-03-30 15:24:19', 1738, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 22:24:19'),
(231, 26, 'unknown', '2026-03-30 15:49:56', 1508, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 22:49:56'),
(232, 32, 'unknown', '2026-04-01 04:20:10', 2139, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 11:20:10'),
(233, 32, 'unknown', '2026-04-01 07:40:13', 681, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:40:13'),
(234, 26, 'unknown', '2026-04-01 07:42:09', 77, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:42:09'),
(235, 32, 'unknown', '2026-04-01 07:43:22', 53, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:43:22'),
(236, 32, 'unknown', '2026-04-01 14:05:55', 408, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 21:05:55'),
(237, 19, 'unknown', '2026-04-01 14:08:59', 172, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 21:08:59'),
(238, 32, 'unknown', '2026-04-01 14:40:51', 1891, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 21:40:51'),
(239, 32, 'unknown', '2026-04-03 07:26:21', 712, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:26:21'),
(240, 32, 'unknown', '2026-04-03 10:18:39', 2377, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:18:39'),
(241, 26, 'unknown', '2026-04-03 10:27:50', 504, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:27:50'),
(242, 32, 'unknown', '2026-04-03 10:57:47', 1767, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:57:47'),
(243, 26, 'unknown', '2026-04-03 12:57:55', 3487, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:57:55'),
(244, 19, 'unknown', '2026-04-03 17:11:09', 85, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 00:11:09'),
(245, 32, 'unknown', '2026-04-09 12:48:07', 1883, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 19:48:07'),
(246, 26, 'unknown', '2026-04-09 14:59:10', 2098, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 21:59:10'),
(247, 26, 'unknown', '2026-04-11 09:17:24', 902, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:17:24'),
(248, 26, 'unknown', '2026-04-11 09:20:40', 108, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:20:40'),
(249, 19, 'unknown', '2026-04-11 09:33:14', 736, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:33:14'),
(250, 19, 'unknown', '2026-04-11 09:57:30', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:57:30'),
(251, 19, 'unknown', '2026-04-11 10:00:34', 98, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 17:00:34'),
(252, 19, 'unknown', '2026-04-11 11:34:51', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 18:34:51'),
(253, 26, 'unknown', '2026-04-11 12:09:53', 565, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 19:09:53'),
(254, 19, 'unknown', '2026-04-11 12:19:33', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 19:19:33'),
(255, 26, 'unknown', '2026-04-12 14:21:53', 882, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 21:21:53'),
(256, 26, 'unknown', '2026-04-13 10:05:09', 2191, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:05:09'),
(257, 32, 'unknown', '2026-04-13 10:06:25', 62, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:06:25'),
(258, 26, 'unknown', '2026-04-13 10:21:58', 904, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:21:58');
INSERT INTO `user_logout_logs` (`id`, `user_id`, `user_type`, `logout_time`, `session_duration`, `ip_address`, `user_agent`, `created_at`) VALUES
(259, 32, 'unknown', '2026-04-13 10:23:17', 66, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:23:17'),
(260, 19, 'unknown', '2026-04-13 13:04:08', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 20:04:08'),
(261, 26, 'unknown', '2026-04-15 09:49:28', 953, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 16:49:28'),
(262, 26, 'unknown', '2026-04-15 13:40:22', 1440, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 20:40:22'),
(263, 32, 'unknown', '2026-04-15 15:52:51', 2455, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:52:51'),
(264, 32, 'unknown', '2026-04-16 08:50:47', 90, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 15:50:47'),
(265, 26, 'unknown', '2026-04-16 09:02:02', 637, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:02:02'),
(266, 26, 'unknown', '2026-04-16 09:04:01', 104, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:04:01'),
(267, 32, 'unknown', '2026-04-16 09:07:08', 149, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:07:08'),
(268, 26, 'unknown', '2026-04-16 09:12:45', 299, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:12:45'),
(269, 32, 'unknown', '2026-04-16 09:17:15', 247, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:17:15'),
(270, 26, 'unknown', '2026-04-16 09:18:20', 26, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:18:20'),
(271, 32, 'unknown', '2026-04-16 11:03:16', 364, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 18:03:16'),
(272, 26, 'unknown', '2026-04-16 11:04:16', 39, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 18:04:16'),
(273, 19, 'unknown', '2026-04-18 06:01:36', 3456, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 13:01:36');

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
(34, 0.00, 24, 0, '2026-03-27 08:27:00'),
(36, 0.00, 24, 0, '2026-03-29 15:31:19');

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
(7, 32, 'puh57rk2g1r9ko38jf8dqfjtq3', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:33:05', NULL, 1),
(8, 32, 'btnlq8grs8slathsa8e5ks2npu', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 13:28:15', '2026-03-27 13:28:37', 0),
(9, 31, '5uvb7hia0n1kfn1nmmfg8gqs0b', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 13:32:18', '2026-03-27 13:32:48', 0),
(10, 19, '9fvhkiq684uuclvbk88c8atrv2', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:29:12', '2026-03-29 15:29:55', 0),
(11, 36, 'p0bd2d5720jvd957tr82rrr454', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:33:42', '2026-03-29 15:42:41', 0),
(12, 32, 'p2ln2p2t74hjah8f5avaqaip98', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:42:53', '2026-03-29 15:56:57', 0),
(13, 26, 'tu4o484g3jr96n61430p31ulu0', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:07:35', '2026-03-29 16:12:12', 0),
(14, 32, 'llkdkvu9nfsm89l0sdv5cc9lel', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:12:28', '2026-03-29 16:20:28', 0),
(15, 26, '0h2l2chrhocadvkns8jjk8pric', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:20:38', '2026-03-29 16:48:49', 0),
(16, 32, 'fsnc2h6k9s527fa03fo3s6imss', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:49:06', '2026-03-29 16:55:07', 0),
(17, 26, '5qou2scvj29aa2ptsgflqhrqo5', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:55:18', '2026-03-29 16:55:38', 0),
(18, 32, 'f19a115c76sobv08p923susvhk', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 16:55:48', '2026-03-29 17:08:36', 0),
(19, 26, 'i7u6v9cfbgk9pcf885eclrfagu', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:08:47', '2026-03-29 17:20:39', 0),
(20, 26, 'tc4pffc1p0bcq0smrg4isp6gir', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:20:53', NULL, 1),
(21, 32, 'f373nmt3t2j2ffe6tnuaaddra6', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 07:45:57', NULL, 1),
(22, 26, 'fthopo3t4j9t07d97t70lrfgro', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 09:05:53', '2026-03-30 09:28:22', 0),
(23, 32, 'l3f99n8edgs58ijnrjs6qprkr4', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 09:28:57', '2026-03-30 10:05:27', 0),
(24, 26, '0vgi472hv6hb6u1erb8dcjfrbd', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:05:52', NULL, 1),
(25, 32, 'deuqpu4if92v105383qr0lbdkb', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 20:54:08', NULL, 1),
(26, 32, '6am9smbio683o1jagofv1g3leq', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 21:55:21', '2026-03-30 22:24:19', 0),
(27, 26, 'ec0u28fn9dgcb9dgqn12610kht', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 22:24:48', '2026-03-30 22:49:56', 0),
(28, 36, '5r5op3l1h59nntenhv12r1ug27', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 22:51:10', NULL, 1),
(29, 26, 'kef9dtp6r31kosap85lq2i98hs', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 20:26:46', NULL, 1),
(30, 32, '482gnkvrofpgab2jof787me1md', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 22:06:17', NULL, 1),
(31, 32, 'gq23gp4f7kkprnvvmvsjcuqsk2', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 09:41:44', NULL, 1),
(32, 32, 'og0tq3ltp69i2uva350kopglds', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 10:44:31', '2026-04-01 11:20:10', 0),
(33, 19, 'k834ijrlbvegnnsm7ecdf6jfik', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 11:20:23', NULL, 1),
(34, 32, '4vm6fkleg3o2ri80bhbp7peq3n', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:28:52', '2026-04-01 14:40:13', 0),
(35, 26, 'l2smae5oao5rmve5o4o1ip1osn', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:40:52', '2026-04-01 14:42:09', 0),
(36, 32, 'eooaiij09kobtmcev2cu85bsjp', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:42:29', '2026-04-01 14:43:22', 0),
(37, 26, '0sjn7qeue927jn2rj8pektpdgh', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:43:35', NULL, 1),
(38, 32, 'v5pv03vck8rmfegi4irl0fu505', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 19:50:35', NULL, 1),
(39, 32, '068v7mf0tmt3qick57e5b12bmo', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 20:59:07', '2026-04-01 21:05:55', 0),
(40, 19, 'okgoqmoef05c9frstie8ft0qni', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 21:06:07', '2026-04-01 21:08:59', 0),
(41, 32, 'rfebqhsns022kic0k2guolsh6l', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 21:09:20', '2026-04-01 21:40:51', 0),
(42, 32, 'qs2u070r6gadetjrfq4ibquch0', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 21:42:08', NULL, 1),
(43, 32, 'el1dtkd40ltmoq49u8cd971h3t', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:21:16', NULL, 1),
(44, 32, 'ebja1i9886b9a52bka9grcojon', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:14:29', '2026-04-03 14:26:21', 0),
(45, 32, 'rd0upg03d6ov3a6v4u8lmn7k5g', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:26:33', NULL, 1),
(46, 32, '9do680060n5vl4131prcglr2iu', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 15:27:08', NULL, 1),
(47, 32, 'h9cb31qihr6jhahg7k8666ba7l', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 16:39:02', '2026-04-03 17:18:39', 0),
(48, 26, 'ebj24o9vusejt1gsdg8b5vjve9', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:19:26', '2026-04-03 17:27:52', 0),
(49, 32, '2k354vrl37vnnem4uk8e0u4tua', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:28:20', '2026-04-03 17:57:47', 0),
(50, 26, '0aju6nuc67dtukfgfumijsb1e0', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:58:04', NULL, 1),
(51, 26, '4heqse9u369lvu2uq1iierug1m', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:59:48', '2026-04-03 19:57:55', 0),
(52, 32, '56g41ok0ds94ggl18veotq3qvv', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:58:09', NULL, 1),
(53, 32, 'bnk1eb554uat135p1n0ok4pn48', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 22:27:44', NULL, 1),
(54, 19, '8a8olno8qiaip1p7m4siqcndip', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 00:09:44', '2026-04-04 00:11:09', 0),
(55, 32, 'sdkvgh2utp5c0ql5upiec4l4td', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 00:11:47', NULL, 1),
(56, 32, 'kpk9s5pnhvkq65ot6vivif9vaq', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 11:09:19', NULL, 1),
(57, 32, 'rqqb5ndto5m5uh602ahjpdtsbt', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 18:14:39', NULL, 1),
(58, 32, '9gp3b3n1cbugnfjti9sg7sqp6j', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 19:36:25', NULL, 1),
(59, 32, 'pk9orqc955k6g9rjb6she06cve', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 19:16:44', '2026-04-09 19:48:07', 0),
(60, 26, 'djod753d1fekvunm7tlsffmdni', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 19:50:37', NULL, 1),
(61, 26, 'sqg3h671o6vudbgtlvuk7em12s', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 21:24:12', '2026-04-09 21:59:10', 0),
(62, 32, '6674ktb3tjvq1f21r2gvjm0nla', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 21:59:34', NULL, 1),
(63, 26, 'llnbghuftlqkau8c45khqpbiri', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 08:02:13', NULL, 1),
(64, 26, '3336fcgmkro8ksb8sk7norlc4h', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 09:02:43', NULL, 1),
(65, 26, 'h3nlbpn7dv1q3qslmi2n3ktu7v', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 13:45:20', NULL, 1),
(66, 26, 'p73cs1q47uq16q19ha5ooq8usq', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 14:52:11', NULL, 1),
(67, 26, 'vfhb56ud1rfn65kvbpg2k4i23e', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 18:20:15', NULL, 1),
(68, 26, 'kqrecrko5rst7ciaf21utvoeni', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 19:31:08', NULL, 1),
(69, 26, 'e5g95o0h8e5ssdbuer7dv6phs1', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 21:20:50', NULL, 1),
(70, 26, 'vqmpucias8mhgc5mv9ij7te0r7', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 07:53:25', NULL, 1),
(71, 26, 'f6qqin938ietfrphsulenvdi6o', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 09:07:18', NULL, 1),
(72, 26, 've1ajn8phmvtkd09b30psj385n', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:02:22', '2026-04-11 16:17:24', 0),
(73, 26, 'vjcr4aan7u3bpq43u81d497l8t', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:18:52', '2026-04-11 16:20:40', 0),
(74, 19, 'repj8cl00fs4hci32lhj1ic0ol', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:20:58', '2026-04-11 16:33:14', 0),
(75, 19, 'm0pm7rl2g1v1ktu3g566gler4c', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:57:21', '2026-04-11 16:57:30', 0),
(76, 19, 'obk436ofg02i0cv656l650g02s', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:58:56', '2026-04-11 17:00:34', 0),
(77, 26, '33o6mmii93u437r6tafn0en86o', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 19:00:28', '2026-04-11 19:09:54', 0),
(78, 26, 'e519msl73qki6fb4q1vtb14301', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 21:07:11', '2026-04-12 21:21:53', 0),
(79, 32, 'd8c49qhrs9jkricchm6l055sjk', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 21:22:14', NULL, 1),
(80, 26, 'm01eoosdrhb3pu4m96kobhp6hg', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 16:28:38', '2026-04-13 17:05:09', 0),
(81, 32, 'peedm25s306gnq5rngfjm39tt4', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:05:23', '2026-04-13 17:06:25', 0),
(82, 26, 's0e0vsjc0o0uvvfkgb71a2e9cr', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:06:54', '2026-04-13 17:21:59', 0),
(83, 32, 'gm6dub1up1jiv6unaf84q07431', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:22:11', '2026-04-13 17:23:17', 0),
(84, 26, 'iu567am3uagun3dpmkicsdlmpi', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 17:30:56', NULL, 1),
(85, 26, 'glp04uhvllnjcod20the4huhil', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 18:28:00', NULL, 1),
(86, 26, 'c7c3g3a9tuds8j2jtvplig7lh8', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 21:42:07', NULL, 1),
(87, 26, 'bahtgia9r2hk70v5j27gtej776', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 16:33:35', '2026-04-15 16:49:29', 0),
(88, 32, '65f9oiue9htmo3agh7p5u41t9f', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 16:49:59', NULL, 1),
(89, 32, '3qu5et7nl88dacebdkttj5ddvo', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 18:00:01', NULL, 1),
(90, 26, 'icqf9p9jjsq2pnir3vs9ps4js4', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 20:16:22', '2026-04-15 20:40:22', 0),
(91, 32, '8fgq2ategbp4cokr0vv0s6shvm', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 20:40:34', NULL, 1),
(92, 32, 'fd3vbnal20u239voga0sqrecun', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:11:56', '2026-04-15 22:52:51', 0),
(93, 32, 'rj5cd8als5f31l4s2bnv0qgi8d', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:53:10', NULL, 1),
(94, 32, 'h566gqvhskkh5vn7rfpqdrci8p', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 15:49:17', '2026-04-16 15:51:02', 0),
(95, 26, 'mt5elocjvncva9tbeua6vavsns', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 15:51:25', '2026-04-16 16:02:02', 0),
(96, 26, 'sgrmem3vq0o0c5ni4ge4a9qhcj', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:02:17', '2026-04-16 16:04:02', 0),
(97, 32, '2jpafaabp8t5rtuhvm2ig60p6j', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:04:39', '2026-04-16 16:07:09', 0),
(98, 26, 'ns34sjfj1grgp31me026k484qn', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:07:46', '2026-04-16 16:12:45', 0),
(99, 32, '4dgl51l7j84r98mv6bu3ae6hnm', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:13:08', '2026-04-16 16:17:15', 0),
(100, 26, 'tvruj65un126san2ju1b7m977e', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:17:54', '2026-04-16 16:18:20', 0),
(101, 32, '4kbhjpbnlnedh06t08p0mavle6', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 16:18:55', NULL, 1),
(102, 32, 'ubf97sgd2i2unl6637vluq2mbb', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 17:57:12', '2026-04-16 18:03:16', 0),
(103, 26, '0svipsu63ag7g7511qop1qtrrq', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 18:03:37', '2026-04-16 18:04:16', 0),
(104, 32, 'tbd1t4a2v01dlj9rlroobljgta', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-16 18:04:39', NULL, 1),
(105, 26, 'pjku80m2qc5j92q819togpvp93', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 09:40:06', NULL, 1),
(106, 26, '62eom628v5ejop80i7q164t51d', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 12:04:00', NULL, 1),
(107, 26, '1u43cr0qqoljc5frrifrk42cd1', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 13:01:58', NULL, 1),
(108, 26, 'en7h5jrkv8vkvd88oahi7eeq1l', 'Desktop', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 14:04:46', NULL, 1);

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
-- Indexes for table `booking_logs`
--
ALTER TABLE `booking_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

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
-- Indexes for table `live_locations`
--
ALTER TABLE `live_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_live_location_user_conversation` (`user_id`,`conversation_id`),
  ADD KEY `idx_live_location_conversation` (`conversation_id`);

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
-- Indexes for table `location_history`
--
ALTER TABLE `location_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location_history_conversation` (`conversation_id`),
  ADD KEY `idx_location_history_user` (`user_id`);

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
-- Indexes for table `ml_predictions_log`
--
ALTER TABLE `ml_predictions_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_created_at` (`created_at`);

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
-- Indexes for table `nlu_booking_classifications`
--
ALTER TABLE `nlu_booking_classifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_category` (`service_category`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `nlu_classifications`
--
ALTER TABLE `nlu_classifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_category` (`service_category`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `nlu_user_feedback`
--
ALTER TABLE `nlu_user_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_classification_id` (`classification_id`);

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
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_payment` (`booking_id`),
  ADD KEY `idx_payments_user_id` (`user_id`),
  ADD KEY `idx_payments_provider_id` (`provider_id`),
  ADD KEY `idx_payments_status` (`status`),
  ADD KEY `idx_payments_transaction_id` (`transaction_id`),
  ADD KEY `idx_payments_created_at` (`created_at`);

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
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

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
  ADD UNIQUE KEY `unique_provider_setting` (`provider_id`,`setting_key`),
  ADD UNIQUE KEY `unique_setting` (`provider_id`,`setting_key`);

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
-- Indexes for table `provider_subscriptions`
--
ALTER TABLE `provider_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_end_date` (`end_date`);

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
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_subscription_id` (`subscription_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transaction_ref` (`transaction_ref`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `booking_logs`
--
ALTER TABLE `booking_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
-- AUTO_INCREMENT for table `live_locations`
--
ALTER TABLE `live_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `location_coordinates`
--
ALTER TABLE `location_coordinates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `location_history`
--
ALTER TABLE `location_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_security`
--
ALTER TABLE `login_security`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `ml_interactions`
--
ALTER TABLE `ml_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ml_predictions_log`
--
ALTER TABLE `ml_predictions_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `nlu_booking_classifications`
--
ALTER TABLE `nlu_booking_classifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nlu_classifications`
--
ALTER TABLE `nlu_classifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nlu_user_feedback`
--
ALTER TABLE `nlu_user_feedback`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=688;

--
-- AUTO_INCREMENT for table `page_views`
--
ALTER TABLE `page_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=381;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `provider_performance`
--
ALTER TABLE `provider_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `provider_services`
--
ALTER TABLE `provider_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `provider_service_areas`
--
ALTER TABLE `provider_service_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `provider_settings`
--
ALTER TABLE `provider_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=218;

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
-- AUTO_INCREMENT for table `provider_subscriptions`
--
ALTER TABLE `provider_subscriptions`
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
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=315;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_logout_logs`
--
ALTER TABLE `user_logout_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=274;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

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
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `provider_subscriptions`
--
ALTER TABLE `provider_subscriptions`
  ADD CONSTRAINT `provider_subscriptions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`);

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
