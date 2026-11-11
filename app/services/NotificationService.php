<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Notification hub for schema uac — implements _shared/NOTIFICATION-IMPLEMENTATION-PLAN.md.
 *
 *  READ  : recent / unreadCount / markRead / markAllRead  (one audience query, §3.3)
 *  WRITE : send() + the toAll/toSystem/toUser/toUserLevel/toOffice/toUserGroup helpers (§5).
 *          Business code calls the helpers; they never throw (return the id, or null).
 *  PUSH  : optional EMQX publish after the INSERT (§6); polling is the delivery guarantee.
 *
 * Audience types (the only vocabulary): all | system | user | userlevel | office | usergroup
 *   all       target 'all'
 *   system    uac.systems.system_id      — users with a uac.user_level_assignments row for that system
 *   user      uac.users.user_id
 *   userlevel uac.user_levels.user_level_id — users assigned that level (user_level_assignments)
 *   office    uac.offices.office_id      — users in uac.user_offices (active)
 *   usergroup supervisor's user_id       — users whose uac.users.reports_to_user_id = target
 */
class NotificationService
{
    public const TYPES = ['all', 'system', 'user', 'userlevel', 'office', 'usergroup'];

    /** Fallback when NOTIFY.ALLOWED_SYSTEMS is not configured. */
    private const DEFAULT_SYSTEMS = [
        'FIMS', 'UAC', 'ACCOUNTING-GAD', 'ACCOUNTING-BAPD', 'ACCOUNTING-DCD', 'TREASURY', 'FMD',
        'ACCOUNTING-PCDS', 'REGIONAL', 'DISTRICT',
    ];

    private const APP_CODE = 'FIMS';

    // ── Read side ────────────────────────────────────────────────

    /** SQL predicate: rows of uac.notifications (alias n) that $uid is in the audience of. */
    private static function audience(int $uid): string
    {
        return "(
              (n.type = 'all'       AND n.target = 'all')
           OR (n.type = 'user'      AND n.target = '{$uid}')
           OR (n.type = 'userlevel' AND n.target IN (SELECT user_level_id::text FROM uac.user_level_assignments WHERE user_id = {$uid}))
           OR (n.type = 'system'    AND n.target IN (SELECT system_id::text FROM uac.user_level_assignments WHERE user_id = {$uid} AND system_id IS NOT NULL))
           OR (n.type = 'office'    AND n.target IN (SELECT office_id::text FROM uac.user_offices WHERE user_id = {$uid} AND COALESCE(is_active, TRUE)))
           OR (n.type = 'usergroup' AND n.target = (SELECT reports_to_user_id::text FROM uac.users WHERE user_id = {$uid}))
        )";
    }

    /** The one function every read path uses. Unread first, newest first. */
    public function getUserNotifications(int $uid, int $limit = 10): array
    {
        $limit = max(1, min(200, $limit));
        $rows  = App\ExecuteRows(
            "SELECT n.id, n.type, n.target, n.subject, n.body, n.link, n.from_system, n.created_at,
                    (r.user_id IS NOT NULL) AS read_by_user
             FROM uac.notifications n
             LEFT JOIN uac.notification_reads r ON r.notification_id = n.id AND r.user_id = {$uid}
             WHERE " . self::audience($uid) . "
             ORDER BY (r.user_id IS NOT NULL) ASC, n.created_at DESC
             LIMIT {$limit}",
            'DB'
        ) ?: [];

        foreach ($rows as &$row) {
            $row['read_by_user'] = filter_var($row['read_by_user'], FILTER_VALIDATE_BOOLEAN);
            $row['time_ago']     = self::timeAgo((string) $row['created_at']);
        }

        return $rows;
    }

    public function recent(int $uid, int $limit = 10): array
    {
        return [
            'success'      => true,
            'data'         => $this->getUserNotifications($uid, $limit),
            'unread_count' => $this->unreadCount($uid),
        ];
    }

    public function unreadCount(int $uid): int
    {
        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM uac.notifications n
             WHERE " . self::audience($uid) . "
               AND NOT EXISTS (SELECT 1 FROM uac.notification_reads r WHERE r.notification_id = n.id AND r.user_id = {$uid})",
            'DB'
        );
    }

    /** Idempotent; only rows inside the caller's audience can be marked. */
    public function markRead(string $id, int $uid): bool
    {
        App\Execute(
            "INSERT INTO uac.notification_reads (notification_id, user_id)
             SELECT n.id, {$uid} FROM uac.notifications n
             WHERE n.id = " . App\QuotedValue($id, DataType::STRING, 'DB') . ' AND ' . self::audience($uid) . "
             ON CONFLICT DO NOTHING",
            'DB'
        );

        return true;
    }

    /** @return int number of notifications newly marked read */
    public function markAllRead(int $uid): int
    {
        $n = App\Execute(
            "INSERT INTO uac.notification_reads (notification_id, user_id)
             SELECT n.id, {$uid} FROM uac.notifications n
             WHERE " . self::audience($uid) . "
             ON CONFLICT DO NOTHING",
            'DB'
        );

        return (int) $n;
    }

    public function isSuperAdmin(int $uid): bool
    {
        return $uid > 0 && trim((string) App\ExecuteScalar(
            "SELECT user_level_id FROM uac.users WHERE user_id = {$uid}",
            'DB'
        )) === '-1';
    }

    // ── Write side: validated send, used by POST /notifications/send ───

    /** @return array{success:bool,message?:string,id?:?string} */
    public function sendValidated(array $in, ?int $senderId = null): array
    {
        $type    = (string) ($in['type'] ?? '');
        $target  = trim((string) ($in['target'] ?? ''));
        $subject = trim((string) ($in['subject'] ?? ''));
        $system  = (string) ($in['from_system'] ?? self::APP_CODE);

        if (!in_array($type, self::TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid type. Must be one of: ' . implode(', ', self::TYPES)];
        }
        if ($type === 'all') {
            $target = 'all';
        } elseif ($target === '') {
            return ['success' => false, 'message' => 'Target is required.'];
        }
        if ($subject === '') {
            return ['success' => false, 'message' => 'Subject is required.'];
        }
        if (!in_array($system, self::allowedSystems(), true)) {
            return ['success' => false, 'message' => 'Invalid system code.'];
        }

        $id = $this->insert($type, $target, $subject, $in['body'] ?? null, $in['link'] ?? null, [
            'from_system' => $system,
            'sender_id'   => $senderId,
            'event_key'   => $in['event_key'] ?? null,
        ]);

        return $id === null
            ? ['success' => false, 'message' => 'Not sent (duplicate event or storage error).']
            : ['success' => true, 'id' => $id];
    }

    // ── Sender library (§5) — static, never throws ───────────────

    public static function send(string $type, string $target, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        try {
            if (!in_array($type, self::TYPES, true) || trim($subject) === '' || ($type !== 'all' && trim($target) === '')) {
                return null;
            }

            return (new self())->insert($type, $type === 'all' ? 'all' : $target, $subject, $body, $link, $opts);
        } catch (\Throwable $e) {
            App\LogError('Notification send failed: ' . $e->getMessage());

            return null;
        }
    }

    public static function toAll(string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('all', 'all', $subject, $body, $link, $opts);
    }

    public static function toSystem(int|string $systemId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('system', (string) $systemId, $subject, $body, $link, $opts);
    }

    public static function toUser(int $userId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return $userId > 0 ? self::send('user', (string) $userId, $subject, $body, $link, $opts) : null;
    }

    public static function toUserLevel(int $levelId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('userlevel', (string) $levelId, $subject, $body, $link, $opts);
    }

    public static function toOffice(int $officeId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('office', (string) $officeId, $subject, $body, $link, $opts);
    }

    public static function toUserGroup(int $supervisorId, string $subject, ?string $body = null, ?string $link = null, array $opts = []): ?string
    {
        return self::send('usergroup', (string) $supervisorId, $subject, $body, $link, $opts);
    }

    // ── Internals ────────────────────────────────────────────────

    /** @return ?string new id, or null when de-duplicated by event_key */
    private function insert(string $type, string $target, string $subject, ?string $body, ?string $link, array $opts): ?string
    {
        $id     = 'ntf_' . bin2hex(random_bytes(12));
        $system = (string) ($opts['from_system'] ?? self::APP_CODE);
        $sender = isset($opts['sender_id']) && (int) $opts['sender_id'] > 0 ? (int) $opts['sender_id'] : null;
        $q      = fn ($v) => $v === null || $v === '' ? 'NULL' : App\QuotedValue((string) $v, DataType::STRING, 'DB');
        $link   = self::safeLink($link);

        $row = App\ExecuteRow(
            'INSERT INTO uac.notifications (id, type, target, subject, body, link, from_system, sender_id, event_key)
             VALUES (' . implode(', ', [
                $q($id), $q($type), $q($target), $q(mb_substr($subject, 0, 200)), $q($body), $q($link),
                $q($system), $sender ?? 'NULL', $q($opts['event_key'] ?? null),
            ]) . ')
             ON CONFLICT (event_key) WHERE event_key IS NOT NULL DO NOTHING
             RETURNING id',
            'DB'
        );
        if (!$row) {
            return null;
        }

        $this->publish([
            'id' => $id, 'type' => $type, 'target' => $target, 'subject' => $subject, 'body' => $body,
            'link' => $link, 'from_system' => $system, 'created_at' => date('c'),
        ]);

        return $id;
    }

    /** Only relative, same-origin links (plan §7/§10). */
    private static function safeLink(?string $link): ?string
    {
        $link = trim((string) $link);

        return ($link !== '' && $link[0] === '/' && !str_starts_with($link, '//')) ? mb_substr($link, 0, 255) : null;
    }

    /** One topic scheme for publisher and browser: app/notifications/{type}/{target}. */
    public static function topic(string $type, string $target): string
    {
        return "app/notifications/{$type}/{$target}";
    }

    /** Optional real-time push via EMQX REST. Off when not configured; failures never surface. */
    private function publish(array $payload): void
    {
        try {
            $url    = trim((string) App\Config('EMQX.API_URL'));
            $key    = (string) App\Config('EMQX.API_KEY');
            $secret = (string) App\Config('EMQX.API_SECRET');
            if ($url === '' || $key === '') {
                return;
            }

            (new \GuzzleHttp\Client(['base_uri' => $url, 'timeout' => 3.0]))->post('/api/v5/publish', [
                'auth' => [$key, $secret],
                'json' => [
                    'topic'   => self::topic($payload['type'], $payload['target']),
                    'qos'     => 1,
                    'payload' => json_encode($payload),
                    'retain'  => false,
                ],
            ]);
        } catch (\Throwable $e) {
            App\LogError('Notification publish failed: ' . $e->getMessage());
        }
    }

    private static function allowedSystems(): array
    {
        try {
            $cfg = trim((string) App\Config('NOTIFY.ALLOWED_SYSTEMS'));
        } catch (\Throwable $e) {
            $cfg = '';
        }

        return $cfg !== '' ? array_map('trim', explode(',', $cfg)) : self::DEFAULT_SYSTEMS;
    }

    private static function timeAgo(string $ts): string
    {
        $t = strtotime($ts);
        if (!$t) {
            return '';
        }
        $d = time() - $t;

        return match (true) {
            $d < 60      => 'Just now',
            $d < 3600    => floor($d / 60) . ' minutes ago',
            $d < 86400   => floor($d / 3600) . ' hours ago',
            default      => date('M d, Y', $t),
        };
    }
}
