<?php
/**
 * Jollof Living — Financial Ledger Service (WP05)
 *
 * Implements immutable double-entry bookkeeping across escrow, guest cash,
 * host earnings, platform fees, gift card liabilities and refunds.
 *
 * All money is tracked in integers (Naira fen / minor units = 1/100 Naira,
 * or standard minor units).
 */
declare(strict_types=1);

final class Ledger
{
    public const ACCT_GUEST_CASH        = 'guest_cash';
    public const ACCT_ESCROW            = 'escrow';
    public const ACCT_HOST_EARNINGS     = 'host_earnings';
    public const ACCT_PLATFORM_FEE      = 'platform_fee';
    public const ACCT_GIFT_LIABILITY    = 'gift_liability';
    public const ACCT_LOYALTY_REDEEMED  = 'loyalty_redeemed';
    public const ACCT_REFUND_OUT        = 'refund_out';

    /**
     * Post a balanced double-entry transaction.
     *
     * Example:
     *   Ledger::postTransaction([
     *       ['account' => Ledger::ACCT_GUEST_CASH, 'direction' => 'debit', 'amount_fen' => 1000000],
     *       ['account' => Ledger::ACCT_ESCROW,     'direction' => 'credit', 'amount_fen' => 1000000],
     *   ], 'payment', 'PI-1234', $bookingId, $userId, 'Booking captured to escrow');
     *
     * @param array<int, array{account:string, direction:string, amount_fen:int}> $legs
     * @throws InvalidArgumentException|RuntimeException
     */
    public static function postTransaction(
        array $legs,
        string $refType,
        string $refId,
        ?int $bookingId = null,
        ?int $userId = null,
        string $narration = '',
        ?string $masterIdemKey = null
    ): bool {
        if (!DB::tableExists('ledger_entries')) {
            return false;
        }

        if (count($legs) < 2) {
            throw new InvalidArgumentException('A ledger transaction requires at least 2 legs.');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($legs as $leg) {
            $amt = (int) ($leg['amount_fen'] ?? 0);
            if ($amt <= 0) {
                throw new InvalidArgumentException('Ledger leg amount must be positive.');
            }
            $dir = strtolower((string) ($leg['direction'] ?? ''));
            if ($dir === 'debit') {
                $totalDebit += $amt;
            } elseif ($dir === 'credit') {
                $totalCredit += $amt;
            } else {
                throw new InvalidArgumentException('Invalid ledger leg direction: ' . $dir);
            }
        }

        if ($totalDebit !== $totalCredit) {
            throw new InvalidArgumentException("Ledger transaction is unbalanced: debit $totalDebit vs credit $totalCredit.");
        }

        $baseKey = $masterIdemKey ?: hash('sha256', $refType . ':' . $refId . ':' . json_encode($legs));
        $now = date('Y-m-d H:i:s');

        $inTxn = DB::pdo()->inTransaction();
        if (!$inTxn) {
            DB::begin();
        }

        try {
            foreach ($legs as $idx => $leg) {
                $legKey = hash('sha256', $baseKey . ':' . $idx);
                
                // Idempotency check: if entry already recorded, skip or succeed safely
                $exists = DB::value('SELECT id FROM ledger_entries WHERE idempotency_key = ?', [$legKey]);
                if ($exists) {
                    continue;
                }

                DB::insert('ledger_entries', [
                    'account_key'     => (string) $leg['account'],
                    'booking_id'      => $bookingId,
                    'user_id'         => $userId,
                    'direction'       => strtolower((string) $leg['direction']),
                    'amount_fen'      => (int) $leg['amount_fen'],
                    'ref_type'        => $refType,
                    'ref_id'          => $refId,
                    'idempotency_key' => $legKey,
                    'narration'       => mb_substr($narration, 0, 255),
                    'created_at'      => $now,
                ]);
            }

            if (!$inTxn) {
                DB::commit();
            }
            return true;
        } catch (Throwable $e) {
            if (!$inTxn) {
                DB::rollback();
            }
            throw $e;
        }
    }

    /**
     * Compute current escrow balance for a specific booking from ledger entries.
     */
    public static function bookingEscrowBalance(int $bookingId): int
    {
        if (!DB::tableExists('ledger_entries')) {
            return 0;
        }
        $credits = (int) DB::value(
            "SELECT COALESCE(SUM(amount_fen), 0) FROM ledger_entries
              WHERE booking_id = ? AND account_key = ? AND direction = 'credit'",
            [$bookingId, self::ACCT_ESCROW],
            0
        );
        $debits = (int) DB::value(
            "SELECT COALESCE(SUM(amount_fen), 0) FROM ledger_entries
              WHERE booking_id = ? AND account_key = ? AND direction = 'debit'",
            [$bookingId, self::ACCT_ESCROW],
            0
        );
        return max(0, $credits - $debits);
    }

    /**
     * Derive true escrow status from ledger state.
     */
    public static function bookingEscrowStatus(int $bookingId): string
    {
        if (!DB::tableExists('ledger_entries')) {
            return 'none';
        }
        $credits = (int) DB::value(
            "SELECT COALESCE(SUM(amount_fen), 0) FROM ledger_entries
              WHERE booking_id = ? AND account_key = ? AND direction = 'credit'",
            [$bookingId, self::ACCT_ESCROW],
            0
        );
        if ($credits === 0) {
            return 'none';
        }
        $debits = (int) DB::value(
            "SELECT COALESCE(SUM(amount_fen), 0) FROM ledger_entries
              WHERE booking_id = ? AND account_key = ? AND direction = 'debit'",
            [$bookingId, self::ACCT_ESCROW],
            0
        );
        $remaining = $credits - $debits;
        if ($remaining > 0) {
            return 'held';
        }
        
        // Released or refunded?
        $hasRefund = (bool) DB::value(
            "SELECT 1 FROM ledger_entries
              WHERE booking_id = ? AND account_key = ? AND ref_type = 'refund' LIMIT 1",
            [$bookingId, self::ACCT_REFUND_OUT]
        );
        return $hasRefund ? 'refunded' : 'released';
    }

    /**
     * Total captured amount across all payment intents for a booking.
     */
    public static function bookingCapturedTotal(int $bookingId): int
    {
        if (!DB::tableExists('payment_intents')) {
            return 0;
        }
        return (int) DB::value(
            "SELECT COALESCE(SUM(amount_fen), 0) FROM payment_intents
              WHERE booking_id = ? AND status = 'captured'",
            [$bookingId],
            0
        );
    }

    /**
     * Total refunded amount across all payment intents / refunds for a booking.
     */
    public static function bookingRefundedTotal(int $bookingId): int
    {
        if (!DB::tableExists('payment_intents')) {
            return 0;
        }
        return (int) DB::value(
            "SELECT COALESCE(SUM(amount_fen), 0) FROM payment_intents
              WHERE booking_id = ? AND status IN ('refunded', 'partial_refund')",
            [$bookingId],
            0
        );
    }
}
