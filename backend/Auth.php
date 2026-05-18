<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

final class Auth
{
    public static function currentUser(): ?array
    {
        $pdo = Database::connection();
        $tokenPayload = verify_auth_token(read_bearer_token());
        if ($tokenPayload) {
            $user = fetch_user_by_id($pdo, (int)$tokenPayload['uid']);
            if ($user && $user['status'] === 'active') {
                return $user;
            }
        }

        start_app_session();
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $user = fetch_user_by_id($pdo, (int)$_SESSION['user_id']);
        if (!$user || $user['status'] !== 'active') {
            session_destroy();
            return null;
        }

        return $user;
    }

    public static function requireUser(): array
    {
        $user = self::currentUser();
        if (!$user) {
            json_response(['ok' => false, 'message' => 'Authentication required.'], 401);
        }

        return $user;
    }

    public static function requireClient(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'client' || empty($user['client_id'])) {
            json_response(['ok' => false, 'message' => 'Client access required.'], 403);
        }

        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin' || empty($user['admin_id'])) {
            json_response(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        return $user;
    }
}
