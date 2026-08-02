<?php
/*
 * Made by Partydragen
 * https://partydragen.com
 *
 * Giveaway module - Local giveaway detail page
 */

const PAGE = 'giveaway';
$page_title = $giveaway_language->get('general', 'giveaway');
require_once(ROOT_PATH . '/core/templates/frontend_init.php');

$route_parts = explode('/', $route);
$route_value = (string)end($route_parts);
$slug_parts = explode('-', $route_value);

if (!isset($slug_parts[0]) || !ctype_digit($slug_parts[0])) {
    require_once(ROOT_PATH . '/404.php');
    die();
}

$giveaway_id = (int)$slug_parts[0];
$giveaway = GiveawayService::find($giveaway_id);

if ($giveaway === null) {
    Redirect::to(URL::build('/giveaway'));
}

$view_url = URL::build('/giveaway/view/' . $giveaway_id);
$captcha = false;

if (Input::exists()) {
    if (!Token::check(Input::get('token'))) {
        $errors[] = $language->get('general', 'invalid_token');
    } elseif (!$user->isLoggedIn()) {
        Session::flash('giveaway_error', $giveaway_language->get('general', 'login_to_enter'));
        Redirect::to($view_url);
    } else {
        $captcha_passed = !$captcha || CaptchaBase::getActiveProvider()->validateToken($_POST);

        if (!$captcha_passed) {
            $errors[] = $language->get('user', 'invalid_recaptcha');
        } else {
            $result = GiveawayService::enter(
                $giveaway_id,
                $user,
                HttpUtils::getRemoteAddress()
            );

            Session::flash(
                $result->isSuccess() ? 'giveaway_success' : 'giveaway_error',
                GiveawayCatalogue::getMessage($result, $giveaway_language, true)
            );

            Redirect::to($view_url);
        }
    }
}

$serialized = GiveawayService::serialize(
    $giveaway,
    $user,
    HttpUtils::getRemoteAddress()
);
$template_giveaway = GiveawayCatalogue::toTemplate($serialized, $giveaway_language);

$entries_list = [];
$entries = DB::getInstance()->query(
    'SELECT user_id, COUNT(*) AS entries
     FROM nl2_giveaway_entries
     WHERE giveaway_id = ?
     GROUP BY user_id
     ORDER BY COUNT(*) DESC',
    [$giveaway_id]
);

foreach ($entries->results() as $entry) {
    $entry_user = new User($entry->user_id);
    if (!$entry_user->exists()) {
        continue;
    }

    $entries_list[] = [
        'username' => $entry_user->getDisplayname(),
        'user_id' => (int)$entry_user->data()->id,
        'user_profile' => $entry_user->getProfileURL(),
        'user_style' => $entry_user->getGroupStyle(),
        'entries' => (int)$entry->entries,
    ];
}

if ($captcha) {
    $template->getEngine()->addVariable(
        'CAPTCHA',
        CaptchaBase::getActiveProvider()->getHtml()
    );

    $template->addJSFiles([
        CaptchaBase::getActiveProvider()->getJavascriptSource() => [],
    ]);

    $submit_script = CaptchaBase::getActiveProvider()->getJavascriptSubmit('form-validate');
    if ($submit_script) {
        $template->addJSScript('
            $("#form-validate").submit(function(e) {
                e.preventDefault();
                ' . $submit_script . '
            });
        ');
    }
}

if (!$user->isLoggedIn()) {
    $template->getEngine()->addVariables([
        'LOGIN_LINK' => URL::build('/login'),
        'LOGIN_TO_ENTER' => $giveaway_language->get('general', 'login_to_enter'),
    ]);
}

$template->getEngine()->addVariables([
    'GIVEAWAY' => $giveaway_language->get('general', 'giveaway'),
    'TOKEN' => Token::get(),
    'CONTENT' => '',
    'ENTRIES_LIST' => $entries_list,
    'GIVEAWAY_ID' => $template_giveaway['id'],
    'PRIZE' => $giveaway_language->get('general', 'prize'),
    'PRIZE_VALUE' => Output::getClean($serialized['prize']),
    'ACTIVE' => $template_giveaway['active'],
    'ENDS_X' => $template_giveaway['ends_x'],
    'ALL_ENTRIES' => $giveaway_language->get('general', 'all_entries'),
    'ENTRIES_X' => $template_giveaway['entries_x'],
    'YOUR_ENTRIES_X' => $template_giveaway['your_entries_x'],
    'WINNERS' => $template_giveaway['winners'],
    'CAN_ENTER' => $template_giveaway['can_enter'],
    'TIME_REMAINING' => $template_giveaway['time_remaining'],
    'ENTER_DISABLED_BUTTON' => $template_giveaway['enter_disabled_button'],
    'ENTER_GIVEAWAY' => $giveaway_language->get('general', 'enter_giveaway'),
]);

// Load modules + template
Module::loadPage($user, $pages, $cache, $smarty, [$navigation, $cc_nav, $staffcp_nav], $widgets, $template);

if (Session::exists('giveaway_success')) {
    $success = Session::flash('giveaway_success');
}

if (Session::exists('giveaway_error')) {
    $errors[] = Session::flash('giveaway_error');
}

if (isset($success)) {
    $template->getEngine()->addVariables([
        'SUCCESS' => $success,
        'SUCCESS_TITLE' => $language->get('general', 'success'),
    ]);
}

if (isset($errors) && count($errors)) {
    $template->getEngine()->addVariables([
        'ERRORS' => $errors,
        'ERRORS_TITLE' => $language->get('general', 'error'),
    ]);
}

$template->onPageLoad();
$template->getEngine()->addVariable('WIDGETS_LEFT', $widgets->getWidgets('left'));
$template->getEngine()->addVariable('WIDGETS_RIGHT', $widgets->getWidgets('right'));

require(ROOT_PATH . '/core/templates/navbar.php');
require(ROOT_PATH . '/core/templates/footer.php');

$template->displayTemplate('giveaway/view_giveaway');
