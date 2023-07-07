<?php
class SubmitUserEntryEndpoint extends KeyAuthEndpoint {

    public function __construct() {
        $this->_route = 'users/{user}/giveaway/entry';
        $this->_module = 'Giveaway';
        $this->_description = 'Submit user entry giveaway';
        $this->_method = 'POST';
    }

    public function execute(Nameless2API $api, User $user): void {
        $api->validateParams($_POST, ['giveaway']);

        $giveaway = new Giveaway($_POST['giveaway']);
        if (!$giveaway->exists()) {
            $api->throwError('giveaway:giveaway_not_found');
        }

        // Make sure giveaway is active
        if (!$giveaway->isActive()) {
            $api->throwError('giveaway:giveaway_already_ended');
        }

        // Any required integrations?
        foreach ($giveaway->getRequiredIntegrations() as $integration) {
            $integrationUser = $user->getIntegration($integration->getName());
            if ($integrationUser == null || $integrationUser->data()->username == null || $integrationUser->data()->identifier == null) {
                $api->throwError('giveaway:giveaway_requires_integration', [$integration->getName()]);
            }
        }

        // Any required groups?
        $required_groups = json_decode($giveaway->data()->required_groups, true) ?? [];
        if (count($required_groups)) {
            $user_groups = $user->getAllGroupIds();
            foreach ($required_groups as $item) {
                if(!array_key_exists($item, $user_groups)) {
                    $api->throwError('giveaway:giveaway_requires_group', [$item]);
                }
            }
        }

        // Has user id or ip already entered?
        $already_entered = DB::getInstance()->query('SELECT entered FROM nl2_giveaway_entries WHERE giveaway_id = ? AND ip = ? OR giveaway_id = ? AND user_id = ? ORDER BY entered DESC LIMIT 1', [$giveaway->data()->id, HttpUtils::getRemoteAddress(), $giveaway->data()->id, $user->data()->id]);
        if ($already_entered->count()) {
            $already_entered = $already_entered->first();

            if ($already_entered->entered >= strtotime('-' . $giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) {
                $has_entered = true;
                $time_left = round(($already_entered->entered - strtotime('-' . $giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) / 60);
            }
        }

        if (!isset($has_entered)) {
            DB::getInstance()->insert("giveaway_entries", [
                'giveaway_id' => $giveaway->data()->id,
                'user_id' => $user->data()->id,
                'entered' => date("U"),
                'ip' => HttpUtils::getRemoteAddress(),
            ]);

            // Re-query giveaway
            EventHandler::executeEvent(new UserEntryGiveawayEvent($giveaway, $user));
            EventHandler::executeEvent(new GiveawayUpdatedEvent($giveaway));

            $api->returnArray(['message' => 'Successfully entered giveaway', 'cooldown' => round((date('U') - strtotime($giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) / 60)]);
        } else {
            $api->throwError('giveaway:already_entered_giveaway', ['cooldown' => $time_left]);
        }
    }
}
