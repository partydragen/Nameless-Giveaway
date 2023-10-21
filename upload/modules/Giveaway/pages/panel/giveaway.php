<?php
/*
 *	Made by Partydragen
 *  https://partydragen.com/
 *
 *  License: MIT
 *
 *  Giveaway module - panel giveaway page
 */

// Can the user view the StaffCP?
if (!$user->handlePanelPageLoad('staffcp.giveaway')) {
    require_once(ROOT_PATH . '/403.php');
    die();
}

define('PAGE', 'panel');
define('PARENT_PAGE', 'giveaway');
define('PANEL_PAGE', 'giveaway');
$page_title = $giveaway_language->get('general', 'giveaway');
require_once(ROOT_PATH . '/core/templates/backend_init.php');

if (!isset($_GET['action'])) {
    if (Input::exists()) {
        $errors = [];

        if (Token::check()) {
            // Show minecraft community giveaways?
            if (isset($_POST['mcc_giveaway']) && $_POST['mcc_giveaway'] == 'on')
                $show_mcc_giveaway = 1;
            else
                $show_mcc_giveaway = 0;

            Settings::set('mcc_giveaway', $show_mcc_giveaway, 'Giveaway');
        } else {
            // Invalid token
            $errors[] = $language->get('general', 'invalid_token');
        }
    }

    // List all giveaways
    $giveaway_query = DB::getInstance()->query('SELECT * FROM nl2_giveaway ORDER BY id DESC');
    if ($giveaway_query->count()) {

        $giveaway_list = [];
        foreach ($giveaway_query->results() as $giveaway) {
            $giveaway_list[] = [
                'id' => Output::getClean($giveaway->id),
                'prize' => Output::getClean($giveaway->prize),
                'winners' => Output::getClean($giveaway->winners),
                'entries' => DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ?', [$giveaway->id])->first()->c,
                'ends' => date(DATE_FORMAT, $giveaway->ends),
                'active' => $giveaway->ends > date('U'),
                'edit_link' => URL::build('/panel/giveaway', 'action=edit&giveaway=' . $giveaway->id)
            ];
        }

        $smarty->assign('GIVEAWAY_LIST', $giveaway_list);
    } else {
        $smarty->assign('NO_GIVEAWAYS', $giveaway_language->get('general', 'no_giveaways'));
    }

    $smarty->assign([
        'NEW_GIVEAWAY' => $giveaway_language->get('general', 'new_giveaway'),
        'NEW_GIVEAWAY_LINK' => URL::build('/panel/giveaway', 'action=new'),
        'ID' => $giveaway_language->get('general', 'id'),
        'PRIZE' => $giveaway_language->get('general', 'prize'),
        'ENTRIES' => $giveaway_language->get('general', 'entries'),
        'WINNERS' => $giveaway_language->get('general', 'winners'),
        'ENDS' => $giveaway_language->get('general', 'ends'),
        'MINECRAFT_COMMUNITY_VALUE' => Settings::get('mcc_giveaway', '1', 'Giveaway'),
    ]);

    $template_file = 'giveaway/giveaway.tpl';
} else {
    switch ($_GET['action']) {
        case 'new';
            // Create new giveaway
            if (Input::exists()) {
                $errors = [];

                if (Token::check()) {
                    $validation = Validate::check($_POST, [
                        'prize' => [
                            Validate::REQUIRED => true,
                            Validate::MIN => 1,
                            Validate::MAX => 128
                        ],
                        'winners' => [
                            Validate::REQUIRED => true,
                            Validate::NUMERIC => true
                        ],
                        'entry_interval' => [
                            Validate::REQUIRED => true,
                            Validate::NUMERIC => true
                        ],
                        'entry_period' => [
                            Validate::REQUIRED => true
                        ],
                        'ends' => [
                            Validate::REQUIRED => true
                        ]
                    ]);

                    if ($validation->passed()) {
                        $required_groups = $_POST['required_groups'];
                        $required_integrations = $_POST['required_integrations'];

                        DB::getInstance()->insert('giveaway', [
                            'prize' => Input::get('prize'),
                            'winners' => Input::get('winners'),
                            'entry_interval' => Input::get('entry_interval'),
                            'entry_period' => Input::get('entry_period'),
                            'created' => date('U'),
                            'ends' => strtotime($_POST['ends']),
                            'required_integrations' => json_encode(isset($required_integrations) && is_array($required_integrations) ? $required_integrations : []),
                            'required_groups' => json_encode(isset($required_groups) && is_array($required_groups) ? $required_groups : [])
                        ]);
                        $giveaway_id = DB::getInstance()->lastId();
                        $giveaway = new Giveaway($giveaway_id);

                        Queue::schedule((new RollGiveawayTask())->fromNew(
                            Module::getIdFromName('Giveaway'),
                            'Roll Giveaway',
                            [],
                            strtotime($_POST['ends']),
                            'giveaway',
                            $giveaway_id
                        ));
                        $task_id = DB::getInstance()->lastId();

                        DB::getInstance()->query('UPDATE nl2_giveaway SET task_id = ? WHERE id = ?', [$task_id, $giveaway_id]);

                        EventHandler::executeEvent(new GiveawayCreatedEvent($giveaway));

                        Session::flash('giveaway_success', $giveaway_language->get('general', 'giveaway_created_successfully'));
                        Redirect::to(URL::build('/panel/giveaway'));
                    } else {
                        // Validation errors
                        $errors = $validation->errors();
                    }
                } else {
                    // Invalid token
                    $errors[] = $language->get('general', 'invalid_token');
                }
            }

            $groups_list = [];
            $groups = DB::getInstance()->query('SELECT * FROM nl2_groups')->results();
            foreach ($groups as $item) {
                $groups_list[] = [
                    'id' => $item->id,
                    'name' => Output::getClean($item->name),
                    'selected' => ((isset($_POST['required_groups']) && is_array($_POST['required_groups'])) ? in_array($item->id, $_POST['required_groups']) : false)
                ];
            }

            $integrations_list = [];
            foreach (Integrations::getInstance()->getEnabledIntegrations() as $item) {
                $integrations_list[] = [
                    'id' => $item->data()->id,
                    'name' => Output::getClean($item->getName()),
                    'selected' => ((isset($_POST['required_integrations']) && is_array($_POST['required_integrations'])) ? in_array($item->data()->id, $_POST['required_integrations']) : false)
                ];
            }

            $smarty->assign([
                'GIVEAWAY_TITLE' => $giveaway_language->get('general', 'creating_giveaway'),
                'BACK' => $language->get('general', 'back'),
                'BACK_LINK' => URL::build('/panel/giveaway'),
                'PRIZE' => $giveaway_language->get('general', 'giveaway_prize'),
                'PRIZE_VALUE' => ((isset($_POST['prize']) && $_POST['prize']) ? Output::getClean(Input::get('prize')) : ''),
                'WINNERS' => $giveaway_language->get('general', 'giveaway_winners'),
                'WINNERS_VALUE' => ((isset($_POST['winners']) && $_POST['winners']) ? Output::getClean(Input::get('winners')) : '1'),
                'ENDS' => $giveaway_language->get('general', 'giveaway_ends'),
                'ENDS_VALUE' => ((isset($_POST['ends']) && $_POST['ends']) ? Input::get('ends') : date('Y-m-d\TH:i')),
                'ENDS_MIN' => date('Y-m-d\TH:i'),
                'ENTRY_INTERVAL' => ((isset($_POST['entry_interval']) && $_POST['entry_interval']) ? Output::getClean(Input::get('entry_interval')) : '1'),
                'ENTRY_PERIOD' => ((isset($_POST['entry_period']) && $_POST['entry_period']) ? Output::getClean(Input::get('entry_period')) : 'no_period'),
                'INTEGRATIONS_LIST' => $integrations_list,
                'GROUPS_LIST' => $groups_list,
            ]);

            $template_file = 'giveaway/giveaway_form.tpl';
        break;
        case 'edit';
            // Edit giveaway
            if (!isset($_GET['giveaway']) || !is_numeric($_GET['giveaway'])) {
                Redirect::to(URL::build('/panel/giveaway'));
            }

            $giveaway = new Giveaway($_GET['giveaway']);
            if (!$giveaway->exists()) {
                Redirect::to(URL::build('/panel/store/products'));
            }

            if (Input::exists()) {
                $errors = [];

                if (Token::check()) {
                    $validation = Validate::check($_POST, [
                        'prize' => [
                            Validate::REQUIRED => true,
                            Validate::MIN => 1,
                            Validate::MAX => 128
                        ],
                        'winners' => [
                            Validate::REQUIRED => true,
                            Validate::NUMERIC => true
                        ],
                        'entry_interval' => [
                            Validate::REQUIRED => true,
                            Validate::NUMERIC => true
                        ],
                        'entry_period' => [
                            Validate::REQUIRED => true
                        ],
                        'ends' => [
                            Validate::REQUIRED => true
                        ]
                    ]);

                    if ($validation->passed()) {
                        $required_groups = $_POST['required_groups'];
                        $required_integrations = $_POST['required_integrations'];
                        $ends = strtotime($_POST['ends']);

                        DB::getInstance()->update('giveaway', $giveaway->data()->id, [
                            'prize' => Input::get('prize'),
                            'winners' => Input::get('winners'),
                            'entry_interval' => Input::get('entry_interval'),
                            'entry_period' => Input::get('entry_period'),
                            'ends' => $ends,
                            'required_integrations' => json_encode(isset($required_integrations) && is_array($required_integrations) ? $required_integrations : []),
                            'required_groups' => json_encode(isset($required_groups) && is_array($required_groups) ? $required_groups : [])
                        ]);

                        // Update task
                        DB::getInstance()->update('queue', $giveaway->data()->task_id, [
                            'scheduled_for' => $ends
                        ]);

                        // Re-query giveaway
                        $giveaway = new Giveaway($giveaway->data()->id);
                        EventHandler::executeEvent(new GiveawayUpdatedEvent($giveaway));

                        Session::flash('giveaway_success', $giveaway_language->get('general', 'giveaway_updated_successfully'));
                        Redirect::to(URL::build('/panel/giveaway'));
                    } else {
                        // Validation errors
                        $errors = $validation->errors();
                    }
                } else {
                    // Invalid token
                    $errors[] = $language->get('general', 'invalid_token');
                }
            }
            
            $groups_list = [];
            $selected_groups = json_decode($giveaway->data()->required_groups, true) ?? [];
            $groups = DB::getInstance()->query('SELECT * FROM nl2_groups')->results();
            foreach ($groups as $item) {
                $groups_list[] = [
                    'id' => $item->id,
                    'name' => Output::getClean($item->name),
                    'selected' => in_array($item->id, $selected_groups)
                ];
            }

            $integrations_list = [];
            $selected_integrations = json_decode($giveaway->data()->required_integrations, true) ?? [];
            foreach (Integrations::getInstance()->getEnabledIntegrations() as $item) {
                $integrations_list[] = [
                    'id' => $item->data()->id,
                    'name' => Output::getClean($item->getName()),
                    'selected' => in_array($item->data()->id, $selected_integrations)
                ];
            }

            $smarty->assign([
                'GIVEAWAY_TITLE' => $giveaway_language->get('general', 'editing_giveaway'),
                'BACK' => $language->get('general', 'back'),
                'BACK_LINK' => URL::build('/panel/giveaway'),
                'PRIZE' => $giveaway_language->get('general', 'giveaway_prize'),
                'PRIZE_VALUE' => Output::getClean($giveaway->data()->prize),
                'WINNERS' => $giveaway_language->get('general', 'giveaway_winners'),
                'WINNERS_VALUE' => Output::getClean($giveaway->data()->winners),
                'ENDS' => $giveaway_language->get('general', 'giveaway_ends'),
                'ENDS_VALUE' => date('Y-m-d\TH:i', Output::getClean($giveaway->data()->ends)),
                'ENDS_MIN' => date('Y-m-d\TH:i', Output::getClean($giveaway->data()->created)),
                'ENTRY_INTERVAL' => Output::getClean($giveaway->data()->entry_interval),
                'ENTRY_PERIOD' => Output::getClean($giveaway->data()->entry_period),
                'INTEGRATIONS_LIST' => $integrations_list,
                'GROUPS_LIST' => $groups_list,
            ]);

            $template_file = 'giveaway/giveaway_form.tpl';
        break;
        case 'delete';
            // Delete giveaway
        break;
        default:
            Redirect::to(URL::build('/panel/giveaway'));
        break;
    }
}

/*$prevmonth = date("m/Y",strtotime("first day of previous month"));

$total_points = $queries->getWhere('giveaway', array('date', '=', $prevmonth));
$points = DB::getInstance()->query("SELECT user_id, count(*) as points FROM nl2_giveaway WHERE date = ? GROUP BY user_id ORDER BY count(*) DESC", array($prevmonth))->results();
$giveaway_entries = array();
if(count($points)) {
    foreach($points as $point){
        $user_query = $queries->getWhere('users', array('id', '=', Output::getClean($point->user_id)));

        $giveaway_entries[] = array(
            'username' => Output::getClean($user_query[0]->username),
            'points' => Output::getClean($point->points)
        );
    }
}

$winner = rand (0, count($total_points));
$winner_user = $queries->getWhere('users', array('id', '=', Output::getClean($total_points[$winner - 1]->user_id)));
*/
// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if (Session::exists('giveaway_success'))
    $success = Session::flash('giveaway_success');

if (isset($success))
    $smarty->assign([
        'SUCCESS' => $success,
        'SUCCESS_TITLE' => $language->get('general', 'success')
    ]);

if (isset($errors) && count($errors))
    $smarty->assign([
        'ERRORS' => $errors,
        'ERRORS_TITLE' => $language->get('general', 'error')
    ]);

$smarty->assign([
    'PARENT_PAGE' => PARENT_PAGE,
    'DASHBOARD' => $language->get('admin', 'dashboard'),
    'GIVEAWAY' => $giveaway_language->get('general', 'giveaway'),
    'PAGE' => PANEL_PAGE,
    'TOKEN' => Token::get(),
    'SUBMIT' => $language->get('general', 'submit')
]);

$template->onPageLoad();

require(ROOT_PATH . '/core/templates/panel_navbar.php');

// Display template
$template->displayTemplate($template_file, $smarty);