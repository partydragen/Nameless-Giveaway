<?php

class GiveawayUpdatedEvent extends AbstractEvent implements HasWebhookParams {

    public Giveaway $giveaway;

    public function __construct(Giveaway $giveaway) {
        $this->giveaway = $giveaway;
    }

    public static function name(): string {
        return 'giveawayUpdated';
    }

    public function webhookParams(): array {
        $required_groups_list = [];
        $required_groups = json_decode($this->giveaway->data()->required_groups, true) ?? [];
        foreach ($required_groups as $item) {
            $required_groups_list[] = (int) $item;
        }

        $required_integrations_list = [];
        $required_integrations = json_decode($this->giveaway->data()->required_integrations, true) ?? [];
        foreach ($required_integrations as $item) {
            $required_integrations_list[] = (int) $item;
        }

        return [
            'id' => (int) $this->giveaway->data()->id,
            'prize' => $this->giveaway->data()->prize,
            'created' => (int) $this->giveaway->data()->created,
            'ends' => (int) $this->giveaway->data()->ends,
            'winners' => (int) $this->giveaway->data()->winners,
            'entry_interval' => (int) $this->giveaway->data()->entry_interval,
            'entry_period' => $this->giveaway->data()->entry_period,
            'required_integrations' => $required_integrations_list,
            'required_groups' => $required_groups_list,
            'entries' => (int) DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ?', [$this->giveaway->data()->id])->first()->c,
            'link' => URL::getSelfURL() . ltrim(URL::build('/giveaway/view/' . $this->giveaway->data()->id), '/')
        ];
    }

    public static function description(): string {
        return (new Language(ROOT_PATH . '/modules/Giveaway/language'))->get('admin', 'giveaway_updated');
    }
}