<?php
/**
 * Giveaway class
 *
 * @package Modules\Giveaway
 * @author Partydragen
 * @version 2.1.0
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
}