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

    header('Access-Control-Allow-Headers: Content-Type');
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

function calculate_age_from_id(string $idNumber): ?int
{
    $digits = preg_replace('/\D/', '', $idNumber);
    if (strlen($digits) < 6) {
        return null;
    }

    $yy = (int)substr($digits, 0, 2);
    $mm = (int)substr($digits, 2, 2);
    $dd = (int)substr($digits, 4, 2);
    $currentYear = (int)date('Y');
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

function clean_user(array $user): array
{
    unset($user['password_hash']);
    $user['age'] = calculate_age_from_id((string)$user['id_number']);
    return $user;
}
