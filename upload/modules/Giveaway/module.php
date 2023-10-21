<?php 
/*
 *  Made by Partydragen
 *  https://partydragen.com
 *  https://mc-server-list.com/
 *
 *  Giveaway module initialisation file
 */

class Giveaway_Module extends Module {

    private Language $_language;
    private Language $_giveaway_language;

    public function __construct($language, $giveaway_language, $pages, Endpoints $endpoints){
        $this->_language = $language;
        $this->_giveaway_language = $giveaway_language;

        $name = 'Giveaway';
        $author = '<a href="https://partydragen.com" target="_blank" rel="nofollow noopener">Partydragen</a> and my <a href="https://partydragen.com/supporters/" target="_blank">Sponsors</a>';
        $module_version = '1.1.0';
        $nameless_version = '2.1.2';

        parent::__construct($this, $name, $author, $module_version, $nameless_version);

        // Define URLs which belong to this module
        $pages->add('Giveaway', '/giveaway', 'pages/giveaway.php', 'giveaway', true);
        $pages->add('Giveaway', '/giveaway/view', 'pages/view_giveaway.php', 'giveaway', true);
        $pages->add('Giveaway', '/panel/giveaway', 'pages/panel/giveaway.php');

        EventHandler::registerEvent(GiveawayCreatedEvent::class);
        EventHandler::registerEvent(GiveawayUpdatedEvent::class);
        EventHandler::registerEvent(GiveawayEndedEvent::class);
        EventHandler::registerEvent(UserEntryGiveawayEvent::class);

        $endpoints->loadEndpoints(ROOT_PATH . '/modules/Giveaway/includes/endpoints');
    }

    public function onInstall() {
        // Initialise
        $this->initialise();
    }

    public function onUninstall() {
        // No actions necessary
    }

    public function onEnable() {
        // Check if we need to initialise again
        $this->initialise();
    }

    public function onDisable() {
        // No actions necessary
    }

    public function onPageLoad($user, $pages, $cache, $smarty, $navs, $widgets, $template) {
        // Add link to navbar
        $cache->setCache('navbar_order');
        if (!$cache->isCached('giveaway_order')){
            $order = 4;
            $cache->store('giveaway_order', 4);
        } else {
            $order = $cache->retrieve('giveaway_order');
        }

        $cache->setCache('navbar_icons');
        if (!$cache->isCached('giveaway_icon'))
            $icon = '';
        else
            $icon = $cache->retrieve('giveaway_icon');

        $navs[0]->add('giveaway', $this->_giveaway_language->get('general', 'giveaway'), URL::build('/giveaway'), 'top', null, $order, $icon);

        if (defined('BACK_END')){
            if ($user->hasPermission('staffcp.giveaway')){
                $cache->setCache('panel_sidebar');
                if (!$cache->isCached('giveaway_order')){
                    $order = 20;
                    $cache->store('giveaway_order', 20);
                } else {
                    $order = $cache->retrieve('giveaway_order');
                }

                $navs[2]->add('giveaway_divider', mb_strtoupper($this->_giveaway_language->get('general', 'giveaway')), 'divider', 'top', null, $order, '');
                if(!$cache->isCached('giveaway_icon')){
                    $icon = '<i class="nav-icon fas fa-dollar-sign"></i>';
                    $cache->store('giveaway_icon', $icon);
                } else
                    $icon = $cache->retrieve('giveaway_icon');

                $navs[2]->add('giveaway', $this->_giveaway_language->get('general', 'giveaway'), URL::build('/panel/giveaway'), 'top', null, ($order + 0.1), $icon);
            }

            /*if (defined('PANEL_PAGE') && PANEL_PAGE == 'dashboard'){
                // Dashboard graph

                // Get data for points
                $points = DB::getInstance()->orderWhere('giveaway', 'entered > ' . strtotime("-1 week"), 'entered', 'ASC')->results();

                $cache->setCache('dashboard_graph');
                if ($cache->isCached('giveaway_data')){
                    $output = $cache->retrieve('giveaway_data');

                } else {
                    $output = [];

                    $output['datasets']['points']['label'] = 'giveaway_language/general/giveaway'; // for $giveaway_language->get('general', 'giveaway');
                    $output['datasets']['points']['colour'] = '#603000';

                    foreach ($points as $point){
                        $date = date('d M Y', $point->entered);
                        $date = '_' . strtotime($date);

                        if(isset($output[$date]['points'])){
                            $output[$date]['points'] = $output[$date]['points'] + 1;
                        } else {
                            $output[$date]['points'] = 1;
                        }
                    }

                    // Fill in missing dates, set points to 0
                    $start = strtotime("-1 week");
                    $start = date('d M Y', $start);
                    $start = strtotime($start);
                    $end = strtotime(date('d M Y'));
                    while($start <= $end){
                        if(!isset($output['_' . $start]['points']))
                            $output['_' . $start]['points'] = 0;

                        $start = strtotime('+1 day', $start);
                    }

                    // Sort by date
                    ksort($output);

                    $cache->store('giveaway_data', $output, 120);

                }

                Core_Module::addDataToDashboardGraph($this->_language->get('admin', 'overview'), $output);
            }*/
        }

        // Check for module updates
        if (isset($_GET['route']) && $user->isLoggedIn() && $user->hasPermission('admincp.update')) {
            // Page belong to this module?
            $page = $pages->getActivePage();
            if ($page['module'] == 'Giveaway') {

                $cache->setCache('giveaway_module_cache');
                if ($cache->isCached('update_check')) {
                    $update_check = $cache->retrieve('update_check');
                } else {
                    $update_check = Giveaway_Module::updateCheck();
                    $cache->store('update_check', $update_check, 3600);
                }

                $update_check = json_decode($update_check);
                if (!isset($update_check->error) && !isset($update_check->no_update) && isset($update_check->new_version)) {
                    $smarty->assign(array(
                        'NEW_UPDATE' => (isset($update_check->urgent) && $update_check->urgent == 'true') ? $this->_giveaway_language->get('general', 'new_urgent_update_available_x', ['module' => $this->getName()]) : $this->_giveaway_language->get('general', 'new_update_available_x', ['module' => $this->getName()]),
                        'NEW_UPDATE_URGENT' => (isset($update_check->urgent) && $update_check->urgent == 'true'),
                        'CURRENT_VERSION' => $this->_giveaway_language->get('general', 'current_version_x', [
                            'version' => Output::getClean($this->getVersion())
                        ]),
                        'NEW_VERSION' => $this->_giveaway_language->get('general', 'new_version_x', [
                            'new_version' => Output::getClean($update_check->new_version)
                        ]),
                        'NAMELESS_UPDATE' => $this->_giveaway_language->get('general', 'view_resource'),
                        'NAMELESS_UPDATE_LINK' => Output::getClean($update_check->link)
                    ));
                }
            }
        }
    }

    public function getDebugInfo(): array {
        return [];
    }

    private function initialise() {
        // Generate tables
        if (!DB::getInstance()->showTables('giveaway')) {
            try {
                DB::getInstance()->createTable("giveaway", " `id` int(11) NOT NULL AUTO_INCREMENT, `prize` varchar(128) NOT NULL, `winners` int(11) NOT NULL, `entry_interval` int(11) NOT NULL, `entry_period` varchar(32) NOT NULL, `created` int(11) NOT NULL, `ends` int(11) NOT NULL, `required_integrations` varchar(128) DEFAULT NULL, `required_groups` varchar(128) DEFAULT NULL, `task_id` int(11) DEFAULT NULL, PRIMARY KEY (`id`)");
            } catch(Exception $e){
                // Error
            }
        }

        if (!DB::getInstance()->showTables('giveaway_entries')) {
            try {
                DB::getInstance()->createTable("giveaway_entries", " `id` int(11) NOT NULL AUTO_INCREMENT, `giveaway_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `entered` int(11) NOT NULL, `ip` varchar(128) NOT NULL, PRIMARY KEY (`id`)");
            } catch(Exception $e){
                // Error
            }
        }

        if (!DB::getInstance()->showTables('giveaway_winners')) {
            try {
                DB::getInstance()->createTable("giveaway_winners", " `id` int(11) NOT NULL AUTO_INCREMENT, `giveaway_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, PRIMARY KEY (`id`)");
            } catch(Exception $e){
                // Error
            }
        }
    }

    private static function updateCheck() {
        $current_version = Settings::get('nameless_version');
        $uid = Settings::get('unique_id');

        $enabled_modules = Module::getModules();
        foreach ($enabled_modules as $enabled_item) {
            if ($enabled_item->getName() == 'Giveaway') {
                $module = $enabled_item;
                break;
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, 'https://api.partydragen.com/stats.php?uid=' . $uid . '&version=' . $current_version . '&module=Giveaway&module_version='.$module->getVersion() . '&domain='. URL::getSelfURL());

        $update_check = curl_exec($ch);
        curl_close($ch);

        $info = json_decode($update_check);
        if (isset($info->message)) {
            die($info->message);
        }

        return $update_check;
    }
}