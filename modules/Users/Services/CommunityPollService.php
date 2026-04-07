<?php

declare(strict_types=1);

namespace Modules\Users\Services;

use Core\Database;
use Core\ModuleSettings;

class CommunityPollService
{
    private Database $db;
    private ModuleSettings $settings;

    public function __construct(Database $db, ModuleSettings $settings)
    {
        $this->db = $db;
        $this->settings = $settings;
    }

    public function surveySettings(): array
    {
        $settings = $this->settings->all('users');

        return [
            'key' => $this->normalizeSurveyKey((string)($settings['community_poll_key'] ?? 'community_features_v1')),
            'status' => $this->normalizeStatus((string)($settings['community_poll_status'] ?? 'active')),
            'allow_comment' => array_key_exists('community_poll_allow_comment', $settings) ? !empty($settings['community_poll_allow_comment']) : true,
            'title' => $this->cleanText($settings['community_poll_title'] ?? null, 180),
            'description' => $this->cleanText($settings['community_poll_description'] ?? null, 500),
            'highlight_title' => $this->cleanText($settings['community_poll_highlight_title'] ?? null, 120),
            'highlight_description' => $this->cleanText($settings['community_poll_highlight_description'] ?? null, 600),
        ];
    }

    public function definition(): array
    {
        return [
            'primary' => [
                'label_key' => 'users.community_poll.question.primary',
                'other_placeholder_key' => 'users.community_poll.other_placeholder.primary',
                'options' => [
                    ['value' => 'personal_messages_all', 'label_key' => 'users.community_poll.option.primary.personal_messages_all'],
                    ['value' => 'personal_messages_verified', 'label_key' => 'users.community_poll.option.primary.personal_messages_verified'],
                    ['value' => 'private_forum_verified', 'label_key' => 'users.community_poll.option.primary.private_forum_verified'],
                    ['value' => 'private_critique_masters', 'label_key' => 'users.community_poll.option.primary.private_critique_masters'],
                    ['value' => 'notifications', 'label_key' => 'users.community_poll.option.primary.notifications'],
                    ['value' => 'client_requests', 'label_key' => 'users.community_poll.option.primary.client_requests'],
                    ['value' => 'master_announcements', 'label_key' => 'users.community_poll.option.primary.master_announcements'],
                    ['value' => 'collaboration_board', 'label_key' => 'users.community_poll.option.primary.collaboration_board'],
                    ['value' => 'nothing_needed', 'label_key' => 'users.community_poll.option.primary.nothing_needed'],
                    ['value' => 'other', 'label_key' => 'users.community_poll.option.other'],
                ],
            ],
            'access' => [
                'label_key' => 'users.community_poll.question.access',
                'other_placeholder_key' => 'users.community_poll.other_placeholder.access',
                'options' => [
                    ['value' => 'all_registered', 'label_key' => 'users.community_poll.option.access.all_registered'],
                    ['value' => 'masters_only', 'label_key' => 'users.community_poll.option.access.masters_only'],
                    ['value' => 'verified_only', 'label_key' => 'users.community_poll.option.access.verified_only'],
                    ['value' => 'mixed_by_level', 'label_key' => 'users.community_poll.option.access.mixed_by_level'],
                    ['value' => 'other', 'label_key' => 'users.community_poll.option.other'],
                ],
            ],
            'goal' => [
                'label_key' => 'users.community_poll.question.goal',
                'other_placeholder_key' => 'users.community_poll.other_placeholder.goal',
                'options' => [
                    ['value' => 'more_clients', 'label_key' => 'users.community_poll.option.goal.more_clients'],
                    ['value' => 'professional_community', 'label_key' => 'users.community_poll.option.goal.professional_community'],
                    ['value' => 'private_communication', 'label_key' => 'users.community_poll.option.goal.private_communication'],
                    ['value' => 'reputation_status', 'label_key' => 'users.community_poll.option.goal.reputation_status'],
                    ['value' => 'useful_tools', 'label_key' => 'users.community_poll.option.goal.useful_tools'],
                    ['value' => 'other', 'label_key' => 'users.community_poll.option.other'],
                ],
            ],
        ];
    }

    public function surveyForUser(int $userId): array
    {
        $settings = $this->surveySettings();
        $response = $this->responseForUser($userId, $settings['key']);

        return [
            'settings' => $settings,
            'questions' => $this->definition(),
            'response' => $response,
            'has_response' => $response !== null,
            'is_visible' => $settings['status'] === 'active' && $this->responsesTableExists(),
            'table_ready' => $this->responsesTableExists(),
        ];
    }

    public function submitResponse(int $userId, array $body): array
    {
        if (!$this->responsesTableExists()) {
            return ['ok' => false, 'code' => 'not_ready'];
        }

        $settings = $this->surveySettings();
        if ($settings['status'] !== 'active') {
            return ['ok' => false, 'code' => 'inactive'];
        }

        if ($this->responseForUser($userId, $settings['key'])) {
            return ['ok' => false, 'code' => 'exists'];
        }

        $definition = $this->definition();

        $answerPrimary = $this->validateAnswer('primary', (string)($body['answer_primary'] ?? ''), $definition);
        $answerAccess = $this->validateAnswer('access', (string)($body['answer_access'] ?? ''), $definition);
        $answerGoal = $this->validateAnswer('goal', (string)($body['answer_goal'] ?? ''), $definition);

        if ($answerPrimary === null || $answerAccess === null || $answerGoal === null) {
            return ['ok' => false, 'code' => 'invalid_answer'];
        }

        $answerPrimaryOther = $this->normalizeOtherText($answerPrimary, $body['answer_primary_other'] ?? null);
        $answerAccessOther = $this->normalizeOtherText($answerAccess, $body['answer_access_other'] ?? null);
        $answerGoalOther = $this->normalizeOtherText($answerGoal, $body['answer_goal_other'] ?? null);

        if ($answerPrimary === 'other' && $answerPrimaryOther === null) {
            return ['ok' => false, 'code' => 'other_required'];
        }
        if ($answerAccess === 'other' && $answerAccessOther === null) {
            return ['ok' => false, 'code' => 'other_required'];
        }
        if ($answerGoal === 'other' && $answerGoalOther === null) {
            return ['ok' => false, 'code' => 'other_required'];
        }

        $comment = $settings['allow_comment'] ? $this->normalizeComment($body['comment'] ?? null) : null;

        $this->db->execute(
            "INSERT INTO user_community_poll_responses (
                survey_key,
                user_id,
                answer_primary,
                answer_primary_other,
                answer_access,
                answer_access_other,
                answer_goal,
                answer_goal_other,
                comment,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $settings['key'],
                $userId,
                $answerPrimary,
                $answerPrimaryOther,
                $answerAccess,
                $answerAccessOther,
                $answerGoal,
                $answerGoalOther,
                $comment,
            ]
        );

        return ['ok' => true, 'code' => 'submitted'];
    }

    public function adminReport(string $audience = 'all'): array
    {
        $settings = $this->surveySettings();
        $responses = $this->responsesTableExists() ? $this->fetchResponses($settings['key']) : [];
        $audience = $this->normalizeAudience($audience);
        $responses = array_values(array_filter($responses, static function (array $row) use ($audience): bool {
            return $audience === 'all' ? true : ($row['audience'] ?? 'registered') === $audience;
        }));

        $definition = $this->definition();
        $summary = [];
        $other = [
            'primary' => [],
            'access' => [],
            'goal' => [],
        ];
        $comments = [];

        foreach ($definition as $questionKey => $question) {
            $counts = [];
            foreach ($question['options'] as $option) {
                $counts[$option['value']] = 0;
            }
            foreach ($responses as $response) {
                $value = (string)($response['answer_' . $questionKey] ?? '');
                if ($value !== '' && array_key_exists($value, $counts)) {
                    $counts[$value]++;
                }
                $otherText = trim((string)($response['answer_' . $questionKey . '_other'] ?? ''));
                if ($otherText !== '') {
                    $other[$questionKey][] = $this->mapFreeTextRow($response, $otherText);
                }
            }

            $summary[$questionKey] = [];
            foreach ($question['options'] as $option) {
                $summary[$questionKey][] = [
                    'value' => $option['value'],
                    'label_key' => $option['label_key'],
                    'count' => $counts[$option['value']] ?? 0,
                ];
            }
        }

        foreach ($responses as $response) {
            $comment = trim((string)($response['comment'] ?? ''));
            if ($comment !== '') {
                $comments[] = $this->mapFreeTextRow($response, $comment);
            }
        }

        return [
            'settings' => $settings,
            'questions' => $definition,
            'responses' => $responses,
            'summary' => $summary,
            'other' => $other,
            'comments' => $comments,
            'audience' => $audience,
            'audience_options' => [
                'all' => 'users.community_poll.audience.all',
                'registered' => 'users.community_poll.audience.registered',
                'master' => 'users.community_poll.audience.master',
                'verified_master' => 'users.community_poll.audience.verified_master',
            ],
            'table_ready' => $this->responsesTableExists(),
        ];
    }

    public function saveSurveySettings(array $body): void
    {
        $this->settings->set('users', 'community_poll_key', $this->normalizeSurveyKey((string)($body['users_community_poll_key'] ?? 'community_features_v1')));
        $this->settings->set('users', 'community_poll_status', $this->normalizeStatus((string)($body['users_community_poll_status'] ?? 'active')));
        $this->settings->set('users', 'community_poll_allow_comment', !empty($body['users_community_poll_allow_comment']) ? 1 : 0);
        $this->settings->set('users', 'community_poll_title', $this->cleanText($body['users_community_poll_title'] ?? null, 180) ?? '');
        $this->settings->set('users', 'community_poll_description', $this->cleanText($body['users_community_poll_description'] ?? null, 500) ?? '');
        $this->settings->set('users', 'community_poll_highlight_title', $this->cleanText($body['users_community_poll_highlight_title'] ?? null, 120) ?? '');
        $this->settings->set('users', 'community_poll_highlight_description', $this->cleanText($body['users_community_poll_highlight_description'] ?? null, 600) ?? '');
    }

    private function fetchResponses(string $surveyKey): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                r.*,
                u.username,
                u.name,
                u.email,
                u.role,
                u.status,
                up.display_name,
                COALESCE(up.is_master, 0) AS is_master,
                COALESCE(up.is_verified, 0) AS is_verified
             FROM user_community_poll_responses r
             INNER JOIN users u ON u.id = r.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE r.survey_key = ?
             ORDER BY r.created_at DESC, r.id DESC",
            [$surveyKey]
        );

        foreach ($rows as &$row) {
            $row['audience'] = $this->detectAudience($row);
            $row['display_identity'] = $this->displayIdentity($row);
        }
        unset($row);

        return $rows;
    }

    private function responseForUser(int $userId, string $surveyKey): ?array
    {
        if (!$this->responsesTableExists()) {
            return null;
        }

        return $this->db->fetch(
            "SELECT *
             FROM user_community_poll_responses
             WHERE survey_key = ? AND user_id = ?
             LIMIT 1",
            [$surveyKey, $userId]
        );
    }

    private function responsesTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $quoted = $this->db->pdo()->quote('user_community_poll_responses');
        $row = $this->db->fetch("SHOW TABLES LIKE {$quoted}");
        $exists = $row !== null;
        return $exists;
    }

    private function validateAnswer(string $questionKey, string $value, array $definition): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $allowed = [];
        foreach ($definition[$questionKey]['options'] as $option) {
            $allowed[] = $option['value'];
        }

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function normalizeOtherText(string $answer, $value): ?string
    {
        if ($answer !== 'other') {
            return null;
        }

        return $this->cleanText($value, 500);
    }

    private function normalizeComment($value): ?string
    {
        return $this->cleanText($value, 1500);
    }

    private function cleanText($value, int $max): ?string
    {
        $plain = trim(strip_tags((string)$value));
        if ($plain === '') {
            return null;
        }
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return mb_strlen($plain) > $max ? mb_substr($plain, 0, $max) : $plain;
    }

    private function normalizeStatus(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['active', 'closed', 'hidden'], true) ? $value : 'active';
    }

    private function normalizeSurveyKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value) ?? '';
        $value = trim($value, '_-');
        return $value !== '' ? substr($value, 0, 64) : 'community_features_v1';
    }

    private function normalizeAudience(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['all', 'registered', 'master', 'verified_master'], true) ? $value : 'all';
    }

    private function detectAudience(array $row): string
    {
        if (!empty($row['is_verified'])) {
            return 'verified_master';
        }
        if (!empty($row['is_master'])) {
            return 'master';
        }
        return 'registered';
    }

    private function displayIdentity(array $row): string
    {
        $displayName = trim((string)($row['display_name'] ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return trim((string)($row['username'] ?? ''));
    }

    private function mapFreeTextRow(array $response, string $text): array
    {
        return [
            'user_id' => (int)($response['user_id'] ?? 0),
            'username' => (string)($response['username'] ?? ''),
            'display_identity' => (string)($response['display_identity'] ?? ''),
            'audience' => (string)($response['audience'] ?? 'registered'),
            'role' => (string)($response['role'] ?? 'user'),
            'text' => $text,
            'created_at' => (string)($response['created_at'] ?? ''),
        ];
    }
}
