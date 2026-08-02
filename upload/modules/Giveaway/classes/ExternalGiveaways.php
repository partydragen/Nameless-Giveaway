<?php

class ExternalGiveaways {

    public static function getGiveaways(User $user): array {
        $giveaways = [];

        if (Util::isModuleEnabled('OAuth2')) {
            foreach (self::getApplications() as $application) {
                $identifier = null;

                if ($user->isLoggedIn()) {
                    $integration_user = $user->getIntegration($application->getName());
                    if ($integration_user !== null && $integration_user->data()->identifier !== null) {
                        $identifier = $integration_user->data()->identifier;
                    }
                }

                $url = self::getApiUrl($application) . '/giveaways';
                if ($identifier !== null) {
                    $url .= '&user=' . rawurlencode((string)$identifier);
                }

                $request = HttpClient::get($url, self::getHeaders($application));
                if ($request->hasError()) {
                    // One unavailable source should not hide all other sources.
                    continue;
                }

                $result = $request->json(true);
                if (!is_array($result) || !isset($result['giveaways']) || !is_array($result['giveaways'])) {
                    continue;
                }

                foreach ($result['giveaways'] as $item) {
                    if (!is_array($item) || !isset($item['id'])) {
                        continue;
                    }

                    $giveaways[] = self::normalizeOAuthGiveaway($application, $item);
                }
            }
        }

        if (Settings::get('mcc_giveaway', '1', 'Giveaway')) {
            $giveaways = array_merge($giveaways, self::getPartydragenGiveaways());
        }

        return $giveaways;
    }

    public static function enterGiveaway(
        int $application_id,
        int $giveaway_id,
        User $user
    ): GiveawayEntryResult {
        $application = self::findApplication($application_id);
        if ($application === null) {
            return GiveawayEntryResult::failure('giveaway_not_found');
        }

        $integration_user = $user->getIntegration($application->getName());
        if ($integration_user === null || $integration_user->data()->identifier === null
        ) {
            return GiveawayEntryResult::failure('giveaway_requires_integration', [
                'integration' => $application->getName(),
            ]);
        }

        $request = HttpClient::post(
            self::getApiUrl($application) . '/users/' . rawurlencode((string)$integration_user->data()->identifier) . '/giveaway/entry',
            json_encode(['giveaway' => $giveaway_id]),
            self::getHeaders($application)
        );

        $json = $request->json(true);

        if ($request->hasError()) {
            $json = is_array($json) ? $json : [];
            $remote_code = (string)($json['error'] ?? 'remote_error');
            $code_parts = explode(':', $remote_code);
            $code = end($code_parts) ?: 'remote_error';
            $meta = isset($json['meta']) && is_array($json['meta']) ? $json['meta'] : [];
            $meta['source_name'] = $application->getName();

            return GiveawayEntryResult::failure(
                $code,
                $meta,
                isset($meta['cooldown']) && is_numeric($meta['cooldown'])
                    ? (int)$meta['cooldown']
                    : null
            );
        }

        $cooldown = is_array($json) && isset($json['cooldown']) && is_numeric($json['cooldown']) ? (int)$json['cooldown'] : null;

        return GiveawayEntryResult::success($cooldown, [
            'source_id' => $application_id,
            'giveaway' => $giveaway_id,
        ]);
    }

    public static function getPartydragenLink(int $giveaway_id): string {
        $referral_code = Settings::get('referral_code', null, 'Giveaway');

        return 'https://partydragen.com/giveaway/view/' . $giveaway_id . (!empty($referral_code) ? '?ref=' . rawurlencode($referral_code) : '');
    }

    private static function normalizeOAuthGiveaway(Application $application, array $item): array {
        $application_id = (int)$application->data()->id;
        $giveaway_id = (int)$item['id'];

        $time_remaining = isset($item['time_remaining']) && is_numeric($item['time_remaining']) ? (int)$item['time_remaining'] : null;

        // Older Giveaway module APIs returned minutes. New APIs explicitly say seconds.
        if (
            $time_remaining !== null
            && ($item['time_remaining_unit'] ?? 'minutes') !== 'seconds'
        ) {
            $time_remaining *= 60;
        }

        return [
            'reference' => 'oauth:' . $application_id . ':' . $giveaway_id,
            'source' => 'oauth',
            'source_id' => $application_id,
            'source_name' => $application->getName(),
            'id' => $giveaway_id,
            'active' => (bool)($item['active'] ?? false),
            'content' => $item['content'] ?? '',
            'prize' => $item['prize'] ?? '',
            'created' => (int)($item['created'] ?? 0),
            'ends' => (int)($item['ends'] ?? 0),
            'entry_interval' => (int)($item['entry_interval'] ?? 0),
            'entry_period' => $item['entry_period'] ?? 'no_period',
            'required_integrations' => array_map('intval', $item['required_integrations'] ?? []),
            'required_groups' => array_map('intval', $item['required_groups'] ?? []),
            'entries' => (int)($item['entries'] ?? 0),
            'your_entries' => (int)($item['your_entries'] ?? 0),
            'can_enter' => (bool)($item['can_enter'] ?? false),
            'time_remaining' => $time_remaining,
            'time_remaining_unit' => 'seconds',
            'winners' => (int)($item['winners'] ?? 0),
            'winners_list' => self::normalizeWinners($item['winners_list'] ?? []),
            'link' => $item['link'] ?? (
                rtrim($application->getWebsiteURL(), '/') . '/giveaway/view/' . $giveaway_id
            ),
            'entry_mode' => 'proxy',
        ];
    }

    private static function getPartydragenGiveaways(): array {
        $request = HttpClient::get(
            'https://partydragen.com/index.php?route=/api/v2/giveaways&active=true'
        );

        if ($request->hasError()) {
            return [];
        }

        $result = $request->json(true);
        if (!is_array($result) || !isset($result['giveaways']) || !is_array($result['giveaways'])) {
            return [];
        }

        $giveaways = [];
        foreach ($result['giveaways'] as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }

            $id = (int)$item['id'];
            $giveaways[] = [
                'reference' => 'mcc:' . $id,
                'source' => 'mcc',
                'source_id' => null,
                'source_name' => 'Partydragen',
                'id' => $id,
                'active' => (bool)($item['active'] ?? false),
                'content' => $item['content'] ?? '',
                'prize' => $item['prize'] ?? '',
                'created' => (int)($item['created'] ?? 0),
                'ends' => (int)($item['ends'] ?? 0),
                'entry_interval' => (int)($item['entry_interval'] ?? 0),
                'entry_period' => $item['entry_period'] ?? 'no_period',
                'required_integrations' => [],
                'required_groups' => [],
                'entries' => (int)($item['entries'] ?? 0),
                'your_entries' => 0,
                'can_enter' => (bool)($item['active'] ?? false),
                'time_remaining' => null,
                'time_remaining_unit' => 'seconds',
                'winners' => (int)($item['winners'] ?? 0),
                'winners_list' => self::normalizeWinners($item['winners_list'] ?? []),
                'link' => self::getPartydragenLink($id),
                'entry_mode' => 'external',
            ];
        }

        return $giveaways;
    }

    private static function normalizeWinners($winners): array {
        if (!is_array($winners)) {
            return [];
        }

        $result = [];
        foreach ($winners as $winner) {
            if (is_object($winner)) {
                $winner = (array)$winner;
            }
            if (!is_array($winner)) {
                continue;
            }

            $user_id = (int)($winner['user_id'] ?? $winner['id'] ?? 0);
            $result[] = [
                'id' => $user_id,
                'user_id' => $user_id,
                'username' => $winner['username'] ?? '',
                'profile' => $winner['profile'] ?? '',
                'style' => $winner['style'] ?? '',
                'avatar' => $winner['avatar'] ?? '',
            ];
        }

        return $result;
    }

    /** @return Application[] */
    private static function getApplications(): array {
        $applications = DB::getInstance()->get('oauth2_applications', ['nameless', '=', '1']);
        if (!$applications->count()) {
            return [];
        }

        $result = [];
        foreach ($applications->results() as $row) {
            $application = new Application(null, null, $row);
            if ($application->exists()) {
                $result[] = $application;
            }
        }

        return $result;
    }

    private static function findApplication(int $application_id): ?Application {
        $query = DB::getInstance()->query(
            'SELECT * FROM nl2_oauth2_applications WHERE id = ? AND nameless = 1 LIMIT 1',
            [$application_id]
        );

        if (!$query->count()) {
            return null;
        }

        $application = new Application(null, null, $query->first());

        return $application->exists() ? $application : null;
    }

    private static function getApiUrl(Application $application): string {
        return rtrim($application->getWebsiteURL(), '/') . '/index.php?route=/api/v2';
    }

    private static function getHeaders(Application $application): array {
        return [
            'headers' => [
                'Authorization' => 'Bearer ' . $application->data()->nameless_api_key,
                'Content-Type' => 'application/json',
            ],
        ];
    }
}
