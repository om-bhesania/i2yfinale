<?php if (!defined('APP_VERSION')) die("Yo, what's up?");  ?>
<!DOCTYPE html>
<html lang="<?= ACTIVE_LANG ?>">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0"/>
        <meta name="theme-color" content="#fff">

        <meta name="description" content="<?= site_settings("site_description") ?>">
        <meta name="keywords" content="<?= site_settings("site_keywords") ?>">

        <link rel="icon" href="<?= site_settings("logomark") ? site_settings("logomark") : APPURL."/assets/img/logomark.png" ?>" type="image/x-icon">
        <link rel="shortcut icon" href="<?= site_settings("logomark") ? site_settings("logomark") : APPURL."/assets/img/logomark.png" ?>" type="image/x-icon">

        <link rel="stylesheet" type="text/css" href="<?= APPURL."/assets/css/plugins.css?v=".VERSION ?>">
        <link rel="stylesheet" type="text/css" href="<?= APPURL."/assets/css/core.css?v=".VERSION ?>">
        <link rel="stylesheet" type="text/css" href="<?= PLUGINS_URL."/".$idname."/assets/css/core.css?v=41".VERSION ?>">
        <link rel="stylesheet" type="text/css" href="<?= PLUGINS_URL."/".$idname."/assets/bootstrap/bootstrap-modal.css?v=".VERSION ?>">

        <title><?= __("Activity Log") ?></title>
    </head>

    <body class="<?= $AuthUser->get("preferences.dark_mode_status") ? $AuthUser->get("preferences.dark_mode_status") == "1" ? "darkside" : "" : "" ?>">
        <?php 
            $Nav = new stdClass;
            $Nav->activeMenu = $idname;
            require_once(APPPATH.'/views/fragments/navigation.fragment.php');
        ?>

        <?php 
            $TopBar = new stdClass;
            $TopBar->title = __("Activity Log");
            $TopBar->btn = false;
            require_once(APPPATH.'/views/fragments/topbar.fragment.php'); 
        ?>

        <?php require_once(__DIR__.'/fragments/log.fragment.php'); ?>
        
        <script type="text/javascript" src="<?= APPURL."/assets/js/plugins.js?v=".VERSION ?>"></script>
        <?php require_once(APPPATH.'/inc/js-locale.inc.php'); ?>
        <script type="text/javascript" src="<?= APPURL."/assets/js/core.js?v=".VERSION ?>"></script>
        <script type="text/javascript" src="<?= PLUGINS_URL."/".$idname."/assets/bootstrap/bootstrap.min.js?v=41".VERSION ?>"></script>
        <script type="text/javascript" src="<?= PLUGINS_URL."/".$idname."/assets/js/core.js?v=".VERSION ?>"></script>
        <script type="text/javascript" charset="utf-8">
            $(function(){
                RepostPro.Index();
                RepostPro.DeleteRepost();
                RepostPro.UpdateStatistics();
                RepostPro.LogActions();

                $(document).ajaxComplete(function(event, xhr, settings) {
                    var rx = new RegExp("(repost-pro\/[0-9]+(\/)log?)$");
                    if (rx.test(settings.url)) {
                        NextPost.Tooltip();
                        // Update selected schedule speed
                        var active_schedule = $(".aside-list-item.active");
                        $.ajax({
                            url: active_schedule.data("url"),
                            type: 'POST',
                            dataType: 'jsonp',
                            data: {
                                action: "update_data",
                                id: active_schedule.data("id")
                            },
                            success: function(resp) {
                                if (resp.result == 1 && resp.speed != 0) {
                                    active_schedule.find("span.speed.speed-value").replaceWith("<span class='speed-value'>" + resp.speed + "</span>");
                                }
                                if (resp.result == 1) {
                                    if (resp.is_active != 0) {
                                        active_schedule.find("span.status").replaceWith("<span class='status color-green'><span class='mdi mdi-circle mr-2'></span>" + __('Active') + "</span>");
                                    } else {
                                        active_schedule.find("span.status").replaceWith("<span class='status'><span class='mdi mdi-circle-outline mr-2'></span>" + __('Deactive') + "</span>");
                                    }
                                }
                            }
                        });
                    }
                });
            })
        </script>

        <!-- GOOGLE ANALYTICS -->
        <?php require_once(APPPATH.'/views/fragments/google-analytics.fragment.php'); ?>
    </body>
</html>