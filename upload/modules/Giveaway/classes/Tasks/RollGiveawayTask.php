<?php

class RollGiveawayTask extends Task {

    public function run(): string {
        $giveaway = new Giveaway($this->getEntityId());
        if (!$giveaway->exists()) {
            $this->setOutput(['error' => 'giveaway does not exist']);
            return Task::STATUS_FAILED;
        }

        $winners = [0];
        $total_winners = $giveaway->data()->winners;
        for ($i = 0; $i < $total_winners; $i++) {
            $imploded_winners = implode(',', $winners);

            $entries = DB::getInstance()->query("SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ? AND user_id NOT IN ($imploded_winners)", [$giveaway->data()->id])->first()->c;
            if ($entries != 0) {
                $winner = rand(0, $entries - 1);

                $winner_user = DB::getInstance()->query("SELECT user_id FROM nl2_giveaway_entries WHERE giveaway_id = ? AND user_id NOT IN ($imploded_winners) LIMIT 1 OFFSET ?", [$giveaway->data()->id, $winner]);
                if ($winner_user->count()) {
                    $user_id = $winner_user->first()->user_id;

                    DB::getInstance()->insert('giveaway_winners', [
                        'giveaway_id' => $giveaway->data()->id,
                        'user_id' => $user_id
                    ]);

                    $winners[] = $user_id;
                }
            }
        }

        unset($winners[0]);
        $this->setOutput(['winners' => $winners]);

        return Task::STATUS_COMPLETED;
    }
}