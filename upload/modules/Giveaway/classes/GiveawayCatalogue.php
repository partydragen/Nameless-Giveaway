<?php

class GiveawayCatalogue {

    public static function getGiveaways(User $user, ?string $ip = null): array {
        $local = GiveawayService::getGiveaways($user, ['limit' => null], $ip);
        $external = ExternalGiveaways::getGiveaways($user);
        $giveaways = array_merge($local, $external);

        usort($giveaways, static function (array $a, array $b): int {
            if ((bool)$a['active'] !== (bool)$b['active']) {
                return $a['active'] ? -1 : 1;
            }

            if ($a['active']) {
                return ((int)$a['ends']) <=> ((int)$b['ends']);
            }

            return ((int)$b['ends']) <=> ((int)$a['ends']);
        });

        return $giveaways;
    }

    public static function getActiveGiveaways(
        User $user,
        ?string $ip = null,
        ?int $limit = null
    ): array {
        $now = time();

        $active_giveaways = array_values(array_filter(
            self::getGiveaways($user, $ip),
            static function (array $giveaway) use ($now): bool {
                if (!(bool)($giveaway['active'] ?? false)) {
                    return false;
                }

                // Also verify the ending timestamp instead of trusting
                // the supplied active value alone.
                return !isset($giveaway['ends'])
                    || (int)$giveaway['ends'] > $now;
            }
        ));

        // Show giveaways ending soonest first.
        usort(
            $active_giveaways,
            static function (array $first, array $second): int {
                $first_ends = (int)($first['ends'] ?? PHP_INT_MAX);
                $second_ends = (int)($second['ends'] ?? PHP_INT_MAX);

                return $first_ends <=> $second_ends;
            }
        );

        if ($limit !== null) {
            if ($limit <= 0) {
                return [];
            }

            $active_giveaways = array_slice(
                $active_giveaways,
                0,
                $limit
            );
        }

        return $active_giveaways;
    }

    public static function enter(string $reference, User $user, string $ip): GiveawayEntryResult {
        if (ctype_digit($reference)) {
            return GiveawayService::enter((int)$reference, $user, $ip);
        }

        $parts = explode(':', $reference);
        $source = $parts[0] ?? '';

        if ($source === 'local' && isset($parts[1]) && ctype_digit($parts[1])) {
            return GiveawayService::enter((int)$parts[1], $user, $ip);
        }

        if (
            $source === 'oauth'
            && isset($parts[1], $parts[2])
            && ctype_digit($parts[1])
            && ctype_digit($parts[2])
        ) {
            return ExternalGiveaways::enterGiveaway(
                (int)$parts[1],
                (int)$parts[2],
                $user
            );
        }

        if ($source === 'mcc' && isset($parts[1]) && ctype_digit($parts[1])) {
            return GiveawayEntryResult::failure('external_entry_required', [
                'redirect' => ExternalGiveaways::getPartydragenLink((int)$parts[1]),
            ]);
        }

        return GiveawayEntryResult::failure('giveaway_not_found');
    }

    public static function toTemplateList(array $giveaways, Language $language): array {
        $result = [];

        foreach ($giveaways as $giveaway) {
            $result[] = self::toTemplate($giveaway, $language);
        }

        return $result;
    }

    public static function toTemplate(array $giveaway, Language $language): array {
        $remaining_seconds = isset($giveaway['time_remaining']) ? (int)$giveaway['time_remaining'] : null;
        $remaining_minutes = $remaining_seconds !== null ? max(1, (int)ceil($remaining_seconds / 60)) : null;

        $prize = (string)$giveaway['prize'];
        /*if (($giveaway['source'] ?? 'local') !== 'local') {
            $prize = '[' . $giveaway['source_name'] . '] ' . $prize;
        }*/

        if (!(bool)$giveaway['active']) {
            $disabled_text = $language->get('general', 'giveaway_already_ended');

        } elseif ($remaining_minutes !== null) {
            $disabled_text = $language->get('general', 'enter_again_in', [
                'minutes' => Output::getClean($remaining_minutes),
            ]);

        } else {
            $disabled_text = $language->get('general', 'already_entered_giveaway');
        }

        return [
            'id' => Output::getClean($giveaway['reference']),
            'source' => Output::getClean($giveaway['source']),
            'source_name' => Output::getClean($giveaway['source_name']),
            'prize' => Output::getClean($prize),
            'active' => (bool)$giveaway['active'],
            'ends_x' => $language->get('general', 'ends_x', [
                'ends' => date(DATE_FORMAT, (int)$giveaway['ends']),
            ]),
            'entries_x' => $language->get('general', 'entries_x', [
                'entries' => (int)$giveaway['entries'],
            ]),
            'your_entries_x' => $language->get('general', 'your_entries_x', [
                'entries' => (int)$giveaway['your_entries'],
            ]),
            'winners' => $giveaway['winners_list'],
            'can_enter' => (bool)$giveaway['can_enter'],
            'time_remaining' => $remaining_minutes,
            'enter_disabled_button' => $disabled_text,
            'view_link' => $giveaway['link'],
            'entry_mode' => $giveaway['entry_mode'],
        ];
    }

    public static function getMessage(
        GiveawayEntryResult $result,
        Language $language,
        bool $allow_html_links = false
    ): string {
        $meta = $result->getMeta();

        switch ($result->getCode()) {
            case 'successfully_entered_giveaway':
                return $language->get('general', 'successfully_entered_giveaway');

            case 'login_to_enter':
                return $language->get('general', 'login_to_enter');

            case 'giveaway_not_found':
                return $language->get('general', 'giveaway_not_found');

            case 'giveaway_already_ended':
                return $language->get('general', 'giveaway_already_ended');

            case 'giveaway_requires_integration':
                return $language->get('general', 'giveaway_requires_integration', [
                    'integration' => Output::getClean($meta['integration'] ?? 'Unknown'),
                    'linkStart' => $allow_html_links
                        ? '<a href="' . URL::build('/user/connections') . '">'
                        : '',
                    'linkEnd' => $allow_html_links ? '</a>' : '',
                ]);

            case 'giveaway_requires_group':
                if (!isset($meta['group_name']) && isset($meta['message'])) {
                    return (string)$meta['message'];
                }

                return $language->get('general', 'giveaway_requires_groups', [
                    'group' => Output::getClean($meta['group_name'] ?? 'Unknown'),
                ]);

            case 'already_entered_giveaway':
                return $language->get('general', 'already_entered_giveaway');

            case 'event_cancelled':
            case 'remote_error':
                return (string)($meta['message'] ?? 'Unknown error occurred');

            default:
                return (string)($meta['message'] ?? 'Unknown error occurred');
        }
    }
}
