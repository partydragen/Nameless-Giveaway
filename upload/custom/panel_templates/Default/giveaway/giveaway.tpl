{include file='header.tpl'}

<body id="page-top">

<!-- Wrapper -->
<div id="wrapper">

    <!-- Sidebar -->
    {include file='sidebar.tpl'}

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main content -->
        <div id="content">

            <!-- Topbar -->
            {include file='navbar.tpl'}

            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">{$GIVEAWAY}</h1>
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{$PANEL_INDEX}">{$DASHBOARD}</a></li>
                        <li class="breadcrumb-item active">{$GIVEAWAY}</li>
                    </ol>
                </div>

                <!-- Update Notification -->
                {include file='includes/update.tpl'}

                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h3 style="display:inline;">{$GIVEAWAY}</h3>
                        <span class="float-md-right"><a href="{$NEW_GIVEAWAY_LINK}" class="btn btn-primary">{$NEW_GIVEAWAY}</a></span>
                        <hr>

                        <!-- Success and Error Alerts -->
                        {include file='includes/alerts.tpl'}

                        {if isset($GIVEAWAY_LIST)}
                            <div class="table-responsive">
                                <table class="table table-borderless table-striped">
                                    <thead>
                                        <tr>
                                            <th>{$ID}</th>
                                            <th>{$PRIZE}</th>
                                            <th>{$WINNERS}</th>
                                            <th>{$ENTRIES}</th>
                                            <th>{$ENDS}</th>
                                            <th</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach from=$GIVEAWAY_LIST item=giveaway}
                                        <tr>
                                            <td><a href="{$giveaway.edit_link}">{$giveaway.id}</a></td>
                                            <td>{$giveaway.prize}</td>
                                            <td>{$giveaway.winners}</td>
                                            <td>{$giveaway.entries}</td>
                                            <td>{$giveaway.ends} {if $giveaway.active}<span class="badge badge-success">Active</span>{else}<span class="badge badge-danger">Ended</span>{/if}</td>
                                            <td>
                                                <div class="float-md-right">
                                                    <a href="{$giveaway.edit_link}" class="btn btn-warning btn-sm"><i class="fas fa-edit fa-fw"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        {else}
                            {$NO_GIVEAWAYS}
                        {/if}

                        <form action="" method="post">
                            <div class="form-group custom-control custom-switch">
                                <input id="inputShowMinecraftCommunityGiveaway" name="mcc_giveaway" type="checkbox" class="custom-control-input"{if $MINECRAFT_COMMUNITY_VALUE eq 1} checked{/if} />
                                <label class="custom-control-label" for="inputShowMinecraftCommunityGiveaway">Include active giveaways from <a href="https://mccommunity.net/giveaway/" target="_blank">Minecraft Community</a> in your giveaway page?<br />Earn Money by your registered users and also earn 10% of the prize if they win!</label>
                            </div>
                            <div class="form-group">
                                <input type="hidden" name="token" value="{$TOKEN}">
                                <input type="hidden" name="type" value="settings">
                                <input type="submit" class="btn btn-primary" value="{$SUBMIT}">
                            </div>
                        </form>

                        <center>
                            <p>Giveaway Module by <a href="https://partydragen.com/" target="_blank">Partydragen</a> and my <a href="https://partydragen.com/supporters/" target="_blank">Sponsors</a></br>
                                <a class="ml-1" href="https://partydragen.com/suggestions/" target="_blank" data-toggle="tooltip"
                                   data-placement="top" title="You can submit suggestions here"><i class="fa-solid fa-thumbs-up text-warning"></i></a>
                                <a class="ml-1" href="https://discord.gg/TtH6tpp" target="_blank" data-toggle="tooltip"
                                   data-placement="top" title="Discord"><i class="fab fa-discord fa-fw text-discord"></i></a>
                                <a class="ml-1" href="https://partydragen.com/" target="_blank" data-toggle="tooltip"
                                   data-placement="top" title="Website"><i class="fas fa-globe fa-fw text-primary"></i></a>
                                <a class="ml-1" href="https://www.patreon.com/partydragen" target="_blank" data-toggle="tooltip"
                                   data-placement="top" title="Support the development on Patreon"><i class="fas fa-heart fa-fw text-danger"></i></a>
                            </p>
                        </center>
                    </div>
                </div>

                <!-- Spacing -->
                <div style="height:1rem;"></div>

                <!-- End Page Content -->
            </div>

            <!-- End Main Content -->
        </div>

        {include file='footer.tpl'}

        <!-- End Content Wrapper -->
    </div>

    <!-- End Wrapper -->
</div>

{include file='scripts.tpl'}

</body>
</html>