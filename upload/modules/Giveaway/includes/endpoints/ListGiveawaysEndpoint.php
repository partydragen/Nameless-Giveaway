<?php

class ListGiveawaysEndpoint extends KeyAuthEndpoint {

    public function __construct() {
        $this->_route = 'giveaways';
        $this->_module = 'Giveaway';
        $this->_description = 'List all giveaways';
        $this->_method = 'GET';
    }

    public function execute(Nameless2API $api): void {
        $query = 'SELECT * FROM nl2_giveaway';
        $where = ' WHERE id <> 0';
        $order = ' ORDER BY `id` DESC';
        $limit = '';
        $params = [];

        if (isset($_GET['giveaway'])) {
            $where .= ' AND id = ?';
            array_push($params, $_GET['giveaway']);
        }

        if (isset($_GET['active'])) {
            $where .= ' AND ends ' . ($_GET['active'] == 'true' ? '>' : '<') .' ?';
            array_push($params, date('U'));
        }

        if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
            $limit .= ' LIMIT '. $_GET['limit'];
        } else {
            $limit .= ' LIMIT 10';
        }

        $giveaways_list = [];
        $giveaway_query = $api->getDb()->query($query . $where . $order . $limit, $params);
        if ($giveaway_query->count()) {
            foreach ($giveaway_query->results() as $item) {
                $giveaway = new Giveaway(null, null, $item);

                $required_groups_list = [];
                $required_groups = json_decode($giveaway->data()->required_groups, true) ?? [];
                foreach ($required_groups as $group) {
                    $required_groups_list[] = (int)$group;
                }

                $required_integrations_list = [];
                $required_integrations = json_decode($giveaway->data()->required_integrations, true) ?? [];
                foreach ($required_integrations as $integration) {
                    $required_integrations_list[] = (int)$integration;
                }

                $winners_list = [];
                $winners_query = DB::getInstance()->query('SELECT user_id FROM nl2_giveaway_winners WHERE giveaway_id = ?', [$giveaway->data()->id]);
                if ($winners_query->count()) {
                    foreach ($winners_query->results() as $winner) {
                        $winner_user = new User($winner->user_id);
                        if ($winner_user->exists()) {
                            $winners_list[] = [
                                'id' => $winner_user->data()->id,
                                'username' => $winner_user->getDisplayname(),
                                'profile' => $winner_user->getProfileURL(),
                                'avatar' => URL::getSelfURL() . ltrim($winner_user->getAvatar(), '/')
                            ];
                        }
                    }
                }

                $giveaways_list[] = [
                    'id' => (int)$giveaway->data()->id,
                    'active' => $giveaway->isActive(),
                    'prize' => $giveaway->data()->prize,
                    'created' => (int)$giveaway->data()->created,
                    'ends' => (int)$giveaway->data()->ends,
                    'entry_interval' => (int)$giveaway->data()->entry_interval,
                    'entry_period' => $giveaway->data()->entry_period,
                    'required_integrations' => $required_integrations_list,
                    'required_groups' => $required_groups_list,
                    'entries' => (int)DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ?', [$giveaway->data()->id])->first()->c,
                    'winners' => (int)$giveaway->data()->winners,
                    'winners_list' => $winners_list,
                    'link' => URL::getSelfURL() . ltrim(URL::build('/giveaway'), '/')
                ];
            }
        }

        $api->returnArray(['giveaways' => $giveaways_list]);
    }
}