<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

final class Auth
{
    public static function currentUser(): ?array
    {
        $tokenPayload = verify_auth_token(read_bearer_token());
        if ($tokenPayload) {
            $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$tokenPayload['uid']]);
            $user = $stmt->fetch();
            if ($user && $user['status'] === 'active') {
                return $user;
            }
        }

        start_app_session();
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

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

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin') {
            json_response(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        return $user;
    }
}
