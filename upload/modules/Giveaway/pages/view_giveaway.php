<?php
/*
 *  Made by Partydragen
 *  https://partydragen.com
 *
 *  Giveaway module - Giveaway page
 */

// Always define page name
const PAGE = 'giveaway';
$page_title = $giveaway_language->get('general', 'giveaway');
require_once(ROOT_PATH . '/core/templates/frontend_init.php');

// Get giveaway ID
$giveaway_id = explode('/', $route);
$giveaway_id = $giveaway_id[count($giveaway_id) - 1];

if (!strlen($giveaway_id)) {
    require_once(ROOT_PATH . '/404.php');
    die();
}

$giveaway_id = explode('-', $giveaway_id);
if (!is_numeric($giveaway_id[0])) {
    require_once(ROOT_PATH . '/404.php');
    die();
}
$giveaway_id = $giveaway_id[0];

$giveaway = new Giveaway($giveaway_id);
if (!$giveaway->exists()) {
    Redirect::to(URL::build('/giveaway'));
}

$captcha = false;

// Handle input
if (Input::exists()) {
    if (Token::check(Input::get('token'))) {
        // Is user logged in?
        if (!$user->isLoggedIn()) {
            Session::flash('giveaway_error', $giveaway_language->get('general', 'login_to_enter'));
            Redirect::to(URL::build('/giveaway'));
        }

        if (!$giveaway->isActive()) {
            Session::flash('giveaway_error', $giveaway_language->get('general', 'giveaway_already_ended'));
            Redirect::to(URL::build('/giveaway'));
        }

        foreach ($giveaway->getRequiredIntegrations() as $integration) {
            $integrationUser = $user->getIntegration($integration->getName());
            if ($integrationUser == null || $integrationUser->data()->username == null || $integrationUser->data()->identifier == null) {
                Session::flash('giveaway_error', $giveaway_language->get('general', 'giveaway_requires_integration', [
                    'integration' => Output::getClean($integration->getName()),
                    'linkStart' => '<a href="' . URL::build('/user/connections') . '">',
                    'linkEnd' => '</a>'
                ]));

                Redirect::to(URL::build('/giveaway'));
            }
        }

        $required_groups = json_decode($giveaway->data()->required_groups, true) ?? [];
        if (count($required_groups)) {
            $user_groups = $user->getAllGroupIds();
            foreach ($required_groups as $item) {
                if(!array_key_exists($item, $user_groups)) {
                    $group = DB::getInstance()->query('SELECT name FROM nl2_groups WHERE id = ?', [$item])->first();

                    Session::flash('giveaway_error', $giveaway_language->get('general', 'giveaway_requires_groups', [
                        'group' => Output::getClean($group->name ?? 'Unknown')
                    ]));

                    Redirect::to(URL::build('/giveaway'));
                }
            }
        }

        if ($captcha) {
            $captcha_passed = CaptchaBase::getActiveProvider()->validateToken($_POST);
        } else {
            $captcha_passed = true;
        }

        if ($captcha_passed) {
            // Has user id or ip already entered?
            $already_entered = DB::getInstance()->query('SELECT entered FROM nl2_giveaway_entries WHERE giveaway_id = ? AND ip = ? OR giveaway_id = ? AND user_id = ? ORDER BY entered DESC LIMIT 1', [$giveaway->data()->id, HttpUtils::getRemoteAddress(), $giveaway->data()->id, $user->data()->id]);
            if ($already_entered->count()) {
                $already_entered = $already_entered->first();

                if ($already_entered->entered >= strtotime('-' . $giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) {
                    $has_entered = true;
                    $time_left = round(($already_entered->entered - strtotime('-' . $giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) / 60);
                }
            }

            if (!isset($has_entered)) {
                DB::getInstance()->insert("giveaway_entries", [
                    'giveaway_id' => $giveaway->data()->id,
                    'user_id' => $user->data()->id,
                    'entered' => date("U"),
                    'ip' => HttpUtils::getRemoteAddress(),
                ]);

                // Re-query giveaway
                EventHandler::executeEvent(new UserEntryGiveawayEvent($giveaway, $user));
                EventHandler::executeEvent(new GiveawayUpdatedEvent($giveaway));

                Session::flash('giveaway_success', $giveaway_language->get('general', 'successfully_entered_giveaway'));
                Redirect::to(URL::build('/giveaway/view/' . $giveaway->data()->id));
            } else {
                Session::flash('giveaway_error', $giveaway_language->get('general', 'already_entered_giveaway'));
                Redirect::to(URL::build('/giveaway/view/' . $giveaway->data()->id));
            }
        } else {
            // Invalid recaptcha
            $errors[] = $language->get('user', 'invalid_recaptcha');
        }
    } else {
        // Invalid token
        $errors[] = $language->get('general', 'invalid_token');
    }
}

// Can user enter?
$can_enter = false;
$time_remaining = 0;
if ($giveaway->isActive() && $user->isLoggedIn()) {
    // Has user already entered last 24 hours?
    $last_entered = DB::getInstance()->query('SELECT entered FROM nl2_giveaway_entries WHERE giveaway_id = ? AND user_id = ? ORDER BY entered DESC LIMIT 1', [$giveaway->data()->id, $user->data()->id]);
    if ($last_entered->count()){
        $last_entered = $last_entered->first();

        if ($giveaway->data()->entry_period != 'no_period' && $giveaway->data()->entry_interval != 0) {
            if ($last_entered->entered < strtotime('-' . $giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) {
                $can_enter = true;
            }

            $time_remaining = round(($last_entered->entered - strtotime('-' . $giveaway->data()->entry_interval . ' ' . $giveaway->data()->entry_period)) / 60);
        }
    } else {
        $can_enter = true;
    }
}

// Get winners
$winners = [];
if (!$giveaway->isActive()) {
    $winners_query = DB::getInstance()->query('SELECT user_id FROM nl2_giveaway_winners WHERE giveaway_id = ?', [$giveaway->data()->id]);
    if ($winners_query->count()) {
        foreach ($winners_query->results() as $winner) {
            $winner_user = new User($winner->user_id);
            if ($winner_user->exists()) {
                $winners[] = [
                    'user_id' => $winner_user->data()->id,
                    'username' => $winner_user->getDisplayname(),
                    'profile' => $winner_user->getProfileURL(),
                    'style' => $winner_user->getGroupStyle(),
                    'avatar' => $winner_user->getAvatar()
                ];
            }
        }
    }
}

// List all entries
$entries_list = [];
$entries = DB::getInstance()->query('SELECT user_id, count(*) as entries FROM nl2_giveaway_entries WHERE giveaway_id = ? GROUP BY user_id ORDER BY count(*) DESC', [$giveaway->data()->id]);
foreach ($entries->results() as $entry) {
    $entry_user = new User($entry->user_id);
    if (!$entry_user->exists()) {
        continue;
    }

    $entries_list[] = [
        'username' => $entry_user->getDisplayname(),
        'user_id' => $entry_user->data()->id,
        'user_profile' => $entry_user->getProfileURL(),
        'user_style' => $entry_user->getGroupStyle(),
        'entries' => $entry->entries
    ];
}

// Smarty variables
if ($captcha) {
    $smarty->assign('CAPTCHA', CaptchaBase::getActiveProvider()->getHtml());
    $template->addJSFiles(array(CaptchaBase::getActiveProvider()->getJavascriptSource() => array()));

    $submitScript = CaptchaBase::getActiveProvider()->getJavascriptSubmit('form-validate');
    if ($submitScript) {
        $template->addJSScript('
            $("#form-validate").submit(function(e) {
                e.preventDefault();
                ' . $submitScript . '
            });
        ');
    }
}

// Is user logged in?
if (!$user->isLoggedIn()) {
    $smarty->assign([
        'LOGIN_LINK' => URL::build('/login'),
        'LOGIN_TO_ENTER' => $giveaway_language->get('general', 'login_to_enter'),
    ]);
}

$smarty->assign([
    'GIVEAWAY' => $giveaway_language->get('general', 'giveaway'),
    'TOKEN' => Token::get(),
    'CONTENT' => '',
    'ENTRIES_LIST' => $entries_list,
    'GIVEAWAY_ID' => Output::getClean($giveaway->data()->id),
    'PRIZE' => $giveaway_language->get('general', 'prize'),
    'PRIZE_VALUE' => Output::getClean($giveaway->data()->prize),
    'ACTIVE' => $giveaway->isActive(),
    'ENDS_X' => $giveaway_language->get('general', 'ends_x', [
        'ends' => date(DATE_FORMAT, $giveaway->data()->ends)
    ]),
    'ALL_ENTRIES' => $giveaway_language->get('general', 'all_entries'),
    'ENTRIES_X' => $giveaway_language->get('general', 'entries_x', [
        'entries' => DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ?', [$giveaway->data()->id])->first()->c
    ]),
    'YOUR_ENTRIES_X' => $giveaway_language->get('general', 'your_entries_x', [
        'entries' => $user->isLoggedIn() ? DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ? AND user_id = ?', [$giveaway->data()->id, $user->data()->id])->first()->c : 0
    ]),
    'WINNERS' => $winners,
    'CAN_ENTER' => $can_enter,
    'TIME_REMAINING' => $time_remaining,
    'ENTER_DISABLED_BUTTON' => $time_remaining != null ? $giveaway_language->get('general', 'enter_again_in', [
        'minutes' => Output::getClean($time_remaining)
    ]) : $giveaway_language->get('general', 'already_entered_giveaway'),
    'ENTER_GIVEAWAY' => $giveaway_language->get('general', 'enter_giveaway'),
]);

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if(Session::exists('giveaway_success')){
    $success = Session::flash('giveaway_success');
}

if(Session::exists('giveaway_error')){
    $errors[] = Session::flash('giveaway_error');
}

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

$template->onPageLoad();

$smarty->assign('WIDGETS_LEFT', $widgets->getWidgets('left'));
$smarty->assign('WIDGETS_RIGHT', $widgets->getWidgets('right'));

require(ROOT_PATH . '/core/templates/navbar.php');
require(ROOT_PATH . '/core/templates/footer.php');

// Display template
$template->displayTemplate('giveaway/view_giveaway.tpl', $smarty);