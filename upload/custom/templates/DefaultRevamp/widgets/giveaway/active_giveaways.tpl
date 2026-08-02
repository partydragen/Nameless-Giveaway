{if isset($ACTIVE_GIVEAWAYS) && count($ACTIVE_GIVEAWAYS)}
<div class="ui fluid card" id=" active-giveaways-widget">
    <div class="content">
                    <h4 class="ui header">{$LATEST_REWARDED_TROPHIES}</h4>
            <div class="description">
    <div class="ui relaxed list active-giveaways-widget">
        {foreach from=$ACTIVE_GIVEAWAYS item=giveaway}
            <div class="item">
                <div class="content">
                    <a class="header" href="{$giveaway.view_link}">
                        {$giveaway.prize}
                    </a>

                    <div class="description">
                        {$giveaway.ends_x}<br>
                        {$giveaway.entries_x}
                    </div>

                    <a class="ui fluid small primary button"
                       href="{$GIVEAWAY_PAGE_LINK}">
                        {$ENTER_GIVEAWAY}
                    </a>
                </div>
            </div>
        {/foreach}
    </div>

    </div>
    </div>
</div>
{/if}