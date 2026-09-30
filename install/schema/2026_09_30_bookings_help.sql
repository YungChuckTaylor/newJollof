-- Jollof Living — Bookings & reservations help article
-- Idempotent content migration. Run after the core schema is installed.
SET NAMES utf8mb4;

INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`)
SELECT
  'Bookings & reservations: how do I make and manage a booking?',
  'Choose a residence in Stays, select available dates and guests, review the policy and total, then complete checkout. Instant Book reservations confirm after payment; other reservations remain pending until the host approves them. Find your reference and status in Trips. Use the reservation actions to request a change or cancel; the residence policy determines any refund. For help, use live chat or the dispute centre with your reservation reference.',
  'booking',
  -1
WHERE NOT EXISTS (
  SELECT 1 FROM `faqs`
  WHERE `question` = 'Bookings & reservations: how do I make and manage a booking?'
);

UPDATE `help_categories`
SET `article_count` = GREATEST(`article_count`, 5)
WHERE `slug` = 'booking';
