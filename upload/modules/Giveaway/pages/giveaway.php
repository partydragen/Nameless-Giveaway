<?php
/*
 * Made by Partydragen
 * https://partydragen.com
 *
 * Giveaway module - Unified giveaway catalogue
 */

const PAGE = 'giveaway';
$page_title = $giveaway_language->get('general', 'giveaway');
require_once(ROOT_PATH . '/core/templates/frontend_init.php');

$captcha = false;

if (Input::exists()) {
    if (!Token::check(Input::get('token'))) {
        $errors[] = $language->get('general', 'invalid_token');

    } elseif (!$user->isLoggedIn()) {
        Session::flash('giveaway_error', $giveaway_language->get('general', 'login_to_enter'));
        Redirect::to(URL::build('/giveaway'));

    } else {
        $captcha_passed = !$captcha || CaptchaBase::getActiveProvider()->validateToken($_POST);

        if (!$captcha_passed) {
            $errors[] = $language->get('user', 'invalid_recaptcha');
        } else {
            $result = GiveawayCatalogue::enter(
                (string)Input::get('giveaway'),
                $user,
                HttpUtils::getRemoteAddress()
            );

            if ($result->getCode() === 'external_entry_required' && isset($result->getMeta()['redirect'])) {
                Redirect::to($result->getMeta()['redirect']);
            }

            Session::flash(
                $result->isSuccess() ? 'giveaway_success' : 'giveaway_error',
                GiveawayCatalogue::getMessage($result, $giveaway_language, true)
            );

            Redirect::to(URL::build('/giveaway'));
        }
    }
}

$giveaways = GiveawayCatalogue::getGiveaways(
    $user,
    HttpUtils::getRemoteAddress()
);

if ($giveaways) {
    $template->getEngine()->addVariables([
        'GIVEAWAY_LIST' => GiveawayCatalogue::toTemplateList($giveaways, $giveaway_language),
        'ENDS' => $giveaway_language->get('general', 'ends'),
        'ENTRIES' => $giveaway_language->get('general', 'entries'),
        'YOUR_ENTRIES' => $giveaway_language->get('general', 'your_entries'),
        'WINNERS' => $giveaway_language->get('general', 'winners'),
        'ACTIVE' => $giveaway_language->get('general', 'active'),
        'ENDED' => $giveaway_language->get('general', 'ended'),
        'ENTER_GIVEAWAY' => $giveaway_language->get('general', 'enter_giveaway'),
    ]);
} else {
    $template->getEngine()->addVariable(
        'NO_GIVEAWAY',
        $giveaway_language->get('general', 'no_giveaways')
    );
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

$template->displayTemplate('giveaway/giveaway');
