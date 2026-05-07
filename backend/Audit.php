<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Audit
{
    public static function log(?int $userId, string $action, string $entityType, ?int $entityId = null, ?string $description = null): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $action, $entityType, $entityId, $description]);
        } catch (PDOException) {
            // Allow the application to continue even if the audit table is not available yet.
        }
    }
}
