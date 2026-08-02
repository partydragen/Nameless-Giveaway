<?php

class ListGiveawaysEndpoint extends KeyAuthEndpoint {

    public function __construct() {
        $this->_route = 'giveaways';
        $this->_module = 'Giveaway';
        $this->_description = 'List all giveaways';
        $this->_method = 'GET';
    }

    public function execute(Nameless2API $api): void {
        $filters = [
            'giveaway' => isset($_GET['giveaway']) && is_numeric($_GET['giveaway']) ? (int)$_GET['giveaway'] : null,
            'active' => $_GET['active'] ?? null,
            'limit' => isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10,
        ];

        $user = null;
        $ip = null;

        if (isset($_GET['user'])) {
            $user = $this->transformUser($api, (string)$_GET['user']);
            $ip = $user->data()->lastip ?? null;
        }

        $api->returnArray([
            'giveaways' => GiveawayService::getGiveaways($user, $filters, $ip),
        ]);
    }

    private function transformUser(Nameless2API $api, string $value) {
        return Endpoints::getAllTransformers()['user']['transformer']($api, $value);
    }
}
