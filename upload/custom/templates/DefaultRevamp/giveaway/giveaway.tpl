{include file='header.tpl'}
{include file='navbar.tpl'}

<div class="ui stackable grid">
    <div class="ui centered row">

        {if count($WIDGETS_LEFT)}
            <div class="ui six wide tablet four wide computer column">
                {foreach from=$WIDGETS_LEFT item=widget}
                    {$widget}
                {/foreach}
            </div>
        {/if}

        <div class="ui {if count($WIDGETS_LEFT) && count($WIDGETS_RIGHT) }four wide tablet eight wide computer{elseif count($WIDGETS_LEFT) || count($WIDGETS_RIGHT)}ten wide tablet twelve wide computer{else}sixteen wide{/if} column">
            <div class="ui segment">
                <h2 class="ui header">{$GIVEAWAY}</h2>
                <div class="ui divider"></div>

                {if isset($SUCCESS)}
                    <div class="ui success icon message">
                        <i class="check icon"></i>
                        <div class="content">
                            <div class="header">{$SUCCESS_TITLE}</div>
                            {$SUCCESS}
                        </div>
                    </div>
                {/if}

                {if isset($ERRORS)}
                    <div class="ui error icon message">
                        <i class="x icon"></i>
                        <div class="content">
                            <ul class="list">
                                {foreach from=$ERRORS item=error}
                                    <li>{$error}</li>
                                {/foreach}
                            </ul>
                        </div>
                    </div>
                {/if}

                {$CONTENT}

                {if isset($GIVEAWAY_LIST)}
                    {foreach from=$GIVEAWAY_LIST item=giveaway}
                        <div class="ui segments">
                            <h4 class="ui segment header"><a href="{$giveaway.view_link}">{$giveaway.prize}</a> {if $giveaway.active}<span class="ui green label right floated">{$ACTIVE}</span>{else}<span class="ui red label right floated">{$ENDED}</span>{/if}</h4>
                            <div class="ui segment">
                                {$giveaway.ends_x}<br/>
                                {$giveaway.entries_x}<br/>
                                {$giveaway.your_entries_x}

                                {if count($giveaway.winners) }
                                    <br/>{$WINNERS}: {foreach from=$giveaway.winners item=winner}<a href="{$winner.profile}" style="{$winner.style}}" data-poload="{$USER_INFO_URL}{$winner.user_id}">{$winner.username}</a> {/foreach}
                                {/if}

                                {if $giveaway.active}
                                    <br/><br/>

                                    {if isset($LOGGED_IN_USER)}
                                        {if $giveaway.can_enter == 1}
                                            <form class="ui form" action="" method="post" id="form-giveaway">
                                                <div class="field">
                                                    <input type="hidden" name="token" value="{$TOKEN}">
                                                    <input type="hidden" name="giveaway" value="{$giveaway.id}">
                                                    <input type="submit" class="fluid mini ui small primary button" value="{$ENTER_GIVEAWAY}">
                                                </div>
                                            </form>
                                        {else}
                                            <button class="fluid mini ui small primary button" disabled>{$giveaway.enter_disabled_button}</button>
                                        {/if}
                                    {else}
                                        <a class="fluid mini ui small primary button" href="{$LOGIN_LINK}" role="button">{$LOGIN_TO_ENTER}</a>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/foreach}
                {else}
                    {$NO_GIVEAWAY}
                {/if}

            </div>
	    </div>

        {if count($WIDGETS_RIGHT)}
            <div class="ui six wide tablet four wide computer column">
                {foreach from=$WIDGETS_RIGHT item=widget}
                    {$widget}
                {/foreach}
            </div>
        {/if}

    </div>
</div>


{include file='footer.tpl'}