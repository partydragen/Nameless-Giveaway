<?php

/**
 * Central service for giveaways owned by this NamelessMC installation.
 *
 * Keep database rules, eligibility checks, serialization and entry insertion
 * here. Controllers and API endpoints should not duplicate these rules.
 */
class GiveawayService {

    public static function find(int $giveaway_id): ?Giveaway {
        $giveaway = new Giveaway((string)$giveaway_id);

        return $giveaway->exists() ? $giveaway : null;
    }

    /**
     * List local giveaways in the common API/catalogue shape.
     *
     * Supported filters:
     * - giveaway: numeric giveaway ID
     * - active: true/false
     * - limit: 1-1000, or null for no explicit limit
     */
    public static function getGiveaways(?User $user = null, array $filters = [], ?string $ip = null): array {
        $where = ['id <> 0'];
        $params = [];

        if (isset($filters['giveaway']) && is_numeric($filters['giveaway'])) {
            $where[] = 'id = ?';
            $params[] = (int)$filters['giveaway'];
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $where[] = 'ends ' . ($active ? '>' : '<=') . ' ?';
                $params[] = time();
            }
        }

        $limit = '';
        if (array_key_exists('limit', $filters) && $filters['limit'] !== null) {
            $safe_limit = max(1, min(1000, (int)$filters['limit']));
            $limit = ' LIMIT ' . $safe_limit;
        }

        $query = DB::getInstance()->query(
            'SELECT * FROM nl2_giveaway WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC' . $limit,
            $params
        );

        $giveaways = [];
        foreach ($query->results() as $row) {
            $giveaways[] = self::serialize(new Giveaway(null, null, $row), $user, $ip);
        }

        return $giveaways;
    }

    /**
     * Create one stable representation used by the API and frontend catalogue.
     */
    public static function serialize(Giveaway $giveaway, ?User $user = null, ?string $ip = null): array {
        $status = [
            'can_enter' => false,
            'time_remaining' => null,
        ];

        // A supplied User may come from an API route transformer and therefore
        // is not backed by the current PHP session. Treat any resolved User as
        // valid here; web controllers remain responsible for login checks.
        if ($user !== null) {
            $status = self::getEntryStatus($giveaway, $user, $ip);
        }

        $required_groups = array_map(
            'intval',
            json_decode($giveaway->data()->required_groups, true) ?? []
        );
        $required_integrations = array_map(
            'intval',
            json_decode($giveaway->data()->required_integrations, true) ?? []
        );

        return [
            'reference' => 'local:' . (int)$giveaway->data()->id,
            'source' => 'local',
            'source_id' => null,
            'source_name' => Settings::get('sitename', 'Local') ?: 'Local',
            'id' => (int)$giveaway->data()->id,
            'active' => $giveaway->isActive(),
            'content' => $giveaway->data()->content,
            'prize' => $giveaway->data()->prize,
            'created' => (int)$giveaway->data()->created,
            'ends' => (int)$giveaway->data()->ends,
            'entry_interval' => (int)$giveaway->data()->entry_interval,
            'entry_period' => $giveaway->data()->entry_period,
            'required_integrations' => $required_integrations,
            'required_groups' => $required_groups,
            'entries' => self::getEntryCount($giveaway),
            'your_entries' => $user !== null
                ? self::getUserEntryCount($giveaway, $user)
                : 0,
            'can_enter' => $status['can_enter'],
            // Always seconds internally and in the API.
            'time_remaining' => $status['time_remaining'],
            'time_remaining_unit' => 'seconds',
            'winners' => (int)$giveaway->data()->winners,
            'winners_list' => self::getWinners($giveaway),
            'link' => rtrim(URL::getSelfURL(), '/') . '/' . ltrim(URL::build('/giveaway/view/' . $giveaway->data()->id), '/'),
            'entry_mode' => 'local',
        ];
    }

    /**
     * Return whether the user can enter and the remaining cooldown in seconds.
     *
     * The optional IP makes listing checks match actual submission checks.
     */
    public static function getEntryStatus(Giveaway $giveaway, User $user, ?string $ip = null): array {
        if (!$giveaway->isActive()) {
            return [
                'can_enter' => false,
                'time_remaining' => null,
            ];
        }

        $last_entry = self::findLastEntry($giveaway, $user, $ip);
        if ($last_entry === null) {
            return [
                'can_enter' => true,
                'time_remaining' => null,
            ];
        }

        if (!self::isRepeatable($giveaway)) {
            return [
                'can_enter' => false,
                'time_remaining' => null,
            ];
        }

        $next_entry_at = self::getNextEntryTimestamp($giveaway, $last_entry);
        $remaining = max(0, $next_entry_at - time());

        return [
            'can_enter' => $remaining === 0,
            'time_remaining' => $remaining > 0 ? $remaining : null,
        ];
    }

    /**
     * Enter a local giveaway. This is the only method that inserts local entries.
     */
    public static function enter(int $giveaway_id, User $user, string $ip): GiveawayEntryResult {
        $giveaway = self::find($giveaway_id);
        if ($giveaway === null) {
            return GiveawayEntryResult::failure('giveaway_not_found');
        }

        if (!$giveaway->isActive()) {
            return GiveawayEntryResult::failure('giveaway_already_ended');
        }

        foreach ($giveaway->getRequiredIntegrations() as $integration) {
            $integration_user = $user->getIntegration($integration->getName());
            if ($integration_user === null || $integration_user->data()->username === null || $integration_user->data()->identifier === null) {
                return GiveawayEntryResult::failure('giveaway_requires_integration', [
                    'integration' => $integration->getName(),
                ]);
            }
        }

        $required_groups = json_decode($giveaway->data()->required_groups, true) ?? [];
        if ($required_groups) {
            $user_groups = $user->getAllGroupIds();

            foreach ($required_groups as $group_id) {
                if (!array_key_exists($group_id, $user_groups)) {
                    $group = DB::getInstance()->query(
                        'SELECT name FROM nl2_groups WHERE id = ?',
                        [$group_id]
                    )->first();

                    return GiveawayEntryResult::failure('giveaway_requires_group', [
                        'group' => (int)$group_id,
                        'group_name' => $group->name ?? 'Unknown',
                    ]);
                }
            }
        }

        $event = new UserPreEntryGiveawayEvent($giveaway, $user);
        EventHandler::executeEvent($event);

        if ($event->isCancelled()) {
            return GiveawayEntryResult::failure('event_cancelled', [
                'message' => $event->getCancelledReason(),
            ]);
        }

        $status = self::getEntryStatus($giveaway, $user, $ip);
        if (!$status['can_enter']) {
            return GiveawayEntryResult::failure(
                'already_entered_giveaway',
                [],
                $status['time_remaining']
            );
        }

        $inserted = DB::getInstance()->insert('giveaway_entries', [
            'giveaway_id' => (int)$giveaway->data()->id,
            'user_id' => (int)$user->data()->id,
            'entered' => time(),
            'ip' => $ip,
        ]);

        if ($inserted === false) {
            return GiveawayEntryResult::failure('entry_failed');
        }

        EventHandler::executeEvent(new UserEntryGiveawayEvent($giveaway, $user));
        EventHandler::executeEvent(new GiveawayUpdatedEvent($giveaway));

        $cooldown = null;
        if (self::isRepeatable($giveaway)) {
            $cooldown = max(0, self::getNextEntryTimestamp($giveaway, time()) - time());
        }

        return GiveawayEntryResult::success($cooldown, [
            'giveaway' => (int)$giveaway->data()->id,
        ]);
    }

    public static function getWinners(Giveaway $giveaway): array {
        $winners = [];

        foreach ($giveaway->getWinners() as $winner) {
            $user_id = (int)($winner['user_id'] ?? $winner['id'] ?? 0);
            $avatar = (string)($winner['avatar'] ?? '');

            if ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
                $avatar = rtrim(URL::getSelfURL(), '/') . '/' . ltrim($avatar, '/');
            }

            $winners[] = [
                'id' => $user_id,
                'user_id' => $user_id,
                'username' => $winner['username'] ?? '',
                'profile' => $winner['profile'] ?? '',
                'style' => $winner['style'] ?? '',
                'avatar' => $avatar,
            ];
        }

        return $winners;
    }

    public static function getEntryCount(Giveaway $giveaway): int {
        $result = DB::getInstance()->query(
            'SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ?',
            [$giveaway->data()->id]
        )->first();

        return (int)($result->c ?? 0);
    }

    public static function getUserEntryCount(Giveaway $giveaway, User $user): int {
        $result = DB::getInstance()->query(
            'SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ? AND user_id = ?',
            [$giveaway->data()->id, $user->data()->id]
        )->first();

        return (int)($result->c ?? 0);
    }

    private static function findLastEntry(Giveaway $giveaway, User $user, ?string $ip): ?int {
        if ($ip !== null && $ip !== '') {
            $query = DB::getInstance()->query(
                'SELECT entered
                 FROM nl2_giveaway_entries
                 WHERE giveaway_id = ?
                   AND (user_id = ? OR ip = ?)
                 ORDER BY entered DESC
                 LIMIT 1',
                [$giveaway->data()->id, $user->data()->id, $ip]
            );
        } else {
            $query = DB::getInstance()->query(
                'SELECT entered
                 FROM nl2_giveaway_entries
                 WHERE giveaway_id = ? AND user_id = ?
                 ORDER BY entered DESC
                 LIMIT 1',
                [$giveaway->data()->id, $user->data()->id]
            );
        }

        return $query->count() ? (int)$query->first()->entered : null;
    }

    private static function isRepeatable(Giveaway $giveaway): bool {
        return $giveaway->data()->entry_period !== 'no_period'
            && (int)$giveaway->data()->entry_interval > 0;
    }

    private static function getNextEntryTimestamp(Giveaway $giveaway, int $from): int {
        $timestamp = strtotime(
            '+' . (int)$giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period,
            $from
        );

        // Invalid stored periods should fail closed instead of allowing spam.
        return $timestamp !== false ? $timestamp : PHP_INT_MAX;
    }
}