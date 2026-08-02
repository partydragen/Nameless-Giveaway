<?php
/**
 * Giveaway class
 *
 * @package Modules\Giveaway
 * @author Partydragen
 * @version 2.2.5
 * @license MIT
 */
class Giveaway {

    private $_data;

    public function __construct(?string $value = null, ?string $field = 'id', $query_data = null) {
        if (!$query_data && $value) {
            $data = DB::getInstance()->get('giveaway', [$field, '=', $value]);
            if ($data->count()) {
                $this->_data = $data->first();
            }
        } else if ($query_data) {
            $this->_data = $query_data;
        }
    }

    /**
     * Does this giveaway exist?
     *
     * @return bool Whether the giveaway exists (has data) or not.
     */
    public function exists(): bool {
        return (!empty($this->_data));
    }

    /**
     * @return object This giveaway's data.
     */
    public function data(): object {
        return $this->_data;
    }

    /**
     * Is this giveaway active?
     *
     * @return bool Whether the giveaway is active or not.
     */
    public function isActive(): bool {
        return $this->data()->ends > date('U');
    }

    /**
     * Get the required user integrations that this giveaway require.
     *
     * @return array List of required integrations.
     */
    public function getRequiredIntegrations(): array {
        $required_integrations_list = [];

        $integrations = Integrations::getInstance();
        $enabled_integrations = $integrations->getEnabledIntegrations();
        $required_integrations = json_decode($this->data()->required_integrations, true) ?? [];
        foreach ($required_integrations as $item) {
            foreach ($enabled_integrations as $integration) {
                if ($integration->data()->id == $item) {
                    $required_integrations_list[$integration->data()->id] = $integration;
                }
            }
        }

        return $required_integrations_list;
    }

    /**
     * Get the canonical entry status. Time remaining is returned in seconds.
     */
    public function getEntryStatus(User $user, ?string $ip = null): array {
        return GiveawayService::getEntryStatus($this, $user, $ip);
    }

    /*
     * Get the winners of this giveaway.
     *
     * @return array List of winners.
     */
    public function getWinners(): array {
        $winners = [];

        if (!$this->isActive()) {
            $winners_query = DB::getInstance()->query('SELECT user_id FROM nl2_giveaway_winners WHERE giveaway_id = ?', [$this->data()->id]);
            if ($winners_query->count()) {
                foreach ($winners_query->results() as $winner) {
                    $winner_user = new User($winner->user_id);
                    if ($winner_user->exists()) {
                        $winners[] = [
                            'user_id' => $winner_user->data()->id,
                            'username' => $winner_user->getDisplayname(),
                            'profile' => $winner_user->getProfileURL(),
                            'style' => $winner_user->getGroupStyle(),
                            'avatar' => $winner_user->getAvatar()
                        ];
                    }
                }
            }
        }

        return $winners;
    }
}