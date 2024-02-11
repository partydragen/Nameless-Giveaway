<?php
/**
 * DeleteUserGiveawayListener class
 *
 * @package Modules\Giveaway
 * @author Partydragen
 * @version 2.1.0
 * @license MIT
 */

class DeleteUserGiveawayListener {

    public static function execute(UserDeletedEvent $event): void {
        DB::getInstance()->query('DELETE FROM nl2_giveaway_entries WHERE user_id = ?', [$event->user->data()->id]);
    }
}