<?php

class GiveawayDeletedEvent extends AbstractEvent implements HasWebhookParams {

    public Giveaway $giveaway;

    public function __construct(Giveaway $giveaway) {
        $this->giveaway = $giveaway;
    }

    public static function name(): string {
        return 'giveawayDeleted';
    }

    public function webhookParams(): array {
        return [
            'id' => (int) $this->giveaway->data()->id
        ];
    }

    public static function description(): string {
        return (new Language(ROOT_PATH . '/modules/Giveaway/language'))->get('admin', 'giveaway_deleted');
    }
}