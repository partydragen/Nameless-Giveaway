<?php

class ActiveGiveawaysWidgets extends WidgetBase {

    private const MAX_GIVEAWAYS = 5;

    private Language $_giveaway_language;
    private Cache $_cache;

    public function __construct(TemplateEngine $engine, Language $giveaway_language, Cache $cache) {
        $this->_name = 'Active Giveaways';
        $this->_description = 'Display the active giveaways';
        $this->_module = 'Giveaway';

        $this->_engine = $engine;
        $this->_giveaway_language = $giveaway_language;
        $this->_cache = $cache;
    }

    public function initialise(): void {
        global $user;

        $this->_cache->setCache('giveaway_module_cache');
        if ($this->_cache->isCached('active_giveaways')) {
            $active_giveaways = $this->_cache->retrieve('active_giveaways');
        } else {
            $active_giveaways = GiveawayCatalogue::getActiveGiveaways(
                $user,
                null,
                self::MAX_GIVEAWAYS
            );

            $this->_cache->store('active_giveaways', $active_giveaways, 600);
        }

        $this->_engine->addVariables([
            'ACTIVE_GIVEAWAYS_TITLE' => $this->_giveaway_language->get('general', 'active_giveaways'),
            'ACTIVE_GIVEAWAYS' => GiveawayCatalogue::toTemplateList(
                $active_giveaways,
                $this->_giveaway_language
            ),
            'GIVEAWAY_PAGE_LINK' => URL::build('/giveaway'),
            'ENTER_GIVEAWAY' => $this->_giveaway_language->get(
                'general',
                'enter_giveaway'
            ),
            'NO_ACTIVE_GIVEAWAYS' => $this->_giveaway_language->get(
                'general',
                'no_giveaways'
            ),
        ]);

        $this->_content = $this->_engine->fetch(
            'widgets/giveaway/active_giveaways'
        );
    }
}