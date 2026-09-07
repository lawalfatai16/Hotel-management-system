<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('restaurant', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();
$resource = $_GET['resource'] ?? null;

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db, $resource) {
            switch ($resource) {
                case 'categories':
                    jsonResponse(['success' => true, 'data' => $db->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll()]);

                case 'items':
                    $search = trim($_GET['search'] ?? '');
                    $where = '1=1'; $params = [];
                    if ($search !== '') { $where .= ' AND mi.name LIKE :s'; $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%"; }
                    $stmt = $db->prepare(
                        "SELECT mi.*, mc.name AS category_name FROM menu_items mi
                         JOIN menu_categories mc ON mc.id = mi.category_id WHERE {$where} ORDER BY mc.name, mi.name"
                    );
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll();
                    foreach ($rows as &$r) $r['price_display'] = formatCurrency((float) $r['price']);
                    jsonResponse(['success' => true, 'data' => $rows]);

                case 'tables':
                    jsonResponse(['success' => true, 'data' => $db->query("SELECT * FROM restaurant_tables ORDER BY table_number")->fetchAll()]);

                case 'order':
                    $id = (int) ($_GET['id'] ?? 0);
                    $stmt = $db->prepare(
                        "SELECT o.*, rt.table_number, g.full_name AS guest_name, r.reservation_code, s.full_name AS staff_name
                         FROM orders o
                         LEFT JOIN restaurant_tables rt ON rt.id = o.table_id
                         LEFT JOIN guests g ON g.id = o.guest_id
                         LEFT JOIN reservations r ON r.id = o.reservation_id
                         LEFT JOIN staff s ON s.id = o.staff_id
                         WHERE o.id = :id"
                    );
                    $stmt->execute([':id' => $id]);
                    $order = $stmt->fetch();
                    if (!$order) jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
                    $itemsStmt = $db->prepare(
                        "SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON mi.id = oi.menu_item_id WHERE oi.order_id = :id"
                    );
                    $itemsStmt->execute([':id' => $id]);
                    $order['items'] = $itemsStmt->fetchAll();
                    jsonResponse(['success' => true, 'data' => $order]);

                default: // orders list
                    $status = $_GET['status'] ?? '';
                    $search = trim($_GET['search'] ?? '');
                    $where = ['1=1']; $params = [];
                    if ($status !== '') { $where[] = 'o.status = :status'; $params[':status'] = $status; }
                    if ($search !== '') { $where[] = '(o.order_code LIKE :s1 OR g.full_name LIKE :s2 OR r.reservation_code LIKE :s3)'; $params[':s'] = "%{$search}%"; }
                    $whereSql = implode(' AND ', $where);
                    $stmt = $db->prepare(
                        "SELECT o.*, rt.table_number, g.full_name AS guest_name, r.reservation_code
                         FROM orders o
                         LEFT JOIN restaurant_tables rt ON rt.id = o.table_id
                         LEFT JOIN guests g ON g.id = o.guest_id
                         LEFT JOIN reservations r ON r.id = o.reservation_id
                         WHERE {$whereSql} ORDER BY o.created_at DESC LIMIT 100"
                    );
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll();
                    foreach ($rows as &$r) $r['total_display'] = formatCurrency((float) $r['total']);
                    jsonResponse(['success' => true, 'data' => $rows]);
            }
        });
        break;

    case 'POST':
        safeExecute(function () use ($db, $user, $resource) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }

            if ($resource === 'categories') {
                if (empty($input['name'])) jsonResponse(['success' => false, 'message' => 'Category name is required.'], 422);
                $db->prepare("INSERT INTO menu_categories (name) VALUES (:name)")->execute([':name' => $input['name']]);
                jsonResponse(['success' => true, 'message' => 'Category added.', 'id' => $db->lastInsertId()]);
            }

            if ($resource === 'items') {
                if (empty($input['category_id']) || empty($input['name']) || !isset($input['price'])) {
                    jsonResponse(['success' => false, 'message' => 'Category, name, and price are required.'], 422);
                }
                $db->prepare(
                    "INSERT INTO menu_items (category_id, name, price, description, is_available) VALUES (:cat, :name, :price, :desc, :avail)"
                )->execute([
                    ':cat' => $input['category_id'], ':name' => $input['name'], ':price' => $input['price'],
                    ':desc' => $input['description'] ?? null, ':avail' => isset($input['is_available']) ? (int) $input['is_available'] : 1,
                ]);
                jsonResponse(['success' => true, 'message' => 'Menu item added.', 'id' => $db->lastInsertId()]);
            }

            if ($resource === 'tables') {
                if (empty($input['table_number'])) jsonResponse(['success' => false, 'message' => 'Table number is required.'], 422);
                $db->prepare("INSERT INTO restaurant_tables (table_number, capacity) VALUES (:num, :cap)")
                   ->execute([':num' => $input['table_number'], ':cap' => $input['capacity'] ?: 4]);
                jsonResponse(['success' => true, 'message' => 'Table added.', 'id' => $db->lastInsertId()]);
            }

            // Default: create an order
            $items = $input['items'] ?? [];
            if (!is_array($items) || count($items) === 0) jsonResponse(['success' => false, 'message' => 'Add at least one item to the order.'], 422);
            $orderType = $input['order_type'] ?? 'dine_in';
            if ($orderType === 'room_charge' && empty($input['reservation_id'])) {
                jsonResponse(['success' => false, 'message' => 'Select a reservation to charge this order to.'], 422);
            }
            if ($orderType === 'dine_in' && empty($input['table_id'])) {
                jsonResponse(['success' => false, 'message' => 'Select a table for a dine-in order.'], 422);
            }

            $db->beginTransaction();
            try {
                $subtotal = 0;
                $lineItems = [];
                foreach ($items as $item) {
                    $stmt = $db->prepare("SELECT price, name FROM menu_items WHERE id = :id AND is_available = 1");
                    $stmt->execute([':id' => $item['menu_item_id']]);
                    $menuItem = $stmt->fetch();
                    if (!$menuItem) throw new RuntimeException('One of the selected items is no longer available.');
                    $qty = max(1, (int) $item['quantity']);
                    $lineTotal = $menuItem['price'] * $qty;
                    $subtotal += $lineTotal;
                    $lineItems[] = ['menu_item_id' => $item['menu_item_id'], 'qty' => $qty, 'unit' => $menuItem['price'], 'total' => $lineTotal];
                }
                $taxRate = (float) setting('tax_rate', 0);
                $tax = round($subtotal * $taxRate / 100, 2);
                $total = $subtotal + $tax;

                $code = nextSequenceCode($db, 'orders', 'order_code', 'ORD-');
                $db->prepare(
                    "INSERT INTO orders (order_code, table_id, guest_id, reservation_id, order_type, status, subtotal, tax, total, staff_id)
                     VALUES (:code, :table_id, :guest_id, :reservation_id, :type, 'open', :subtotal, :tax, :total, NULL)"
                )->execute([
                    ':code' => $code, ':table_id' => $input['table_id'] ?: null, ':guest_id' => $input['guest_id'] ?: null,
                    ':reservation_id' => $input['reservation_id'] ?: null, ':type' => $orderType,
                    ':subtotal' => $subtotal, ':tax' => $tax, ':total' => $total,
                ]);
                $orderId = $db->lastInsertId();

                $itemStmt = $db->prepare(
                    "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, line_total) VALUES (:oid, :mid, :qty, :unit, :total)"
                );
                foreach ($lineItems as $li) {
                    $itemStmt->execute([':oid' => $orderId, ':mid' => $li['menu_item_id'], ':qty' => $li['qty'], ':unit' => $li['unit'], ':total' => $li['total']]);
                }

                if ($orderType === 'dine_in' && !empty($input['table_id'])) {
                    $db->prepare("UPDATE restaurant_tables SET status = 'occupied' WHERE id = :id")->execute([':id' => $input['table_id']]);
                }

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Auth::logAudit($user['id'], "{$user['username']} created order {$code} (" . formatCurrency($total) . ")", 'restaurant');
            jsonResponse(['success' => true, 'message' => "Order {$code} created.", 'id' => $orderId]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user, $resource) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }

            if ($resource === 'items') {
                $id = (int) ($input['id'] ?? 0);
                if (!$id) jsonResponse(['success' => false, 'message' => 'Missing item id.'], 422);
                $db->prepare(
                    "UPDATE menu_items SET category_id=:cat, name=:name, price=:price, description=:desc, is_available=:avail WHERE id=:id"
                )->execute([
                    ':cat' => $input['category_id'], ':name' => $input['name'], ':price' => $input['price'],
                    ':desc' => $input['description'] ?? null, ':avail' => isset($input['is_available']) ? (int) $input['is_available'] : 1, ':id' => $id,
                ]);
                jsonResponse(['success' => true, 'message' => 'Menu item updated.']);
            }

            // Order status update
            $id = (int) ($input['id'] ?? 0);
            $status = $input['status'] ?? '';
            $validStatuses = ['open', 'served', 'paid', 'cancelled'];
            if (!$id || !in_array($status, $validStatuses, true)) jsonResponse(['success' => false, 'message' => 'Invalid order update.'], 422);

            $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch();
            if (!$order) jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);

            $db->prepare("UPDATE orders SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $id]);

            if (in_array($status, ['paid', 'cancelled'], true) && $order['table_id']) {
                $db->prepare("UPDATE restaurant_tables SET status = 'available' WHERE id = :id")->execute([':id' => $order['table_id']]);
            }

            Auth::logAudit($user['id'], "{$user['username']} marked order {$order['order_code']} as {$status}", 'restaurant', $order['status'], $status);
            jsonResponse(['success' => true, 'message' => "Order marked {$status}."]);
        });
        break;

    case 'DELETE':
        safeExecute(function () use ($db, $user, $resource) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
            $csrf = $input['csrf_token'] ?? $_GET['csrf_token'] ?? null;
            if (!Auth::verifyCsrf($csrf)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing id.'], 422);

            if ($resource === 'items') {
                $inUse = $db->prepare("SELECT COUNT(*) FROM order_items WHERE menu_item_id = :id");
                $inUse->execute([':id' => $id]);
                if ((int) $inUse->fetchColumn() > 0) jsonResponse(['success' => false, 'message' => 'This item has order history and cannot be deleted. Mark it unavailable instead.'], 422);
                $db->prepare("DELETE FROM menu_items WHERE id = :id")->execute([':id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Menu item removed.']);
            }

            if ($resource === 'categories') {
                $inUse = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE category_id = :id");
                $inUse->execute([':id' => $id]);
                if ((int) $inUse->fetchColumn() > 0) jsonResponse(['success' => false, 'message' => 'This category still has menu items in it.'], 422);
                $db->prepare("DELETE FROM menu_categories WHERE id = :id")->execute([':id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Category removed.']);
            }

            if ($resource === 'tables') {
                $db->prepare("DELETE FROM restaurant_tables WHERE id = :id")->execute([':id' => $id]);
                jsonResponse(['success' => true, 'message' => 'Table removed.']);
            }

            jsonResponse(['success' => false, 'message' => 'Unsupported delete request.'], 422);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
