<?php
/**
 * Plugin Name: Yemen Travel — Yemen Tourism
 * Description: Arabic RTL tourism site powered by a Strapi 5 content API.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: yemen-travel-site
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Yemen_Travel_Site {
    const OPTION = 'yemen_travel_settings';
    const VERSION = '1.0.0';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('yemen_travel_home', [$this, 'home_shortcode']);
        add_shortcode('yemen_destinations', [$this, 'destinations_shortcode']);
    }

    private function defaults() {
        return ['api_url' => '', 'api_token' => '', 'whatsapp' => '', 'cache_ttl' => 300];
    }

    private function settings() {
        return wp_parse_args(get_option(self::OPTION, []), $this->defaults());
    }

    public function admin_menu() {
        add_options_page('Yemen Travel', 'Yemen Travel', 'manage_options', 'yemen-travel', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('yemen_travel_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : [];
        $url = isset($input['api_url']) ? esc_url_raw(untrailingslashit(trim($input['api_url'])), ['http', 'https']) : '';
        $parts = $url ? wp_parse_url($url) : false;
        return [
            'api_url' => is_array($parts) && !empty($parts['host']) && in_array($parts['scheme'] ?? '', ['http', 'https'], true) ? $url : '',
            'api_token' => isset($input['api_token']) ? sanitize_text_field($input['api_token']) : '',
            'whatsapp' => isset($input['whatsapp']) ? preg_replace('/[^0-9]/', '', (string) $input['whatsapp']) : '',
            'cache_ttl' => isset($input['cache_ttl']) ? max(0, min(86400, absint($input['cache_ttl']))) : 300,
        ];
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = $this->settings();
        ?>
        <div class="wrap">
            <h1>Yemen Travel — رِحلات اليمن</h1>
            <p>اربط الموقع بخادم Strapi. أدخل العنوان الأساسي فقط، مثل <code>https://cms.example.com</code>، من دون <code>/api</code>.</p>
            <form method="post" action="options.php">
                <?php settings_fields('yemen_travel_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="yt-api-url">عنوان Strapi</label></th><td><input class="regular-text" type="url" id="yt-api-url" name="<?php echo esc_attr(self::OPTION); ?>[api_url]" value="<?php echo esc_attr($settings['api_url']); ?>" placeholder="https://cms.example.com" required></td></tr>
                    <tr><th><label for="yt-api-token">رمز القراءة</label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="yt-api-token" name="<?php echo esc_attr(self::OPTION); ?>[api_token]" value="<?php echo esc_attr($settings['api_token']); ?>"><p class="description">أنشئ رمز API بصلاحية قراءة في Strapi. يرسل WordPress الرمز من الخادم ولا يضعه في صفحة الزائر.</p></td></tr>
                    <tr><th><label for="yt-whatsapp">رقم واتساب</label></th><td><input class="regular-text" type="tel" id="yt-whatsapp" name="<?php echo esc_attr(self::OPTION); ?>[whatsapp]" value="<?php echo esc_attr($settings['whatsapp']); ?>" placeholder="9677xxxxxxxx"><p class="description">اكتب رمز الدولة والرقم من دون علامة + أو مسافات.</p></td></tr>
                    <tr><th><label for="yt-cache-ttl">التخزين المؤقت (ثانية)</label></th><td><input type="number" min="0" max="86400" id="yt-cache-ttl" name="<?php echo esc_attr(self::OPTION); ?>[cache_ttl]" value="<?php echo esc_attr($settings['cache_ttl']); ?>"><p class="description">اكتب 0 أثناء تجربة المحتوى لتحديثه في كل زيارة.</p></td></tr>
                </table>
                <?php submit_button('حفظ الإعدادات'); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_assets() {
        if (!is_singular()) {
            return;
        }
        $post = get_post();
        if (!$post || (!has_shortcode($post->post_content, 'yemen_travel_home') && !has_shortcode($post->post_content, 'yemen_destinations'))) {
            return;
        }
        wp_enqueue_style('yemen-travel-site', plugins_url('assets/site.css', __FILE__), [], self::VERSION);
        wp_enqueue_script('yemen-travel-site', plugins_url('assets/site.js', __FILE__), [], self::VERSION, true);
    }

    private function request($path) {
        $settings = $this->settings();
        if (empty($settings['api_url'])) {
            return new WP_Error('yt_no_api_url', 'Strapi URL is not configured.');
        }
        $cache_key = 'yt_' . md5($settings['api_url'] . '|' . $settings['api_token'] . '|' . $path);
        if ((int) $settings['cache_ttl'] > 0) {
            $cached = get_transient($cache_key);
            if (false !== $cached) {
                return $cached;
            }
        }
        $url = trailingslashit($settings['api_url']) . 'api/' . ltrim($path, '/');
        $args = ['timeout' => 12, 'headers' => ['Accept' => 'application/json']];
        if (!empty($settings['api_token'])) {
            $args['headers']['Authorization'] = 'Bearer ' . $settings['api_token'];
        }
        $url_parts = wp_parse_url($url);
        $allow_local_strapi = defined('YTS_ALLOW_DOCKER_STRAPI') && YTS_ALLOW_DOCKER_STRAPI
            && strtolower($url_parts['host'] ?? '') === 'strapi'
            && (int) ($url_parts['port'] ?? 0) === 1337
            && ($url_parts['scheme'] ?? '') === 'http';
        if ($allow_local_strapi) {
            add_filter('http_request_host_is_external', [$this, 'allow_docker_strapi_host'], 10, 3);
            add_filter('http_allowed_safe_ports', [$this, 'allow_docker_strapi_port'], 10, 3);
        }
        try {
            $response = wp_safe_remote_get($url, $args);
        } finally {
            if ($allow_local_strapi) {
                remove_filter('http_request_host_is_external', [$this, 'allow_docker_strapi_host'], 10);
                remove_filter('http_allowed_safe_ports', [$this, 'allow_docker_strapi_port'], 10);
            }
        }
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('yt_api_http', 'Strapi returned HTTP ' . $code);
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || !array_key_exists('data', $body)) {
            return new WP_Error('yt_api_json', 'Strapi response is missing data.');
        }
        if ((int) $settings['cache_ttl'] > 0) {
            set_transient($cache_key, $body['data'], (int) $settings['cache_ttl']);
        }
        return $body['data'];
    }

    public function allow_docker_strapi_host($external, $host, $url) {
        return $external || (defined('YTS_ALLOW_DOCKER_STRAPI') && YTS_ALLOW_DOCKER_STRAPI && strtolower($host) === 'strapi');
    }

    public function allow_docker_strapi_port($ports, $host, $url) {
        if (defined('YTS_ALLOW_DOCKER_STRAPI') && YTS_ALLOW_DOCKER_STRAPI && strtolower($host) === 'strapi') {
            $parts = wp_parse_url($url);
            if ((int) ($parts['port'] ?? 0) === 1337 && ($parts['scheme'] ?? '') === 'http') {
                $ports[] = 1337;
            }
        }
        return array_values(array_unique($ports));
    }

    private function item_attributes($item) {
        if (!is_array($item)) {
            return [];
        }
        return isset($item['attributes']) && is_array($item['attributes']) ? $item['attributes'] : $item;
    }

    private function single($value) {
        if (isset($value['data'])) {
            $value = $value['data'];
        }
        if (is_array($value) && isset($value[0])) {
            $value = $value[0];
        }
        return $this->item_attributes($value);
    }

    private function media_url($value) {
        if (is_string($value)) {
            return esc_url($value);
        }
        if (!is_array($value)) {
            return '';
        }
        $media = $this->single($value);
        $url = $media['url'] ?? '';
        if (!$url) {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return esc_url($url);
        }
        return esc_url(untrailingslashit($this->settings()['api_url']) . '/' . ltrim($url, '/'));
    }

    private function image_url($item) {
        $url = $item['imageUrl'] ?? $item['coverImageUrl'] ?? '';
        if ($url) {
            return esc_url($url);
        }
        return $this->media_url($item['coverImage'] ?? $item['image'] ?? null);
    }

    private function site_settings() {
        $data = $this->request('site-setting?populate=*');
        return is_wp_error($data) ? [] : $this->single($data);
    }

    private function destinations() {
        $data = $this->request('destinations?sort=sortOrder:asc&pagination[pageSize]=24');
        if (is_wp_error($data) || !is_array($data)) {
            return [];
        }
        return array_map([$this, 'item_attributes'], $data);
    }

    private function image_credit($item) {
        $credit = $item['imageCredit'] ?? '';
        $source = $item['imageSourceUrl'] ?? '';
        $license = $item['imageLicenseUrl'] ?? '';
        if (!$credit && !$source && !$license) {
            return '';
        }
        $parts = [];
        if ($credit) {
            $parts[] = esc_html($credit);
        }
        if ($source) {
            $parts[] = '<a href="' . esc_url($source) . '" target="_blank" rel="noopener noreferrer">مصدر الصورة</a>';
        }
        if ($license) {
            $parts[] = '<a href="' . esc_url($license) . '" target="_blank" rel="noopener noreferrer">الترخيص</a>';
        }
        return '<small class="yt-credit">' . implode(' · ', $parts) . '</small>';
    }

    private function destination_card($item) {
        $name = $item['name'] ?? '';
        $image = $this->image_url($item);
        $region = $item['region'] ?? '';
        $summary = $item['summary'] ?? '';
        $heritage = $item['heritageUrl'] ?? '';
        ob_start();
        ?>
        <article class="yt-card">
            <?php if ($image) : ?><div class="yt-card-media"><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy"><?php if ($region) : ?><span class="yt-region"><?php echo esc_html($region); ?></span><?php endif; ?></div><?php endif; ?>
            <div class="yt-card-body"><h3><?php echo esc_html($name); ?></h3><p><?php echo esc_html($summary); ?></p>
                <?php if ($heritage) : ?><a class="yt-text-link" href="<?php echo esc_url($heritage); ?>" target="_blank" rel="noopener noreferrer">اكتشف الموقع التراثي <span aria-hidden="true">↗</span></a><?php endif; ?>
                <?php echo $this->image_credit($item); ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    public function destinations_shortcode() {
        $destinations = $this->destinations();
        ob_start();
        ?>
        <section class="yt-section yt-destinations" dir="rtl" lang="ar">
            <div class="yt-section-heading"><span class="yt-eyebrow">من الجزر إلى المدن التاريخية</span><h2>وجهات تستحق أن تُروى</h2><p>تعرف على تنوع اليمن من خلال معلومات وصور موثقة، ثم افتح المصدر الرسمي لكل موقع.</p></div>
            <?php if ($destinations) : ?><div class="yt-card-grid"><?php foreach ($destinations as $destination) : ?><?php echo $this->destination_card($destination); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; ?></div>
            <?php else : ?><p class="yt-empty">لم تُضف وجهات بعد. أضف وجهات وانشرها في Strapi، ثم راجع إعداد الاتصال في لوحة WordPress.</p><?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function home_shortcode() {
        $site = $this->site_settings();
        $destinations = $this->destinations();
        $name = $site['siteName'] ?? 'رِحلات اليمن';
        $headline = $site['heroTitle'] ?? 'اليمن… حكاية من بحرٍ وجبلٍ وتاريخ';
        $tagline = $site['heroText'] ?? $site['tagline'] ?? 'اكتشف جزرًا فريدة ومدنًا عريقة وثقافةً لا تشبه سواها.';
        $hero_image = $site['heroImageUrl'] ?? '';
        $hero_credit = [
            'imageCredit' => $site['heroImageCredit'] ?? '',
            'imageSourceUrl' => $site['heroImageSourceUrl'] ?? '',
            'imageLicenseUrl' => $site['heroImageLicenseUrl'] ?? '',
        ];
        if (!$hero_image && !empty($destinations[0])) {
            $hero_image = $this->image_url($destinations[0]);
            $hero_credit = $destinations[0];
        }
        $phone = $site['contactPhone'] ?? '';
        $email = $site['contactEmail'] ?? '';
        $whatsapp = $site['contactWhatsApp'] ?? $this->settings()['whatsapp'];
        $whatsapp = preg_replace('/[^0-9]/', '', (string) $whatsapp);
        ob_start();
        ?>
        <div class="yemen-travel-site" dir="rtl" lang="ar">
            <header class="yt-header"><a class="yt-brand" href="#yt-home"><span class="yt-brand-mark" aria-hidden="true">ي</span><span><?php echo esc_html($name); ?><small>اكتشف اليمن</small></span></a><button class="yt-nav-toggle" type="button" aria-expanded="false" aria-controls="yt-navigation">القائمة</button><nav id="yt-navigation" class="yt-nav"><a href="#yt-destinations">الوجهات</a><a href="#yt-story">عن اليمن</a><a href="#yt-contact">تواصل</a></nav><a class="yt-header-cta" href="#yt-contact">خطط لرحلتك <span aria-hidden="true">←</span></a></header>
            <section id="yt-home" class="yt-hero"<?php if ($hero_image) : ?> style="<?php echo esc_attr('--yt-hero-image:url(' . esc_url_raw($hero_image) . ')'); ?>"<?php endif; ?>><div class="yt-hero-content"><span class="yt-eyebrow yt-eyebrow-light">أرض الحكايات والدهشة</span><h1><?php echo esc_html($headline); ?></h1><p><?php echo esc_html($tagline); ?></p><div class="yt-hero-actions"><a class="yt-button yt-button-accent" href="#yt-destinations">استكشف الوجهات <span aria-hidden="true">←</span></a><a class="yt-button yt-button-quiet" href="#yt-story">اكتشف الحكاية</a></div></div><div class="yt-hero-note"><span>01 / 04</span><span>رحلة إلى قلب الجزيرة العربية</span></div><?php echo $this->image_credit($hero_credit); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
            <div class="yt-intro-strip"><span class="yt-intro-symbol" aria-hidden="true">✳</span><p>من تضاريس سقطرى الفريدة إلى عمارة صنعاء وشبام — مساحة تجمع الوجهات اليمنية ومعلوماتها ومصادرها.</p><a href="#yt-destinations">ابدأ الاستكشاف <span aria-hidden="true">↙</span></a></div>
            <div id="yt-destinations"><?php echo $this->destinations_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <section class="yt-story" id="yt-story" dir="rtl" lang="ar"><div class="yt-story-index">YEMEN<br>01 — 04</div><div class="yt-story-copy"><span class="yt-eyebrow">مكان متعدد الطبقات</span><h2>رحلة واحدة، عوالم متباينة</h2><p>تتغير ملامح الرحلة بين الساحل والمرتفعات والوديان والجزر. هنا تبدأ بالتعرّف إلى المكان، ثم تتابع إلى مصادره التراثية وتفاصيله.</p><a class="yt-text-link" href="https://whc.unesco.org/en/statesparties/ye/" target="_blank" rel="noopener noreferrer">اطّلع على مواقع اليمن لدى اليونسكو <span aria-hidden="true">↗</span></a></div><div class="yt-story-art" aria-hidden="true"><span>اليمن</span><i>Y</i></div></section>
            <section class="yt-contact" id="yt-contact" dir="rtl" lang="ar"><div><span class="yt-eyebrow yt-eyebrow-light">ابدأ التخطيط</span><h2>أي حكاية تريد أن تعيشها؟</h2><p>تواصل مع فريقك المحلي لإعداد معلومات رحلتك. أضف رقم التواصل والبريد في إعدادات Strapi.</p></div><div class="yt-contact-actions"><?php if ($whatsapp) : ?><a class="yt-button yt-button-accent" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener noreferrer">تواصل عبر واتساب <span aria-hidden="true">↗</span></a><?php endif; ?><?php if ($email) : ?><a class="yt-button yt-button-outline" href="mailto:<?php echo esc_attr(sanitize_email($email)); ?>">راسلنا بالبريد</a><?php endif; ?><?php if (!$whatsapp && !$email && $phone) : ?><a class="yt-button yt-button-accent" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>">اتصل بنا</a><?php endif; ?><?php if (!$whatsapp && !$email && !$phone) : ?><span class="yt-contact-hint">أضف رقم واتساب أو بريدًا إلكترونيًا في Strapi لإظهار وسيلة التواصل.</span><?php endif; ?></div></section>
            <footer class="yt-footer"><a class="yt-brand yt-brand-footer" href="#yt-home"><span class="yt-brand-mark" aria-hidden="true">ي</span><span><?php echo esc_html($name); ?><small>دليل سياحي رقمي لليمن</small></span></a><span>المعلومات التراثية مرتبطة بمصادرها. © <?php echo esc_html(wp_date('Y')); ?></span><a href="https://whc.unesco.org/en/statesparties/ye/" target="_blank" rel="noopener noreferrer">مصادر التراث <span aria-hidden="true">↗</span></a></footer>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Yemen_Travel_Site();

