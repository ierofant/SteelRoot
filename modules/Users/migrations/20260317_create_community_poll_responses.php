<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS user_community_poll_responses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                survey_key VARCHAR(64) NOT NULL,
                user_id INT NOT NULL,
                answer_primary VARCHAR(64) NOT NULL,
                answer_primary_other VARCHAR(500) NULL,
                answer_access VARCHAR(64) NOT NULL,
                answer_access_other VARCHAR(500) NULL,
                answer_goal VARCHAR(64) NOT NULL,
                answer_goal_other VARCHAR(500) NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_community_poll (survey_key, user_id),
                KEY idx_user_community_poll_survey (survey_key),
                KEY idx_user_community_poll_user (user_id),
                CONSTRAINT fk_user_community_poll_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_community_poll_responses");
    }
};
