<?php
namespace Plugins\RepostPro;
use LanguageDetection\Language;

require_once PLUGINS_PATH."/".IDNAME."/vendor/autoload.php";

// Disable direct access
if (!defined('APP_VERSION')) 
    die("Yo, what's up?");

/**
 * Index Controller
 */
class IndexController extends \Controller
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

        // Show only active tasks
        $only_active = \Input::get("only_active");
        if ($only_active == "yes") {
            $AuthUser->set("data.repost_pro_only_active", true)
                     ->save();
        } elseif ($only_active == "no") {
            $AuthUser->set("data.repost_pro_only_active", false)
                     ->save();
        }

        $only_active_cv = $AuthUser->get("data.repost_pro_only_active");

        // Get accounts
        $Accounts = \Controller::model("Accounts");
        $Accounts->setPageSize(30)
                 ->setPage(\Input::get("page"))
                 ->where("user_id", "=", $AuthUser->get("id"));
                 
        $search_query = \Input::get("q");
        if (!empty($search_query)) {
            $search_query = trim($search_query);
            $Accounts->where("username", "LIKE", $search_query."%");
        }

        $Accounts->orderBy("id","DESC")
                 ->fetchData();

        $Settings = namespace\settings();

        require_once PLUGINS_PATH."/".$this->getVariable("idname")."/models/ScheduleModel.php";
        $sc_data = [];
        foreach ($Accounts->getDataAs("Account") as $a) {
            $sc = new ScheduleModel([
                "account_id" => $a->get("id"),
                "user_id" => $a->get("user_id")
            ]);

            $speed = $sc->get("speed");

            $speed_val = 0;
            if ($speed == "auto") {
                $speed_val = 1;
            } elseif ($speed == "very_slow") {
                $speed_val = $Settings->get("data.speeds.very_slow");
            } elseif ($speed == "slow") {
                $speed_val = $Settings->get("data.speeds.slow");
            } elseif ($speed == "medium") {
                $speed_val = $Settings->get("data.speeds.medium");
            } elseif ($speed == "fast") {
                $speed_val = $Settings->get("data.speeds.fast");
            } elseif ($speed == "very_fast") {
                $speed_val = $Settings->get("data.speeds.very_fast");
            } else {
                $speed_val = 0;
            }

            $sc_data[$a->get("id")] = [
                "is_active" => $sc->get("is_active"),
                "speed" => $speed_val,
                "only_active" => $only_active_cv ? "1" : "0",
                "schedule_date" => $sc->get("schedule_date"),
                "repost_time_visual" => $sc->get("data.repost_time_visual")
            ];    
        }

        if (\Input::post("action") == "update_data") {
            $this->update_data();
        } elseif (\Input::post("action") == "delete_repost") {
            $this->delete_repost();
        } 

        $this->setVariable("Accounts", $Accounts);
        $this->setVariable("sc_data", $sc_data);

        $this->view(PLUGINS_PATH."/".self::IDNAME."/views/index.php", null);
    }

    /**
     * Get selected schedule estimated speed
     * @return string
     */
    private function update_data()
    {
        $this->resp->result = 0;

        $AuthUser = $this->getVariable("AuthUser");

        if (!\Input::post("id")) {
            $this->resp->title = __("Error");
            $this->resp->msg = __("ID is requred!");
            $this->jsonecho();
        }

        $Account = \Controller::model("Account", \Input::post("id"));

        // Check Account ID and Account Status
        if (!$Account->isAvailable() ||
            $Account->get("user_id") != $AuthUser->get("id")) 
        {
            $this->resp->title = __("Error");
            $this->resp->msg = __("Account does not exist or ID is invalid.");
            $this->jsonecho();
        }

        $Schedule = new ScheduleModel([
            "account_id" => $Account->get("id"),
            "user_id" => $AuthUser->get("id")
        ]);

        $speed = $Schedule->get("speed");
        $Settings = namespace\settings();

        $speed_val = 0;
        if ($speed == "auto") {
            $speed_val = 1;
        } elseif ($speed == "very_slow") {
            $speed_val = $Settings->get("data.speeds.very_slow");
        } elseif ($speed == "slow") {
            $speed_val = $Settings->get("data.speeds.slow");
        } elseif ($speed == "medium") {
            $speed_val = $Settings->get("data.speeds.medium");
        } elseif ($speed == "fast") {
            $speed_val = $Settings->get("data.speeds.fast");
        } elseif ($speed == "very_fast") {
            $speed_val = $Settings->get("data.speeds.very_fast");
        } else {
            $speed_val = 0;
        }

        $repost_time_visual = $Schedule->get("data.repost_time_visual");
        if (!empty($repost_time_visual)) {
            $repost_times = json_decode($repost_time_visual, true);
            $this->resp->speed = count($repost_times);
        } else {
            $this->resp->speed = $speed_val;
        }

        $schedule_date = $Schedule->get("schedule_date");
        $this->resp->schedule_date_value = "";
        if (!empty($schedule_date)) {
            $nextdate = new \Moment\Moment($schedule_date, date_default_timezone_get());
            $nextdate->setTimezone($AuthUser->get("preferences.timezone"));
            $diff = $nextdate->fromNow(); 
            if ($diff->getDirection() == "future") {
                $this->resp->schedule_date_value = __("Next %s", $diff->getRelative());
            }
        } 

        $this->resp->is_active = $Schedule->get("is_active") ? (int)$Schedule->get("is_active") : 0;

        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Get selected schedule estimated speed
     * @return string
     */
    private function delete_repost() 
    {
        $this->resp->result = 0;

        $AuthUser = $this->getVariable("AuthUser");

        if (!\Input::post("account_id")) {
            $this->resp->msg = __("Account ID is requred!");
            $this->jsonecho();
        }

        $Account = \Controller::model("Account", \Input::post("account_id"));

        // Check Account ID and Account Status
        if (!$Account->isAvailable() ||
            $Account->get("user_id") != $AuthUser->get("id")) 
        {
            $this->resp->msg = __("Account does not exist or ID is invalid.");
            $this->jsonecho();
        }

        if (!\Input::post("repost_id")) {
            $this->resp->msg = __("Repost ID is requred!");
            $this->jsonecho();
        }

        require_once PLUGINS_PATH."/".$this->getVariable("idname")."/models/LogModel.php";
        $Log = new LogModel([
            "id" => \Input::post("repost_id"),
            "account_id" => $Account->get("id"),
            "user_id" => $AuthUser->get("id")
        ]);

        if (!$Log->isAvailable()) {
            $this->resp->msg = __("Repost log does not exist or invalid.");
            $this->jsonecho();
        }

        $media_type_id = $Log->get("data.grabbed.media_type");
        if (empty($media_type_id)) {
            $this->resp->msg = __("Media type id not defined or invalid.");
            $this->jsonecho();
        }

        if ($media_type_id == "2") {
            $media_type = "VIDEO";
        } else if ($media_type_id == "8") {
            $media_type = "CAROUSEL";
        } else {
            $media_type = "PHOTO";
        }

        $media_id = $Log->get("data.reposted.media_id");
        if (empty($media_id)) {
            $this->resp->msg = __("Media ID not defined or invalid.");
            $this->jsonecho();
        }

        // Get self account info few times and skip this process, if this 7 retries unsuccessful
        // Mobile proxy connection break adaptation
        $reconnect_get = 0;
        $reconnect_get_count = 0;
        $reconnect_get_status = 0;
        do { 
            $reconnect_get_count += 1;
            if ($reconnect_get_count == 7) {
                $reconnect_get = 1;
                $reconnect_get_status = 0;
            }
            // Try login to Instagram
            try {
                $Instagram = \InstagramController::login($Account);
                $reconnect_get = 1;
                $reconnect_get_status = 1;
            } catch (\InstagramAPI\Exception\NetworkException $e) {
                // Couldn't connect to Instagram account because of network or connection error
                // We will try 10 times otherwise we will show error user message
                if ($reconnect_get_count == 7) {
                    $this->resp->msg = $e->getMessage();
                    $this->jsonecho();
                }
                sleep(7);
            } catch (\InstagramAPI\Exception\EmptyResponseException $e) {
                // Instagram send us empty response 
                // We will try 10 times otherwise we will show error user message
                if ($reconnect_get_count == 7) {
                    $this->resp->msg = $e->getMessage();
                    $this->jsonecho();
                }
                sleep(7);
            } catch (\Exception $e) {
                $separated = $e->getMessage();
                $text = explode(" | ", $separated, 2);
                $this->resp->title = isset($text[0]) ? $text[0] : __("Oops...");
                $this->resp->msg = isset($text[1]) ? $text[1] : $e->getMessage();

                if ($text[0] == __("Challenge Required") ||
                    $text[0] == __("Login Required") ||
                    $text[0] == __("Invalid Username") ||
                    $text[0] == __("2FA Required") ||
                    $text[0] == __("Incorrect Password")) {
                    // Redirect user to account settings
                    $account_id = Input::post("id"); 
                    if (isset($account_id)) {
                        $this->resp->redirect = APPURL."/accounts/".$account_id; 
                    } else {
                        $this->resp->redirect = APPURL."/accounts"; 
                    }
                }
                
                $this->jsonecho();
            }
        } while (!$reconnect_get);

        if (!$reconnect_get_status) {
            $this->resp->title = __("Couldn't connect to Instagram");
            $this->resp->msg= __("Account not logged in. Please try again later or contact to Support.");
            $this->jsonecho();
        }

        // Mobile proxy connection break adaptation
        $reconnect_get = 0;
        $reconnect_get_count = 0;
        $reconnect_get_status = 0;
        do { 
            $reconnect_get_count += 1;
            if ($reconnect_get_count == 7) {
                $reconnect_get = 1;
                $reconnect_get_status = 0;
            }
            // Try login to Instagram
            try {
                $delete_response = $Instagram->media->delete($media_id, $media_type);
                $this->resp->post_deleted = true;
                $reconnect_get = 1;
                $reconnect_get_status = 1;
            } catch (\InstagramAPI\Exception\NetworkException $e) {
                // Couldn't connect to Instagram account because of network or connection error
                // We will try 10 times otherwise we will show error user message
                if ($reconnect_get_count == 7) {
                    $this->resp->msg = $e->getMessage();
                    $this->jsonecho();
                }
                sleep(7);
            } catch (\InstagramAPI\Exception\EmptyResponseException $e) {
                // Instagram send us empty response 
                // We will try 10 times otherwise we will show error user message
                if ($reconnect_get_count == 7) {
                    $this->resp->msg = $e->getMessage();
                    $this->jsonecho();
                }
                sleep(7);
            } catch (\InstagramAPI\Exception\InstagramException $e) {
                $this->resp->msg = $e->getMessage(); 
                $this->jsonecho();
            } catch (\Exception $e) {
                $this->resp->msg = $e->getMessage(); 
                $this->jsonecho();
            }
        } while (!$reconnect_get);

        $Log->delete();
        $this->resp->log_deleted = true;

        $this->resp->result = 1;
        $this->jsonecho();
    }
}