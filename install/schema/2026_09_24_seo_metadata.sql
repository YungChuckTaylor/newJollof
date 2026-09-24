-- ============================================================================
-- 2026_09_24 — SEO metadata: keyword-first titles + SERP-length descriptions
-- ----------------------------------------------------------------------------
-- Titles lead with the search query ("luxury short-let apartments in Lagos"),
-- not the brand; descriptions are trimmed to ~155 characters so they never
-- truncate in search snippets.
--
-- Idempotent by design:
--   * existing rows are only overwritten while they still hold the ORIGINAL
--     copy (an admin-customised title is never clobbered);
--   * missing rows are inserted.
-- Safe to run any number of times, in any dialect (MySQL / SQLite).
-- ============================================================================

UPDATE `page_meta` SET
  `title` = 'Luxury Short-Let Apartments in Lagos & Abuja | Jollof Living',
  `description` = 'Book verified luxury apartments and penthouses in Lagos & Abuja — hotel-grade service, escrow payments, AI concierge and 24/7 support.'
WHERE `page_key` = 'index' AND `title` = 'Jollof Living - Luxury Living, African Soul';

UPDATE `page_meta` SET
  `title` = 'Luxury Stays & Short-Lets in Lagos & Abuja | Jollof Living',
  `description` = 'Browse every verified Jollof Living residence across Lekki, Victoria Island, Ikoyi, Banana Island and Abuja — filters, map view and instant booking.'
WHERE `page_key` = 'stays' AND `title` = 'The Collection - Luxury Stays | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Luxury Experiences in Lagos & Abuja | Jollof Living',
  `description` = 'Boat cruises, private chefs, spa rituals, art tours, airport transfers and event hosting — add authentic experiences to any stay.'
WHERE `page_key` = 'experiences' AND `title` = 'Experiences | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Lagos & Abuja Neighbourhood Guides | Jollof Living',
  `description` = 'Insider guides to the best areas to stay in Lagos and Abuja — dining, nightlife, transport, safety and average nightly prices.'
WHERE `page_key` = 'neighborhoods' AND `title` = 'Neighbourhood Guides | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'The Journal — Luxury Travel Guides for Nigeria | Jollof Living',
  `description` = 'Travel guides, neighbourhood stories and design features from the Jollof Living editorial desk.'
WHERE `page_key` = 'blog' AND `title` = 'The Journal | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'List Your Home & Keep 88% | Jollof Living Hosts',
  `description` = 'Earn more from your Lagos or Abuja property — photography, pricing intelligence, guest screening and payouts handled for you.'
WHERE `page_key` = 'host' AND `title` = 'Host with Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Jollof Club Membership — Points & Rewards | Jollof Living',
  `description` = 'Earn Jollof Points on every stay, climb from Bronze to Platinum and redeem rewards, upgrades and gift cards.'
WHERE `page_key` = 'membership' AND `title` = 'Jollof Club - Bronze to Platinum | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Help Centre & 24/7 Support | Jollof Living',
  `description` = 'Booking help, dispute support, payment questions and emergency assistance — plus 24/7 live chat with our concierge desk.'
WHERE `page_key` = 'help' AND `title` = 'Help Centre | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Verified Guest Reviews | Jollof Living',
  `description` = 'Real, verified reviews from guests who stayed with Jollof Living — plus the platform rules that keep them honest.'
WHERE `page_key` = 'reviews' AND `title` = 'Reviews | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'About Jollof Living — Luxury Living, African Soul',
  `description` = 'The story, standards and safeguards behind Nigeria''s premium short-let platform.'
WHERE `page_key` = 'about' AND `title` = 'About Jollof Living - Luxury Living, African Soul';

UPDATE `page_meta` SET
  `title` = 'Corporate Stays for Business Teams | Jollof Living',
  `description` = 'Corporate housing in Lagos & Abuja with centralised billing, PO support and travel policy enforcement for teams of any size.'
WHERE `page_key` = 'business' AND `title` = 'Jollof for Business | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Luxury Stay Gift Cards | Jollof Living',
  `description` = 'Digital gift cards for luxury stays across Lagos & Abuja — delivered by email or WhatsApp and they never expire.'
WHERE `page_key` = 'giftcards' AND `title` = 'Gift Cards | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Refer a Friend — Give ₦10,000, Get ₦10,000 | Jollof Living',
  `description` = 'The most generous referral programme in Nigerian travel — share your code and you both earn.'
WHERE `page_key` = 'referral' AND `title` = 'Refer & Earn - Give N10,000, Get N10,000 | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'The Jollof Living App — Keyless Check-In | Jollof Living',
  `description` = 'Keyless check-in, live messaging, wallet passes and voice booking — the luxury stay platform in your pocket.'
WHERE `page_key` = 'app' AND `title` = 'Mobile App | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Product Roadmap | Jollof Living',
  `description` = 'What''s shipping, what''s building and what''s dreaming on the Jollof Living platform.'
WHERE `page_key` = 'future' AND `title` = 'Roadmap | Jollof Living';

UPDATE `page_meta` SET
  `title` = 'Explore Every Residence on the Map | Jollof Living',
  `description` = 'An interactive map of every Jollof Living residence in Lagos & Abuja with live nightly rates and availability.'
WHERE `page_key` = 'map' AND `title` = 'Explore the Map | Jollof Living';

-- ---------------------------------------------------------------------
-- Pages that may not have a row yet (newer pages) — insert when missing.
-- ---------------------------------------------------------------------

INSERT INTO `page_meta` (`page_key`, `title`, `description`)
SELECT 'collections', 'Curated Luxury Stay Collections | Jollof Living',
       'Waterfront escapes, sky penthouses, Abuja executive homes, heritage houses, romantic retreats and family villas — hand-picked by our team.'
WHERE NOT EXISTS (SELECT 1 FROM `page_meta` WHERE `page_key` = 'collections');

INSERT INTO `page_meta` (`page_key`, `title`, `description`)
SELECT 'concierge', 'AI Concierge — Plan Your Stay in Seconds | Jollof Living',
       'Ask about stays, prices, itineraries, transfers and private chefs — AI concierge, 24/7.'
WHERE NOT EXISTS (SELECT 1 FROM `page_meta` WHERE `page_key` = 'concierge');

INSERT INTO `page_meta` (`page_key`, `title`, `description`)
SELECT 'compare', 'Compare Luxury Residences Side by Side | Jollof Living',
       'Compare up to three Jollof Living residences — pricing, ratings, bedrooms and amenities side by side.'
WHERE NOT EXISTS (SELECT 1 FROM `page_meta` WHERE `page_key` = 'compare');
