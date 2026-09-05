<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('invoices', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            if (!empty($_GET['id'])) {
                $stmt = $db->prepare(
                    "SELECT inv.*, g.full_name AS guest_name, g.phone AS guest_phone, g.email AS guest_email,
                            r.reservation_code, rm.room_number
                     FROM invoices inv
                     JOIN guests g ON g.id = inv.guest_id
                     LEFT JOIN reservations r ON r.id = inv.reservation_id
                     LEFT JOIN rooms rm ON rm.id = r.room_id
                     WHERE inv.id = :id"
                );
                $stmt->execute([':id' => (int) $_GET['id']]);
                $invoice = $stmt->fetch();
                if (!$invoice) jsonResponse(['success' => false, 'message' => 'Invoice not found.'], 404);

                $itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = :id");
                $itemsStmt->execute([':id' => $invoice['id']]);
                $invoice['items'] = $itemsStmt->fetchAll();

                jsonResponse(['success' => true, 'data' => $invoice]);
            }

            $search = trim($_GET['search'] ?? '');
            $status = $_GET['status'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $where = ['1=1'];
            $params = [];
            if ($search !== '') {
                $where[] = '(inv.invoice_number LIKE :s1 OR g.full_name LIKE :s2 OR r.reservation_code LIKE :s3)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%";
            }
            if ($status !== '') { $where[] = 'inv.status = :status'; $params[':status'] = $status; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare(
                "SELECT COUNT(*) FROM invoices inv JOIN guests g ON g.id = inv.guest_id LEFT JOIN reservations r ON r.id = inv.reservation_id WHERE {$whereSql}"
            );
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT inv.*, g.full_name AS guest_name, g.phone AS guest_phone, r.reservation_code
                 FROM invoices inv
                 JOIN guests g ON g.id = inv.guest_id
                 LEFT JOIN reservations r ON r.id = inv.reservation_id
                 WHERE {$whereSql}
                 ORDER BY inv.issued_at DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['total_display'] = formatCurrency((float) $r['total']);
                $r['balance_display'] = formatCurrency((float) $r['balance']);
                $r['guest_whatsapp'] = formatWhatsAppNumber($r['guest_phone'] ?? null);
            }

            jsonResponse([
                'success' => true,
                'data' => $rows,
                'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
            ]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id || ($input['action'] ?? '') !== 'void') jsonResponse(['success' => false, 'message' => 'Unsupported request.'], 422);

            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $invoice = $stmt->fetch();
            if (!$invoice) jsonResponse(['success' => false, 'message' => 'Invoice not found.'], 404);

            $db->prepare("UPDATE invoices SET status = 'void' WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} voided invoice {$invoice['invoice_number']}", 'invoices', $invoice['status'], 'void');
            jsonResponse(['success' => true, 'message' => "Invoice {$invoice['invoice_number']} voided."]);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
