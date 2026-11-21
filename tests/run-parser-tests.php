<?php
/**
 * Amazon Product Showcase — parser fixture tests.
 *
 * Runs the plugin's pure functions (page parser, share-link resolver, ASIN
 * extraction, star renderer) against saved real Amazon page captures in
 * tests/fixtures/ — no WordPress install needed.
 *
 * Usage (any machine with PHP CLI, e.g. the Local site's PHP):
 *   php tests/run-parser-tests.php
 * Exit code 0 = all checks passed, 1 = at least one failure.
 *
 * Fixtures are trimmed windows of genuine Amazon.ca markup captured
 * 2026-11-21 (book-hair: ASIN B09LF9HNZT; book-shadow-mate: B08MTDBPKP,
 * reached via an a.co share link; airpods: B0BDHWDR12). When Amazon changes
 * its markup and a check fails here, update the parser first, then refresh
 * the fixture — never edit an expectation to match a broken parse.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

/* ---------- Minimal WordPress stubs (only what the tested functions call) ---------- */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_basename($f) { return 'amazon-product-showcase/' . basename($f); }
function plugin_dir_url($f) { return 'http://example.test/wp-content/plugins/amazon-product-showcase/'; }
function sanitize_text_field($s) { $s = strip_tags((string) $s); $s = preg_replace('/[\r\n\t ]+/', ' ', $s); return trim($s); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function esc_url_raw($u) { return trim((string) $u); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s) { return $s; }
function __($s) { return $s; }
function get_transient() { return false; }
function set_transient() { return true; }
function delete_transient() { return true; }
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
function current_time($t) { return date('Y-m-d H:i:s'); }
function date_i18n($f, $ts) { return date($f, $ts); }
function is_wp_error($t) { return false; }
function wp_remote_get() { return array('body' => isset($GLOBALS['APS_TEST_BODY']) ? $GLOBALS['APS_TEST_BODY'] : ''); }
function wp_remote_head() { return array(); }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_retrieve_response_code() { return 200; }
function wp_remote_retrieve_header() { return ''; }

require dirname(__DIR__) . '/amazon-product-showcase.php';

/* ---------- Tiny assertion harness ---------- */
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label\n"; }
}
function has($arr, $key, $needle) { return isset($arr[$key]) && strpos((string) $arr[$key], $needle) !== false; }
function eq($arr, $key, $val) { return isset($arr[$key]) && (string) $arr[$key] === (string) $val; }
function fixture($name) { return file_get_contents(__DIR__ . '/fixtures/' . $name); }

/* ---------- Fixture 1: Kindle book (The Life & Death of a Hair) ---------- */
echo "book-hair.html\n";
$p = aps_parse_product_html(fixture('book-hair.html'));
check('parses at all', is_array($p));
check('title', has($p, 'title', 'Death of a Hair'));
check('price 95.00', eq($p, 'price', '95.00'));
check('rating 3.5', eq($p, 'rating', '3.5'));
check('reviews 3', eq($p, 'reviews', '3'));
check('author', eq($p, 'author', 'Nicolette Oakwell-Morgan'));
check('editor', eq($p, 'editor', 'Dennis Furlan'));
check('pages', eq($p, 'pages', '267 pages'));
check('language', eq($p, 'language', 'English'));
check('file size', eq($p, 'filesize', '12.4 MB'));
check('cover from hiRes JSON', has($p, 'image', '61OjGy37qWL'));
check('description is the real book blurb, not the meta echo', has($p, 'description', 'After 40+ years focussing on 100% Permanent Hair Removal'));
check('individual reviews extracted', isset($p['reviews_list']) && count($p['reviews_list']) >= 2);
check('review 1 reviewer', isset($p['reviews_list'][0]['name']) && $p['reviews_list'][0]['name'] === 'GL');
check('review 1 title', isset($p['reviews_list'][0]['title']) && strpos($p['reviews_list'][0]['title'], 'Permanent Hair Removal is Achievable') !== false);
check('review 1 body', isset($p['reviews_list'][0]['body']) && strpos($p['reviews_list'][0]['body'], 'big blessing') !== false);

/* ---------- Fixture 2: Kindle book reached via an a.co share link ---------- */
echo "book-shadow-mate.html\n";
$p = aps_parse_product_html(fixture('book-shadow-mate.html'));
check('title', eq($p, 'title', 'Shadow Mate (Wolf Moon Academy Book 1)'));
check('price 4.99', eq($p, 'price', '4.99'));
check('author', eq($p, 'author', 'Jen L. Grey'));
check('pages', eq($p, 'pages', '230 pages'));
check('cover', has($p, 'image', '913C-Qo2X9L'));

/* ---------- Fixture 3: non-book product (AirPods Pro) ---------- */
echo "airpods.html\n";
$p = aps_parse_product_html(fixture('airpods.html'));
check('title', has($p, 'title', 'Apple AirPods Pro'));
check('rating 4.6', eq($p, 'rating', '4.6'));
check('reviews 56567', eq($p, 'reviews', '56567'));
check('brand', eq($p, 'brand', 'Apple'));
check('description from feature bullets', has($p, 'description', 'Active Noise Cancellation'));
check('cover', has($p, 'image', '61SUj2aKoEL'));
// Known Amazon behaviour: this page ships no price in server HTML (JS-rendered).
// PA-API is the source for it. If this check starts failing because a price
// appears, update the fixture note — do not delete the check silently.
check('price absent in page HTML (documented)', !isset($p['price']) || $p['price'] === '');

/* ---------- Share-link resolver: canonical URL wins ---------- */
echo "resolver + asin\n";
$GLOBALS['APS_TEST_BODY'] = fixture('book-shadow-mate.html');
$resolved = aps_resolve_short_url('https://a.co/d/00NnbbFg');
check('a.co share link resolves to the product page', strpos($resolved, '/dp/B08MTDBPKP') !== false);
check('asin from /dp/', aps_asin_from_url('https://www.amazon.ca/dp/B09LF9HNZT/') === 'B09LF9HNZT');
check('asin from /gp/aw/d/ (app share)', aps_asin_from_url('https://www.amazon.ca/gp/aw/d/B08MTDBPKP?psc=1') === 'B08MTDBPKP');
check('asin from ?asin=', aps_asin_from_url('https://www.amazon.ca/x?asin=B0BDHWDR12') === 'B0BDHWDR12');
check('no asin in a bare short link', aps_asin_from_url('https://a.co/d/00NnbbFg') === '');

/* ---------- Star renderer (the 4.2 fix) ---------- */
echo "stars\n";
$h = aps_stars_html('4.2', '13');
check('4.2 renders an 84% fill', strpos($h, 'width:84%') !== false);
check('screen-reader label', strpos($h, 'Rated 4.2 out of 5 (13 ratings)') !== false);
check('no exotic half-star glyph', strpos($h, '⯪') === false);
check('3.5 renders a 70% fill', strpos(aps_stars_html('3.5'), 'width:70%') !== false);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
