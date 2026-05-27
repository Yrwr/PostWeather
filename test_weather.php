<?php
/**
 * PostWeather 插件调试工具 v1.2
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PostWeather 插件调试工具</h1>";

// 1. 检查 PHP 版本
echo "<h2>1. PHP 版本检查</h2>";
$phpVersion = phpversion();
echo "PHP 版本: " . $phpVersion . "<br>";
if (version_compare($phpVersion, '7.0', '<')) {
    echo "<span style='color:red'>❌ PHP 版本太低，需要 7.0+</span><br>";
} else {
    echo "<span style='color:green'>✅ PHP 版本符合要求</span><br>";
}

// 2. 检查扩展
echo "<h2>2. PHP 扩展检查</h2>";
$extensions = array('curl', 'json');
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color:green'>✅ $ext 扩展已加载</span><br>";
    } else {
        echo "<span style='color:red'>❌ $ext 扩展未加载</span><br>";
    }
}

// 3. 检查 allow_url_fopen
echo "<h2>3. allow_url_fopen 检查</h2>";
if (ini_get('allow_url_fopen')) {
    echo "<span style='color:green'>✅ allow_url_fopen 已启用</span><br>";
} else {
    echo "<span style='color:orange'>⚠️ allow_url_fopen 未启用，将使用 curl</span><br>";
}

// 4. 获取当前 IP
echo "<h2>4. 当前 IP 测试</h2>";
$headers = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR', 'HTTP_X_REAL_IP');
$testIP = '127.0.0.1';
foreach ($headers as $header) {
    if (!empty($_SERVER[$header])) {
        $ips = explode(',', $_SERVER[$header]);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $testIP = $ip;
            break;
        }
    }
}
echo "检测到的 IP: <strong>$testIP</strong><br>";

// 5. 测试 IP 地理位置 API（直接测试完整 URL）
echo "<h2>5. IP 地理位置 API 测试</h2>";
$geoUrl = "http://ip-api.com/json/$testIP?lang=zh-CN";
echo "URL: $geoUrl<br>";

$geoCh = curl_init();
curl_setopt($geoCh, CURLOPT_URL, $geoUrl);
curl_setopt($geoCh, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($geoCh, CURLOPT_TIMEOUT, 15);
curl_setopt($geoCh, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($geoCh, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($geoCh, CURLOPT_USERAGENT, 'PostWeather/1.2 (+https://yrwr.net)');
curl_setopt($geoCh, CURLOPT_FOLLOWLOCATION, 1);
$geoResponse = curl_exec($geoCh);
$geoError = curl_error($geoCh);
$geoHttpCode = curl_getinfo($geoCh, CURLINFO_HTTP_CODE);
curl_close($geoCh);

if ($geoResponse !== false && $geoHttpCode == 200) {
    echo "HTTP 状态码: $geoHttpCode ✅<br>";
    $geoData = json_decode($geoResponse, true);
    if ($geoData && isset($geoData['status']) && $geoData['status'] === 'success') {
        echo "<span style='color:green'>✅ 地理位置获取成功!</span><br>";
        echo "城市: " . (isset($geoData['city']) ? $geoData['city'] : '未知') . "<br>";
        echo "坐标: {$geoData['lat']}, {$geoData['lon']}<br>";
        $successGeo = $geoData;
    } else {
        echo "<span style='color:red'>❌ API 返回状态失败!</span><br>";
        $successGeo = null;
    }
} else {
    echo "<span style='color:red'>❌ 请求失败: HTTP $geoHttpCode, 错误: $geoError</span><br>";
    $successGeo = null;
}

// 6. 测试天气 API
echo "<h2>6. 天气 API 测试</h2>";
if ($successGeo && isset($successGeo['lat']) && isset($successGeo['lon'])) {
    $lat = $successGeo['lat'];
    $lon = $successGeo['lon'];
    $weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=Asia/Shanghai&forecast_days=1";
    echo "URL: $weatherUrl<br>";

    $weatherCh = curl_init();
    curl_setopt($weatherCh, CURLOPT_URL, $weatherUrl);
    curl_setopt($weatherCh, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($weatherCh, CURLOPT_TIMEOUT, 15);
    curl_setopt($weatherCh, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($weatherCh, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($weatherCh, CURLOPT_USERAGENT, 'PostWeather/1.2 (+https://yrwr.net)');
    $weatherResponse = curl_exec($weatherCh);
    $weatherError = curl_error($weatherCh);
    $weatherHttpCode = curl_getinfo($weatherCh, CURLINFO_HTTP_CODE);
    curl_close($weatherCh);

    if ($weatherResponse !== false && $weatherHttpCode == 200) {
        echo "HTTP 状态码: $weatherHttpCode ✅<br>";
        $weatherData = json_decode($weatherResponse, true);
        if ($weatherData && isset($weatherData['daily'])) {
            echo "<span style='color:green'>✅ 天气获取成功!</span><br>";
            $code = $weatherData['daily']['weather_code'][0];
            $tempMax = round($weatherData['daily']['temperature_2m_max'][0]);
            $tempMin = round($weatherData['daily']['temperature_2m_min'][0]);
            
            $iconMap = array(
                0 => '☀️', 1 => '🌤️', 2 => '⛅', 3 => '☁️',
                45 => '🌫️', 48 => '🌫️',
                51 => '🌧️', 53 => '🌧️', 55 => '🌧️',
                61 => '🌧️', 63 => '🌧️', 65 => '🌧️',
                66 => '🌨️', 67 => '🌨️',
                71 => '❄️', 73 => '❄️', 75 => '❄️',
                80 => '🌦️', 81 => '🌦️', 82 => '⛈️',
                95 => '⛈️', 96 => '⛈️', 99 => '⛈️'
            );
            $icon = isset($iconMap[$code]) ? $iconMap[$code] : '🌤️';
            
            echo "天气: $icon<br>";
            echo "温度: {$tempMin}~$tempMax °C<br>";
            echo "<strong>显示效果: $icon {$successGeo['city']} {$tempMin}~{$tempMax}°C</strong><br>";
        } else {
            echo "<span style='color:red'>❌ 天气数据格式错误!</span><br>";
        }
    } else {
        echo "<span style='color:red'>❌ 请求失败: HTTP $weatherHttpCode, 错误: $weatherError</span><br>";
    }
} else {
    echo "跳过天气测试（地理位置获取失败）<br>";
}

// 7. 总结
echo "<hr>";
echo "<h2>测试总结</h2>";

$allSuccess = $successGeo && isset($successGeo['lat']);

if ($allSuccess) {
    echo "<p style='color:green;font-size:18px;'>🎉 所有 API 测试通过！插件应该可以正常工作。</p>";
    echo "<p>如果发布文章后仍不显示天气，请检查：</p>";
    echo "<ol>";
    echo "<li>插件是否已启用</li>";
    echo "<li>插件是否有写入数据库的权限</li>";
    echo "<li>查看浏览器控制台是否有 JavaScript 错误</li>";
    echo "<li>检查文章是否有天气字段数据</li>";
    echo "</ol>";
} else {
    echo "<p style='color:red;font-size:18px;'>❌ API 测试失败，请检查网络连接。</p>";
}

echo "<hr>";
echo "<p>调试工具版本: 1.2 | 生成时间: " . date('Y-m-d H:i:s') . "</p>";
?>
