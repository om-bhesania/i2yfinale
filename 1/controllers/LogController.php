<?php
namespace Plugins\RepostPro;

// Disable direct access
if (!defined('APP_VERSION')) 
    die("Yo, what's up?");

/**
 * Log Controller
 */
class LogController extends \Controller
{
    /**
     * idname of the plugin for internal use
     */
    const IDNAME = 'repost-pro';

    
    /**
     * Process
     */
    public function process()
    {
        $AuthUser = $this->getVariable("AuthUser");
        $Route = $this->getVariable("Route");
        $this->setVariable("idname", self::IDNAME);

        // Auth
        if (!$AuthUser){
            header("Location: ".APPURL."/login");
            exit;
        } else if ($AuthUser->isExpired()) {
            header("Location: ".APPURL."/expired");
            exit;
        }

        $user_modules = $AuthUser->get("settings.modules");
        if (!is_array($user_modules) || !in_array(self::IDNAME, $user_modules)) {
            // Module is not accessible to this user
            header("Location: ".APPURL."/post");
            exit;
        }


        // Get account
        $Account = \Controller::model("Account", $Route->params->id);
        if (!$Account->isAvailable() || 
            $Account->get("user_id") != $AuthUser->get("id")) 
        {
            header("Location: ".APPURL."/e/".self::IDNAME);
            exit;
        }
        $this->setVariable("Account", $Account);


        // Get Schedule
        require_once PLUGINS_PATH."/".self::IDNAME."/models/ScheduleModel.php";
        $Schedule = new ScheduleModel([
            "account_id" => $Account->get("id"),
            "user_id" => $Account->get("user_id")
        ]);
        $this->setVariable("Schedule", $Schedule);


        // Get Activity Log
        require_once PLUGINS_PATH."/".self::IDNAME."/models/LogsModel.php";
        $ActivityLog = new LogsModel;
        $ActivityLog->setPageSize(30)
                    ->setPage(\Input::get("page"))
                    ->where("user_id", "=", $AuthUser->get("id"))
                    ->where("account_id", "=", $Account->get("id"))
                    ->orderBy("id","DESC")
                    ->fetchData();

        $Logs = [];
        $as = [PLUGINS_PATH."/".self::IDNAME."/models/LogModel.php", 
               __NAMESPACE__."\LogModel"];
        foreach ($ActivityLog->getDataAs($as) as $l) {
            $Logs[] = $l;
        }

        $this->setVariable("ActivityLog", $ActivityLog)
             ->setVariable("Logs", $Logs);

        if (\Input::post("action") == "select-log") {
            $this->select_log();
        } elseif (\Input::post("action") == "bulk-select") {
            $this->bulk_select();
        } elseif (\Input::post("action") == "bulk-unselect") {
            $this->bulk_select(false);
        } elseif (\Input::post("action") == "bulk-remove") {
            $this->bulk_remove();
        } elseif (\Input::post("action") == "update-statistics") {
            $this->update_statistics();
        }

        // View
        $this->view(PLUGINS_PATH."/".self::IDNAME."/views/log.php", null);
    }

    /**
     * Check ER post is good or not
     * @return void
     */
    private function isGoodERPost($er, $followers)
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

    /**
     * Remember that Log is selected
     * @return void
     */
    private function select_log()
    {
        $this->resp->result = 0;

        if (!\Input::post("id")) {
            $this->resp->msg = __("ID is requred!");
            $this->jsonecho();
        }

        $Log = new LogModel(\Input::post("id"));

        if (!$Log->isAvailable()) {
            $this->resp->msg = __("Log doesn't exist!");
            $this->jsonecho();
        }

        $log_selected = (bool) \Input::post("log_selected");

        $Log->set("data.log_selected", !$log_selected)->save();

        $this->resp->log_selected = !$log_selected;
        $this->resp->result = 1;
        $this->jsonecho();
    } 

    /**
     * Select all exist proxies
     * @return void
     */
    private function bulk_select(
        $select = true)
    {
        $this->resp->result = 0;

        $Account = $this->getVariable("Account");
        $this->resp->account_id = $Account->get("id");

        $Logs = \DB::table(TABLE_PREFIX."repost_pro_log");
        $Logs->select("id")
             ->where("account_id", "=", $Account->get("id")); 

        foreach ($Logs->get() as $Log) {
            $LogM = new LogModel($Log->id);
            if ($LogM->isAvailable()) {
                $LogM->set("data.log_selected", $select)->save();
            }
        }

        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Bulk remove all exist proxies
     * @return void
     */
    private function bulk_remove() 
    {
        $this->resp->result = 0;
        $this->resp->deleted_logs = [];

        $Account = $this->getVariable("Account");
        $this->resp->account_id = $Account->get("id");

        $Logs = \DB::table(TABLE_PREFIX."repost_pro_log");
        $Logs->select("id")
             ->where("account_id", "=", $Account->get("id"))
             ->where("data", "LIKE", '%"log_selected":true%');

        foreach ($Logs->get() as $Log) {
            $LogM = new LogModel($Log->id);
            if ($LogM->isAvailable()) {
                $this->resp->deleted_logs[] = $LogM->get("id");
                $LogM->delete();
            }
        }

        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Update statistics in logs
     * @return void
     */
    private function update_statistics() 
    {
        $this->resp->result = 0;
        $AuthUser = $this->getVariable("AuthUser");
        $Account = $this->getVariable("Account");
        $Schedule = $this->getVariable("Schedule");

        // Login to Instagram
        try {
            $Instagram = \InstagramController::login($Account);
        } catch (\InstagramAPI\Exception\NetworkException $e) {
            $this->resp->msg = __("We couldn't connect to Instagram. Please try again in few minutes.");
            $this->jsonecho();
        } catch (\InstagramAPI\Exception\EmptyResponseException $e) {
            $this->resp->msg = __("Instagram send us empty response. Please try again in few minutes.");
            $this->jsonecho();
        } catch (\Exception $e) {
            $this->resp->msg = $e->getMessage();
            $this->jsonecho();   
        }

        $this->resp->analytics = [];
        // Collect analytics data about latest feed posts and save to specified log
        try {
            // Logging client events
            $Instagram->event->sendNavigation('main_profile', 'feed_timeline', 'self_profile');

            $Instagram->people->getFriendship($Instagram->account_id);
            $self_feed = $Instagram->timeline->getSelfUserFeed();
            $Instagram->highlight->getUserFeed($Instagram->account_id);
            $Instagram->people->getSelfInfo();
            $Instagram->story->getUserStoryFeed($Instagram->account_id);

            // Logging client events
            $Instagram->event->sendProfileView($Instagram->account_id);

            if (isset($self_feed)) {
                $feed_items = $self_feed->getItems();
                if (count($feed_items) > 0) {
                    foreach ($feed_items as $key => $f) {
                        // Logging client events
                        // Send a thumbnail impression
                        $Instagram->event->sendThumbnailImpression('instagram_thumbnail_impression', $f, 'self_profile');

                        $media_code = $f->getCode();
                        $LogData = new LogModel([
                			"user_id" => $AuthUser->get("id"),
                			"account_id" => $Account->get("id"),
                			"LIKE_data" => "%" . $media_code . "%",
                			"status" => "success"
                        ]);
                        if ($LogData->isAvailable()) {
                            $likes_count = (int)$f->getLikeCount();
                            $comments_count = (int)$f->getCommentCount();
                            $views_count = (int)$f->getViewCount();

                            $this->resp->analytics[$media_code]["likes_count"] = $likes_count;
                            $this->resp->analytics[$media_code]["comments_count"] = $comments_count;
                            $this->resp->analytics[$media_code]["views_count"] = $views_count;

                            $total_actions = $likes_count + $comments_count;

                            // Log last analytics update time 
                            $Schedule->set("data.last_analytics_update", time())
                                     ->save();

                            $last_analytics_update = new \Moment\Moment(date("Y-m-d H:i:s", time()), date_default_timezone_get());
                            $last_analytics_update->setTimezone($AuthUser->get("preferences.timezone"));
                            $this->resp->last_analytics_update = $last_analytics_update->format($AuthUser->get("preferences.timeformat") == "12" ? "h:iA (d.m.Y)" : "H:i (d.m.Y)");

                            // Log basic post insights info
                            $LogData->set("data.analytics.likes_count", $likes_count)
                                    ->set("data.analytics.comments_count", $comments_count)
                                    ->set("data.analytics.views_count", $views_count)
                                    ->save();

                            //  Calculate and save ER post 
                            if ($total_actions > 0) {
                                $account_data = "data." . $Account->get("username");
                                $followers_count = $AuthUser->get($account_data . ".follower_count") ? (int)$AuthUser->get($account_data . ".follower_count") : 0;
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
                                        $this->resp->analytics[$media_code]["er_post"] = $er_post;
                                        $this->resp->analytics[$media_code]["er_post_is_good"] = $is_good;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Logging client events
            $Instagram->event->sendNavigation('main_home', 'self_profile', 'feed_timeline');
            $Instagram->event->forceSendBatch();
        } catch (\InstagramAPI\Exception\NetworkException $e) {
            $this->resp->msg = __("We couldn't connect to Instagram. Please try again in few minutes.");
            $this->jsonecho();
        } catch (\InstagramAPI\Exception\EmptyResponseException $e) {
            $this->resp->msg = __("Instagram send us empty response. Please try again in few minutes.");
            $this->jsonecho(); 
        } catch (\InstagramAPI\Exception\InstagramException $e) {
            $this->resp->msg = $e->getMessage();
            $this->jsonecho();   
        } catch (\Exception $e) {
            $this->resp->msg = $e->getMessage();
            $this->jsonecho();   
        }

        $this->resp->result = 1;
        $this->jsonecho();
    }
}
