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
                        <h5 style="display:inline">{$GIVEAWAY_TITLE}</h5>
                        <div class="float-md-right">
                            <a href="{$BACK_LINK}" class="btn btn-warning">{$BACK}</a>
                        </div>
                        <hr>

                        <!-- Success and Error Alerts -->
                        {include file='includes/alerts.tpl'}

                        <form role="form" action="" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="inputPrize">{$PRIZE}</label>
                                        <input type="text" name="prize" class="form-control" id="inputPrize" placeholder="{$PRIZE}" value="{$PRIZE_VALUE}">
                                    </div>
                                    <div class="form-group">
                                        <label for="inputEnds">{$ENDS}</label>
                                        <input type="datetime-local" id="inputEnds" name="ends" value="{$ENDS_VALUE}" min="{$ENDS_MIN}" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label for="inputRequiredIntegrations">Require User Integrations</label> <span
                                                class="badge badge-info"><i class="fas fa-question-circle"
                                                        data-container="body" data-toggle="popover"
                                                        data-placement="top" title="Info"
                                                        data-content="User will be required to have these integrations to enter the giveaway"></i></span>
                                        <select name="required_integrations[]" id="inputRequiredIntegrations" class="form-control" multiple>
                                            {foreach from=$INTEGRATIONS_LIST item=integration}
                                                <option value="{$integration.id}"{if $integration.selected} selected{/if}>{$integration.name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="inputWinners">{$WINNERS}</label>
                                        <input type="number" name="winners" class="form-control" id="inputWinners" value="{$WINNERS_VALUE}">
                                    </div>
                                    <div class="form-group">
                                        <label for="inputEntryInterval">User can enter every</label>
                                        <div class="input-group">
                                          <input type="number" name="entry_interval" class="form-control" id="inputEntryInterval" value="{$ENTRY_INTERVAL}">
                                          <select name="entry_period" class="form-control">
                                            <option value="no_period" {if $ENTRY_PERIOD == 'no_period'} selected{/if}>No Period</option>
                                            <option value="hour" {if $ENTRY_PERIOD == 'hour'} selected{/if}>Hour</option>
                                            <option value="day" {if $ENTRY_PERIOD == 'day'} selected{/if}>Day</option>
                                            <option value="week" {if $ENTRY_PERIOD == 'week'} selected{/if}>Week</option>
                                            <option value="month" {if $ENTRY_PERIOD == 'month'} selected{/if}>Month</option>
                                            <option value="year" {if $ENTRY_PERIOD == 'year'} selected{/if}>Year</option>
                                          </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="inputRequiredGroups">Require Groups</label> <span
                                                class="badge badge-info"><i class="fas fa-question-circle"
                                                        data-container="body" data-toggle="popover"
                                                        data-placement="top" title="Info"
                                                        data-content="User will be required to have these groups to enter the giveaway"></i></span>
                                        <select name="required_groups[]" id="inputRequiredGroups" class="form-control" multiple>
                                            {foreach from=$GROUPS_LIST item=group}
                                                <option value="{$group.id}"{if $group.selected} selected{/if}>{$group.name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="hidden" name="token" value="{$TOKEN}">
                                <input type="submit" class="btn btn-primary" value="{$SUBMIT}">
                            </div>
                        </form>

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

<script type="text/javascript">
    $(document).ready(() => {
        $('#inputRequiredGroups').select2({ placeholder: "No groups selected" });
    })
    $(document).ready(() => {
        $('#inputRequiredIntegrations').select2({ placeholder: "No integrations selected" });
    })
</script>

</body>
</html>