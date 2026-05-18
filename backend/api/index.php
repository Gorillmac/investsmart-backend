<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../Auth.php';
require_once __DIR__ . '/../Audit.php';
require_once __DIR__ . '/../RecommendationService.php';

bootstrap_cors();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$data = json_input();

function resolve_client_id(PDO $pdo, array $user, ?int $requestedUserId = null): int
{
    if (!empty($user['client_id'])) {
        return (int)$user['client_id'];
    }

    if ($requestedUserId) {
        $target = fetch_user_by_id($pdo, $requestedUserId);
        if ($target && !empty($target['client_id'])) {
            return (int)$target['client_id'];
        }
    }

    error_response('Client account not found.', 404);
}

try {
    switch ($action) {
        case 'register':
            require_method('POST');
            require_fields($data, ['full_name', 'surname', 'id_number', 'email', 'password', 'confirm_password']);

            if ((string)$data['password'] !== (string)$data['confirm_password']) {
                error_response('Passwords do not match.', 422);
            }

            $email = strtolower(trim((string)$data['email']));
            $fullName = trim((string)$data['full_name']);
            $surname = trim((string)$data['surname']);
            $idNumber = trim((string)$data['id_number']);
            $contactInfo = trim((string)($data['contact_info'] ?? ''));

            if (fetch_user_by_email($pdo, $email)) {
                response([
                    'ok' => true,
                    'created' => false,
                    'message' => 'That account already exists. Please sign in.',
                    'redirect' => 'signin',
                    'email' => $email,
                ]);
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([
                    $email,
                    password_hash((string)$data['password'], PASSWORD_DEFAULT),
                    'client',
                    'active',
                ]);

                $userId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO clients (user_id, full_name, surname, id_number, contact_info) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $userId,
                    $fullName,
                    $surname,
                    $idNumber,
                    $contactInfo,
                ]);

                $pdo->commit();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (fetch_user_by_email($pdo, $email)) {
                    response([
                        'ok' => true,
                        'created' => false,
                        'message' => 'That account already exists. Please sign in.',
                        'redirect' => 'signin',
                        'email' => $email,
                    ]);
                }

                throw $exception;
            }

            $user = fetch_user_by_email($pdo, $email);
            if ($user && !empty($user['client_id'])) {
                Audit::log((int)$user['id'], 'register', 'client', (int)$user['client_id'], 'Client account created.');
            }

            response([
                'ok' => true,
                'created' => true,
                'message' => 'Account created successfully. Please sign in.',
                'redirect' => 'signin',
                'email' => $email,
            ]);

        case 'login':
            require_method('POST');
            require_fields($data, ['email', 'password']);
            $user = fetch_user_by_email($pdo, strtolower(trim((string)$data['email'])));

            if (!$user || !password_verify((string)$data['password'], (string)$user['password_hash'])) {
                error_response('Invalid email or password.', 401);
            }

            if (($user['status'] ?? '') !== 'active') {
                error_response('This account is inactive. Please contact the administrator.', 403);
            }

            $token = issue_token($pdo, (int)$user['id']);
            Audit::log((int)$user['id'], 'login', (string)$user['role'], (int)($user['client_id'] ?: $user['admin_id'] ?: $user['id']), 'User signed in.');

            response([
                'ok' => true,
                'token' => $token,
                'user' => clean_user($user),
            ]);

        case 'logout':
            require_method('POST');
            $currentUser = Auth::currentUser();
            if ($currentUser) {
                revoke_token($pdo, get_bearer_token());
                Audit::log((int)$currentUser['id'], 'logout', (string)$currentUser['role'], (int)($currentUser['client_id'] ?: $currentUser['admin_id'] ?: $currentUser['id']), 'User signed out.');
            }
            response(['ok' => true]);

        case 'me':
            $user = Auth::currentUser();
            if (!$user) {
                response(['ok' => true, 'user' => null]);
            }

            $finance = null;
            if (($user['role'] ?? '') === 'client' && !empty($user['client_id'])) {
                $stmt = $pdo->prepare('SELECT * FROM financial_profiles WHERE client_id = ?');
                $stmt->execute([(int)$user['client_id']]);
                $finance = $stmt->fetch() ?: null;
            }

            response(['ok' => true, 'user' => clean_user($user), 'finance' => $finance]);

        case 'profile':
            require_method('POST');
            $user = Auth::requireUser();
            require_fields($data, ['email']);

            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([
                strtolower(trim((string)$data['email'])),
                (int)$user['id'],
            ]);

            if (($user['role'] ?? '') === 'client' && !empty($user['client_id'])) {
                $pdo->prepare('UPDATE clients SET contact_info = ? WHERE id = ?')->execute([
                    trim((string)($data['contact_info'] ?? '')),
                    (int)$user['client_id'],
                ]);
            }

            if (($user['role'] ?? '') === 'admin' && !empty($user['admin_id'])) {
                $pdo->prepare('UPDATE admins SET contact_info = ? WHERE id = ?')->execute([
                    trim((string)($data['contact_info'] ?? '')),
                    (int)$user['admin_id'],
                ]);
            }

            $fresh = fetch_user_by_id($pdo, (int)$user['id']);
            Audit::log((int)$user['id'], 'update_profile', (string)$user['role'], (int)($user['client_id'] ?: $user['admin_id'] ?: $user['id']), 'Profile details updated.');
            response(['ok' => true, 'user' => clean_user($fresh)]);

        case 'finance':
            require_method('POST');
            $user = Auth::requireClient();

            $gross = money_value($data['gross_salary'] ?? 0);
            $deductions = money_value($data['deductions'] ?? 0);
            $expenses = money_value($data['monthly_expenses'] ?? 0);
            $savings = money_value($data['current_savings'] ?? 0);
            $net = $gross - $deductions - $expenses;

            $stmt = $pdo->prepare(
                'INSERT INTO financial_profiles (client_id, gross_salary, deductions, monthly_expenses, current_savings, net_salary)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE gross_salary = VALUES(gross_salary), deductions = VALUES(deductions),
                 monthly_expenses = VALUES(monthly_expenses), current_savings = VALUES(current_savings), net_salary = VALUES(net_salary)'
            );
            $stmt->execute([(int)$user['client_id'], $gross, $deductions, $expenses, $savings, $net]);

            Audit::log((int)$user['id'], 'save_finance', 'financial_profile', (int)$user['client_id'], 'Financial profile saved or updated.');
            response(['ok' => true, 'net_salary' => $net]);

        case 'recommend':
            require_method('POST');
            $user = Auth::requireClient();
            $service = new RecommendationService($pdo);
            $result = $service->recommend($user, $data);
            response(['ok' => true, 'results' => $result]);

        case 'plans':
            if ($method === 'GET') {
                $user = Auth::requireUser();
                $clientId = resolve_client_id($pdo, $user, isset($_GET['user_id']) ? (int)$_GET['user_id'] : null);
                $stmt = $pdo->prepare(
                    'SELECT p.*, b.name AS bank_name, b.contact_info AS bank_contact,
                            COALESCE(p.bank_investment_url, b.website) AS bank_website,
                            b.details AS bank_details
                     FROM investment_plans p
                     LEFT JOIN banks b ON b.id = p.bank_id
                     WHERE p.client_id = ?
                     ORDER BY p.created_at DESC'
                );
                $stmt->execute([$clientId]);
                response(['ok' => true, 'plans' => $stmt->fetchAll()]);
            }

            if ($method === 'POST') {
                $user = Auth::requireClient();
                require_fields($data, ['bank_id', 'user_plan_name', 'investment_amount', 'risk', 'liquidity', 'horizon', 'expected_return', 'bank_investment_url']);

                $stmt = $pdo->prepare(
                    'INSERT INTO investment_plans (
                        client_id, bank_id, user_plan_name, investment_amount, monthly_contribution, monthly_amount,
                        investment_goal, risk, liquidity, horizon, expected_return, score, bank_investment_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    (int)$user['client_id'],
                    (int)$data['bank_id'],
                    trim((string)$data['user_plan_name']),
                    money_value($data['investment_amount']),
                    !empty($data['monthly_contribution']) ? 1 : 0,
                    money_value($data['monthly_amount'] ?? 0),
                    trim((string)($data['investment_goal'] ?? '')),
                    trim((string)$data['risk']),
                    trim((string)$data['liquidity']),
                    trim((string)$data['horizon']),
                    money_value($data['expected_return']),
                    (int)($data['score'] ?? 0),
                    trim((string)$data['bank_investment_url']),
                ]);

                Audit::log((int)$user['id'], 'save_plan', 'investment_plan', (int)$pdo->lastInsertId(), 'Investment plan saved.');
                response(['ok' => true]);
            }

            error_response('Method not allowed.', 405);

        case 'plan':
            if ($method === 'PUT') {
                $user = Auth::requireClient();
                require_fields($data, ['id', 'user_plan_name', 'investment_amount']);
                $stmt = $pdo->prepare('UPDATE investment_plans SET user_plan_name = ?, investment_amount = ?, investment_goal = ? WHERE id = ? AND client_id = ?');
                $stmt->execute([
                    trim((string)$data['user_plan_name']),
                    money_value($data['investment_amount']),
                    trim((string)($data['investment_goal'] ?? '')),
                    (int)$data['id'],
                    (int)$user['client_id'],
                ]);
                Audit::log((int)$user['id'], 'update_plan', 'investment_plan', (int)$data['id'], 'Investment plan updated.');
                response(['ok' => true]);
            }

            if ($method === 'DELETE') {
                $user = Auth::requireClient();
                $id = (int)($_GET['id'] ?? 0);
                $stmt = $pdo->prepare('DELETE FROM investment_plans WHERE id = ? AND client_id = ?');
                $stmt->execute([$id, (int)$user['client_id']]);
                Audit::log((int)$user['id'], 'delete_plan', 'investment_plan', $id, 'Investment plan deleted.');
                response(['ok' => true]);
            }

            error_response('Method not allowed.', 405);

        case 'banks':
            if ($method === 'GET') {
                response(['ok' => true, 'banks' => $pdo->query('SELECT * FROM banks ORDER BY name')->fetchAll()]);
            }

            $admin = Auth::requireAdmin();
            require_fields($data, ['name', 'contact_info', 'website', 'plan_type', 'expected_return', 'risk', 'liquidity', 'horizon']);

            if ($method === 'POST') {
                $stmt = $pdo->prepare('INSERT INTO banks (name, contact_info, website, plan_type, expected_return, risk, liquidity, horizon, allows_monthly, details, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    trim((string)$data['name']),
                    trim((string)$data['contact_info']),
                    trim((string)$data['website']),
                    trim((string)$data['plan_type']),
                    money_value($data['expected_return']),
                    trim((string)$data['risk']),
                    trim((string)$data['liquidity']),
                    trim((string)$data['horizon']),
                    !empty($data['allows_monthly']) ? 1 : 0,
                    trim((string)($data['details'] ?? '')),
                    (int)$admin['admin_id'],
                ]);
                Audit::log((int)$admin['id'], 'create_bank', 'admin', (int)$admin['admin_id'], 'Bank created.');
                response(['ok' => true]);
            }

            if ($method === 'PUT') {
                require_fields($data, ['id']);
                $stmt = $pdo->prepare('UPDATE banks SET name = ?, contact_info = ?, website = ?, plan_type = ?, expected_return = ?, risk = ?, liquidity = ?, horizon = ?, allows_monthly = ?, details = ? WHERE id = ?');
                $stmt->execute([
                    trim((string)$data['name']),
                    trim((string)$data['contact_info']),
                    trim((string)$data['website']),
                    trim((string)$data['plan_type']),
                    money_value($data['expected_return']),
                    trim((string)$data['risk']),
                    trim((string)$data['liquidity']),
                    trim((string)$data['horizon']),
                    !empty($data['allows_monthly']) ? 1 : 0,
                    trim((string)($data['details'] ?? '')),
                    (int)$data['id'],
                ]);
                Audit::log((int)$admin['id'], 'update_bank', 'admin', (int)$admin['admin_id'], 'Bank updated.');
                response(['ok' => true]);
            }

            if ($method === 'DELETE') {
                $id = (int)($_GET['id'] ?? 0);
                $stmt = $pdo->prepare('DELETE FROM banks WHERE id = ?');
                $stmt->execute([$id]);
                Audit::log((int)$admin['id'], 'delete_bank', 'admin', (int)$admin['admin_id'], 'Bank removed.');
                response(['ok' => true]);
            }

            error_response('Method not allowed.', 405);

        case 'admin-users':
            $admin = Auth::requireAdmin();
            $rows = $pdo->query(user_select_sql() . ' ORDER BY u.created_at DESC')->fetchAll();
            $users = array_map(static function (array $row): array {
                $clean = clean_user($row);
                $clean['user_type'] = $row['role'];
                return $clean;
            }, $rows);
            response(['ok' => true, 'users' => $users]);

        case 'admin-user-status':
            require_method('POST');
            $admin = Auth::requireAdmin();
            require_fields($data, ['id', 'status']);
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([
                trim((string)$data['status']),
                (int)$data['id'],
            ]);
            Audit::log((int)$admin['id'], 'change_user_status', 'admin', (int)$admin['admin_id'], 'User status changed.');
            response(['ok' => true]);

        case 'admin-user-delete':
            require_method('DELETE');
            $admin = Auth::requireAdmin();
            $id = (int)($_GET['id'] ?? 0);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            Audit::log((int)$admin['id'], 'delete_user', 'admin', (int)$admin['admin_id'], 'Client user deleted by admin.');
            response(['ok' => true]);

        case 'admin-user-update':
            require_method('POST');
            $admin = Auth::requireAdmin();
            require_fields($data, ['id', 'email']);
            $target = fetch_user_by_id($pdo, (int)$data['id']);
            if (!$target) {
                error_response('User not found.', 404);
            }

            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([
                strtolower(trim((string)$data['email'])),
                (int)$data['id'],
            ]);

            if (($target['role'] ?? '') === 'client' && !empty($target['client_id'])) {
                $pdo->prepare('UPDATE clients SET full_name = ?, surname = ?, contact_info = ? WHERE id = ?')->execute([
                    trim((string)($data['full_name'] ?? $target['full_name'] ?? '')),
                    trim((string)($data['surname'] ?? $target['surname'] ?? '')),
                    trim((string)($data['contact_info'] ?? $target['contact_info'] ?? '')),
                    (int)$target['client_id'],
                ]);
            }

            if (($target['role'] ?? '') === 'admin' && !empty($target['admin_id'])) {
                $pdo->prepare('UPDATE admins SET full_name = ?, surname = ?, contact_info = ? WHERE id = ?')->execute([
                    trim((string)($data['full_name'] ?? $target['full_name'] ?? '')),
                    trim((string)($data['surname'] ?? $target['surname'] ?? '')),
                    trim((string)($data['contact_info'] ?? $target['contact_info'] ?? '')),
                    (int)$target['admin_id'],
                ]);
            }

            Audit::log((int)$admin['id'], 'update_user', 'admin', (int)$admin['admin_id'], 'User record updated by admin.');
            response(['ok' => true]);

        case 'admin-reset-password':
            require_method('POST');
            $admin = Auth::requireAdmin();
            require_fields($data, ['id', 'password']);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
                password_hash((string)$data['password'], PASSWORD_DEFAULT),
                (int)$data['id'],
            ]);
            Audit::log((int)$admin['id'], 'reset_password', 'admin', (int)$admin['admin_id'], 'Temporary password issued by admin.');
            response(['ok' => true]);

        case 'admin-report':
            $admin = Auth::requireAdmin();

            $overview = [
                'total_users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
                'total_plans' => (int)$pdo->query('SELECT COUNT(*) FROM investment_plans')->fetchColumn(),
                'total_savings' => (float)$pdo->query('SELECT COALESCE(SUM(current_savings), 0) FROM financial_profiles')->fetchColumn(),
                'average_plan_amount' => (float)$pdo->query('SELECT COALESCE(AVG(investment_amount), 0) FROM investment_plans')->fetchColumn(),
                'projected_portfolio_total' => (float)$pdo->query('SELECT COALESCE(SUM(investment_amount), 0) FROM investment_plans')->fetchColumn(),
            ];

            $risk = $pdo->query('SELECT risk, COUNT(*) AS total FROM investment_plans GROUP BY risk ORDER BY total DESC')->fetchAll();
            $banks = $pdo->query('SELECT b.name, COUNT(p.id) AS total FROM banks b LEFT JOIN investment_plans p ON p.bank_id = b.id GROUP BY b.id, b.name ORDER BY total DESC, b.name ASC')->fetchAll();
            $planTypes = $pdo->query('SELECT plan_type, COUNT(*) AS total FROM banks GROUP BY plan_type ORDER BY total DESC, plan_type ASC')->fetchAll();
            $ageGroups = [];
            $clientRows = $pdo->query('SELECT id_number FROM clients')->fetchAll();
            foreach ($clientRows as $row) {
                $age = calculate_age_from_id((string)($row['id_number'] ?? ''));
                if ($age === null) {
                    continue;
                }
                $bucket = $age < 25 ? '18-24' : ($age < 35 ? '25-34' : ($age < 45 ? '35-44' : ($age < 55 ? '45-54' : '55+')));
                $ageGroups[$bucket] = ($ageGroups[$bucket] ?? 0) + 1;
            }

            response([
                'ok' => true,
                'overview' => $overview,
                'risk' => array_values($risk),
                'banks' => array_values($banks),
                'plan_types' => array_values($planTypes),
                'age_groups' => array_map(static fn($label, $total) => ['label' => $label, 'total' => $total], array_keys($ageGroups), array_values($ageGroups)),
            ]);

        case 'admin-activity':
            $admin = Auth::requireAdmin();
            $rows = $pdo->query(
                'SELECT a.*, u.email,
                        c.full_name AS client_name,
                        ad.full_name AS admin_name
                 FROM activity_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN clients c ON c.id = a.client_id
                 LEFT JOIN admins ad ON ad.id = a.admin_id
                 ORDER BY a.created_at DESC
                 LIMIT 100'
            )->fetchAll();
            response(['ok' => true, 'activity' => $rows]);

        case 'admin-export':
            $admin = Auth::requireAdmin();
            $type = $_GET['type'] ?? '';

            if ($type === 'users') {
                $rows = $pdo->query(user_select_sql() . ' ORDER BY u.created_at DESC')->fetchAll();
                csv_download('investsmart-users.csv', $rows);
            }

            if ($type === 'banks') {
                $rows = $pdo->query('SELECT * FROM banks ORDER BY name')->fetchAll();
                csv_download('investsmart-banks.csv', $rows);
            }

            if ($type === 'plans') {
                $rows = $pdo->query(
                    'SELECT p.user_plan_name, p.investment_amount, p.monthly_amount, p.investment_goal, p.risk, p.liquidity, p.horizon,
                            p.expected_return, p.bank_investment_url, b.name AS bank_name
                     FROM investment_plans p
                     LEFT JOIN banks b ON b.id = p.bank_id
                     ORDER BY p.created_at DESC'
                )->fetchAll();
                csv_download('investsmart-plans.csv', $rows);
            }

            if ($type === 'activity') {
                $rows = $pdo->query(
                    'SELECT a.action, a.entity_type, a.entity_id, a.description, a.created_at,
                            COALESCE(c.full_name, ad.full_name, u.email) AS actor
                     FROM activity_logs a
                     LEFT JOIN users u ON u.id = a.user_id
                     LEFT JOIN clients c ON c.id = a.client_id
                     LEFT JOIN admins ad ON ad.id = a.admin_id
                     ORDER BY a.created_at DESC'
                )->fetchAll();
                csv_download('investsmart-activity.csv', $rows);
            }

            error_response('Unknown export type.', 404);

        default:
            error_response('Unknown action.', 404);
    }
} catch (PDOException $exception) {
    $message = 'A database error occurred. Please try again.';

    if ($action === 'register') {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email !== '' && fetch_user_by_email($pdo, $email)) {
            response([
                'ok' => true,
                'created' => false,
                'message' => 'That account already exists. Please sign in.',
                'redirect' => 'signin',
                'email' => $email,
            ]);
        }
    }

    $duplicateHint = strtolower($exception->getMessage());
    if (str_contains($duplicateHint, 'duplicate') || str_contains($duplicateHint, 'unique')) {
        $message = 'That ID number or email address is already registered.';
    }

    error_response($message, 500, ['detail' => APP_DEBUG ? $exception->getMessage() : null]);
} catch (Throwable $exception) {
    error_response($exception->getMessage(), 500);
}
