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

        $giveaway_language = new Language(
            ROOT_PATH . '/modules/Giveaway/language',
            DEFAULT_LANGUAGE
        );

        $result = GiveawayService::enter(
            (int)$_POST['giveaway'],
            $user,
            (string)($user->data()->lastip ?? '')
        );

        $message = GiveawayCatalogue::getMessage(
            $result,
            $giveaway_language,
            false
        );

        if (!$result->isSuccess()) {
            $meta = $result->getMeta();
            $meta['message'] = $message;

            if ($result->getCooldown() !== null) {
                $meta['cooldown'] = $result->getCooldown();
            }

            $api->throwError('giveaway:' . $result->getCode(), $meta);
        }

        $response = ['message' => $message];
        if ($result->getCooldown() !== null) {
            $response['cooldown'] = $result->getCooldown();
        }

        $api->returnArray($response);
    }
}
