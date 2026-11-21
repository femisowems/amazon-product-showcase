<?php
/**
 * Plugin Name: Amazon Product Showcase
 * Description: Showcase any Amazon product on any WordPress page via shortcode or Elementor widget, in 3 selectable styles. The product URL is set in Settings (or per shortcode/widget) — it is not hardcoded. Theme-agnostic.
 * Version: 1.7.4
 * Author: StarterDev
 * Text Domain: amazon-product-showcase
 */

if (!defined('ABSPATH')) { exit; }

define('APS_VERSION', '1.7.4');

/* ---------- Product data ----------
   Nothing about a specific product is hardcoded. The URL comes at runtime from,
   in order: shortcode/widget attributes, then Settings -> Amazon Product.
   The ASIN is parsed out of that URL. Display fields come from Settings
   (editable there) with per-shortcode overrides. */
function aps_asin_from_url($url) {
    if (preg_match('~/(?:dp|gp/product|gp/aw/d|exec/obidos/ASIN)/([A-Z0-9]{10})~i', (string) $url, $m)) { return strtoupper($m[1]); }
    if (preg_match('~[?&]asin=([A-Z0-9]{10})~i', (string) $url, $m)) { return strtoupper($m[1]); }
    return '';
}
function aps_default_product($options = array(), $url = '') {
    // No manual product fields: every value below starts empty and is filled
    // only by the auto-pull from the Amazon URL (PA-API or page read).
    return array(
        'asin' => aps_asin_from_url($url), 'url' => $url,
        'title' => '', 'subtitle' => '', 'author' => '', 'editor' => '',
        'format' => '', 'brand' => '', 'price' => '', 'currency' => '$',
        'rating' => '', 'reviews' => '', 'reviews_list' => array(), 'pages' => '', 'language' => '',
        'pubdate' => '', 'filesize' => '', 'description' => '', 'author_bio' => '',
        'cover_text' => '', 'image' => '',
    );
}

function aps_styles() {
    return array(
        'clean' => __('1 — Clean Professional', 'amazon-product-showcase'),
        'dark'  => __('2 — Dark Premium', 'amazon-product-showcase'),
        'warm'  => __('3 — Warm Editorial', 'amazon-product-showcase'),
    );
}

/* Map numeric / alias style values to a canonical key */
function aps_normalize_style($style) {
    $map = array(
        '1' => 'clean', 'clean' => 'clean', 'professional' => 'clean',
        '2' => 'dark',  'dark' => 'dark',   'premium' => 'dark',
        '3' => 'warm',  'warm' => 'warm',   'editorial' => 'warm',
    );
    $key = strtolower(trim((string) $style));
    return isset($map[$key]) ? $map[$key] : '';
}

/* ---------- Settings ---------- */
function aps_register_settings() {
    register_setting('aps_settings', 'aps_options', 'aps_sanitize_options');
    add_settings_section('aps_main', __('Product Showcase Settings', 'amazon-product-showcase'), '__return_false', 'amazon-product-showcase');
    add_settings_field('aps_style', __('Default style', 'amazon-product-showcase'), 'aps_field_style', 'amazon-product-showcase', 'aps_main');
    add_settings_field('aps_url', __('Amazon product URL', 'amazon-product-showcase'), 'aps_field_url', 'amazon-product-showcase', 'aps_main');
    add_settings_field('aps_tag', __('Amazon affiliate tag (optional)', 'amazon-product-showcase'), 'aps_field_tag', 'amazon-product-showcase', 'aps_main');
    add_settings_field('aps_button', __('Button text', 'amazon-product-showcase'), 'aps_field_button', 'amazon-product-showcase', 'aps_main');
    add_settings_field('aps_layout', __('Default layout', 'amazon-product-showcase'), 'aps_field_layout', 'amazon-product-showcase', 'aps_main');
    add_settings_field('aps_defaults', __('Placeholder defaults', 'amazon-product-showcase'), 'aps_field_defaults', 'amazon-product-showcase', 'aps_main');
    add_settings_field('aps_simple', __('Simple sections', 'amazon-product-showcase'), 'aps_field_simple', 'amazon-product-showcase', 'aps_main');
}
add_action('admin_init', 'aps_register_settings');

function aps_sanitize_options($input) {
    $out = array();
    $style = aps_normalize_style(isset($input['style']) ? $input['style'] : '');
    $out['style'] = $style ? $style : 'clean';
    $out['url'] = isset($input['url']) ? esc_url_raw($input['url']) : '';
    $out['tag'] = isset($input['tag']) ? sanitize_text_field($input['tag']) : '';
    $out['button_text'] = isset($input['button_text']) ? sanitize_text_field($input['button_text']) : 'Buy now on Amazon';
    $out['layout'] = (isset($input['layout']) && in_array($input['layout'], array('card', 'full-page', 'showcase', 'editorial', 'landing', 'sidebar', 'split', 'story', 'bento'), true)) ? $input['layout'] : 'full-page';
    $out['use_defaults'] = !empty($input['use_defaults']) ? 1 : 0;
    $out['simple'] = !empty($input['simple']) ? 1 : 0;
    $out['access_key'] = isset($input['access_key']) ? sanitize_text_field($input['access_key']) : '';
    $out['secret_key'] = isset($input['secret_key']) ? sanitize_text_field($input['secret_key']) : '';
    return $out;
}

function aps_get_options() {
    $defaults = array('style' => 'clean', 'url' => '', 'tag' => '', 'button_text' => 'Buy now on Amazon', 'layout' => 'full-page', 'access_key' => '', 'secret_key' => '', 'update_feed' => '', 'use_defaults' => 1, 'simple' => 0);
    $saved = get_option('aps_options', array());
    return array_merge($defaults, is_array($saved) ? $saved : array());
}

function aps_field_defaults() {
    $o = aps_get_options();
    printf('<label><input type="checkbox" name="aps_options[use_defaults]" value="1" %s> %s</label>', checked($o['use_defaults'], 1, false), esc_html__('Fill any block whose pulled data is missing with sample placeholder content, so every layout renders as a complete page', 'amazon-product-showcase'));
    echo '<p class="description">' . esc_html__('Real pulled data always wins — placeholders only appear where Amazon returned nothing (e.g. review cards on a pull that carried no reviews). Turn off for a live site if you prefer empty blocks to hide instead. Per-instance override: defaults="0" in the shortcode / widget.', 'amazon-product-showcase') . '</p>';
}
function aps_field_simple() {
    $o = aps_get_options();
    printf('<label><input type="checkbox" name="aps_options[simple]" value="1" %s> %s</label>', checked($o['simple'], 1, false), esc_html__('Render simpler pages: hide the decorative extras (highlight tiles, stat strip, testimonial banner, closing CTA band) and keep hero, description, details and reviews', 'amazon-product-showcase'));
    echo '<p class="description">' . esc_html__('Per-instance override: simple="1" / simple="0" in the shortcode or the Elementor widget switcher.', 'amazon-product-showcase') . '</p>';
}
function aps_field_style() {
    $o = aps_get_options();
    echo '<select name="aps_options[style]">';
    foreach (aps_styles() as $k => $label) {
        printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($o['style'], $k, false), esc_html($label));
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Applies site-wide. Can be overridden per shortcode with style="clean|dark|warm" (or 1|2|3), and per Elementor widget in its controls.', 'amazon-product-showcase') . '</p>';
}
function aps_field_url() {
    $o = aps_get_options();
    printf('<input type="url" class="regular-text" name="aps_options[url]" value="%s">', esc_attr($o['url']));
    echo '<p class="description">' . esc_html__('Paste any Amazon product link here, e.g. https://www.amazon.ca/dp/XXXXXXXXXX/ — or pass url="..." in the shortcode / widget instead. Nothing is hardcoded.', 'amazon-product-showcase') . '</p>';
}
function aps_field_tag() {
    $o = aps_get_options();
    printf('<input type="text" class="regular-text" name="aps_options[tag]" value="%s" placeholder="yourtag-20">', esc_attr($o['tag']));
}
function aps_field_button() {
    $o = aps_get_options();
    printf('<input type="text" class="regular-text" name="aps_options[button_text]" value="%s">', esc_attr($o['button_text']));
}
function aps_field_layout() {
    $o = aps_get_options();
    echo '<select name="aps_options[layout]">';
    printf('<option value="full-page" %s>%s</option>', selected($o['layout'], 'full-page', false), esc_html__('Full page', 'amazon-product-showcase'));
    printf('<option value="card" %s>%s</option>', selected($o['layout'], 'card', false), esc_html__('Card only', 'amazon-product-showcase'));
    printf('<option value="showcase" %s>%s</option>', selected($o['layout'], 'showcase', false), esc_html__('Showcase page (rivals a designed book page)', 'amazon-product-showcase'));
    printf('<option value="editorial" %s>%s</option>', selected($o['layout'], 'editorial', false), esc_html__('Design A — Editorial Split (full page)', 'amazon-product-showcase'));
    printf('<option value="landing" %s>%s</option>', selected($o['layout'], 'landing', false), esc_html__('Design B — Centered Landing (full page)', 'amazon-product-showcase'));
    printf('<option value="sidebar" %s>%s</option>', selected($o['layout'], 'sidebar', false), esc_html__('Design C — Sidebar Commerce (full page)', 'amazon-product-showcase'));
    printf('<option value="split" %s>%s</option>', selected($o['layout'], 'split', false), esc_html__('Design D — Split Hero (full page)', 'amazon-product-showcase'));
    printf('<option value="story" %s>%s</option>', selected($o['layout'], 'story', false), esc_html__('Design E — Story Scroll (full page)', 'amazon-product-showcase'));
    printf('<option value="bento" %s>%s</option>', selected($o['layout'], 'bento', false), esc_html__('Design F — Bento Grid (full page)', 'amazon-product-showcase'));
    echo '</select>';
}

function aps_settings_page() {
    add_options_page('Amazon Product Showcase', 'Amazon Product', 'manage_options', 'amazon-product-showcase', 'aps_render_settings_page');
}
add_action('admin_menu', 'aps_settings_page');

function aps_render_settings_page() {
    if (!current_user_can('manage_options')) { return; }
    $o = aps_get_options();
    echo '<div class="wrap"><h1>Amazon Product Showcase</h1>';
    echo '<p>Shortcode: <code>[amazon_product]</code> &nbsp;|&nbsp; Override style: <code>[amazon_product style="dark"]</code> &nbsp;|&nbsp; Layouts: <code>card</code>, <code>full-page</code>, <code>showcase</code>, <code>editorial</code>, <code>landing</code>, <code>sidebar</code>, <code>split</code>, <code>story</code>, <code>bento</code> — e.g. <code>[amazon_product layout="editorial"]</code></p>';
    echo '<form method="post" action="options.php">';
    settings_fields('aps_settings');
    do_settings_sections('amazon-product-showcase');
    // Updates panel — manual check button + feed configuration
    $info = get_option('aps_update_info', array());
    echo '<h2>Updates</h2>';
    printf('<p>Current version: <strong>%s</strong></p>', esc_html(APS_VERSION));
    if (!empty($info['latest'])) {
        printf('<p>Latest known: <strong>%s</strong> &nbsp; Last checked: %s</p>', esc_html($info['latest']), esc_html(isset($info['checked_at']) ? $info['checked_at'] : ''));
        if (!empty($info['update_available'])) {
            if (!empty($info['download_url'])) {
                echo '<p><strong>An update is available.</strong> Go to <a href="' . esc_url(admin_url('plugins.php')) . '">Plugins</a> and click <em>Update now</em> under Amazon Product Showcase.</p>';
                printf('<p><a class="button button-primary" href="%s">Download v%s</a></p>', esc_url($info['download_url']), esc_html($info['latest']));
            } else {
                echo '<p><strong>An update is available.</strong> Please install it from the manual zip provided by the developer — one-click download activates once the GitHub release for this version is live.</p>';
            }
        } else {
            echo '<p>&#10003; You are up to date.</p>';
        }
    }
    if (isset($_GET['aps_update_check'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status flag only, no state change here
        $msgs = array('uptodate' => 'Checked — you are on the latest version.', 'available' => 'Checked — a new version is available, see below.', 'nofeed' => 'No update feed URL is set yet — add one below, then check again.', 'error' => 'The update check failed — the feed could not be reached or did not return valid JSON.');
        $k = sanitize_key(wp_unslash($_GET['aps_update_check'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($msgs[$k])) { printf('<div class="notice notice-info inline"><p>%s</p></div>', esc_html($msgs[$k])); }
    }
    $check_url = wp_nonce_url(admin_url('admin-post.php?action=aps_check_updates'), 'aps_check_updates');
    printf('<p><a class="button button-primary" href="%s">Check for updates</a></p>', esc_url($check_url));
    printf('<p><label>Update feed URL <input type="url" class="regular-text" name="aps_options[update_feed]" value="%s" placeholder="https://.../amazon-product-showcase.json"></label></p>', esc_attr($o['update_feed']));
    echo '<p class="description">A JSON feed like your other apps: {"latest": "1.0.3", "version": "1.0.3", "download_url": "https://.../amazon-product-showcase.zip"}. Set it once — after that, the Check for updates button (here, and on the Plugins screen) is all you need. WordPress will also surface available updates natively on the Plugins screen.</p>';
    // Auto-fetch status + refresh button
    echo '<h2>Product details — auto-fetch</h2>';
    echo '<p class="description">Details are pulled from the Amazon product URL: with PA-API keys + affiliate/partner tag below, it uses Amazon\'s official Product Advertising API (signed GetItems, cached 24 hours). Without keys, it makes a best-effort read of the product page itself — Amazon often blocks that, in which case the status below says so and the card renders only what could be pulled.</p>';
    $last = get_option('aps_fetch_status', array());
    if (!empty($last)) { printf('<p>Last pull: <strong>%s</strong> via %s at %s</p>', esc_html(isset($last['ok']) && $last['ok'] ? 'success' : 'failed — nothing pulled'), esc_html(isset($last['source']) ? $last['source'] : ''), esc_html(isset($last['at']) ? $last['at'] : '')); if (!empty($last['message'])) { printf('<p class="description">%s</p>', esc_html($last['message'])); } }
    $fetchlog = get_option('aps_fetch_log', array());
    if (is_array($fetchlog) && $fetchlog) {
        echo '<h3>Fetch log</h3><table class="widefat striped" style="max-width:1050px"><thead><tr><th>When</th><th>ASIN</th><th>Source</th><th>Result</th><th>Fields pulled</th><th>Cached until</th></tr></thead><tbody>';
        foreach (array_slice($fetchlog, 0, 10) as $e) {
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($e['at']), esc_html($e['asin']), esc_html($e['source']), !empty($e['ok']) ? '✓ success' : '✗ failed (cover only)', esc_html($e['fields']), esc_html($e['cached_until']));
        }
        echo '</tbody></table>';
    }
    $refresh_url = wp_nonce_url(admin_url('admin-post.php?action=aps_refresh_product'), 'aps_refresh_product');
    printf('<p><a class="button" href="%s">Refresh product details now</a></p>', esc_url($refresh_url));
    // PA-API keys (stored in the same option array)
    echo '<h2>Amazon Product Advertising API (optional)</h2>';
    echo '<p class="description">Add Access Key, Secret Key and your affiliate/partner tag (the Affiliate tag field above) and the plugin pulls product details automatically via PA-API. Without keys it tries a best-effort page read instead.</p>';
    printf('<p><label>Access Key <input type="text" class="regular-text" name="aps_options[access_key]" value="%s"></label></p>', esc_attr($o['access_key']));
    printf('<p><label>Secret Key <input type="password" class="regular-text" name="aps_options[secret_key]" value="%s"></label></p>', esc_attr($o['secret_key']));
    submit_button();
    echo '</form></div>';
}

/* ---------- Assets ----------
   Registered up front so the Elementor widget can declare them as
   dependencies (get_style_depends / get_script_depends) — that is what makes
   the styles load inside the Elementor editor preview, where a render-time
   enqueue never arrives. The shortcode path still enqueues directly. */
function aps_register_assets() {
    wp_register_style('aps-style', plugin_dir_url(__FILE__) . 'assets/css/amazon-product-showcase.css', array(), APS_VERSION);
    wp_register_script('aps-frontend', plugin_dir_url(__FILE__) . 'assets/js/amazon-product-showcase.js', array(), APS_VERSION, true);
}
add_action('wp_enqueue_scripts', 'aps_register_assets');

function aps_enqueue_assets() {
    wp_enqueue_style('aps-style');
    wp_enqueue_script('aps-frontend');
}


/* ---------- Auto-fetch product details from the Amazon URL ----------
   Primary: PA-API 5.0 GetItems (official, signed SigV4). Needs Access Key,
   Secret Key and an affiliate/partner tag in Settings.
   Fallback: best-effort parse of the public product page (og tags / price
   markup). Amazon frequently blocks non-browser reads, so a fallback failure
   is recorded in aps_fetch_status; the card then renders only what could
   be derived (at minimum the ASIN cover image).
   Results are cached in a transient for 24 hours (Amazon price-display rules). */
function aps_marketplace_from_url($url) {
    $host = (string) parse_url((string) $url, PHP_URL_HOST);
    if (strpos($host, 'amazon.ca') !== false) { return array('www.amazon.ca', 'webservices.amazon.ca', 'us-east-1'); }
    if (strpos($host, 'amazon.co.uk') !== false) { return array('www.amazon.co.uk', 'webservices.amazon.co.uk', 'eu-west-1'); }
    if (strpos($host, 'amazon.de') !== false) { return array('www.amazon.de', 'webservices.amazon.de', 'eu-west-1'); }
    return array('www.amazon.com', 'webservices.amazon.com', 'us-east-1');
}

function aps_fetch_via_paapi($asin, $url, $options) {
    if (empty($options['access_key']) || empty($options['secret_key']) || empty($options['tag'])) { return null; }
    list($marketplace, $host, $region) = aps_marketplace_from_url($url);
    $service = 'ProductAdvertisingAPI';
    $payload = wp_json_encode(array(
        'ItemIds' => array($asin),
        'Resources' => array('ItemInfo.Title', 'ItemInfo.Features', 'ItemInfo.ByLineInfo', 'ItemInfo.ProductInfo', 'ItemInfo.TechnicalInfo', 'ItemInfo.ContentInfo', 'Images.Primary.Large', 'Offers.Listings.Price', 'Offers.Listings.SavingBasis', 'Offers.Listings.DeliveryInfo.IsPrimeEligible'),
        'PartnerTag' => $options['tag'],
        'PartnerType' => 'Associates',
        'Marketplace' => $marketplace,
    ));
    $ts = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    $target = 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems';
    $signed_headers = 'content-encoding;content-type;host;x-amz-date;x-amz-target';
    $canonical = "POST\n/paapi5/getitems\n\ncontent-encoding:amz-1.0\ncontent-type:application/json; charset=utf-8\nhost:" . $host . "\nx-amz-date:" . $ts . "\nx-amz-target:" . $target . "\n\n" . $signed_headers . "\n" . hash('sha256', $payload);
    $scope = $date . '/' . $region . '/' . $service . '/aws4_request';
    $to_sign = "AWS4-HMAC-SHA256\n" . $ts . "\n" . $scope . "\n" . hash('sha256', $canonical);
    $k = hash_hmac('sha256', $date, 'AWS4' . $options['secret_key'], true);
    $k = hash_hmac('sha256', $region, $k, true);
    $k = hash_hmac('sha256', $service, $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $sig = hash_hmac('sha256', $to_sign, $k);
    $auth = 'AWS4-HMAC-SHA256 Credential=' . $options['access_key'] . '/' . $scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $sig;
    $res = wp_remote_post('https://' . $host . '/paapi5/getitems', array('timeout' => 20, 'body' => $payload, 'headers' => array(
        'Content-Type' => 'application/json; charset=utf-8', 'Content-Encoding' => 'amz-1.0',
        'X-Amz-Date' => $ts, 'X-Amz-Target' => $target, 'Authorization' => $auth,
    )));
    if (is_wp_error($res) || 200 !== (int) wp_remote_retrieve_response_code($res)) { return null; }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($data['ItemsResult']['Items'][0])) { return null; }
    $it = $data['ItemsResult']['Items'][0];
    $out = array();
    if (!empty($it['ItemInfo']['Title']['DisplayValue'])) { $out['title'] = sanitize_text_field($it['ItemInfo']['Title']['DisplayValue']); }
    if (!empty($it['Images']['Primary']['Large']['URL'])) { $out['image'] = esc_url_raw($it['Images']['Primary']['Large']['URL']); }
    if (!empty($it['Offers']['Listings'][0]['Price']['Amount'])) { $out['price'] = number_format((float) $it['Offers']['Listings'][0]['Price']['Amount'], 2); }
    if (!empty($it['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'])) { $out['brand'] = sanitize_text_field($it['ItemInfo']['ByLineInfo']['Brand']['DisplayValue']); }
    if (!empty($it['ItemInfo']['ByLineInfo']['Contributors'])) { foreach ((array) $it['ItemInfo']['ByLineInfo']['Contributors'] as $c) { if (!empty($c['Name']) && isset($c['RoleType']) && $c['RoleType'] === 'Author') { $out['author'] = sanitize_text_field($c['Name']); } } }
    if (!empty($it['ItemInfo']['ContentInfo']['Languages']['DisplayValues'][0]['DisplayValue'])) { $out['language'] = sanitize_text_field($it['ItemInfo']['ContentInfo']['Languages']['DisplayValues'][0]['DisplayValue']); }
    if (!empty($it['ItemInfo']['Features']['DisplayValues'])) { $out['description'] = sanitize_textarea_field(implode(' ', array_map('sanitize_text_field', (array) $it['ItemInfo']['Features']['DisplayValues']))); }
    if (!empty($it['ItemInfo']['ContentInfo']['PublicationDate']['DisplayValue'])) { $out['pubdate'] = sanitize_text_field($it['ItemInfo']['ContentInfo']['PublicationDate']['DisplayValue']); }
    if (!empty($it['ItemInfo']['ContentInfo']['PagesCount']['DisplayValue'])) { $out['pages'] = sanitize_text_field($it['ItemInfo']['ContentInfo']['PagesCount']['DisplayValue']); }
    if (!empty($it['DetailPageURL'])) { $out['url'] = esc_url_raw($it['DetailPageURL']); }
    return $out ? $out : null;
}

function aps_fetch_via_page($url) {
    $res = wp_remote_get($url, array('timeout' => 20, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'));
    if (is_wp_error($res) || 200 !== (int) wp_remote_retrieve_response_code($res)) { return null; }
    return aps_parse_product_html(wp_remote_retrieve_body($res));
}

/* Pure parser: Amazon product-page HTML in, product fields out (null when the
   page is a bot wall or nothing recognisable). Only text sanitising from WP,
   so tests/run-parser-tests.php can exercise it against saved fixture pages
   without a WordPress install. */
function aps_parse_product_html($html) {
    if (!is_string($html) || $html === '') { return null; }
    if (stripos($html, 'captcha') !== false && stripos($html, 'productTitle') === false) { return null; } // blocked / bot wall
    $out = array();
    if (preg_match('~<span[^>]+id="productTitle"[^>]*>(.*?)</span>~s', $html, $m)) { $out['title'] = sanitize_text_field(wp_strip_all_tags($m[1])); }
    // Cover image: many Amazon pages have no og:image — the hi-res URL lives in the image JSON
    if (preg_match('~"hiRes":"(https:[^"]+)"~', $html, $m)) { $out['image'] = esc_url_raw($m[1]); }
    elseif (preg_match('~id="landingImage"[^>]*data-a-dynamic-image="&quot;(https:[^&]+)~s', $html, $m)) { $out['image'] = esc_url_raw($m[1]); }
    elseif (preg_match('~og:image[^>]*content="([^"]+)"~i', $html, $m)) { $out['image'] = esc_url_raw($m[1]); }
    // Price: whole/fraction spans, or the aria-label price span
    if (preg_match('~<span class="a-price-whole">([0-9,]+)<span class="a-price-decimal">.</span></span><span class="a-price-fraction">([0-9]{2})</span>~', $html, $m)) { $out['price'] = str_replace(',', '', $m[1]) . '.' . $m[2]; }
    elseif (preg_match('~aria-label="\$([0-9][0-9,]*\.[0-9]{2})" class="a-size-base a-color-price~', $html, $m)) { $out['price'] = str_replace(',', '', $m[1]); }
    elseif (preg_match('~<span class="a-price[^"]*"[^>]*>\s*<span class="a-offscreen">\$?([0-9][0-9,]*\.[0-9]{2})</span>~s', $html, $m)) { $out['price'] = str_replace(',', '', $m[1]); }
    elseif (preg_match('~<span[^>]+class="a-offscreen"[^>]*>\$?([0-9][0-9,]*\.[0-9]{2})</span>~', $html, $m)) { $out['price'] = str_replace(',', '', $m[1]); }
    if (preg_match('~<meta[^>]+name="description"[^>]+content="([^"]+)"~i', $html, $m)) { $out['description'] = sanitize_textarea_field($m[1]); }
    if (preg_match('~([0-9]\.[0-9]) out of 5 stars~', $html, $m)) { $out['rating'] = $m[1]; }
    if (preg_match('~id="acrCustomerReviewText" aria-label="([0-9,]+) Reviews?"~', $html, $m)) { $out['reviews'] = str_replace(',', '', $m[1]); }
    elseif (preg_match('~id="acrCustomerReviewText"[^>]*>\(?([0-9,]+)\)?<~', $html, $m)) { $out['reviews'] = str_replace(',', '', $m[1]); }
    // Byline: author / editor spans
    if (preg_match_all('~class="author[^"]*"[^>]*>\s*<a[^>]*>([^<]+)</a>\s*<span class="contribution"[^>]*>\s*<span[^>]*>\((\w+)\)~', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $pair) {
            if ($pair[2] === 'Author' && empty($out['author'])) { $out['author'] = sanitize_text_field($pair[1]); }
            if ($pair[2] === 'Editor' && empty($out['editor'])) { $out['editor'] = sanitize_text_field($pair[1]); }
        }
    }
    // Brand line for non-book products ("Visit the X Store")
    if (preg_match('~Visit the ([^<]{2,60}?) Store~', $html, $m)) { $out['brand'] = sanitize_text_field($m[1]); }
    // Feature bullets make a better description than the meta tag for most products
    $fb = strpos($html, 'id="feature-bullets"');
    if ($fb !== false && preg_match_all('~<span class="a-list-item">\s*([^<]{10,300}?)\s*</span>~s', substr($html, $fb, 8000), $mm)) {
        $items = array();
        foreach ($mm[1] as $it) { $it = sanitize_text_field(html_entity_decode($it, ENT_QUOTES, 'UTF-8')); if ($it !== '' && stripos($it, 'Make sure this fits') === false) { $items[] = $it; } }
        if ($items) { $out['description'] = sanitize_textarea_field(implode(' ', array_slice($items, 0, 5))); }
    }
    if (isset($out['description'])) { $out['description'] = preg_replace('~\s*: Amazon\.[a-z.]+:.*$~', '', $out['description']); }
    // Books: the real blurb lives in the bookDescription widget — it beats the
    // meta description, which merely echoes "Title eBook : Author names".
    $bd = strpos($html, 'bookDescription_feature_div');
    if ($bd !== false) {
        $start = strpos($html, '>', $bd);
        if ($start !== false) {
            $txt = preg_replace('~\s+~', ' ', sanitize_textarea_field(wp_strip_all_tags(substr($html, $start + 1, 6000))));
            foreach (array('Read more', 'About the author') as $stop) { $sp = strpos($txt, $stop); if ($sp !== false && $sp > 200) { $txt = substr($txt, 0, $sp); } }
            $txt = trim($txt);
            if (strlen($txt) > 80) { $out['description'] = substr($txt, 0, 600); }
        }
    }
    // Book detail tiles (Print length / Language / File size / Publication date)
    if (preg_match('~Print length:\s*([0-9,]+ pages)~', $html, $m)) { $out['pages'] = $m[1]; }
    if (preg_match('~>Language</span>.{0,500}?<span>([A-Za-z]+)</span>~s', $html, $m)) { $out['language'] = $m[1]; }
    if (preg_match('~File size</span>.{0,700}?<span>([^<]{2,20})</span>~s', $html, $m)) { $out['filesize'] = sanitize_text_field($m[1]); }
    if (!empty($out['filesize']) || !empty($out['pages'])) { $out['format'] = 'Kindle Edition'; }
    $pd = strpos($html, 'Publication date</span>');
    if ($pd !== false && preg_match('~([A-Z][a-z]{2,9}\.? [0-9]{1,2},? [0-9]{4})~', substr($html, $pd, 1500), $m)) { $out['pubdate'] = $m[1]; }
    // Individual reviews: the product page carries a few full review blocks —
    // enough for testimonial-style cards in the showcase layout.
    $reviews_list = array();
    $off = 0;
    while (count($reviews_list) < 3 && ($rp = strpos($html, 'data-hook="review"', $off)) !== false) {
        $off = $rp + 10;
        $seg = substr($html, $rp, 7000);
        $rev = array('name' => '', 'stars' => '', 'title' => '', 'date' => '', 'body' => '');
        if (preg_match('~<span class="a-profile-name">([^<]+)</span>~', $seg, $m)) { $rev['name'] = sanitize_text_field($m[1]); }
        if (preg_match('~([0-9.]+) out of 5 stars~', $seg, $m)) { $rev['stars'] = $m[1]; }
        $txt = preg_replace('~\s+~', ' ', wp_strip_all_tags($seg));
        if (preg_match('~out of 5 stars\s*(.+?)\s*Reviewed in~s', $txt, $m)) { $rev['title'] = sanitize_text_field($m[1]); }
        if (preg_match('~(Reviewed in .+?[0-9]{4})~', $txt, $m)) { $rev['date'] = sanitize_text_field($m[1]); }
        if (preg_match('~double tap to read brief content\.\s*(.+?)\s*(?:Read more|Read less|Helpful|Report)~s', $txt, $m)) {
            $rev['body'] = sanitize_textarea_field(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            if (strlen($rev['body']) > 500) { $rev['body'] = substr($rev['body'], 0, 500); }
        }
        if ($rev['body'] !== '' || $rev['title'] !== '') { $reviews_list[] = $rev; }
    }
    if ($reviews_list) { $out['reviews_list'] = $reviews_list; }
    return (isset($out['title']) || isset($out['price']) || isset($out['image'])) ? $out : null;
}

function aps_resolve_short_url($url) {
    // Share links from the Amazon page/app (a.co, amzn.to, amzn.eu, ...) carry no ASIN.
    // a.co answers HEAD with a 404, so resolve with a GET first and take the product
    // URL from the final page's canonical link (og:url, then any /dp/ link as fallback).
    $res = wp_remote_get($url, array('timeout' => 15, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'));
    if (!is_wp_error($res)) {
        $body = (string) wp_remote_retrieve_body($res);
        if (preg_match('~<link[^>]+rel="canonical"[^>]+href="([^"]+)"~i', $body, $m) && aps_asin_from_url($m[1])) { return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'); }
        if (preg_match('~property="og:url"[^>]+content="([^"]+)"~i', $body, $m) && aps_asin_from_url($m[1])) { return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'); }
        if (preg_match('~https?://[^"\' ]*/(?:dp|gp/product|gp/aw/d)/[A-Z0-9]{10}[^"\' ]*~i', $body, $m)) { return html_entity_decode($m[0], ENT_QUOTES, 'UTF-8'); }
    }
    // Backup: follow redirect headers hop by hop for shorteners that do answer HEAD
    $current = $url;
    for ($i = 0; $i < 5; $i++) {
        if (aps_asin_from_url($current)) { return $current; }
        $res = wp_remote_head($current, array('timeout' => 15, 'redirection' => 0, 'user-agent' => 'Mozilla/5.0'));
        if (is_wp_error($res)) { return $current; }
        $code = (int) wp_remote_retrieve_response_code($res);
        $loc = wp_remote_retrieve_header($res, 'location');
        if ($code >= 300 && $code < 400 && $loc) { $current = $loc; continue; }
        return $current;
    }
    return $current;
}

/* Observability: every real pull is appended to aps_fetch_log (newest first,
   capped at 20) and shown in Settings — source, fields pulled, cache expiry. */
function aps_log_fetch($asin, $url, $source, $ok, $fields, $ttl) {
    $log = get_option('aps_fetch_log', array());
    if (!is_array($log)) { $log = array(); }
    array_unshift($log, array(
        'at' => current_time('mysql'), 'asin' => $asin, 'url' => $url, 'source' => $source,
        'ok' => (bool) $ok, 'fields' => $fields,
        'cached_until' => date_i18n('Y-m-d H:i:s', time() + (int) $ttl),
    ));
    update_option('aps_fetch_log', array_slice($log, 0, 20), false);
}

function aps_fetch_product($url, $options, $force = false) {
    $asin = aps_asin_from_url($url);
    if ($asin === '' && $url) {
        $resolved = aps_resolve_short_url($url);
        $asin = aps_asin_from_url($resolved);
        if ($asin) { $url = $resolved; }
    }
    if (!$asin || !$url) { return array(); }
    $key = 'aps_product_v2_' . md5($asin . '|' . $url);
    if (!$force) {
        $cached = get_transient($key);
        if (is_array($cached)) { return $cached; }
    }
    $fetched = aps_fetch_via_paapi($asin, $url, $options);
    $source = 'PA-API';
    if (!$fetched) { $fetched = aps_fetch_via_page($url); $source = 'product page (best-effort)'; }
    if ($fetched) {
        if (empty($fetched['image'])) { $fetched['image'] = aps_asin_image_url($asin, $url); }
        set_transient($key, $fetched, DAY_IN_SECONDS);
        aps_log_fetch($asin, $url, $source, true, implode(', ', array_keys($fetched)), DAY_IN_SECONDS);
        update_option('aps_fetch_status', array('ok' => true, 'source' => $source, 'at' => current_time('mysql'), 'message' => 'Pulled for ASIN ' . $asin . '; cached for 24 hours.'));
        return $fetched;
    }
    // Last resort: the cover image is derivable from the ASIN alone (Amazon image CDN)
    $partial = array('image' => aps_asin_image_url($asin, $url));
    set_transient($key, $partial, HOUR_IN_SECONDS);
    aps_log_fetch($asin, $url, $source, false, 'image (ASIN fallback only)', HOUR_IN_SECONDS);
    update_option('aps_fetch_status', array('ok' => false, 'source' => $source, 'at' => current_time('mysql'), 'message' => 'Pull failed for ASIN ' . $asin . ' (no PA-API keys, or Amazon blocked the page read). Showing the ASIN cover image only; will retry in an hour.'));
    return $partial;
}

function aps_handle_refresh_product() {
    if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
    check_admin_referer('aps_refresh_product');
    $o = aps_get_options();
    if (!empty($o['url'])) {
        delete_transient('aps_product_v2_' . md5(aps_asin_from_url($o['url']) . '|' . $o['url']));
        delete_transient('aps_product_' . md5(aps_asin_from_url($o['url']) . '|' . $o['url']));
        aps_fetch_product($o['url'], $o, true);
    }
    wp_safe_redirect(admin_url('options-general.php?page=amazon-product-showcase'));
    exit;
}
add_action('admin_post_aps_refresh_product', 'aps_handle_refresh_product');

/* ---------- Rendering ---------- */
function aps_product_url($options, $atts) {
    $url = !empty($atts['url']) ? esc_url_raw($atts['url']) : $options['url'];
    $tag = !empty($atts['tag']) ? sanitize_text_field($atts['tag']) : $options['tag'];
    if ($tag) { $url = add_query_arg('tag', $tag, $url); }
    return $url;
}

/* Accessible star rating: five base stars with a clipped coloured overlay, so
   any rating (e.g. 4.2) renders accurately without exotic glyphs; the wrapper
   carries a screen-reader label. */
function aps_stars_html($rating, $reviews = '') {
    $r = (float) $rating;
    if ($r <= 0) { return ''; }
    $pct = max(0, min(100, ($r / 5) * 100));
    $label = sprintf('Rated %s out of 5', $rating);
    if ($reviews !== '') { $label .= sprintf(' (%s ratings)', $reviews); }
    return '<span class="aps__stars" role="img" aria-label="' . esc_attr($label) . '"><span class="aps__stars-base" aria-hidden="true">★★★★★</span><span class="aps__stars-fill" style="width:' . $pct . '%" aria-hidden="true">★★★★★</span></span>';
}


/* ---------- Placeholder defaults (v1.5.0) ----------
   Generic sample content, used ONLY to fill fields the auto-pull did not
   return, so every layout/block renders as a complete page. Real pulled
   data always wins. Controlled by the use_defaults option (default on),
   overridable per instance with defaults="0". */
function aps_placeholder_defaults() {
    return array(
        'asin' => 'B0SAMPLE01',
        'title' => 'Aurora Wireless Headphones — Sample Product',
        'format' => 'Premium Edition',
        'author' => 'Jordan Ellis',
        'author_bio' => 'Jordan Ellis is a product designer and writer who has spent a decade testing everyday tech and home goods, and writes the guides they wished existed before buying.',
        'price' => '49.99',
        'rating' => '4.5',
        'reviews' => '128',
        'pages' => '320 pages',
        'language' => 'English',
        'filesize' => '2.4 MB',
        'pubdate' => 'January 1, 2024',
        'description' => 'Meet the Aurora Wireless Headphones, designed for long days and longer playlists. Plush memory-foam earcups and a feather-light frame keep them comfortable from the first call to the last track. Adaptive noise cancelling tunes itself to your surroundings, while the 40-hour battery turns charging into a weekly habit instead of a daily one. And when it is time to top up, ten minutes over USB-C buys you five more hours of listening.',
        'reviews_list' => array(
            array('name' => 'Sam R.', 'stars' => '5', 'title' => 'Comfortable all day', 'date' => 'March 2, 2024', 'body' => 'I wear these through entire workdays and forget they are on. The noise cancelling is strong without feeling pressurised, and the battery genuinely lasts the week.'),
            array('name' => 'Priya K.', 'stars' => '4', 'title' => 'Great value for the price', 'date' => 'February 14, 2024', 'body' => 'Sound is warm and clear, calls come through crisp, and the case feels premium. Docked one star because the equaliser in the app takes a little exploring to find.'),
        ),
    );
}

/* ---------- Shared building blocks for the full-page designs ---------- */
function aps_buy_anchor($url, $button, $p) {
    return '<a class="aps__button" href="' . esc_url($url) . '" target="_blank" rel="sponsored nofollow noopener"'
        . ($p['title'] !== '' ? ' aria-label="' . esc_attr(sprintf('Buy %s on Amazon (opens in a new tab)', $p['title'])) . '"' : '')
        . '>' . esc_html($button) . '</a>';
}
function aps_market_label($url) {
    $host = (string) parse_url($url, PHP_URL_HOST);
    return (strpos($host, 'amazon.') !== false) ? ('Amazon' . substr($host, strpos($host, 'amazon.') + 6)) : 'Amazon';
}
function aps_byline_text($p) {
    $byline = array();
    if ($p['author'] !== '') { $byline[] = 'by ' . $p['author'] . ' (Author)'; }
    if ($p['editor'] !== '') { $byline[] = $p['editor'] . ' (Editor)'; }
    if ($p['brand'] !== '' && $p['author'] === '') { $byline[] = 'by ' . $p['brand']; }
    return implode(', ', $byline);
}
function aps_desc_html($p, $extra_class = '') {
    if ($p['description'] === '') { return ''; }
    $desc = $p['description'];
    if (mb_strlen($desc) > 320) {
        $cut = mb_strrpos(mb_substr($desc, 0, 320), ' ');
        if ($cut === false || $cut < 120) { $cut = 320; }
        return '<p class="aps__desc ' . esc_attr($extra_class) . '"><span class="aps__desc-preview">' . esc_html(mb_substr($desc, 0, $cut)) . '…</span><span class="aps__desc-rest" hidden>' . esc_html(mb_substr($desc, $cut)) . '</span> <button type="button" class="aps__readmore" aria-expanded="false">Read more</button></p>';
    }
    return '<p class="aps__desc ' . esc_attr($extra_class) . '">' . esc_html($desc) . '</p>';
}
function aps_cover_html($p, $class = 'aps__cover') {
    $h = '<figure class="' . esc_attr($class) . '">';
    if (!empty($p['image'])) {
        $img = '<img src="' . esc_url($p['image']) . '" alt="' . esc_attr($p['title'] !== '' ? $p['title'] : 'Product image') . '" loading="lazy" decoding="async">';
        $h .= (!isset($p['zoom']) || $p['zoom'])
            ? '<button type="button" class="aps__zoombtn" data-aps-zoom aria-label="' . esc_attr('Enlarge image of ' . ($p['title'] !== '' ? $p['title'] : 'product')) . '">' . $img . '</button>'
            : $img;
    } else {
        $h .= '<div class="aps__cover-art"><strong>' . esc_html($p['title']) . '</strong><span>' . esc_html($p['format']) . '</span><em>' . esc_html($p['author']) . '</em></div>';
    }
    return $h . '</figure>';
}
function aps_details_items($p) {
    $items = array(array('ASIN', $p['asin']));
    if ($p['brand'] !== '') { $items[] = array('Brand', $p['brand']); }
    if ($p['pubdate'] !== '') { $items[] = array('Publication date', $p['pubdate']); }
    if ($p['language'] !== '') { $items[] = array('Language', $p['language']); }
    if ($p['filesize'] !== '') { $items[] = array('File size', $p['filesize']); }
    if ($p['pages'] !== '') { $items[] = array('Print length', $p['pages']); }
    if ($p['format'] !== '') { $items[] = array('Format', $p['format']); }
    return $items;
}
function aps_details_section_html($p) {
    $h = '<div class="aps__section"><h3>' . esc_html__('Product details', 'amazon-product-showcase') . '</h3><ul class="aps__details">';
    foreach (aps_details_items($p) as $it) { $h .= '<li><strong>' . esc_html($it[0]) . ':</strong> ' . esc_html($it[1]) . '</li>'; }
    return $h . '</ul></div>';
}
function aps_reviews_section_html($p) {
    if ($p['rating'] === '') { return ''; }
    $h = '<div class="aps__section"><h3>' . esc_html__('Customer reviews', 'amazon-product-showcase') . '</h3>';
    $h .= '<p>' . aps_stars_html($p['rating'], $p['reviews']) . ' <strong>' . esc_html($p['rating']) . ' out of 5</strong> — ' . esc_html($p['reviews']) . ' global ratings</p>';
    if (!empty($p['reviews_list'])) {
        $h .= '<div class="aps__reviewcards">';
        foreach ($p['reviews_list'] as $rev) {
            $h .= '<blockquote class="aps__reviewcard">';
            if ($rev['stars'] !== '') { $h .= aps_stars_html($rev['stars']); }
            if ($rev['title'] !== '') { $h .= '<h4>' . esc_html($rev['title']) . '</h4>'; }
            if ($rev['body'] !== '') { $h .= '<p>' . esc_html($rev['body']) . '</p>'; }
            $h .= '<footer>' . esc_html($rev['name']) . ($rev['date'] !== '' ? ' — ' . esc_html($rev['date']) : '') . '</footer></blockquote>';
        }
        $h .= '</div>';
    }
    return $h . '</div>';
}
function aps_tiles_html($p, $mkt_label) {
    $tiles = array();
    if ($p['rating'] !== '') { $tiles[] = array('star', sprintf('%s out of 5', $p['rating']), sprintf('%s global ratings', $p['reviews'] !== '' ? $p['reviews'] : '—')); }
    $fact_main = $p['pages'] !== '' ? $p['pages'] : ($p['format'] !== '' ? $p['format'] : $p['brand']);
    $fact_sub = trim(implode(' · ', array_filter(array($p['language'], $p['filesize'], $p['pubdate']))));
    if ($fact_main !== '') { $tiles[] = array('book', $fact_main, $fact_sub); }
    if ($p['price'] !== '') { $tiles[] = array('tag', $p['currency'] . $p['price'], sprintf('on %s', $mkt_label)); }
    if (!$tiles) { return ''; }
    $icons = array(
        'star' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/></svg>',
        'book' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3h13a1 1 0 011 1v15a1 1 0 01-1 1H5a2 2 0 00-2 2V5a2 2 0 012-2zm0 15a2 2 0 00-2 2h15v-3H5.5A1.5 1.5 0 015 18z"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 3h8l9 9-8 8-9-9V3zm4 3.5A1.75 1.75 0 105.25 4.75 1.75 1.75 0 007 6.5z"/></svg>',
    );
    $h = '<div class="aps__highlights">';
    foreach ($tiles as $t) { $h .= '<div class="aps__highlight">' . $icons[$t[0]] . '<strong>' . esc_html($t[1]) . '</strong><span>' . esc_html($t[2]) . '</span></div>'; }
    return $h . '</div>';
}
function aps_cta_band_html($p, $url, $button) {
    return '<div class="aps__ctaband"><div class="aps__ctaband-text"><strong>' . esc_html($p['title']) . '</strong>'
        . ($p['price'] !== '' ? '<span class="aps__ctaband-price">' . esc_html($p['currency'] . $p['price']) . '</span>' : '')
        . '</div>' . aps_buy_anchor($url, $button, $p) . '</div>';
}

/* ---------- The three full-page designs (v1.3.0) ----------
   Product-agnostic: every block renders only from pulled data and hides
   when its data is absent. Colour comes from the chosen style (clean /
   dark / warm) via the --aps-accent variable. */
function aps_story_chapters($text) {
    $parts = preg_split('/(?<=[.!?])\s+/', trim($text));
    $parts = array_values(array_filter(array_map('trim', $parts), function ($x) { return $x !== ''; }));
    if (count($parts) <= 1) { return $parts; }
    $chunks = array(array(), array(), array());
    foreach ($parts as $i => $part) { $chunks[min(2, intdiv($i * 3, count($parts)))][] = $part; }
    $out = array();
    foreach ($chunks as $c) { if ($c) { $out[] = implode(' ', $c); } }
    return $out;
}

function aps_render_design_page($layout, $p, $url, $button, $style, $simple = false) {
    $mkt = aps_market_label($url);
    $byline = aps_byline_text($p);
    ob_start();
    echo '<article class="aps aps--' . esc_attr($style) . ' aps--' . esc_attr($layout) . ($simple ? ' aps--simple' : '') . '"' . ($p['title'] !== '' ? ' aria-label="' . esc_attr($p['title']) . '"' : '') . '>';

    if ($layout === 'editorial') {
        // A — Editorial Split: headline block, story column + floated cover,
        // pull-quote from the top review, stat strip, details, reviews, CTA.
        echo '<header class="aps__ed-head">';
        if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
        printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
        if ($byline !== '') { printf('<div class="aps__byline">%s</div>', esc_html($byline)); }
        if ($p['rating'] !== '') { printf('<div class="aps__rating">%s <span aria-hidden="true">%s (%s)</span></div>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews'])); }
        echo '</header>';
        echo '<div class="aps__ed-body"><div class="aps__ed-main">';
        echo aps_desc_html($p, 'aps__dropcap');
        if (!empty($p['reviews_list'][0])) {
            $r0 = $p['reviews_list'][0];
            echo '<blockquote class="aps__pullquote">';
            if ($r0['stars'] !== '') { echo aps_stars_html($r0['stars']); }
            if ($r0['title'] !== '') { printf('<p class="aps__pullquote-title">%s</p>', esc_html($r0['title'])); }
            if ($r0['body'] !== '') { printf('<p>%s</p>', esc_html(mb_strlen($r0['body']) > 220 ? mb_substr($r0['body'], 0, 220) . '…' : $r0['body'])); }
            printf('<footer>— %s</footer>', esc_html($r0['name']));
            echo '</blockquote>';
        }
        $stats = array();
        if ($p['rating'] !== '') { $stats[] = array($p['rating'] . ' / 5', 'reader rating'); }
        if ($p['pages'] !== '') { $stats[] = array(str_replace(' pages', '', $p['pages']), 'pages'); }
        if ($p['reviews'] !== '') { $stats[] = array($p['reviews'], 'global ratings'); }
        if ($p['filesize'] !== '') { $stats[] = array($p['filesize'], 'file size'); }
        if ($stats && !$simple) {
            echo '<div class="aps__statstrip">';
            foreach (array_slice($stats, 0, 3) as $st) { printf('<div><strong>%s</strong><span>%s</span></div>', esc_html($st[0]), esc_html($st[1])); }
            echo '</div>';
        }
        echo '<div class="aps__buy">';
        if ($p['price'] !== '') { printf('<span class="aps__price">%s%s</span>', esc_html($p['currency']), esc_html($p['price'])); }
        echo aps_buy_anchor($url, $button, $p);
        echo '</div>';
        printf('<div class="aps__meta">%s</div>', esc_html('Available on ' . $mkt));
        echo aps_details_section_html($p);
        if ($p['author_bio'] !== '') { printf('<div class="aps__section"><h3>%s</h3><p><strong>%s</strong> — %s</p></div>', esc_html__('About the author', 'amazon-product-showcase'), esc_html($p['author']), esc_html($p['author_bio'])); }
        echo aps_reviews_section_html($p);
        echo '</div>'; // ed-main
        echo aps_cover_html($p, 'aps__cover aps__ed-cover');
        echo '</div>'; // ed-body
        if (!$simple) { echo aps_cta_band_html($p, $url, $button); }
    }

    if ($layout === 'landing') {
        // B — Centered Landing: centred hero, oversized price, tiles, reviews,
        // plus a buy bar fixed to the bottom of the viewport.
        echo '<div class="aps__ld-hero">';
        echo aps_cover_html($p, 'aps__cover aps__ld-cover');
        if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
        printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
        if ($byline !== '') { printf('<div class="aps__byline">%s</div>', esc_html($byline)); }
        if ($p['rating'] !== '') { printf('<div class="aps__rating">%s <span aria-hidden="true">%s (%s)</span></div>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews'])); }
        if ($p['price'] !== '') { printf('<div class="aps__ld-price">%s%s</div>', esc_html($p['currency']), esc_html($p['price'])); }
        echo '<div class="aps__ld-buy">' . aps_buy_anchor($url, $button, $p) . '</div>';
        printf('<div class="aps__meta">%s</div>', esc_html('Available on ' . $mkt));
        echo '</div>';
        echo '<div class="aps__ld-desc">' . aps_desc_html($p) . '</div>';
        if (!$simple) { echo aps_tiles_html($p, $mkt); }
        echo aps_details_section_html($p);
        if ($p['author_bio'] !== '') { printf('<div class="aps__section"><h3>%s</h3><p><strong>%s</strong> — %s</p></div>', esc_html__('About the author', 'amazon-product-showcase'), esc_html($p['author']), esc_html($p['author_bio'])); }
        echo aps_reviews_section_html($p);
        echo '<div class="aps__stickybar"><span class="aps__stickybar-title">' . esc_html($p['title']) . '</span>'
            . ($p['price'] !== '' ? '<span class="aps__stickybar-price">' . esc_html($p['currency'] . $p['price']) . '</span>' : '')
            . aps_buy_anchor($url, $button, $p) . '</div>';
    }

    if ($layout === 'sidebar') {
        // C — Sidebar Commerce: sticky buy/spec sidebar, content flows right.
        echo '<div class="aps__sb-wrap">';
        echo '<aside class="aps__sb-side">';
        echo aps_cover_html($p, 'aps__cover aps__sb-cover');
        if ($p['price'] !== '') { printf('<div class="aps__sb-price">%s%s</div>', esc_html($p['currency']), esc_html($p['price'])); }
        echo aps_buy_anchor($url, $button, $p);
        if ($p['filesize'] !== '' || $p['pages'] !== '') { printf('<a class="aps__sample" href="%s" target="_blank" rel="sponsored nofollow noopener">%s</a>', esc_url($url), esc_html__('Send a free sample', 'amazon-product-showcase')); }
        printf('<div class="aps__meta">%s</div>', esc_html('Available on ' . $mkt));
        echo '<ul class="aps__sb-specs">';
        foreach (aps_details_items($p) as $it) { printf('<li><strong>%s:</strong> %s</li>', esc_html($it[0]), esc_html($it[1])); }
        echo '</ul>';
        echo '</aside>';
        echo '<div class="aps__sb-main">';
        if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
        printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
        if ($byline !== '') { printf('<div class="aps__byline">%s</div>', esc_html($byline)); }
        if ($p['rating'] !== '') { printf('<div class="aps__rating">%s <span aria-hidden="true">%s (%s)</span></div>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews'])); }
        echo aps_desc_html($p);
        if (!$simple) { echo aps_tiles_html($p, $mkt); }
        if ($p['author_bio'] !== '') { printf('<div class="aps__section"><h3>%s</h3><p><strong>%s</strong> — %s</p></div>', esc_html__('About the author', 'amazon-product-showcase'), esc_html($p['author']), esc_html($p['author_bio'])); }
        echo aps_reviews_section_html($p);
        echo '</div></div>'; // sb-main, sb-wrap
        if (!$simple) { echo aps_cta_band_html($p, $url, $button); }
    }

    if ($layout === 'split') {
        // D — Split Hero: two-tone hero band (panel + image), trust strip,
        // highlight trio, description, details, reviews, CTA band.
        echo '<div class="aps__sp-hero"><div class="aps__sp-panel">';
        if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
        elseif ($p['brand'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['brand'])); }
        printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
        if ($byline !== '') { printf('<div class="aps__byline">%s</div>', esc_html($byline)); }
        if ($p['rating'] !== '') { printf('<div class="aps__rating">%s <span aria-hidden="true">%s (%s)</span></div>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews'])); }
        if ($p['price'] !== '') { printf('<div class="aps__sp-price">%s%s</div>', esc_html($p['currency']), esc_html($p['price'])); }
        echo '<div class="aps__sp-buy">' . aps_buy_anchor($url, $button, $p) . '</div>';
        printf('<div class="aps__meta">%s</div>', esc_html('Available on ' . $mkt));
        echo '</div>';
        echo aps_cover_html($p, 'aps__cover aps__sp-cover');
        echo '</div>';
        $trust = array();
        if ($p['rating'] !== '') { $trust[] = array($p['rating'] . ' / 5', 'reader rating'); }
        if ($p['reviews'] !== '') { $trust[] = array($p['reviews'], 'global ratings'); }
        if ($p['pages'] !== '') { $trust[] = array($p['pages'], 'print length'); }
        if ($p['filesize'] !== '') { $trust[] = array($p['filesize'], 'file size'); }
        if ($p['language'] !== '') { $trust[] = array($p['language'], 'language'); }
        if ($trust) {
            echo '<div class="aps__truststrip">';
            foreach (array_slice($trust, 0, 4) as $t) { printf('<div><strong>%s</strong><span>%s</span></div>', esc_html($t[0]), esc_html($t[1])); }
            echo '</div>';
        }
        if (!$simple) { echo aps_tiles_html($p, $mkt); }
        echo '<div class="aps__sp-desc">' . aps_desc_html($p) . '</div>';
        echo aps_details_section_html($p);
        if ($p['author_bio'] !== '') { printf('<div class="aps__section"><h3>%s</h3><p><strong>%s</strong> — %s</p></div>', esc_html__('About the author', 'amazon-product-showcase'), esc_html($p['author']), esc_html($p['author_bio'])); }
        echo aps_reviews_section_html($p);
        if (!$simple) { echo aps_cta_band_html($p, $url, $button); }
    }

    if ($layout === 'story') {
        // E — Story Scroll: centred hero, description as numbered chapters,
        // testimonial banner from the top review, reviews, closing buy card.
        echo '<div class="aps__st-hero">';
        if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
        printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
        if ($byline !== '') { printf('<div class="aps__byline">%s</div>', esc_html($byline)); }
        if ($p['rating'] !== '') { printf('<div class="aps__rating">%s <span aria-hidden="true">%s (%s)</span></div>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews'])); }
        echo aps_cover_html($p, 'aps__cover aps__st-cover');
        echo '</div>';
        $chapters = aps_story_chapters($p['description']);
        foreach ($chapters as $i => $chunk) {
            printf('<section class="aps__chapter"><span class="aps__chapter-num" aria-hidden="true">%02d</span><p>%s</p></section>', $i + 1, esc_html($chunk));
            if ($i === 0 && !$simple && !empty($p['reviews_list'][0])) {
                $r0 = $p['reviews_list'][0];
                echo '<div class="aps__banner">';
                if ($r0['stars'] !== '') { echo aps_stars_html($r0['stars']); }
                if ($r0['title'] !== '') { printf('<p class="aps__banner-title">%s</p>', esc_html($r0['title'])); }
                if ($r0['body'] !== '') { printf('<p>%s</p>', esc_html(mb_strlen($r0['body']) > 260 ? mb_substr($r0['body'], 0, 260) . '…' : $r0['body'])); }
                printf('<footer>— %s</footer>', esc_html($r0['name']));
                echo '</div>';
            }
        }
        if (!$simple) { echo aps_tiles_html($p, $mkt); }
        echo aps_details_section_html($p);
        if ($p['author_bio'] !== '') { printf('<div class="aps__section"><h3>%s</h3><p><strong>%s</strong> — %s</p></div>', esc_html__('About the author', 'amazon-product-showcase'), esc_html($p['author']), esc_html($p['author_bio'])); }
        echo aps_reviews_section_html($p);
        echo '<div class="aps__buycard"><strong>' . esc_html($p['title']) . '</strong>'
            . ($p['price'] !== '' ? '<span class="aps__buycard-price">' . esc_html($p['currency'] . $p['price']) . '</span>' : '')
            . aps_buy_anchor($url, $button, $p)
            . '<div class="aps__meta">' . esc_html('Available on ' . $mkt) . '</div></div>';
    }

    if ($layout === 'bento') {
        // F — Bento Grid: everything at a glance in tiles, then the full
        // description / details / reviews below, with a sticky buy bar.
        echo '<div class="aps__bento">';
        echo aps_cover_html($p, 'aps__cover aps__btile aps__btile--img');
        echo '<div class="aps__btile aps__btile--title">';
        if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
        printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
        if ($byline !== '') { printf('<div class="aps__byline">%s</div>', esc_html($byline)); }
        echo '</div>';
        if ($p['price'] !== '') {
            echo '<div class="aps__btile aps__btile--price"><div class="aps__btile-price">' . esc_html($p['currency'] . $p['price']) . '</div><div class="aps__btile-sub">' . esc_html('on ' . $mkt) . '</div>' . aps_buy_anchor($url, $button, $p) . '</div>';
        }
        if ($p['rating'] !== '') {
            echo '<div class="aps__btile aps__btile--rating">' . aps_stars_html($p['rating'], $p['reviews']) . '<strong>' . esc_html($p['rating']) . ' / 5</strong><span>' . esc_html($p['reviews']) . ' global ratings</span></div>';
        }
        echo '<div class="aps__btile aps__btile--specs"><strong>' . esc_html__('Specs', 'amazon-product-showcase') . '</strong><ul>';
        foreach (array_slice(aps_details_items($p), 0, 5) as $it) { printf('<li><span>%s</span> %s</li>', esc_html($it[0]), esc_html($it[1])); }
        echo '</ul></div>';
        if (!empty($p['reviews_list'][0])) {
            $r0 = $p['reviews_list'][0];
            echo '<blockquote class="aps__btile aps__btile--review">';
            if ($r0['stars'] !== '') { echo aps_stars_html($r0['stars']); }
            if ($r0['title'] !== '') { printf('<strong>%s</strong>', esc_html($r0['title'])); }
            printf('<footer>— %s</footer>', esc_html($r0['name']));
            echo '</blockquote>';
        } elseif ($p['description'] !== '') {
            echo '<div class="aps__btile aps__btile--review"><p>' . esc_html(mb_strlen($p['description']) > 140 ? mb_substr($p['description'], 0, 140) . '…' : $p['description']) . '</p></div>';
        }
        if (!empty($p['reviews_list'][1])) {
            $r1 = $p['reviews_list'][1];
            echo '<blockquote class="aps__btile aps__btile--review">';
            if ($r1['stars'] !== '') { echo aps_stars_html($r1['stars']); }
            if ($r1['title'] !== '') { printf('<strong>%s</strong>', esc_html($r1['title'])); }
            printf('<footer>— %s</footer>', esc_html($r1['name']));
            echo '</blockquote>';
        }
        echo '</div>'; // bento grid
        echo '<div class="aps__bento-below">' . aps_desc_html($p) . '</div>';
        echo aps_details_section_html($p);
        if ($p['author_bio'] !== '') { printf('<div class="aps__section"><h3>%s</h3><p><strong>%s</strong> — %s</p></div>', esc_html__('About the author', 'amazon-product-showcase'), esc_html($p['author']), esc_html($p['author_bio'])); }
        echo aps_reviews_section_html($p);
        echo '<div class="aps__stickybar"><span class="aps__stickybar-title">' . esc_html($p['title']) . '</span>'
            . ($p['price'] !== '' ? '<span class="aps__stickybar-price">' . esc_html($p['currency'] . $p['price']) . '</span>' : '')
            . aps_buy_anchor($url, $button, $p) . '</div>';
    }


    echo '</article>';
    return ob_get_clean();
}

function aps_render_product($atts) {
    $options = aps_get_options();
    $style = aps_normalize_style(isset($atts['style']) ? $atts['style'] : '');
    if (!$style) { $style = $options['style']; }
    $layout = (isset($atts['layout']) && in_array($atts['layout'], array('card', 'full-page', 'showcase', 'editorial', 'landing', 'sidebar', 'split', 'story', 'bento'), true)) ? $atts['layout'] : $options['layout'];
    $button = !empty($atts['button_text']) ? sanitize_text_field($atts['button_text']) : $options['button_text'];
    $url = aps_product_url($options, $atts);
    $p = aps_default_product($options, $url);
    // Auto-pulled details are the only source for product data
    $fetched = aps_fetch_product($url, $options);
    foreach (array('title', 'price', 'image', 'description', 'author', 'editor', 'brand', 'format', 'rating', 'reviews', 'reviews_list', 'pages', 'language', 'pubdate', 'filesize') as $pk) {
        if (empty($p[$pk]) && !empty($fetched[$pk])) { $p[$pk] = $fetched[$pk]; }
    }
    if (!empty($atts['title'])) { $p['title'] = sanitize_text_field($atts['title']); }
    if (!empty($atts['price'])) { $p['price'] = sanitize_text_field($atts['price']); }
    if (!empty($atts['image'])) { $p['image'] = esc_url_raw($atts['image']); }
    // Placeholder defaults: fill whatever the pull did not return, so every
    // block renders. Real data (and explicit overrides above) always wins.
    $use_defaults = !empty($options['use_defaults']);
    if (isset($atts['defaults']) && $atts['defaults'] !== '') { $use_defaults = in_array(strtolower((string) $atts['defaults']), array('1', 'yes', 'true', 'on'), true); }
    if ($use_defaults) {
        $ph = aps_placeholder_defaults();
        foreach ($ph as $dk => $dv) {
            if (empty($p[$dk])) { $p[$dk] = $dv; }
        }
        // Pairing rule: placeholder author + bio only travel together. If the
        // author is real (pulled) but the bio was placeholder-filled, drop the
        // bio so the About section hides instead of mismatching.
        if (!empty($p['author']) && $p['author'] !== $ph['author'] && isset($p['author_bio']) && $p['author_bio'] === $ph['author_bio']) {
            $p['author_bio'] = '';
        }
    }
    $zoom = true;
    if (isset($atts['zoom']) && $atts['zoom'] !== '') { $zoom = in_array(strtolower((string) $atts['zoom']), array('1', 'yes', 'true', 'on'), true); }
    $p['zoom'] = $zoom ? 1 : 0;
    $simple = !empty($options['simple']);
    if (isset($atts['simple']) && $atts['simple'] !== '') { $simple = in_array(strtolower((string) $atts['simple']), array('1', 'yes', 'true', 'on'), true); }
    aps_enqueue_assets();
    if (!$url) { return '<div class="aps aps--' . esc_attr($style) . '"><p>' . esc_html__('Set an Amazon product URL in Settings \u2192 Amazon Product, or pass url="..." to the [amazon_product] shortcode.', 'amazon-product-showcase') . '</p></div>'; }

    if (in_array($layout, array('editorial', 'landing', 'sidebar', 'split', 'story', 'bento'), true)) {
        return aps_render_design_page($layout, $p, $url, $button, $style, $simple);
    }
    ob_start();
    $cls = 'aps aps--' . esc_attr($style) . ' aps--' . esc_attr($layout) . ($simple ? ' aps--simple' : '');
    echo '<article class="' . $cls . '"' . ($p['title'] !== '' ? ' aria-label="' . esc_attr($p['title']) . '"' : '') . '>';
    echo '<div class="aps__hero">';
    // Cover
    echo '<figure class="aps__cover">';
    if (!empty($p['image'])) {
        if ($zoom) {
            printf('<button type="button" class="aps__zoombtn" data-aps-zoom aria-label="%s"><img src="%s" alt="%s" loading="lazy" decoding="async"></button>', esc_attr('Enlarge image of ' . ($p['title'] !== '' ? $p['title'] : 'product')), esc_url($p['image']), esc_attr($p['title'] !== '' ? $p['title'] : 'Product image'));
        } else {
            printf('<img src="%s" alt="%s" loading="lazy" decoding="async">', esc_url($p['image']), esc_attr($p['title'] !== '' ? $p['title'] : 'Product image'));
        }
    } else {
        printf('<div class="aps__cover-art"><strong>%s</strong><span>%s</span><em>%s</em></div>', esc_html($p['title']), esc_html($p['format']), esc_html($p['author']));
    }
    echo '</figure>';
    // Main
    echo '<div class="aps__main">';
    if ($p['format'] !== '') { printf('<div class="aps__kicker">%s</div>', esc_html($p['format'])); }
    printf('<h2 class="aps__title">%s</h2>', esc_html($p['title']));
    $byline = array();
    if ($p['author'] !== '') { $byline[] = 'by ' . $p['author'] . ' (Author)'; }
    if ($p['editor'] !== '') { $byline[] = $p['editor'] . ' (Editor)'; }
    if ($byline) { printf('<div class="aps__byline">%s</div>', esc_html(implode(', ', $byline))); }
    elseif ($p['brand'] !== '') { printf('<div class="aps__byline">%s</div>', esc_html($p['brand'])); }
    if ($p['rating'] !== '') { printf('<div class="aps__rating">%s <span aria-hidden="true">%s (%s)</span></div>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews'])); }
    if ($p['description'] !== '') {
        $desc = $p['description'];
        if (mb_strlen($desc) > 320) {
            $cut = mb_strrpos(mb_substr($desc, 0, 320), ' ');
            if ($cut === false || $cut < 120) { $cut = 320; }
            printf('<p class="aps__desc"><span class="aps__desc-preview">%s…</span><span class="aps__desc-rest" hidden>%s</span> <button type="button" class="aps__readmore" aria-expanded="false">Read more</button></p>',
                esc_html(mb_substr($desc, 0, $cut)), esc_html(mb_substr($desc, $cut)));
        } else {
            printf('<p class="aps__desc">%s</p>', esc_html($desc));
        }
    }
    $chips = array_filter(array($p['pages'], $p['language'], $p['pubdate'], $p['filesize']));
    if ($chips) { echo '<div class="aps__chips">'; foreach ($chips as $chip) { printf('<span class="aps__chip">%s</span>', esc_html($chip)); } echo '</div>'; }
    echo '<div class="aps__buy">';
    if ($p['price'] !== '') { printf('<span class="aps__price">%s%s</span>', esc_html($p['currency']), esc_html($p['price'])); }
    printf('<a class="aps__button" href="%s" target="_blank" rel="sponsored nofollow noopener"%s>%s</a>', esc_url($url), $p['title'] !== '' ? ' aria-label="' . esc_attr(sprintf('Buy %s on Amazon (opens in a new tab)', $p['title'])) . '"' : '', esc_html($button));
    if ($p['filesize'] !== '' || $p['pages'] !== '') { printf('<a class="aps__sample" href="%s" target="_blank" rel="sponsored nofollow noopener">%s</a>', esc_url($url), esc_html__('Send a free sample', 'amazon-product-showcase')); }
    echo '</div>';
    $mkt_host = (string) parse_url($url, PHP_URL_HOST);
    $mkt_label = (strpos($mkt_host, 'amazon.') !== false) ? ('Amazon' . substr($mkt_host, strpos($mkt_host, 'amazon.') + 6)) : 'Amazon';
    printf('<div class="aps__meta">%s</div>', esc_html('Available on ' . $mkt_label));
    echo '</div></div>'; // hero

    if ($layout === 'showcase' && !$simple) {
        // Highlights: three data tiles (rating / facts / price) — the icon row
        // of a designed product page, built only from pulled data.
        $tiles = array();
        if ($p['rating'] !== '') {
            $tiles[] = array('star', sprintf('%s out of 5', $p['rating']), sprintf('%s global ratings', $p['reviews'] !== '' ? $p['reviews'] : '—'));
        }
        $fact_main = $p['pages'] !== '' ? $p['pages'] : ($p['format'] !== '' ? $p['format'] : $p['brand']);
        $fact_sub = trim(implode(' · ', array_filter(array($p['language'], $p['filesize'], $p['pubdate']))));
        if ($fact_main !== '') { $tiles[] = array('book', $fact_main, $fact_sub); }
        if ($p['price'] !== '') {
            $tiles[] = array('tag', $p['currency'] . $p['price'], sprintf('on %s', $mkt_label));
        }
        if ($tiles && !$simple) {
            $icons = array(
                'star' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/></svg>',
                'book' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3h13a1 1 0 011 1v15a1 1 0 01-1 1H5a2 2 0 00-2 2V5a2 2 0 012-2zm0 15a2 2 0 00-2 2h15v-3H5.5A1.5 1.5 0 015 18z"/></svg>',
                'tag' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 3h8l9 9-8 8-9-9V3zm4 3.5A1.75 1.75 0 105.25 4.75 1.75 1.75 0 007 6.5z"/></svg>',
            );
            echo '<div class="aps__highlights">';
            foreach ($tiles as $t) {
                printf('<div class="aps__highlight">%s<strong>%s</strong><span>%s</span></div>', $icons[$t[0]], esc_html($t[1]), esc_html($t[2]));
            }
            echo '</div>';
        }
    }

    if ($layout !== 'card') {
        echo '<div class="aps__section"><h3>' . esc_html__('Product details', 'amazon-product-showcase') . '</h3>';
        echo '<ul class="aps__details">';
        printf('<li><strong>ASIN:</strong> %s</li>', esc_html($p['asin']));
        if ($p['brand'] !== '') { printf('<li><strong>Brand:</strong> %s</li>', esc_html($p['brand'])); }
        if ($p['pubdate'] !== '') { printf('<li><strong>Publication date:</strong> %s</li>', esc_html($p['pubdate'])); }
        if ($p['language'] !== '') { printf('<li><strong>Language:</strong> %s</li>', esc_html($p['language'])); }
        if ($p['filesize'] !== '') { printf('<li><strong>File size:</strong> %s</li>', esc_html($p['filesize'])); }
        if ($p['pages'] !== '') { printf('<li><strong>Print length:</strong> %s</li>', esc_html($p['pages'])); }
        echo '</ul></div>';
        if ($p['author_bio'] !== '') {
            echo '<div class="aps__section"><h3>' . esc_html__('About the author', 'amazon-product-showcase') . '</h3>';
            printf('<p><strong>%s</strong> — %s</p>', esc_html($p['author']), esc_html($p['author_bio']));
            echo '</div>';
        }
        if ($p['rating'] !== '') {
            echo '<div class="aps__section"><h3>' . esc_html__('Customer reviews', 'amazon-product-showcase') . '</h3>';
            printf('<p>%s <strong>%s out of 5</strong> — %s global ratings</p>', aps_stars_html($p['rating'], $p['reviews']), esc_html($p['rating']), esc_html($p['reviews']));
            if (!empty($p['reviews_list'])) {
                echo '<div class="aps__reviewcards">';
                foreach ($p['reviews_list'] as $rev) {
                    echo '<blockquote class="aps__reviewcard">';
                    if ($rev['stars'] !== '') { echo aps_stars_html($rev['stars']); }
                    if ($rev['title'] !== '') { printf('<h4>%s</h4>', esc_html($rev['title'])); }
                    if ($rev['body'] !== '') { printf('<p>%s</p>', esc_html($rev['body'])); }
                    printf('<footer>%s%s</footer>', esc_html($rev['name']), $rev['date'] !== '' ? ' — ' . esc_html($rev['date']) : '');
                    echo '</blockquote>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }
    if ($layout === 'showcase') {
        echo '<div class="aps__ctaband">';
        printf('<div class="aps__ctaband-text"><strong>%s</strong>%s</div>', esc_html($p['title']), $p['price'] !== '' ? '<span class="aps__ctaband-price">' . esc_html($p['currency'] . $p['price']) . '</span>' : '');
        printf('<a class="aps__button" href="%s" target="_blank" rel="sponsored nofollow noopener">%s</a>', esc_url($url), esc_html($button));
        echo '</div>';
    }
    echo '</article>';
    return ob_get_clean();
}

/* Shortcode: [amazon_product] [amazon_product style="dark"] [amazon_product layout="card"] */
function aps_shortcode($atts) {
    $atts = shortcode_atts(array('style' => '', 'layout' => '', 'url' => '', 'tag' => '', 'button_text' => '', 'title' => '', 'price' => '', 'image' => '', 'defaults' => '', 'simple' => '', 'zoom' => ''), $atts, 'amazon_product');
    return aps_render_product($atts);
}
add_shortcode('amazon_product', 'aps_shortcode');


/* ---------- Self-update: feed check + WordPress native update flow ---------- */
function aps_plugin_file() { return plugin_basename(__FILE__); }
function aps_plugin_slug() { return dirname(plugin_basename(__FILE__)); }

function aps_check_for_updates() {
    $o = aps_get_options();
    if (empty($o['update_feed'])) { return new WP_Error('aps_nofeed', 'No update feed URL configured.'); }
    $res = wp_remote_get($o['update_feed'], array('timeout' => 15, 'headers' => array('Accept' => 'application/json')));
    if (is_wp_error($res)) { return $res; }
    if (200 !== (int) wp_remote_retrieve_response_code($res)) { return new WP_Error('aps_feed_http', 'Update feed did not return 200.'); }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) { return new WP_Error('aps_feed_json', 'Update feed did not return valid JSON.'); }
    $latest = '';
    if (!empty($data['latest'])) { $latest = sanitize_text_field($data['latest']); }
    elseif (!empty($data['version'])) { $latest = sanitize_text_field($data['version']); }
    if (!$latest) { return new WP_Error('aps_feed_version', 'Update feed has no version.'); }
    $info = array(
        'latest' => $latest,
        'download_url' => isset($data['download_url']) ? esc_url_raw($data['download_url']) : (isset($data['package']) ? esc_url_raw($data['package']) : ''),
        'checked_at' => current_time('mysql'),
        'update_available' => version_compare($latest, APS_VERSION, '>'),
        'requires' => isset($data['requires']) ? sanitize_text_field($data['requires']) : '',
        'tested' => isset($data['tested']) ? sanitize_text_field($data['tested']) : '',
    );
    update_option('aps_update_info', $info);
    // Force WordPress to re-evaluate plugin updates on its next check
    delete_site_transient('update_plugins');
    return $info;
}

function aps_handle_check_updates() {
    if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }
    check_admin_referer('aps_check_updates');
    $result = aps_check_for_updates();
    if (is_wp_error($result)) {
        $code = ($result->get_error_code() === 'aps_nofeed') ? 'nofeed' : 'error';
        wp_safe_redirect(add_query_arg('aps_update_check', $code, admin_url('options-general.php?page=amazon-product-showcase')));
    } else {
        wp_safe_redirect(add_query_arg('aps_update_check', !empty($result['update_available']) ? 'available' : 'uptodate', admin_url('options-general.php?page=amazon-product-showcase')));
    }
    exit;
}
add_action('admin_post_aps_check_updates', 'aps_handle_check_updates');

/* Inject a pending update into WordPress's own update list, so the native
   "Update now" button on the Plugins screen does the actual install. */
function aps_inject_update($transient) {
    if (empty($transient->checked)) { return $transient; }
    $info = get_option('aps_update_info', array());
    if (!empty($info['update_available']) && !empty($info['download_url'])) {
        $obj = new stdClass();
        $obj->id = 'amazon-product-showcase';
        $obj->slug = aps_plugin_slug();
        $obj->plugin = aps_plugin_file();
        $obj->new_version = $info['latest'];
        $obj->url = '';
        $obj->package = $info['download_url'];
        if (!empty($info['requires'])) { $obj->requires = $info['requires']; }
        if (!empty($info['tested'])) { $obj->tested = $info['tested']; }
        $transient->response[aps_plugin_file()] = $obj;
    }
    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'aps_inject_update');

/* Plugins-screen links: Settings + Check for updates */
function aps_action_links($links) {
    $settings = '<a href="' . esc_url(admin_url('options-general.php?page=amazon-product-showcase')) . '">' . esc_html__('Settings', 'amazon-product-showcase') . '</a>';
    $check = '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=aps_check_updates'), 'aps_check_updates')) . '">' . esc_html__('Check for updates', 'amazon-product-showcase') . '</a>';
    array_unshift($links, $settings, $check);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'aps_action_links');

/* ---------- Elementor widget ---------- */
function aps_register_elementor_widget($widgets_manager) {
    require_once __DIR__ . '/includes/class-aps-elementor-widget.php';
    $widgets_manager->register(new \APS_Elementor_Widget());
}
add_action('elementor/widgets/register', 'aps_register_elementor_widget');
