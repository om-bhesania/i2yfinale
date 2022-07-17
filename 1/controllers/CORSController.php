<?php
namespace Plugins\RepostPro;

// Disable direct access
if (!defined('APP_VERSION')) 
    die("Yo, what's up?");

/**
 * CORS Controller for Edit Profile & Feed module
 * 
 * @version 1.1
 * @author Nextpost.tech (https://nextpost.tech)
 * 
 */
class CORSController extends \Controller
{
    /**
     * idname of the plugin for internal use
     */
    const IDNAME = 'repost-pro';

    public function getResponseHeader($header, $response) {
        foreach ($response as $key => $r) {
            // Match the header name up to ':', compare lower case
            if (stripos($r, $header . ':') === 0) {
                list($headername, $headervalue) = explode(":", $r, 2);
                return trim($headervalue);
            }
        }
    }

    /**
     * Process
     * @return null
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
            header("Location: ".APPURL."/accounts");
            exit;
        }

        if (!isset($Route->params->id)) {
            echo "Account ID is required.";
            exit;
        }

        $Account = \Controller::model("Account", $Route->params->id);
        if (!$Account->isAvailable() || 
            $Account->get("user_id") != $AuthUser->get("id")) 
        {
            header("Location: ".APPURL."/accounts");
            exit;
        }

        $url = \Input::request("media_url");

        if (!isset($url)) {
            echo "URL is required.";
            exit;
        }

        $queries = array();
        if ($_SERVER['QUERY_STRING']) {
            parse_str($_SERVER['QUERY_STRING'], $queries);
            if (isset($queries["media_url"])) {
                unset($queries["media_url"]);
            }
            if (!empty($queries)) {
                $url = $url . "&" . http_build_query($queries);
            } 
        }

        $proxy = null;
        if ($Account->get("proxy")) {
            $proxy = $Account->get("proxy");
        }

        try {
            $proxy_parts  = explode('://', $proxy);
            if ($proxy_parts[0] == "https") {
                $client = new \GuzzleHttp\Client();
                $res = $client->request('GET', $url,
                    [
                        "verify" => SSL_ENABLED,
                        "timeout" => 10,
                        "proxy" => $proxy_parts[1],
                        "curl"    => [
                            CURLOPT_USERAGENT => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36",
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Make http client work with HTTP 2/0
                        ]
                    ]);
            } else {
                $client = new \GuzzleHttp\Client();
                $res = $client->request('GET', $url,
                [
                    "verify" => SSL_ENABLED,
                    "timeout" => 10,
                    "proxy" => $proxy,
                    "curl"    => [
                        CURLOPT_USERAGENT => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36"
                    ],
                ]);
            }

            $body = $res->getBody();
            if (!empty($body)) {
                header("Content-Type: " . $res->getHeaderLine("Content-Type"));
                header("Content-Length: " . $res->getHeaderLine("Content-Length"));
                echo $body;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        exit;
    }
}
