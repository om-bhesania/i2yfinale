<?php if (!defined('APP_VERSION')) die("Yo, what's up?"); ?>

<div class="skeleton skeleton--full">
    <div class="clearfix">
        <aside class="skeleton-aside hide-on-medium-and-down">
            <?php 
                $Settings = Plugins\RepostPro\settings();
                $cf_worker = $Settings->get("data.cf_worker") ? $Settings->get("data.cf_worker") : null;

                $form_action = APPURL . "/e/" . $idname;
                include PLUGINS_PATH . "/" . $idname ."/views/fragments/aside-search-form.fragment.php";
            ?> 

            <div class="js-search-results">
                <div class="aside-list js-loadmore-content" data-loadmore-id="1"></div>
            </div>

            <div class="loadmore pt-20 mb-20 none">
                <a class="fluid button button--light-outline js-loadmore-btn js-autoloadmore-btn" data-loadmore-id="1" href="<?= APPURL."/e/".$idname."?aid=".$Account->get("id")."&ref=log" ?>">
                    <span class="icon sli sli-refresh"></span>
                    <?= __("Load More") ?>
                </a>
            </div>
        </aside>

        <section class="skeleton-content">
            <div class="section-header back-button-wh none">
                <a href="<?= APPURL."/e/".$idname."/" ?>">
            	    <span class="mdi mdi-reply"></span><?= __("Back") ?>
                </a>
            </div>

            <div class="section-header clearfix">
                <h2 class="section-title">
                    <?= "@" . htmlchars($Account->get("username")) ?>
                    <?php if ($Account->get("login_required")): ?>
                        <small class="color-danger ml-15">
                            <span class="mdi mdi-information"></span>    
                            <?= __("Re-login required!") ?>
                        </small>
                    <?php endif ?>
                </h2>
            </div>

            <div class="arp-tab-heads pb-15 clearfix">
                <a href="<?= APPURL."/e/".$idname."/".$Account->get("id") ?>"><?= __("Settings") ?></a>
                <a href="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>" class="active"><?= __("Activity Log") ?></a>
            </div>

            <?php if ($ActivityLog->getTotalCount() > 0): ?>
                <div class="arp-log-list js-loadmore-content" data-loadmore-id="2">
                    <?php if ($ActivityLog->getPage() == 1): ?>
                        <div class="repost-pro-last-analytics-update">
                            <?php 
                                $last_analytics_timestamp = $Schedule->get("data.last_analytics_update");
                                if (!empty($last_analytics_timestamp)):
                                    $last_analytics_update = new \Moment\Moment(date("Y-m-d H:i:s", $last_analytics_timestamp), date_default_timezone_get());
                                    $last_analytics_update->setTimezone($AuthUser->get("preferences.timezone")); 
                                    $last_analytics_time = $last_analytics_update->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA (d.m.Y)" : "H:i (d.m.Y)");
                            ?>
                                <div class="last-analytics-update-time mb-10"><?= __("Last statistics update at %s", '<span class="last-analytics-update-timestamp">' . $last_analytics_time . '</span>')  ?></div>             
                            <?php endif ?>
                            <a href="javascript:void(0)" 
                                class="js-repost-pro-statistics-update repost-pro-bulk-btn mdi mdi-refresh button small button--light-outline mr-5 mb-5" 
                                data-action-url="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"
                                data-account-id="<?= $Account->get("id") ?>"><?= __("Refresh statistics") ?></a>
                            <a href="javascript:void(0)" 
                                class="js-repost-pro-bulk-select repost-pro-bulk-btn mdi mdi-checkbox-marked-circle-outline button small button--light-outline mr-5 mb-5" 
                                data-action-url="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"
                                data-account-id="<?= $Account->get("id") ?>"><?= __("Select All") ?></a>
                            <a href="javascript:void(0)" 
                                class="js-repost-pro-bulk-unselect repost-pro-bulk-btn mdi mdi-close-circle-outline button small button--light-outline mr-5 mb-5" 
                                data-action-url="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"
                                data-account-id="<?= $Account->get("id") ?>"><?= __("Unselect All") ?></a>
                            <a href="javascript:void(0)" 
                                class="js-repost-pro-bulk-delete repost-pro-bulk-btn mdi mdi-delete button small button--light-outline mr-5 mb-5" 
                                data-action-url="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"
                                data-account-id="<?= $Account->get("id") ?>"><?= __("Delete log(s)") ?></a>
                        </div>
                    <?php endif ?>

                    <?php if ($ActivityLog->getPage() == 1 && $Schedule->get("is_active")): ?>
                        <?php 
                            $nextdate = new \Moment\Moment($Schedule->get("schedule_date"), date_default_timezone_get());
                            $nextdate->setTimezone($AuthUser->get("preferences.timezone"));

                            $diff = $nextdate->fromNow(); 
                            $nexttime = $nextdate->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA (d.m.Y)" : "H:i (d.m.Y)");
                        ?>
                        <?php if ($diff->getDirection() == "future"): ?>
                            <div class="arp-next-schedule">
                                <?= __("Next repost will be at %s", $nexttime) ?>
                            </div>
                        <?php elseif (abs($diff->getSeconds()) < 60): ?>
                            <div class="arp-next-schedule">
                                <?= __("Repost scheduled...") ?>
                            </div>
                        <?php else: ?>
                            <div class="arp-next-schedule">
                                <?= __("Repost in progress...") ?>
                            </div>
                        <?php endif ?>
                    <?php endif ?>

                    <?php foreach ($Logs as $l): ?>
                        <div class="arp-log-list-item <?= $l->get("status") ?><?= $l->get("data.log_selected") ? " log-selected" : "" ?>" data-repost-id="<?= $l->get("id") ?>">
                            <div class="options repost-pro-log-action">
                                <a href="javascript:void(0)" class="mdi<?= $l->get("data.log_selected") ? " mdi-checkbox-marked-circle-outline" : " mdi-checkbox-blank-circle-outline" ?> js-repost-pro-select-log" data-log-id="<?= $l->get("id") ?>" data-url="<?= APPURL."/e/".$idname."/".$Account->get("id")."/log" ?>"></a>
                            </div>
                            <div class="clearfix">
                                <span class="circle">
                                    <?php $media_type = $l->get("data.grabbed.media_type"); ?>
                                    <?php if ($l->get("status") == "success" && $media_type != 2): ?>
                                        <?php $img = $l->get("data.grabbed.media_thumb"); ?>
                                        <span class="img lazy" data-bg="<?= $img ? ($cf_worker ? $cf_worker . "/" . htmlchars($img) : APPURL."/e/".$idname."/".$Account->get("id")."/cors/?media_url=" . htmlchars($img)) : "" ?>"></span>
                                    <?php elseif ($l->get("status") == "success"): ?>
                                        <span class="text video-type"></span>
                                    <?php else: ?>
                                        <span class="text log-notice"></span>    
                                    <?php endif ?>
                                </span>

                                <div class="inner clearfix">
                                    <?php 
                                        $date = new \Moment\Moment($l->get("date"), date_default_timezone_get());
                                        $date->setTimezone($AuthUser->get("preferences.timezone"));

                                        $fulldate = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                  . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                    ?>

                                    <div class="action">
                                        <?php if ($l->get("status") == "success"): ?>
                                            <?php
                                                $media_type = $l->get("data.grabbed.media_type");
                                                $product_type = $l->get("data.grabbed.product_type");
                                                if ($media_type == 1) {
                                                    $type_label = __("photo");
                                                } else if ($media_type == 2 && $product_type == "clips") {
                                                    $type_label = __("reel");
                                                } else if ($media_type == 2) {
                                                    $type_label = __("video");
                                                } else if ($media_type == 8) {
                                                    $type_label = __("album");
                                                } else {
                                                    $type_label = __("media");
                                                }

                                                $username = "<a href='https://www.instagram.com/".htmlchars($l->get("data.grabbed.user.username"))."' target='_blank'>".htmlchars($l->get("data.grabbed.user.username"))."</a>";
                                                $type_label = "<a href='https://www.instagram.com/p/".htmlchars($l->get("data.grabbed.media_code"))."' target='_blank'>".$type_label."</a>";

                                                echo __("Reposted {username}'s {post}", [
                                                    "{username}" => $username,
                                                    "{post}" => $type_label 
                                                ]);
                                            ?>
                                            <span class="date" title="<?= $fulldate ?>"><?= $date->fromNow()->getRelative() ?></span>
                                        <?php else: ?>
                                            <?php if ($l->get("data.error.msg")): ?>
                                                <div class="error-msg">
                                                    <?= __($l->get("data.error.msg")) ?>
                                                    <span class="date"><?= $date->fromNow()->getRelative() ?></span>    
                                                </div>
                                            <?php endif ?>
                                            <?php if ($l->get("data.error.details")): ?>
                                                <div class="error-details"><?= __($l->get("data.error.details")) ?></div>
                                            <?php endif ?>
                                        <?php endif ?>
                                    </div>

                                    <div class="meta">
                                        <?php if ($l->get("data.trigger")): ?>
                                            <?php $trigger = $l->get("data.trigger"); ?>
                                            <?php if ($trigger->type == "hashtag"): ?>
                                                <a class="meta mr-10" href="<?= "https://www.instagram.com/explore/tags/".htmlchars($trigger->value) ?>" target="_blank">
                                                    <span class="icon mdi mdi-pound"></span>
                                                    <?= htmlchars($trigger->value) ?>
                                                </a>
                                            <?php elseif ($trigger->type == "hashtag_reels"): ?>
                                                <a class="meta mr-10" href="<?= "https://www.instagram.com/explore/tags/".htmlchars($trigger->value) ?>" target="_blank">
                                                    <span class="icon mdi mdi-pound"></span>
                                                    <?= htmlchars($trigger->value) . " " . __("(reels)") ?>
                                                </a>
                                            <?php elseif ($trigger->type == "location"): ?>
                                                <a class="meta mr-10" href="<?= "https://www.instagram.com/explore/locations/".htmlchars($trigger->id) ?>" target="_blank">
                                                    <span class="icon mdi mdi-map-marker"></span>
                                                    <?= htmlchars($trigger->value) ?>
                                                </a>
                                            <?php elseif ($trigger->type == "people"): ?>
                                                <a class="meta mr-10" href="<?= "https://www.instagram.com/".htmlchars($trigger->value) ?>" target="_blank">
                                                    <span class="icon mdi mdi-instagram"></span>
                                                    <?= htmlchars($trigger->value) ?>
                                                </a>
                                            <?php elseif ($trigger->type == "people_reels"): ?>
                                                <a class="meta mr-10" href="<?= "https://www.instagram.com/".htmlchars($trigger->value) ?>" target="_blank">
                                                    <span class="icon mdi mdi-instagram"></span>
                                                    <?= htmlchars($trigger->value) . " " . __("(reels)") ?>
                                                </a>
                                            <?php elseif ($trigger->type == "collection"): ?>
                                                <span class="meta mr-10">
                                                    <span class="icon mdi mdi-bookmark"></span>
                                                    <?= htmlchars($trigger->value) ?>
                                                </span>
                                            <?php elseif ($trigger->type == "music"): ?>
                                                <a class="meta mr-10" href="<?= "https://www.instagram.com/reels/audio/".htmlchars($trigger->id) ?>" target="_blank">
                                                    <span class="icon mdi mdi-instagram"></span>
                                                    <?= htmlchars($trigger->value) . " " . __("(reels)") ?>
                                                </a>
                                            <?php endif ?>
                                        <?php endif ?>
                                        
                                        <?php if ($l->get("status_caption") == "success-1"): ?>
                                            <span class="meta mr-10">
                                                <a class="meta" href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.reposted.media_code")) ?>" target="_blank">
                                                <span class="mdi mdi-checkbox-marked-circle-outline color-success"></span>
                                                <?= __("Caption") ?>
                                                </a>
                                            </span>
                                        <?php elseif ($l->get("status_caption") == "success-2"): ?>
                                            <span class="meta mr-10">
                                                <a class="meta" href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.reposted.media_code")) ?>" target="_blank">
                                                    <span class="mdi mdi-checkbox-marked-circle-outline color-success">
                                                    </span><?= __("Caption re-added") ?>
                                                </a>
                                            </span>
                                        <?php elseif ($l->get("status_caption") == "in-action"): ?>
                                            <span class="meta mr-10">
                                                <span class="mdi mdi-timelapse color-mid"></span><?= __("Caption") ?></span>
                                        <?php elseif ($l->get("status_caption") == "error"): ?>
                                            <span class="meta mr-10">
                                                <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                <span class="tooltip tippy" 
                                                    data-position="top"
                                                    data-size="small"
                                                    title="<?= __("Empty caption detected.") . " " . __("Error response") . ": " . htmlchars($l->get("data.caption_edit.msg")) ?>">
                                                    <?= __("Caption") ?>
                                                </span>
                                            </span>
                                        <?php endif ?>
                                        
                                        <?php if ($l->get("status_first_comment") == "success"): ?>
                                            <span class="meta mr-10">
                                                <a class="meta" href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.reposted.media_code")) ?>" target="_blank">
                                                    <span class="mdi mdi-checkbox-marked-circle-outline color-success"></span>
                                                    <?= __("First comment") ?>
                                                </a>
                                            </span>
                                        <?php elseif ($l->get("status_first_comment") == "error"): ?>
                                            <span class="meta mr-10">
                                                <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                <span class="tooltip tippy" 
                                                    data-position="top"
                                                    data-size="small"
                                                    title="<?= __("Empty first comment detected.") . " " . __("Error response") . ": " . htmlchars($l->get("data.first_comment_fail")) ?>">
                                                    <?= __("First comment") ?>
                                                </span>
                                            </span>
                                        <?php endif ?>
                                        
                                        <?php if ($l->get("status_share_to_stories") == "success"): ?>
                                            <span class="meta mr-10">

                                            <?php 
                                                $share_to_stories = $l->get("data.reposted_story.media_pk") ?
                                                htmlchars($Account->get("username"))."/".htmlchars($l->get("data.reposted_story.media_pk")) :
                                                htmlchars($Account->get("username"));
                                            ?>
                                                <a class="meta" href="<?= "https://www.instagram.com/stories/".$share_to_stories ?>" target="_blank">
                                                    <span class="mdi mdi-checkbox-marked-circle-outline color-success"></span>
                                                    <?= __("Stories") ?>
                                                </a>
                                            </span>
                                        <?php elseif ($l->get("status_share_to_stories") == "in-action"): ?>
                                            <span class="meta mr-10">
                                                    <span class="mdi mdi-timelapse color-mid"></span>
                                                    <span class="color-mid">
                                                    <?= __("Stories") ?>
                                                </span>
                                            </span>
                                        <?php elseif ($l->get("status_share_to_stories") == "error"): ?>
                                            <span class="meta mr-10">
                                                <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                <span class="tooltip tippy" 
                                                    data-position="top"
                                                    data-size="small"
                                                    title="<?= __("Story not published.") . " " . __("Error response") . ": " . htmlchars($l->get("data.sts_fail")) ?>">
                                                    <?= __("Stories") ?>
                                                </span>
                                            </span>
                                        <?php endif ?>

                                        <?php 
                                            $xpost_destination = "data.share_to_facebook.user_xposting.xpost_destination";
                                            $destination_id = $l->get($xpost_destination . ".destination_id");
                                            $destination_name = $l->get($xpost_destination . ".destination_name");
                                            $destination_type = $l->get($xpost_destination . ".destination_type");
                                            if (!empty($destination_name) && !empty($destination_id) && !empty($destination_type)):
                                        ?>
                                            <?php if ($l->get("data.share_to_facebook.post_status") == "success"): ?>
                                                <span class="meta mr-10">
                                                    <a class="meta" href="<?= "https://facebook.com/".$destination_id ?>" target="_blank">
                                                        <span class="mdi mdi-checkbox-marked-circle-outline color-success"></span>
                                                        <?= __("Share %s to Facebook (%s)", "post", $destination_name) ?>
                                                    </a>
                                                </span>
                                            <?php elseif ($l->get("data.share_to_facebook.post_status") == "in-action"): ?>
                                                <span class="meta mr-10">
                                                        <span class="mdi mdi-timelapse color-mid"></span>
                                                        <span class="color-mid">
                                                        <?= __("Share %s to Facebook (%s)", "post", $destination_name) ?>
                                                    </span>
                                                </span>
                                            <?php elseif ($l->get("data.share_to_facebook.post_status") == "error"): ?>
                                                <span class="meta mr-10">
                                                    <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                    <span class="tooltip tippy" 
                                                        data-position="top"
                                                        data-size="small"
                                                        title="<?= __("Post not shared.") . " " . __("Error response") . ": " . htmlchars($l->get("data.stf_p_fail")) ?>">
                                                        <?= __("Share %s to Facebook (%s)", "post", $destination_name) ?>
                                                    </span>
                                                </span>
                                            <?php endif ?>
                                        
                                            <?php if ($l->get("data.share_to_facebook.story_status") == "success"): ?>
                                                <span class="meta mr-10">
                                                    <a class="meta" href="<?= "https://facebook.com/".$destination_id ?>" target="_blank">
                                                        <span class="mdi mdi-checkbox-marked-circle-outline color-success"></span>
                                                        <?= __("Share %s to Facebook (%s)", "story", $destination_name) ?>
                                                    </a>
                                                </span>
                                            <?php elseif ($l->get("data.share_to_facebook.story_status") == "in-action"): ?>
                                                <span class="meta mr-10">
                                                        <span class="mdi mdi-timelapse color-mid"></span>
                                                        <span class="color-mid">
                                                        <?= __("Share %s to Facebook (%s)", "story", $destination_name) ?>
                                                    </span>
                                                </span>
                                            <?php elseif ($l->get("data.share_to_facebook.story_status") == "error"): ?>
                                                <span class="meta mr-10">
                                                    <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                    <span class="tooltip tippy" 
                                                        data-position="top"
                                                        data-size="small"
                                                        title="<?= __("Story not shared.") . " " . __("Error response") . ": " . htmlchars($l->get("data.stf_s_fail")) ?>">
                                                        <?= __("Share %s to Facebook (%s)", "story", $destination_name) ?>
                                                    </span>
                                                </span>
                                            <?php endif ?>

                                            <?php if ($l->get("data.share_to_facebook.reel_status") == "success"): ?>
                                                <span class="meta mr-10">
                                                    <a class="meta" href="<?= "https://facebook.com/".$destination_id ?>" target="_blank">
                                                        <span class="mdi mdi-checkbox-marked-circle-outline color-success"></span>
                                                        <?= __("Share %s to Facebook (%s)", "reel", $destination_name) ?>
                                                    </a>
                                                </span>
                                            <?php elseif ($l->get("data.share_to_facebook.reel_status") == "in-action"): ?>
                                                <span class="meta mr-10">
                                                        <span class="mdi mdi-timelapse color-mid"></span>
                                                        <span class="color-mid">
                                                        <?= __("Share %s to Facebook (%s)", "reel", $destination_name) ?>
                                                    </span>
                                                </span>
                                            <?php elseif ($l->get("data.share_to_facebook.reel_status") == "error"): ?>
                                                <span class="meta mr-10">
                                                    <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                    <span class="tooltip tippy" 
                                                        data-position="top"
                                                        data-size="small"
                                                        title="<?= __("Reel not shared.") . " " . __("Error response") . ": " . htmlchars($l->get("data.stf_r_fail")) ?>">
                                                        <?= __("Share %s to Facebook (%s)", "reel", $destination_name) ?>
                                                    </span>
                                                </span>
                                            <?php endif ?>
                                        <?php else: ?>
                                            <?php if ($l->get("data.share_to_facebook.post_status") == "error"): ?>
                                                <span class="meta mr-10">
                                                    <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                    <span class="tooltip tippy" 
                                                        data-position="top"
                                                        data-size="small"
                                                        title="<?= __("Post not shared.") . " " . __("Error response") . ": " . htmlchars($l->get("data.stf_p_fail")) ?>">
                                                        <?= __("Share %s to Facebook", "post") ?>
                                                    </span>
                                                </span>
                                            <?php endif ?>
                                            <?php if ($l->get("data.share_to_facebook.story_status") == "error"): ?>
                                                <span class="meta mr-10">
                                                    <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                    <span class="tooltip tippy" 
                                                        data-position="top"
                                                        data-size="small"
                                                        title="<?= __("Story not shared.") . " " . __("Error response") . ": " . htmlchars($l->get("data.stf_s_fail")) ?>">
                                                        <?= __("Share %s to Facebook", "story") ?>
                                                    </span>
                                                </span>
                                            <?php endif ?>
                                            <?php if ($l->get("data.share_to_facebook.reel_status") == "error"): ?>
                                                <span class="meta mr-10">
                                                    <span class="mdi mdi-close-circle-outline color-danger"></span>
                                                    <span class="tooltip tippy" 
                                                        data-position="top"
                                                        data-size="small"
                                                        title="<?= __("Reel not shared.") . " " . __("Error response") . ": " . htmlchars($l->get("data.stf_r_fail")) ?>">
                                                        <?= __("Share %s to Facebook", "reel") ?>
                                                    </span>
                                                </span>
                                            <?php endif ?>
                                        <?php endif ?>

                                        <?php if ($l->get("status") == "success"): ?>
                                            <?php if ($l->get("is_deleted")): ?>
                                                <span class="meta">
                                                    <span class="color-success mdi mdi-delete"></span>

                                                    <?php 
                                                        $date = new DateTime(
                                                            $l->get("remove_date"),
                                                            new DateTimeZone(date_default_timezone_get()));
                                                        $date->setTimezone(new DateTimeZone($AuthUser->get("preferences.timezone")));
                                                        $date = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                            . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                                    ?>
                                                    <span class="color-success">
                                                        <?= __("Removed at %s", $date) ?>
                                                    </span>
                                                </span>
                                            <?php elseif ($l->get("is_removable")): ?>
                                                <span class="meta">
                                                    <span class="color-mid mdi mdi-delete"></span>

                                                    <?php 
                                                        $date = new DateTime(
                                                            $l->get("remove_scheduled"),
                                                            new DateTimeZone(date_default_timezone_get()));
                                                        $date->setTimezone(new DateTimeZone($AuthUser->get("preferences.timezone")));
                                                        $date = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                            . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                                    ?>
                                                    <?= __("Scheduled to remove at %s", $date) ?>
                                                </span>
                                            <?php elseif ($l->get("data.remove.status") == "error" && $l->get("data.remove.error.msg")): ?>
                                                <span class="meta">
                                                    <span class="color-danger mdi mdi-delete"></span>

                                                    <?php 
                                                        $date = new DateTime(
                                                            $l->get("remove_date"),
                                                            new DateTimeZone(date_default_timezone_get()));
                                                        $date->setTimezone(new DateTimeZone($AuthUser->get("preferences.timezone")));
                                                        $date = $date->format($AuthUser->get("preferences.dateformat")) . " " 
                                                            . $date->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA" : "H:i");
                                                    ?>
                                                    <span class="color-danger"><?= __("Failed to remove at %s", $date) ?></span>
                                                </span>

                                                <?php if ($l->get("data.remove.error.msg")): ?>
                                                    <div class="error-msg mt-10">
                                                        <?= __($l->get("data.remove.error.msg")) ?>
                                                    </div>
                                                <?php endif ?>

                                                <?php if ($l->get("data.remove.error.details")): ?>
                                                    <div class="error-details"><?= __($l->get("data.remove.error.details")) ?></div>
                                                <?php endif ?>
                                            <?php endif ?>
                                        <?php endif ?>
                                    </div>

                                    <?php if ($l->get("data.analytics")): ?>
                                        <div class="mt-20 data-analytics" data-media-code="<?= $l->get("data.reposted.media_code") ?>">
                                            <?php
                                                $views_count = $l->get("data.analytics.views_count") ? $l->get("data.analytics.views_count") : 0; 
                                                $views_state = $views_count ? "" : " none";
                                                $likes_count = $l->get("data.analytics.likes_count") ? $l->get("data.analytics.likes_count") : 0; 
                                                $likes_state = $likes_count ? "" : " none";
                                                $comments_count = $l->get("data.analytics.comments_count") ? $l->get("data.analytics.comments_count") : 0; 
                                                $comments_state = $comments_count ? "" : " none";

                                                $er_post = $l->get("data.analytics.er_post") ? $l->get("data.analytics.er_post") : 0;
                                                $er_post_state = " none";
                                                if ($er_post && $er_post !== "0.00" && $er_post !== "0.01") {
                                                    $er_post_state = "";
                                                }
                                                $er_post_style_1 = $l->get("data.analytics.er_post_is_good") ? " good-er-post" : "";
                                                $er_post_style_2 = $l->get("data.analytics.er_post_is_good") ? " good-er-post" : "";
                                            ?>
                                            <div class="mr-0 mb-5 meta-analytics er-post-box<?= $er_post_style_1 ?><?= $er_post_state ?>">
                                                <span class="repost-pro-logs-er-post<?= $er_post_style_2 ?>"><?= __("ER<sub>post</sub>") . ": " . '<span class="rp-er-post-value">' . $er_post . "</span>" . "%" ?></span>
                                            </div>
                                            <div class="mr-0 mb-5 meta-analytics rp-views-box<?= $views_state ?>">
                                                <span class="repost-pro-logs-views-count"><span class="mdi mdi-video"></span><?= '<span class="rp-views-count">' . number_format($views_count, 0, ',', ' ') . "</span>" . " " . __("views") ?></span>
                                            </div>
                                            <div class="mr-0 mb-5 meta-analytics rp-likes-box<?= $likes_state ?>">
                                                <span class="repost-pro-logs-likes-count"><span class="mdi mdi-heart"></span><?= '<span class="rp-likes-count">' . number_format($likes_count, 0, ',', ' ') . "</span>" . " " . __("likes") ?></span>
                                            </div>
                                            <div class="mr-0 mb-5 meta-analytics rp-comments-box<?= $comments_state ?>">
                                                <span class="repost-pro-logs-comments-count"><span class="mdi mdi-comment"></span><?= '<span class="rp-comments-count">' . number_format($comments_count, 0, ',', ' ') . "</span>" . " " . __("comments") ?></span>
                                            </div>
                                        </div>
                                    <?php endif ?>

                                    <?php if ($l->get("data.debug") && (isset($_SESSION['nprl']) && $_SESSION['nprl'] || $AuthUser->isAdmin())): ?>
                                        <?php
                                            $d_original_media_code = $l->get("data.debug.original_media_code") ? $l->get("data.debug.original_media_code") : "";
                                            $d_action = $l->get("data.debug.action") ? $l->get("data.debug.action") : "";
                                            $d_code = $l->get("data.debug.code") ? $l->get("data.debug.code") : "";
                                            $d_file = $l->get("data.debug.file") ? $l->get("data.debug.file") : "";
                                            $d_line = $l->get("data.debug.line") ? $l->get("data.debug.line") : "";
                                            $d_string = $l->get("data.debug.string") ? $l->get("data.debug.string") : "";
                                        ?>
                                        <div class="meta debug-data">
                                                <?= __('Error details') ?>
                                            <?php if ($d_action): ?>
                                                <?= " · " . __('Action') . ": " . $d_action ?>
                                            <?php endif ?>
                                            <?php if ($d_original_media_code): ?>
                                                <?= " · " . __('Original media code') . ": " . $d_original_media_code ?>
                                            <?php endif ?>
                                            <?php if ($d_file): ?>
                                                <?= "</br>" . __('File') . ": " . $d_file ?>
                                            <?php endif ?>
                                            <?php if ($d_line): ?>
                                                <?= " · " . __('Line') . ": " . $d_line ?>
                                            <?php endif ?>
                                            <?php if ($d_code): ?>
                                                <?= " · " . __('Code') . ": " . $d_code ?>
                                            <?php endif ?>
                                            <?php if ($d_string): ?>
                                                <?= "</br>" . __('Full error trace') . ": " . $d_string ?>
                                            <?php endif ?>
                                        </div>
                                    <?php endif ?>

                                    <div class="buttons clearfix">
                                        <?php if ($l->get("data.grabbed.media_code")): ?>
                                            <a href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.grabbed.media_code")) ?>" class="button small button--light-outline mb-10" target="_blank">
                                                <?= __("View Original") ?>
                                            </a>
                                        <?php endif ?>

                                        <?php if (!$l->get("is_deleted") && $l->get("data.reposted.media_code")): ?>
                                            <a href="<?= "https://www.instagram.com/p/".htmlchars($l->get("data.reposted.media_code")) ?>" class="button small button--light-outline mb-10" target="_blank">
                                                <?= __("View Reposted") ?>
                                            </a>
                                        <?php endif ?>

                                        <?php if (!$l->get("is_deleted") && $l->get("data.reposted.media_code")): ?>
                                            <a href="javascript:void(0)" class="js-delete-repost button small button--light-outline mb-10" 
                                                data-repost-id="<?= $l->get("id") ?>"
                                                data-account-id="<?= $l->get("account_id") ?>">
                                                <?= __("Delete post") ?>
                                            </a>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="arp-amount-of-action">
                    <?= __("Total %s actions", $ActivityLog->getTotalCount()) ?>
                </div>

                <?php if($ActivityLog->getPage() < $ActivityLog->getPageCount()): ?>
                    <div class="loadmore mt-20 mb-20">
                        <?php 
                            $url = parse_url($_SERVER["REQUEST_URI"]);
                            $path = $url["path"];
                            if(isset($url["query"])){
                                $qs = parse_str($url["query"], $qsarray);
                                unset($qsarray["page"]);

                                $url = $path."?".(count($qsarray) > 0 ? http_build_query($qsarray)."&" : "")."page=";
                            }else{
                                $url = $path."?page=";
                            }
                        ?>
                        <a class="fluid button button--light-outline js-loadmore-btn" data-loadmore-id="2" href="<?= $url.($ActivityLog->getPage()+1) ?>">
                            <span class="icon sli sli-refresh"></span>
                            <?= __("Load More") ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p><?= __("Activity log for %s is empty.", 
                    "<a href='https://www.instagram.com/".htmlchars($Account->get("username"))."' target='_blank'>@".htmlchars($Account->get("username"))."</a>") ?></p>
                </div>
            <?php endif ?>
        </section>
    </div>
</div>