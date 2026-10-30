<?php
// network/client-payment.php — Bayad ng client (Accounting) laban sa contract ng inquiry
//
// Ito ang "billing ng Accounting": ang mga bayad na itinatala sa Approved 2D & Quotation
// (noblecrm_deposit_payment, galing sa index-accounting-crmdetail.php).
// Bawal mag-Turnover hangga't hindi pa bayad nang buo ang contract (noblecrminquiry.contract_amount).
//
// Kapag wala pa ang table na noblecrm_deposit_payment, ang dating isang deposit (noblecrm_2dquotation.deposit_amount) ang gamit.

if (!function_exists('cpClientPaidSql')) {

    function cpHasPaymentTable(mysqli $conn): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $r = $conn->query("SHOW TABLES LIKE 'noblecrm_deposit_payment'");
                $has = $r && $r->num_rows > 0;
            } catch (Throwable $e) {
                $has = false;
            }
        }
        return $has;
    }

    // SQL: kabuuang bayad ng client para sa isang inquiry. $inqExpr = hal. 'i.id' o 'q.inquiry_id'
    function cpClientPaidSql(mysqli $conn, string $inqExpr): string
    {
        return cpHasPaymentTable($conn)
            ? "(SELECT COALESCE(SUM(dp.amount), 0) FROM noblecrm_deposit_payment dp WHERE dp.inquiry_id = $inqExpr)"
            : "(SELECT COALESCE(MAX(q9.deposit_amount), 0) FROM noblecrm_2dquotation q9 WHERE q9.inquiry_id = $inqExpr)";
    }

    // Inquiry ng isang DR (DR → transmittal → PR → BOM → 2D quotation)
    function cpInquiryOfDr(mysqli $conn, int $drId): int
    {
        $st = $conn->prepare("SELECT q.inquiry_id FROM noblepc_dr d
                              JOIN nobletransmittal t ON t.id = d.transmittal_id
                              JOIN noblepurchaserequest p ON p.id = t.pr_id
                              JOIN noblecrm_bom b ON b.id = p.bom_id
                              JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                              WHERE d.id = ? LIMIT 1");
        $st->bind_param('i', $drId);
        $st->execute();
        $inq = (int) ($st->get_result()->fetch_assoc()['inquiry_id'] ?? 0);
        $st->close();
        return $inq;
    }

    /** @return array{inquiry_id:int, contract:float, paid:float, balance:float, ok:bool} */
    function cpClientPayment(mysqli $conn, int $inquiryId): array
    {
        $out = ['inquiry_id' => $inquiryId, 'contract' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'ok' => false];
        if ($inquiryId <= 0) {
            return $out;
        }
        $st = $conn->prepare("SELECT COALESCE(i.contract_amount, 0) AS contract, " . cpClientPaidSql($conn, 'i.id') . " AS paid
                              FROM noblecrminquiry i WHERE i.id = ? LIMIT 1");
        $st->bind_param('i', $inquiryId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$r) {
            return $out;
        }
        $out['contract'] = round((float) $r['contract'], 2);
        $out['paid'] = round((float) $r['paid'], 2);
        $out['balance'] = max(0, round($out['contract'] - $out['paid'], 2));
        $out['ok'] = $out['contract'] > 0 && $out['paid'] >= $out['contract'] - 0.004;
        return $out;
    }
}