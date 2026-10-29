<?php
// network/po-bom-group.php — Pagsasama ng mga BOM na sabay ginawa sa Cutting (iisang upload / progression)
//
// Sa Cutting, puwedeng gumawa ng ilang BOM sa iisang upload. Pagdating sa PO, iisang "order" na sila:
//   isang row sa Purchase Orders · isang Receive · isang Purchase Request · isang Transmittal · isang DR.
// Ang PR ay may "lead" na bom_id (ang unang BOM) para gumana pa rin ang lahat ng lumang JOIN,
// at nakalista ang LAHAT ng BOM nito sa noblepurchaserequest_bom (pr_id, bom_id).
//
// Kapag wala pa ang table na noblepurchaserequest_bom (hindi pa na-run ang SQL), gagana pa rin:
// isang BOM = isang PR gaya ng dati.

if (!function_exists('poHasPrLink')) {

    // May table na ba para sa maraming BOM bawat PR?
    function poHasPrLink(mysqli $conn): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $r = $conn->query("SHOW TABLES LIKE 'noblepurchaserequest_bom'");
                $has = $r && $r->num_rows > 0;
            } catch (Throwable $e) {
                $has = false;
            }
        }
        return $has;
    }

    // SQL: pinakabagong PR id ng isang BOM (lead man o kasama lang). $col = hal. 'b.id'
    function poPrOfBomSql(mysqli $conn, string $col): string
    {
        $direct = "(SELECT MAX(p2.id) FROM noblepurchaserequest p2 WHERE p2.bom_id = $col)";
        if (!poHasPrLink($conn)) {
            return $direct;
        }
        return "NULLIF(GREATEST(COALESCE((SELECT MAX(pb.pr_id) FROM noblepurchaserequest_bom pb WHERE pb.bom_id = $col), 0),
                                COALESCE($direct, 0)), 0)";
    }

    // SQL: susi ng grupo ng isang BOM (parehong upload = parehong grupo). $alias = hal. 'b'
    function poGroupKeySql(string $alias = 'b'): string
    {
        return "COALESCE(CONCAT('p', $alias.progression_id), CONCAT('b', $alias.id))";
    }

    /**
     * Lahat ng approved na BOM na kasama ng $bomId (iisang progression), naka-ayos ayon sa id.
     * Laging kasama ang $bomId mismo. Hindi kasama ang TEMP.
     */
    function poBomGroupIds(mysqli $conn, int $bomId): array
    {
        if ($bomId <= 0) {
            return [];
        }
        $st = $conn->prepare("SELECT id, progression_id FROM noblecrm_bom WHERE id = ? LIMIT 1");
        $st->bind_param('i', $bomId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return [];
        }
        if (empty($row['progression_id'])) {
            return [(int) $row['id']];
        }
        $pid = (int) $row['progression_id'];
        $st = $conn->prepare("SELECT id FROM noblecrm_bom
                              WHERE progression_id = ? AND approved_at IS NOT NULL AND bom_number NOT LIKE 'TEMP-%'
                              ORDER BY id ASC");
        $st->bind_param('i', $pid);
        $st->execute();
        $ids = array_map('intval', array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $st->close();
        if (!in_array((int) $row['id'], $ids, true)) {
            $ids[] = (int) $row['id'];
            sort($ids);
        }
        return $ids;
    }

    // Ilista ang lahat ng BOM ng isang PR (para sa bagong PR)
    function poLinkPrBoms(mysqli $conn, int $prId, array $bomIds): void
    {
        if (!poHasPrLink($conn) || $prId <= 0) {
            return;
        }
        $st = $conn->prepare("INSERT IGNORE INTO noblepurchaserequest_bom (pr_id, bom_id) VALUES (?, ?)");
        foreach (array_unique(array_map('intval', $bomIds)) as $bid) {
            if ($bid <= 0) continue;
            $st->bind_param('ii', $prId, $bid);
            $st->execute();
        }
        $st->close();
    }

    // Mga BOM number ng isang PR, hal. "BOM-0003, BOM-0004"
    function poPrBomNumbers(mysqli $conn, int $prId): string
    {
        if ($prId <= 0) {
            return '';
        }
        if (poHasPrLink($conn)) {
            $st = $conn->prepare("SELECT b.bom_number FROM noblecrm_bom b
                                  WHERE b.id IN (SELECT pb.bom_id FROM noblepurchaserequest_bom pb WHERE pb.pr_id = ?)
                                     OR b.id = (SELECT p.bom_id FROM noblepurchaserequest p WHERE p.id = ?)
                                  ORDER BY b.id");
            $st->bind_param('ii', $prId, $prId);
        } else {
            $st = $conn->prepare("SELECT b.bom_number FROM noblecrm_bom b JOIN noblepurchaserequest p ON p.bom_id = b.id WHERE p.id = ?");
            $st->bind_param('i', $prId);
        }
        $st->execute();
        $nums = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'bom_number');
        $st->close();
        return implode(', ', array_unique($nums));
    }
}