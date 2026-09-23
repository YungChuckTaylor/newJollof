<?php
/**
 * Jollof Living — Cancellation Engine (WP05)
 *
 * Implements policy-driven, table-tested refund calculations (flexible, moderate, strict).
 * Computes split between cash refunds, gift balance restoration, and points reversal.
 */
declare(strict_types=1);

final class Cancellations
{
    /**
     * Compute refund breakdown for a booking cancelled at a specific timestamp.
     *
     * Policies:
     *  - flexible: full refund up to 24h before check-in, 0% after.
     *  - moderate: full refund up to 5 days (120h) before check-in, 50% up to 24h before, 0% after.
     *  - strict:   50% refund up to 14 days (336h) before check-in, 0% after.
     *
     * @param array $booking
     * @param DateTimeInterface|string|null $now
     * @return array{
     *   eligible: bool,
     *   pct: float,
     *   refund_fen: int,
     *   cash_refund_fen: int,
     *   gift_refund_fen: int,
     *   points_to_reverse: int,
     *   hours_before: float,
     *   policy: string
     * }
     */
    public static function compute(array $booking, $now = null): array
    {
        $nowDt = $now instanceof DateTimeInterface
            ? $now
            : new DateTime($now ? (string) $now : 'now', new DateTimeZone('UTC'));

        $checkinStr = (string) ($booking['checkin'] ?? '');
        $checkinDt = new DateTime($checkinStr . ' 15:00:00', new DateTimeZone('UTC'));

        $diffSeconds = $checkinDt->getTimestamp() - $nowDt->getTimestamp();
        $hoursBefore = $diffSeconds / 3600.0;

        $policy = strtolower((string) ($booking['policy'] ?? 'moderate'));
        if (!in_array($policy, ['flexible', 'moderate', 'strict'], true)) {
            $policy = 'moderate';
        }

        $pct = 0.0;
        if ($hoursBefore > 0) {
            switch ($policy) {
                case 'flexible':
                    $pct = $hoursBefore >= 24.0 ? 1.0 : 0.0;
                    break;
                case 'moderate':
                    if ($hoursBefore >= 120.0) {
                        $pct = 1.0;
                    } elseif ($hoursBefore >= 24.0) {
                        $pct = 0.5;
                    } else {
                        $pct = 0.0;
                    }
                    break;
                case 'strict':
                    $pct = $hoursBefore >= 336.0 ? 0.5 : 0.0;
                    break;
            }
        }

        $totalNaira = (int) ($booking['total'] ?? 0);
        $totalFen = $totalNaira * 100;

        // Breakdown analysis: did the guest use gift credit?
        $breakdown = json_decode((string) ($booking['breakdown'] ?? ''), true) ?: [];
        $giftNaira = (int) ($breakdown['gift'] ?? 0);
        $giftFen = $giftNaira * 100;
        $cashPaidFen = max(0, $totalFen); // total already had gift deducted in quote()

        $refundFen = (int) round($totalFen * $pct);
        
        // Reversal of points
        $pointsEarned = (int) ($booking['points_earned'] ?? 0);
        $pointsToReverse = (int) round($pointsEarned * $pct);

        return [
            'eligible'          => $refundFen > 0,
            'pct'               => $pct,
            'refund_fen'        => $refundFen,
            'cash_refund_fen'   => $refundFen,
            'gift_refund_fen'   => (int) round($giftFen * $pct),
            'points_to_reverse' => $pointsToReverse,
            'hours_before'      => $hoursBefore,
            'policy'            => $policy,
        ];
    }
}
