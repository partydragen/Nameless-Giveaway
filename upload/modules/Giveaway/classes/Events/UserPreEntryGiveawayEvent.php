<?php

class UserPreEntryGiveawayEvent extends AbstractEvent {

    use Cancellable;

    public Giveaway $giveaway;
    public User $user;

    public function __construct(Giveaway $giveaway, User $user) {
        $this->giveaway = $giveaway;
        $this->user = $user;
    }

    public static function name(): string {
        return 'userPreEntryGiveaway';
    }

    public static function description(): string {
        return (new Language(ROOT_PATH . '/modules/Giveaway/language'))->get('admin', 'user_pre_entry_giveaway');
    }

    public static function internal(): bool {
        return true;
    }

    public static function return(): bool {
        return true;
    }
}