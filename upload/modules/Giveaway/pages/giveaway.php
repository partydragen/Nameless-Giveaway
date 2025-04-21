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

$captcha = false;

if (Settings::get('mcc_giveaway', '1', 'Giveaway')) {
    $cache->setCache('giveaways');
    if ($cache->isCached('giveaway_list')) {
        $giveaway_list = $cache->retrieve('giveaway_list');
    } else {
        $request = HttpClient::get('https://partydragen.com/index.php?route=/api/v2/giveaways&active=true');
        if (!$request->hasError()) {
            $result = $request->json();

            $referral_code = Settings::get('referral_code', null, 'Minecraft Community');

            $giveaway_list = [];
            foreach ($result->giveaways as $giveaway) {
                $giveaway_list[] = [
                    'id' => Output::getClean('mcc-' . $giveaway->id),
                    'prize' => '[Partydragen] ' . Output::getClean($giveaway->prize),
                    'active' => $giveaway->active,
                    'ends_x' => $giveaway_language->get('general', 'ends_x', [
                        'ends' => date(DATE_FORMAT, $giveaway->ends)
                    ]),
                    'entries_x' => $giveaway_language->get('general', 'entries_x', [
                        'entries' => $giveaway->entries
                    ]),
                    'your_entries_x' => $giveaway_language->get('general', 'your_entries_x', [
                        'entries' => 0
                    ]),
                    'winners' => $giveaway->winners_list,
                    'can_enter' => true,
                    'time_remaining' => 0,
                    'enter_disabled_button' => $giveaway_language->get('general', 'already_entered_giveaway'),
                    'view_link' => 'https://partydragen.com/giveaway/view/' . $giveaway->id  . (!empty($referral_code) ? '?ref=' . $referral_code : '')
                ];
            }
        }

        $cache->store('giveaway_list', $giveaway_list, 180);
    }
}

// Handle input
if (Input::exists()) {
    if (Token::check(Input::get('token'))) {
        // Check if this is a Minecraft Community giveaway
        if (!is_numeric(Input::get('giveaway'))) {
            $referral_code = Settings::get('referral_code', null, 'Minecraft Community');

            Redirect::to('https://partydragen.com/giveaway' . (!empty($referral_code) ? '?ref=' . $referral_code : ''));
        }

        // Is user logged in?
        if (!$user->isLoggedIn()) {
            Session::flash('giveaway_error', $giveaway_language->get('general', 'login_to_enter'));
            Redirect::to(URL::build('/giveaway'));
        }

        $giveaway = new Giveaway(Input::get('giveaway'));
        if (!$giveaway->exists()) {
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

        // Execute event with allow modules to interact with it
        $event = new UserPreEntryGiveawayEvent(
            $giveaway,
            $user,
        );
        EventHandler::executeEvent($event);

        // Check if the event returned any errors
        if ($event->isCancelled()) {
            Session::flash('giveaway_error', $event->getCancelledReason());
            Redirect::to(URL::build('/giveaway'));
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
                Redirect::to(URL::build('/giveaway'));
            } else {
                Session::flash('giveaway_error', $giveaway_language->get('general', 'already_entered_giveaway'));
                Redirect::to(URL::build('/giveaway'));
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

// Get current active giveaways
$giveaway_query = DB::getInstance()->query('SELECT * FROM nl2_giveaway ORDER BY id DESC');
if ($giveaway_query->count() || isset($giveaway_list)) {
    $giveaway_list = $giveaway_list ?? [];

    foreach ($giveaway_query->results() as $giveaway) {
        $giveaway = new Giveaway(null, null, $giveaway);

        // Is giveaway active?
        $active = $giveaway->isActive();

        // Can user enter?
        $can_enter = false;
        $time_remaining = 0;
        if ($active && $user->isLoggedIn()) {
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
        if (!$active) {
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

        $giveaway_list[] = [
            'id' => Output::getClean($giveaway->data()->id),
            'prize' => Output::getClean($giveaway->data()->prize),
            'active' => $active,
            'ends_x' => $giveaway_language->get('general', 'ends_x', [
                'ends' => date(DATE_FORMAT, $giveaway->data()->ends)
            ]),
            'entries_x' => $giveaway_language->get('general', 'entries_x', [
                'entries' => DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ?', [$giveaway->data()->id])->first()->c
            ]),
            'your_entries_x' => $giveaway_language->get('general', 'your_entries_x', [
                'entries' => $user->isLoggedIn() ? DB::getInstance()->query('SELECT COUNT(*) AS c FROM nl2_giveaway_entries WHERE giveaway_id = ? AND user_id = ?', [$giveaway->data()->id, $user->data()->id])->first()->c : 0
            ]),
            'winners' => $winners,
            'can_enter' => $can_enter,
            'time_remaining' => $time_remaining,
            'enter_disabled_button' => $time_remaining != null ? $giveaway_language->get('general', 'enter_again_in', [
                'minutes' => Output::getClean($time_remaining)
            ]) : $giveaway_language->get('general', 'already_entered_giveaway'),
            'view_link' => URL::build('/giveaway/view/' . $giveaway->data()->id)
        ];
    }

    $template->getEngine()->addVariables([
        'GIVEAWAY_LIST' => $giveaway_list,
        'ENDS' => $giveaway_language->get('general', 'ends'),
        'ENTRIES' => $giveaway_language->get('general', 'entries'),
        'YOUR_ENTRIES' => $giveaway_language->get('general', 'your_entries'),
        'WINNERS' => $giveaway_language->get('general', 'winners'),
        'ACTIVE' => $giveaway_language->get('general', 'active'),
        'ENDED' => $giveaway_language->get('general', 'ended'),
        'ENTER_GIVEAWAY' => $giveaway_language->get('general', 'enter_giveaway'),
    ]);
} else {
    $template->getEngine()->addVariable('NO_GIVEAWAY', $giveaway_language->get('general', 'no_giveaways'));
}

// Smarty variables
if ($captcha) {
    $template->getEngine()->addVariable('CAPTCHA', CaptchaBase::getActiveProvider()->getHtml());
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
    $template->getEngine()->addVariables([
        'LOGIN_LINK' => URL::build('/login'),
        'LOGIN_TO_ENTER' => $giveaway_language->get('general', 'login_to_enter'),
    ]);
}

$template->getEngine()->addVariables([
    'GIVEAWAY' => $giveaway_language->get('general', 'giveaway'),
    'TOKEN' => Token::get(),
    'CONTENT' => ''
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
    $template->getEngine()->addVariables([
        'SUCCESS' => $success,
        'SUCCESS_TITLE' => $language->get('general', 'success')
    ]);

if (isset($errors) && count($errors))
    $template->getEngine()->addVariables([
        'ERRORS' => $errors,
        'ERRORS_TITLE' => $language->get('general', 'error')
    ]);

$template->onPageLoad();

$template->getEngine()->addVariable('WIDGETS_LEFT', $widgets->getWidgets('left'));
$template->getEngine()->addVariable('WIDGETS_RIGHT', $widgets->getWidgets('right'));

require(ROOT_PATH . '/core/templates/navbar.php');
require(ROOT_PATH . '/core/templates/footer.php');

// Display template
$template->displayTemplate('giveaway/giveaway');