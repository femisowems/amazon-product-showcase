# Amazon Product Showcase — v1.7.6

> **Under construction.** This plugin was built in November 2026 as interview preparation for an Amazon SDE interview loop. Development is paused — it will be picked up and completed later.


Showcase any Amazon product on any WordPress page, in your own styling — not Amazon's embed.
**The product URL is not hardcoded.** Set it once in Settings, or pass it per shortcode/widget.

## Design mockups
The nine layouts were designed from these mockups. Each is a single self-contained HTML file (images inlined) — the live links below open them in any browser:
- **One generic product — all 9 layouts:** [Live](https://femisowems.github.io/amazon-product-showcase/mockups/generic-product-all-9-layouts.html) · [File](mockups/generic-product-all-9-layouts.html)
- **Full-page layout mockups:** [Live](https://femisowems.github.io/amazon-product-showcase/mockups/full-page-layout-mockups.html) · [File](mockups/full-page-layout-mockups.html)

## Install
1. WordPress Admin → Plugins → Add New → Upload Plugin → choose `amazon-product-showcase.zip`
2. Activate
3. Settings → Amazon Product — paste the Amazon product URL and pick your default style. Details are pulled automatically (see below)

## The 3 styles (selectable)
Settings → Amazon Product → **Default style**:
- **1 — Clean Professional**
- **2 — Dark Premium**
- **3 — Warm Editorial**

Override per use:
- Shortcode: `[amazon_product url="https://www.amazon.ca/dp/XXXXXXXXXX/" style="dark"]` — style accepts `clean|dark|warm` or `1|2|3`
- Elementor: the **Amazon Product** widget has URL, Style and Layout controls

## Usage
- Full page: `[amazon_product]` (uses the Settings URL)
- Card only: `[amazon_product layout="card"]`
- Any theme: all CSS is scoped under `.aps`, enqueued only when used, no framework/jQuery dependency.
- The ASIN is parsed automatically from the product URL at runtime.

## Settings
Product URL, display fields, default style, affiliate tag (optional), button text, default layout, optional PA-API keys.

## Updates
Settings → Amazon Product has a **Check for updates** button (also on the Plugins screen, under the plugin name).

1. Set the **Update feed URL** once in Settings — a JSON file you host (e.g. a GitHub gist / release asset) like:
   `{"latest": "1.0.3", "version": "1.0.3", "download_url": "https://.../amazon-product-showcase.zip"}`
2. After that, click **Check for updates** — it reads the feed, compares versions, and if a newer one exists, WordPress shows its native **Update now** button on the Plugins screen, which does the install. No manual zip upload needed.
3. If no feed URL is set, the button tells you so instead of failing silently.

## Auto-fetching product details
You do **not** need to type the title/price in by hand.
- Paste the product URL (Settings, shortcode `url="..."`, or the Elementor widget). The ASIN is parsed from it.
- **With PA-API credentials** (Access Key + Secret Key + affiliate/partner tag in Settings): the plugin pulls title, large image, price, brand and features via Amazon's official Product Advertising API 5.0 GetItems (SigV4-signed, marketplace-aware: amazon.ca → webservices.amazon.ca / us-east-1), cached 24 hours.
- **Without credentials:** it makes a best-effort read of the product page (title, og:image, price). Amazon often blocks server-side reads — if that happens, the plugin records it in Settings and falls back to the manual fields.
- **Refresh product details now** button in Settings clears the cache and re-pulls immediately.

## v1.0.4 fixes
- Fixed "Undefined variable $o" / "Trying to access array offset on value of type null" warnings on the Settings page (the variable is now defined before the Updates panel uses it).
- Amazon short links are now supported: share links like `https://a.co/d/xxxxxxx` are resolved through their redirects to extract the ASIN, so auto-fetch works with them too.

## v1.0.5
- **Manual product input removed** (at the owner's request): there are no editable title/price/etc. fields in Settings anymore. Product details come only from the auto-pull off the Amazon URL; sections with no pulled data are hidden rather than shown blank.
- Settings now contains only: style, product URL, affiliate tag, button text, layout, update feed, and the PA-API Access/Secret keys.

## v1.0.6
- No more dead download link: while the update feed carries no `download_url` (i.e. until the GitHub release asset exists), Settings no longer shows a Download button or tells you to click "Update now" — it says an update is available and to install it from the manual zip. Detection via Check for updates is unchanged. When a real package URL is added to the feed later, the Download button and native WordPress update return automatically.

## v1.0.7
- **Product display fix** (from a live test where the card rendered empty): the page parser was written against guessed markup and missed most fields on real Kindle pages. Rebuilt against a captured real product page: cover from the `hiRes` image JSON (Kindle pages have no og:image), price from the whole/fraction spans or aria-label price, reviews from the `(N)` / aria-label forms, author + editor from the byline spans, and Print length / Language / File size / Publication date from the book-detail tiles.
- Cover fallback: if a pull returns no image (or fails entirely), the cover is derived from the ASIN via Amazon's image CDN, so the product always shows its cover; a cover-only result is cached for 1 hour instead of 24 so a full pull retries sooner. Cache key bumped to v2 so stale partial caches from older versions are ignored.
- Render hardening: byline, description, price and detail rows now print only when they have data — no more "by (Author), (Editor)", bare "$", or empty detail rows. Removed the hardcoded Kindle feature rows (Screen Reader / Page Flip etc.) from Product details since they were not pulled per-product.

## v1.0.8
- **Fully product-agnostic** (owner's direction: the URL decides what is showcased — nothing about any specific product is baked in; grep for the book's ASIN/title/author returns zero). Removed the last product-flavoured defaults: no assumed 'Kindle Edition' format (the kicker shows only a format actually pulled), the 'Sold by Amazon.ca' line is now derived from the URL's marketplace ('Available on Amazon.ca' / '.com' / etc.), and the 'Send a free sample' link appears only when ebook data (file size / print length) was pulled.
- Parser generalised beyond books, verified against a non-book page (AirPods Pro 2 on Amazon.ca): brand from the 'Visit the X Store' byline, description from the feature bullets (meta-description fallback, Amazon suffix stripped), extra price pattern. Note: Amazon withholds the price from server-side HTML on some non-book products (it loads via JS) — on those, everything but price pulls; PA-API returns the price when keys are set.

## v1.0.9
- **Share links from the Amazon page/app work reliably**: a.co answers HEAD requests with a 404 (verified against a real share link), which defeated the old HEAD-first resolver. Resolution is now GET-first: follow the share link, then take the product URL from the final page's canonical link (og:url second, any /dp/ link last) — the canonical link names the exact product, never a recommendation. HEAD redirect-following remains as a backup for other shorteners (amzn.to, amzn.eu). ASIN parsing also broadened for app share URLs (`/gp/aw/d/`) and legacy `/exec/obidos/ASIN/` links. Verified end-to-end by simulation with a real a.co share link.

## Tests
`tests/run-parser-tests.php` runs the page parser, share-link resolver, ASIN extraction and star renderer against saved real Amazon pages (`tests/fixtures/` — a Kindle book, a second book reached via an a.co share link, and a non-book product). No WordPress install needed, just PHP CLI: `php tests/run-parser-tests.php`. Run it after any parser change; when Amazon's markup shifts, fix the parser first, then refresh fixtures — never edit an expectation to match a broken parse. One documented expectation: the AirPods fixture yields NO price, because Amazon ships no price in that page's server HTML (PA-API is the source for it).

## v1.1.0
- **Rating fix**: stars are no longer a fixed glyph string. A base row of five stars with a clipped coloured overlay renders any rating accurately (4.2 = 84% fill), with a screen-reader label ("Rated 4.2 out of 5 (13 ratings)").
- **Read more / Read less** for long descriptions (>320 chars): preview with an accessible toggle button (`aria-expanded`), vanilla JS, no dependencies.
- **Fetch log** (observability): every pull is recorded — time, ASIN, source (PA-API / page read), success or cover-only fallback, fields pulled, cached-until — and shown as a table in Settings.
- **Accessibility pass**: the card is a semantic `<article>` with an aria-label, the cover is a `<figure>` with lazy-loaded image and real alt text, the Buy button gets a descriptive aria-label, and all interactive elements have visible `:focus-visible` outlines in every style.
- **Parser refactored** into a pure function (`aps_parse_product_html`) so the fixture tests above can exercise it outside WordPress.

## v1.1.1
- **Real book descriptions**: for books, the description now comes from Amazon's bookDescription block (the actual blurb — e.g. "After 40+ years focussing on 100% Permanent Hair Removal…") instead of the meta description, which merely echoes "Title eBook : Author names". The longer real text also exercises the Read more toggle. Fixture + test expectation updated.

## v1.2.0
- **Showcase layout** (`layout="showcase"` in the shortcode, the Elementor Layout control, or as the Settings default): a full product page designed to rival a hand-built book page like OMI's — oversized hero (250px cover, 36px title), a three-tile highlights row (rating / product facts / price, with icons), the standard details and author sections, testimonial-style review cards, and a closing call-to-action band with price + Buy button. Works with all three styles.
- **Individual review extraction**: the page parser now pulls up to 3 full reviews (reviewer, stars, title, date, body) from the product page, rendered as review cards in the full-page and showcase layouts. PA-API does not provide review text, so cards appear only when the page read supplies them.

## v1.3.0
- **Three full-page designs**, selectable like the other layouts (`layout="editorial" | "landing" | "sidebar"` in the shortcode, the Elementor Layout control, or the Settings default) and combinable with all three colour styles. All product-agnostic — every block renders only from pulled data and hides when empty:
  - **Design A — Editorial Split**: serif headline block, story column with drop-cap blurb, pull-quote from the top review, stat strip, cover floated right with a deep shadow.
  - **Design B — Centered Landing**: centred hero with oversized price, highlights tiles, review cards, and a buy bar fixed to the bottom of the viewport.
  - **Design C — Sidebar Commerce**: sticky sidebar (cover, price, Buy, specs) with the content flowing on the right.
- Shared building blocks (buy anchor, description with Read more, details/reviews sections, highlight tiles, CTA band) factored into helpers used by all designs.

## v1.4.0
- **Three more full-page designs** (from the full-page mockup page Femi approved), selectable the same way (`layout="split" | "story" | "bento"`), product-agnostic, data-driven:
  - **Design D — Split Hero**: two-tone hero (accent panel with title/rating/price/Buy + full-height product image), trust strip, highlight tiles, description, details, reviews, CTA band.
  - **Design E — Story Scroll**: centred serif hero; the description is split into numbered chapters (01–03); the top review becomes a mid-page testimonial banner; closing buy card.
  - **Design F — Bento Grid**: image/title/price/rating/specs/top-review tiles in one grid, then description, details and reviews below, with a sticky buy bar.
- Panel colours come from per-style variables (--aps-panel / --aps-on-panel) alongside --aps-accent.

## v1.5.0
- **Placeholder defaults for every block** (Settings → "Placeholder defaults", on by default): any field the auto-pull did not return is filled with generic sample content, so all nine layouts render as complete pages — e.g. review cards now appear even on pulls where Amazon served no review blocks. Real pulled data always wins; placeholders only fill gaps. Turn it off in Settings for a live site, or per instance with `defaults="0"` in the shortcode / the Elementor "Placeholder defaults" switcher.

## v1.5.1
- Placeholder-defaults pairing fixes (from a live inspection): the sample Brand no longer fills in (it leaked onto real products — a book showed "Brand: Aurora"), and the placeholder author bio is dropped whenever the author is real, so a real author is never paired with the sample bio (the About section simply hides instead).
- Bento grid: the cover now displays in full (contain, no cropping) and a second review tile fills the grid row when a second review exists.
- Sticky buy bar: title truncates with an ellipsis instead of squeezing the price and button onto a second line.

## v1.5.2
- CSS specificity fix: the base figure reset (`.aps figure.aps__cover{margin:0}`) was outranking the centred-cover margins, so the Story Scroll cover rendered stuck to the left instead of centred under the title (spotted in a live screenshot). Story, Landing and Sidebar covers now centre correctly.
- Story buy card: title, price and button are block-level and always stack, instead of the price running into the title line.

## v1.6.0
- **Self-contained design system**: the plugin no longer inherits the theme's typography. It ships its own font stacks (system fonts only — no external webfont requests): a display serif (Iowan Old Style / Palatino / Georgia) for editorial-style titles and section headings, heavy sans for the modern layouts (Split, Landing, Bento, Sidebar), clean sans body text with antialiasing throughout.
- Polish pass in the direction of the design mockups: pill buttons with soft shadows and a hover lift (Warm style's square button unified to pill), softer layered shadows on review cards, highlight tiles, bento tiles and the sidebar card, looser line-height on descriptions and detail lists, tighter headline letter-spacing, 18px container radius.

## v1.6.1
- Elementor widget brought to full parity with the shortcode: new per-widget overrides for affiliate tag, title and price (previously hardcoded empty), so a widget instance can track a different tag or correct a pulled title/price.
- New Style tab on the widget: accent colour (mapped to the design system's --aps-accent variable), button background and text colours, and a title typography group control — all Elementor-native, all optional, empty keeps the chosen style's design.

## v1.7.0
- **Enlarge the product image**: every cover/product image is now a click-to-enlarge button (hover/focus shows an "Enlarge" chip) opening a full-screen lightbox with the large image. Esc, the backdrop, or the close button dismisses it; keyboard focus is managed and returned. Works in all nine layouts; the text cover-art fallback is unchanged.
- **Simple sections mode**: a new Settings toggle (also `simple="1"` in the shortcode and a switcher in the Elementor widget) strips the decorative extras — highlight tiles, stat strip, testimonial banner and the closing CTA band — leaving hero, description, details and reviews for a cleaner, simpler page. Buy buttons and buy bars stay.

## v1.7.1
- The v1.7.0 features are now controllable per instance, including through the Elementor widget: a new "Enlarge image on click" switcher (on by default) turns the lightbox off per widget/shortcode (`zoom="0"`), rendering a plain image instead. The widget's Simple sections switcher now also applies explicitly per widget rather than deferring to Settings.

## v1.7.2
- Elementor Style tab expanded, per the owner's direction: a **Product image** section with an image-size slider (enlarge or shrink the product image per widget, 80–640px) and a corner-radius slider; and a **Sections** section with simple styling for the other sections — text colour, section heading colour, price colour, and section spacing. All optional; empty keeps the layout's built-in design.

## v1.7.3
- Hotfix: two Style-tab control descriptions in the Elementor widget carried an unescaped apostrophe ("layout's") that caused a PHP parse error (critical error on sites with Elementor active). Reworded; no behaviour change.

## v1.7.4
- Fix: the showcase rendered unstyled inside the Elementor editor preview (the selected style — e.g. Dark Premium — did not show; text ran together, cover unpositioned). Cause: the CSS/JS were only enqueued at render time, which never reaches the Elementor editor canvas. The assets are now registered up front and the widget declares them via get_style_depends() / get_script_depends(), so Elementor loads them in the editor preview and on the front end.

## v1.7.5
- Elementor Style tab → Product image: new Image alignment control (left / center / right) for the product image, alongside the size and corner-radius sliders. Empty keeps the layout's built-in position.

## v1.7.6
- Small-screen pass for all nine layouts (480px and under): heroes stack with the cover centred, content columns lose their desktop min-widths so nothing overflows, a max-width guard caps the product image (including sizes set with the Elementor slider), Bento goes single-column, review cards stack, the CTA band stacks, the sticky buy bar compacts, and titles/padding tighten per layout.
