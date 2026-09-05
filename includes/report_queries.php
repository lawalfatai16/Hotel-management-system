<?php
/**
 * Builds report data for a given type + date range.
 * Returns: ['title'=>, 'columns'=>[[key,label,type]], 'rows'=>[...], 'chart'=>?, 'totals'=>?]
 * column type is one of: text, number, currency, date, datetime — used by both
 * the on-screen table (JS formats) and the CSV/Excel export (PHP formats),
 * so every report and every export format reads from the exact same query.
 */
function buildReport(PDO $db, string $type, string $from, string $to): array
{
    switch ($type) {

        case 'revenue': {
            $stmt = $db->prepare(
                "SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS total FROM payments
                 WHERE DATE(paid_at) BETWEEN :from AND :to GROUP BY DATE(paid_at) ORDER BY d"
            );
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll();
            $grand = array_sum(array_column($rows, 'total'));
            return [
                'title' => 'Revenue Report',
                'columns' => [['key' => 'd', 'label' => 'Date', 'type' => 'date'], ['key' => 'total', 'label' => 'Revenue', 'type' => 'currency']],
                'rows' => $rows,
                'chart' => ['type' => 'line', 'labels' => array_column($rows, 'd'), 'values' => array_map('floatval', array_column($rows, 'total'))],
                'totals' => ['Total Revenue' => $grand],
            ];
        }

        case 'yearly_revenue': {
            $year = substr($from, 0, 4);
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, COALESCE(SUM(amount),0) AS total FROM payments
                 WHERE YEAR(paid_at) = :year GROUP BY m ORDER BY m"
            );
            $stmt->execute([':year' => $year]);
            $rows = $stmt->fetchAll();
            return [
                'title' => "Yearly Revenue ({$year})",
                'columns' => [['key' => 'm', 'label' => 'Month', 'type' => 'text'], ['key' => 'total', 'label' => 'Revenue', 'type' => 'currency']],
                'rows' => $rows,
                'chart' => ['type' => 'bar', 'labels' => array_column($rows, 'm'), 'values' => array_map('floatval', array_column($rows, 'total'))],
                'totals' => ['Total Revenue' => array_sum(array_column($rows, 'total'))],
            ];
        }

        case 'occupancy': {
            $totalRooms = (int) $db->query("SELECT COUNT(*) FROM rooms WHERE deleted_at IS NULL")->fetchColumn();
            $rows = [];
            $cursor = strtotime($from);
            $end = strtotime($to);
            while ($cursor <= $end) {
                $date = date('Y-m-d', $cursor);
                $stmt = $db->prepare(
                    "SELECT COUNT(DISTINCT room_id) FROM reservations
                     WHERE deleted_at IS NULL AND status != 'cancelled' AND check_in_date <= :d1 AND check_out_date > :d2"
                );
                $stmt->execute([':d1' => $date, ':d2' => $date]);
                $occupied = (int) $stmt->fetchColumn();
                $rate = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;
                $rows[] = ['d' => $date, 'occupied' => $occupied, 'total' => $totalRooms, 'rate' => $rate];
                $cursor = strtotime('+1 day', $cursor);
            }
            $avg = count($rows) ? round(array_sum(array_column($rows, 'rate')) / count($rows), 1) : 0;
            return [
                'title' => 'Occupancy Report',
                'columns' => [['key' => 'd', 'label' => 'Date', 'type' => 'date'], ['key' => 'occupied', 'label' => 'Occupied', 'type' => 'number'], ['key' => 'total', 'label' => 'Total Rooms', 'type' => 'number'], ['key' => 'rate', 'label' => 'Occupancy %', 'type' => 'text']],
                'rows' => $rows,
                'chart' => ['type' => 'line', 'labels' => array_column($rows, 'd'), 'values' => array_column($rows, 'rate')],
                'totals' => ['Average Occupancy' => $avg . '%'],
            ];
        }

        case 'reservations': {
            $stmt = $db->prepare(
                "SELECT r.reservation_code, g.full_name AS guest, rm.room_number, r.check_in_date, r.check_out_date,
                        r.total_amount, r.payment_status, r.status
                 FROM reservations r JOIN guests g ON g.id = r.guest_id JOIN rooms rm ON rm.id = r.room_id
                 WHERE r.deleted_at IS NULL AND r.created_at BETWEEN :from AND :to2 ORDER BY r.created_at DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Reservation Report',
                'columns' => [['key' => 'reservation_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'guest', 'label' => 'Guest', 'type' => 'text'], ['key' => 'room_number', 'label' => 'Room', 'type' => 'text'], ['key' => 'check_in_date', 'label' => 'Check-in', 'type' => 'date'], ['key' => 'check_out_date', 'label' => 'Check-out', 'type' => 'date'], ['key' => 'total_amount', 'label' => 'Total', 'type' => 'currency'], ['key' => 'payment_status', 'label' => 'Payment', 'type' => 'text'], ['key' => 'status', 'label' => 'Status', 'type' => 'text']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Reservations' => count($rows)],
            ];
        }

        case 'cancelled_reservations': {
            $stmt = $db->prepare(
                "SELECT r.reservation_code, g.full_name AS guest, rm.room_number, r.check_in_date, r.check_out_date, r.total_amount
                 FROM reservations r JOIN guests g ON g.id = r.guest_id JOIN rooms rm ON rm.id = r.room_id
                 WHERE r.status = 'cancelled' AND r.updated_at BETWEEN :from AND :to2 ORDER BY r.updated_at DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Cancelled Reservations',
                'columns' => [['key' => 'reservation_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'guest', 'label' => 'Guest', 'type' => 'text'], ['key' => 'room_number', 'label' => 'Room', 'type' => 'text'], ['key' => 'check_in_date', 'label' => 'Check-in', 'type' => 'date'], ['key' => 'check_out_date', 'label' => 'Check-out', 'type' => 'date'], ['key' => 'total_amount', 'label' => 'Lost Value', 'type' => 'currency']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Cancelled' => count($rows)],
            ];
        }

        case 'checkin': {
            $stmt = $db->prepare(
                "SELECT r.reservation_code, g.full_name AS guest, rm.room_number, c.check_in_time, u.username AS checked_in_by
                 FROM checkins c JOIN reservations r ON r.id = c.reservation_id JOIN guests g ON g.id = r.guest_id
                 JOIN rooms rm ON rm.id = r.room_id LEFT JOIN users u ON u.id = c.checked_in_by
                 WHERE c.check_in_time BETWEEN :from AND :to2 ORDER BY c.check_in_time DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Check-In Report',
                'columns' => [['key' => 'reservation_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'guest', 'label' => 'Guest', 'type' => 'text'], ['key' => 'room_number', 'label' => 'Room', 'type' => 'text'], ['key' => 'check_in_time', 'label' => 'Checked In', 'type' => 'datetime'], ['key' => 'checked_in_by', 'label' => 'By', 'type' => 'text']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Check-ins' => count($rows)],
            ];
        }

        case 'checkout': {
            $stmt = $db->prepare(
                "SELECT r.reservation_code, g.full_name AS guest, rm.room_number, co.check_out_time, co.outstanding_balance, u.username AS checked_out_by
                 FROM checkouts co JOIN reservations r ON r.id = co.reservation_id JOIN guests g ON g.id = r.guest_id
                 JOIN rooms rm ON rm.id = r.room_id LEFT JOIN users u ON u.id = co.checked_out_by
                 WHERE co.check_out_time BETWEEN :from AND :to2 ORDER BY co.check_out_time DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Check-Out Report',
                'columns' => [['key' => 'reservation_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'guest', 'label' => 'Guest', 'type' => 'text'], ['key' => 'room_number', 'label' => 'Room', 'type' => 'text'], ['key' => 'check_out_time', 'label' => 'Checked Out', 'type' => 'datetime'], ['key' => 'outstanding_balance', 'label' => 'Outstanding', 'type' => 'currency'], ['key' => 'checked_out_by', 'label' => 'By', 'type' => 'text']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Check-outs' => count($rows)],
            ];
        }

        case 'guests': {
            $stmt = $db->prepare(
                "SELECT g.full_name, g.phone, g.country,
                        (SELECT COUNT(*) FROM reservations r WHERE r.guest_id = g.id AND r.deleted_at IS NULL AND r.status != 'cancelled') AS stays,
                        (SELECT COALESCE(SUM(total_amount),0) FROM reservations r WHERE r.guest_id = g.id AND r.deleted_at IS NULL AND r.status != 'cancelled') AS spent
                 FROM guests g WHERE g.created_at BETWEEN :from AND :to2 AND g.deleted_at IS NULL ORDER BY spent DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Guest Report',
                'columns' => [['key' => 'full_name', 'label' => 'Guest', 'type' => 'text'], ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'], ['key' => 'country', 'label' => 'Country', 'type' => 'text'], ['key' => 'stays', 'label' => 'Stays', 'type' => 'number'], ['key' => 'spent', 'label' => 'Total Spent', 'type' => 'currency']],
                'rows' => $rows, 'chart' => null, 'totals' => ['New Guests' => count($rows)],
            ];
        }

        case 'payments': {
            $stmt = $db->prepare(
                "SELECT p.paid_at, g.full_name AS guest, r.reservation_code, p.amount, p.payment_method, p.reference_number
                 FROM payments p LEFT JOIN guests g ON g.id = p.guest_id LEFT JOIN reservations r ON r.id = p.reservation_id
                 WHERE p.paid_at BETWEEN :from AND :to2 ORDER BY p.paid_at DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            $byMethod = [];
            foreach ($rows as $r) $byMethod[$r['payment_method']] = ($byMethod[$r['payment_method']] ?? 0) + (float) $r['amount'];
            return [
                'title' => 'Payment Report',
                'columns' => [['key' => 'paid_at', 'label' => 'Date', 'type' => 'datetime'], ['key' => 'guest', 'label' => 'Guest', 'type' => 'text'], ['key' => 'reservation_code', 'label' => 'Reservation', 'type' => 'text'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'currency'], ['key' => 'payment_method', 'label' => 'Method', 'type' => 'text'], ['key' => 'reference_number', 'label' => 'Reference', 'type' => 'text']],
                'rows' => $rows,
                'chart' => ['type' => 'doughnut', 'labels' => array_keys($byMethod), 'values' => array_values($byMethod)],
                'totals' => ['Total Collected' => array_sum(array_column($rows, 'amount'))],
            ];
        }

        case 'outstanding_balances': {
            $stmt = $db->query(
                "SELECT r.reservation_code, g.full_name AS guest, rm.room_number, r.total_amount,
                        (r.total_amount - COALESCE((SELECT SUM(amount) FROM payments WHERE reservation_id = r.id),0)) AS balance
                 FROM reservations r JOIN guests g ON g.id = r.guest_id JOIN rooms rm ON rm.id = r.room_id
                 WHERE r.payment_status != 'paid' AND r.deleted_at IS NULL AND r.status != 'cancelled'
                 HAVING balance > 0 ORDER BY balance DESC"
            );
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Outstanding Balances',
                'columns' => [['key' => 'reservation_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'guest', 'label' => 'Guest', 'type' => 'text'], ['key' => 'room_number', 'label' => 'Room', 'type' => 'text'], ['key' => 'total_amount', 'label' => 'Total', 'type' => 'currency'], ['key' => 'balance', 'label' => 'Balance Due', 'type' => 'currency']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Total Outstanding' => array_sum(array_column($rows, 'balance'))],
            ];
        }

        case 'expenses': {
            $stmt = $db->prepare(
                "SELECT e.expense_date, e.category, e.description, e.amount, e.payment_method, s.full_name AS staff_name
                 FROM expenses e LEFT JOIN staff s ON s.id = e.staff_id
                 WHERE e.expense_date BETWEEN :from AND :to ORDER BY e.expense_date DESC"
            );
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll();
            $byCategory = [];
            foreach ($rows as $r) $byCategory[$r['category']] = ($byCategory[$r['category']] ?? 0) + (float) $r['amount'];
            return [
                'title' => 'Expense Report',
                'columns' => [['key' => 'expense_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'category', 'label' => 'Category', 'type' => 'text'], ['key' => 'description', 'label' => 'Description', 'type' => 'text'], ['key' => 'amount', 'label' => 'Amount', 'type' => 'currency'], ['key' => 'payment_method', 'label' => 'Method', 'type' => 'text'], ['key' => 'staff_name', 'label' => 'Recorded By', 'type' => 'text']],
                'rows' => $rows,
                'chart' => ['type' => 'doughnut', 'labels' => array_keys($byCategory), 'values' => array_values($byCategory)],
                'totals' => ['Total Expenses' => array_sum(array_column($rows, 'amount'))],
            ];
        }

        case 'events': {
            $stmt = $db->prepare(
                "SELECT event_code, event_name, client_name, event_type, event_date, venue, price, status
                 FROM events WHERE deleted_at IS NULL AND event_date BETWEEN :from AND :to ORDER BY event_date"
            );
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Event Report',
                'columns' => [['key' => 'event_code', 'label' => 'Code', 'type' => 'text'], ['key' => 'event_name', 'label' => 'Event', 'type' => 'text'], ['key' => 'client_name', 'label' => 'Client', 'type' => 'text'], ['key' => 'event_type', 'label' => 'Type', 'type' => 'text'], ['key' => 'event_date', 'label' => 'Date', 'type' => 'date'], ['key' => 'venue', 'label' => 'Venue', 'type' => 'text'], ['key' => 'price', 'label' => 'Price', 'type' => 'currency'], ['key' => 'status', 'label' => 'Status', 'type' => 'text']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Total Events' => count($rows), 'Total Value' => array_sum(array_column($rows, 'price'))],
            ];
        }

        case 'restaurant_sales': {
            $stmt = $db->prepare(
                "SELECT o.order_code, o.order_type, o.created_at, o.total, o.status, rt.table_number
                 FROM orders o LEFT JOIN restaurant_tables rt ON rt.id = o.table_id
                 WHERE o.status = 'paid' AND o.created_at BETWEEN :from AND :to2 ORDER BY o.created_at DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Restaurant Sales Report',
                'columns' => [['key' => 'order_code', 'label' => 'Order', 'type' => 'text'], ['key' => 'order_type', 'label' => 'Type', 'type' => 'text'], ['key' => 'table_number', 'label' => 'Table', 'type' => 'text'], ['key' => 'created_at', 'label' => 'Date', 'type' => 'datetime'], ['key' => 'total', 'label' => 'Total', 'type' => 'currency']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Total Sales' => array_sum(array_column($rows, 'total'))],
            ];
        }

        case 'staff_activity': {
            $stmt = $db->prepare(
                "SELECT u.username, COUNT(*) AS actions, MAX(al.created_at) AS last_action
                 FROM audit_logs al JOIN users u ON u.id = al.user_id
                 WHERE al.created_at BETWEEN :from AND :to2 GROUP BY u.username ORDER BY actions DESC"
            );
            $stmt->execute([':from' => $from . ' 00:00:00', ':to2' => $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Staff Activity Report',
                'columns' => [['key' => 'username', 'label' => 'User', 'type' => 'text'], ['key' => 'actions', 'label' => 'Actions Logged', 'type' => 'number'], ['key' => 'last_action', 'label' => 'Last Action', 'type' => 'datetime']],
                'rows' => $rows, 'chart' => null, 'totals' => ['Active Users' => count($rows)],
            ];
        }

        case 'room_performance': {
            $stmt = $db->prepare(
                "SELECT rm.room_number, rt.name AS room_type,
                        COUNT(r.id) AS bookings, COALESCE(SUM(r.total_amount),0) AS revenue
                 FROM rooms rm JOIN room_types rt ON rt.id = rm.room_type_id
                 LEFT JOIN reservations r ON r.room_id = rm.id AND r.status != 'cancelled' AND r.deleted_at IS NULL
                     AND r.check_in_date BETWEEN :from AND :to
                 WHERE rm.deleted_at IS NULL GROUP BY rm.id ORDER BY revenue DESC"
            );
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll();
            return [
                'title' => 'Room Performance Report',
                'columns' => [['key' => 'room_number', 'label' => 'Room', 'type' => 'text'], ['key' => 'room_type', 'label' => 'Type', 'type' => 'text'], ['key' => 'bookings', 'label' => 'Bookings', 'type' => 'number'], ['key' => 'revenue', 'label' => 'Revenue', 'type' => 'currency']],
                'rows' => $rows,
                'chart' => ['type' => 'bar', 'labels' => array_column($rows, 'room_number'), 'values' => array_map('floatval', array_column($rows, 'revenue'))],
                'totals' => ['Total Revenue' => array_sum(array_column($rows, 'revenue'))],
            ];
        }

        default:
            return ['title' => 'Unknown Report', 'columns' => [], 'rows' => [], 'chart' => null, 'totals' => []];
    }
}

const REPORT_TYPES = [
    'revenue' => 'Revenue Report',
    'yearly_revenue' => 'Yearly Revenue',
    'occupancy' => 'Occupancy Report',
    'reservations' => 'Reservation Report',
    'cancelled_reservations' => 'Cancelled Reservations',
    'checkin' => 'Check-In Report',
    'checkout' => 'Check-Out Report',
    'guests' => 'Guest Report',
    'payments' => 'Payment Report',
    'outstanding_balances' => 'Outstanding Balances',
    'expenses' => 'Expense Report',
    'events' => 'Event Report',
    'restaurant_sales' => 'Restaurant Sales Report',
    'staff_activity' => 'Staff Activity Report',
    'room_performance' => 'Room Performance Report',
];
