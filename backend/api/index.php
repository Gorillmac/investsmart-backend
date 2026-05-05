<?php
declare(strict_types=1);

require_once __DIR__ . '/../Auth.php';
require_once __DIR__ . '/../RecommendationService.php';

apply_cors();
start_app_session();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::connection();

try {
    if ($action === 'register' && $method === 'POST') {
        $data = request_json();
        require_fields($data, ['full_name', 'surname', 'id_number', 'email', 'password', 'confirm_password']);
        if ($data['password'] !== $data['confirm_password']) {
            json_response(['ok' => false, 'message' => 'Passwords do not match.'], 422);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            json_response(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO users (full_name, surname, id_number, email, password_hash) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            trim((string)$data['full_name']),
            trim((string)$data['surname']),
            trim((string)$data['id_number']),
            trim((string)$data['email']),
            password_hash((string)$data['password'], PASSWORD_DEFAULT),
        ]);

        $_SESSION['user_id'] = (int)$pdo->lastInsertId();
        json_response(['ok' => true, 'message' => 'Account created.']);
    }

    if ($action === 'login' && $method === 'POST') {
        $data = request_json();
        require_fields($data, ['email', 'password']);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([trim((string)$data['email'])]);
        $user = $stmt->fetch();

        if (!$user || !password_verify((string)$data['password'], $user['password_hash']) || $user['status'] !== 'active') {
            json_response(['ok' => false, 'message' => 'Invalid credentials or inactive account.'], 401);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        json_response(['ok' => true, 'user' => clean_user($user)]);
    }

    if ($action === 'logout') {
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($action === 'me') {
        $user = Auth::currentUser();
        if (!$user) {
            json_response(['ok' => true, 'user' => null]);
        }
        $stmt = $pdo->prepare('SELECT * FROM financial_profiles WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        json_response(['ok' => true, 'user' => clean_user($user), 'finance' => $stmt->fetch() ?: null]);
    }

    if ($action === 'profile' && $method === 'POST') {
        $user = Auth::requireUser();
        $data = request_json();
        $stmt = $pdo->prepare('UPDATE users SET email = ?, contact_info = ? WHERE id = ?');
        $stmt->execute([trim((string)$data['email']), trim((string)($data['contact_info'] ?? '')), $user['id']]);
        json_response(['ok' => true, 'message' => 'Profile updated.']);
    }

    if ($action === 'finance' && $method === 'POST') {
        $user = Auth::requireUser();
        $data = request_json();
        $gross = money_value($data['gross_salary'] ?? null);
        $deductions = money_value($data['deductions'] ?? null);
        $expenses = money_value($data['monthly_expenses'] ?? null);
        $savings = money_value($data['current_savings'] ?? null);
        $net = max(0, $gross - $deductions - $expenses);

        $stmt = $pdo->prepare(
            'INSERT INTO financial_profiles (user_id, gross_salary, deductions, monthly_expenses, current_savings, net_salary)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE gross_salary = VALUES(gross_salary), deductions = VALUES(deductions),
             monthly_expenses = VALUES(monthly_expenses), current_savings = VALUES(current_savings), net_salary = VALUES(net_salary)'
        );
        $stmt->execute([$user['id'], $gross, $deductions, $expenses, $savings, $net]);
        json_response(['ok' => true, 'message' => 'Financial data saved.', 'net_salary' => $net]);
    }

    if ($action === 'recommend' && $method === 'POST') {
        $user = Auth::requireUser();
        $data = request_json();
        require_fields($data, ['plan_name', 'investment_amount', 'horizon', 'risk', 'liquidity', 'investment_goal']);
        $age = calculate_age_from_id((string)$user['id_number']) ?? 30;
        $data['monthly_contribution'] = !empty($data['monthly_contribution']);
        $recommendations = RecommendationService::recommend($data, $age);
        json_response(['ok' => true, 'recommendations' => $recommendations]);
    }

    if ($action === 'plans' && $method === 'GET') {
        $user = Auth::requireUser();
        $ownerId = $user['role'] === 'admin' && isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$user['id'];
        $stmt = $pdo->prepare(
            'SELECT p.*, b.name bank_name, b.contact_info bank_contact, b.website bank_website, b.details bank_details
             FROM investment_plans p LEFT JOIN banks b ON b.id = p.bank_id WHERE p.user_id = ? ORDER BY p.created_at DESC'
        );
        $stmt->execute([$ownerId]);
        json_response(['ok' => true, 'plans' => $stmt->fetchAll()]);
    }

    if ($action === 'plans' && $method === 'POST') {
        $user = Auth::requireUser();
        $data = request_json();
        require_fields($data, ['bank_id', 'user_plan_name', 'investment_amount', 'risk', 'liquidity', 'horizon', 'expected_return']);
        $stmt = $pdo->prepare(
            'INSERT INTO investment_plans (user_id, bank_id, user_plan_name, investment_amount, monthly_contribution, monthly_amount, investment_goal, risk, liquidity, horizon, expected_return, score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'],
            (int)$data['bank_id'],
            trim((string)$data['user_plan_name']),
            money_value($data['investment_amount']),
            !empty($data['monthly_contribution']) ? 1 : 0,
            money_value($data['monthly_amount'] ?? 0),
            trim((string)($data['investment_goal'] ?? '')),
            $data['risk'],
            $data['liquidity'],
            $data['horizon'],
            money_value($data['expected_return']),
            (int)($data['score'] ?? 0),
        ]);
        json_response(['ok' => true, 'message' => 'Plan saved.']);
    }

    if ($action === 'plan' && $method === 'PUT') {
        $user = Auth::requireUser();
        $data = request_json();
        require_fields($data, ['id', 'user_plan_name', 'investment_amount', 'investment_goal']);
        $stmt = $pdo->prepare('UPDATE investment_plans SET user_plan_name = ?, investment_amount = ?, investment_goal = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([trim((string)$data['user_plan_name']), money_value($data['investment_amount']), trim((string)$data['investment_goal']), (int)$data['id'], $user['id']]);
        json_response(['ok' => true, 'message' => 'Plan updated.']);
    }

    if ($action === 'plan' && $method === 'DELETE') {
        $user = Auth::requireUser();
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM investment_plans WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        json_response(['ok' => true, 'message' => 'Plan deleted.']);
    }

    if ($action === 'admin-users') {
        Auth::requireAdmin();
        $rows = $pdo->query('SELECT id, full_name, surname, id_number, email, role, status, contact_info, created_at FROM users ORDER BY created_at DESC')->fetchAll();
        foreach ($rows as &$row) {
            $row['age'] = calculate_age_from_id((string)$row['id_number']);
        }
        json_response(['ok' => true, 'users' => $rows]);
    }

    if ($action === 'admin-user-status' && $method === 'POST') {
        Auth::requireAdmin();
        $data = request_json();
        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ? AND role != "admin"');
        $stmt->execute([$data['status'] === 'inactive' ? 'inactive' : 'active', (int)$data['id']]);
        json_response(['ok' => true, 'message' => 'User status updated.']);
    }

    if ($action === 'admin-user-delete' && $method === 'DELETE') {
        Auth::requireAdmin();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role != "admin"');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        json_response(['ok' => true, 'message' => 'User deleted.']);
    }

    if ($action === 'admin-user-update' && $method === 'POST') {
        Auth::requireAdmin();
        $data = request_json();
        require_fields($data, ['id', 'full_name', 'surname', 'email']);
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, surname = ?, email = ?, contact_info = ? WHERE id = ?');
        $stmt->execute([
            trim((string)$data['full_name']),
            trim((string)$data['surname']),
            trim((string)$data['email']),
            trim((string)($data['contact_info'] ?? '')),
            (int)$data['id'],
        ]);
        json_response(['ok' => true, 'message' => 'User updated.']);
    }

    if ($action === 'admin-reset-password' && $method === 'POST') {
        Auth::requireAdmin();
        $data = request_json();
        require_fields($data, ['id']);
        $temporary = 'Invest123!';
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role != "admin"');
        $stmt->execute([password_hash($temporary, PASSWORD_DEFAULT), (int)$data['id']]);
        json_response(['ok' => true, 'message' => 'Password reset.', 'temporary_password' => $temporary]);
    }

    if ($action === 'banks' && $method === 'GET') {
        Auth::requireUser();
        json_response(['ok' => true, 'banks' => $pdo->query('SELECT * FROM banks ORDER BY name')->fetchAll()]);
    }

    if ($action === 'bank' && in_array($method, ['POST', 'PUT'], true)) {
        Auth::requireAdmin();
        $data = request_json();
        require_fields($data, ['name', 'contact_info', 'plan_type', 'expected_return', 'risk', 'liquidity', 'horizon']);
        if ($method === 'POST') {
            $stmt = $pdo->prepare('INSERT INTO banks (name, contact_info, website, plan_type, expected_return, risk, liquidity, horizon, allows_monthly, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['name'], $data['contact_info'], $data['website'] ?? '', $data['plan_type'], money_value($data['expected_return']), $data['risk'], $data['liquidity'], $data['horizon'], !empty($data['allows_monthly']) ? 1 : 0, $data['details'] ?? '']);
        } else {
            $stmt = $pdo->prepare('UPDATE banks SET name = ?, contact_info = ?, website = ?, plan_type = ?, expected_return = ?, risk = ?, liquidity = ?, horizon = ?, allows_monthly = ?, details = ? WHERE id = ?');
            $stmt->execute([$data['name'], $data['contact_info'], $data['website'] ?? '', $data['plan_type'], money_value($data['expected_return']), $data['risk'], $data['liquidity'], $data['horizon'], !empty($data['allows_monthly']) ? 1 : 0, $data['details'] ?? '', (int)$data['id']]);
        }
        json_response(['ok' => true, 'message' => 'Bank saved.']);
    }

    if ($action === 'bank' && $method === 'DELETE') {
        Auth::requireAdmin();
        $stmt = $pdo->prepare('DELETE FROM banks WHERE id = ?');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        json_response(['ok' => true, 'message' => 'Bank removed.']);
    }

    if ($action === 'admin-report') {
        Auth::requireAdmin();
        $metrics = [
            'total_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn(),
            'total_savings' => (float)$pdo->query('SELECT COALESCE(SUM(current_savings), 0) FROM financial_profiles')->fetchColumn(),
            'total_plans' => (int)$pdo->query('SELECT COUNT(*) FROM investment_plans')->fetchColumn(),
        ];
        $risk = $pdo->query('SELECT risk, COUNT(*) total FROM investment_plans GROUP BY risk')->fetchAll();
        json_response(['ok' => true, 'metrics' => $metrics, 'risk' => $risk]);
    }

    json_response(['ok' => false, 'message' => 'Unknown endpoint.'], 404);
} catch (PDOException $exception) {
    json_response(['ok' => false, 'message' => 'Database error: ' . $exception->getMessage()], 500);
}
