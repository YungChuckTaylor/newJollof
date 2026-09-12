<?php
/**
 * Jollof Living — AI concierge.
 *
 * Answers guest questions from live inventory in MySQL. Intent matching is
 * rule-based so it works on shared hosting with no external API; if an LLM
 * key is configured it is used first and this becomes the fallback.
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


final class Concierge
{
    public static function reply(string $text, ?int $userId = null): string
    {
        $remote = self::askModel($text, $userId);
        if ($remote !== null) {
            return $remote;
        }
        return self::rules($text, $userId);
    }

    /** Optional hosted-model call; returns null when not configured. */
    private static function askModel(string $text, ?int $userId): ?string
    {
        $key = (string) config('ai.api_key', '');
        if ($key === '' || !function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init((string) config('ai.endpoint', 'https://api.openai.com/v1/chat/completions'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_POSTFIELDS     => json_encode([
                'model' => config('ai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => self::systemPrompt()],
                    ['role' => 'user', 'content' => mb_substr($text, 0, 1000)],
                ],
                'max_tokens' => 320,
            ]),
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$raw) {
            return null;
        }
        $j = json_decode((string) $raw, true);
        $out = $j['choices'][0]['message']['content'] ?? null;
        return is_string($out) && trim($out) !== '' ? nl2br(e(trim($out))) : null;
    }

    private static function systemPrompt(): string
    {
        $props = Repo::properties();
        $lines = [];
        foreach (array_slice($props, 0, 20) as $p) {
            $lines[] = sprintf(
                '- %s (%s, %s) — %s/night, %d guests, %d beds, %s★',
                $p['name'], $p['area'], $p['city'], money((int) $p['price']),
                (int) $p['guests'], (int) $p['beds'], (string) $p['rating']
            );
        }
        return "You are the Jollof Living concierge — warm, concise, British English. "
            . "You help guests book luxury short-stay residences in Lagos and Abuja. "
            . "Answer in 2-4 short sentences. Current inventory:\n" . implode("\n", $lines);
    }

    /* ------------------------------------------------------------ rules */

    private static function rules(string $text, ?int $userId): string
    {
        $q = mb_strtolower($text);
        $has = static fn(array $words) => (bool) array_filter($words, static fn($w) => str_contains($q, $w));

        /* --- price / budget --- */
        if ($has(['cheap', 'budget', 'affordable', 'under', 'less than', 'price', 'cost', 'how much'])) {
            $cap = 0;
            if (preg_match('~(\d[\d,\.]*)\s*(k|m)?~', str_replace(['₦', 'n'], '', $q), $m)) {
                $n = (float) str_replace(',', '', $m[1]);
                $cap = (int) ($n * (($m[2] ?? '') === 'm' ? 1000000 : (($m[2] ?? '') === 'k' ? 1000 : 1)));
            }
            $list = Repo::properties();
            if ($cap > 5000) {
                $list = array_values(array_filter($list, static fn($p) => (int) $p['price'] <= $cap));
            }
            usort($list, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
            if (!$list) {
                return "Nothing sits under that figure right now, but our lowest nightly rate is "
                    . money(self::minPrice()) . ". Shall I show you the best value residences?";
            }
            return "Here are the best value options right now:<br>" . self::cards(array_slice($list, 0, 3));
        }

        /* --- availability / dates --- */
        if ($has(['available', 'availability', 'free', 'tonight', 'this weekend', 'next week', 'dates'])) {
            $from = date('Y-m-d', strtotime('+1 day'));
            $to = date('Y-m-d', strtotime('+3 days'));
            $open = array_values(array_filter(
                Repo::properties(),
                static fn($p) => BookingService::isAvailable((int) $p['pid'], $from, $to)
            ));
            if (!$open) {
                return "We are fully committed for those dates — join a waitlist on any residence and I will alert you the moment it opens.";
            }
            return "Open for " . date('j M', strtotime($from)) . " – " . date('j M', strtotime($to)) . ":<br>"
                . self::cards(array_slice($open, 0, 3));
        }

        /* --- area search --- */
        foreach (Repo::areas() as $area) {
            if (str_contains($q, mb_strtolower((string) $area))) {
                $list = array_values(array_filter(Repo::properties(), static fn($p) => $p['area'] === $area));
                if ($list) {
                    return "In <b>" . e((string) $area) . "</b> we have " . count($list) . " residence"
                        . (count($list) === 1 ? '' : 's') . ":<br>" . self::cards(array_slice($list, 0, 3));
                }
            }
        }

        /* --- amenity search --- */
        $amenityWords = ['pool' => 'Pool', 'gym' => 'Gym', 'wifi' => 'Wi-Fi', 'parking' => 'Parking',
                         'chef' => 'Chef', 'generator' => 'Generator', 'pet' => 'Pet friendly',
                         'beach' => 'Beach access', 'workspace' => 'Workspace'];
        foreach ($amenityWords as $word => $label) {
            if (str_contains($q, $word)) {
                $list = array_values(array_filter(Repo::properties(true), static function ($p) use ($label) {
                    foreach ((array) ($p['amens'] ?? []) as $a) {
                        if (stripos((string) $a, $label) !== false) return true;
                    }
                    return false;
                }));
                if ($list) {
                    return "These residences have " . e(mb_strtolower($label)) . ":<br>" . self::cards(array_slice($list, 0, 3));
                }
            }
        }

        /* --- transfers, chefs and services --- */
        if ($has(['airport', 'transfer', 'pick up', 'pickup', 'driver', 'car'])) {
            $a = Repo::addons()['transfer'] ?? null;
            return "Airport transfers in a chauffeured SUV are " . ($a ? money((int) $a['price']) : "available on request")
                . " each way, bookable as an add-on at checkout. Our driver meets you inside arrivals with a name board.";
        }
        if ($has(['chef', 'food', 'cook', 'breakfast', 'jollof'])) {
            $a = Repo::addons()['chef'] ?? null;
            return "A private chef is " . ($a ? money((int) $a['price']) . " per day" : "available on request")
                . " — Nigerian classics, continental or a tasting menu. Add it at checkout or ask me and I will arrange it.";
        }
        if ($has(['clean', 'housekeeping', 'laundry'])) {
            return "Every stay includes a professional clean before arrival. Mid-stay housekeeping and laundry can be added at checkout or arranged any time through this chat.";
        }

        /* --- policies --- */
        if ($has(['cancel', 'refund', 'policy'])) {
            return "Flexible bookings refund in full up to 24 hours before check-in. Moderate refunds in full up to 5 days before. Strict refunds 50% up to 7 days before. Your reservation page shows exactly which applies.";
        }
        if ($has(['escrow', 'safe', 'secure', 'scam', 'trust'])) {
            return "Your payment is held in escrow and only released to the host after you check in. Every host is KYC-verified and every residence is inspected by our team before it goes live.";
        }
        if ($has(['pay', 'payment', 'card', 'transfer', 'crypto'])) {
            $names = array_column(Repo::payMethods(), 'name');
            return "We accept " . e(implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names)) . ". You can also split a booking into two payments at checkout.";
        }
        if ($has(['points', 'tier', 'club', 'loyalty', 'reward'])) {
            if ($userId) {
                $u = Repo::userRow($userId);
                if ($u) {
                    return "You are <b>" . e(ucfirst((string) $u['tier'])) . "</b> with <b>" . number_format((int) $u['points'])
                        . "</b> points. Points convert at 100 points = ₦1,000 towards any stay.";
                }
            }
            return "Jollof Club runs Bronze through Platinum. You earn points on every night booked, and they convert at 100 points = ₦1,000 towards a future stay.";
        }
        if ($has(['host', 'list my', 'earn', 'rent out'])) {
            return "Listing takes about ten minutes and hosts keep 88% of every booking. Start at <a href=\"" . e(url('host-onboarding.php')) . "\">List your residence</a> and our team verifies within 24 hours.";
        }
        if ($has(['long stay', 'monthly', 'month', 'weekly', 'week'])) {
            $r = Repo::rates();
            return "Weekly stays save " . round(((float) $r['weeklyDisc']) * 100) . "% and monthly stays save "
                . round(((float) $r['monthlyDisc']) * 100) . "%. The discount applies automatically once your dates qualify.";
        }
        if ($has(['hello', 'hi ', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            return "Hello ✨ Tell me your dates, city and budget and I will shortlist the right residences — or ask me about transfers, chefs, long-stay rates or Jollof Club.";
        }
        if ($has(['thank', 'thanks', 'cheers'])) {
            return "A pleasure. I am here whenever you need me — bookings, transfers, chefs or a full itinerary.";
        }

        /* --- fallback: a genuine shortlist --- */
        $top = Repo::properties();
        usort($top, static fn($a, $b) => (float) $b['rating'] <=> (float) $a['rating']);
        return "I can help with dates, budgets, neighbourhoods, transfers and chefs. Meanwhile, our highest-rated residences are:<br>"
            . self::cards(array_slice($top, 0, 3));
    }

    private static function minPrice(): int
    {
        $prices = array_map(static fn($p) => (int) $p['price'], Repo::properties());
        return $prices ? min($prices) : 0;
    }

    /** Small inline result cards the chat window can render. */
    private static function cards(array $list): string
    {
        $out = '';
        foreach ($list as $p) {
            $out .= '<a class="conc-card" href="' . e(url('stay.php?p=' . urlencode((string) $p['id']))) . '">'
                . '<b>' . e((string) $p['name']) . '</b><span>' . e((string) $p['area']) . ' · '
                . e(money((int) $p['price'])) . '/night · ' . e((string) $p['rating']) . '★</span></a>';
        }
        return $out;
    }
}
