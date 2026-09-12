-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 12, 2026 at 08:47 AM
-- Server version: 5.7.44-48
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wal7zkit_jollof`
--

-- --------------------------------------------------------

--
-- Table structure for table `addons`
--

CREATE TABLE `addons` (
  `akey` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `is_percent` tinyint(1) NOT NULL DEFAULT '0',
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addons`
--

INSERT INTO `addons` (`akey`, `name`, `price`, `is_percent`, `icon`, `note`, `active`, `sort_order`) VALUES
('carbon', 'Carbon offset', 2500.0000, 0, 'leaf', 'Verified offset program', 1, 5),
('exp', 'Signature experience bundle', 60000.0000, 0, 'spark', 'Boat cruise + private chef for two', 1, 6),
('housekeeping', 'Mid-stay housekeeping', 20000.0000, 0, 'broom', 'Once per 7 nights', 1, 2),
('insurance', 'Stay protection (3%)', 0.0300, 1, 'shield', 'Covers cancellations & damage', 1, 4),
('lateco', 'Late check-out · 2pm', 10000.0000, 0, 'clock', 'Subject to availability', 1, 3),
('transfer', 'Airport transfer', 25000.0000, 0, 'car', 'Vetted luxury SUV · both ways', 1, 0),
('welcome', 'Curated welcome basket', 15000.0000, 0, 'gift', 'Nigerian snacks, wine & a handwritten note', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `push_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `user_id`, `token_hash`, `device`, `platform`, `push_token`, `last_used_at`, `expires_at`, `revoked`, `created_at`) VALUES
(1, 18, '51b8357bef7c5ac72455e3bc249b6fdeb999cc31b36f92b740cff3d07df0f549', 'Android', 'android', NULL, '2026-09-06 00:26:55', '2026-12-05 00:26:18', 1, '2026-09-05 18:26:18'),
(2, 14, '081514d5b798f290bf33af160a5016acf16092576182e31599c2d88d1ba3973c', 'Android', 'android', NULL, '2026-09-06 12:15:25', '2026-12-05 12:13:14', 0, '2026-09-06 06:13:14'),
(3, 14, 'f974aec2196c0a76ef55407cfbab071cc98256d5bf7f1c368a3ed0fec769db32', 'Android', 'android', NULL, '2026-09-06 17:05:51', '2026-12-05 16:56:43', 1, '2026-09-06 10:56:43'),
(4, 17, '22813431e093ae253557cc6236c87a7f00af091a1ab46fe3f07e019652663c00', 'Android', 'android', NULL, '2026-09-07 20:40:09', '2026-12-05 17:16:20', 1, '2026-09-06 11:16:20'),
(5, 14, '3856ea88b52f6662724fd9581efab413aa8233a63ef7659b94e629e8734912e5', 'Android', 'android', NULL, '2026-09-12 02:04:33', '2026-12-11 01:55:22', 0, '2026-09-11 19:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `actor` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(240) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('ok','info','warn','bad') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `actor`, `action`, `level`, `ip`, `time_label`, `created_at`) VALUES
(1, 'A. Ogunlesi (Admin)', 'Approved listing · mod-0812', 'ok', NULL, '09:12', '2026-09-03 14:00:23'),
(2, 'Finance Bot', 'Payout batch #88731 settled', 'info', NULL, '08:47', '2026-09-03 14:00:23'),
(3, 'Moderator 3', 'Flagged review · sky-garden', 'warn', NULL, 'Yesterday', '2026-09-03 14:00:23'),
(4, 'D. Rufai (Support)', 'Resolved dispute #D-4410 · partial refund', 'ok', NULL, 'Yesterday', '2026-09-03 14:00:23'),
(5, 'DM: Risk', 'Escalated FRA-0916 to manual review', 'warn', NULL, '2 days ago', '2026-09-03 14:00:23'),
(6, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '102.89.46.67', '20:02', '2026-09-03 14:02:02'),
(7, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '102.89.42.215', '20:10', '2026-09-03 14:10:31'),
(8, 'orenomhesse@gmail.com', 'Admin signed out', 'info', '102.89.46.67', '22:57', '2026-09-03 16:57:02'),
(9, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '105.113.64.191', '12:42', '2026-09-05 06:42:00'),
(10, 'orenomhesse@gmail.com', 'Admin signed out', 'info', '105.113.64.191', '12:42', '2026-09-05 06:42:11'),
(11, 'invest.lucrobusiness@gmail.com', 'Failed admin sign-in', 'bad', '105.113.64.191', '14:23', '2026-09-05 08:23:59'),
(12, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '105.113.64.191', '14:24', '2026-09-05 08:24:25'),
(13, 'orenomhesse@gmail.com', 'Admin signed out', 'info', '105.113.64.191', '14:36', '2026-09-05 08:36:04'),
(14, 'scie.techie@outlook.com', 'New account created', 'ok', '105.113.64.191', '14:36', '2026-09-05 08:36:33'),
(15, 'scie.techie@outlook.com', 'Account created', 'ok', '105.113.64.191', '14:36', '2026-09-05 08:36:33'),
(16, 'info@tamsluxe.com', 'New owner account created', 'ok', '37.120.213.201', '18:34', '2026-09-05 12:34:41'),
(17, 'info@tamsluxe.com', 'Account created', 'ok', '37.120.213.201', '18:34', '2026-09-05 12:34:42'),
(18, 'info@tamsluxe.com', 'Listing submitted: The Favored Court', 'info', '37.120.213.201', '18:38', '2026-09-05 12:38:03'),
(19, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '37.120.213.201', '18:39', '2026-09-05 12:39:13'),
(20, 'orenomhesse@gmail.com', 'Approved listing #1 — The Onyx Penthouse', 'ok', '37.120.213.201', '18:40', '2026-09-05 12:40:24'),
(21, 'orenomhesse@gmail.com', 'Verified user info@tamsluxe.com', 'ok', '37.120.213.201', '18:47', '2026-09-05 12:47:05'),
(22, 'orenomhesse@gmail.com', 'Approved listing #13 — The Favored Court', 'ok', '105.113.61.67', '20:06', '2026-09-05 14:06:07'),
(23, 'orenomhesse@gmail.com', 'Signed out', 'info', '105.113.61.67', '20:07', '2026-09-05 14:07:10'),
(24, 'scie.techie@outlook.com', 'Signed out', 'info', '105.113.61.67', '22:03', '2026-09-05 16:03:07'),
(25, 'local@gmail.com', 'New account created', 'ok', '105.113.61.67', '22:05', '2026-09-05 16:05:56'),
(26, 'local@gmail.com', 'Account created', 'ok', '105.113.61.67', '22:05', '2026-09-05 16:05:57'),
(27, 'local@gmail.com', 'Booking JL-2026-8027 · The Onyx Penthouse', 'ok', '105.113.61.67', '22:11', '2026-09-05 16:11:02'),
(28, 'local@gmail.com', 'Invoice emailed for JL-2026-8027', 'info', '105.113.61.67', '22:12', '2026-09-05 16:12:00'),
(29, 'gtg@ytyt.bg', 'New account created', 'ok', '105.113.61.67', '00:26', '2026-09-05 18:26:18'),
(30, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '102.88.106.70', '12:15', '2026-09-06 06:15:56'),
(31, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '102.88.108.133', '11:32', '2026-09-07 05:32:47'),
(32, 'local@gmail.com', 'Signed in', 'ok', '102.89.47.230', '18:50', '2026-09-07 12:50:45'),
(33, 'local@gmail.com', 'Upgraded to owner from the Android app', 'info', '102.89.47.230', '20:40', '2026-09-07 14:40:09'),
(34, 'look@yahoo.com', 'New owner account created', 'ok', '105.113.117.64', '17:49', '2026-09-11 11:49:14'),
(35, 'look@yahoo.com', 'Account created', 'ok', '105.113.117.64', '17:49', '2026-09-11 11:49:14'),
(36, 'orenomhesse@gmail.com', 'Admin signed in', 'ok', '105.113.117.64', '17:50', '2026-09-11 11:50:02'),
(37, 'jollofliving@yahoo.com', 'Signed out', 'info', '105.113.117.64', '19:12', '2026-09-11 13:12:42'),
(38, 'orenomhesse@gmail.com', 'Failed admin sign-in', 'bad', '105.113.117.64', '19:13', '2026-09-11 13:13:00'),
(39, 'jollofliving@yahoo.com', 'Admin signed in', 'ok', '105.113.117.64', '19:13', '2026-09-11 13:13:36');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_time` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `img` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'p1',
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `slug`, `category`, `title`, `date_label`, `read_time`, `img`, `excerpt`, `body`, `published`, `sort_order`, `created_at`) VALUES
(1, 'top-10-stays-lagos', 'Guides', 'The Top 10 Stays in Lagos Right Now', 'Aug 2026', '9 min', 'p1', 'From sky-high penthouses to lagoon villas — the residences defining luxury travel in Lagos this season.', '[\"Lagos has never had more beautiful places to stay. A decade of architectural ambition, a new wave of design talent and the world\'s most vibrant energy have combined into a stay-city without equal.\",\"Our editors visited, slept and hosted at every residence in the current collection, scoring comfort, design, service and location. Here is what rose to the top.\",\"Number one this season is The Onyx Penthouse, a black-marble and brass tour de force in Lekki Phase 1. The private cinema room convinced our judges within the first hour.\",\"Rounding out the list: The Sky Garden Terrace\'s sunset lounge, Heritage House\'s restored courtyard, and the Eko Icon Suite — the smartest budget-luxury play in the city.\"]', 1, 0, '2026-09-03 14:00:23'),
(2, 'jollof-100', 'Food', 'The Legend of Jollof, Explained Over One Plate', 'Jul 2026', '6 min', 'exp-chef', 'Every Nigerian claims the perfect recipe. Our private chefs settle the argument — with party rice, of course.', '[\"There is no dish in West Africa with a bigger reputation. Jollof rice is the dish of celebration, competition and occasionally international diplomacy.\",\"It begins with a smoky base: onions, peppers, scotch bonnet and tomato, cooked low until the flavour lays down. The rice cooks in that sauce, never after it.\",\"Party rice takes it further — the smoky, slightly charred version cooked over a bigger flame, served at every occasion that matters.\",\"Our private chefs run jollof masterclasses in every residence on request, with the recipe that won them their family bragging rights.\"]', 1, 1, '2026-09-03 14:00:23'),
(3, 'guidetodettydecember', 'Culture', 'The Insider\'s Guide to Detty December', 'Jun 2026', '11 min', 'p4', 'The world\'s greatest month of music, parties and homecoming — and how to live it like a local.', '[\"When the diaspora flies home, Lagos becomes the happiest city on earth. Detty December is a full month of concerts, festivals, beach days and long dinners.\",\"Base yourself near the action — Vi, Ikoyi or Lekki Phase 1 — and let the concierge build your calendar, because events sell out in days.\",\"Book early. Our best residences are reserved 60 to 90 days ahead, and the villa team at Villa Azur becomes the most popular address in the city.\",\"Use the last-minute deals channel for flexibility, and always arrange transfers in advance — the December roads demand it.\"]', 1, 2, '2026-09-03 14:00:23'),
(4, 'work-from-lagos', 'Work', 'Working From Lagos: The Executive Playbook', 'May 2026', '8 min', 'p5', 'Fibre Wi-Fi, power resilience and a desk with a view — how to run a serious work week from Nigeria\'s capital.', '[\"Lagos and Abuja are quietly becoming remote-work capitals. English-speaking, arts-rich, food-obsessed, and connected by subsea cables to Europe in under 60ms.\",\"The key is choosing a residence built for work: dedicated desks, backup generation, and fibre that never blinks. Our Maitama and Eko Atlantic addresses are favourites.\",\"Extend the workday into the evening the Lagos way — rooftop dinner, jazz, then breakfast with lagoon light.\",\"Our Corporate Ready homes support PO billing, traveler policies and monthly invoicing for companies sending teams.\"]', 1, 3, '2026-09-03 14:00:23'),
(5, 'longstay-guide', 'Living', 'Six Weeks in Lagoon Living: A Long-Stay Diary', 'Apr 2026', '7 min', 'p12', 'What it actually costs, what it actually feels like, and why monthly-staying is the best way to know a city.', '[\"We asked a guest who stayed six weeks at The Lagoon Villa to keep a diary: costs, routines, discoveries, and the moments she\'d never have found as a tourist.\",\"Week one: the split-payment plan meant the move was painless. Weekly housekeeping kept the villa perfect. Week three: she had a standing table at two restaurants and a regular boatman.\",\"Long-stay pricing drops 25% on monthly bookings, with a digital lease agreement that covers everything in plain language.\",\"By week six she knew the market, the barber, the sunset spot and the tide chart.\"]', 1, 4, '2026-09-03 14:00:23'),
(6, 'escrow-explained', 'Trust', 'How Escrow Makes Luxury Stays Safe', 'Mar 2026', '5 min', 'p2', 'Your money is held by Jollof Living until you check in. Here\'s the full flow — and why it changes everything.', '[\"The biggest fear in travel is paying for something that isn\'t what you were promised. Escrow removes that anxiety completely.\",\"When you book, your payment is captured but held by Jollof Living — the host has not received it. You check in, inspect, and confirm. Only then are funds released.\",\"If the residence isn\'t as described, our Dispute Resolution Centre steps in within hours, not days.\",\"Every booking on the platform is protected this way, with a refund timeline published before you pay.\"]', 1, 5, '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `ref` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `guest_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin` date DEFAULT NULL,
  `checkout` date DEFAULT NULL,
  `nights` int(11) NOT NULL DEFAULT '1',
  `guests` int(11) NOT NULL DEFAULT '1',
  `policy` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'moderate',
  `pay_method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addons` text COLLATE utf8mb4_unicode_ci,
  `promo_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `split_payment` tinyint(1) NOT NULL DEFAULT '0',
  `is_request` tinyint(1) NOT NULL DEFAULT '0',
  `subtotal` int(11) NOT NULL DEFAULT '0',
  `fees` int(11) NOT NULL DEFAULT '0',
  `taxes` int(11) NOT NULL DEFAULT '0',
  `discount` int(11) NOT NULL DEFAULT '0',
  `total` int(11) NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `breakdown` text COLLATE utf8mb4_unicode_ci,
  `points_earned` int(11) NOT NULL DEFAULT '0',
  `status` enum('pending','confirmed','active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `escrow_status` enum('held','released','refunded','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'held',
  `checkin_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `ref`, `user_id`, `property_id`, `guest_name`, `guest_email`, `guest_phone`, `checkin`, `checkout`, `nights`, `guests`, `policy`, `pay_method`, `addons`, `promo_code`, `split_payment`, `is_request`, `subtotal`, `fees`, `taxes`, `discount`, `total`, `currency`, `breakdown`, `points_earned`, `status`, `escrow_status`, `checkin_code`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'JL-2026-8027', 17, 1, 'Riverton Adeboye', 'local@gmail.com', '+2347077249425', '2026-09-14', '2026-09-15', 1, 2, 'flexible', 'bank', '[\"lateco\"]', NULL, 1, 0, 195000, 30600, 16920, 0, 242520, 'NGN', '{\"nights\":1,\"nightly\":185000,\"base\":185000,\"lengthDiscount\":0,\"lengthDiscRate\":0,\"addons\":[{\"key\":\"lateco\",\"name\":\"Late check-out \\u00b7 2pm\",\"amount\":10000}],\"addonTotal\":10000,\"cleaning\":15000,\"service\":15600,\"vat\":16920,\"promoCode\":\"\",\"promoLabel\":\"\",\"promoDiscount\":0,\"gift\":0,\"subtotal\":195000,\"fees\":30600,\"taxes\":16920,\"discount\":0,\"total\":242520,\"deposit\":48504,\"halfNow\":121260}', 1210, 'confirmed', 'held', '1771#', NULL, '2026-09-05 16:11:02', '2026-09-05 16:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `booking_events`
--

CREATE TABLE `booking_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `event` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_events`
--

INSERT INTO `booking_events` (`id`, `booking_id`, `event`, `detail`, `created_at`) VALUES
(1, 1, 'confirmed', 'Payment captured into escrow · bank', '2026-09-05 16:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `window_label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `revenue` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` enum('ok','info','warn','bad') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campaigns`
--

INSERT INTO `campaigns` (`id`, `code`, `name`, `window_label`, `status`, `revenue`, `level`, `created_at`) VALUES
(1, 'NAIROBI-25', 'Detty December Preview', 'Oct 1 – Dec 20', 'Live', '₦18.4M', 'ok', '2026-09-03 14:00:23'),
(2, 'REFER10', 'Give ₦10k, Get ₦10k', 'Rolling', 'Live', '₦6.1M', 'ok', '2026-09-03 14:00:23'),
(3, 'WELCOME5', 'First-stay 5%', 'Rolling', 'Live', '₦2.2M', 'ok', '2026-09-03 14:00:23'),
(4, 'VIP-GOLD', 'Platinum early access', 'Q4', 'Scheduled', '—', 'info', '2026-09-03 14:00:23'),
(5, 'MAR25', 'March long-stay promo', 'Mar 2027', 'Draft', '—', 'info', '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `cms_blocks`
--

CREATE TABLE `cms_blocks` (
  `id` int(10) UNSIGNED NOT NULL,
  `block_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Live',
  `owner` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_blocks`
--

INSERT INTO `cms_blocks` (`id`, `block_key`, `title`, `body`, `status`, `owner`, `updated_label`, `updated_at`) VALUES
(1, 'block-1', 'Home hero slide 2', NULL, 'Live', 'A. Ogunlesi', 'Jul 30', '2026-09-03 14:00:23'),
(2, 'block-2', 'Neighbourhood guide · Ikoyi', NULL, 'Live', 'Content team', 'Aug 12', '2026-09-03 14:00:23'),
(3, 'block-3', 'Blog: Detty December insider\'s guide', NULL, 'Live', 'Content team', 'Aug 24', '2026-09-03 14:00:23'),
(4, 'block-4', 'Promo banner · Last-minute deals', NULL, 'Live', 'Growth', 'Sep 1', '2026-09-03 14:00:23'),
(5, 'block-5', 'Help: How escrow works', NULL, 'Draft', 'Support', NULL, '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `img` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'p1',
  `wide` tinyint(1) NOT NULL DEFAULT '0',
  `tall` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collections`
--

INSERT INTO `collections` (`id`, `slug`, `name`, `sub`, `img`, `wide`, `tall`, `sort_order`) VALUES
(1, 'waterfront', 'Waterfront Escapes', 'Lagoon & ocean living', 'p3', 0, 1, 0),
(2, 'lagos-penthouses', 'Lagos Penthouses', 'Above the skyline', 'p1', 0, 0, 1),
(3, 'abuja-executive', 'Abuja Executive Suites', 'Power stays, perfected', 'p9', 0, 0, 2),
(4, 'heritage', 'Cultural Heritage Stays', 'Stories in every wall', 'p7', 0, 0, 3),
(5, 'romantic', 'Romantic Getaways', 'For two, beautifully', 'p2', 1, 0, 4),
(6, 'family', 'Family & Long Stays', 'Room to breathe', 'p10', 0, 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `collection_properties`
--

CREATE TABLE `collection_properties` (
  `collection_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collection_properties`
--

INSERT INTO `collection_properties` (`collection_id`, `property_id`, `sort_order`) VALUES
(1, 2, 0),
(1, 3, 1),
(1, 8, 2),
(2, 1, 0),
(2, 4, 1),
(2, 6, 2),
(3, 5, 0),
(3, 11, 1),
(4, 7, 0),
(5, 2, 0),
(5, 4, 1),
(5, 8, 2),
(6, 8, 2),
(6, 9, 0),
(6, 12, 1);

-- --------------------------------------------------------

--
-- Table structure for table `compare_items`
--

CREATE TABLE `compare_items` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compare_items`
--

INSERT INTO `compare_items` (`user_id`, `property_id`, `created_at`) VALUES
(14, 1, '2026-09-03 14:14:50'),
(14, 2, '2026-09-03 14:13:19');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `ckey` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'support',
  `host_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preview` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `online` tinyint(1) NOT NULL DEFAULT '1',
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user_id`, `ckey`, `kind`, `host_id`, `name`, `subtitle`, `preview`, `online`, `last_message_at`, `created_at`) VALUES
(1, 1, 'concierge', 'concierge', NULL, 'Jollof Concierge', 'AI · online', 'Lovely! The Onyx Penthouse in Lekki Phase 1 is available Sep 5–7 at ₦185,000/night — it\'s Instant Book, so I can reserve', 1, NULL, '2026-09-03 14:00:23'),
(2, 1, 'team-onyx', 'host', NULL, 'The Onyx Penthouse', 'Superhost · replies ~5 min', 'Already done — arriving with the welcome basket at 2pm. See you tomorrow! ✓✓', 1, NULL, '2026-09-03 14:00:23'),
(3, 1, 'support', 'support', NULL, 'Jollof Support', '24/7 · human team', 'Of course! Extended Sep 17→18 at your existing rate. I\'ve sent a modification request to the host — you\'ll be notified w', 1, NULL, '2026-09-03 14:00:23'),
(4, 14, 'concierge', 'concierge', NULL, 'Jollof Concierge', 'AI · online', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:Villa A', 1, '2026-09-03 20:12:36', '2026-09-03 14:12:02'),
(5, 14, 'support', 'support', NULL, 'Jollof Support', '24/7 · human team', NULL, 1, '2026-09-03 20:12:02', '2026-09-03 14:12:02'),
(6, 15, 'concierge', 'concierge', NULL, 'Jollof Concierge', 'AI · online', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:Villa A', 1, '2026-09-05 17:32:36', '2026-09-05 08:36:33'),
(7, 15, 'support', 'support', NULL, 'Jollof Support', '24/7 · human team', NULL, 1, '2026-09-05 14:36:33', '2026-09-05 08:36:33'),
(8, 16, 'concierge', 'concierge', NULL, 'Jollof Concierge', 'AI · online', NULL, 1, '2026-09-05 18:34:41', '2026-09-05 12:34:41'),
(9, 16, 'support', 'support', NULL, 'Jollof Support', '24/7 · human team', NULL, 1, '2026-09-05 18:34:41', '2026-09-05 12:34:41'),
(10, 17, 'concierge', 'concierge', NULL, 'Jollof Concierge', 'AI · online', NULL, 1, '2026-09-05 22:05:56', '2026-09-05 16:05:56'),
(11, 17, 'support', 'support', NULL, 'Jollof Support', '24/7 · human team', NULL, 1, '2026-09-05 22:05:56', '2026-09-05 16:05:56'),
(12, 19, 'concierge', 'concierge', NULL, 'Jollof Concierge', 'AI · online', NULL, 1, '2026-09-11 17:49:14', '2026-09-11 11:49:14'),
(13, 19, 'support', 'support', NULL, 'Jollof Support', '24/7 · human team', NULL, 1, '2026-09-11 17:49:14', '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(10) UNSIGNED NOT NULL,
  `kind` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contact',
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `meta` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `experiences`
--

CREATE TABLE `experiences` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `img` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'p1',
  `price` int(11) NOT NULL DEFAULT '0',
  `duration` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `experiences`
--

INSERT INTO `experiences` (`id`, `slug`, `name`, `category`, `img`, `price`, `duration`, `description`, `active`, `sort_order`) VALUES
(1, 'cruise', 'Lagos Lagoon Cruise', 'Signature', 'exp-boat', 85000, '3 hours', 'Sunset aboard a private yacht — champagne, canapés and the skyline drifting past on the world\'s most storied lagoon.', 1, 0),
(2, 'chef', 'Private Chef & Jollof Masterclass', 'Taste', 'exp-chef', 55000, '2–3 hours', 'A Jollof Living-approved chef cooks a tasting menu in your kitchen — or teaches you the legend of the perfect jollof.', 1, 1),
(3, 'spa', 'Spa & Wellness Ritual', 'Restore', 'exp-spa', 45000, '90 min', 'In-residence massages, facials and private trainers, delivered to your door by vetted therapists.', 1, 2),
(4, 'tour', 'Art & Heritage City Tour', 'Culture', 'exp-tour', 35000, '4 hours', 'Nike Art Gallery, Freedom Park and the creative studios of Lagos, guided by artists and local historians.', 1, 3),
(5, 'transfer', 'Airport Transfer', 'Essential', 'p11', 25000, '45 min', 'Vetted chauffeurs in luxury SUVs — meet-and-greet, flight tracking and a chilled bottle on board.', 1, 4),
(6, 'events', 'Event Curation', 'Bespoke', 'p4', 120000, 'Half day', 'Birthdays, proposals, corporate retreats — our events team designs and runs the whole thing.', 1, 5),
(7, 'shopper', 'Personal Shopper', 'Bespoke', 'p7', 30000, '3 hours', 'Pre-stock groceries, fashion-styling runs, or gift sourcing — someone brilliant, on your side.', 1, 6),
(8, 'festival', 'Festival Calendar', 'Culture', 'exp-tour', 0, 'Ongoing', 'Detty December, Eko Jazz, art weeks — we curate tickets and tables for the moments everyone remembers.', 1, 7);

-- --------------------------------------------------------

--
-- Table structure for table `experience_bookings`
--

CREATE TABLE `experience_bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `experience_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `guest_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_for` date DEFAULT NULL,
  `guests` int(11) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(10) UNSIGNED NOT NULL,
  `question` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`) VALUES
(1, 'What are the cancellation policies?', 'Three transparent tiers: Flexible (full refund up to 48h before check-in), Moderate (full refund up to 5 days, 50% after), and Strict (50% up to 14 days). Each listing shows its policy clearly before you book.', NULL, 0),
(2, 'How do escrow payments work?', 'Your payment is held securely by Jollof Living and released to the host only after you confirm check-in. If the property isn\'t as described, our Dispute Resolution Centre and 24/7 team step in.', NULL, 1),
(3, 'Can I split payments on long stays?', 'Yes. For stays of 30 nights or more you can pay in two installments — 50% at booking and 50% at check-in — easing the financial burden of long-term luxury living.', NULL, 2),
(4, 'What does Jollof Verified mean?', 'Properties that pass an in-person or video inspection earn the Jollof Verified badge. Every host also completes full KYC identity verification, and every listing is screened by our AI moderation systems.', NULL, 3),
(5, 'Do I need an extended-stay agreement?', 'For 30+ night stays we generate a digital lease-style agreement with clear terms — signed securely in-app, with a copy stored in your encrypted documents vault.', NULL, 4),
(6, 'What payment methods do you accept?', 'Cards (Visa, Mastercard, Verve), bank transfer, USSD, mobile money, Paystack, Flutterwave, Stripe, Apple Pay and Google Pay — in NGN, USD, GBP or EUR with real-time exchange rates.', NULL, 5),
(7, 'How is my identity verified?', 'Three steps: government ID upload, a selfie match, and phone/SMS verification. Verified guests unlock instant booking on more residences and build trust with hosts.', NULL, 6),
(8, 'Can I host an event at a residence?', 'Selected listings (marked \'Event Hosting\') accept intimate events — birthdays, proposals, small retreats. Always request approval at booking; strict noise policies apply after 10pm.', NULL, 7),
(9, 'What is Jollof Points and how do I earn them?', 'Earn points per night based on your membership tier (5×–15×). Redeem for discounts, late check-outs, upgrades and experiences. Your ledger is in your account.', NULL, 8),
(10, 'How do I become a host?', 'Start the listing wizard — you\'ll be guided step by step through details, pricing, policies and photos. Our team then verifies and (on request) photographs your property. You can earn from your first booking.', NULL, 9),
(11, 'Is there help if something goes wrong?', '24/7 support on chat, phone and WhatsApp, an in-app dispute resolution centre, and one-tap emergency assistance. Median first response is under 3 minutes.', NULL, 10),
(12, 'Do you support corporate travel?', 'Jollof Living for Business provides centralized billing, PO numbers, travel policy enforcement, monthly invoicing and a dedicated account manager. Bookings count toward your company\'s travel dashboard.', NULL, 11);

-- --------------------------------------------------------

--
-- Table structure for table `fraud_flags`
--

CREATE TABLE `fraud_flags` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score` int(11) NOT NULL DEFAULT '0',
  `level` enum('ok','info','warn','bad') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fraud_flags`
--

INSERT INTO `fraud_flags` (`id`, `code`, `subject`, `reason`, `score`, `level`, `status`, `resolved_at`, `created_at`) VALUES
(1, 'FRA-0919', 'Booking · Villa Azur', 'Card mismatch + new account', 72, 'warn', 'open', NULL, '2026-09-03 14:00:23'),
(2, 'FRA-0921', 'Payment · ₦1.2M', 'Velocity anomaly on card', 54, 'warn', 'open', NULL, '2026-09-03 14:00:23'),
(3, 'FRA-0920', 'Listing · Banana Breeze', 'Image reuse detected', 88, 'bad', 'open', NULL, '2026-09-03 14:00:23'),
(4, 'FRA-0917', 'Guest message', 'Phishing link in chat', 61, 'warn', 'open', NULL, '2026-09-03 14:00:23'),
(5, 'FRA-0915', 'Payout change', 'Bank change 24h after booking', 45, 'info', 'open', NULL, '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `fx_rates`
--

CREATE TABLE `fx_rates` (
  `code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(18,10) NOT NULL DEFAULT '1.0000000000',
  `decimals` tinyint(4) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fx_rates`
--

INSERT INTO `fx_rates` (`code`, `symbol`, `rate`, `decimals`, `sort_order`) VALUES
('EUR', '€', 0.0005464481, 0, 3),
('GBP', '£', 0.0004672897, 0, 2),
('NGN', '₦', 1.0000000000, 0, 0),
('USD', '$', 0.0006451613, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `gift_cards`
--

CREATE TABLE `gift_cards` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int(11) NOT NULL DEFAULT '0',
  `balance` int(11) NOT NULL DEFAULT '0',
  `purchaser` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `help_categories`
--

CREATE TABLE `help_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `article_count` int(11) NOT NULL DEFAULT '0',
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `help_categories`
--

INSERT INTO `help_categories` (`id`, `slug`, `name`, `article_count`, `icon`, `sort_order`) VALUES
(1, 'booking', 'Booking & reservations', 4, 'calendar', 0),
(2, 'payments', 'Payments, invoices & tax', 3, 'wallet', 1),
(3, 'hosting', 'Hosting on Jollof Living', 3, 'building', 2),
(4, 'trust', 'Trust, safety & disputes', 4, 'shield', 3),
(5, 'account', 'Account, ID & security', 3, 'lock', 4),
(6, 'app', 'Using the app', 2, 'phone', 5);

-- --------------------------------------------------------

--
-- Table structure for table `host_channels`
--

CREATE TABLE `host_channels` (
  `id` int(10) UNSIGNED NOT NULL,
  `host_id` int(10) UNSIGNED NOT NULL,
  `channel` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('connected','disconnected','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected',
  `last_sync_at` datetime DEFAULT NULL,
  `note` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `host_channels`
--

INSERT INTO `host_channels` (`id`, `host_id`, `channel`, `status`, `last_sync_at`, `note`) VALUES
(1, 16, 'Airbnb', 'disconnected', NULL, NULL),
(2, 16, 'Booking.com', 'disconnected', NULL, NULL),
(3, 16, 'VRBO / Expedia', 'disconnected', NULL, NULL),
(4, 16, 'Direct', 'connected', '2026-09-05 18:34:41', 'Always in sync'),
(5, 17, 'Airbnb', 'disconnected', NULL, NULL),
(6, 17, 'Booking.com', 'disconnected', NULL, NULL),
(7, 17, 'VRBO / Expedia', 'disconnected', NULL, NULL),
(8, 17, 'Direct', 'connected', '2026-09-07 20:40:09', 'Always in sync'),
(9, 19, 'Airbnb', 'disconnected', NULL, NULL),
(10, 19, 'Booking.com', 'disconnected', NULL, NULL),
(11, 19, 'VRBO / Expedia', 'disconnected', NULL, NULL),
(12, 19, 'Direct', 'connected', '2026-09-11 17:49:14', 'Always in sync');

-- --------------------------------------------------------

--
-- Table structure for table `host_insights`
--

CREATE TABLE `host_insights` (
  `id` int(10) UNSIGNED NOT NULL,
  `host_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `kind` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'listing',
  `title` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` enum('ok','info','warn','bad') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `status` enum('open','applied','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `host_payout_settings`
--

CREATE TABLE `host_payout_settings` (
  `host_id` int(10) UNSIGNED NOT NULL,
  `schedule` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_last` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `host_payout_settings`
--

INSERT INTO `host_payout_settings` (`host_id`, `schedule`, `bank_name`, `account_name`, `account_last`, `updated_at`) VALUES
(16, 'weekly', NULL, NULL, NULL, '2026-09-05 12:34:41'),
(17, 'weekly', NULL, NULL, NULL, '2026-09-07 14:40:09'),
(19, 'weekly', NULL, NULL, NULL, '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `host_team`
--

CREATE TABLE `host_team` (
  `id` int(10) UNSIGNED NOT NULL,
  `host_id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_role` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cohost',
  `permissions` varchar(240) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'calendar,messages',
  `status` enum('invited','active','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invited',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `host_templates`
--

CREATE TABLE `host_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `host_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_on` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'send',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `host_templates`
--

INSERT INTO `host_templates` (`id`, `host_id`, `title`, `body`, `trigger_on`, `icon`, `active`, `created_at`) VALUES
(1, 16, 'Booking confirmation', 'Hi {name}! Your reservation at {property} is confirmed. {dates}. We cannot wait to host you.', 'confirmed', 'send', 1, '2026-09-05 12:34:41'),
(2, 16, 'Check-in instructions', 'Welcome! The smart lock opens with {code}. Wi-Fi details and parking are in your trip notes.', 'checkin', 'key', 1, '2026-09-05 12:34:41'),
(3, 16, 'Check-out reminder', 'Check-out is at 11am tomorrow. Message us if you would like a late check-out.', 'checkout', 'clock', 1, '2026-09-05 12:34:41'),
(4, 17, 'Booking confirmation', 'Hi {name}! Your reservation at {property} is confirmed. {dates}. We cannot wait to host you.', 'confirmed', 'send', 1, '2026-09-07 14:40:09'),
(5, 17, 'Check-in instructions', 'Welcome! The smart lock opens with {code}. Wi-Fi details and parking are in your trip notes.', 'checkin', 'key', 1, '2026-09-07 14:40:09'),
(6, 17, 'Check-out reminder', 'Check-out is at 11am tomorrow. Message us if you would like a late check-out.', 'checkout', 'clock', 1, '2026-09-07 14:40:09'),
(7, 19, 'Booking confirmation', 'Hi {name}! Your reservation at {property} is confirmed. {dates}. We cannot wait to host you.', 'confirmed', 'send', 1, '2026-09-11 11:49:14'),
(8, 19, 'Check-in instructions', 'Welcome! The smart lock opens with {code}. Wi-Fi details and parking are in your trip notes.', 'checkin', 'key', 1, '2026-09-11 11:49:14'),
(9, 19, 'Check-out reminder', 'Check-out is at 11am tomorrow. Message us if you would like a late check-out.', 'checkout', 'clock', 1, '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `identifier` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `identifier`, `ip`, `success`, `created_at`) VALUES
(1, 'orenomhesse@gmail.com', '102.89.46.67', 1, '2026-09-03 14:02:02'),
(2, 'orenomhesse@gmail.com', '102.89.42.215', 1, '2026-09-03 14:10:31'),
(3, 'orenomhesse@gmail.com', '105.113.64.191', 1, '2026-09-05 06:42:00'),
(4, 'invest.lucrobusiness@gmail.com', '105.113.64.191', 0, '2026-09-05 08:23:59'),
(5, 'orenomhesse@gmail.com', '105.113.64.191', 1, '2026-09-05 08:24:25'),
(6, 'orenomhesse@gmail.com', '37.120.213.201', 1, '2026-09-05 12:39:13'),
(7, 'invest.lucrobusiness@gmail.com', '102.88.106.70', 0, '2026-09-06 06:11:57'),
(8, 'orenomhesse@gmail.com', '102.88.106.70', 1, '2026-09-06 06:13:14'),
(9, 'orenomhesse@gmail.com', '102.88.106.70', 1, '2026-09-06 06:15:56'),
(10, 'orenomhesse@gmail.com', '102.88.109.66', 1, '2026-09-06 10:56:43'),
(11, 'invest.lucrobusiness@gmail.com', '102.88.109.66', 0, '2026-09-06 11:14:14'),
(12, 'invest.lucrobusiness@gmail.com', '102.88.109.66', 0, '2026-09-06 11:14:30'),
(13, 'invest.lucrobusiness@gmail.com', '102.88.109.66', 0, '2026-09-06 11:14:42'),
(14, 'local@gmail.com', '102.88.109.66', 1, '2026-09-06 11:16:20'),
(15, 'orenomhesse@gmail.com', '102.88.108.133', 1, '2026-09-07 05:32:47'),
(16, 'local@gmail.com', '102.89.47.230', 1, '2026-09-07 12:50:45'),
(17, 'orenomhesse@gmail.com', '105.113.117.64', 1, '2026-09-11 11:50:02'),
(18, 'orenomhesse@gmail.com', '105.113.117.64', 0, '2026-09-11 13:13:00'),
(19, 'jollofliving@yahoo.com', '105.113.117.64', 1, '2026-09-11 13:13:36'),
(20, 'jollofliving@yahoo.com', '105.113.63.171', 1, '2026-09-11 19:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `conversation_id` int(10) UNSIGNED NOT NULL,
  `sender` enum('me','bot','host','support') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bot',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_label` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_flag` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender`, `body`, `time_label`, `read_flag`, `created_at`) VALUES
(1, 1, 'bot', 'Welcome! I\'m <b>Jollof</b> ✨ — booking, transfers, chefs, anything.', '09:41', 0, '2026-09-03 14:00:23'),
(2, 1, 'me', 'Book me something nice in Lekki for next weekend', '09:42', 0, '2026-09-03 14:00:23'),
(3, 1, 'bot', 'Lovely! <b>The Onyx Penthouse</b> in Lekki Phase 1 is available Sep 5–7 at ₦185,000/night — it\'s <b>Instant Book</b>, so I can reserve it now. Want me to add an airport transfer?', '09:42', 0, '2026-09-03 14:00:23'),
(4, 2, 'bot', 'Welcome to The Onyx! Your check-in code is 4471# — the lift is express to P22.', '08:05', 0, '2026-09-03 14:00:23'),
(5, 2, 'me', 'Perfect. Could we get a crib for the little one?', '08:11', 0, '2026-09-03 14:00:23'),
(6, 2, 'bot', 'Already done — arriving with the welcome basket at 2pm. See you tomorrow! ✓✓', '08:12', 1, '2026-09-03 14:00:23'),
(7, 3, 'me', 'Hi — can I add a night to my Ocean Pearl stay?', 'Yesterday', 0, '2026-09-03 14:00:23'),
(8, 3, 'bot', 'Of course! Extended Sep 17→18 at your existing rate. I\'ve sent a modification request to the host — you\'ll be notified within the hour.', 'Yesterday', 1, '2026-09-03 14:00:23'),
(9, 4, 'bot', 'Welcome! I\'m <b>Jollof</b> ✨ — booking, transfers, chefs, anything.', '20:12', 0, '2026-09-03 14:12:02'),
(10, 5, 'bot', 'Hello! Our team is here around the clock — reply any time.', '20:12', 0, '2026-09-03 14:12:02'),
(11, 4, 'me', 'Hi', '20:12', 1, '2026-09-03 14:12:02'),
(12, 4, 'bot', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:<br><a class=\"conc-card\" href=\"/jollof/stay.php?p=villa-azur\"><b>Villa Azur</b><span>Banana Island · ₦420,000/night · 4.99★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=onyx\"><b>The Onyx Penthouse</b><span>Lekki Phase 1 · ₦185,000/night · 4.97★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=lagoon-villa\"><b>The Lagoon Villa</b><span>Victoria Island · ₦175,000/night · 4.96★</span></a>', '20:12', 1, '2026-09-03 14:12:02'),
(13, 4, 'me', 'I need an apartment in Lekki', '20:12', 1, '2026-09-03 14:12:23'),
(14, 4, 'bot', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:<br><a class=\"conc-card\" href=\"/jollof/stay.php?p=villa-azur\"><b>Villa Azur</b><span>Banana Island · ₦420,000/night · 4.99★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=onyx\"><b>The Onyx Penthouse</b><span>Lekki Phase 1 · ₦185,000/night · 4.97★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=lagoon-villa\"><b>The Lagoon Villa</b><span>Victoria Island · ₦175,000/night · 4.96★</span></a>', '20:12', 1, '2026-09-03 14:12:23'),
(15, 4, 'me', '200000', '20:12', 1, '2026-09-03 14:12:36'),
(16, 4, 'bot', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:<br><a class=\"conc-card\" href=\"/jollof/stay.php?p=villa-azur\"><b>Villa Azur</b><span>Banana Island · ₦420,000/night · 4.99★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=onyx\"><b>The Onyx Penthouse</b><span>Lekki Phase 1 · ₦185,000/night · 4.97★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=lagoon-villa\"><b>The Lagoon Villa</b><span>Victoria Island · ₦175,000/night · 4.96★</span></a>', '20:12', 1, '2026-09-03 14:12:36'),
(17, 6, 'bot', 'Welcome! I\'m <b>Jollof</b> ✨ — booking, transfers, chefs, anything.', '14:36', 1, '2026-09-05 08:36:33'),
(18, 7, 'bot', 'Hello! Our team is here around the clock — reply any time.', '14:36', 0, '2026-09-05 08:36:33'),
(19, 6, 'me', 'Check-in code, please', '17:32', 1, '2026-09-05 17:32:29'),
(20, 6, 'bot', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:<br><a class=\"conc-card\" href=\"/jollof/stay.php?p=villa-azur\"><b>Villa Azur</b><span>Banana Island · ₦420,000/night · 4.99★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=onyx\"><b>The Onyx Penthouse</b><span>Lekki Phase 1 · ₦185,000/night · 4.97★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=lagoon-villa\"><b>The Lagoon Villa</b><span>Victoria Island · ₦175,000/night · 4.96★</span></a>', '17:32', 1, '2026-09-05 11:32:29'),
(21, 6, 'me', 'Can I extend by a night?', '17:32', 1, '2026-09-05 17:32:36'),
(22, 6, 'bot', 'I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:<br><a class=\"conc-card\" href=\"/jollof/stay.php?p=villa-azur\"><b>Villa Azur</b><span>Banana Island · ₦420,000/night · 4.99★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=onyx\"><b>The Onyx Penthouse</b><span>Lekki Phase 1 · ₦185,000/night · 4.97★</span></a><a class=\"conc-card\" href=\"/jollof/stay.php?p=lagoon-villa\"><b>The Lagoon Villa</b><span>Victoria Island · ₦175,000/night · 4.96★</span></a>', '17:32', 1, '2026-09-05 11:32:36'),
(23, 8, 'bot', 'Welcome! I\'m <b>Jollof</b> ✨ — booking, transfers, chefs, anything.', '18:34', 0, '2026-09-05 12:34:41'),
(24, 9, 'bot', 'Hello! Our team is here around the clock — reply any time.', '18:34', 0, '2026-09-05 12:34:41'),
(25, 10, 'bot', 'Welcome! I\'m <b>Jollof</b> ✨ — booking, transfers, chefs, anything.', '22:05', 0, '2026-09-05 16:05:56'),
(26, 11, 'bot', 'Hello! Our team is here around the clock — reply any time.', '22:05', 0, '2026-09-05 16:05:56'),
(27, 12, 'bot', 'Welcome! I\'m <b>Jollof</b> ✨ — booking, transfers, chefs, anything.', '17:49', 0, '2026-09-11 11:49:14'),
(28, 13, 'bot', 'Hello! Our team is here around the clock — reply any time.', '17:49', 0, '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `moderation_queue`
--

CREATE TABLE `moderation_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `item` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_slug` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` enum('ok','info','warn','bad') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `moderation_queue`
--

INSERT INTO `moderation_queue` (`id`, `item`, `ref_slug`, `item_type`, `note`, `level`, `status`, `created_at`) VALUES
(1, 'Island Retreat Photos (v2)', 'island-retreat', 'Photo set · 24 images', 'AI passed · awaiting human check', 'warn', 'open', '2026-09-03 14:00:23'),
(2, 'New listing: Banana Breeze Villa', NULL, 'New listing · Legit Lagos', 'KYC complete · inspection booked', 'info', 'open', '2026-09-03 14:00:23'),
(3, 'Review flag: \'perfect\' ×19', 'sky-garden', 'Suspicious pattern', 'AI flagged · human review', 'warn', 'open', '2026-09-03 14:00:23'),
(4, 'Villa Azur Dec pricing', 'villa-azur', 'Pricing change · +20%', 'Payout standard', 'ok', 'open', '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `neighborhoods`
--

CREATE TABLE `neighborhoods` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `img` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'p1',
  `avg_price` int(11) NOT NULL DEFAULT '0',
  `stays_count` int(11) NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `neighborhoods`
--

INSERT INTO `neighborhoods` (`id`, `slug`, `name`, `tag`, `img`, `avg_price`, `stays_count`, `description`, `sort_order`) VALUES
(1, 'victoria-island', 'Victoria Island', 'The beating heart of Lagos', 'p2', 145000, 2, 'Lagos\' commercial centre turned nightlife capital — boardrooms by day, some of West Africa\'s best restaurants and rooftops by night.', 0),
(2, 'ikoyi', 'Ikoyi', 'Old money, green avenues', 'p4', 210000, 2, 'Lagos\' most storied residential district — quiet tree-lined streets, boutiques, golf and the lagoon on every horizon.', 1),
(3, 'lekki', 'Lekki Phase 1', 'The new money neighbourhood', 'p10', 165000, 2, 'The city\'s fastest-growing luxury postcode — new towers, art galleries, beach clubs and the best of Lagos\' creative energy.', 2),
(4, 'banana', 'Banana Island', 'Nigeria\'s most exclusive address', 'p12', 330000, 2, 'Africa\'s most celebrated artificial island — a private, guarded enclave of villas where the lagoon meets absolute calm.', 3),
(5, 'eko-atlantic', 'Eko Atlantic', 'The city of tomorrow', 'p11', 115000, 2, 'Built on land reclaimed from the Atlantic — Eko Atlantic is a planned city of glass towers, promenades and ocean views, rising fast.', 4),
(6, 'maitama', 'Maitama, Abuja', 'The gilded north', 'p9', 175000, 2, 'Abuja\'s diplomatic and executive quarter — green hills, embassies and discreet luxury minutes from the Central Business District.', 5);

-- --------------------------------------------------------

--
-- Table structure for table `neighborhood_items`
--

CREATE TABLE `neighborhood_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `kind` enum('dining','night','transport','safety','culture') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `neighborhood_items`
--

INSERT INTO `neighborhood_items` (`id`, `neighborhood_id`, `kind`, `item`, `sort_order`) VALUES
(1, 1, 'dining', 'Bogobiri House — Afro-modern fine dining', 0),
(2, 1, 'dining', 'Circular Bar — lagoon-side cocktails', 1),
(3, 1, 'dining', 'Rooftop Grill at the George', 2),
(4, 1, 'night', 'Flytime Music Hall', 0),
(5, 1, 'night', 'Landmark Beach sundowners', 1),
(6, 1, 'transport', 'Chauffeurs & ride apps in 5 min', 0),
(7, 1, 'transport', 'Ferry terminal to Ikoyi', 1),
(8, 1, 'transport', 'Helipad nearby', 2),
(9, 1, 'safety', 'Well-lit main arteries', 0),
(10, 1, 'safety', 'Estate security at gated residences', 1),
(11, 1, 'safety', '24/7 concierge line', 2),
(12, 1, 'culture', 'Nike Art Gallery · Terra Kulture', 0),
(13, 2, 'dining', 'The George — fine dining', 0),
(14, 2, 'dining', 'Awolowo Road cafés', 1),
(15, 2, 'dining', 'Bungalow Restaurant', 2),
(16, 2, 'night', 'M Restaurant rooftop', 0),
(17, 2, 'night', 'The View, Ikoyi', 1),
(18, 2, 'transport', 'Awolowo Road shuttle', 0),
(19, 2, 'transport', 'Boat services to VI', 1),
(20, 2, 'transport', 'Ride apps everywhere', 2),
(21, 2, 'safety', 'Very low traffic noise', 0),
(22, 2, 'safety', 'Gated compounds', 1),
(23, 2, 'safety', 'Street-level lighting', 2),
(24, 2, 'culture', 'Heritage architecture walks', 0),
(25, 3, 'dining', 'Nike Art Gallery Café', 0),
(26, 3, 'dining', 'Casa Chianti — Italian', 1),
(27, 3, 'dining', 'Suckling Pig by Bol', 2),
(28, 3, 'night', 'Beach clubs on Landmark', 0),
(29, 3, 'night', 'Live at the Jazzhole', 1),
(30, 3, 'transport', 'Lekki-Epe expressway', 0),
(31, 3, 'transport', 'Ride apps & chauffeurs', 1),
(32, 3, 'transport', 'Water taxi to VI', 2),
(33, 3, 'safety', 'Estate security teams', 0),
(34, 3, 'safety', 'CCTV at main gates', 1),
(35, 3, 'safety', 'Emergency response', 2),
(36, 3, 'culture', 'Nike Art Gallery · Design Week', 0),
(37, 4, 'dining', 'Estate clubhouse', 0),
(38, 4, 'dining', 'Private chefs on call', 1),
(39, 4, 'dining', 'Premium provisions delivery', 2),
(40, 4, 'night', 'Private villa evenings', 0),
(41, 4, 'night', 'Yacht sundowners', 1),
(42, 4, 'transport', 'Private bridge access', 0),
(43, 4, 'transport', 'Helipad on the island', 1),
(44, 4, 'transport', 'Chauffeured fleets', 2),
(45, 4, 'safety', '24/7 armed estate security', 0),
(46, 4, 'safety', 'Access-controlled bridge', 1),
(47, 4, 'safety', 'Villa alarm systems', 2),
(48, 4, 'culture', 'Island yachting & regattas', 0),
(49, 5, 'dining', 'Promenade cafés', 0),
(50, 5, 'dining', 'Azure Beach Club', 1),
(51, 5, 'dining', 'Marina restaurants', 2),
(52, 5, 'night', 'Beachfront lounges', 0),
(53, 5, 'night', 'Skyline bars in new towers', 1),
(54, 5, 'transport', 'Linked to VI by Boulevard', 0),
(55, 5, 'transport', 'Ride apps 24/7', 1),
(56, 5, 'transport', 'Underground parking', 2),
(57, 5, 'safety', 'Brand-new infrastructure', 0),
(58, 5, 'safety', 'CCTV everywhere', 1),
(59, 5, 'safety', 'Rapid-response security', 2),
(60, 5, 'culture', 'Art & design galleries opening weekly', 0),
(61, 6, 'dining', 'Nairobi Street restaurants', 0),
(62, 6, 'dining', 'Transcorp Hilton dining', 1),
(63, 6, 'dining', 'Jabi Lake eateries', 2),
(64, 6, 'night', 'Diplomatic lounges', 0),
(65, 6, 'night', 'Sunset at Jabi Lake', 1),
(66, 6, 'transport', 'Airport 35 min', 0),
(67, 6, 'transport', 'Chauffeur services', 1),
(68, 6, 'transport', 'Gated estate entry', 2),
(69, 6, 'safety', 'Diplomatic security presence', 0),
(70, 6, 'safety', 'CCTV & patrols', 1),
(71, 6, 'safety', '24/7 concierge', 2),
(72, 6, 'culture', 'National Gallery nearby', 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'check',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `time_label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_flag` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `icon`, `title`, `body`, `time_label`, `read_flag`, `created_at`) VALUES
(1, 1, 'check', 'Booking confirmed', 'Your stay at The Onyx Penthouse (Sep 10–17) is confirmed. Invoice #JL-2026-0912 is ready.', '2h ago', 0, '2026-09-03 14:00:23'),
(2, 1, 'heart', 'Price drop on a wishlist home', 'The Atelier Loft dropped to ₦135,000/night — 13% below your last view.', '5h ago', 0, '2026-09-03 14:00:23'),
(3, 1, 'calendar', 'Check-in in 3 days', 'Digital lock code for Ocean Pearl Suite unlocks Sep 6, 3:00 PM.', '1d ago', 0, '2026-09-03 14:00:23'),
(4, 1, 'spark', 'AI price alert', 'Book Tuesday–Thursday for an estimated 15% saving on The Lagoon Villa.', '1d ago', 1, '2026-09-03 14:00:23'),
(5, 1, 'wallet', 'Payout processed', '₦2,574,000 sent to your Zenith account (ref JL-PO-88731).', '2d ago', 1, '2026-09-03 14:00:23'),
(6, 1, 'star', 'New review received', 'Adaeze O. rated your stay 5.0★ — “impeccable.”', '3d ago', 1, '2026-09-03 14:00:23'),
(7, 1, 'gift', 'Referral bonus earned', 'Tunde K. joined with your code — ₦10,000 credit added.', '4d ago', 1, '2026-09-03 14:00:23'),
(8, 1, 'moon', 'Detty December waitlist', 'You joined the Villa Azur waitlist for Dec 20 – Jan 5. We\'ll alert you instantly.', '5d ago', 1, '2026-09-03 14:00:23'),
(9, 15, 'spark', 'Welcome to Jollof Living ✨', 'Your account is ready. Explore the collections and save your first residence.', 'just now', 1, '2026-09-05 08:36:33'),
(10, 16, 'spark', 'Welcome to Jollof Living ✨', 'Your account is ready. Explore the collections and save your first residence.', 'just now', 0, '2026-09-05 12:34:41'),
(11, 16, 'home', 'Listing submitted', 'The Favored Court is with our verification team — expect an update within 24 hours.', 'just now', 0, '2026-09-05 12:38:03'),
(12, 1, 'check', 'Listing approved ✨', 'The Onyx Penthouse is now live on Jollof Living.', 'just now', 0, '2026-09-05 12:40:24'),
(13, 16, 'shield', 'Account verified ✨', 'Your identity check is complete. Instant booking is now enabled.', 'just now', 0, '2026-09-05 12:47:05'),
(14, 16, 'check', 'Listing approved ✨', 'The Favored Court is now live on Jollof Living.', 'just now', 0, '2026-09-05 14:06:07'),
(15, 17, 'spark', 'Welcome to Jollof Living ✨', 'Your account is ready. Explore the collections and save your first residence.', 'just now', 1, '2026-09-05 16:05:56'),
(16, 17, 'check', 'Booking confirmed', 'Your stay at The Onyx Penthouse (2026-09-14 → 2026-09-15) · invoice JL-2026-8027.', 'just now', 1, '2026-09-05 16:11:02'),
(17, 19, 'spark', 'Welcome to Jollof Living ✨', 'Your account is ready. Explore the collections and save your first residence.', 'just now', 0, '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `page_meta`
--

CREATE TABLE `page_meta` (
  `page_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `page_meta`
--

INSERT INTO `page_meta` (`page_key`, `title`, `description`) VALUES
('404', 'Page Not Found | Jollof Living', 'This address doesn\'t exist - yet.'),
('about', 'About Jollof Living - Luxury Living, African Soul', 'The story, standards and safeguards behind Nigeria\'s premium stay platform.'),
('account', 'My Account | Jollof Living', 'KYC, verification, profile, security and preferences.'),
('admin', 'Back Office | Jollof Living', 'Platform operations - GMV, moderation, users, campaigns, fraud AI, CMS and audit.'),
('admin-login', 'Back Office Sign In | Jollof Living', 'Restricted sign-in for the Jollof Living platform operations console.'),
('app', 'Mobile App | Jollof Living', 'Keyless check-in, live messaging, wallet passes and voice booking.'),
('auth', 'Sign In | Jollof Living', 'Email, phone or social - onboarding takes 90 seconds.'),
('blog', 'The Journal | Jollof Living', 'Travel guides, culture pieces and stories from the Jollof Living editorial desk.'),
('business', 'Jollof for Business | Jollof Living', 'Corporate stays with centralized billing, PO support and travel policy enforcement.'),
('collections', 'Curated Collections | Jollof Living', 'Waterfront escapes, sky penthouses, Abuja executive, heritage homes, romantic retreats and family villas.'),
('compare', 'Compare Residences | Jollof Living', 'Side-by-side pricing, ratings and amenities - up to 3 residences.'),
('concierge', 'Jollof Concierge - AI, 24/7 | Jollof Living', 'Ask about stays, prices, itineraries, transfers and chefs.'),
('confirm', 'Booking Confirmed | Jollof Living', 'Your reservation is confirmed - invoice, check-in details and points earned.'),
('experiences', 'Experiences | Jollof Living', 'Boat cruises, private chefs, spa rituals, art tours, airport transfers, event hosting and VIP shopping.'),
('future', 'Roadmap | Jollof Living', 'Live product roadmap - what\'s shipping, what\'s building, what\'s dreaming.'),
('giftcards', 'Gift Cards | Jollof Living', 'Digital gift cards delivered by email or WhatsApp - never expire.'),
('help', 'Help Centre | Jollof Living', 'Answers, dispute support, emergency assistance and 24/7 live help.'),
('host', 'Host with Jollof Living', 'List your residence, keep 88% - photography, pricing intelligence, guest screening and payouts handled.'),
('host-dashboard', 'Host Dashboard | Jollof Living', 'Earnings, occupancy, calendar, pricing, AI tools, co-hosts and payouts.'),
('host-onboarding', 'Listing Wizard | Jollof Living', 'Guided step-by-step listing creation - save and return anytime.'),
('index', 'Jollof Living - Luxury Living, African Soul', 'Premium luxury apartments in Lagos & Abuja - exclusive short-term and long-term stays with concierge, escrow payments and AI.'),
('map', 'Explore the Map | Jollof Living', 'Interactive map of every Jollof Living residence in Lagos & Abuja with live nightly rates.'),
('membership', 'Jollof Club - Bronze to Platinum | Jollof Living', 'Earn Jollof Points on every stay, climb tiers, redeem rewards and gift cards.'),
('messages', 'Messages | Jollof Living', 'Real-time chat with hosts, support and Jollof.'),
('neighborhoods', 'Neighbourhood Guides | Jollof Living', 'Insider guides to Lagos and Abuja - dining, nightlife, transport and safety.'),
('notifications', 'Notifications | Jollof Living', 'Booking alerts, price drops and promotions.'),
('payments', 'Payments & Payouts | Jollof Living', 'Invoices for guests, earnings and payouts for hosts - NGN, USD, GBP, EUR.'),
('referral', 'Refer & Earn - Give N10,000, Get N10,000 | Jollof Living', 'The most generous referral in Nigerian travel.'),
('reviews', 'Reviews | Jollof Living', 'Verified guest reviews, AI summaries and the platform rules that keep them honest.'),
('stays', 'The Collection - Luxury Stays | Jollof Living', 'Browse exclusive residences across Lekki, Victoria Island, Ikoyi, Banana Island and Abuja - filters, map and instant booking.'),
('trips', 'My Trips | Jollof Living', 'Every reservation, request and stay in one place.'),
('wishlist', 'Wishlist | Jollof Living', 'Named lists, price-drop alerts and sharing.');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `amount` int(11) NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `status` enum('initiated','paid','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initiated',
  `payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `user_id`, `reference`, `gateway`, `amount`, `currency`, `status`, `payload`, `created_at`) VALUES
(1, 1, 17, 'JL-2026-8027-P1', 'record_only', 121260, 'NGN', 'initiated', '{\"method\":\"bank\",\"split\":true}', '2026-09-05 16:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

CREATE TABLE `payouts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int(11) NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `bank` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pay_methods`
--

CREATE TABLE `pay_methods` (
  `mkey` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pay_methods`
--

INSERT INTO `pay_methods` (`mkey`, `name`, `note`, `icon`, `active`, `sort_order`) VALUES
('applepay', 'Apple Pay', 'One touch on iOS', 'lock', 1, 6),
('bank', 'Bank transfer', 'All Nigerian banks', 'building', 1, 3),
('card', 'Credit / debit card', 'Visa, Mastercard, Verve', 'wallet', 1, 0),
('flutterwave', 'Flutterwave', 'Cards, bank, mobile money', 'globe', 1, 2),
('googlepay', 'Google Pay', 'One touch on Android', 'lock', 1, 7),
('mobile', 'Mobile money', 'MTN MoMo, OPay, PalmPay', 'phone', 1, 5),
('paystack', 'Paystack', 'Instant · NGN & USD', 'bolt', 1, 1),
('ussd', 'USSD', '*737# style dial codes', 'phone', 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `points_ledger`
--

CREATE TABLE `points_ledger` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date_label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int(11) NOT NULL DEFAULT '0',
  `kind` enum('earn','redeem') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'earn',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `points_ledger`
--

INSERT INTO `points_ledger` (`id`, `user_id`, `date_label`, `description`, `amount`, `kind`, `created_at`) VALUES
(1, 1, 'Sep 2, 2026', 'Shell Lagoon Experience', -12000, 'redeem', '2026-09-03 14:00:23'),
(2, 1, 'Aug 30, 2026', 'Stay · Ocean Pearl Suite (12n × 12×)', 52560, 'earn', '2026-09-03 14:00:23'),
(3, 1, 'Aug 1, 2026', 'Stay · The Sky Garden Terrace (7n × 12×)', 31200, 'earn', '2026-09-03 14:00:23'),
(4, 1, 'Jul 12, 2026', 'Referral bonus · Tunde K.', 10000, 'earn', '2026-09-03 14:00:23'),
(5, 1, 'Jul 4, 2026', 'Stay · Heritage House (3n × 12×)', 8640, 'earn', '2026-09-03 14:00:23'),
(6, 1, 'Jun 20, 2026', 'Gift purchase · ₦100,000 card', 1000, 'earn', '2026-09-03 14:00:23'),
(7, 17, 'Sep 5, 2026', 'Stay · The Onyx Penthouse (1n)', 1210, 'earn', '2026-09-05 16:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_rules`
--

CREATE TABLE `pricing_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `host_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('seasonal','weekend','lastminute','length','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'seasonal',
  `adjust_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promos`
--

CREATE TABLE `promos` (
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `off` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `flat` int(11) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` date DEFAULT NULL,
  `uses` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promos`
--

INSERT INTO `promos` (`code`, `label`, `off`, `flat`, `active`, `expires_at`, `uses`) VALUES
('JOLLOF10', '10% off your stay', 0.1000, 0, 1, NULL, 0),
('REFER5000', '₦5,000 off', 0.0000, 5000, 1, NULL, 0),
('WELCOME5', '5% off first booking', 0.0500, 0, 1, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'p1',
  `price` int(11) NOT NULL DEFAULT '0',
  `old_price` int(11) DEFAULT NULL,
  `rating` decimal(3,2) NOT NULL DEFAULT '0.00',
  `reviews_count` int(11) NOT NULL DEFAULT '0',
  `beds` decimal(3,1) NOT NULL DEFAULT '1.0',
  `baths` decimal(3,1) NOT NULL DEFAULT '1.0',
  `guests` int(11) NOT NULL DEFAULT '2',
  `ptype` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Apartment',
  `badge` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_gold` tinyint(1) NOT NULL DEFAULT '0',
  `instant` tinyint(1) NOT NULL DEFAULT '1',
  `policy` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'moderate',
  `map_x` decimal(5,3) NOT NULL DEFAULT '0.500',
  `map_y` decimal(5,3) NOT NULL DEFAULT '0.500',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_new` tinyint(1) NOT NULL DEFAULT '0',
  `tour` tinyint(1) NOT NULL DEFAULT '0',
  `floor_label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sold_out` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_summary` text COLLATE utf8mb4_unicode_ci,
  `host_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('draft','pending','live','paused','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `slug`, `name`, `area`, `city`, `img`, `price`, `old_price`, `rating`, `reviews_count`, `beds`, `baths`, `guests`, `ptype`, `badge`, `badge_gold`, `instant`, `policy`, `map_x`, `map_y`, `featured`, `is_new`, `tour`, `floor_label`, `sold_out`, `description`, `ai_summary`, `host_id`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'onyx', 'The Onyx Penthouse', 'Lekki Phase 1', 'Lagos', 'p1', 185000, 210000, 4.97, 128, 3.0, 3.5, 6, 'Penthouse', 'Jollof Verified', 1, 1, 'moderate', 0.290, 0.720, 1, 0, 1, '3 Bed Penthouse', NULL, 'A dark, cinematic penthouse in the sky above Lekki Phase 1. Black marble walls, brass detailing and a private cinema — engineered for quiet drama, with the Lagos skyline flickering beyond 12-foot glass.', 'Guests love the cinema room, the flawless Wi-Fi for video calls, and the concierge\'s speed. A few noted the lift can be slow at peak hours.', 1, 'live', 0, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(2, 'ocean-spearl', 'Ocean Pearl Suite', 'Victoria Island', 'Lagos', 'p2', 145000, NULL, 4.92, 96, 2.0, 2.0, 4, 'Oceanfront Suite', 'Superhost', 0, 1, 'flexible', 0.520, 0.420, 1, 0, 1, '2 Bed Suite', NULL, 'Wake to the Atlantic. A serene two-bedroom suite with a private balcony over the water, soft linen, warm oak and a slow morning light that turns the ocean to silver.', 'Guests consistently praise the sunrise view and digital check-in. The building\'s gym gets a few mentions as small.', 1, 'live', 1, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(3, 'villa-azur', 'Villa Azur', 'Banana Island', 'Lagos', 'p3', 420000, NULL, 4.99, 74, 4.0, 5.0, 8, 'Private Villa', 'Jollof Verified', 1, 0, 'strict', 0.360, 0.560, 1, 0, 1, '4 Bed Villa', '2026-12-20/2027-01-05', 'Banana Island\'s quietest address. A private infinity pool melting into the lagoon, palm-framed terraces, and a villa team on call. This is Nigerian luxury at its most unhurried.', 'Perfect scores across the board. Guests highlight the boat jetty and the invisible-but-present villa team. High demand — book early or join the waitlist.', 1, 'live', 2, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(4, 'sky-garden', 'The Sky Garden Terrace', 'Ikoyi', 'Lagos', 'p4', 210000, NULL, 4.95, 143, 3.0, 3.0, 6, 'Terrace Residence', 'Superhost', 0, 1, 'moderate', 0.470, 0.680, 1, 0, 1, '3 Bed Terrace', NULL, 'A lantern-lit rooftop over Ikoyi. Outdoor lounge, heat lamps, and the whole lagoon as your backdrop — designed for golden-hour dinners and long, warm nights.', 'The terrace is the star — guests mention sunsets constantly. Nightlife watchers note street noise on weekends; earplugs provided.', 1, 'live', 3, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(5, 'maitama', 'Maitama Executive Residence', 'Maitama', 'Abuja', 'p5', 165000, NULL, 4.90, 88, 2.0, 2.5, 4, 'Executive Suite', 'Corporate Ready', 0, 1, 'flexible', 0.750, 0.200, 0, 0, 1, '2 Bed Executive', NULL, 'The address of choice for dignitaries and executives. A calm, brass-lit residence above the green hills of Maitama, minutes from the Central Business District.', 'Corporate guests rate value highly thanks to PO billing and invoicing. The desk and meeting corner get frequent praise; gym access requires a short walk.', 1, 'live', 4, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(6, 'atelier-loft', 'The Atelier Loft', 'Eko Atlantic', 'Lagos', 'p6', 135000, 155000, 4.87, 66, 2.0, 2.0, 4, 'Designer Loft', 'Last-Minute Deal', 1, 1, 'flexible', 0.220, 0.680, 0, 1, 0, '2 Bed Loft', NULL, 'A double-height loft in the city of the future. Raw concrete softened with oak and brass, and Eko Atlantic\'s skyline glowing past floor-to-ceiling glass.', 'Guests call it \'architectural eye candy\'. A few note morning construction noise in the district — the double glazing handles most of it.', 1, 'live', 5, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(7, 'heritage-house', 'Heritage House Lagos', 'Old Ikoyi', 'Lagos', 'p7', 120000, NULL, 4.93, 112, 3.0, 2.0, 6, 'Heritage Stay', 'Cultural Heritage', 0, 0, 'moderate', 0.500, 0.620, 0, 0, 1, '3 Bed Courtyard House', NULL, 'A lovingly restored 1950s Ikoyi courtyard house — shutters, terrazzo and antique woods, layered with contemporary comfort and Nigerian art on every wall.', 'Guests describe it as a museum that is also home. The courtyard breakfasts, curated art and the house tour are mentioned in most reviews.', 1, 'live', 6, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(8, 'lagoon-villa', 'The Lagoon Villa', 'Victoria Island', 'Lagos', 'hero', 175000, NULL, 4.96, 151, 3.0, 3.5, 6, 'Waterfront Villa', 'Waterfront Escapes', 0, 1, 'moderate', 0.440, 0.500, 1, 0, 1, '3 Bed Villa', NULL, 'Glass-walled living above the lagoon. An emerald-and-gold great room that turns into a lantern at dusk, with the city shimmering across the water.', 'The dock at sunset is the most-photographed spot in the portfolio. Generous for families; split payments made two-month stays popular with relocators.', 1, 'live', 7, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(9, 'lagoon-duplex', 'Lekki Lagoon Duplex', 'Lekki Phase 1', 'Lagos', 'p10', 165000, NULL, 4.88, 59, 4.0, 4.5, 8, 'Furnished Townhouse', 'Family Favourite', 0, 1, 'moderate', 0.310, 0.780, 0, 1, 1, '4 Bed Duplex', NULL, 'A warm, wood-screened duplex with a plunge-pool courtyard — made for families and long-term comfort. Quiet street, generous living, thoughtful storage.', 'Families love the playroom and pantry. Excellent value for 30+ nights — monthly pricing with -25% plus housekeeping keeps long stays effortless.', 1, 'live', 8, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(10, 'eko-icon', 'The Eko Icon Suite', 'Eko Atlantic', 'Lagos', 'p11', 95000, NULL, 4.85, 41, 1.0, 1.5, 2, 'Hotel Suite', 'New This Season', 0, 1, 'flexible', 0.250, 0.620, 0, 1, 0, '1 Bed Suite', NULL, 'A boutique one-bedroom suite with soft champagne tones and city views — the smart, elegant base for a weekend in Eko Atlantic. Hotel services, apartment privacy.', 'Effortless for short stays — guests praise the 24/7 reception and rain shower. It books fast on event weekends.', 1, 'live', 9, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(11, 'abuja-sky', 'Abuja Sky Residence', 'Maitama', 'Abuja', 'p9', 190000, NULL, 4.91, 77, 3.0, 3.0, 5, 'Penthouse', 'Corporate Ready', 0, 0, 'strict', 0.790, 0.260, 0, 0, 1, '3 Bed Penthouse', NULL, 'High above the green hills of Abuja — a charcoal-and-brass penthouse with sunset terraces on two sides and the skyline of the capital at your feet.', 'Terraces and privacy are the recurring love letters. A couple of guests asked for earlier check-in — late checkout is available on request.', 1, 'live', 10, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(12, 'island-retreat', 'The Island Retreat', 'Banana Island', 'Lagos', 'p12', 240000, NULL, 4.94, 63, 3.0, 3.0, 6, 'Garden Villa', 'Jollof Verified', 1, 0, 'moderate', 0.340, 0.500, 0, 0, 1, '3 Bed Garden Villa', NULL, 'A garden villa wrapped in palms on the most exclusive island in West Africa — daybeds by a still reflecting pool, birdsong mornings and complete, guarded calm.', 'The garden is the soul of the house — guests describe it as \'a private botanical resort\'. Chef bookings sell out, so reserve ahead.', 1, 'live', 11, '2026-09-03 14:00:23', '2026-09-03 14:00:23'),
(13, 'the-favored-court', 'The Favored Court', 'Asokoro', 'Abuja', 'p1', 150000, NULL, 0.00, 0, 4.0, 5.0, 4, 'Apartment', NULL, 0, 0, 'moderate', 0.500, 0.500, 0, 0, 0, NULL, NULL, 'The favored Court is an exquisitely furnished 4 bedroom apartment. The favored Court is an exquisitely furnished 4 bedroom apartment. The favored Court is an exquisitely furnished 4 bedroom apartment.', NULL, 16, 'live', 0, '2026-09-05 12:38:03', '2026-09-05 12:38:03'),
(14, 'grand-patreon-villa-93323', 'Grand Patreon Villa', 'Ikoyi', 'Lagos', 'p1', 200000, NULL, 0.00, 0, 3.0, 3.0, 6, 'Apartment', NULL, 0, 1, 'moderate', 0.500, 0.500, 0, 0, 0, NULL, NULL, 'The best villas. The best villas. The best villas. The best villas. The best villas. The best villas.', NULL, 14, 'pending', 0, '2026-09-06 06:15:23', '2026-09-06 06:15:23');

-- --------------------------------------------------------

--
-- Table structure for table `property_amenities`
--

CREATE TABLE `property_amenities` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `amenity` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_amenities`
--

INSERT INTO `property_amenities` (`id`, `property_id`, `amenity`, `sort_order`) VALUES
(1, 1, 'Infinity pool', 0),
(2, 1, 'Private cinema', 1),
(3, 1, 'Smart home', 2),
(4, 1, 'Gym', 3),
(5, 1, 'Rooftop terrace', 4),
(6, 1, 'Chef\'s kitchen', 5),
(7, 1, 'Fast Wi-Fi (1Gbps)', 6),
(8, 1, '24/7 security', 7),
(9, 1, 'Backup generator', 8),
(10, 1, 'Concierge on call', 9),
(11, 2, 'Ocean view balcony', 0),
(12, 2, 'King beds', 1),
(13, 2, 'Daily housekeeping', 2),
(14, 2, 'Smart TV + Netflix', 3),
(15, 2, 'Fast Wi-Fi', 4),
(16, 2, 'Backup power', 5),
(17, 2, 'Airport pickup available', 6),
(18, 2, 'Espresso bar', 7),
(19, 3, 'Private infinity pool', 0),
(20, 3, 'Lagoon frontage', 1),
(21, 3, 'Full villa team', 2),
(22, 3, 'Wine cellar', 3),
(23, 3, 'Poolside bar', 4),
(24, 3, 'Private gym', 5),
(25, 3, 'Event hosting', 6),
(26, 3, 'Boat jetty', 7),
(27, 3, 'Smart home', 8),
(28, 3, 'Secure parking', 9),
(29, 4, 'Rooftop terrace', 0),
(30, 4, 'Outdoor lounge', 1),
(31, 4, 'Lagoon view', 2),
(32, 4, 'BBQ + pizza oven', 3),
(33, 4, 'Wine fridge', 4),
(34, 4, 'Backup generator', 5),
(35, 4, 'Smart locks', 6),
(36, 4, 'Sonos sound', 7),
(37, 5, 'Hill view', 0),
(38, 5, 'Boss-friendly desk', 1),
(39, 5, 'Meeting corner', 2),
(40, 5, 'Fast fibre Wi-Fi', 3),
(41, 5, 'Invoicing & PO billing', 4),
(42, 5, 'Backup power', 5),
(43, 5, 'Airport transfer', 6),
(44, 5, 'Tea & coffee bar', 7),
(45, 6, 'Double-height glass', 0),
(46, 6, 'Designer kitchen', 1),
(47, 6, 'City skyline view', 2),
(48, 6, 'Gym access', 3),
(49, 6, 'Smart home', 4),
(50, 6, 'Fast Wi-Fi', 5),
(51, 6, 'Valet parking', 6),
(52, 6, 'Balcony', 7),
(53, 7, 'Courtyard garden', 0),
(54, 7, 'Curated art collection', 1),
(55, 7, 'Heritage architecture', 2),
(56, 7, 'Outdoor dining', 3),
(57, 7, 'Cook available', 4),
(58, 7, 'Library', 5),
(59, 7, 'Fast Wi-Fi', 6),
(60, 7, 'Board games', 7),
(61, 8, 'Lagoon frontage', 0),
(62, 8, 'Floor-to-ceiling glass', 1),
(63, 8, 'Private dock', 2),
(64, 8, 'Chef\'s kitchen', 3),
(65, 8, 'Home cinema', 4),
(66, 8, 'Gym', 5),
(67, 8, 'Concierge on call', 6),
(68, 8, 'Paddle boards', 7),
(69, 9, 'Plunge pool courtyard', 0),
(70, 9, '4 ensuite bedrooms', 1),
(71, 9, 'Full kitchen + pantry', 2),
(72, 9, 'Playroom', 3),
(73, 9, 'Office nook', 4),
(74, 9, 'Fast Wi-Fi', 5),
(75, 9, 'Secure parking for 3', 6),
(76, 9, 'Housekeeping available', 7),
(77, 10, 'Ocean-facing view', 0),
(78, 10, 'Hotel services', 1),
(79, 10, 'Smart TV', 2),
(80, 10, 'Rain shower', 3),
(81, 10, 'Nespresso', 4),
(82, 10, 'Fast Wi-Fi', 5),
(83, 10, 'Daily housekeeping', 6),
(84, 10, '24/7 reception', 7),
(85, 11, 'Twin terraces', 0),
(86, 11, 'Hill & skyline views', 1),
(87, 11, 'Master suite + dressing', 2),
(88, 11, 'Chef\'s kitchen', 3),
(89, 11, 'Meeting corner', 4),
(90, 11, 'Fibre Wi-Fi', 5),
(91, 11, 'Gym access', 6),
(92, 11, 'Airport transfer', 7),
(93, 11, 'Backup power', 8),
(94, 12, 'Private garden', 0),
(95, 12, 'Reflecting pool', 1),
(96, 12, 'Daybeds & hammocks', 2),
(97, 12, 'Outdoor rain shower', 3),
(98, 12, 'Chef available', 4),
(99, 12, 'Reading room', 5),
(100, 12, 'Smart home', 6),
(101, 12, 'Estate security', 7),
(102, 12, 'Boat access', 8),
(103, 13, 'Fast Wi-Fi', 0),
(104, 13, 'Gym', 1),
(105, 13, 'Pool', 2),
(106, 13, 'Kitchen', 3),
(107, 13, 'Air conditioning', 4),
(108, 13, 'Smart home', 5),
(109, 13, 'Security', 6),
(110, 13, 'Backup power', 7),
(111, 13, 'Parking', 8),
(112, 13, 'Breakfast', 9),
(113, 13, 'Washing machine', 10),
(114, 13, 'Workspace', 11);

-- --------------------------------------------------------

--
-- Table structure for table `property_calendar`
--

CREATE TABLE `property_calendar` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `day` date NOT NULL,
  `price` int(11) DEFAULT NULL,
  `status` enum('open','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `note` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

CREATE TABLE `property_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `path`, `caption`, `sort_order`) VALUES
(1, 13, 'sample-living.jpg', NULL, 0),
(2, 13, 'sample-garden.jpg', NULL, 1),
(3, 13, 'sample-bath.jpg', NULL, 2),
(4, 13, 'sample-bedroom.jpg', NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `property_nearby`
--

CREATE TABLE `property_nearby` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `place` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `distance` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_nearby`
--

INSERT INTO `property_nearby` (`id`, `property_id`, `place`, `distance`, `sort_order`) VALUES
(1, 1, 'Ocean Basket Restaurant', '1.2 km', 0),
(2, 1, 'Landmark Beach', '3.4 km', 1),
(3, 1, 'Lekki Conservation Centre', '6.1 km', 2),
(4, 1, 'Palms Mall', '2.0 km', 3),
(5, 1, 'Nike Art Gallery', '4.6 km', 4),
(6, 2, 'Eko Hotel & Suites', '1.5 km', 0),
(7, 2, 'Landmark Event Centre', '2.8 km', 1),
(8, 2, 'Bar Beach', '1.1 km', 2),
(9, 2, 'Cubana Lagos', '2.2 km', 3),
(10, 3, 'Banana Island Shopping Court', '1.0 km', 0),
(11, 3, 'Ikoyi Golf Club', '4.2 km', 1),
(12, 3, 'The Wheatbaker', '5.0 km', 2),
(13, 3, 'Nike Art Gallery', '6.5 km', 3),
(14, 4, 'Awolowo Road boutique strip', '1.4 km', 0),
(15, 4, 'The George Hotel', '0.9 km', 1),
(16, 4, 'Ikoyi Golf Club', '2.3 km', 2),
(17, 4, 'Rooftop M Restaurant', '1.1 km', 3),
(18, 5, 'Transcorp Hilton', '2.6 km', 0),
(19, 5, 'World Trade Centre', '3.1 km', 1),
(20, 5, 'Jabi Lake Mall', '6.0 km', 2),
(21, 5, 'Sheraton Abuja', '4.4 km', 3),
(22, 6, 'Eko Atlantic Boulevard', '0.4 km', 0),
(23, 6, 'Azure Beach', '2.0 km', 1),
(24, 6, 'City Mall, VI', '3.6 km', 2),
(25, 6, 'Eko Hotel', '3.9 km', 3),
(26, 7, 'Freedom Park', '2.8 km', 0),
(27, 7, 'Nike Art Gallery', '3.2 km', 1),
(28, 7, 'Cathedral Church of Christ', '3.0 km', 2),
(29, 7, 'Terra Kulture', '2.4 km', 3),
(30, 8, 'Lagos Yacht Club', '1.8 km', 0),
(31, 8, 'Landmark Beach', '3.0 km', 1),
(32, 8, 'Nike Art Gallery', '5.2 km', 2),
(33, 8, 'Circular Bar, VI', '1.6 km', 3),
(34, 9, 'Nike Art Gallery', '1.9 km', 0),
(35, 9, 'Lekki Market', '1.2 km', 1),
(36, 9, 'Landmark Beach', '5.0 km', 2),
(37, 9, 'Casa Chianti', '0.8 km', 3),
(38, 10, 'Eko Atlantic Promenade', '0.3 km', 0),
(39, 10, 'Azure Beach', '2.1 km', 1),
(40, 10, 'Palms Mall', '3.4 km', 2),
(41, 11, 'Transcorp Hilton', '2.2 km', 0),
(42, 11, 'Nairobi Street restaurants', '1.5 km', 1),
(43, 11, 'Jabi Lake', '5.8 km', 2),
(44, 11, 'City Gate', '3.0 km', 3),
(45, 12, 'Banana Island Shopping Court', '0.8 km', 0),
(46, 12, 'Ikoyi Golf Club', '4.5 km', 1),
(47, 12, 'Villa Cinemas', '5.4 km', 2);

-- --------------------------------------------------------

--
-- Table structure for table `property_scores`
--

CREATE TABLE `property_scores` (
  `property_id` int(10) UNSIGNED NOT NULL,
  `cleanliness` decimal(3,2) NOT NULL DEFAULT '0.00',
  `accuracy` decimal(3,2) NOT NULL DEFAULT '0.00',
  `communication` decimal(3,2) NOT NULL DEFAULT '0.00',
  `location` decimal(3,2) NOT NULL DEFAULT '0.00',
  `checkin` decimal(3,2) NOT NULL DEFAULT '0.00',
  `value_score` decimal(3,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_scores`
--

INSERT INTO `property_scores` (`property_id`, `cleanliness`, `accuracy`, `communication`, `location`, `checkin`, `value_score`) VALUES
(1, 4.90, 5.00, 5.00, 4.90, 5.00, 4.80),
(2, 4.90, 4.90, 4.90, 5.00, 4.90, 4.80),
(3, 5.00, 5.00, 5.00, 5.00, 5.00, 5.00),
(4, 4.90, 4.90, 5.00, 4.90, 5.00, 4.90),
(5, 4.80, 4.90, 4.90, 5.00, 4.90, 4.80),
(6, 4.80, 4.90, 4.80, 4.90, 4.90, 4.70),
(7, 4.90, 4.90, 5.00, 4.90, 4.90, 4.90),
(8, 4.90, 5.00, 4.90, 4.90, 5.00, 4.90),
(9, 4.80, 4.90, 4.90, 4.80, 4.90, 4.90),
(10, 4.90, 4.80, 4.90, 4.80, 4.90, 4.70),
(11, 4.90, 4.90, 4.90, 5.00, 4.80, 4.80),
(12, 4.90, 5.00, 4.90, 5.00, 4.90, 4.90);

-- --------------------------------------------------------

--
-- Table structure for table `recent_views`
--

CREATE TABLE `recent_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recent_views`
--

INSERT INTO `recent_views` (`id`, `user_id`, `session_key`, `property_id`, `viewed_at`) VALUES
(5, 14, NULL, 2, '2026-09-03 16:22:08'),
(7, NULL, '08aba883fcc5632224334417694d3542', 1, '2026-09-03 16:57:21'),
(8, 14, NULL, 1, '2026-09-04 01:49:26'),
(9, 17, NULL, 1, '2026-09-05 16:07:34'),
(10, NULL, '6a875672f691990ac5d7313c7b48f1ff', 1, '2026-09-07 12:09:56'),
(11, NULL, '6a875672f691990ac5d7313c7b48f1ff', 4, '2026-09-07 12:10:28'),
(12, NULL, '5f6da0c128c8de98b33b9a4e679e0dcc', 1, '2026-09-09 03:14:39');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `friend_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invited',
  `credit` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_ref` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` decimal(2,1) NOT NULL DEFAULT '5.0',
  `scores` text COLLATE utf8mb4_unicode_ci,
  `status` enum('published','pending','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `property_id`, `user_id`, `booking_ref`, `author`, `meta`, `body`, `rating`, `scores`, `status`, `created_at`) VALUES
(1, 1, NULL, NULL, 'Adaeze O.', 'Lagos · Apr 2026', 'The cinema room alone is worth it. Spotless, concierge arranged a private chef — impeccable.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(2, 1, NULL, NULL, 'James K.', 'London · Jun 2026', 'Feels like a five-star hotel floor, but entirely yours. Escrow payment gave total peace of mind.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(3, 1, NULL, NULL, 'Chuka E.', 'Abuja · Aug 2026', 'Smart-home everything, blackout blinds, flawless Wi-Fi for calls. Best stay I\'ve had in Lagos.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(4, 2, NULL, NULL, 'Funmi A.', 'Lagos · Mar 2026', 'The balcony view at sunrise is unbeatable. Concierge had a boat booked in ten minutes.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(5, 2, NULL, NULL, 'Daniel M.', 'Accra · Jun 2026', 'Spotless, quiet, and the digital check-in meant midnight arrival was effortless.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(6, 2, NULL, NULL, 'Sarah B.', 'New York · Aug 2026', 'Long-stay discount + split payment made a month here very comfortable.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(7, 3, NULL, NULL, 'Bolanle T.', 'Lagos · Feb 2026', 'We hosted a small engagement here. The team was invisible until we needed them — flawless.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(8, 3, NULL, NULL, 'Hans W.', 'Berlin · May 2026', 'The best villa we\'ve stayed at anywhere in Africa. The boat jetty is a touch of genius.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(9, 3, NULL, NULL, 'Zainab M.', 'Dubai · Jul 2026', 'Escrow release only after check-in confirmation — exactly how luxury platforms should work.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(10, 4, NULL, NULL, 'Tobi F.', 'Lagos · Jan 2026', 'Sunsets from this terrace should be illegal. One of Lagos\' best-kept secrets.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(11, 4, NULL, NULL, 'Anna P.', 'Paris · Apr 2026', 'Beautiful, airy, and the welcome package with chin-chin and wine was so thoughtful.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(12, 4, NULL, NULL, 'Emeka N.', 'Enugu · Aug 2026', 'Booked via the AI concierge — three messages and it was done.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(13, 5, NULL, NULL, 'Ngozi U.', 'Abuja · May 2026', 'Corporate billing with PO numbers was seamless for our team\'s month-long project.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(14, 5, NULL, NULL, 'Robert S.', 'Nairobi · Jul 2026', 'The quietest executive stay in Abuja. Wi-Fi never blinked during two weeks of calls.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(15, 5, NULL, NULL, 'Hadiza Y.', 'Kano · Aug 2026', 'Airport pickup, check-in, everything arranged by message. Effortless.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(16, 6, NULL, NULL, 'Kelechi O.', 'Lagos · Aug 2026', 'Architectural eye candy, and 15% off for booking within 48 hours of check-in.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(17, 6, NULL, NULL, 'Grace L.', 'Accra · Aug 2026', 'The loft has a gallery feel. Concierge recommended an amazing rooftop dinner nearby.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(18, 6, NULL, NULL, 'Uche A.', 'Lagos · Sep 2026', 'Checking in took ninety seconds with the keyless code.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(19, 7, NULL, NULL, 'Yewande S.', 'Lagos · Mar 2026', 'Stayed two nights and felt like I\'d been to a museum that also happens to be home.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(20, 7, NULL, NULL, 'Olivia R.', 'Cape Town · Jun 2026', 'The courtyard breakfasts were the highlight of my Nigeria trip.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(21, 7, NULL, NULL, 'Ibrahim G.', 'Kano · Aug 2026', 'Beautiful, storied, immaculate. The house tour was a lovely touch.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(22, 8, NULL, NULL, 'Amina Z.', 'Abuja · Feb 2026', 'We watched the sunset from the dock every evening. Pure magic.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(23, 8, NULL, NULL, 'Tom H.', 'London · May 2026', 'The AI review summary said guests love the view — it undersold it.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(24, 8, NULL, NULL, 'Chioma P.', 'Lagos · Jul 2026', 'Split payments made a two-month stay painless. Felt like a resident.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(25, 9, NULL, NULL, 'Damilola A.', 'Lagos · Aug 2026', 'We moved in for six weeks between homes. It absorbed our whole family life beautifully.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(26, 9, NULL, NULL, 'Kate O.', 'Ibadan · Sep 2026', 'The courtyard pool saved our summer. Kids never wanted to leave.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(27, 9, NULL, NULL, 'Femi S.', 'Abuja · Sep 2026', 'Secure parking, backup power, calm street. Long-stay done right.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(28, 10, NULL, NULL, 'Bella E.', 'Lagos · Sep 2026', 'Perfect weekend base. The reception team is lovely and the bed is cloud-soft.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(29, 10, NULL, NULL, 'Ahmed B.', 'Kano · Sep 2026', 'Check-in took two minutes. Great Wi-Fi, great shower, great value.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(30, 10, NULL, NULL, 'Rita N.', 'Port Harcourt · Sep 2026', 'Eko Atlantic\'s best budget-luxury secret.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(31, 11, NULL, NULL, 'Moses A.', 'Abuja · Jun 2026', 'Sunset on the west terrace, briefing in the meeting corner. This is the executive life.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(32, 11, NULL, NULL, 'Claire D.', 'London · Jul 2026', 'The most beautiful home I\'ve stayed in in West Africa. Immaculate.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(33, 11, NULL, NULL, 'Yakubu M.', 'Kaduna · Sep 2026', 'Quiet, secure, elegant. The booking request was confirmed within an hour.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(34, 12, NULL, NULL, 'Oluwaseun F.', 'Lagos · Apr 2026', 'Birdsong mornings, pool reflections, total privacy. A quiet kind of luxury.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(35, 12, NULL, NULL, 'Marie L.', 'Paris · Jul 2026', 'The rainshower under the palms is unforgettable. Villa team was superb.', 5.0, NULL, 'published', '2026-09-03 14:00:23'),
(36, 12, NULL, NULL, 'Tunde K.', 'Lagos · Sep 2026', 'Booked the chef for a birthday lunch. Effortless, memorable, immaculate.', 5.0, NULL, 'published', '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `roadmap`
--

CREATE TABLE `roadmap` (
  `id` int(10) UNSIGNED NOT NULL,
  `phase` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'soon',
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'soon',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roadmap`
--

INSERT INTO `roadmap` (`id`, `phase`, `title`, `description`, `status`, `sort_order`) VALUES
(1, 'live', 'Jollof Living for Business', 'Corporate travel portal with centralized billing, travel policy enforcement and team dashboards.', 'live', 0),
(2, 'dev', 'Jollof Living Experiences', 'A standalone marketplace for local experiences, separate from property stays.', 'dev', 1),
(3, 'dev', 'Jollof Living Homes', 'Six-to-twelve-month furnished rentals for relocating professionals.', 'dev', 2),
(4, 'dev', 'Jollof Living Invest', 'Fractional property investment — own a share of luxury residences and earn rental income.', 'dev', 3),
(5, 'soon', 'AR Property Previews', 'Augmented-reality walk-throughs through your phone camera.', 'soon', 4),
(6, 'soon', 'Blockchain Verification', 'Immutable ownership and review verification on-chain.', 'soon', 5),
(7, 'soon', 'IoT Smart Monitoring', 'Real-time noise, energy and occupancy monitoring for hosts.', 'soon', 6),
(8, 'soon', 'Jollof Living Magazine', 'A digital lifestyle publication showcasing African luxury living.', 'soon', 7),
(9, 'soon', 'The Jollof Card', 'Co-branded debit card with cashback on bookings and partner perks.', 'soon', 8),
(10, 'dev', 'Carbon Offset Programme', 'Offset your travel footprint at checkout — verified projects.', 'dev', 9);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `skey` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `svalue` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`skey`, `svalue`, `updated_at`) VALUES
('contact_email', 'hello@jollofliving.com', '2026-09-03 14:00:23'),
('contact_phone', '+234 800 000 0000', '2026-09-03 14:00:23'),
('default_currency', 'NGN', '2026-09-03 14:00:23'),
('host_take_rate', '0.12', '2026-09-03 14:00:23'),
('installed_at', '2026-09-03T20:00:23+01:00', '2026-09-03 14:00:23'),
('rate_cleaning', '15000', '2026-09-03 14:00:23'),
('rate_deposit', '0.2', '2026-09-03 14:00:23'),
('rate_insurance', '0.03', '2026-09-03 14:00:23'),
('rate_monthlyDisc', '0.25', '2026-09-03 14:00:23'),
('rate_service', '0.08', '2026-09-03 14:00:23'),
('rate_vat', '0.075', '2026-09-03 14:00:23'),
('rate_weeklyDisc', '0.12', '2026-09-03 14:00:23'),
('site_domain', 'www.jollofliving.com', '2026-09-03 14:00:23'),
('site_name', 'Jollof Living', '2026-09-03 14:00:23'),
('site_tagline', 'Luxury Living, African Soul', '2026-09-03 14:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `subscribers`
--

CREATE TABLE `subscribers` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sync_operations`
--

CREATE TABLE `sync_operations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `client_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'done',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sync_operations`
--

INSERT INTO `sync_operations` (`id`, `user_id`, `client_key`, `endpoint`, `response`, `status`, `created_at`) VALUES
(1, 14, '1788693320851-s4umym65', 'listing-create', '{\"ok\":true,\"message\":\"Listing submitted — our team will verify within 24h ✨\",\"data\":{\"slug\":\"grand-patreon-villa-93323\",\"id\":14}}', 'done', '2026-09-06 06:15:23');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(10) UNSIGNED NOT NULL,
  `author` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `author`, `meta`, `quote`, `active`, `sort_order`) VALUES
(1, 'Adaeze O.', 'The Onyx Penthouse · Lekki', 'From the airport pickup to the private chef, everything felt considered. This is how luxury travel should work — with African soul.', 1, 0),
(2, 'James K.', 'Villa Azur · Banana Island', 'I\'ve used the big platforms in thirty countries. Nothing matches this finish, this service, or this trust in payments.', 1, 1),
(3, 'Ngozi U.', 'Maitama Executive · Abuja', 'Our company books a month of suites here. Corporate invoicing, split payments, zero friction.', 1, 2),
(4, 'Tobi F.', 'The Sky Garden Terrace · Ikoyi', 'The AI concierge planned our whole weekend — boat, chef, late check-out. Three messages, done.', 1, 3),
(5, 'Hans W.', 'Ocean Pearl · Victoria Island', 'The escrow system means you pay with confidence. Check-in confirmation released the host\'s funds — it just works.', 1, 4),
(6, 'Yewande S.', 'Heritage House · Old Ikoyi', 'An old Lagos house, restored perfectly. I felt the culture in a way no hotel could ever give me.', 1, 5),
(7, 'Kelechi O.', 'The Atelier Loft · Eko Atlantic', 'Booked the last-minute deal two days before arriving. Best decision of the trip.', 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `tiers`
--

CREATE TABLE `tiers` (
  `tkey` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `letter` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requirement` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points_label` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_points` int(11) NOT NULL DEFAULT '0',
  `multiplier` int(11) NOT NULL DEFAULT '5',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiers`
--

INSERT INTO `tiers` (`tkey`, `letter`, `name`, `requirement`, `points_label`, `min_points`, `multiplier`, `featured`, `sort_order`) VALUES
('bronze', 'B', 'Bronze', 'Every member', '0+', 0, 5, 0, 0),
('gold', 'G', 'Gold', '50,000 points', '50k+', 50000, 12, 1, 2),
('platinum', 'P', 'Platinum', '150,000 points', '150k+', 150000, 15, 0, 3),
('silver', 'S', 'Silver', '10,000 points', '10k+', 10000, 8, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tier_perks`
--

CREATE TABLE `tier_perks` (
  `id` int(10) UNSIGNED NOT NULL,
  `tkey` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perk` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tier_perks`
--

INSERT INTO `tier_perks` (`id`, `tkey`, `perk`, `sort_order`) VALUES
(1, 'bronze', 'Earn 5× Jollof Points per stay', 0),
(2, 'bronze', 'Wishlists & price-drop alerts', 1),
(3, 'bronze', 'Standard concierge support', 2),
(4, 'bronze', 'Welcome baskets on select stays', 3),
(5, 'silver', 'Earn 8× Jollof Points', 0),
(6, 'silver', 'Late check-out on request', 1),
(7, 'silver', 'Priority support line', 2),
(8, 'silver', 'Monthly members-only deals', 3),
(9, 'gold', 'Earn 12× Jollof Points', 0),
(10, 'gold', 'Complimentary upgrades', 1),
(11, 'gold', 'Free airport transfers', 2),
(12, 'gold', 'Early access to new listings', 3),
(13, 'platinum', 'Earn 15× Jollof Points', 0),
(14, 'platinum', 'Dedicated account manager', 1),
(15, 'platinum', 'Personal shopper & errand service', 2),
(16, 'platinum', 'VIP event invitations', 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('guest','host','corporate','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guest',
  `is_host` tinyint(1) NOT NULL DEFAULT '0',
  `tier` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bronze',
  `points` int(11) NOT NULL DEFAULT '0',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Verified',
  `status_level` enum('ok','warn','bad') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `kyc_verified` tinyint(1) NOT NULL DEFAULT '0',
  `referral_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `is_host`, `tier`, `points`, `avatar`, `city`, `status`, `status_level`, `kyc_verified`, `referral_code`, `last_login_at`, `created_at`) VALUES
(1, 'Adebayo Ogunlesi', 'adebayo@jollofliving.com', '+234 801 000 0001', '$2y$10$e0NRl6nZ2cXjTuJ9Ii9K1uV1oSJ2xg2Yy3s3zXqf7z0eG7HpB2mFa', 'guest', 1, 'gold', 62400, NULL, 'Lagos', 'Verified', 'ok', 1, 'ADEBAYO10', NULL, '2026-09-03 14:00:23'),
(2, 'Jollof Operations', 'ops@jollofliving.com', '+234 801 000 0000', '$2y$10$e0NRl6nZ2cXjTuJ9Ii9K1uV1oSJ2xg2Yy3s3zXqf7z0eG7HpB2mFa', 'admin', 0, 'platinum', 0, NULL, 'Lagos', 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(3, 'Adaeze Okonkwo', 'adaeze.okonkwo@example.com', NULL, NULL, 'guest', 0, 'gold', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(4, 'James Kepler', 'james.kepler@example.com', NULL, NULL, 'guest', 0, 'silver', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(5, 'Ngozi Ude', 'ngozi.ude@example.com', NULL, NULL, 'corporate', 0, 'platinum', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(6, 'Tunde Bakare', 'tunde.bakare@example.com', NULL, NULL, 'host', 1, 'gold', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(7, 'Hans Weber', 'hans.weber@example.com', NULL, NULL, 'guest', 0, 'bronze', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(8, 'Chioma P', 'chioma.p@example.com', NULL, NULL, 'guest', 0, 'gold', 0, NULL, NULL, 'Pending ID', 'warn', 0, NULL, NULL, '2026-09-03 14:00:23'),
(9, 'Moses Adeyemi', 'moses.adeyemi@example.com', NULL, NULL, 'host', 1, 'silver', 0, NULL, NULL, 'Flagged · 2FA off', 'warn', 0, NULL, NULL, '2026-09-03 14:00:23'),
(10, 'Yewande Sola', 'yewande.sola@example.com', NULL, NULL, 'guest', 0, 'gold', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(11, 'Femi Scores', 'femi.scores@example.com', NULL, NULL, 'host', 1, 'bronze', 0, NULL, NULL, 'Under review', 'warn', 0, NULL, NULL, '2026-09-03 14:00:23'),
(12, 'Zainab M', 'zainab.m@example.com', NULL, NULL, 'guest', 0, 'platinum', 0, NULL, NULL, 'Verified', 'ok', 1, NULL, NULL, '2026-09-03 14:00:23'),
(13, 'Idris Yusuf', 'idris.yusuf@example.com', NULL, NULL, 'guest', 0, 'bronze', 0, NULL, NULL, 'Suspended · dispute', 'bad', 0, NULL, NULL, '2026-09-03 14:00:23'),
(14, 'yungchuck', 'jollofliving@yahoo.com', NULL, '$2y$10$MAWW7pu9YflW3D/3XxJKRuTndlNf8aU6mQyEon96lXwSglsNLolQC', 'admin', 0, 'platinum', 0, NULL, NULL, 'Verified', 'ok', 1, '9D1B749E', '2026-09-12 01:55:22', '2026-09-03 14:00:23'),
(15, 'Uche Keith-obi', 'scie.techie@outlook.com', '+23419293826339', '$2y$10$PXGPY4rrLkfRZbujcDmsVu4t.kn6d.yf.i3c9xWbn3QLvktPmgpum', 'guest', 0, 'bronze', 0, NULL, NULL, 'Pending ID', 'warn', 0, 'UCHEKEITHOBI57', '2026-09-05 14:36:33', '2026-09-05 08:36:33'),
(16, 'Rapper Sane', 'info@tamsluxe.com', '+2349038644534', '$2y$10$s9lx.bRe4/3lPq0lVu9io.MHRLexCaMIMlcQa0342q8fm4VO66cWG', 'host', 1, 'bronze', 0, NULL, NULL, 'Verified', 'warn', 1, 'RAPPERSANE58', '2026-09-05 18:34:41', '2026-09-05 12:34:41'),
(17, 'Riverton Adeboye', 'local@gmail.com', '+2347077249425', '$2y$10$6gGti0KcT.d/kqgN2t2cJeIDWGsauiQiE./LqSWOry0nHHe9vcTG2', 'host', 1, 'bronze', 1210, NULL, NULL, 'Pending ID', 'warn', 0, 'RIVERTONADEBOYE33', '2026-09-07 18:50:45', '2026-09-05 16:05:56'),
(18, 'Check man', 'gtg@ytyt.bg', '08033357235', '$2y$10$kROzdvTpstJ9cYLCIL/BL.5q8BXyuyG8C2T/19YYF0YUmauehLeF6', 'guest', 0, 'bronze', 0, NULL, NULL, 'Pending ID', 'warn', 0, 'CHECKMAN36', NULL, '2026-09-05 18:26:18'),
(19, 'Adebayo Adeale', 'look@yahoo.com', '23433355522', '$2y$10$XBfQi4yRZT.Tpta6VgZS2uufikIdr0up.7r7oQhV.pqBawLdYCqwq', 'host', 1, 'bronze', 0, NULL, NULL, 'Pending ID', 'warn', 0, 'ADEBAYOADEALE66', '2026-09-11 17:49:14', '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_prefs`
--

CREATE TABLE `user_prefs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `pref_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pref_value` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `waitlist`
--

CREATE TABLE `waitlist` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'My wishlist',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `slug`, `name`, `created_at`) VALUES
(1, 14, 'default', 'My wishlist', '2026-09-03 14:02:03'),
(2, 15, 'default', 'My wishlist', '2026-09-05 08:36:33'),
(3, 16, 'default', 'My wishlist', '2026-09-05 12:34:41'),
(4, 17, 'default', 'My wishlist', '2026-09-05 16:05:56'),
(5, 18, 'default', 'My wishlist', '2026-09-05 18:26:18'),
(6, 19, 'default', 'My wishlist', '2026-09-11 11:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `wishlist_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist_items`
--

INSERT INTO `wishlist_items` (`wishlist_id`, `property_id`, `created_at`) VALUES
(1, 1, '2026-09-03 14:23:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addons`
--
ALTER TABLE `addons`
  ADD PRIMARY KEY (`akey`);

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token_hash`),
  ADD KEY `idx_tok_user` (`user_id`),
  ADD KEY `idx_tok_live` (`revoked`,`expires_at`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blog_slug` (`slug`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bk_ref` (`ref`),
  ADD KEY `idx_bk_user` (`user_id`),
  ADD KEY `idx_bk_prop` (`property_id`),
  ADD KEY `idx_bk_status` (`status`);

--
-- Indexes for table `booking_events`
--
ALTER TABLE `booking_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_be_bk` (`booking_id`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_camp_code` (`code`);

--
-- Indexes for table `cms_blocks`
--
ALTER TABLE `cms_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cms_key` (`block_key`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_col_slug` (`slug`);

--
-- Indexes for table `collection_properties`
--
ALTER TABLE `collection_properties`
  ADD PRIMARY KEY (`collection_id`,`property_id`);

--
-- Indexes for table `compare_items`
--
ALTER TABLE `compare_items`
  ADD PRIMARY KEY (`user_id`,`property_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cv_user` (`user_id`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `experiences`
--
ALTER TABLE `experiences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_exp_slug` (`slug`);

--
-- Indexes for table `experience_bookings`
--
ALTER TABLE `experience_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eb_exp` (`experience_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fraud_flags`
--
ALTER TABLE `fraud_flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fraud_code` (`code`);

--
-- Indexes for table `fx_rates`
--
ALTER TABLE `fx_rates`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `gift_cards`
--
ALTER TABLE `gift_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_gc_code` (`code`);

--
-- Indexes for table `help_categories`
--
ALTER TABLE `help_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_help_slug` (`slug`);

--
-- Indexes for table `host_channels`
--
ALTER TABLE `host_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hc` (`host_id`,`channel`),
  ADD KEY `idx_hc_host` (`host_id`);

--
-- Indexes for table `host_insights`
--
ALTER TABLE `host_insights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hi_host` (`host_id`);

--
-- Indexes for table `host_payout_settings`
--
ALTER TABLE `host_payout_settings`
  ADD PRIMARY KEY (`host_id`);

--
-- Indexes for table `host_team`
--
ALTER TABLE `host_team`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ht_host` (`host_id`);

--
-- Indexes for table `host_templates`
--
ALTER TABLE `host_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_htpl_host` (`host_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_la_id` (`identifier`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_cv` (`conversation_id`);

--
-- Indexes for table `moderation_queue`
--
ALTER TABLE `moderation_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `neighborhoods`
--
ALTER TABLE `neighborhoods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nb_slug` (`slug`);

--
-- Indexes for table `neighborhood_items`
--
ALTER TABLE `neighborhood_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ni_nb` (`neighborhood_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nt_user` (`user_id`);

--
-- Indexes for table `page_meta`
--
ALTER TABLE `page_meta`
  ADD PRIMARY KEY (`page_key`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pay_bk` (`booking_id`);

--
-- Indexes for table `payouts`
--
ALTER TABLE `payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_user` (`user_id`);

--
-- Indexes for table `pay_methods`
--
ALTER TABLE `pay_methods`
  ADD PRIMARY KEY (`mkey`);

--
-- Indexes for table `points_ledger`
--
ALTER TABLE `points_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pl_user` (`user_id`);

--
-- Indexes for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pr_host` (`host_id`);

--
-- Indexes for table `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prop_slug` (`slug`),
  ADD KEY `idx_prop_status` (`status`),
  ADD KEY `idx_prop_city` (`city`),
  ADD KEY `idx_prop_host` (`host_id`);

--
-- Indexes for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pa_prop` (`property_id`);

--
-- Indexes for table `property_calendar`
--
ALTER TABLE `property_calendar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cal_day` (`property_id`,`day`),
  ADD KEY `idx_cal_prop` (`property_id`);

--
-- Indexes for table `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pi_prop` (`property_id`);

--
-- Indexes for table `property_nearby`
--
ALTER TABLE `property_nearby`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pn_prop` (`property_id`);

--
-- Indexes for table `property_scores`
--
ALTER TABLE `property_scores`
  ADD PRIMARY KEY (`property_id`);

--
-- Indexes for table `recent_views`
--
ALTER TABLE `recent_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rv_user` (`user_id`),
  ADD KEY `idx_rv_sess` (`session_key`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rf_user` (`user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rev_prop` (`property_id`),
  ADD KEY `idx_rev_status` (`status`);

--
-- Indexes for table `roadmap`
--
ALTER TABLE `roadmap`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`skey`);

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_news_email` (`email`),
  ADD KEY `idx_sub_status` (`status`);

--
-- Indexes for table `sync_operations`
--
ALTER TABLE `sync_operations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sync_key` (`user_id`,`client_key`),
  ADD KEY `idx_sync_user` (`user_id`,`created_at`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tiers`
--
ALTER TABLE `tiers`
  ADD PRIMARY KEY (`tkey`);

--
-- Indexes for table `tier_perks`
--
ALTER TABLE `tier_perks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tp_key` (`tkey`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `user_prefs`
--
ALTER TABLE `user_prefs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pref` (`user_id`,`pref_key`);

--
-- Indexes for table `waitlist`
--
ALTER TABLE `waitlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wt_prop` (`property_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wl` (`user_id`,`slug`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`wishlist_id`,`property_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking_events`
--
ALTER TABLE `booking_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cms_blocks`
--
ALTER TABLE `cms_blocks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `experiences`
--
ALTER TABLE `experiences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `experience_bookings`
--
ALTER TABLE `experience_bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `fraud_flags`
--
ALTER TABLE `fraud_flags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gift_cards`
--
ALTER TABLE `gift_cards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `help_categories`
--
ALTER TABLE `help_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `host_channels`
--
ALTER TABLE `host_channels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `host_insights`
--
ALTER TABLE `host_insights`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `host_team`
--
ALTER TABLE `host_team`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `host_templates`
--
ALTER TABLE `host_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `moderation_queue`
--
ALTER TABLE `moderation_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `neighborhoods`
--
ALTER TABLE `neighborhoods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `neighborhood_items`
--
ALTER TABLE `neighborhood_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `points_ledger`
--
ALTER TABLE `points_ledger`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pricing_rules`
--
ALTER TABLE `pricing_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `property_amenities`
--
ALTER TABLE `property_amenities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `property_calendar`
--
ALTER TABLE `property_calendar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `property_nearby`
--
ALTER TABLE `property_nearby`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `recent_views`
--
ALTER TABLE `recent_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `roadmap`
--
ALTER TABLE `roadmap`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sync_operations`
--
ALTER TABLE `sync_operations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tier_perks`
--
ALTER TABLE `tier_perks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_prefs`
--
ALTER TABLE `user_prefs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `waitlist`
--
ALTER TABLE `waitlist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_prefs`
--
ALTER TABLE `user_prefs`
  ADD CONSTRAINT `fk_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
