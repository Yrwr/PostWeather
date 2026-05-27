# PostWeather - 文章天气插件

自动获取并显示文章发布时的天气信息，基于作者 IP 地址定位。适合写日记、多人创作的博客。

## 功能特点

- 自动获取作者 IP 和地理位置
- 调用免费天气 API（Open-Meteo）获取天气数据
- 发布文章时自动保存天气信息
- 完全不影响发布速度（Cookie 缓存机制）
- 支持游客和登录用户

## 安装方法

1. 下载插件包并解压
2. 将 `PostWeather` 文件夹上传到 `/usr/plugins/` 目录
3. 进入 Typecho 后台 → 控制台 → 插件
4. 启用 PostWeather 插件

## 主题集成

### 方法一：在 functions.php 中添加辅助函数（推荐）

在主题的 `functions.php` 文件中添加：

```php
/**
 * 获取文章天气显示
 * @param int $cid 文章ID
 * @return string 天气HTML，如果插件未启用返回空
 */
function getPostWeatherDisplay($cid) {
    if (class_exists('PostWeather_Plugin')) {
        return PostWeather_Plugin::showWeather($cid);
    }
    return '';
}
```

### 方法二：直接调用（简单场景）

在需要显示天气的位置直接调用：

```php
<?php echo PostWeather_Plugin::showWeather($this->cid); ?>
```

## 使用示例

### 1. 在文章列表中显示（index.php）

```php
<?php while($this->next()): ?>
<article>
    <h2><a href="<?php $this->permalink() ?>"><?php $this->title() ?></a></h2>
    <span><?php echo getPostWeatherDisplay($this->cid); ?></span>
    <p><?php $this->excerpt(200, '...'); ?></p>
</article>
<?php endwhile; ?>
```

### 2. 在文章详情页显示（post.php）

```php
<header>
    <h1><?php $this->title() ?></h1>
    <div class="meta">
        <span><?php echo getPostWeatherDisplay($this->cid); ?></span>
        <span><?php $this->date(); ?></span>
    </div>
</header>
```

### 3. 直接调用示例

```php
<!-- 直接调用，不使用辅助函数 -->
<?php if (class_exists('PostWeather_Plugin')): ?>
    <span class="weather"><?php echo PostWeather_Plugin::showWeather($this->cid); ?></span>
<?php endif; ?>
```

## 显示效果

插件会输出类似这样的格式：

```
☀️ 郑州市 17~27°C
```

## 工作原理

### 缓存机制

1. 用户访问网站时，页面加载完成 3 秒后自动获取天气
2. 天气数据保存到 Cookie 中（有效期 2 小时）
3. 发布文章时直接从 Cookie 读取并保存到数据库
4. 确保天气是当天数据，避免使用过期缓存

### 为什么不影响发布速度？

- 天气获取在用户访问时已完成（3 秒延迟 + 异步请求）
- 发布文章时直接读取 Cookie，无需调用外部 API
- 数据库插入操作只需几毫秒

## CSS 样式示例

```css
.weather {
    display: inline-block;
    font-size: 14px;
    color: #666;
    margin-left: 10px;
}
```

## API 服务

插件使用以下免费 API：

- **IP 定位**：http://ip-api.com （无需 API Key）
- **天气数据**：https://api.open-meteo.com （完全免费）

## 系统要求

- PHP >= 7.0
- PHP curl 或 allow_url_fopen 扩展
- 服务器可访问外部 API（ip-api.com、api.open-meteo.com）

## 版本历史

- **v1.4.0** - 优化缓存机制，延迟获取不影响浏览
- **v1.3.0** - Cookie 缓存方案
- **v1.2.0** - PHP 7.2 兼容性修复
- **v1.1.0** - SSL 验证优化
- **v1.0.0** - 初始版本

## 注意事项

1. 天气数据保存后不会自动更新
2. Cookie 有效期为 2 小时
3. 跨天后会自动获取新天气
4. 插件卸载后已保存的天气数据不会删除

## 常见问题

### Q: 发布文章后没有显示天气？

A: 请检查：
1. 插件是否已启用
2. 访问网站后是否等待了 3 秒（首次需要获取缓存）
3. Cookie 是否被浏览器阻止

### Q: 天气显示为"未知城市"？

A: 可能是 IP 获取失败或 API 调用失败，请检查服务器网络连接。

### Q: 如何手动刷新天气？

A: 删除浏览器中的 `postweather_cache` Cookie，然后刷新页面。

## 调试工具

插件目录包含调试工具 `test_weather.php`，用于检测：

- PHP 版本和扩展
- 网络连接状态
- API 调用结果

访问 `http://你的域名/usr/plugins/PostWeather/test_weather.php` 查看。

## 作者信息

- 作者：masy
- 网站：https://yrwr.net/1466.html
- 版本：1.4.0

## 许可证

MIT License
