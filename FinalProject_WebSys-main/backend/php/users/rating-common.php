<?php

function ensureUserRatingsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_ratings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            purchase_id INT UNSIGNED NOT NULL,
            reviewer_id INT UNSIGNED NOT NULL,
            reviewee_id INT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            review VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rating_purchase_reviewer (purchase_id, reviewer_id),
            INDEX idx_rating_reviewee (reviewee_id, created_at),
            CONSTRAINT fk_rating_purchase FOREIGN KEY (purchase_id)
                REFERENCES purchases(id) ON DELETE CASCADE,
            CONSTRAINT fk_rating_reviewer FOREIGN KEY (reviewer_id)
                REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_rating_reviewee FOREIGN KEY (reviewee_id)
                REFERENCES users(id) ON DELETE CASCADE,
            CHECK (rating BETWEEN 1 AND 5)
        ) ENGINE=InnoDB"
    );

    $columns = $pdo->query('SHOW COLUMNS FROM user_ratings')->fetchAll(PDO::FETCH_COLUMN);
    $hasLegacyRater = in_array('rater_id', $columns, true);
    $hasLegacyRatedUser = in_array('rated_user_id', $columns, true);

    // Older installs used rater_id/rated_user_id plus a required role column.
    // Migrate in place so existing ratings are preserved and new inserts work.
    if ($hasLegacyRater || $hasLegacyRatedUser || in_array('role', $columns, true)) {
        $foreignKeys = $pdo->query(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user_ratings'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($foreignKeys as $foreignKey) {
            $quotedName = '`' . str_replace('`', '``', $foreignKey) . '`';
            $pdo->exec("ALTER TABLE user_ratings DROP FOREIGN KEY {$quotedName}");
        }

        $changes = [];
        if ($hasLegacyRater) {
            $changes[] = 'CHANGE COLUMN rater_id reviewer_id INT UNSIGNED NOT NULL';
        }
        if ($hasLegacyRatedUser) {
            $changes[] = 'CHANGE COLUMN rated_user_id reviewee_id INT UNSIGNED NOT NULL';
        }
        if (in_array('role', $columns, true)) {
            $changes[] = 'DROP COLUMN role';
        }
        if ($changes) {
            $pdo->exec('ALTER TABLE user_ratings ' . implode(', ', $changes));
        }

        $pdo->exec(
            'ALTER TABLE user_ratings
               ADD CONSTRAINT fk_rating_purchase_v2 FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
             ADD CONSTRAINT fk_rating_reviewer_v2 FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
             ADD CONSTRAINT fk_rating_reviewee_v2 FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE'
        );
    }
}