<?php
declare(strict_types=1);

require_once __DIR__ . '/../Auth.php';
require_once __DIR__ . '/../Audit.php';
require_once __DIR__ . '/../RecommendationService.php';

apply_cors();
start_app_session();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::connection();

function projected_plan_total(array $plan): float
{
    $months = ($plan['horizon'] ?? 'Short') === 'Long' ? 60 : (($plan['horizon'] ?? 'Short') === 'Medium' ? 36 : 12);
    $principal = (float)($plan['investment_amount'] ?? 0);
    $monthlyContribution = (float)($plan['monthly_amount'] ?? 0);
    $monthlyRate = ((float)($plan['expected_return'] ?? 0)) / 100 / 12;
    $principalFutureValue = $principal * pow(1 + $monthlyRate, $months);
    $contributionFutureValue = 0.0;

    if ($monthlyContribution > 0) {
        $contributionFutureValue = $monthlyRate == 0.0
            ? $monthlyContribution * $months
            : $monthlyContribution * ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
    }

    return round($principalFutureValue + $contributionFutureValue, 2);
}

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

        $userId = (int)$pdo->lastInsertId();
        Audit::log($userId, 'register', 'user', $userId, 'User account created.');
        $_SESSION['user_id'] = $userId;
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        json_response(['ok' => true, 'message' => 'Account created.', 'user' => clean_user($user), 'token' => issue_auth_token($user)]);
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
        Audit::log((int)$user['id'], 'login', 'user', (int)$user['id'], 'User signed in.');
        json_response(['ok' => true, 'user' => clean_user($user), 'token' => issue_auth_token($user)]);
    }

    if ($action === 'logout') {
        $currentUser = Auth::currentUser();
        if ($currentUser) {
            Audit::log((int)$currentUser['id'], 'logout', 'user', (int)$currentUser['id'], 'User signed out.');
        }
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
        Audit::log((int)$user['id'], 'update_profile', 'user', (int)$user['id'], 'Profile details updated.');
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
        Audit::log((int)$user['id'], 'save_finance', 'financial_profile', (int)$user['id'], 'Financial profile saved or updated.');
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
        $planId = (int)$pdo->lastInsertId();
        Audit::log((int)$user['id'], 'save_plan', 'investment_plan', $planId, 'Investment plan saved.');
        json_response(['ok' => true, 'message' => 'Plan saved.']);
    }

    if ($action === 'plan' && $method === 'PUT') {
        $user = Auth::requireUser();
        $data = request_json();
        require_fields($data, ['id', 'user_plan_name', 'investment_amount', 'investment_goal']);
        $stmt = $pdo->prepare('UPDATE investment_plans SET user_plan_name = ?, investment_amount = ?, investment_goal = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([trim((string)$data['user_plan_name']), money_value($data['investment_amount']), trim((string)$data['investment_goal']), (int)$data['id'], $user['id']]);
        Audit::log((int)$user['id'], 'update_plan', 'investment_plan', (int)$data['id'], 'Investment plan updated.');
        json_response(['ok' => true, 'message' => 'Plan updated.']);
    }

    if ($action === 'plan' && $method === 'DELETE') {
        $user = Auth::requireUser();
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM investment_plans WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        Audit::log((int)$user['id'], 'delete_plan', 'investment_plan', $id, 'Investment plan deleted.');
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
        Audit::log((int)Auth::requireAdmin()['id'], 'change_user_status', 'user', (int)$data['id'], 'User status changed to ' . ($data['status'] === 'inactive' ? 'inactive' : 'active') . '.');
        json_response(['ok' => true, 'message' => 'User status updated.']);
    }

    if ($action === 'admin-user-delete' && $method === 'DELETE') {
        $admin = Auth::requireAdmin();
        $userId = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role != "admin"');
        $stmt->execute([$userId]);
        Audit::log((int)$admin['id'], 'delete_user', 'user', $userId, 'User deleted by admin.');
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
        Audit::log((int)Auth::requireAdmin()['id'], 'update_user', 'user', (int)$data['id'], 'User record updated by admin.');
        json_response(['ok' => true, 'message' => 'User updated.']);
    }

    if ($action === 'admin-reset-password' && $method === 'POST') {
        Auth::requireAdmin();
        $data = request_json();
        require_fields($data, ['id']);
        $temporary = 'Invest123!';
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role != "admin"');
        $stmt->execute([password_hash($temporary, PASSWORD_DEFAULT), (int)$data['id']]);
        Audit::log((int)Auth::requireAdmin()['id'], 'reset_password', 'user', (int)$data['id'], 'Temporary password issued by admin.');
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
            Audit::log((int)Auth::requireAdmin()['id'], 'create_bank', 'bank', (int)$pdo->lastInsertId(), 'Bank created.');
        } else {
            $stmt = $pdo->prepare('UPDATE banks SET name = ?, contact_info = ?, website = ?, plan_type = ?, expected_return = ?, risk = ?, liquidity = ?, horizon = ?, allows_monthly = ?, details = ? WHERE id = ?');
            $stmt->execute([$data['name'], $data['contact_info'], $data['website'] ?? '', $data['plan_type'], money_value($data['expected_return']), $data['risk'], $data['liquidity'], $data['horizon'], !empty($data['allows_monthly']) ? 1 : 0, $data['details'] ?? '', (int)$data['id']]);
            Audit::log((int)Auth::requireAdmin()['id'], 'update_bank', 'bank', (int)$data['id'], 'Bank updated.');
        }
        json_response(['ok' => true, 'message' => 'Bank saved.']);
    }

    if ($action === 'bank' && $method === 'DELETE') {
        $admin = Auth::requireAdmin();
        $bankId = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM banks WHERE id = ?');
        $stmt->execute([$bankId]);
        Audit::log((int)$admin['id'], 'delete_bank', 'bank', $bankId, 'Bank removed.');
        json_response(['ok' => true, 'message' => 'Bank removed.']);
    }

    if ($action === 'admin-report') {
        Auth::requireAdmin();
        $userRows = $pdo->query('SELECT id_number FROM users WHERE role = "user"')->fetchAll();
        $ages = array_values(array_filter(array_map(
            static fn (array $row): ?int => calculate_age_from_id((string)$row['id_number']),
            $userRows
        ), static fn (?int $age): bool => $age !== null));
        $allPlans = $pdo->query('SELECT investment_amount, monthly_amount, expected_return, horizon FROM investment_plans')->fetchAll();
        $projectedPortfolioTotal = array_reduce($allPlans, static fn (float $sum, array $plan): float => $sum + projected_plan_total($plan), 0.0);
        $dominantRisk = $pdo->query('SELECT risk, COUNT(*) total FROM investment_plans GROUP BY risk ORDER BY total DESC LIMIT 1')->fetch();
        $topBank = $pdo->query('SELECT b.name label, COUNT(*) total FROM investment_plans p LEFT JOIN banks b ON b.id = p.bank_id GROUP BY b.name ORDER BY total DESC LIMIT 1')->fetch();
        $metrics = [
            'total_users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn(),
            'total_savings' => (float)$pdo->query('SELECT COALESCE(SUM(current_savings), 0) FROM financial_profiles')->fetchColumn(),
            'total_plans' => (int)$pdo->query('SELECT COUNT(*) FROM investment_plans')->fetchColumn(),
            'average_plan_amount' => (float)$pdo->query('SELECT COALESCE(AVG(investment_amount), 0) FROM investment_plans')->fetchColumn(),
            'average_user_age' => $ages ? round(array_sum($ages) / count($ages), 1) : 0,
            'projected_portfolio_total' => round($projectedPortfolioTotal, 2),
            'dominant_risk' => $dominantRisk['risk'] ?? 'N/A',
            'top_bank' => $topBank['label'] ?? 'N/A',
        ];
        $risk = $pdo->query('SELECT risk, COUNT(*) total FROM investment_plans GROUP BY risk')->fetchAll();
        $banks = $pdo->query('SELECT b.name label, COUNT(*) total FROM investment_plans p LEFT JOIN banks b ON b.id = p.bank_id GROUP BY b.name ORDER BY total DESC')->fetchAll();
        $horizons = $pdo->query('SELECT horizon label, COUNT(*) total FROM investment_plans GROUP BY horizon ORDER BY total DESC')->fetchAll();
        $planTypes = $pdo->query('SELECT b.plan_type label, COUNT(*) total FROM investment_plans p LEFT JOIN banks b ON b.id = p.bank_id GROUP BY b.plan_type ORDER BY total DESC')->fetchAll();
        $ageGroups = $pdo->query("
            SELECT
              CASE
                WHEN CAST(LEFT(id_number, 2) AS UNSIGNED) <= " . (int)date('y') . " THEN
                  CASE
                    WHEN TIMESTAMPDIFF(YEAR, STR_TO_DATE(CONCAT('20', LEFT(id_number, 6)), '%Y%m%d'), CURDATE()) BETWEEN 18 AND 35 THEN '18-35'
                    WHEN TIMESTAMPDIFF(YEAR, STR_TO_DATE(CONCAT('20', LEFT(id_number, 6)), '%Y%m%d'), CURDATE()) BETWEEN 36 AND 55 THEN '36-55'
                    ELSE '56+'
                  END
                ELSE
                  CASE
                    WHEN TIMESTAMPDIFF(YEAR, STR_TO_DATE(CONCAT('19', LEFT(id_number, 6)), '%Y%m%d'), CURDATE()) BETWEEN 18 AND 35 THEN '18-35'
                    WHEN TIMESTAMPDIFF(YEAR, STR_TO_DATE(CONCAT('19', LEFT(id_number, 6)), '%Y%m%d'), CURDATE()) BETWEEN 36 AND 55 THEN '36-55'
                    ELSE '56+'
                  END
              END AS label,
              COUNT(*) total
            FROM users
            WHERE role = 'user'
            GROUP BY label
            ORDER BY label
        ")->fetchAll();
        json_response(['ok' => true, 'metrics' => $metrics, 'risk' => $risk, 'banks' => $banks, 'horizons' => $horizons, 'plan_types' => $planTypes, 'age_groups' => $ageGroups]);
    }

    if ($action === 'admin-activity') {
        Auth::requireAdmin();
        $hasActivityTable = (bool)$pdo->query("SHOW TABLES LIKE 'activity_logs'")->fetchColumn();
        $rows = $hasActivityTable ? $pdo->query('
            SELECT a.*, u.full_name, u.surname
            FROM activity_logs a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.created_at DESC
            LIMIT 25
        ')->fetchAll() : [];
        json_response(['ok' => true, 'activities' => $rows]);
    }

    if ($action === 'admin-export') {
        Auth::requireAdmin();
        $type = $_GET['type'] ?? 'users';
        if ($type === 'banks') {
            $rows = $pdo->query('SELECT name, contact_info, website, plan_type, expected_return, risk, liquidity, horizon, allows_monthly FROM banks ORDER BY name')->fetchAll();
        } elseif ($type === 'plans') {
            $rows = $pdo->query('
                SELECT p.user_plan_name, p.investment_amount, p.monthly_amount, p.investment_goal, p.risk, p.liquidity, p.horizon, p.expected_return, b.name AS bank_name
                FROM investment_plans p
                LEFT JOIN banks b ON b.id = p.bank_id
                ORDER BY p.created_at DESC
            ')->fetchAll();
        } elseif ($type === 'activity') {
            $hasActivityTable = (bool)$pdo->query("SHOW TABLES LIKE 'activity_logs'")->fetchColumn();
            $rows = $hasActivityTable ? $pdo->query('
                SELECT a.action, a.entity_type, a.entity_id, a.description, a.created_at, CONCAT(COALESCE(u.full_name, ""), " ", COALESCE(u.surname, "")) AS actor
                FROM activity_logs a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC
            ')->fetchAll() : [];
        } else {
            $rows = $pdo->query('SELECT full_name, surname, email, id_number, role, status, contact_info, created_at FROM users ORDER BY created_at DESC')->fetchAll();
        }
        json_response(['ok' => true, 'type' => $type, 'rows' => $rows]);
    }

    json_response(['ok' => false, 'message' => 'Unknown endpoint.'], 404);
} catch (PDOException $exception) {
    if ((int)$exception->getCode() === 23000) {
        json_response(['ok' => false, 'message' => 'That ID number or email address is already registered.'], 409);
    }
    json_response(['ok' => false, 'message' => 'Database error: ' . $exception->getMessage()], 500);
}
