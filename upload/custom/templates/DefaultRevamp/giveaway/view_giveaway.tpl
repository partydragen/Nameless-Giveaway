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
                <h2 style="display:inline" class="white">{$GIVEAWAY}</h2>
                <span class="res right floated">
                    {if isset($LOGGED_IN_USER)}
                        {if $CAN_ENTER == 1}
                            <form class="ui form" action="" method="post" id="form-giveaway">
                                <div class="field">
                                    <input type="hidden" name="token" value="{$TOKEN}">
                                    <input type="hidden" name="giveaway" value="{$GIVEAWAY_ID}">
                                    <input type="submit" class="fluid mini ui small primary button" value="{$ENTER_GIVEAWAY}">
                                </div>
                            </form>
                        {else}
                            <button class="fluid mini ui small primary button" disabled>{$ENTER_DISABLED_BUTTON}</button>
                        {/if}
                    {else}
                        <a class="fluid mini ui small primary button" href="{$LOGIN_LINK}" role="button">{$LOGIN_TO_ENTER}</a>
                    {/if}
                </span>
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

                <h5>
                    {$PRIZE}: {$PRIZE_VALUE}</br>
                    {$ENDS_X}<br/>
                    {$YOUR_ENTRIES_X}<br/>
                    {$ENTRIES_X}<br/></br>
                    {$ALL_ENTRIES}:
                    <h5>
                        <table class="ui fixed single line selectable unstackable small padded res table" id="giveaway_entries">
                            <tbody>
                            {foreach from=$ENTRIES_LIST item=entry}
                                <tr>
                                    {if !isset($entry.user_avatar)}
                                        <td><a href="{$entry.user_profile}" style="{$entry.user_style}}" data-poload="{$USER_INFO_URL}{$entry.user_id}">{$entry.username}</a> <span class="right floated">{$entry.entries}</span></td>
                                    {else}
                                        <td>
                                            <div class="ui relaxed list">
                                                <div class="item">
                                                    <img class="ui mini circular image" src="{$entry.user_avatar}" alt="{$entry.username}">
                                                    <div class="content">
                                                        <a href="{$entry.user_profile}" style="{$entry.user_style}}" data-poload="{$USER_INFO_URL}{$entry.user_id}">{$entry.username}</a></br>
                                                    </div>
                                                    <span class="right floated">{$entry.entries}</span>
                                                </div>
                                            </div>
                                        </td>
                                    {/if}
                                </tr
                            {/foreach}
                            </tbody>
                        </table>
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