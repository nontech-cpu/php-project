<?php
/**
 * Logs a rate change into rate_history so admins can see who changed
 * what, and when.
 */
function log_rate_change(PDO $db, string $table, int $recordId, string $field, $oldValue, $newValue, string $changedBy): void
{
    if ((string) $oldValue === (string) $newValue) {
        return; // nothing actually changed
    }

    $stmt = $db->prepare(
        "INSERT INTO rate_history (table_name, record_id, field_name, old_value, new_value, changed_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$table, $recordId, $field, $oldValue, $newValue, $changedBy]);
}
