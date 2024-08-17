<?php

class UserEntryGiveawayEvent extends AbstractEvent implements HasWebhookParams {

    public Giveaway $giveaway;
    public User $user;

    public function __construct(Giveaway $giveaway, User $user) {
        $this->giveaway = $giveaway;
        $this->user = $user;
    }

    public static function name(): string {
        return 'userEntryGiveaway';
    }

    public function webhookParams(): array {
        return [
            'id' => (int) $this->giveaway->data()->id,
            'user' => [
                'id' => (int) $this->user->data()->id,
                'username' => $this->user->data()->username
            ],
            'link' => URL::getSelfURL() . ltrim(URL::build('/giveaway/view/' . $this->giveaway->data()->id), '/')
        ];
    }

    public static function description(): string {
        return (new Language(ROOT_PATH . '/modules/Giveaway/language'))->get('admin', 'user_entry_giveaway');
    }
}
