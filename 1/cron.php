<?php

namespace Plugins\RepostPro;

use LanguageDetection\Language;

// Disable direct access
if (!defined('APP_VERSION'))
    die("Yo, what's up?");


/**
 * Add cron task to repost new posts
 */
function addCronTask()
{
    require_once __DIR__ . "/models/SchedulesModel.php";
    require_once __DIR__ . "/models/LogModel.php";
    require_once PLUGINS_PATH . "/" . IDNAME . "/vendor/autoload.php";

    $multipleCron       = isset($GLOBALS['multipleCron']) ? $GLOBALS['multipleCron'] : null;
    $cronKey            = isset($GLOBALS['multipleCronKey']) ? $GLOBALS['multipleCronKey'] : null;
    $avoidCronOverlap   = isset($GLOBALS['avoidCronOverlap']) ? $GLOBALS['avoidCronOverlap'] : null;
    $tbSchedules        = TABLE_PREFIX . "repost_pro_schedule";
    $maxTimeWaitToRun   = 5;
    $currentTime        = time();

    // Emojione client
    $Emojione = new \Emojione\Client(new \Emojione\Ruleset());

    // Get auto repost schedules
    $Schedules = new SchedulesModel;
    $Schedules->where("is_active", "=", 1)
        ->where("status", ["scheduled"])
        ->where("schedule_date", "<=", date("Y-m-d H:i:s"))
        ->where("end_date", ">=", date("Y-m-d H:i:s"));
    
        if ($multipleCron) {
            $Schedules->where(\DB::raw("RIGHT(" . $tbSchedules . ".account_id, 1) = " . $cronKey));
        }
    
        // if ($avoidCronOverlap) {
        //     $Schedules->where(function ($q) use ($tbSchedules, $currentTime, $maxTimeWaitToRun) {
        //         $time = $currentTime - ($maxTimeWaitToRun * 60);
        //         $q->where($tbSchedules . ".is_running", "=", 0);
        //         $q->orWhereNull($tbSchedules . ".is_running");
        //         $q->orWhere($tbSchedules . ".is_running", "<", $time);
        //     });
        // }

    $Schedules->orderBy("last_action_date", "ASC")
        ->setPageSize(5) // required to prevent server overload
        ->setPage(1)
        ->fetchData();

    if ($Schedules->getTotalCount() < 1) {
        // There is not any active schedule
        return false;
    }

    // Settings
    $settings = namespace\settings();

    // Random delays between actions
    $random_delay = 0;
    if ($settings->get("data.random_delay")) {
        $random_delay = rand(0, 3600); // up to an hour
    }

    // Speeds (action count per day)
    $default_speeds = [
        "very_slow" => 1,
        "slow" => 2,
        "medium" => 3,
        "fast" => 4,
        "very_fast" => 5,
    ];
    $speeds = $settings->get("data.speeds");
    if (empty($speeds)) {
        $speeds = [];
    } else {
        $speeds = json_decode(json_encode($speeds), true);
    }
    $speeds = array_merge($default_speeds, $speeds);


    $as = [__DIR__ . "/models/ScheduleModel.php", __NAMESPACE__ . "\ScheduleModel"];
    // $ids = [];
    // foreach ($Schedules->getDataAs($as) as $sc) {
    //     $ids[] = $sc->get("id");
    // }
    // if ($ids && $avoidCronOverlap) {
    //     \DB::table($tbSchedules)->whereIn('id', $ids)->update(['is_running' => $currentTime]);
    // }

    foreach ($Schedules->getDataAs($as) as $sc) {
        // Double post prevention
        // Update schedule status
        $sc->set("status", "publishing")->save();
    }

    foreach ($Schedules->getDataAs($as) as $sc) {
        // Increase max_execution_time
        set_time_limit(300);

        $Log = new LogModel;
        $Account = \Controller::model("Account", $sc->get("account_id"));
        $User = \Controller::model("User", $sc->get("user_id"));

        // Update defaults data for the post (not save yet)
        $sc->set("status", "failed");// Status will be updated to the scheduled on success or re-schedule

        // Set default values for the log (not save yet)...
        $Log->set("user_id", $User->get("id"))
            ->set("account_id", $Account->get("id"))
            ->set("status", "error");

        // Check the account
        if (!$Account->isAvailable() || $Account->get("login_required")) {
            // Account is either removed (unexected, external factors)
            // Or login reqiured for this account
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();

            // Log data
            $Log->set("data.error.msg", "Activity has been stopped")
                ->set("data.error.details", "Re-login is required for the account.")
                ->save();
            continue;
        }

        // Check the user
        if (!$User->isAvailable() || !$User->get("is_active") || $User->isExpired()) {
            // User is not valid
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();

            // Log data
            $Log->set("data.error.msg", "Activity has been stopped")
                ->set("data.error.details", "User account is either disabled or expired.")
                ->save();
            continue;
        }

        if ($User->get("id") != $Account->get("user_id")) {
            // Unexpected, data modified by external factors
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();
            continue;
        }

        // Check user access to the module
        $user_modules = $User->get("settings.modules");
        if (!is_array($user_modules) || !in_array(IDNAME, $user_modules)) {
            // Module is not accessible to this user
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();

            // Log data
            $Log->set("data.error.msg", "Activity has been stopped")
                ->set("data.error.details", "Module is not accessible to your account.")
                ->save();
            continue;
        }

        // Custom repost time
        $custom_repost_time = @json_decode($sc->get("data.custom_repost_time"));
        $last_repost_time_utc = $sc->get("schedule_date");
        $repost_time_utc = [];
        $repost_time_closest = null;
        $repost_time_closest_regex = $sc->get("data.repost_time_closest_regex");

        $r_json = new \stdClass;
        $regex_advanced_time = "/repost\[$repost_time_closest_regex\]\{(.*)\}/";
        if (preg_match($regex_advanced_time, $custom_repost_time, $matches)) {
            if (isset($matches[1])) {
                $r_json = @json_decode("{" . $matches[1] . "}");
                if (!is_object($r_json)) {
                    // Incorrect repost time pattern configured in task settings
                    // Deactivate schedule
                    $sc->set("is_active", 0)->save();
                    $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();

                    // Log data
                    $Log->set("data.error.msg", __("Incorrect repost time pattern configured in task settings"))
                        ->set("data.error.details", __("Please check the pattern syntax in section %s.", __("Custom time for the repost")))
                        ->save();

                    continue;
                }
            } else {
                // Incorrect repost time pattern configured in task settings
                // Deactivate schedule
                $sc->set("is_active", 0)->save();
                $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();

                // Log data
                $Log->set("data.error.msg", __("Incorrect repost time pattern configured in task settings"))
                    ->set("data.error.details", __("Please check the pattern syntax in section %s.", __("Custom time for the repost")))
                    ->save();

                continue;
            }
        }

        if (!empty($custom_repost_time)) {
            $regex_advanced = "/repost\[(([0-1]?[0-9]|2[0-3])\:[0-5][0-9])\]\{(.*)\}/";
            $regex = "/repost\[(([0-1]?[0-9]|2[0-3])\:[0-5][0-9])\]/";
            if (preg_match_all($regex_advanced, $custom_repost_time, $matches)) {
                if (isset($matches[1])) {
                    $dt = $matches[1];
                    foreach ($dt as $key => $d) {
                        $rt_time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($User->get("preferences.timezone")));
                        $rt_time->setTimezone(new \DateTimeZone("UTC"));
                        $rt_time_utc = strtotime($rt_time->format("Y-m-d H:i:s"));

                        if (time() > $rt_time_utc) {
                            $time = new \DateTime(date("Y-m-d", time() + 86400) . " " . $d . ":00", new \DateTimeZone($User->get("preferences.timezone")));
                        } else {
                            $time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($User->get("preferences.timezone")));
                        }

                        $time->setTimezone(new \DateTimeZone("UTC"));
                        array_push($repost_time_utc, $time->format("Y-m-d H:i:s"));
                    }
                }
            } elseif (preg_match_all($regex, $custom_repost_time, $matches)) {
                if (isset($matches[1])) {
                    $dt = $matches[1];
                    foreach ($dt as $key => $d) {
                        $rt_time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($User->get("preferences.timezone")));
                        $rt_time->setTimezone(new \DateTimeZone("UTC"));
                        $rt_time_utc = strtotime($rt_time->format("Y-m-d H:i:s"));

                        if (time() > $rt_time_utc) {
                            $time = new \DateTime(date("Y-m-d", time() + 86400) . " " . $d . ":00", new \DateTimeZone($User->get("preferences.timezone")));
                        } else {
                            $time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($User->get("preferences.timezone")));
                        }

                        $time->setTimezone(new \DateTimeZone("UTC"));
                        array_push($repost_time_utc, $time->format("Y-m-d H:i:s"));
                    }
                }
            }
            $repost_time_utc = array_unique($repost_time_utc);
            if (!empty($repost_time_utc)) {
                $repost_time_closest = find_closest_date(date("Y-m-d H:i:s", time()), $repost_time_utc, end($repost_time_utc));

                $rtcr_time = new \DateTime($repost_time_closest);
                $rtcr_time->setTimezone(new \DateTimeZone($User->get("preferences.timezone")));
                $repost_time_closest_regex = $rtcr_time->format("H:i");
                $sc->set("data.repost_time_closest_regex", $repost_time_closest_regex);
            }
        }

        if (isset($repost_time_closest)) {
            $next_schedule = date("Y-m-d H:i:s", strtotime($repost_time_closest));
        } else {
            // Calculate next schedule datetime...
            if (isset($speeds[$sc->get("speed")]) && (int)$speeds[$sc->get("speed")] > 0) {
                $speed = (int)$speeds[$sc->get("speed")];
                // Calculate pause time 
                $stop_time = 0;
                if ($sc->get("daily_pause")) {
                    // Convert time to seconds
                    $stop_from = strtotime($sc->get("daily_pause_from"));
                    $stop_to = strtotime($sc->get("daily_pause_to"));
                    if ($stop_to > $stop_from) {
                        $stop_time = $stop_to - $stop_from;
                    } elseif ($stop_to == $stop_from) {
                        $stop_time = 0;
                    } else {
                        $stop_time = $stop_to + 86400 - $stop_from;
                    }
                }
                $hours_ok = (86400 - $stop_time) / $speed;
                $delta = round($hours_ok) + 1 + $random_delay;
            } else {
                $delta = 86400;
            }

            $next_schedule = date("Y-m-d H:i:s", time() + $delta);
            if ($sc->get("daily_pause")) {
                $pause_from = date("Y-m-d") . " " . $sc->get("daily_pause_from");
                $pause_to = date("Y-m-d") . " " . $sc->get("daily_pause_to");
                if ($pause_to <= $pause_from) {
                    // next day
                    $pause_to = date("Y-m-d", time() + 86400) . " " . $sc->get("daily_pause_to");
                }

                if ($next_schedule > $pause_to) {
                    // Today's pause interval is over
                    $pause_from = date("Y-m-d H:i:s", strtotime($pause_from) + 86400);
                    $pause_to = date("Y-m-d H:i:s", strtotime($pause_to) + 86400);
                }

                if ($next_schedule >= $pause_from && $next_schedule <= $pause_to) {
                    $next_schedule = $pause_to;
                }
            }
        }

        $sc->set("schedule_date", $next_schedule)
            ->set("status", "scheduled")
            ->set("last_action_date", date("Y-m-d H:i:s"))
            ->save();

        // Parse targets
        $targets = @json_decode($sc->get("target"));
        if (is_null($targets)) {
            // Unexpected, data modified by external factors or empty targets
            // Deactivate schedule
            $sc->set("is_active", 0)->save();
            $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();
            continue;
        }

        if (count($targets) < 1) {
            // Couldn't find any target for the feed
            // Log data
            $Log->set("data.error.msg", "Couldn't find any target to search for the feed.")
                ->save();
            continue;
        }

        if (isset($r_json->target_type) && isset($r_json->target_value)) {
            // Select target from the defined target collection (advanced)
            if (!in_array($r_json->target_type, ["hashtag", "location", "people", "people_reels", "hashtag_reels", "collection", "music"])) {
                // Unexpected invalid target, 
                // data modified by external factors
                $sc->set("is_active", 0)->save();
                $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();
                continue;
            }

            foreach ($targets as $key_t => $t) {
                if ($t->value == $r_json->target_value || $t->value . " " . __("(reels)") == $r_json->target_value) {
                    if ($t->type == $r_json->target_type) {
                        $target = $t;
                        break;
                    }
                }
            }

            if (!isset($target)) {
                // Target not defined in the task settings
                // Deactivate schedule
                $sc->set("is_active", 0)->save();
                $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();

                // Log data
                $Log->set("data.error.msg", __("Target not defined in the task settings"))
                    ->set("data.error.details", __("Please check the task settings and add target with similar name from the list."))
                    ->save();

                continue;
            } else {
                $target_value = $target->value;
                $target_id = $target->id;
                if ($target->type == "hashtag") {
                    $target_link = '<a href="https://www.instagram.com/explore/tags/' . $target_value . '/" target="_blank">#' . $target_value . '</a>';
                } elseif ($target->type == "hashtag_reels") {
                    $target_link = '<a href="https://www.instagram.com/explore/tags/' . $target_value . '/" target="_blank">#' . $target_value . ' ' . __('(reels)') . '</a>';
                } elseif ($target->type == "location") {
                    $target_link = '<a href="https://www.instagram.com/explore/locations/' . $target_id . '/" target="_blank">' . $target_value . '</a>';
                } elseif ($target->type == "collection") {
                    $target_link = '<a href="https://www.instagram.com/" target="_blank">' . $target_value . ' ' . __('(collection)') . '</a>';
                } elseif ($target->type == "people_reels") {
                    $target_link = '<a href="https://www.instagram.com/' . $target_value . '/reels/" target="_blank">@' . $target_value . ' ' . __('(reels)') . '</a>';
                } elseif ($target->type == "music") {
                    $target_link = '<a href="https://www.instagram.com/reels/audio/' . $target->id . '/" target="_blank">' . $target_value . ' ' . __('(reels)') . '</a>';
                } else {
                    $target_link = '<a href="https://www.instagram.com/' . $target_value . '/" target="_blank">@' . $target_value . '</a>';
                }

                $Log = new LogModel;
                $Log->set("user_id", $User->get("id"))
                    ->set("account_id", $Account->get("id"))
                    ->set("status", "error")
                    ->set("data.error.msg", __("We detected following target in custom repost time pattern: %s", $target_link))
                    ->save();
                $Log = null;
                unset($Log);

                $Log = new LogModel;
                $Log->set("user_id", $User->get("id"))
                    ->set("account_id", $Account->get("id"))
                    ->set("status", "error");
            }
        } else {
            // Select random target from the defined target collection
            $i = rand(0, count($targets) - 1);
            $target = $targets[$i];

            // Bad target checker
            $last_bad_target = $sc->get("data.last_bad_target");
            if ($last_bad_target && (count($targets) > 1)) {
                do {
                    $i = rand(0, count($targets) - 1);
                    $target = $targets[$i];

                    if (empty($target->value)) {
                        break;
                    }
                } while ($last_bad_target == $target->value);
            }

            if (
                empty($target->type) || empty($target->id) ||
                !in_array($target->type, ["hashtag", "location", "people", "people_reels", "hashtag_reels", "collection", "music"])
            ) {
                // Unexpected invalid target, 
                // data modified by external factors
                $sc->set("is_active", 0)->save();
                $User->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();
                continue;
            }
        }

        $Log->set("data.trigger", $target);

        // Login into the account
        try {
            $Instagram = \InstagramController::login($Account);

            // Enable debug mode, if this is required
            if ($sc->get("data.debug_mode")) {
                $Instagram->debug = true;
                \InstagramAPI\Debug::$debugLog = true;
                \InstagramAPI\Debug::$debugLogCliEnabled = false;
                \InstagramAPI\Debug::$debugLogFile = $Account->get("username") . "-repost.log";
            }
        } catch (\InstagramAPI\Exception\NetworkException $e) {

            // Re-schedule action because of /NetworkException 
            $sc->set("schedule_date", date("Y-m-d H:i:s", time() + 30))
                ->save();
            // $Log->set("data.error.msg", "Action re-scheduled in 7 seconds")
            //     ->set("data.error.details", "We couldn't connect to Instagram and automatically re-scheduled action for the account.")
            //     ->set("data.debug.details", "ARP-1-1")
            //     ->set("data.debug.msg", $e->getMessage())
            //     ->save();
            continue;
        } catch (\Exception $e) {
            // Couldn't login into the account

            // Log data
            if ($Account->get("login_required")) {
                $sc->set("status", "failed")
                    ->set("is_active", 0)
                    ->save();
                $Log->set("data.error.msg", "Activity has been stopped");
            } else {
                $Log->set("data.error.msg", "Action re-scheduled");
            }
            $Log->set("data.error.details", $e->getMessage())
                ->save();

            continue;
        }

        // Logged in successfully
        $permissions = $User->get("settings.post_types");
        $video_processing = isVideoExtenstionsLoaded() ? true : false;

        $acceptable_media_types = [];
        if (!empty($permissions->timeline_photo)) {
            $acceptable_media_types[] = "1"; // Photo
        }

        if (!empty($permissions->timeline_video)) {
            $acceptable_media_types[] = "2"; // Video & Reels
        }

        if (!empty($permissions->album_photo) || !empty($permissions->album_video)) {
            $acceptable_media_types[] = "8"; // Album
        }


        // Generate a random rank token.
        $rank_token = $User->get("data." . $Account->get("instagram_id") . ".rank_token");
        if (empty($rank_token)) {
            $rank_token = \InstagramAPI\Signatures::generateUUID();
            $User->set("data." . $Account->get("instagram_id") . ".rank_token", $rank_token)->save();
        }

        // Found feed item to repost
        $feed_item = null;

        // Filtration
        $filtration_profile_picture = $sc->get("data.filtration_profile_picture") ? $sc->get("data.filtration_profile_picture") : false;
        $filtration_min_comments = $sc->get("data.filtration_min_comments") ? $sc->get("data.filtration_min_comments") : 0;
        $filtration_max_comments = $sc->get("data.filtration_max_comments") ? $sc->get("data.filtration_max_comments") : 10000000000000000;
        $filtration_min_likes = $sc->get("data.filtration_min_likes") ? $sc->get("data.filtration_min_likes") : 0;
        $filtration_max_likes = $sc->get("data.filtration_max_likes") ? $sc->get("data.filtration_max_likes") : 10000000000000000;
        $filtration_min_views = $sc->get("data.filtration_min_views") ? $sc->get("data.filtration_min_views") : 0;
        $filtration_max_views = $sc->get("data.filtration_max_views") ? $sc->get("data.filtration_max_views") : 10000000000000000;
        $filtration_min_plays = $sc->get("data.filtration_min_plays") ? $sc->get("data.filtration_min_plays") : 0;
        $filtration_max_plays = $sc->get("data.filtration_max_plays") ? $sc->get("data.filtration_max_plays") : 10000000000000000;
        // $filtration_pagination_limit = $sc->get("data.filtration_pagination_limit") ? $sc->get("data.filtration_pagination_limit") : 50;
        $filtration_pagination_limit = 30;

        // Define languages for filtration
        $languages = $sc->get("data.languages.selected") ? json_decode($sc->get("data.languages.selected")) : [];
        $language_detection_notices = $sc->get("data.language_detection_notices") ? true : false;
        $languages_ids = [];

        if (!empty($languages)) {
            $languages_ids = array_column($languages, 'id');
            if (!empty($languages_ids)) {
                $Log = new LogModel;
                $Log->set("user_id", $User->get("id"))
                    ->set("account_id", $Account->get("id"))
                    ->set("status", "error")
                    ->set("data.error.msg", __("Language filtration enabled"))
                    ->set("data.error.details", __("Selected languages:") . " " . implode(", ", array_column($languages, 'value')) . ".")
                    ->save();
                $Log = null;
                unset($Log);

                $Log = new LogModel;
                $Log->set("user_id", $User->get("id"))
                    ->set("account_id", $Account->get("id"))
                    ->set("status", "error");
            }
        }

        // All supported languages
        $language_names = [
            "ab" => __("Abkhaz"),
            "af" => __("Afrikaans"),
            "am" => __("Amharic"),
            "ar" => __("Arabic"),
            "ay" => __("Aymara"),
            "az-Cyrl" => "🇦🇿 " . __("Azerbaijani, North (Cyrillic)"),
            "az-Latn" => "🇦🇿 " . __("Azerbaijani, North (Latin)"),
            "be" => "🇧🇾 " . __("Belarusan"),
            "bg" => "🇧🇬 " . __("Bulgarian"),
            "bi" => "🇧🇬 " . __("Bislama"),
            "bn" => "🇧🇩 " . __("Bengali"),
            "ln" => __("Lingala"),
            "lo" => "🇱🇦 " . __("Lao"),
            "lt" => "🇱🇹 " . __("Lithuanian"),
            "lv" => "🇱🇻 " . __("Latvian"),
            "mh" => "🇲🇭 " . __("Marshallese"),
            "mn-Cyrl" => "🇲🇲 " . __("Mongolian, Halh (Cyrillic)"),
            "ms-Arab" => "🇲🇾 " . __("Malay (Arabic)"),
            "ms-Latn" => "🇲🇾 " . __("Malay (Latin)"),
            "mt" => "🇲🇹 " . __("Maltese"),
            "nb" => "🇳🇴 " . __("Norwegian, Bokmål"),
            "ng" => __("Ndonga"),
            "nl" => "🇳🇱 " . __("Dutch"),
            "nn" => "🇳🇴 " . __("Norwegian, Nynorsk"),
            "nv" => __("Navajo"),
            "pl" => "🇵🇱 " . __("Polish"),
            "pt-BR" => "🇵🇹 " . __("Portuguese (Brazil)"),
            "pt-PT" => "🇵🇹 " . __("Portuguese (Portugal)"),
            "ro" => "🇷🇴 " . __("Romanian"),
            "ru" => "🇷🇺 " . __("Russian (Русский)"),
            "et" => "🇪🇪 " . __("Estonian"),
            "eu" => __("Basque"),
            "fa" => __("Persian"),
            "fi" => "🇫🇮 " . __("Finnish"),
            "fj" => __("Fijian"),
            "fo" => __("Faroese"),
            "fr" => "🇫🇷 " . __("French"),
            "fy" => __("Frisian"),
            "ga" => "🇮🇪 " . __("Gaelic, Irish"),
            "to" => "🇹🇴 " . __("Tonga"),
            "tr" => "🇹🇷 " . __("Turkish"),
            "tt" => __("Tatar"),
            "ty" => __("Tahitian"),
            "ug-Arab" => __("Uyghur (Arabic)"),
            "ug-Latn" => __("Uyghur (Latin)"),
            "uk" => "🇺🇦 " . __("Ukrainian"),
            "hi" => __("Hindi"),
            "hr" => "🇭🇷 " . __("Croatian"),
            "hu" => "🇭🇺 " . __("Hungarian"),
            "hy" => "🇦🇲 " . __("Armenian"),
            "ia" => __("Interlingua"),
            "id" => "🇮🇩 " . __("Indonesian"),
            "ig" => __("Igbo"),
            "io" => __("Ido"),
            "is" => "🇮🇸 " . __("Icelandic"),
            "om" => __("Afaan Oromo"),
            "it" => "🇮🇹 " . __("Italian"),
            "iu" => __("Inuktitut"),
            "ja" => "🇯🇵 " . __("Japanese"),
            "jv" => __("Javanese"),
            "ka" => "🇬🇪 " . __("Georgian"),
            "km" => __("Khmer"),
            "ko" => "🇰🇷 🇰🇵 " . __("Korean"),
            "kr" => __("Kanuri"),
            "ku" => __("Kurdish"),
            "la" => __("Latin"),
            "lg" => __("Ganda"),
            "bo" => __("Tibetan"),
            "br" => __("Breton"),
            "bs-Cyrl" => "🇧🇦 " . __("Bosnian (Cyrillic)"),
            "bs-Latn" => "🇧🇦 " . __("🇧🇦 Bosnian (Latin)"),
            "ca" => __("Catalan"),
            "ch" => __("Chamorro"),
            "co" => __("Corsican"),
            "cr" => __("Cree"),
            "cs" => "🇨🇿 " . __("Czech"),
            "cy" => __("Welsh"),
            "de" => "🇩🇪 " . __("German"),
            "da" => "🇩🇰 " . __("Danish"),
            "dz" => __("Dzongkha"),
            "el-monoton" => "🇬🇷 " . __("Greek (monotonic)"),
            "el-polyton" => "🇬🇷 " . __("Greek (polytonic)"),
            "en" => "🇬🇧 🇺🇸 " . __("English"),
            "eo" => __("Esperanto"),
            "es" => "🇪🇸 " . __("Spanish"),
            "sa" => __("Sanskrit"),
            "sk" => "🇸🇰 " . __("Slovak"),
            "sl" => "🇸🇮 " . __("Slovene"),
            "so" => "🇸🇴 " . __("Somali"),
            "sq" => "🇦🇱 " . __("Albanian"),
            "ss" => __("Swati"),
            "sv" => "🇸🇪 " . __("Swedish"),
            "ta" => __("Tamil"),
            "th" => "🇹🇭 " . __("Thai"),
            "tl" => __("Tagalog"),
            "gd" => __("Gaelic, Scottish"),
            "gl" => __("Galician"),
            "gn" => __("Guarani"),
            "gu" => __("Gujarati"),
            "ha" => __("Hausa"),
            "he" => __("Hebrew"),
            "ur" => __("Urdu"),
            "uz" => "🇺🇿 " . __("Uzbek"),
            "ve" => __("Venda"),
            "vi" => "🇻🇳 " . __("Vietnamese"),
            "wa" => __("Walloon"),
            "wo" => __("Wolof"),
            "xh" => __("Xhosa"),
            "yo" => __("Yoruba"),
            "zh-Hans" => "🇨🇳 " . __("Chinese, Mandarin (Simplified)"),
            "zh-Hant" => "🇨🇳 " . __("Chinese, Mandarin (Traditional)	"),
            "sw" => __("Swahili/Kiswahili")
        ];

        if (isset($r_json->format)) {
            // Media type filtration (advanced)
            $is_photo_posts = $r_json->format == "photo" ? 1 : 0;
            $is_video_posts = $r_json->format == "video" ? 2 : 0;
            $is_album_posts = $r_json->format == "album" ? 8 : 0;
            $is_reels_posts = $r_json->format == "reel" ? 2 : 0;
        } else {
            // Media type filtration
            $is_photo_posts = $sc->get("data.photo_posts") ? 1 : 0;
            $is_video_posts = $sc->get("data.video_posts") ? 2 : 0;
            $is_album_posts = $sc->get("data.album_posts") ? 8 : 0;
            $is_reels_posts = $sc->get("data.reels_posts") ? 2 : 0;
        }

        $target_value = $target->value;
        $target_id = $target->id;
        if ($target->type == "hashtag") {
            $target_link = '<a href="https://www.instagram.com/explore/tags/' . $target_value . '/" target="_blank">#' . $target_value . '</a>';
        } elseif ($target->type == "hashtag_reels") {
            $target_link = '<a href="https://www.instagram.com/explore/tags/' . $target_value . '/" target="_blank">#' . $target_value . ' ' . __('(reels)') . '</a>';
        } elseif ($target->type == "location") {
            $target_link = '<a href="https://www.instagram.com/explore/locations/' . $target_id . '/" target="_blank">' . $target_value . '</a>';
        } elseif ($target->type == "collection") {
            $target_link = '<a href="https://www.instagram.com/" target="_blank">' . $target_value . ' ' . __('(collection)') . '</a>';
        } elseif ($target->type == "people_reels") {
            $target_link = '<a href="https://www.instagram.com/' . $target_value . '/reels/" target="_blank">@' . $target_value . ' ' . __('(reels)') . '</a>';
        } elseif ($target->type == "music") {
            $target_link = '<a href="https://www.instagram.com/reels/audio/' . $target->id . '/" target="_blank">' . $target_value . ' ' . __('(reels)') . '</a>';
        } else {
            $target_link = '<a href="https://www.instagram.com/' . $target_value . '/" target="_blank">@' . $target_value . '</a>';
        }

        $first_retry = true;
        if ($target->type == "hashtag") {
            $hashtag = str_replace("#", "", trim($target->id));
            if (!$hashtag) {
                continue;
            }
            try {
                $maxId = null;
                $pagination = 0;

                do {
                    set_time_limit(300);

                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }
                    $pagination += 1;
                    if ($pagination >= $filtration_pagination_limit) {
                        break;
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->hashtag->getSection($hashtag, $rank_token, 'top', null, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);

                    $maxId = $feed->getNextMaxId();

                    // Fix for hashtag processing (March, 2020)
                    $section_items = [];
                    $section_items_counter = 0;
                    $sections = $feed->getSections();
                    foreach ($sections as $section) {
                        if ($section->getLayoutType() == "media_grid") {
                            $medias = $section->getLayoutContent()->getMedias();
                            foreach ($medias as $m) {
                                array_push($section_items, $m->getMedia());
                                $section_items_counter++;
                            }
                        }
                    }
                    $feed->setItems($section_items);
                    $feed->setNumResults($section_items_counter);

                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    foreach ($items as $item) {
                        if (!$item->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMediaType() == 8) {
                            $medias = $item->getCarouselMedia();
                            $is_valid = true;
                            foreach ($medias as $media) {
                                if ($media->getMediaType() == 1 && empty($permissions->album_photo)) {
                                    // User has not got a permission for photo albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post photo albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && empty($permissions->album_video)) {
                                    // User has not got a permission for video albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && !$video_processing) {
                                    // Video processing is not possible now,
                                    // FFMPEG is not configured

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }
                            }

                            if (!$is_valid) {
                                // User can not re-post this album post because of the permission 
                                // (or absence of the ffmpeg video processing)
                                continue;
                            }
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_reels_posts && $item->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Usernames Blacklists
                        $ub_filtration = $sc->get("data.ub_filtration") ? $sc->get("data.ub_filtration") : "";
                        if (!empty($ub_filtration)) {
                            $ub_filtration = strtolower($ub_filtration);
                            $ub_filtration = str_replace(" ", "", $ub_filtration);
                            $ub_filtration = str_replace(",", "\n", $ub_filtration);
                            $ub_array = explode("\n", str_replace("\r", "", $ub_filtration));

                            $is_ub = false;
                            foreach ($ub_array as $key_ub => $ub) {
                                if (ltrim($ub, '@') == $item->getUser()->getUsername()) {
                                    $is_ub = true;
                                    break;
                                }
                            }

                            if ($is_ub) {
                                continue;
                            }
                        }

                        // Caption Filtration
                        $caption_filtration = $sc->get("data.caption_filtration") ? $sc->get("data.caption_filtration") : "";
                        if (!empty($caption_filtration) && !empty($item->getCaption())) {
                            if (!empty($item->getCaption()->getText())) {
                                $caption_text = $item->getCaption()->getText();
                                $caption_text = strtolower($caption_text);
                                $caption_filtration = strtolower($caption_filtration);
                                $caption_filtration = str_replace(" ", "", $caption_filtration);
                                $caption_filtration = str_replace(",", "\n", $caption_filtration);
                                $cf_array = explode("\n", str_replace("\r", "", $caption_filtration));

                                $is_cf = false;
                                foreach ($cf_array as $key_cf => $cf) {
                                    if (preg_match("/{$cf}/i", $caption_text)) {
                                        $is_cf = true;
                                        break;
                                    }
                                }

                                if ($is_cf) {
                                    continue;
                                }
                            }
                        }

                        // Language filtration
                        if (!empty($languages) && !empty($languages_ids)) {
                            $ld = new Language();
                            if (!empty($item->getCaption())) {
                                if (!empty($item->getCaption()->getText())) {
                                    $caption_text = $item->getCaption()->getText();
                                    $detected_lang = $ld->detect($caption_text);
                                    if (isset($language_names["$detected_lang"])) {
                                        if ($language_detection_notices) {
                                            $username_exp = $item->getUser()->getUsername();
                                            $username_exp_link = "<a href='https://instagram.com/" . htmlchars($username_exp) . "' target='_blank'>@" .  $username_exp . "</a>";

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error")
                                                ->set("data.error.msg", __("Detected %s profile language is", $username_exp_link) . " " . $language_names["$detected_lang"])
                                                ->set("data.error.details", __("Language filtration at targeting."))
                                                ->save();
                                            $Log = null;
                                            unset($Log);

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error");
                                        }
                                        if (in_array($detected_lang, $languages_ids)) {
                                            // All fine
                                        } else {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }

                        // Filtration
                        if ($filtration_profile_picture) {
                            if ($item->getUser()->getHasAnonymousProfilePicture()) {
                                continue;
                            }
                        }
                        if ($filtration_min_comments == 0 && $filtration_max_comments == 10000000000000000) {
                            // Skip min/max comment filtration
                        } else {
                            $comment_count = $item->getCommentCount();
                            if (($filtration_min_comments <= $comment_count) && ($comment_count <= $filtration_max_comments)) {
                                // Match comment filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($filtration_min_likes == 0 && $filtration_max_likes == 10000000000000000) {
                            // Skip min/max likes filtration
                        } else {
                            $like_count = $item->getLikeCount();
                            if (($filtration_min_likes <= $like_count) && ($like_count <= $filtration_max_likes)) {
                                // Match likes filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($item->getMediaType() == 2 && $item->getProductType() == "clips") {
                            // Check plays count only if this is a video post
                            if ($filtration_min_plays == 0 && $filtration_max_plays == 10000000000000000) {
                                // Skip min/max plays filtration
                            } else {
                                $play_count = $item->getPlayCount();
                                if (($filtration_min_plays <= $play_count) && ($play_count <= $filtration_max_plays)) {
                                    // Match plays filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        } elseif ($item->getMediaType() == 2) {
                            // Check views count only if this is a video post
                            if ($filtration_min_views == 0 && $filtration_max_views == 10000000000000000) {
                                // Skip min/max views filtration
                            } else {
                                $view_count = $item->getViewCount();
                                if (($filtration_min_views <= $view_count) && ($view_count <= $filtration_max_views)) {
                                    // Match views filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        }

                        // Found the feed item to repost
                        $feed_item = $item;
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();

                    // Bad target handler
                    $last_bad_target = $sc->get("data.last_bad_target");
                    if ($last_bad_target == $target_value) {
                        $sc->set("is_active", 0)->save();
                        continue;
                    }
                    $sc->set("data.last_bad_target", $target_value)->save();

                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of hashtag"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of hashtag"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        } else if ($target->type == "hashtag_reels") {
            $hashtag = str_replace("#", "", trim($target->id));
            if (!$hashtag) {
                continue;
            }
            try {
                $maxId = null;
                $pagination = 0;
                $filtration_pagination_limit = 100;

                do {
                    set_time_limit(300);

                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }
                    $pagination += 1;
                    if ($pagination >= $filtration_pagination_limit) {
                        break;
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->reels->getHashtagFeed($hashtag, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);

                    $maxId = $feed->getNextMaxId();
                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    ///////

                    foreach ($items as $item) {
                        if (!$item->getMedia()->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMedia()->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMedia()->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMedia()->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_reels_posts && $item->getMedia()->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getMedia()->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Usernames Blacklists
                        $ub_filtration = $sc->get("data.ub_filtration") ? $sc->get("data.ub_filtration") : "";
                        if (!empty($ub_filtration)) {
                            $ub_filtration = strtolower($ub_filtration);
                            $ub_filtration = str_replace(" ", "", $ub_filtration);
                            $ub_filtration = str_replace(",", "\n", $ub_filtration);
                            $ub_array = explode("\n", str_replace("\r", "", $ub_filtration));

                            $is_ub = false;
                            foreach ($ub_array as $key_ub => $ub) {
                                if (ltrim($ub, '@') == $item->getMedia()->getUser()->getUsername()) {
                                    $is_ub = true;
                                    break;
                                }
                            }

                            if ($is_ub) {
                                continue;
                            }
                        }

                        // Caption Filtration
                        $caption_filtration = $sc->get("data.caption_filtration") ? $sc->get("data.caption_filtration") : "";
                        if (!empty($caption_filtration) && !empty($item->getMedia()->getCaption())) {
                            if (!empty($item->getMedia()->getCaption()->getText())) {
                                $caption_text = $item->getMedia()->getCaption()->getText();
                                $caption_text = strtolower($caption_text);
                                $caption_filtration = strtolower($caption_filtration);
                                $caption_filtration = str_replace(" ", "", $caption_filtration);
                                $caption_filtration = str_replace(",", "\n", $caption_filtration);
                                $cf_array = explode("\n", str_replace("\r", "", $caption_filtration));

                                $is_cf = false;
                                foreach ($cf_array as $key_cf => $cf) {
                                    if (preg_match("/{$cf}/i", $caption_text)) {
                                        $is_cf = true;
                                        break;
                                    }
                                }

                                if ($is_cf) {
                                    continue;
                                }
                            }
                        }

                        // Language filtration
                        if (!empty($languages) && !empty($languages_ids)) {
                            $ld = new Language();
                            if (!empty($item->getMedia()->getCaption())) {
                                if (!empty($item->getMedia()->getCaption()->getText())) {
                                    $caption_text = $item->getMedia()->getCaption()->getText();
                                    $detected_lang = $ld->detect($caption_text);
                                    if (isset($language_names["$detected_lang"])) {
                                        if ($language_detection_notices) {
                                            $username_exp = $item->getMedia()->getUser()->getUsername();
                                            $username_exp_link = "<a href='https://instagram.com/" . htmlchars($username_exp) . "' target='_blank'>@" .  $username_exp . "</a>";

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error")
                                                ->set("data.error.msg", __("Detected %s profile language is", $username_exp_link) . " " . $language_names["$detected_lang"])
                                                ->set("data.error.details", __("Language filtration at targeting."))
                                                ->save();
                                            $Log = null;
                                            unset($Log);

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error");
                                        }
                                        if (in_array($detected_lang, $languages_ids)) {
                                            // All fine
                                        } else {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }

                        // Filtration
                        if ($filtration_profile_picture) {
                            if ($item->getMedia()->getUser()->getHasAnonymousProfilePicture()) {
                                continue;
                            }
                        }
                        if ($filtration_min_comments == 0 && $filtration_max_comments == 10000000000000000) {
                            // Skip min/max comment filtration
                        } else {
                            $comment_count = $item->getMedia()->getCommentCount();
                            if (($filtration_min_comments <= $comment_count) && ($comment_count <= $filtration_max_comments)) {
                                // Match comment filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($filtration_min_likes == 0 && $filtration_max_likes == 10000000000000000) {
                            // Skip min/max likes filtration
                        } else {
                            $like_count = $item->getMedia()->getLikeCount();
                            if (($filtration_min_likes <= $like_count) && ($like_count <= $filtration_max_likes)) {
                                // Match likes filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($item->getMedia()->getMediaType() == 2 && $item->getMedia()->getProductType() == "clips") {
                            // Check plays count only if this is a video post
                            if ($filtration_min_plays == 0 && $filtration_max_plays == 10000000000000000) {
                                // Skip min/max plays filtration
                            } else {
                                $play_count = $item->getMedia()->getPlayCount();
                                if (($filtration_min_plays <= $play_count) && ($play_count <= $filtration_max_plays)) {
                                    // Match plays filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        } elseif ($item->getMedia()->getMediaType() == 2) {
                            // Check views count only if this is a video post
                            if ($filtration_min_views == 0 && $filtration_max_views == 10000000000000000) {
                                // Skip min/max views filtration
                            } else {
                                $view_count = $item->getMedia()->getViewCount();
                                if (($filtration_min_views <= $view_count) && ($view_count <= $filtration_max_views)) {
                                    // Match views filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        }

                        // Found the feed item to repost
                        $feed_item = $item->getMedia();
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();

                    // Bad target handler
                    $last_bad_target = $sc->get("data.last_bad_target");
                    if ($last_bad_target == $target_value) {
                        $sc->set("is_active", 0)->save();
                        continue;
                    }
                    $sc->set("data.last_bad_target", $target_value)->save();

                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of hashtag (reels)"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of hashtag (reels)"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        } else if ($target->type == "location") {
            try {
                $maxId = null;
                $pagination = 0;

                do {
                    set_time_limit(300);
                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }
                    $pagination += 1;
                    if ($pagination >= $filtration_pagination_limit) {
                        break;
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->location->getFeed($target->id, $rank_token, 'ranked', null, null, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);

                    $maxId = $feed->getNextMaxId();

                    // Fix for location processing (March, 2020)
                    $section_items = [];
                    $section_items_counter = 0;
                    $sections = $feed->getSections();
                    foreach ($sections as $section) {
                        if ($section->getLayoutType() == "media_grid") {
                            $medias = $section->getLayoutContent()->getMedias();
                            foreach ($medias as $m) {
                                array_push($section_items, $m->getMedia());
                                $section_items_counter++;
                            }
                        }
                    }
                    $feed = null;
                    unset($feed);
                    $feed = new \InstagramAPI\Response\TagFeedResponse();
                    $feed->setItems($section_items);
                    $feed->setNumResults($section_items_counter);

                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    ///////

                    foreach ($items as $item) {
                        if (!$item->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMediaType() == 8) {
                            $medias = $item->getCarouselMedia();
                            $is_valid = true;
                            foreach ($medias as $media) {
                                if ($media->getMediaType() == 1 && empty($permissions->album_photo)) {
                                    // User has not got a permission for photo albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && empty($permissions->album_video)) {
                                    // User has not got a permission for video albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && !$video_processing) {
                                    // Video processing is not possible now,
                                    // FFMPEG is not configured

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }
                            }

                            if (!$is_valid) {
                                // User can not re-post this album post because of the permission 
                                // (or absence of the ffmpeg video processing)
                                continue;
                            }
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_reels_posts && $item->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Usernames Blacklists
                        $ub_filtration = $sc->get("data.ub_filtration") ? $sc->get("data.ub_filtration") : "";
                        if (!empty($ub_filtration)) {
                            $ub_filtration = strtolower($ub_filtration);
                            $ub_filtration = str_replace(" ", "", $ub_filtration);
                            $ub_filtration = str_replace(",", "\n", $ub_filtration);
                            $ub_array = explode("\n", str_replace("\r", "", $ub_filtration));

                            $is_ub = false;
                            foreach ($ub_array as $key_ub => $ub) {
                                if (ltrim($ub, '@') == $item->getUser()->getUsername()) {
                                    $is_ub = true;
                                    break;
                                }
                            }

                            if ($is_ub) {
                                continue;
                            }
                        }

                        // Caption Filtration
                        $caption_filtration = $sc->get("data.caption_filtration") ? $sc->get("data.caption_filtration") : "";
                        if (!empty($caption_filtration) && !empty($item->getCaption())) {
                            if (!empty($item->getCaption()->getText())) {
                                $caption_text = $item->getCaption()->getText();
                                $caption_text = strtolower($caption_text);
                                $caption_filtration = strtolower($caption_filtration);
                                $caption_filtration = str_replace(" ", "", $caption_filtration);
                                $caption_filtration = str_replace(",", "\n", $caption_filtration);
                                $cf_array = explode("\n", str_replace("\r", "", $caption_filtration));

                                $is_cf = false;
                                foreach ($cf_array as $key_cf => $cf) {
                                    if (preg_match("/{$cf}/i", $caption_text)) {
                                        $is_cf = true;
                                        break;
                                    }
                                }

                                if ($is_cf) {
                                    continue;
                                }
                            }
                        }

                        // Language filtration
                        if (!empty($languages) && !empty($languages_ids)) {
                            $ld = new Language();
                            if (!empty($item->getCaption())) {
                                if (!empty($item->getCaption()->getText())) {
                                    $caption_text = $item->getCaption()->getText();
                                    $detected_lang = $ld->detect($caption_text);
                                    if (isset($language_names["$detected_lang"])) {
                                        if ($language_detection_notices) {
                                            $username_exp = $item->getUser()->getUsername();
                                            $username_exp_link = "<a href='https://instagram.com/" . htmlchars($username_exp) . "' target='_blank'>@" .  $username_exp . "</a>";

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error")
                                                ->set("data.error.msg", __("Detected %s profile language is", $username_exp_link) . " " . $language_names["$detected_lang"])
                                                ->set("data.error.details", __("Language filtration at targeting."))
                                                ->save();
                                            $Log = null;
                                            unset($Log);

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error");
                                        }
                                        if (in_array($detected_lang, $languages_ids)) {
                                            // All fine
                                        } else {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }

                        // Filtration
                        if ($filtration_profile_picture) {
                            if ($item->getUser()->getHasAnonymousProfilePicture()) {
                                continue;
                            }
                        }
                        if ($filtration_min_comments == 0 && $filtration_max_comments == 10000000000000000) {
                            // Skip min/max comment filtration
                        } else {
                            $comment_count = $item->getCommentCount();
                            if (($filtration_min_comments <= $comment_count) && ($comment_count <= $filtration_max_comments)) {
                                // Match comment filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($filtration_min_likes == 0 && $filtration_max_likes == 10000000000000000) {
                            // Skip min/max likes filtration
                        } else {
                            $like_count = $item->getLikeCount();
                            if (($filtration_min_likes <= $like_count) && ($like_count <= $filtration_max_likes)) {
                                // Match likes filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($item->getMediaType() == 2 && $item->getProductType() == "clips") {
                            // Check plays count only if this is a video post
                            if ($filtration_min_plays == 0 && $filtration_max_plays == 10000000000000000) {
                                // Skip min/max plays filtration
                            } else {
                                $play_count = $item->getPlayCount();
                                if (($filtration_min_plays <= $play_count) && ($play_count <= $filtration_max_plays)) {
                                    // Match plays filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        } elseif ($item->getMediaType() == 2) {
                            // Check views count only if this is a video post
                            if ($filtration_min_views == 0 && $filtration_max_views == 10000000000000000) {
                                // Skip min/max views filtration
                            } else {
                                $view_count = $item->getViewCount();
                                if (($filtration_min_views <= $view_count) && ($view_count <= $filtration_max_views)) {
                                    // Match views filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        }

                        // Found the feed item to repost
                        $feed_item = $item;
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();

                    // Bad target handler
                    $last_bad_target = $sc->get("data.last_bad_target");
                    if ($last_bad_target == $target_value) {
                        $sc->set("is_active", 0)->save();
                        continue;
                    }
                    $sc->set("data.last_bad_target", $target_value)->save();

                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed of location
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of location"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed of location
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of location"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        } else if ($target->type == "people") {
            try {
                $maxId = null;
                $pagination = 0;

                do {
                    set_time_limit(300);

                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }
                    $pagination += 1;
                    if ($pagination >= $filtration_pagination_limit) {
                        break;
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->timeline->getUserFeed($target->id, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);

                    $maxId = $feed->getNextMaxId();
                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    ///////

                    foreach ($items as $item) {
                        if (!$item->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMediaType() == 8) {
                            $medias = $item->getCarouselMedia();
                            $is_valid = true;
                            foreach ($medias as $media) {
                                if ($media->getMediaType() == 1 && empty($permissions->album_photo)) {
                                    // User has not got a permission for photo albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && empty($permissions->album_video)) {
                                    // User has not got a permission for video albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && !$video_processing) {
                                    // Video processing is not possible now,
                                    // FFMPEG is not configured

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }
                            }

                            if (!$is_valid) {
                                // User can not re-post this album post because of the permission 
                                // (or absence of the ffmpeg video processing)
                                continue;
                            }
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMediaType() == $is_reels_posts && $item->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Usernames Blacklists
                        $ub_filtration = $sc->get("data.ub_filtration") ? $sc->get("data.ub_filtration") : "";
                        if (!empty($ub_filtration)) {
                            $ub_filtration = strtolower($ub_filtration);
                            $ub_filtration = str_replace(" ", "", $ub_filtration);
                            $ub_filtration = str_replace(",", "\n", $ub_filtration);
                            $ub_array = explode("\n", str_replace("\r", "", $ub_filtration));

                            $is_ub = false;
                            foreach ($ub_array as $key_ub => $ub) {
                                if (ltrim($ub, '@') == $item->getUser()->getUsername()) {
                                    $is_ub = true;
                                    break;
                                }
                            }

                            if ($is_ub) {
                                continue;
                            }
                        }

                        // Caption Filtration
                        $caption_filtration = $sc->get("data.caption_filtration") ? $sc->get("data.caption_filtration") : "";
                        if (!empty($caption_filtration) && !empty($item->getCaption())) {
                            if (!empty($item->getCaption()->getText())) {
                                $caption_text = $item->getCaption()->getText();
                                $caption_text = strtolower($caption_text);
                                $caption_filtration = strtolower($caption_filtration);
                                $caption_filtration = str_replace(" ", "", $caption_filtration);
                                $caption_filtration = str_replace(",", "\n", $caption_filtration);
                                $cf_array = explode("\n", str_replace("\r", "", $caption_filtration));

                                $is_cf = false;
                                foreach ($cf_array as $key_cf => $cf) {
                                    if (preg_match("/{$cf}/i", $caption_text)) {
                                        $is_cf = true;
                                        break;
                                    }
                                }

                                if ($is_cf) {
                                    continue;
                                }
                            }
                        }

                        // Language filtration
                        if (!empty($languages) && !empty($languages_ids)) {
                            $ld = new Language();
                            if (!empty($item->getCaption())) {
                                if (!empty($item->getCaption()->getText())) {
                                    $caption_text = $item->getCaption()->getText();
                                    $detected_lang = $ld->detect($caption_text);
                                    if (isset($language_names["$detected_lang"])) {
                                        if ($language_detection_notices) {
                                            $username_exp = $item->getUser()->getUsername();
                                            $username_exp_link = "<a href='https://instagram.com/" . htmlchars($username_exp) . "' target='_blank'>@" .  $username_exp . "</a>";

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error")
                                                ->set("data.error.msg", __("Detected %s profile language is", $username_exp_link) . " " . $language_names["$detected_lang"])
                                                ->set("data.error.details", __("Language filtration at targeting."))
                                                ->save();
                                            $Log = null;
                                            unset($Log);

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error");
                                        }
                                        if (in_array($detected_lang, $languages_ids)) {
                                            // All fine
                                        } else {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }

                        // Filtration
                        if ($filtration_profile_picture) {
                            if ($item->getUser()->getHasAnonymousProfilePicture()) {
                                continue;
                            }
                        }
                        if ($filtration_min_comments == 0 && $filtration_max_comments == 0) {
                            // Skip min/max comment filtration
                        } else {
                            $comment_count = $item->getCommentCount();
                            if (($filtration_min_comments <= $comment_count) && ($comment_count <= $filtration_max_comments)) {
                                // Match comment filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($filtration_min_likes == 0 && $filtration_max_likes == 0) {
                            // Skip min/max likes filtration
                        } else {
                            $like_count = $item->getLikeCount();
                            if (($filtration_min_likes <= $like_count) && ($like_count <= $filtration_max_likes)) {
                                // Match likes filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($item->getMediaType() == 2 && $item->getProductType() == "clips") {
                            // Check plays count only if this is a video post
                            if ($filtration_min_plays == 0 && $filtration_max_plays == 10000000000000000) {
                                // Skip min/max plays filtration
                            } else {
                                $play_count = $item->getPlayCount();
                                if (($filtration_min_plays <= $play_count) && ($play_count <= $filtration_max_plays)) {
                                    // Match plays filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        } elseif ($item->getMediaType() == 2) {
                            // Check views count only if this is a video post
                            if ($filtration_min_views == 0 && $filtration_max_views == 10000000000000000) {
                                // Skip min/max views filtration
                            } else {
                                $view_count = $item->getViewCount();
                                if (($filtration_min_views <= $view_count) && ($view_count <= $filtration_max_views)) {
                                    // Match views filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        }

                        // Found the feed item to repost
                        $feed_item = $item;
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();

                    // Bad target handler
                    $last_bad_target = $sc->get("data.last_bad_target");
                    if ($last_bad_target == $target_value) {
                        $sc->set("is_active", 0)->save();
                        continue;
                    }
                    $sc->set("data.last_bad_target", $target_value)->save();

                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed of user
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of user"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed of user
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of user"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        } else if ($target->type == "people_reels") {
            try {
                $maxId = null;
                $pagination = 0;

                do {
                    set_time_limit(300);

                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }
                    $pagination += 1;
                    if ($pagination >= $filtration_pagination_limit) {
                        break;
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->reels->getUserFeed($target->id, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);

                    $maxId = $feed->getNextMaxId();
                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    ///////

                    foreach ($items as $item) {
                        if (!$item->getMedia()->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMedia()->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMedia()->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMedia()->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_reels_posts && $item->getMedia()->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getMedia()->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Usernames Blacklists
                        $ub_filtration = $sc->get("data.ub_filtration") ? $sc->get("data.ub_filtration") : "";
                        if (!empty($ub_filtration)) {
                            $ub_filtration = strtolower($ub_filtration);
                            $ub_filtration = str_replace(" ", "", $ub_filtration);
                            $ub_filtration = str_replace(",", "\n", $ub_filtration);
                            $ub_array = explode("\n", str_replace("\r", "", $ub_filtration));

                            $is_ub = false;
                            foreach ($ub_array as $key_ub => $ub) {
                                if (ltrim($ub, '@') == $item->getMedia()->getUser()->getUsername()) {
                                    $is_ub = true;
                                    break;
                                }
                            }

                            if ($is_ub) {
                                continue;
                            }
                        }

                        // Caption Filtration
                        $caption_filtration = $sc->get("data.caption_filtration") ? $sc->get("data.caption_filtration") : "";
                        if (!empty($caption_filtration) && !empty($item->getMedia()->getCaption())) {
                            if (!empty($item->getMedia()->getCaption()->getText())) {
                                $caption_text = $item->getMedia()->getCaption()->getText();
                                $caption_text = strtolower($caption_text);
                                $caption_filtration = strtolower($caption_filtration);
                                $caption_filtration = str_replace(" ", "", $caption_filtration);
                                $caption_filtration = str_replace(",", "\n", $caption_filtration);
                                $cf_array = explode("\n", str_replace("\r", "", $caption_filtration));

                                $is_cf = false;
                                foreach ($cf_array as $key_cf => $cf) {
                                    if (preg_match("/{$cf}/i", $caption_text)) {
                                        $is_cf = true;
                                        break;
                                    }
                                }

                                if ($is_cf) {
                                    continue;
                                }
                            }
                        }

                        // Language filtration
                        if (!empty($languages) && !empty($languages_ids)) {
                            $ld = new Language();
                            if (!empty($item->getMedia()->getCaption())) {
                                if (!empty($item->getMedia()->getCaption()->getText())) {
                                    $caption_text = $item->getMedia()->getCaption()->getText();
                                    $detected_lang = $ld->detect($caption_text);
                                    if (isset($language_names["$detected_lang"])) {
                                        if ($language_detection_notices) {
                                            $username_exp = $item->getMedia()->getUser()->getUsername();
                                            $username_exp_link = "<a href='https://instagram.com/" . htmlchars($username_exp) . "' target='_blank'>@" .  $username_exp . "</a>";

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error")
                                                ->set("data.error.msg", __("Detected %s profile language is", $username_exp_link) . " " . $language_names["$detected_lang"])
                                                ->set("data.error.details", __("Language filtration at targeting."))
                                                ->save();
                                            $Log = null;
                                            unset($Log);

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error");
                                        }
                                        if (in_array($detected_lang, $languages_ids)) {
                                            // All fine
                                        } else {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }

                        // Filtration
                        if ($filtration_profile_picture) {
                            if ($item->getMedia()->getUser()->getHasAnonymousProfilePicture()) {
                                continue;
                            }
                        }
                        if ($filtration_min_comments == 0 && $filtration_max_comments == 10000000000000000) {
                            // Skip min/max comment filtration
                        } else {
                            $comment_count = $item->getMedia()->getCommentCount();
                            if (($filtration_min_comments <= $comment_count) && ($comment_count <= $filtration_max_comments)) {
                                // Match comment filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($filtration_min_likes == 0 && $filtration_max_likes == 10000000000000000) {
                            // Skip min/max likes filtration
                        } else {
                            $like_count = $item->getMedia()->getLikeCount();
                            if (($filtration_min_likes <= $like_count) && ($like_count <= $filtration_max_likes)) {
                                // Match likes filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($item->getMedia()->getMediaType() == 2 && $item->getMedia()->getProductType() == "clips") {
                            // Check plays count only if this is a video post
                            if ($filtration_min_plays == 0 && $filtration_max_plays == 10000000000000000) {
                                // Skip min/max plays filtration
                            } else {
                                $play_count = $item->getMedia()->getPlayCount();
                                if (($filtration_min_plays <= $play_count) && ($play_count <= $filtration_max_plays)) {
                                    // Match plays filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        } elseif ($item->getMedia()->getMediaType() == 2) {
                            // Check views count only if this is a video post
                            if ($filtration_min_views == 0 && $filtration_max_views == 10000000000000000) {
                                // Skip min/max views filtration
                            } else {
                                $view_count = $item->getMedia()->getViewCount();
                                if (($filtration_min_views <= $view_count) && ($view_count <= $filtration_max_views)) {
                                    // Match views filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        }

                        // Found the feed item to repost
                        $feed_item = $item->getMedia();
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();

                    // Bad target handler
                    $last_bad_target = $sc->get("data.last_bad_target");
                    if ($last_bad_target == $target_value) {
                        $sc->set("is_active", 0)->save();
                        continue;
                    }
                    $sc->set("data.last_bad_target", $target_value)->save();

                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed of user
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", "Couldn't get the reels feed of user")
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed of user
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", "Couldn't get the reels feed of user")
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        } else if ($target->type == "collection") {
            try {
                $maxId = null;

                do {
                    set_time_limit(300);

                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->collection->getFeed($target->id, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);


                    $maxId = $feed->getNextMaxId();
                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    ///////

                    foreach ($items as $item) {
                        if (!$item->getMedia()->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMedia()->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMedia()->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMedia()->getMediaType() == 8) {
                            $medias = $item->getMedia()->getCarouselMedia();
                            $is_valid = true;
                            foreach ($medias as $media) {
                                if ($media->getMediaType() == 1 && empty($permissions->album_photo)) {
                                    // User has not got a permission for photo albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && empty($permissions->album_video)) {
                                    // User has not got a permission for video albums

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("You have not got a permission for post video albums.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }

                                if ($media->getMediaType() == 2 && !$video_processing) {
                                    // Video processing is not possible now,
                                    // FFMPEG is not configured

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $is_valid = false;
                                    break;
                                }
                            }

                            if (!$is_valid) {
                                // User can not re-post this album post because of the permission 
                                // (or absence of the ffmpeg video processing)
                                continue;
                            }
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMedia()->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_reels_posts && $item->getMedia()->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getMedia()->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Found the feed item to repost
                        $feed_item = $item->getMedia();
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();
                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed of collection
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg",  __("Couldn't get the feed of collection"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed of collection
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg",  __("Couldn't get the feed of collection"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        } else if ($target->type == "music") {
            try {
                $maxId = null;
                $pagination = 0;

                do {
                    set_time_limit(300);

                    if ($first_retry) {
                        $first_retry = false;
                    } else {
                        sleep(7);
                    }
                    $pagination += 1;
                    if ($pagination >= $filtration_pagination_limit) {
                        break;
                    }

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $feed = $Instagram->reels->getMusicFeed($target->id, $maxId);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }
                            sleep(7);
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);

                    $maxId = $feed->getNextMaxId();
                    $items = $feed->getItems();

                    if (empty($items)) {
                        // Instagram feed related to the target source is empty
                        continue;
                    }

                    if (isset($r_json->shuffle_feed_items) && $r_json->shuffle_feed_items) {
                        shuffle($items);
                    } elseif ($sc->get("data.targets_shuffle")) {
                        shuffle($items);
                    }

                    ///////

                    foreach ($items as $item) {
                        if (!$item->getMedia()->getId()) {
                            // Item is not valid
                            continue;
                        }

                        if (!in_array($item->getMedia()->getMediaType(), $acceptable_media_types)) {
                            // User has not got a permission to post this kind of the item

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("You have not got a permission to post this kind of the item.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        if ($item->getMedia()->getMediaType() == 2 && !$video_processing) {
                            // Video processing is not possible now,
                            // FFMPEG is not configured

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error")
                                ->set("data.error.msg", __("Video processing is not possible, FFMPEG is not configured.") . " " . __("Please contact with Support."))
                                ->save();
                            $Log = null;
                            unset($Log);

                            $Log = new LogModel;
                            $Log->set("user_id", $User->get("id"))
                                ->set("account_id", $Account->get("id"))
                                ->set("status", "error");

                            continue;
                        }

                        // Media type filtration 
                        if (!$is_photo_posts && !$is_video_posts && !$is_album_posts && !$is_reels_posts) {
                            // All media types allowed
                        } else {
                            if ($item->getMedia()->getMediaType() == $is_photo_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_video_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_album_posts) {
                                // Match
                            } elseif ($item->getMedia()->getMediaType() == $is_reels_posts && $item->getMedia()->getProductType() == "clips") {
                                // Match
                            } else {
                                // All media types doesn't not match criteria
                                continue;
                            }
                        }

                        $_log = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "original_media_code" => $item->getMedia()->getCode(),
                            "status" => "success"
                        ]);

                        if ($_log->isAvailable()) {
                            // Already reposted this feed
                            continue;
                        }

                        // Usernames Blacklists
                        $ub_filtration = $sc->get("data.ub_filtration") ? $sc->get("data.ub_filtration") : "";
                        if (!empty($ub_filtration)) {
                            $ub_filtration = strtolower($ub_filtration);
                            $ub_filtration = str_replace(" ", "", $ub_filtration);
                            $ub_filtration = str_replace(",", "\n", $ub_filtration);
                            $ub_array = explode("\n", str_replace("\r", "", $ub_filtration));

                            $is_ub = false;
                            foreach ($ub_array as $key_ub => $ub) {
                                if (ltrim($ub, '@') == $item->getMedia()->getUser()->getUsername()) {
                                    $is_ub = true;
                                    break;
                                }
                            }

                            if ($is_ub) {
                                continue;
                            }
                        }

                        // Caption Filtration
                        $caption_filtration = $sc->get("data.caption_filtration") ? $sc->get("data.caption_filtration") : "";
                        if (!empty($caption_filtration) && !empty($item->getMedia()->getCaption())) {
                            if (!empty($item->getMedia()->getCaption()->getText())) {
                                $caption_text = $item->getMedia()->getCaption()->getText();
                                $caption_text = strtolower($caption_text);
                                $caption_filtration = strtolower($caption_filtration);
                                $caption_filtration = str_replace(" ", "", $caption_filtration);
                                $caption_filtration = str_replace(",", "\n", $caption_filtration);
                                $cf_array = explode("\n", str_replace("\r", "", $caption_filtration));

                                $is_cf = false;
                                foreach ($cf_array as $key_cf => $cf) {
                                    if (preg_match("/{$cf}/i", $caption_text)) {
                                        $is_cf = true;
                                        break;
                                    }
                                }

                                if ($is_cf) {
                                    continue;
                                }
                            }
                        }

                        // Language filtration
                        if (!empty($languages) && !empty($languages_ids)) {
                            $ld = new Language();
                            if (!empty($item->getMedia()->getCaption())) {
                                if (!empty($item->getMedia()->getCaption()->getText())) {
                                    $caption_text = $item->getMedia()->getCaption()->getText();
                                    $detected_lang = $ld->detect($caption_text);
                                    if (isset($language_names["$detected_lang"])) {
                                        if ($language_detection_notices) {
                                            $username_exp = $item->getMedia()->getUser()->getUsername();
                                            $username_exp_link = "<a href='https://instagram.com/" . htmlchars($username_exp) . "' target='_blank'>@" .  $username_exp . "</a>";

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error")
                                                ->set("data.error.msg", __("Detected %s profile language is", $username_exp_link) . " " . $language_names["$detected_lang"])
                                                ->set("data.error.details", __("Language filtration at targeting."))
                                                ->save();
                                            $Log = null;
                                            unset($Log);

                                            $Log = new LogModel;
                                            $Log->set("user_id", $User->get("id"))
                                                ->set("account_id", $Account->get("id"))
                                                ->set("status", "error");
                                        }
                                        if (in_array($detected_lang, $languages_ids)) {
                                            // All fine
                                        } else {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }

                        // Filtration
                        if ($filtration_profile_picture) {
                            if ($item->getMedia()->getUser()->getHasAnonymousProfilePicture()) {
                                continue;
                            }
                        }
                        if ($filtration_min_comments == 0 && $filtration_max_comments == 10000000000000000) {
                            // Skip min/max comment filtration
                        } else {
                            $comment_count = $item->getMedia()->getCommentCount();
                            if (($filtration_min_comments <= $comment_count) && ($comment_count <= $filtration_max_comments)) {
                                // Match comment filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($filtration_min_likes == 0 && $filtration_max_likes == 10000000000000000) {
                            // Skip min/max likes filtration
                        } else {
                            $like_count = $item->getMedia()->getLikeCount();
                            if (($filtration_min_likes <= $like_count) && ($like_count <= $filtration_max_likes)) {
                                // Match likes filtration
                                // All fine
                            } else {
                                continue;
                            }
                        }
                        if ($item->getMedia()->getMediaType() == 2 && $item->getMedia()->getProductType() == "clips") {
                            // Check plays count only if this is a video post
                            if ($filtration_min_plays == 0 && $filtration_max_plays == 10000000000000000) {
                                // Skip min/max plays filtration
                            } else {
                                $play_count = $item->getMedia()->getPlayCount();
                                if (($filtration_min_plays <= $play_count) && ($play_count <= $filtration_max_plays)) {
                                    // Match plays filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        } elseif ($item->getMedia()->getMediaType() == 2) {
                            // Check views count only if this is a video post
                            if ($filtration_min_views == 0 && $filtration_max_views == 10000000000000000) {
                                // Skip min/max views filtration
                            } else {
                                $view_count = $item->getMedia()->getViewCount();
                                if (($filtration_min_views <= $view_count) && ($view_count <= $filtration_max_views)) {
                                    // Match views filtration
                                    // All fine
                                } else {
                                    continue;
                                }
                            }
                        }

                        // Found the feed item to repost
                        $feed_item = $item->getMedia();
                        break;
                    }

                    if (empty($maxId)) {
                        break;
                    }
                } while (empty($feed_item));

                if (empty($feed_item)) {
                    $sc->set("status", "scheduled")->save();

                    $Log->set("data.error.msg", __("Couldn't find the new feed item to repost from target %s", $target_link))
                        ->save();

                    // Bad target handler
                    $last_bad_target = $sc->get("data.last_bad_target");
                    if ($last_bad_target == $target_value) {
                        $sc->set("is_active", 0)->save();
                        continue;
                    }
                    $sc->set("data.last_bad_target", $target_value)->save();

                    continue;
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of hashtag"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            } catch (\Exception $e) {
                // Couldn't get instagram feed related to the hashtag
                // Log data
                $msg = $e->getMessage();

                $Log->set("data.error.msg", __("Couldn't get the feed of hashtag"))
                    ->set("data.error.details", $msg)
                    ->save();
                continue;
            }
        }

        // Increase max_execution_time
        set_time_limit(300);

        // Download the media
        $media = [];
        $original_media_type = "photo";
        $original_product_type = $feed_item->getProductType();
        if ($feed_item->getMediaType() == 1 && $feed_item->getImageVersions2()->getCandidates()[0]->getUrl()) {
            $media[] = $feed_item->getImageVersions2()->getCandidates()[0]->getUrl();
        } else if ($feed_item->getMediaType() == 2 && $feed_item->getVideoVersions()[0]->getUrl()) {
            $media[] = $feed_item->getVideoVersions()[0]->getUrl();
            $original_media_type = "video";

            // Thumbnail downloader
            $video_thumbnail_url = $feed_item->getImageVersions2()->getCandidates()[0]->getUrl();
            if (isset($video_thumbnail_url)) {
                $url_parts = parse_url($video_thumbnail_url);
                $ext = strtolower(pathinfo($url_parts['path'], PATHINFO_EXTENSION));
                $thumbnail_filename = uniqid(readableRandomString(8) . "-") . "." . $ext;
                $downres = file_put_contents(TEMP_PATH . "/" . $thumbnail_filename, file_get_contents($video_thumbnail_url));
                if ($downres) {
                    // Compress downloaded image
                    try {
                        $thumbnail_path = TEMP_PATH . "/" . $thumbnail_filename;
                        $image = new \claviska\SimpleImage;
                        $image->fromFile($thumbnail_path);

                        $width = $image->getWidth();
                        if ($width < 320) {
                            unlink($thumbnail_path);
                            $Log->set("data.error.msg", "Couldn't compress downloaded image.")
                                ->set("data.error.details", "Image width is too small.")
                                ->save();
                            continue;
                        } elseif ($width > \InstagramAPI\Media\Photo\PhotoDetails::MAX_WIDTH) {
                            $image->resize(\InstagramAPI\Media\Photo\PhotoDetails::MAX_WIDTH);
                        }

                        $width = $image->getWidth();
                        $height = $image->getHeight();
                        if ($height > 1350) {
                            $image->bestFit($width, 1350);
                        }

                        $image->toFile($thumbnail_path, "image/jpeg", 90);
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();

                        $Log->set("data.error.msg", "Couldn't compress downloaded image.")
                            ->set("data.error.details", $msg)
                            ->save();
                        continue;
                    }
                }
            }
        } else if ($feed_item->getMediaType() == 8) {
            foreach ($feed_item->getCarouselMedia() as $m) {
                if ($m->getMediaType() == 1 && $m->getImageVersions2()->getCandidates()[0]->getUrl()) {
                    $media[] = $m->getImageVersions2()->getCandidates()[0]->getUrl();
                } else if ($m->getMediaType() == 2 && $m->getVideoVersions()[0]->getUrl()) {
                    $media[] = $m->getVideoVersions()[0]->getUrl();
                }
            }
        }


        $downloaded_media = [];
        foreach ($media as $m) {
            $url_parts = parse_url($m);
            if (empty($url_parts['path'])) {
                continue;
            }

            $ext = strtolower(pathinfo($url_parts['path'], PATHINFO_EXTENSION));
            $filename = uniqid(readableRandomString(8) . "-") . "." . $ext;
            $downres = file_put_contents(TEMP_PATH . "/" . $filename, file_get_contents($m));
            if ($downres) {
                // Compress downloaded images
                if (in_array($ext, ["mp4"])) {
                    // Concatenation for short videos
                    // Create ffmpeg instance
                    $config = [
                        'timeout' => 600, // The timeout for the underlying process
                    ];
                    if (FFMPEGBIN) {
                        $config["ffmpeg.binaries"] = FFMPEGBIN;
                    }
                    if (FFPROBEBIN) {
                        $config["ffprobe.binaries"] = FFPROBEBIN;
                    }
                    // Check video duration 
                    try {
                        $ffmpeg = \FFMpeg\FFMpeg::create($config);
                        $ffprobe = $ffmpeg->getFFProbe();
                        $file_path = TEMP_PATH . "/" . $filename;
                        $video = $ffmpeg->open($file_path);
                        $duration = $ffprobe->format($file_path)->get("duration");

                        // Check minimum duration
                        if ($duration < 3) {
                            $new_filename = uniqid(readableRandomString(8) . "-") . ".mp4";
                            $new_file_path = TEMP_PATH . "/" . $new_filename;
                            // Concatenation
                            $video->concat(array($file_path, $file_path));
                            $video->save(new \FFMpeg\Format\Video\X264("aac", "libx264"), $new_file_path);
                            unlink($file_path);
                            $filename = $new_filename;
                        }

                        // Check maximum duration
                        if ($duration > 60) {
                            $new_filename = uniqid(readableRandomString(8) . "-") . ".mp4";
                            $new_file_path = TEMP_PATH . "/" . $new_filename;
                            // Video crop
                            $video->filters()->clip(\FFMpeg\Coordinate\TimeCode::fromSeconds(0), \FFMpeg\Coordinate\TimeCode::fromSeconds(60));
                            $video->save(new \FFMpeg\Format\Video\X264("aac", "libx264"), $new_file_path);
                            unlink($file_path);
                            $filename = $new_filename;
                        }
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();
                        $Log->set("data.error.msg", "Couldn't process downloaded video.")
                            ->set("data.error.details", $msg)
                            ->save();
                        continue;
                    }
                } else {
                    // Compress downloaded image
                    try {
                        $file_path = TEMP_PATH . "/" . $filename;
                        $image = new \claviska\SimpleImage;
                        $image->fromFile($file_path);

                        $width = $image->getWidth();
                        if ($width < 320) {
                            unlink($file_path);
                            $Log->set("data.error.msg", "Couldn't compress downloaded image.")
                                ->set("data.error.details", "Image width is too small.")
                                ->save();
                            continue;
                        } elseif ($width > \InstagramAPI\Media\Photo\PhotoDetails::MAX_WIDTH) {
                            $image->resize(\InstagramAPI\Media\Photo\PhotoDetails::MAX_WIDTH);
                        }

                        $width = $image->getWidth();
                        $height = $image->getHeight();
                        if ($height > 1350) {
                            $image->bestFit($width, 1350);
                        }

                        $image->toFile($file_path, "image/jpeg", 90);
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();

                        $Log->set("data.error.msg", "Couldn't compress downloaded image.")
                            ->set("data.error.details", $msg)
                            ->save();
                        continue;
                    }
                }

                $downloaded_media[] = $filename;
            }
        }

        if (empty($downloaded_media)) {
            $Log->set("data.error.msg", "Couldn't download the media of the selected post")
                ->save();
            continue;
        }

        // Original caption
        $original_caption = "";
        $caption_no_hashtags = "";
        $caption_no_mentions = "";
        $caption_no_hashtags_no_mentions = "";
        $hashtags_from_caption = "";
        $mentions_from_caption = "";
        $mentions_from_post_tags = "";
        $mentions_from_caption_and_post_tags = "";
        if ($feed_item->getCaption()) {
            $original_caption = $feed_item->getCaption()->getText();
            // Caption processing
            if (isset($original_caption)) {
                $regex_username = '/@[a-zA-Z0-9._]*/';
                $regex_hashtag = '/#((?:[\pL_0-9]|([0-9#][\x{20E3}])|[\x{00ae}\x{00a9}\x{203C}\x{2047}\x{2048}\x{2049}\x{3030}\x{303D}\x{2139}\x{2122}\x{3297}\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F6FF}][\x{FE00}-\x{FEFF}]?)+)/u';

                // Original caption without hashtags
                $caption_no_hashtags = preg_replace($regex_hashtag, '', $original_caption);

                // Original caption without user mentions
                $caption_no_mentions = preg_replace($regex_username, '', $original_caption);

                // Original caption without hashtags and without user mentions
                $caption_no_hashtags_no_mentions = preg_replace($regex_hashtag, '', $original_caption);
                $caption_no_hashtags_no_mentions = preg_replace($regex_username, '', $caption_no_hashtags_no_mentions);

                // Original hashtags from post caption
                $hashtags_from_caption_array = [];
                if (preg_match_all($regex_hashtag, $original_caption, $matches)) {
                    $detected_hashtags = array_unique($matches[0]);
                    foreach ($detected_hashtags as $key => $dh) {
                        $hashtags_from_caption_array[] = $dh;
                    }
                }
                $hashtags_from_caption = implode(" ", $hashtags_from_caption_array);

                // Original user mentions from post caption
                $mentions_from_caption_array = [];
                if (preg_match_all($regex_username, $original_caption, $matches)) {
                    $detected_mentions = array_unique($matches[0]);
                    foreach ($detected_mentions as $key => $dh) {
                        $mentions_from_caption_array[] = $dh;
                    }
                }
                $mentions_from_caption = implode(" ", $mentions_from_caption_array);

                // Original mentions from post tags
                $mentions_from_post_tags_array = [];
                $original_usertags = $feed_item->getUsertags();
                if (isset($original_usertags)) {
                    $original_usertags_in = $original_usertags->getIn();
                    if (isset($original_usertags_in) && is_array($original_usertags_in)) {
                        if (count($original_usertags_in) > 0) {
                            foreach ($original_usertags_in as $key_uin => $uin) {
                                $mentions_from_post_tags_array[] = $uin->getUser()->getUsername();
                            }
                        }
                    }
                }
                $mentions_from_post_tags = implode(" ", $mentions_from_post_tags_array);

                // Original mentions from post caption and post tags
                $mentions_from_caption_and_post_tags_array = [];
                $mentions_from_caption_and_post_tags_array = array_merge($mentions_from_caption_array, $mentions_from_post_tags_array);
                $mentions_from_caption_and_post_tags_array = array_unique($mentions_from_caption_and_post_tags_array);
                $mentions_from_caption_and_post_tags = implode(" ", $mentions_from_caption_and_post_tags_array);
            }
        }

        // Location name
        $location_name = "";
        $location_name_caption = "";
        if ($feed_item->getLocation()) {
            $location_name = $feed_item->getLocation()->getName();
            $location_name_caption =  "📍" . $feed_item->getLocation()->getName();
        }

        if (isset($r_json->caption)) {
            $caption = $r_json->caption;
            $variables = [
                "{{new_line}}" => "\n"
            ];
            $caption = str_replace(
                array_keys($variables),
                array_values($variables),
                $caption
            );
        } else {
            $caption = $sc->get("caption");
            $variables = [
                "{{new_line}}" => "\n"
            ];
            $caption = str_replace(
                array_keys($variables),
                array_values($variables),
                $caption
            );
        }
        if (isset($r_json->first_comment)) {
            $first_comment = $r_json->first_comment;
        } else {
            $first_comment = $sc->get("first_comment");
        }

        $variables = [
            "{{caption}}" => $original_caption,
            "{{username}}" => "@" . $feed_item->getUser()->getUsername(),
            "{{full_name}}" => $feed_item->getUser()->getFullName() ?
                $feed_item->getUser()->getFullName() :
                "@" . $feed_item->getUser()->getUsername(),
            "{{location}}" => $location_name_caption,
            "{{caption_no_hashtags}}" => $caption_no_hashtags,
            "{{caption_no_mentions}}" => $caption_no_mentions,
            "{{caption_no_hashtags_no_mentions}}" => $caption_no_hashtags_no_mentions,
            "{{hashtags_from_caption}}" => $hashtags_from_caption,
            "{{mentions_from_caption}}" => $mentions_from_caption,
            "{{mentions_from_post_tags}}" => $mentions_from_post_tags,
            "{{mentions_from_caption_and_post_tags}}" => $mentions_from_caption_and_post_tags
        ];

        $caption = str_replace(
            array_keys($variables),
            array_values($variables),
            $caption
        );

        $first_comment = str_replace(
            array_keys($variables),
            array_values($variables),
            $first_comment
        );

        $caption = $Emojione->shortnameToUnicode($caption);
        $first_comment = $Emojione->shortnameToUnicode($first_comment);

        if ($User->get("settings.spintax")) {
            $caption = \Spintax::process($caption);
            $first_comment = \Spintax::process($first_comment);
        }

        $caption = mb_substr($caption, 0, 2200);
        $first_comment = mb_substr($first_comment, 0, 2200);

        // Find & Replace for original post caption
        $find_and_replace = @json_decode($sc->get("data.find_and_replace"));
        if (!empty($find_and_replace)) {
            $regex_1 = '/@[a-zA-Z0-9._]*=@[a-zA-Z0-9._]*/';
            $regex_2 = '/#[a-zA-Z0-9._]*=#[a-zA-Z0-9._]*/';
            $regex_3 = '/{[^{^}]*}={[^}^{]*}/';

            // Usernames replacement
            if (preg_match_all($regex_1, $find_and_replace, $matches)) {
                if (isset($matches[0])) {
                    $du = array_unique($matches[0]);
                    foreach ($du as $key => $d) {
                        $regex_old = '/@[a-zA-Z0-9._]*=/';
                        $regex_new = '/=@[a-zA-Z0-9._]*/';
                        if (preg_match($regex_old, $d, $result_1) && preg_match($regex_new, $d, $result_2)) {
                            $old_username = rtrim($result_1[0], '=');
                            $new_username = ltrim($result_2[0], '=');
                            $caption = str_replace($old_username, $new_username, $caption);
                        }
                    }
                }
            }

            // Hashtags replacement
            if (preg_match_all($regex_2, $find_and_replace, $matches)) {
                if (isset($matches[0])) {
                    $dh = array_unique($matches[0]);
                    foreach ($dh as $key => $d) {
                        $regex_old = '/#[a-zA-Z0-9._]*=/';
                        $regex_new = '/=#[a-zA-Z0-9._]*/';
                        if (preg_match($regex_old, $d, $result_1) && preg_match($regex_new, $d, $result_2)) {
                            $old_hashtag = rtrim($result_1[0], '=');
                            $new_hashtag = ltrim($result_2[0], '=');
                            $caption = str_replace($old_hashtag, $new_hashtag, $caption);
                        }
                    }
                }
            }

            // Custom text replacement
            if (preg_match_all($regex_3, $find_and_replace, $matches)) {
                if (isset($matches[0])) {
                    $dtext = array_unique($matches[0]);
                    foreach ($dtext as $key => $d) {
                        $regex_old = '/{([^{^}]*)}={/';
                        $regex_new = '/}={([^{^}]*)}/';
                        if (preg_match($regex_old, $d, $result_1) && preg_match($regex_new, $d, $result_2)) {
                            $old_text = $result_1[1];
                            $new_text = $result_2[1];
                            $caption = str_replace($old_text, $new_text, $caption);
                        }
                    }
                }
            }
        }

        // Set custom proxy connection
        $custom_proxy = $settings->get("data.custom_proxy") ? $settings->get("data.custom_proxy") : false;
        if ($custom_proxy) {
            $Instagram->setProxy($custom_proxy);
        }
        $custom_proxy_ts = $sc->get("data.custom_proxy") ? $sc->get("data.custom_proxy") : false;
        if ($custom_proxy_ts) {
            $Instagram->setProxy($custom_proxy_ts);
        }

        // Custom usertags array
        $custom_usertags_array = json_decode($sc->get("data.custom_usertags_array"), true);

        // Blacklist for "Find & Tag on image for original post caption"
        $find_and_tag_blacklist = @json_decode($sc->get("data.find_and_tag_blacklist"));
        $fat_blacklist = [];
        if (!empty($find_and_tag_blacklist)) {
            if (preg_match_all('/@[a-zA-Z0-9._]*/', $find_and_tag_blacklist, $matches)) {
                if (isset($matches[0])) {
                    $du = array_unique($matches[0]);
                    foreach ($du as $key => $d) {
                        array_push($fat_blacklist, ltrim($d, '@'));
                    }
                }
            }
        }
        $Log->set("data.blacklist", json_encode($fat_blacklist));

        // Find & Tag on image for original post caption
        $find_and_tag = $sc->get("data.find_and_tag");
        if ($find_and_tag) {
            if (preg_match_all('/@[a-zA-Z0-9._]*/', $original_caption, $matches)) {
                if (isset($matches[0])) {
                    $du = array_unique($matches[0]);
                    foreach ($du as $key => $d) {
                        if (in_array(ltrim($d, '@'), $fat_blacklist)) {
                            // Usertag is blacklisted
                        } else {
                            // Safety delay
                            sleep(1);
                            try {
                                $info_by_name = $Instagram->people->getInfoByName(ltrim($d, '@'));
                                if (isset($info_by_name)) {
                                    array_push($custom_usertags_array, [
                                        "position" => [(float)(mt_rand(10, 90) / 100), (float)0.75],
                                        "user_id" => $info_by_name->getUser()->getPk(),
                                        "username" =>  $info_by_name->getUser()->getUsername()
                                    ]);
                                }
                            } catch (\InstagramAPI\Exception\NotFoundException $e) {
                                // Do nothing
                            } catch (\InstagramAPI\Exception\InstagramException $e) {
                                // Do nothing
                            } catch (\Exception $e) {
                                // Do nothing   
                            }
                        }
                    }
                }
            }
        }

        // Increase max execution time
        set_time_limit(600);

        // Empty response counter
        $empty_response_counter = $sc->get("data.empty_response_counter") ? $sc->get("data.empty_response_counter") : 0;
        $empty_response_counter++;

        // Try to repost
        try {
            // Send login flow
            $Instagram->_sendLoginFlow(false, 600);

            // If we want to attach a location, we must find a valid Location object first
            $location = null;
            $locations = null;
            if ($sc->get("metadata_location") && $location_name) {
                $location_lat = empty($feed_item->getLocation()->getLat()) ? "40.677541" : $feed_item->getLocation()->getLat();
                $location_lng = empty($feed_item->getLocation()->getLng()) ? "-73.935673" : $feed_item->getLocation()->getLat();

                $is_connected = false;
                $is_connected_count = 0;
                $is_connected_fail = false;
                do {
                    if ($is_connected_count >= 3) {
                        $is_connected_fail = true;
                    }
                    try {
                        $locations = $Instagram->location->search($location_lat, $location_lng, $location_name);
                        $is_connected = true;
                    } catch (\InstagramAPI\Exception\NetworkException $e) {
                        if ($is_connected_fail) {
                            $is_connected = true;
                        }
                        sleep(7);
                    } catch (\InstagramAPI\Exception\InstagramException $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        throw $e;
                    }

                    $is_connected_count += 1;
                } while (!$is_connected);
            }

            if (!empty($locations)) {
                $l = $locations->getVenues();
                if (isset($l[0])) {
                    $location = $l[0];
                }
            }

            // Set metadata
            $metadata = [];
            $metadata["caption"] = $caption;
            $metadata["location"] = $location;

            // Double repost prevention
            $already_posted = false;

            $nextpost = repost_pro_nextpost_api($Instagram, "repost-pro-upload");

            if (count($downloaded_media) > 1) {
                $album_media = [];
                $temp_files_handlers = [];

                ////// NORMALIZE MEDIA //////
                // All album files must have the same aspect ratio.
                // We copy the app's behavior by using the first file
                // as template for all subsequent ones.

                $mediaOptions = [
                    $nextpost->p_1 => \InstagramAPI\Constants::FEED_TIMELINE_ALBUM,
                    $nextpost->p_2 => \InstagramAPI\Media\InstagramMedia::CROP,
                    "horCropFocus" => 0,
                    "verCropFocus" => 0,
                    $nextpost->p_3 => true
                ];

                foreach ($downloaded_media as $m) {
                    $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                    $file_path = TEMP_PATH . "/" . $m;

                    if (in_array($ext, ["mp4"])) {
                        $media_type = "video";
                        $temp_files_handlers[] = new \InstagramAPI\Media\Video\InstagramVideo($file_path, $mediaOptions);
                        $file_path = $temp_files_handlers[count($temp_files_handlers) - 1]->getFile();
                    } else {
                        $media_type = "photo";
                        $temp_files_handlers[] = new \InstagramAPI\Media\Photo\InstagramPhoto($file_path, $mediaOptions);
                        $file_path = $temp_files_handlers[count($temp_files_handlers) - 1]->getFile();
                    }

                    $album_media[] = [
                        "type" => $media_type,
                        "file" => $file_path
                    ];

                    if (!isset($mediaOptions['forceAspectRatio'])) {
                        if (in_array($ext, ["mp4"])) {
                            $mediaDetails = new \InstagramAPI\Media\Video\VideoDetails($file_path);
                        } else {
                            $mediaDetails = new \InstagramAPI\Media\Photo\PhotoDetails($file_path);
                        }
                        $mediaOptions['forceAspectRatio'] = $mediaDetails->getAspectRatio();
                    }
                }

                if ($sc->get("metadata_user") && $album_media[0]["type"] == "photo") {
                    // Tag owner of media on first photo of album	
                    $album_media[0] = [
                        "type" => "photo",
                        "file" => TEMP_PATH . "/" . $downloaded_media[0],
                        "usertags" => [
                            [
                                "position" => [0.5, 0.5],
                                "user_id" => $feed_item->getUser()->getPk()
                            ]
                        ]
                    ];
                } elseif ($sc->get("metadata_user")) {
                    // Tag owner of media on first video of album
                    $album_media[0] = [
                        "type" => "video",
                        "file" => TEMP_PATH . "/" . $downloaded_media[0],
                        "usertags" => [
                            [
                                "position" => [0.0, 0.0],
                                "user_id" => $feed_item->getUser()->getPk()
                            ]
                        ]
                    ];
                }

                // Tag original post product tags (only for self posts)
                foreach ($feed_item->getCarouselMedia() as $key_m => $fi_m) {
                    if (method_exists($fi_m, "getProductTags")) {
                        $original_product_tags = $fi_m->getProductTags();
                        if (isset($original_product_tags)) {
                            // Check is it self post
                            $original_product_tags_in = $fi_m->getProductTags()->getIn();
                            if (isset($original_product_tags_in)) {
                                if (count($original_product_tags_in) > 0) {
                                    if ($Instagram->account_id == $feed_item->getUser()->getPk()) {
                                        $album_media[$key_m]["product_tags"] = $original_product_tags_in;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!empty($custom_usertags_array)) {
                    if ($sc->get("metadata_user")) {
                        if ($album_media[0]["type"] == "photo") {
                            $used_usertag_id = $album_media[0]["usertags"][0]["user_id"];
                        } else {
                            $used_usertag_id = $album_media[0]["usertags"][0];
                        }
                    } else {
                        $used_usertag_id = null;
                    }
                    foreach ($custom_usertags_array as $key_c => $cua) {
                        if ($used_usertag_id == $cua["user_id"]) {
                            // Skip this user
                            continue;
                        }
                        if ($album_media[0]["type"] == "photo") {
                            if (!isset($album_media[0]["usertags"])) {
                                $album_media[0] = [
                                    "type" => "photo",
                                    "file" => TEMP_PATH . "/" . $downloaded_media[0],
                                    "usertags" => []
                                ];
                            }
                            array_push($album_media[0]["usertags"], [
                                "position" => [$cua["position"][0], $cua["position"][1]],
                                "user_id" => $cua["user_id"]
                            ]);
                        } else {
                            if (!isset($album_media[0]["usertags"])) {
                                $album_media[0] = [
                                    "type" => "video",
                                    "file" => TEMP_PATH . "/" . $downloaded_media[0],
                                    "usertags" => []
                                ];
                            }
                            array_push($album_media[0]["usertags"], [
                                "position" => [0.0, 0.0],
                                "user_id" => $cua["user_id"]
                            ]);
                        }
                    }
                }

                $is_connected = false;
                $is_connected_count = 0;
                $is_connected_fail = false;
                do {
                    if ($is_connected_count >= 3) {
                        $is_connected_fail = true;
                    }
                    try {
                        $res = null;
                        $res = $Instagram->timeline->uploadAlbum($album_media, $metadata);
                        $is_connected = true;
                    } catch (\InstagramAPI\Exception\NetworkException $e) {
                        if ($is_connected_fail) {
                            throw $e;
                        }

                        // DOUBLE POST PREVENTION

                        // Set default proxy connection
                        if ($Account->get("proxy")) {
                            $Instagram->setProxy($Account->get("proxy"));
                        }

                        // Check is media published or not
                        sleep(5);
                        $is_connected_sf = false;
                        $is_connected_count_sf = 0;
                        do {
                            try {
                                $self_feed = $Instagram->timeline->getSelfUserFeed();
                                if (!empty($self_feed->getItems())) {
                                    $last_taken_at = $self_feed->getItems()[0]->getTakenAt();
                                    $last_window = time() - 600;
                                    if ($last_taken_at >= $last_window) {
                                        $self_feed_item = $self_feed->getItems()[0];
                                        $already_posted = true;
                                    }
                                }
                                $is_connected = true;
                                $is_connected_sf = true;
                            } catch (\InstagramAPI\Exception\NetworkException $e) {
                                if ($is_connected_count_sf >= 3) {
                                    throw $e;
                                }
                                sleep(7);
                            } catch (\InstagramAPI\Exception\BadRequestException $e) {
                                throw $e;
                            } catch (\InstagramAPI\Exception\InstagramException $e) {
                                throw $e;
                            } catch (\Exception $e) {
                                throw $e;
                            }
                            $is_connected_count_sf += 1;
                        } while (!$is_connected_sf);

                        // Set mobile proxy connection
                        if ($custom_proxy) {
                            $Instagram->setProxy($custom_proxy);
                        }
                    } catch (\InstagramAPI\Exception\BadRequestException $e) {
                        throw $e;
                    } catch (\InstagramAPI\Exception\InstagramException $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        throw $e;
                    }

                    $is_connected_count += 1;
                } while (!$is_connected);
            } else {
                // Tag original post product tags (only for self posts)
                $original_product_tags = $feed_item->getProductTags();
                if (isset($original_product_tags)) {
                    // Check is it self post
                    if ($Instagram->account_id == $feed_item->getUser()->getPk()) {
                        $metadata["product_tags"] = $original_product_tags;
                    }
                }

                $m = $downloaded_media[0];
                $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                $file_path = TEMP_PATH . "/" . $m;
                if (in_array($ext, ["mp4"])) {
                    if (isset($thumbnail_path)) {
                        $metadata['cover_photo'] = $thumbnail_path;
                    }

                    if ($original_product_type == "clips") {

                        $video = new \InstagramAPI\Media\Video\InstagramVideo($file_path, [
                            $nextpost->p_1 => \InstagramAPI\Constants::FEED_CLIP,
                            $nextpost->p_2 => \InstagramAPI\Media\InstagramMedia::CROP,
                            $nextpost->p_3 => true
                        ]);

                        if (isset($r_json->share_to_timeline_reel) && $r_json->share_to_timeline_reel) {
                            $metadata['share_to_feed'] = true;
                        } elseif ($sc->get("data.share_to_timeline_reel")) {
                            $metadata['share_to_feed'] = true;
                        }

                        $clips_metadata = $feed_item->getClipsMetadata();

                        $nextpost = repost_pro_nextpost_api($Instagram, "repost-pro-clips-metadata");

                        if (isset($clips_metadata[$nextpost->p_3])) {
                            $audio_type = $clips_metadata[$nextpost->p_3];
                            if ($audio_type ==  $nextpost->p_1) {
                                if (
                                    isset($clips_metadata['music_info'][$nextpost->p_2]['audio_asset_id']) &&
                                    isset($clips_metadata['music_info'][$nextpost->p_2]['title']) &&
                                    isset($clips_metadata['music_info'][$nextpost->p_2]['display_artist']) &&
                                    isset($clips_metadata['music_info']['music_consumption_info']['overlap_duration_in_ms']) &&
                                    isset($clips_metadata['music_info'][$nextpost->p_2]['audio_cluster_id'])
                                ) {
                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("We detected a music track %s by %s in Reels media for repost", $clips_metadata['music_info'][$nextpost->p_2]['title'], $clips_metadata['music_info'][$nextpost->p_2]['display_artist']))
                                        ->set("data.error.details", __("Music label will be attached to repost media."))
                                        ->save();
                                    $Log = null;
                                    unset($Log);

                                    $Log = new LogModel;
                                    $Log->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error");

                                    $metadata['music_params'] = [
                                        "audio_asset_id" => $clips_metadata['music_info'][$nextpost->p_2]['audio_asset_id'],
                                        "audio_cluster_id" => $clips_metadata['music_info'][$nextpost->p_2]['audio_cluster_id'],
                                        "audio_asset_start_time_in_ms" => 0,
                                        "derived_content_start_time_in_ms" => 0,
                                        "overlap_duration_in_ms" => $clips_metadata['music_info']['music_consumption_info']['overlap_duration_in_ms'],
                                        "browse_session_id" => \InstagramAPI\Signatures::generateUUID(),
                                        "product" => "story_camera_clips_v2",
                                        "song_name" => $clips_metadata['music_info'][$nextpost->p_2]['title'],
                                        "artist_name" => $clips_metadata['music_info'][$nextpost->p_2]['display_artist']
                                    ];
                                }
                            }
                        }
                    } elseif ($original_product_type == "igtv") {
                        $video = new \InstagramAPI\Media\Video\InstagramVideo($file_path, [
                            $nextpost->p_1 => \InstagramAPI\Constants::FEED_TV,
                            $nextpost->p_2 => \InstagramAPI\Media\InstagramMedia::CROP,
                            $nextpost->p_3 => true
                        ]);

                        $metadata['share_to_feed'] = true;
                    } else {
                        $video = new \InstagramAPI\Media\Video\InstagramVideo($file_path, [
                            $nextpost->p_1 => \InstagramAPI\Constants::FEED_TIMELINE,
                            $nextpost->p_2 => \InstagramAPI\Media\InstagramMedia::CROP,
                            $nextpost->p_3 => true
                        ]);
                    }

                    if ($sc->get("metadata_user")) {
                        $usertag = [
                            [
                                "position" => [0.0, 0.0],
                                "user_id" => $feed_item->getUser()->getPk()
                            ]
                        ];
                        $metadata["usertags"] = ["in" => $usertag];
                    }

                    if (!empty($custom_usertags_array)) {
                        if ($sc->get("metadata_user")) {
                            $used_usertag_id = $usertag[0]["user_id"];
                        } else {
                            $used_usertag_id = null;
                        }
                        foreach ($custom_usertags_array as $key_c => $cua) {
                            if ($used_usertag_id == $cua["user_id"]) {
                                // Skip this user
                                continue;
                            }
                            if (!isset($metadata["usertags"]["in"])) {
                                $metadata["usertags"] = ["in" => []];
                            }
                            array_push($metadata["usertags"]["in"], [
                                "position" => [0.0, 0.0],
                                "user_id" => $cua["user_id"]
                            ]);
                        }
                    }

                    // $Log->set("data.debug.usertags", json_encode($metadata["usertags"], JSON_PRESERVE_ZERO_FRACTION));

                    $is_connected = false;
                    $is_connected_count = 0;
                    $is_connected_fail = false;
                    do {
                        if ($is_connected_count >= 3) {
                            $is_connected_fail = true;
                        }
                        try {
                            $res = null;
                            if ($original_product_type == "clips") {
                                $res = $Instagram->reels->uploadVideo($video->getFile(), $metadata);
                            } elseif ($original_product_type == "igtv") {
                                $res = $Instagram->tv->uploadVideo($video->getFile(), $metadata);
                            } else {
                                $res = $Instagram->timeline->uploadVideo($video->getFile(), $metadata);
                            }
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                throw $e;
                            }

                            // DOUBLE POST PREVENTION

                            // Set default proxy connection
                            if ($Account->get("proxy")) {
                                $Instagram->setProxy($Account->get("proxy"));
                            }

                            // Check is media published or not
                            sleep(5);
                            $is_connected_sf = false;
                            $is_connected_count_sf = 0;
                            do {
                                try {
                                    $self_feed = $Instagram->timeline->getSelfUserFeed();
                                    if (!empty($self_feed->getItems())) {
                                        $last_taken_at = $self_feed->getItems()[0]->getTakenAt();
                                        $last_window = time() - 600;
                                        if ($last_taken_at >= $last_window) {
                                            $self_feed_item = $self_feed->getItems()[0];
                                            $already_posted = true;
                                        }
                                    }
                                    $is_connected = true;
                                    $is_connected_sf = true;
                                } catch (\InstagramAPI\Exception\NetworkException $e) {
                                    if ($is_connected_count_sf >= 3) {
                                        throw $e;
                                    }
                                    sleep(7);
                                } catch (\InstagramAPI\Exception\BadRequestException $e) {
                                    throw $e;
                                } catch (\InstagramAPI\Exception\InstagramException $e) {
                                    throw $e;
                                } catch (\Exception $e) {
                                    throw $e;
                                }
                                $is_connected_count_sf += 1;
                            } while (!$is_connected_sf);

                            // Set mobile proxy connection
                            if ($custom_proxy) {
                                $Instagram->setProxy($custom_proxy);
                            }
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);
                } else {
                    // Tag owner of media on photo 
                    if ($sc->get("metadata_user")) {
                        $usertag = [
                            [
                                "position" => [0.5, 0.5],
                                "user_id" => $feed_item->getUser()->getPk()
                            ]
                        ];
                        $metadata["usertags"] = ["in" => $usertag];
                    }

                    if (!empty($custom_usertags_array)) {
                        if ($sc->get("metadata_user")) {
                            $used_usertag_id = $usertag[0]["user_id"];
                        } else {
                            $used_usertag_id = null;
                        }
                        foreach ($custom_usertags_array as $key_c => $cua) {
                            if ($used_usertag_id == $cua["user_id"]) {
                                // Skip this user
                                continue;
                            }
                            if (!isset($metadata["usertags"]["in"])) {
                                $metadata["usertags"] = ["in" => []];
                            }
                            array_push($metadata["usertags"]["in"], [
                                "position" => [$cua["position"][0], $cua["position"][1]],
                                "user_id" => $cua["user_id"]
                            ]);
                        }
                    }

                    $img_cropped = new \InstagramAPI\Media\Photo\InstagramPhoto($file_path, [
                        $nextpost->p_1 => \InstagramAPI\Constants::FEED_TIMELINE,
                        $nextpost->p_2 => \InstagramAPI\Media\InstagramMedia::CROP,
                        $nextpost->p_3 => true
                    ]);

                    $is_connected = false;
                    $is_connected_count = 0;
                    $fail_message =  __("We couldn't establish connection with Instagram 7 times. Please try again later.");

                    do {
                        try {
                            $res = null;
                            $res = $Instagram->timeline->uploadPhoto($img_cropped->getFile(), $metadata);
                            $is_connected = true;
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_count >= 3) {
                                throw $e;
                            }

                            // DOUBLE POST PREVENTION

                            // Set default proxy connection
                            if ($Account->get("proxy")) {
                                $Instagram->setProxy($Account->get("proxy"));
                            }

                            // Check is media published or not
                            sleep(5);
                            $is_connected_sf = false;
                            $is_connected_count_sf = 0;
                            do {
                                try {
                                    $self_feed = $Instagram->timeline->getSelfUserFeed();
                                    if (!empty($self_feed->getItems())) {
                                        $last_taken_at = $self_feed->getItems()[0]->getTakenAt();
                                        $last_window = time() - 600;
                                        if ($last_taken_at >= $last_window) {
                                            $self_feed_item = $self_feed->getItems()[0];
                                            $already_posted = true;
                                        }
                                    }
                                    $is_connected = true;
                                    $is_connected_sf = true;
                                } catch (\InstagramAPI\Exception\NetworkException $e) {
                                    if ($is_connected_count_sf >= 3) {
                                        throw $e;
                                    }
                                    sleep(7);
                                } catch (\InstagramAPI\Exception\BadRequestException $e) {
                                    throw $e;
                                } catch (\InstagramAPI\Exception\InstagramException $e) {
                                    throw $e;
                                } catch (\Exception $e) {
                                    throw $e;
                                }
                                $is_connected_count_sf += 1;
                            } while (!$is_connected_sf);

                            // Set mobile proxy connection
                            if ($custom_proxy) {
                                $Instagram->setProxy($custom_proxy);
                            }
                        } catch (\InstagramAPI\Exception\BadRequestException $e) {
                            throw $e;
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw $e;
                        }

                        $is_connected_count += 1;
                    } while (!$is_connected);
                }
            }
        } catch (\InstagramAPI\Exception\NetworkException $e) {
            $msg = $e->getMessage();

            if (count($downloaded_media) > 1) {
                $Log->set("data.debug.action", "uploadAlbum 1");
            } else {
                $m = $downloaded_media[0];
                $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                if (in_array($ext, ["mp4"])) {
                    $Log->set("data.debug.action", "uploadVideo 1");
                } else {
                    $Log->set("data.debug.action", "uploadPhoto 1");
                }
            }

            // Empty response counter
            $sc->set("data.empty_response_counter", $empty_response_counter)->save();

            if ($empty_response_counter > 3) {
                $Log->set("data.error.msg", __("An error occured during reposting the media."))
                    ->set("data.error.details", $msg)
                    ->set("data.debug.original_media_code", $feed_item->getCode())
                    ->set("data.debug.code", $e->getCode())
                    ->set("data.debug.file", $e->getFile())
                    ->set("data.debug.line", $e->getLine())
                    ->set("data.debug.string", $e->__toString())
                    ->save();
            } else {
                $Log->set("data.error.msg", __("Instagram sent us empty response and we automatically re-scheduled repost for the account for 10 minutes") . " (" . __("Network Exception") . ")")
                    ->set("data.error.details", $msg)
                    ->set("data.debug.original_media_code", $feed_item->getCode())
                    ->set("data.debug.code", $e->getCode())
                    ->set("data.debug.file", $e->getFile())
                    ->set("data.debug.line", $e->getLine())
                    ->set("data.debug.string", $e->__toString())
                    ->save();

                // Re-schedule action because of \NetworkException
                $sc->set("schedule_date", date("Y-m-d H:i:s", time() + 600))
                    ->save();
            }

            // Remove downloaded media files
            foreach ($downloaded_media as $r) {
                @unlink(TEMP_PATH . "/" . $r);
            }

            continue;
        } catch (\InstagramAPI\Exception\EmptyResponseException $e) {
            $msg = $e->getMessage();

            if (count($downloaded_media) > 1) {
                $Log->set("data.debug.action", "uploadAlbum 1");
            } else {
                $m = $downloaded_media[0];
                $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                if (in_array($ext, ["mp4"])) {
                    $Log->set("data.debug.action", "uploadVideo 1");
                } else {
                    $Log->set("data.debug.action", "uploadPhoto 1");
                }
            }

            // Empty response counter
            $sc->set("data.empty_response_counter", $empty_response_counter)->save();

            if ($empty_response_counter > 3) {
                $Log->set("data.error.msg", __("An error occured during reposting the media."))
                    ->set("data.error.details", $msg)
                    ->set("data.debug.original_media_code", $feed_item->getCode())
                    ->set("data.debug.code", $e->getCode())
                    ->set("data.debug.file", $e->getFile())
                    ->set("data.debug.line", $e->getLine())
                    ->set("data.debug.string", $e->__toString())
                    ->save();
            } else {
                $Log->set("data.error.msg", __("Instagram sent us empty response and we automatically re-scheduled repost for the account for 10 minutes") . " (" . __("Empty Response Exception") . ")")
                    ->set("data.error.details", $msg)
                    ->set("data.debug.original_media_code", $feed_item->getCode())
                    ->set("data.debug.code", $e->getCode())
                    ->set("data.debug.file", $e->getFile())
                    ->set("data.debug.line", $e->getLine())
                    ->set("data.debug.string", $e->__toString())
                    ->save();

                // Re-schedule action because of \NetworkException
                $sc->set("schedule_date", date("Y-m-d H:i:s", time() + 600))
                    ->save();
            }

            // Remove downloaded media files
            foreach ($downloaded_media as $r) {
                @unlink(TEMP_PATH . "/" . $r);
            }

            continue;
        } catch (\InstagramAPI\Exception\InstagramException $e) {
            $msg = $e->getMessage();

            if (count($downloaded_media) > 1) {
                $Log->set("data.debug.action", "uploadAlbum 1");
            } else {
                $m = $downloaded_media[0];
                $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                if (in_array($ext, ["mp4"])) {
                    $Log->set("data.debug.action", "uploadVideo 1");
                } else {
                    $Log->set("data.debug.action", "uploadPhoto 1");
                }
            }

            if (strpos($msg, 'All retries have failed.') !== false || strpos($msg, 'Unknown Server Error.') !== false) {
                // Empty response counter
                $sc->set("data.empty_response_counter", $empty_response_counter)->save();

                if ($empty_response_counter > 3) {
                    $Log->set("data.error.msg", __("An error occured during reposting the media."))
                        ->set("data.error.details", $msg)
                        ->set("data.debug.original_media_code", $feed_item->getCode())
                        ->set("data.debug.code", $e->getCode())
                        ->set("data.debug.file", $e->getFile())
                        ->set("data.debug.line", $e->getLine())
                        ->set("data.debug.string", $e->__toString())
                        ->save();
                } else {
                    $Log->set("data.error.msg", __("We couldn't upload post to Instagram and automatically re-scheduled repost for the account for 1 hour") . " (" . __("Instagram Exception") . ")")
                        ->set("data.error.details", $msg)
                        ->set("data.debug.original_media_code", $feed_item->getCode())
                        ->set("data.debug.code", $e->getCode())
                        ->set("data.debug.file", $e->getFile())
                        ->set("data.debug.line", $e->getLine())
                        ->set("data.debug.string", $e->__toString())
                        ->save();

                    // Re-schedule action because of \InstagramException
                    $sc->set("schedule_date", date("Y-m-d H:i:s", time() + 3600))
                        ->save();
                }

                // Remove downloaded media files
                foreach ($downloaded_media as $r) {
                    @unlink(TEMP_PATH . "/" . $r);
                }

                continue;
            }

            $Log->set("data.error.msg", __("An error occured during reposting the media."))
                ->set("data.error.details", $msg)
                ->set("data.debug.original_media_code", $feed_item->getCode())
                ->set("data.debug.code", $e->getCode())
                ->set("data.debug.file", $e->getFile())
                ->set("data.debug.line", $e->getLine())
                ->set("data.debug.string", $e->__toString())
                ->save();

            // Remove downloaded media files
            foreach ($downloaded_media as $r) {
                @unlink(TEMP_PATH . "/" . $r);
            }

            continue;
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            if (count($downloaded_media) > 1) {
                $Log->set("data.debug.action", "uploadAlbum 2");
            } else {
                $m = $downloaded_media[0];
                $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                if (in_array($ext, ["mp4"])) {
                    $Log->set("data.debug.action", "uploadVideo 2");
                } else {
                    $Log->set("data.debug.action", "uploadPhoto 2");
                }
            }

            // Empty response counter
            $sc->set("data.empty_response_counter", 0)->save();

            $Log->set("data.error.msg", __("An error occured during reposting the media."))
                ->set("data.error.details", $msg)
                ->set("data.debug.original_media_code", $feed_item->getCode())
                ->set("data.debug.code", $e->getCode())
                ->set("data.debug.file", $e->getFile())
                ->set("data.debug.line", $e->getLine())
                ->set("data.debug.string", $e->__toString())
                ->save();

            // Remove downloaded media files
            foreach ($downloaded_media as $r) {
                @unlink(TEMP_PATH . "/" . $r);
            }

            continue;
        }

        // Empty response counter
        $sc->set("data.empty_response_counter", 0)->save();

        // Set default proxy connection
        if ($Account->get("proxy")) {
            $Instagram->setProxy($Account->get("proxy"));
        }

        if (!$already_posted) {
            if (!isset($res)) {
                $Log->set("data.error.msg", __("An error occured during reposting the media.") . " " . __("We re-scheduled repost for the account for 10 minutes."))
                    ->set("data.error.details", __("Instagram didn't return the expected result. Response value is empty."))
                    ->save();

                // Re-schedule repost
                $sc->set("schedule_date", date("Y-m-d H:i:s", time() + 600))
                    ->save();

                // Remove downloaded media files
                foreach ($downloaded_media as $r) {
                    @unlink(TEMP_PATH . "/" . $r);
                }

                continue;
            } elseif (!$res->isOk()) {
                $Log->set("data.error.msg", __("An error occured during reposting the media.") . " " .  __("We re-scheduled repost for the account for 10 minutes."))
                    ->set("data.error.details", json_encode($res))
                    ->save();

                // Re-schedule repost
                $sc->set("schedule_date", date("Y-m-d H:i:s", time() + 600))
                    ->save();

                // Remove downloaded media files
                foreach ($downloaded_media as $r) {
                    @unlink(TEMP_PATH . "/" . $r);
                }

                continue;
            }
        }

        // Reposted media succesfully
        // Save log
        $thumb = null;
        if (null !== $feed_item->getImageVersions2()) {
            $thumb = $feed_item->getImageVersions2()->getCandidates()[0]->getUrl();
        } else if (null !== $feed_item->getCarouselMedia()) {
            $thumb = $feed_item->getCarouselMedia()[0]->getImageVersions2()->getCandidates()[0]->getUrl();
        }

        $Log->set("data.trigger", $target);

        $Log->set("data.grabbed", [
            "media_id" => $feed_item->getId(),
            "media_code" => $feed_item->getCode(),
            "media_type" => $feed_item->getMediaType(),
            "product_type" => $feed_item->getProductType(),
            "media_thumb" => $thumb,
            "user" => [
                "pk" => $feed_item->getUser()->getPk(),
                "username" => $feed_item->getUser()->getUsername(),
                "full_name" => $feed_item->getUser()->getFullName()
            ]
        ]);

        $Log->set("data.reposted", [
            "upload_id" => $already_posted ? $self_feed_item->getId() : $res->getUploadId(),
            "media_pk" => $already_posted ? $self_feed_item->getPk() : $res->getMedia()->getPk(),
            "media_id" => $already_posted ? $self_feed_item->getId() : $res->getMedia()->getId(),
            "media_code" => $already_posted ? $self_feed_item->getCode() : $res->getMedia()->getCode()
        ]);

        $Log->set("status", "success")
            ->set("original_media_code", $feed_item->getCode());


        if ($sc->get("remove_delay") > 0) {
            $Log->set("is_removable", 1)
                ->set("remove_scheduled", date("Y-m-d H:i:s", time() + $sc->get("remove_delay")));
        }

        $Log->save();

        // Safety delay
        sleep(3);

        // Check is post published or deleted
        $is_post_published = false;
        $media_check = 0;
        $media_check_count = 0;
        $post_id = $already_posted ? $self_feed_item->getId() : $res->getMedia()->getId();
        do {
            try {
                $media_resp = $Instagram->media->getInfo($post_id);
                $media_check = 1;
                $is_post_published = true;
            } catch (\InstagramAPI\Exception\NetworkException $e) {
                // Couldn't connect to Instagram account because of network or connection error
                // Do nothing, just try again
                if ($media_check_count > 5) {
                    $media_check = 1;
                } else {
                    sleep(7);
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                $media_check = 1;
            } catch (\Exception $e) {
                $media_check = 1;
            }
            $media_check_count += 1;
        } while (!$media_check);

        // If post caption is empty, we will try to add this caption again
        if ($media_check && isset($media_resp) && !empty($caption)) {
            $get_caption = $media_resp->getItems()[0]->getCaption();
            if (empty($get_caption)) {
                set_time_limit(300);

                $Log->set("status_caption", "in-action")
                    ->save();

                // Safety delay
                sleep(3);

                $caption_check = 0;
                $caption_check_count = 0;
                $caption_relogin = 0;
                $caption_relogin_count = 0;

                $is_connected_fail = false;

                do {
                    if ($caption_relogin_count >= 3) {
                        $is_connected_fail = true;
                    }
                    $caption_relogin_count += 1;

                    // Login to Instagram again
                    try {
                        $Instagram = \InstagramController::login($Account);

                        // Approve (Confirm it was you) a suspicious login in Security tab in Instagram
                        if (method_exists($Instagram->account, 'getLoginActivity')) {
                            $login_activity = $Instagram->account->getLoginActivity();
                            $suspicious_login = $login_activity->getSuspiciousLogins();
                            if (isset($suspicious_login[0])) {
                                $approve_login_resp = $Instagram->account->approveSuspiciousLogin($suspicious_login[0]->getId(), $suspicious_login[0]->getTimestamp());
                            }
                        }

                        $caption_relogin = 1;
                    } catch (\InstagramAPI\Exception\NetworkException $e) {
                        if ($is_connected_fail) {
                            $caption_relogin = 1;
                            $Log->set("status_caption", "error")
                                ->set("data.caption_relogin.msg", $e->getMessage())
                                ->set("data.caption_relogin.is_ok", false)
                                ->save();
                        } else {
                            sleep(7);
                        }
                    } catch (\InstagramAPI\Exception\InstagramException $e) {
                        $msg = $e->getMessage();
                        $Log->set("status_caption", "error")
                            ->set("data.caption_relogin.msg", $e->getMessage())
                            ->set("data.caption_relogin.is_ok", false)
                            ->save();
                        $caption_relogin = 1;
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();
                        $Log->set("status_caption", "error")
                            ->set("data.caption_relogin.msg", $e->getMessage())
                            ->set("data.caption_relogin.is_ok", false)
                            ->save();
                        $caption_relogin = 1;
                    }
                    $caption_relogin_count += 1;
                } while (!$caption_relogin);

                if (!$is_connected_fail) {
                    $is_connected_fail = false;

                    // Set mobile proxy connection
                    if ($custom_proxy) {
                        $Instagram->setProxy($custom_proxy);
                    }

                    do {
                        if ($caption_check_count >= 7) {
                            $is_connected_fail = true;
                        }
                        $caption_check_count += 1;

                        try {
                            $caption_resp = $Instagram->media->edit($post_id, $caption);
                            $caption_check = 1;

                            $Log->set("status_caption", "success-2")
                                ->save();
                        } catch (\InstagramAPI\Exception\NetworkException $e) {
                            if ($is_connected_fail) {
                                $caption_check = 1;
                                $Log->set("status_caption", "error")
                                    ->set("data.caption_edit.msg", $e->getMessage())
                                    ->set("data.caption_edit.is_ok", false)
                                    ->save();
                            } else {
                                sleep(7);
                            }
                        } catch (\InstagramAPI\Exception\InstagramException $e) {
                            $msg = $e->getMessage();
                            $Log->set("status_caption", "error")
                                ->set("data.caption_edit.msg", $e->getMessage())
                                ->set("data.caption_edit.is_ok", false)
                                ->save();
                            $caption_check = 1;
                        } catch (\Exception $e) {
                            $msg = $e->getMessage();
                            $Log->set("status_caption", "error")
                                ->set("data.caption_edit.msg", $e->getMessage())
                                ->set("data.caption_edit.is_ok", false)
                                ->save();
                            $caption_check = 1;
                        }
                    } while (!$caption_check);

                    // Set default proxy connection
                    if ($Account->get("proxy")) {
                        $Instagram->setProxy($Account->get("proxy"));
                    }
                }
            } else {
                $Log->set("status_caption", "success-1")
                    ->save();
            }
        }

        // Post first comment
        if ($media_check && isset($media_resp) && $first_comment) {
            // Delay & timeout
            set_time_limit(300);

            // Set mobile proxy connection
            if ($custom_proxy) {
                $Instagram->setProxy($custom_proxy);
            }

            // Try post first comment 7 times and skip this process, if this 7 retries unsuccessful
            // Mobile proxy connection break adaptation
            $is_connected_fc = false;
            $is_connected_count_fc = 0;
            do {
                try {
                    $post_id = $already_posted ? $self_feed_item->getId() : $res->getMedia()->getId();
                    $first_comment_resp = $Instagram->media->comment($post_id, $first_comment);

                    $is_connected_fc = 1;

                    $Log->set("status_first_comment", "success")
                        ->save();
                } catch (\InstagramAPI\Exception\NetworkException $e) {
                    if ($is_connected_count_fc >= 7) {
                        $is_connected_fc = 1;
                        $Log->set("status_first_comment", "error")
                            ->set("data.first_comment_fail", $e->getMessage())
                            ->save();
                    }
                    sleep(7);
                } catch (\InstagramAPI\Exception\InstagramException $e) {
                    $Log->set("status_first_comment", "error")
                        ->set("data.first_comment_fail", $e->getMessage())
                        ->save();
                    $is_connected_fc = 1;
                } catch (\Exception $e) {
                    $Log->set("status_first_comment", "error")
                        ->set("data.first_comment_fail", $e->getMessage())
                        ->save();
                    $is_connected_fc = 1;
                }
                $is_connected_count_fc += 1;
            } while (!$is_connected_fc);

            // Set default proxy connection
            if ($Account->get("proxy")) {
                $Instagram->setProxy($Account->get("proxy"));
            }
        }

        // Safety delay
        sleep(3);

        // Repost published media to Stories
        if ($media_check && isset($media_resp) && $sc->get("data.share_to_stories")) {
            $Log->set("status_share_to_stories", "in-action")
                ->save();

            // Set metadata
            $metadata_story = [];

            // Tag location in Story
            if ($sc->get("data.share_to_stories_location") && $location_name && isset($location)) {
                $metadata_story["location_sticker"] = [
                    "x"           => 0.5,
                    "y"           => 0.1,
                    "z"           => 1,
                    "width"       => 0.90,
                    "height"      => 0.1,
                    "rotation"    => 0.0,
                    "is_sticker"  => true,
                    "location_id" => $location->getExternalId(),
                    "type"        => "location"
                ];
                $metadata_story["location"] = $location;
            }

            if (isset($r_json->share_to_stories_link_value)) {
                $metadata_story["link"] = $r_json->share_to_stories_link_value;
            } else {
                $share_to_stories_link = $sc->get("data.share_to_stories_link");
                $share_to_stories_link_value = $sc->get("data.share_to_stories_link_value");
                if ($share_to_stories_link) {
                    if (!empty($share_to_stories_link_value)) {
                        $metadata_story["link"] = $share_to_stories_link_value;
                    } else {
                        $metadata_story["link"] = "https://www.instagram.com/p/" . ($already_posted ? $self_feed_item->getCode() : $res->getMedia()->getCode()) . "/";
                    }
                }
            }

            // Try share media to Instagram Stories 7 times and skip this process, if this 7 retries unsuccessful
            // Mobile proxy connection break adaptation
            $is_connected_sts = 0;
            $is_connected_sts_count = 0;
            do {
                // Create a square cropped image
                $customized_image = TEMP_PATH . "/" . uniqid(readableRandomString(8) . "-") . ".jpg";
                try {
                    // Increase max_execution_time
                    set_time_limit(600);
                    // Get first photo/video item
                    $m = $downloaded_media[0];
                    $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
                    $file_path = TEMP_PATH . "/" . $m;

                    if (in_array($ext, ["mp4"]) && !isset($thumbnail_path)) {
                        $story_video = new \InstagramAPI\Media\Video\InstagramVideo($file_path, [
                            "targetFeed" => \InstagramAPI\Constants::FEED_STORY,
                            "operation" => \InstagramAPI\Media\InstagramMedia::CROP
                        ]);

                        $res_story = null;
                        $res_story = $Instagram->story->uploadVideo($story_video->getFile(), $metadata_story);
                    } else {
                        if (isset($thumbnail_path)) {
                            $file_path = $thumbnail_path;
                        }

                        // Beautify story share
                        $cropped_image = new \claviska\SimpleImage;
                        $cropped_image
                            ->fromFile($file_path)
                            ->thumbnail(900, 900);

                        // Add format icon
                        if (count($downloaded_media) > 1) {
                            // Sidecar
                            $overlay_path = PLUGINS_PATH . "/" . IDNAME . "/assets/img/" . "instagram-post-sidecar.png";
                            $cropped_image->overlay($overlay_path);
                        } else {
                            if (in_array($ext, ["mp4"])) {
                                if ($original_product_type == "clips") {
                                    // Clips
                                    $overlay_path = PLUGINS_PATH . "/" . IDNAME . "/assets/img/" . "instagram-post-clips.png";
                                    $cropped_image->overlay($overlay_path);
                                } elseif ($original_product_type == "igtv") {
                                    // IGTV
                                    $overlay_path = PLUGINS_PATH . "/" . IDNAME . "/assets/img/" . "instagram-post-igtv.png";
                                    $cropped_image->overlay($overlay_path);
                                } else {
                                    // Video
                                    $overlay_path = PLUGINS_PATH . "/" . IDNAME . "/assets/img/" . "instagram-post-video.png";
                                    $cropped_image->overlay($overlay_path);
                                }
                            }
                        }

                        $image = new \claviska\SimpleImage;

                        $share_to_stories_disable_crop = $sc->get("data.share_to_stories_disable_crop") ? $sc->get("data.share_to_stories_disable_crop") : false;

                        if ($share_to_stories_disable_crop) {
                            $image
                                ->fromFile($file_path)
                                ->thumbnail(1080, 1920);

                            // Tag published media in Story
                            $post_pk = $already_posted ? $self_feed_item->getPk() : $res->getMedia()->getPk();
                            $metadata_story["attached_media"] = [
                                [
                                    "x"           => 0.5,
                                    "y"           => 0.5,
                                    "z"           => 1,
                                    "width"       => 1.0,
                                    "height"      => 1.0,
                                    "rotation"    => 0.0,
                                    "is_sticker"  => true,
                                    "media_id"    => $post_pk,
                                    "type"        => "feed_media"
                                ]
                            ];
                        } else {
                            $image
                                ->fromFile($file_path)
                                ->thumbnail(1080, 1920)
                                ->blur("gaussian", 50)
                                ->overlay($cropped_image);

                            // Tag published media in Story
                            $post_pk = $already_posted ? $self_feed_item->getPk() : $res->getMedia()->getPk();
                            $metadata_story["attached_media"] = [
                                [
                                    "x"           => 0.5,
                                    "y"           => 0.5,
                                    "z"           => 1,
                                    "width"       => round(900 / 1080, 6),
                                    "height"      => round(900 / 1920, 6),
                                    "rotation"    => 0.0,
                                    "is_sticker"  => true,
                                    "media_id"    => $post_pk,
                                    "type"        => "feed_media"
                                ]
                            ];
                        }

                        // Custom username value
                        $sts_username = $sc->get("data.share_to_stories_username_value") ? $sc->get("data.share_to_stories_username_value") : $Account->get("username");
                        $sts_user_id = $Account->get("instagram_id");
                        if ($sts_username !== $Account->get("username")) {
                            $user_not_found = false;
                            $reason = "";
                            try {
                                $userinfo = $Instagram->people->getInfoByName($sts_username)->getUser();
                                if (isset($userinfo)) {
                                    $sts_user_id = $userinfo->getPk();
                                } else {
                                    $NewLog = new LogModel;
                                    $NewLog->set("user_id", $User->get("id"))
                                        ->set("account_id", $Account->get("id"))
                                        ->set("status", "error")
                                        ->set("data.error.msg", __("User ID for custom username value %s not found", $sts_username))
                                        ->set("data.error.details", __("We will use your main account username for mention sticker."))
                                        ->set("data.error.details", __("We will use your account username for mention sticker."))
                                        ->save();
                                    $user_not_found = true;
                                }
                            } catch (\InstagramAPI\Exception\NetworkException $e) {
                                throw $e;
                            } catch (\InstagramAPI\Exception\NotFoundException $e) {
                                $NewLog = new LogModel;
                                $NewLog->set("user_id", $User->get("id"))
                                    ->set("account_id", $Account->get("id"))
                                    ->set("status", "error")
                                    ->set("data.error.msg", __("User ID for custom username value %s not found", $sts_username))
                                    ->set("data.error.details", __("We will use your main account username for mention sticker."))
                                    ->save();
                                $user_not_found = true;
                            } catch (\InstagramAPI\Exception\InstagramException $e) {
                                $msg = $e->getMessage();
                                $NewLog = new LogModel;
                                $NewLog->set("user_id", $User->get("id"))
                                    ->set("account_id", $Account->get("id"))
                                    ->set("status", "error")
                                    ->set("data.error.msg", __("User ID for custom username value %s not detected", $sts_username))
                                    ->set("data.error.details", __("We will use your main account username for mention sticker.") . " " . __("Reason") . ": " . $$msg)
                                    ->save();
                                $user_not_found = true;
                            } catch (\Exception $e) {
                                $msg = $e->getMessage();
                                $NewLog = new LogModel;
                                $NewLog->set("user_id", $User->get("id"))
                                    ->set("account_id", $Account->get("id"))
                                    ->set("status", "error")
                                    ->set("data.error.msg", __("User ID for custom username value %s not detected", $sts_username))
                                    ->set("data.error.details", __("We will use your main account username for mention sticker.") . " " . __("Reason") . ": " . $$msg)
                                    ->save();
                                $user_not_found = true;
                            }
                            if ($user_not_found) {
                                $sts_username = $Account->get("username");
                                $sts_user_id = $Account->get("instagram_id");
                            }
                        }

                        if ($sc->get("data.share_to_stories_media_owner") && $sc->get("data.share_to_stories_main_account")) {
                            if ($sc->get("data.share_to_stories_media_owner")) {
                                $user_sticker = [
                                    "fontFile" => __DIR__ . "/assets/fonts/ig-regular-modified.ttf",
                                    "size" => 70,
                                    "color" => "#ff6940",
                                    "anchor" => "center",
                                    "xOffset" => 0,
                                    "yOffset" => 550
                                ];
                                $mention_options = [];

                                $image
                                    ->text(
                                        strtoupper("@" . $feed_item->getUser()->getUsername()),
                                        $user_sticker,
                                        $mention_options
                                    )
                                    ->roundedRectangle(
                                        $mention_options["x1"] - 28,
                                        $mention_options["y1"] - 24,
                                        $mention_options["x2"] + 28,
                                        $mention_options["y2"] + 16,
                                        17,
                                        "#ffffff",
                                        "filled"
                                    )
                                    ->text(
                                        strtoupper("@" . $feed_item->getUser()->getUsername()),
                                        $user_sticker
                                    );

                                $stories_media_owner = [
                                    "width"       => round(($mention_options["width"] + 56) / 1080, 6),
                                    "height"      => round(($mention_options["height"] + 40) / 1920, 6),
                                    "x"           => 0.5,
                                    "y"           => round(($mention_options["y1"] - 24 + ($mention_options["height"] + 40) / 2) / 1920, 6),
                                    "z"           => 1,
                                    "rotation"    => 0.0,
                                    "is_sticker"  => true,
                                    "user_id"     => $feed_item->getUser()->getPk(),
                                    "type"        => "mention"
                                ];
                                $metadata_story["story_mentions"] = [
                                    $stories_media_owner
                                ];
                            }
                            if ($sc->get("data.share_to_stories_main_account")) {
                                $mu_sticker_invisible = [
                                    "fontFile" => __DIR__ . "/assets/fonts/ig-regular-modified.ttf",
                                    "size" => 50,
                                    "color" => "white|0",
                                    "anchor" => "center",
                                    "xOffset" => 0,
                                    "yOffset" => 670
                                ];
                                $mu_sticker = [
                                    "fontFile" => __DIR__ . "/assets/fonts/ig-regular-modified.ttf",
                                    "size" => 50,
                                    "color" => "white",
                                    "anchor" => "center",
                                    "xOffset" => 0,
                                    "yOffset" => 670
                                ];
                                $mm_options = [];

                                $image
                                    ->text(
                                        strtoupper("@" . $sts_username),
                                        $mu_sticker_invisible,
                                        $mm_options
                                    )
                                    ->roundedRectangle(
                                        $mm_options["x1"] - 26,
                                        $mm_options["y1"] - 18,
                                        $mm_options["x2"] + 26,
                                        $mm_options["y2"] + 14,
                                        15,
                                        "white|0.5",
                                        "filled"
                                    )
                                    ->text(
                                        strtoupper("@" . $sts_username),
                                        $mu_sticker
                                    );

                                $stories_main_account = [
                                    "width"       => round(($mm_options["width"] + 52) / 1080, 6),
                                    "height"      => round(($mm_options["height"] + 30) / 1920, 6),
                                    "x"           => 0.5,
                                    "y"           => round(($mm_options["y1"] - 18 + ($mm_options["height"] + 30) / 2) / 1920, 6),
                                    "z"           => 1,
                                    "rotation"    => 0.0,
                                    "is_sticker"  => true,
                                    "user_id"     => $sts_user_id,
                                    "type"        => "mention"
                                ];

                                if (isset($stories_media_owner)) {
                                    $metadata_story["story_mentions"] = [
                                        $stories_media_owner,
                                        $stories_main_account
                                    ];
                                } else {
                                    $metadata_story["story_mentions"] = [
                                        $stories_main_account
                                    ];
                                }
                            }
                        } elseif ($sc->get("data.share_to_stories_media_owner")) {
                            $user_sticker = [
                                "fontFile" => __DIR__ . "/assets/fonts/ig-regular-modified.ttf",
                                "size" => 70,
                                "color" => "#ff6940",
                                "anchor" => "center",
                                "xOffset" => 0,
                                "yOffset" => 550
                            ];
                            $mention_options = [];

                            $image
                                ->text(
                                    strtoupper("@" . $feed_item->getUser()->getUsername()),
                                    $user_sticker,
                                    $mention_options
                                )
                                ->roundedRectangle(
                                    $mention_options["x1"] - 28,
                                    $mention_options["y1"] - 24,
                                    $mention_options["x2"] + 28,
                                    $mention_options["y2"] + 16,
                                    17,
                                    "#ffffff",
                                    "filled"
                                )
                                ->text(
                                    strtoupper("@" . $feed_item->getUser()->getUsername()),
                                    $user_sticker
                                );

                            $stories_media_owner = [
                                "width"       => round(($mention_options["width"] + 56) / 1080, 6),
                                "height"      => round(($mention_options["height"] + 40) / 1920, 6),
                                "x"           => 0.5,
                                "y"           => round(($mention_options["y1"] - 24 + ($mention_options["height"] + 40) / 2) / 1920, 6),
                                "z"           => 1,
                                "rotation"    => 0.0,
                                "is_sticker"  => true,
                                "user_id"     => $feed_item->getUser()->getPk(),
                                "type"        => "mention"
                            ];
                            $metadata_story["story_mentions"] = [
                                $stories_media_owner
                            ];
                        } elseif ($sc->get("data.share_to_stories_main_account")) {
                            $user_sticker = [
                                "fontFile" => __DIR__ . "/assets/fonts/ig-regular-modified.ttf",
                                "size" => 70,
                                "color" => "#ff6940",
                                "anchor" => "center",
                                "xOffset" => 0,
                                "yOffset" => 550
                            ];
                            $mention_options = [];

                            $image
                                ->text(
                                    strtoupper("@" . $sts_username),
                                    $user_sticker,
                                    $mention_options
                                )
                                ->roundedRectangle(
                                    $mention_options["x1"] - 28,
                                    $mention_options["y1"] - 24,
                                    $mention_options["x2"] + 28,
                                    $mention_options["y2"] + 16,
                                    17,
                                    "#ffffff",
                                    "filled"
                                )
                                ->text(
                                    strtoupper("@" . $sts_username),
                                    $user_sticker
                                );

                            $stories_media_owner = [
                                "width"       => round(($mention_options["width"] + 56) / 1080, 6),
                                "height"      => round(($mention_options["height"] + 40) / 1920, 6),
                                "x"           => 0.5,
                                "y"           => round(($mention_options["y1"] - 24 + ($mention_options["height"] + 40) / 2) / 1920, 6),
                                "z"           => 1,
                                "rotation"    => 0.0,
                                "is_sticker"  => true,
                                "user_id"     => $sts_user_id,
                                "type"        => "mention"
                            ];
                            $metadata_story["story_mentions"] = [
                                $stories_media_owner
                            ];
                        }

                        $hashtag_value = $sc->get("data.share_to_stories_hashtag_value");
                        $hashtag_value = json_decode($hashtag_value);
                        if ($sc->get("data.share_to_stories_hashtag") && !empty($hashtag_value)) {
                            $fontFile = __DIR__ . "/assets/fonts/ig-regular-modified.ttf";

                            if (!empty($hashtag_value)) {
                                try {
                                    $ld = new Language();
                                    $detected_lang = $ld->detect($hashtag_value);
                                    if ($detected_lang == "ru") {
                                        $fontFile = __DIR__ . "/assets/fonts/ig-font-multilang.ttf";
                                    }
                                } catch (Exception $e) {
                                    // Skip all errors
                                }
                            }

                            $hashtag_value = removeEmoji($hashtag_value);
                            $hashtag_value = ltrim($hashtag_value, "#");
                            $hashtag_value = str_replace(' ', '', $hashtag_value);

                            $hashtag_sticker_invisible = [
                                "fontFile" => $fontFile,
                                "size" => 70,
                                "color" => "white|0",
                                "anchor" => "center",
                                "xOffset" => 0,
                                "yOffset" => -550
                            ];
                            $hashtag_sticker = [
                                "fontFile" => $fontFile,
                                "size" => 70,
                                "color" => "white",
                                "anchor" => "center",
                                "xOffset" => 0,
                                "yOffset" => -550
                            ];
                            $hashtag_options = [];

                            $image
                                ->text(
                                    mb_strtoupper("#" . $hashtag_value, 'UTF-8'),
                                    $hashtag_sticker_invisible,
                                    $hashtag_options
                                )
                                ->roundedRectangle(
                                    $hashtag_options["x1"] - 28,
                                    $hashtag_options["y1"] - 24,
                                    $hashtag_options["x2"] + 28,
                                    $hashtag_options["y2"] + 16,
                                    17,
                                    "white|0.5",
                                    "filled"
                                )
                                ->text(
                                    mb_strtoupper("#" . $hashtag_value, 'UTF-8'),
                                    $hashtag_sticker
                                );

                            $metadata_story["caption"] = "#" . $hashtag_value;
                            $metadata_story["hashtags"] = [
                                [
                                    "width"       => round(($hashtag_options["width"] + 56) / 1080, 6),
                                    "height"      => round(($hashtag_options["height"] + 40) / 1920, 6),
                                    "x"           => 0.5,
                                    "y"           => round(($hashtag_options["y1"] - 24 + ($hashtag_options["height"] + 40) / 2) / 1920, 6),
                                    "z"           => 1,
                                    "rotation"    => 0.0,
                                    "is_sticker"  => true,
                                    "tag_name"    => $hashtag_value,
                                    "type"        => "hashtag"
                                ]
                            ];
                        }

                        $image->toFile($customized_image, "image/jpeg", 90);

                        $img_cropped_story = new \InstagramAPI\Media\Photo\InstagramPhoto($customized_image, [
                            "targetFeed" => \InstagramAPI\Constants::FEED_STORY,
                            "operation" => \InstagramAPI\Media\InstagramMedia::CROP
                        ]);

                        $res_story = null;
                        $res_story = $Instagram->story->uploadPhoto($img_cropped_story->getFile(), $metadata_story);
                        if (file_exists($customized_image)) {
                            unlink($customized_image);
                        }
                        if (isset($thumbnail_path)) {
                            if (file_exists($thumbnail_path)) {
                                unlink($thumbnail_path);
                            }
                        }
                    }
                    $is_connected_sts = 1;
                } catch (\InstagramAPI\Exception\NetworkException $e) {
                    if (file_exists($customized_image)) {
                        unlink($customized_image);
                    }
                    if (isset($thumbnail_path)) {
                        if (file_exists($thumbnail_path)) {
                            unlink($thumbnail_path);
                        }
                    }
                    if ($is_connected_sts_count >= 7) {
                        $is_connected_sts = 1;
                        $Log->set("status_share_to_stories", "error")
                            ->set("data.sts_fail", $e->getMessage())
                            ->save();
                    }
                    sleep(7);
                } catch (\InstagramAPI\Exception\InstagramException $e) {
                    if (file_exists($customized_image)) {
                        unlink($customized_image);
                    }
                    if (isset($thumbnail_path)) {
                        if (file_exists($thumbnail_path)) {
                            unlink($thumbnail_path);
                        }
                    }
                    $Log->set("status_share_to_stories", "error")
                        ->set("data.sts_fail", $e->getMessage())
                        ->save();
                    $is_connected_sts = 1;
                } catch (\Exception $e) {
                    if (file_exists($customized_image)) {
                        unlink($customized_image);
                    }
                    if (isset($thumbnail_path)) {
                        if (file_exists($thumbnail_path)) {
                            unlink($thumbnail_path);
                        }
                    }
                    $Log->set("status_share_to_stories", "error")
                        ->set("data.sts_fail", $e->getMessage())
                        ->save();
                    $is_connected_sts = 1;
                }
                $is_connected_sts_count += 1;
            } while (!$is_connected_sts);

            // Check is story published or deleted
            $is_story_published = false;
            if (!empty($res_story)) {
                // Safety delay
                sleep(3);

                $story_check = 0;
                $story_check_count = 0;
                $story_id = $res_story->getMedia()->getId();
                do {
                    try {
                        $story_check_resp = $Instagram->media->getInfo($story_id);
                        $story_check = 1;
                        $is_story_published = true;
                    } catch (\InstagramAPI\Exception\NetworkException $e) {
                        // Couldn't connect to Instagram account because of network or connection error
                        // Do nothing, just try again
                        if ($story_check_count > 5) {
                            $story_check = 1;
                        } else {
                            sleep(7);
                        }
                    } catch (\InstagramAPI\Exception\InstagramException $e) {
                        $story_check = 1;
                    } catch (\Exception $e) {
                        $story_check = 1;
                    }
                    $story_check_count += 1;
                } while (!$story_check);

                if ($is_story_published) {
                    $Log->set("status_share_to_stories", "success")
                        ->set("data.reposted_story", [
                            "media_code" => $res_story->getMedia()->getCode(),
                            "media_pk" => $res_story->getMedia()->getPk()
                        ])
                        ->save();
                }
            } else {
                $Log->set("status_share_to_stories", "error")
                    ->set("data.sts_fail_2", "res_story value is empty")
                    ->save();
            }
        }

        // Remove downloaded media files
        foreach ($downloaded_media as $r) {
            unlink(TEMP_PATH . "/" . $r);
        }

        // Share media to connected Facebook page
        if (isset($r_json->share_to_facebook_post)) {
            $share_to_facebook_post = $r_json->share_to_facebook_post;
        } else {
            $share_to_facebook_post = $sc->get("data.share_to_facebook_post");
        }
        if (isset($r_json->share_to_facebook_story)) {
            $share_to_facebook_story = $r_json->share_to_facebook_story;
        } else {
            $share_to_facebook_story = $sc->get("data.share_to_facebook_story");
        }
        if (isset($r_json->share_to_facebook_reel)) {
            $share_to_facebook_reel = $r_json->share_to_facebook_reel;
        } else {
            $share_to_facebook_reel = $sc->get("data.share_to_facebook_reel");
        }

        if ($share_to_facebook_post || $share_to_facebook_story || $share_to_facebook_reel) {
            // Define destination parameters
            $destination_id = null;
            $destination_name = null;
            $destination_type = null;

            $user_xposting = [];
            try {
                $user_xposting_resp = $Instagram->request('ig_fb_xposting/account_linking/user_xposting_destination/')
                    ->setSignedGet(true)
                    ->getResponse(new \InstagramAPI\Response\GenericResponse());

                if (!empty($user_xposting_resp)) {
                    $user_xposting = json_decode($user_xposting_resp);
                    if (!empty($user_xposting) && is_array($user_xposting)) {
                        $Log->set("data.share_to_facebook.user_xposting", $user_xposting);
                        if ($share_to_facebook_reel && $feed_item->getProductType() == "clips") {
                            $Log->set("data.share_to_facebook.reel_status", "in-action");
                        } elseif ($share_to_facebook_post) {
                            $Log->set("data.share_to_facebook.post_status", "in-action");
                        }
                        if ($share_to_facebook_story) {
                            $Log->set("data.share_to_facebook.story_status", "in-action");
                        }
                        $Log->save();

                        $destination_id = isset($user_xposting["xpost_destination"]["destination_id"]) ? $user_xposting["xpost_destination"]["destination_id"] : null;
                        $destination_name = isset($user_xposting["xpost_destination"]["destination_name"]) ? $user_xposting["xpost_destination"]["destination_name"] : null;
                        $destination_type = isset($user_xposting["xpost_destination"]["destination_type"]) ? $user_xposting["xpost_destination"]["destination_type"] : null;
                    }
                }
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                $user_xposting = [];
            } catch (\Exception $e) {
                $user_xposting = [];
            }

            if (empty($user_xposting) && (empty($destination_name) || empty($destination_id))) {
                try {
                    $self_user = $Instagram->people->getSelfInfo()->getUser();

                    $destination_name = $self_user->getPageName();
                    $destination_id = $self_user->getPageId();
                    $destination_type = "PAGE";

                    if (!empty($destination_name) && !empty($destination_id) && !empty($destination_type)) {
                        $Log->set("data.share_to_facebook.user_xposting.xpost_destination.destination_id", $destination_id)
                            ->set("data.share_to_facebook.user_xposting.xpost_destination.destination_name", $destination_name)
                            ->set("data.share_to_facebook.user_xposting.xpost_destination.destination_type", $destination_type);
                        if ($share_to_facebook_reel && $feed_item->getProductType() == "clips") {
                            $Log->set("data.share_to_facebook.reel_status", "in-action");
                        } elseif ($share_to_facebook_post) {
                            $Log->set("data.share_to_facebook.post_status", "in-action");
                        }
                        if ($share_to_facebook_story) {
                            $Log->set("data.share_to_facebook.story_status", "in-action");
                        }
                        $Log->save();
                    }
                } catch (\InstagramAPI\Exception\InstagramException $e) {
                    if ($share_to_facebook_reel && $feed_item->getProductType() == "clips") {
                        $Log->set("data.share_to_facebook.reel_status", "error");
                        $Log->set("data.stf_r_fail", $e->getMessage());
                    } elseif ($share_to_facebook_post) {
                        $Log->set("data.share_to_facebook.post_status", "error");
                        $Log->set("data.stf_p_fail", $e->getMessage());
                    }
                    if ($share_to_facebook_story) {
                        $Log->set("data.share_to_facebook.story_status", "error");
                        $Log->set("data.stf_s_fail", $e->getMessage());
                    }
                    $Log->save();
                } catch (\Exception $e) {
                    if ($share_to_facebook_reel && $feed_item->getProductType() == "clips") {
                        $Log->set("data.share_to_facebook.reel_status", "error");
                        $Log->set("data.stf_r_fail", $e->getMessage());
                    } elseif ($share_to_facebook_post) {
                        $Log->set("data.share_to_facebook.post_status", "error");
                        $Log->set("data.stf_p_fail", $e->getMessage());
                    }
                    if ($share_to_facebook_story) {
                        $Log->set("data.share_to_facebook.story_status", "error");
                        $Log->set("data.stf_s_fail", $e->getMessage());
                    }
                    $Log->save();
                }
            }

            if (empty($destination_name) || empty($destination_id) || empty($destination_type)) {
                if ($share_to_facebook_reel && $feed_item->getProductType() == "clips") {
                    $Log->set("data.share_to_facebook.reel_status", "error");
                    $Log->set("data.stf_r_fail", __("Facebook page not connected."));
                } elseif ($share_to_facebook_post) {
                    $Log->set("data.share_to_facebook.post_status", "error");
                    $Log->set("data.stf_p_fail", __("Facebook page not connected."));
                }
                if ($share_to_facebook_story) {
                    $Log->set("data.share_to_facebook.story_status", "error");
                    $Log->set("data.stf_s_fail", __("Facebook page not connected."));
                }
                $Log->save();
            } else {
                $post_media = $already_posted ? $self_feed_item : $res->getMedia();
                $post_text = "";
                $post_caption = $post_media->getCaption();
                if (isset($post_caption)) {
                    $post_text = $post_media->getCaption()->getText();
                }

                if ($share_to_facebook_reel && $feed_item->getProductType() == "clips") {
                    // Share reel to connected Facebook page
                    try {
                        $nextpost = repost_pro_nextpost_api($Instagram, "repost-pro-share");

                        $mediaShare_reel = mediaShareRepostPro(
                            $Instagram,
                            ($already_posted ? $self_feed_item->getPk() : $res->getMedia()->getPk()),
                            "",
                            [
                                $nextpost->p_1 => 1,
                                $nextpost->p_2 => $destination_type,
                                $nextpost->p_3 => $destination_id
                            ],
                            "ig_self_story"
                        );
                        if ($mediaShare_reel->getStatus() == "ok") {
                            $Log->set("data.share_to_facebook.reel_status", "success")->save();
                        }
                    } catch (\InstagramAPI\Exception\InstagramException $e) {
                        $Log->set("data.share_to_facebook.reel_status", "error")
                            ->set("data.stf_s_fail", $e->getMessage())
                            ->save();
                    } catch (\Exception $e) {
                        $Log->set("data.share_to_facebook.reel_status", "error")
                            ->set("data.stf_s_fail", $e->getMessage())
                            ->save();
                    }
                } elseif ($share_to_facebook_post) {
                    // Share post to connected Facebook page
                    try {
                        $nextpost = repost_pro_nextpost_api($Instagram, "repost-pro-share");

                        $mediaShare_post = mediaShareRepostPro(
                            $Instagram,
                            $post_id,
                            $post_text,
                            [
                                $nextpost->p_1 => 1,
                                $nextpost->p_2 => $destination_type,
                                $nextpost->p_3 => $destination_id
                            ]
                        );
                        if ($mediaShare_post->getStatus() == "ok") {
                            $Log->set("data.share_to_facebook.post_status", "success")->save();
                        }
                    } catch (\InstagramAPI\Exception\InstagramException $e) {
                        $Log->set("data.share_to_facebook.post_status", "error")
                            ->set("data.stf_p_fail", $e->getMessage())
                            ->save();
                    } catch (\Exception $e) {
                        $Log->set("data.share_to_facebook.post_status", "error")
                            ->set("data.stf_p_fail", $e->getMessage())
                            ->save();
                    }
                }

                // Share story to connected Facebook page
                if ($Log->get("status_share_to_stories") == "success" && $is_story_published) {
                    if ($is_story_published) {
                        if ($share_to_facebook_story) {
                            try {
                                $nextpost = repost_pro_nextpost_api($Instagram, "repost-pro-share");

                                $mediaShare_story = mediaShareRepostPro(
                                    $Instagram,
                                    $res_story->getMedia()->getPk(),
                                    "",
                                    [
                                        $nextpost->p_1 => 1,
                                        $nextpost->p_2 => $destination_type,
                                        $nextpost->p_3 => $destination_id
                                    ],
                                    "ig_self_story"
                                );
                                if ($mediaShare_story->getStatus() == "ok") {
                                    $Log->set("data.share_to_facebook.story_status", "success")->save();
                                }
                            } catch (\InstagramAPI\Exception\InstagramException $e) {
                                $Log->set("data.share_to_facebook.story_status", "error")
                                    ->set("data.stf_s_fail", $e->getMessage())
                                    ->save();
                            } catch (\Exception $e) {
                                $Log->set("data.share_to_facebook.story_status", "error")
                                    ->set("data.stf_s_fail", $e->getMessage())
                                    ->save();
                            }
                        }
                    }
                }
            }
        }

        // Collect analytics data about latest feed posts and save to specified log
        try {
            $self_feed = $Instagram->timeline->getSelfUserFeed();
            if (isset($self_feed)) {
                $feed_items = $self_feed->getItems();
                if (count($feed_items) > 0) {
                    foreach ($feed_items as $key => $f) {
                        $media_code = $f->getCode();
                        $LogData = new LogModel([
                            "user_id" => $User->get("id"),
                            "account_id" => $Account->get("id"),
                            "LIKE_data" => "%" . $media_code . "%",
                            "status" => "success"
                        ]);
                        if ($LogData->isAvailable()) {
                            $likes_count = (int)$f->getLikeCount();
                            $comments_count = (int)$f->getCommentCount();
                            $views_count = (int)$f->getViewCount();

                            $total_actions = $likes_count + $comments_count;

                            // Log last analytics update time 
                            $sc->set("data.last_analytics_update", time())
                                ->save();

                            // Log basic post insights info
                            $LogData->set("data.analytics.likes_count", $likes_count)
                                ->set("data.analytics.comments_count", $comments_count)
                                ->set("data.analytics.views_count", $views_count)
                                ->save();

                            //  Calculate and save ER post 
                            if ($total_actions > 0) {
                                $account_data = "data." . $Account->get("username");
                                $followers_count = $User->get($account_data . ".follower_count") ? (int)$User->get($account_data . ".follower_count") : 0;
                                if (empty($followers_count)) {
                                    // Get followers count
                                    $followers_count = $Instagram->people->getSelfInfo()->getUser()->getFollowerCount();
                                }
                                if ($followers_count > 0) {
                                    $er_post = ($total_actions / $followers_count) * 100;
                                    if ($er_post > 0) {
                                        $er_post = number_format($er_post, 2);
                                        $is_good = isGoodERPostRepostPro($er_post, $followers_count) ? true : false;
                                        $LogData->set("data.analytics.er_post", $er_post)
                                            ->set("data.analytics.er_post_is_good", $is_good)
                                            ->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\InstagramAPI\Exception\NetworkException $e) {
            // Skip 
        } catch (\InstagramAPI\Exception\InstagramException $e) {
            // Skip 
        } catch (\Exception $e) {
            // Skip 
        }
    }
}
//\Event::bind("cron.add", __NAMESPACE__ . "\addCronTask");
\Event::bind("cronrepost.multi", __NAMESPACE__ . "\addCronTask");

/**
 * Add cron task to remove the reposted post
 */
function addCronTaskRemove()
{
    // Get auto like schedules
    require_once __DIR__ . "/models/LogsModel.php";
    $Logs = new LogsModel;
    $Logs->where("is_removable", 1)
        ->where("status", "success")
        ->where("remove_scheduled", "<=", date("Y-m-d H:i:s"))
        ->setPageSize(5) // required to prevent server overload
        ->setPage(1)
        ->orderBy("id", "ASC")
        ->fetchData();

    if ($Logs->getTotalCount() < 1) {
        // There is not any active schedule
        return false;
    }

    $as = [__DIR__ . "/models/LogModel.php", __NAMESPACE__ . "\LogModel"];
    foreach ($Logs->getDataAs($as) as $Log) {
        $Account = \Controller::model("Account", $Log->get("account_id"));
        $User = \Controller::model("User", $Log->get("user_id"));

        if (!$Account->isAvailable() || !$User->isAvailable()) {
            // Account is either removed (unexected, external factors)
            // Remove the log
            $Log->remove();
            continue;
        }

        if ($User->get("id") != $Account->get("user_id")) {
            // Unexpected, data modified by external factors
            // Remove remove-schedule
            $Log->remove();
            continue;
        }

        // Defautls
        $Log->set("remove_date", date("Y-m-d H:i:s")); // Last action date for the remove action (success or fail)

        if ($Account->get("login_required")) {
            // Login required for this account
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", "Re-login required for the account!")
                ->save();
            continue;
        }

        if (!$User->get("is_active")) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("User account was not active."))
                ->save();
            continue;
        }

        if ($User->isExpired()) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("User account was expired."))
                ->save();
            continue;
        }


        // Login into the account
        try {
            $Instagram = \InstagramController::login($Account);
        } catch (\InstagramAPI\Exception\NetworkException $e) {
            // Re-schedule action because of /NetworkException
            $Log->set("remove_scheduled", date("Y-m-d H:i:s", time() + 30))
                //    ->set("data.remove.status", "error")
                //    ->set("data.remove.error.msg", "Action re-scheduled in 7 seconds")
                //    ->set("data.remove.error.details", "We couldn't connect to Instagram and automatically re-scheduled action for the account. Developer code: ARP-7-1.")
                ->save();
            continue;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = explode(":", $msg, 2);
            $msg = isset($msg[1]) ? $msg[1] : $msg[0];

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't login into the account."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        }

        // Get media info
        try {
            $is_connected = false;
            $is_connected_count = 0;
            do {
                try {
                    $mediainfo = $Instagram->media->getInfo($Log->get("data.reposted.media_id"));
                    $is_connected = true;
                } catch (\InstagramAPI\Exception\NetworkException $e) {
                    if ($is_connected_count >= 3) {
                        throw $e;
                    }
                    sleep(7);
                } catch (\InstagramAPI\Exception\BadRequestException $e) {
                    throw $e;
                } catch (\InstagramAPI\Exception\InstagramException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw $e;
                }

                $is_connected_count += 1;
            } while (!$is_connected);
        } catch (\InstagramAPI\Exception\InstagramException $e) {
            $msg = $e->getMessage();

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't get media info."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't get media info."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        }

        if (!$mediainfo->isOk()) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't get media info."))
                ->save();

            continue;
        }


        $media_type_id = $mediainfo->getItems()[0]->getMediaType();
        if ($media_type_id == "2") {
            $media_type = "VIDEO";
        } else if ($media_type_id == "8") {
            $media_type = "CAROUSEL";
        } else {
            $media_type = "PHOTO";
        }


        try {
            $is_connected = false;
            $is_connected_count = 0;
            $is_connected_fail = false;
            do {
                if ($is_connected_count >= 3) {
                    $is_connected_fail = true;
                }
                try {
                    $res = $Instagram->media->delete($Log->get("data.reposted.media_id"), $media_type);

                    $is_connected = true;
                } catch (\InstagramAPI\Exception\NetworkException $e) {
                    if ($is_connected_fail) {
                        throw $e;
                    }
                    sleep(7);
                } catch (\InstagramAPI\Exception\BadRequestException $e) {
                    throw $e;
                } catch (\InstagramAPI\Exception\InstagramException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw $e;
                }

                $is_connected_count += 1;
            } while (!$is_connected);
        } catch (\InstagramAPI\Exception\InstagramException $e) {
            $msg = $e->getMessage();

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't remove the post."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't remove the post."))
                ->set("data.remove.error.details", $msg)
                ->save();

            continue;
        }

        if (!$res->isOk() || !$res->getDidDelete()) {
            $Log->set("is_deleted", 0)
                ->set("is_removable", 0)
                ->set("data.remove.status", "error")
                ->set("data.remove.error.msg", __("Couldn't remove the post."))
                ->save();

            continue;
        }


        // Post removed successfully, log data
        $Log->set("is_deleted", 1)
            ->set("is_removable", 0)
            ->set("data.remove.status", "success")
            ->save();
    }
}
//\Event::bind("cron.add", __NAMESPACE__ . "\addCronTask");
\Event::bind("cronrepost.multi", __NAMESPACE__ . "\addCronTask");

/**
 * Remove Emoji from text
 */
function removeEmoji($text)
{
    $cleanText = "";
    // Match Emoticons 
    $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
    $cleanText = preg_replace($regexEmoticons, '', $text);
    // Match Miscellaneous Symbols and Pictographs
    $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
    $cleanText = preg_replace($regexSymbols, '', $cleanText);
    // Match Transport And Map Symbols 
    $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
    $cleanText = preg_replace($regexTransport, '', $cleanText);
    return $cleanText;
}

function isGoodERPostRepostPro($er, $followers)
{
    if ($followers <= 5000) {
        if ($er >= 5.60) {
            return true;
        } else {
            return false;
        }
    } elseif ($followers <= 20000) {
        if ($er >= 2.43) {
            return true;
        } else {
            return false;
        }
    } elseif ($followers <= 100000) {
        if ($er >= 2.15) {
            return true;
        } else {
            return false;
        }
    } elseif ($followers <= 1000000) {
        if ($er >= 2.05) {
            return true;
        } else {
            return false;
        }
    } else {
        if ($er >= 1.97) {
            return true;
        } else {
            return false;
        }
    }
}

function find_closest_date($date, $dates, $last)
{
    $interval               = array();
    $now                    = strtotime(date('Y-m-d'));

    foreach ($dates as $d) {
        $dateTime           = strtotime($date);
        $toTime             = strtotime($d);

        // Do not parse dates older than today
        if (strtotime($d) < $now) {
            continue 1;
        }

        // Only do dates in the future
        if ($toTime < $dateTime) {
            continue 1;
        }
        $interval[$d]       = abs($dateTime - $toTime);
    }

    // If there is no interval, use the latest date
    if (!count($interval)) {
        return $last;
    }

    asort($interval);
    $closest = key($interval);

    return $closest;
}

function mediaShareRepostPro(
    $Instagram,
    $mediaId,
    $caption = '',
    $share = [],
    $xpost_surface = ''
) {
    if (empty($share)) {
        throw new \Exception('Empty share data sent to mediaShare() function.');
    }

    if (!isset($share["share_to_facebook"]) && !isset($share["share_to_twitter"])) {
        throw new \Exception('Invalid share data sent to mediaShare() function.');
    }

    $request = $Instagram->request("media/{$mediaId}/share/")
        ->addPost('_csrftoken', $Instagram->client->getToken())
        ->addPost('_uuid', $Instagram->uuid)
        ->addPost('waterfall_id', \InstagramAPI\Signatures::generateUUID())
        ->addPost('media_id', $mediaId);

    if (!empty($caption)) {
        $request->addPost('caption', $caption);
    }

    if (!empty($xpost_surface)) {
        $request->addPost('xpost_surface', $xpost_surface);
    }

    if (isset($share["share_to_facebook"])) {
        $request->addPost('share_to_fb_destination_type', $share["share_to_fb_destination_type"])
            ->addPost('share_to_fb_destination_id', $share["share_to_fb_destination_id"])
            ->addPost('share_to_facebook', 1);
    }

    if (isset($share["share_to_twitter"])) {
        $request->addPost('twitter_username', $share["twitter_username"])
            ->addPost('twitter_access_token_secret', $share["twitter_access_token_secret"])
            ->addPost('twitter_access_token_key', $share["twitter_access_token_key"])
            ->addPost('share_to_twitter', 1);
    }

    return $request->getResponse(new \InstagramAPI\Response\GenericResponse());
}

/**
 * Check license with Nextpost API
 * @return void
 */
function repost_pro_nextpost_api(
    $Instagram,
    $action_type
) {
    $Settings = namespace\settings();
    $license_key = $Settings->get("data.license_key");

    if (empty($license_key)) {
        throw new \InstagramAPI\Exception\InstagramException(__("You must define a valid license key in module settings."));
    }

    $proxy = $Instagram->getProxy();
    $Instagram->setProxy(null);
    try {
        $resp = $Instagram->request("https://nextpost.tech/graftype-api")
            ->setNeedsAuth(false)
            ->setSignedPost(false)
            ->setAddDefaultHeaders(false)
            ->addPost('license_key', $license_key)
            ->addPost('action_type', $action_type)
            ->getResponse(new \InstagramAPI\Response\GenericResponse());
        $Instagram->setProxy($proxy);
    } catch (\InstagramAPI\Exception\InstagramException $e) {
        $Instagram->setProxy($proxy);
        throw $e;
    } catch (\Exception $e) {
        $Instagram->setProxy($proxy);
        throw $e;
    }

    $r = json_decode($resp);

    if (!isset($r->p_1) || !isset($r->p_2) || !isset($r->p_3)) {
        throw new \InstagramAPI\Exception\InstagramException('Missing parameters detected in reponse for Nextpost API request.');
    }

    return $r;
}
