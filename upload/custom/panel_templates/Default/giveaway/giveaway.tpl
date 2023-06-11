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
                        <center><p>Giveaway Module by <a href="https://partydragen.com/" target="_blank">Partydragen</a></br>Support on <a href="https://discord.gg/TtH6tpp" target="_blank">Discord</a></p></center>
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