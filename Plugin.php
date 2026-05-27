<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 文章天气插件
 * 
 * @package PostWeather
 * @author masy
 * @version 1.4.0
 * @link https://yrwr.net
 */
class PostWeather_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('PostWeather_Plugin', 'saveWeather');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('PostWeather_Plugin', 'saveWeather');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('PostWeather_Plugin', 'outputFooterScript');
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('PostWeather_Plugin', 'handleApi');
        
        return _t('插件已激活');
    }

    public static function deactivate()
    {
        return _t('插件已禁用');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function saveWeather($contents, $widget)
    {
        $cid = null;
        if (is_array($contents) && isset($contents['cid'])) {
            $cid = intval($contents['cid']);
        } elseif (is_object($widget) && isset($widget->cid)) {
            $cid = intval($widget->cid);
        }
        
        if (!$cid || $cid <= 0) {
            return $contents;
        }

        $db = Typecho_Db::get();
        $result = $db->fetchRow($db->select('str_value')->from('table.fields')
            ->where('cid = ?', $cid)->where('name = ?', 'weather'));
        
        if ($result) {
            return $contents;
        }

        $weatherData = self::getWeatherFromCookie();

        if (!$weatherData) {
            return $contents;
        }

        if (empty($weatherData['city']) || empty($weatherData['icon'])) {
            return $contents;
        }

        $weatherData['created'] = time();

        try {
            $db->query($db->insert('table.fields')->rows(array(
                'cid' => $cid,
                'name' => 'weather',
                'str_value' => json_encode($weatherData, JSON_UNESCAPED_UNICODE),
                'type' => 'str'
            )));
        } catch (Exception $e) {
        }

        return $contents;
    }

    public static function getWeatherFromCookie()
    {
        if (!isset($_COOKIE['postweather_cache'])) {
            return null;
        }

        $cacheData = $_COOKIE['postweather_cache'];
        $data = json_decode($cacheData, true);

        if (!$data || !isset($data['weather'])) {
            return null;
        }

        if (isset($data['expires']) && $data['expires'] < time()) {
            return null;
        }

        $today = date('Y-m-d');
        if (isset($data['weather_date'])) {
            if ($data['weather_date'] !== $today) {
                return null;
            }
        } else {
            return null;
        }

        return $data['weather'];
    }

    public static function outputFooterScript()
    {
        $cacheData = self::getWeatherFromCookie();
        if ($cacheData) {
            return;
        }
        ?>
        <script>
        (function() {
            setTimeout(function() {
                var cache = {
                    weather: null,
                    weather_date: null,
                    expires: 0
                };
                
                function fetchWeather() {
                    fetch('?action=postweather_api')
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success && data.weather) {
                                cache.weather = data.weather;
                                cache.weather_date = data.weather_date;
                                cache.expires = data.expires;
                                var expires = new Date();
                                expires.setTime(expires.getTime() + (2 * 60 * 60 * 1000));
                                document.cookie = 'postweather_cache=' + encodeURIComponent(JSON.stringify(cache)) + ';path=/;expires=' + expires.toUTCString();
                            }
                        })
                        .catch(function() {});
                }
                
                fetchWeather();
            }, 3000);
        })();
        </script>
        <?php
    }

    public static function handleApi()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'postweather_api') {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        $ip = self::getClientIP();
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(array('success' => false, 'message' => '无法获取IP'));
            exit;
        }

        $geo = self::getCityByIP($ip);
        if (!$geo) {
            echo json_encode(array('success' => false, 'message' => '无法获取位置'));
            exit;
        }

        $weather = self::getWeatherByCoords($geo['lat'], $geo['lon']);
        if (!$weather) {
            echo json_encode(array('success' => false, 'message' => '无法获取天气'));
            exit;
        }

        if (empty($geo['city']) || empty($weather['icon'])) {
            echo json_encode(array('success' => false, 'message' => '数据不完整'));
            exit;
        }

        $weatherData = array(
            'city' => $geo['city'],
            'icon' => $weather['icon'],
            'tempMin' => $weather['tempMin'],
            'tempMax' => $weather['tempMax']
        );

        $today = date('Y-m-d');
        $expires = time() + (2 * 60 * 60);

        echo json_encode(array(
            'success' => true,
            'weather' => $weatherData,
            'weather_date' => $today,
            'expires' => $expires
        ));
        exit;
    }

    public static function getClientIP()
    {
        $headers = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR', 'HTTP_X_REAL_IP');
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (self::isPublicIP($ip)) {
                    return $ip;
                }
            }
        }
        return null;
    }

    private static function isPublicIP($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        return true;
    }

    private static function getCityByIP($ip)
    {
        $url = 'http://ip-api.com/json/' . $ip . '?lang=zh-CN';
        $response = self::httpGet($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['status']) || $data['status'] !== 'success') {
            return null;
        }

        $city = '未知城市';
        if (isset($data['city'])) {
            $city = $data['city'];
        } elseif (isset($data['regionName'])) {
            $city = $data['regionName'];
        } elseif (isset($data['country'])) {
            $city = $data['country'];
        }

        return array(
            'city' => $city,
            'lat' => $data['lat'],
            'lon' => $data['lon']
        );
    }

    private static function getWeatherByCoords($lat, $lon)
    {
        $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=Asia/Shanghai&forecast_days=1';
        $response = self::httpGet($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['daily'])) {
            return null;
        }

        $code = 0;
        if (isset($data['daily']['weather_code'][0])) {
            $code = $data['daily']['weather_code'][0];
        }

        $tempMax = 0;
        if (isset($data['daily']['temperature_2m_max'][0])) {
            $tempMax = round($data['daily']['temperature_2m_max'][0]);
        }

        $tempMin = 0;
        if (isset($data['daily']['temperature_2m_min'][0])) {
            $tempMin = round($data['daily']['temperature_2m_min'][0]);
        }

        $iconMap = array(
            0 => '☀️',
            1 => '🌤️', 2 => '⛅', 3 => '☁️',
            45 => '🌫️', 48 => '🌫️',
            51 => '🌧️', 53 => '🌧️', 55 => '🌧️',
            56 => '🌧️', 57 => '🌧️',
            61 => '🌧️', 63 => '🌧️', 65 => '🌧️',
            66 => '🌨️', 67 => '🌨️',
            71 => '❄️', 73 => '❄️', 75 => '❄️',
            77 => '🌨️',
            80 => '🌦️', 81 => '🌦️', 82 => '⛈️',
            85 => '❄️', 86 => '❄️',
            95 => '⛈️', 96 => '⛈️', 99 => '⛈️'
        );

        $icon = '🌤️';
        if (isset($iconMap[$code])) {
            $icon = $iconMap[$code];
        }

        return array(
            'icon' => $icon,
            'tempMin' => $tempMin,
            'tempMax' => $tempMax
        );
    }

    private static function httpGet($url)
    {
        $timeout = 15;

        if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => $timeout,
                    'user_agent' => 'PostWeather/1.4 (+https://yrwr.net)',
                ),
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                )
            ));
            return file_get_contents($url, false, $context);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PostWeather/1.4 (+https://yrwr.net)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data ? $data : null;
        }

        return null;
    }

    public static function showWeather($cid)
    {
        $db = Typecho_Db::get();
        $result = $db->fetchRow($db->select('str_value')->from('table.fields')
            ->where('cid = ?', $cid)->where('name = ?', 'weather'));
        
        if (!$result) {
            return '';
        }

        $weather = json_decode($result['str_value'], true);
        if (!$weather || !isset($weather['city'])) {
            return '';
        }

        return $weather['icon'] . ' ' . $weather['city'] . ' ' . $weather['tempMin'] . '~' . $weather['tempMax'] . '°C';
    }
}
