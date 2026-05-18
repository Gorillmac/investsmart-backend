<?php
declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => SESSION_SECURE_COOKIES,
            'httponly' => true,
            'samesite' => SESSION_SECURE_COOKIES ? 'None' : 'Lax',
        ]);
        session_start();
    }
}

function apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, ngrok-skip-browser-warning');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            json_response(['ok' => false, 'message' => "Missing field: {$field}"], 422);
        }
    }
}

function money_value(mixed $value): float
{
    if (!is_numeric($value)) {
        json_response(['ok' => false, 'message' => 'Numeric value expected.'], 422);
    }

    return round((float)$value, 2);
}

function calculate_age_from_id(?string $idNumber): ?int
{
    if ($idNumber === null || $idNumber === '') {
        return null;
    }

    $digits = preg_replace('/\D/', '', $idNumber);
    if (strlen($digits) < 6) {
        return null;
    }

    $yy = (int)substr($digits, 0, 2);
    $mm = (int)substr($digits, 2, 2);
    $dd = (int)substr($digits, 4, 2);
    $century = ($yy <= (int)date('y')) ? 2000 : 1900;
    $year = $century + $yy;

    if (!checkdate($mm, $dd, $year)) {
        return null;
    }

    $birth = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $mm, $dd));
    $today = new DateTimeImmutable('today');
    if ($birth > $today) {
        $birth = $birth->modify('-100 years');
    }

    return $birth->diff($today)->y;
}

function user_select_sql(): string
{
    return "
        SELECT
            u.id,
            u.email,
            u.password_hash,
            u.role,
            u.status,
            u.created_at,
            c.id AS client_id,
            a.id AS admin_id,
            COALESCE(c.full_name, a.full_name) AS full_name,
            COALESCE(c.surname, a.surname) AS surname,
            c.id_number,
            COALESCE(c.contact_info, a.contact_info) AS contact_info,
            a.employee_code
        FROM users u
        LEFT JOIN clients c ON c.user_id = u.id
        LEFT JOIN admins a ON a.user_id = u.id
    ";
}

function fetch_user_by_id(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(user_select_sql() . ' WHERE u.id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function fetch_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(user_select_sql() . ' WHERE u.email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function clean_user(array $user): array
{
    unset($user['password_hash']);
    $user['age'] = calculate_age_from_id($user['id_number'] ?? null);
    return $user;
}

function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/')) ?: '';
}

function issue_auth_token(array $user): string
{
    $payload = [
        'uid' => (int)$user['id'],
        'exp' => time() + (60 * 60 * 12),
    ];
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $payloadEncoded, APP_KEY);
    return $payloadEncoded . '.' . $signature;
}

function read_bearer_token(): ?string
{
    $directHeader = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($directHeader !== '') {
        return trim($directHeader);
    }

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches) !== 1) {
        return null;
    }

    return trim($matches[1]);
}

function verify_auth_token(?string $token): ?array
{
    if (!$token || !str_contains($token, '.')) {
        return null;
    }

    [$payloadEncoded, $signature] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payloadEncoded, APP_KEY);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode(base64url_decode($payloadEncoded), true);
    if (!is_array($payload) || empty($payload['uid']) || empty($payload['exp']) || (int)$payload['exp'] < time()) {
        return null;
    }

    return $payload;
}
