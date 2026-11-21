<?php
if (!defined('ABSPATH')) { exit; }

class APS_Elementor_Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'amazon_product_showcase'; }
    public function get_title() { return __('Amazon Product', 'amazon-product-showcase'); }
    public function get_icon() { return 'eicon-product-images'; }
    public function get_categories() { return array('general'); }
    public function get_style_depends() { return array('aps-style'); }
    public function get_script_depends() { return array('aps-frontend'); }

    public function get_keywords() { return array('amazon', 'product', 'showcase', 'affiliate', 'book', 'kindle'); }

    protected function register_controls() {
        $this->start_controls_section('content_section', array('label' => __('Product', 'amazon-product-showcase')));
        $this->add_control('aps_url', array(
            'label' => __('Amazon product URL', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::URL,
            'default' => array('url' => ''), // No hardcoded product URL — set it in the widget or in Settings
        ));
        $this->add_control('aps_style', array(
            'label' => __('Style', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                '' => __('Use Settings default', 'amazon-product-showcase'),
                'clean' => __('1 — Clean Professional', 'amazon-product-showcase'),
                'dark' => __('2 — Dark Premium', 'amazon-product-showcase'),
                'warm' => __('3 — Warm Editorial', 'amazon-product-showcase'),
            ),
            'default' => '',
        ));
        $this->add_control('aps_layout', array(
            'label' => __('Layout', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => array('full-page' => __('Full page', 'amazon-product-showcase'), 'card' => __('Card only', 'amazon-product-showcase'), 'showcase' => __('Showcase page', 'amazon-product-showcase'), 'editorial' => __('Design A — Editorial Split', 'amazon-product-showcase'), 'landing' => __('Design B — Centered Landing', 'amazon-product-showcase'), 'sidebar' => __('Design C — Sidebar Commerce', 'amazon-product-showcase'), 'split' => __('Design D — Split Hero', 'amazon-product-showcase'), 'story' => __('Design E — Story Scroll', 'amazon-product-showcase'), 'bento' => __('Design F — Bento Grid', 'amazon-product-showcase')),
            'default' => 'full-page',
        ));
        $this->add_control('aps_button_text', array(
            'label' => __('Button text', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => __('Use Settings default', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_tag', array(
            'label' => __('Affiliate tag (override)', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => __('Use Settings default', 'amazon-product-showcase'),
            'description' => __('Per-widget Amazon Associates tag for this product\'s Buy link.', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_title', array(
            'label' => __('Title (override)', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => __('Use the pulled title', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_price', array(
            'label' => __('Price (override)', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => __('Use the pulled price', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_defaults', array(
            'label' => __('Placeholder defaults', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('On', 'amazon-product-showcase'),
            'label_off' => __('Off', 'amazon-product-showcase'),
            'return_value' => '1',
            'default' => '1',
            'description' => __('Fill blocks whose pulled data is missing with sample content so the layout renders complete.', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_zoom', array(
            'label' => __('Enlarge image on click', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('On', 'amazon-product-showcase'),
            'label_off' => __('Off', 'amazon-product-showcase'),
            'return_value' => '1',
            'default' => '1',
            'description' => __('Clicking the product image opens it enlarged in a lightbox.', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_simple', array(
            'label' => __('Simple sections', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('On', 'amazon-product-showcase'),
            'label_off' => __('Off', 'amazon-product-showcase'),
            'return_value' => '1',
            'default' => '',
            'description' => __('Hide the decorative extras (tiles, stat strip, banner, CTA band). Empty follows the Settings default.', 'amazon-product-showcase'),
        ));
        $this->add_control('aps_image', array(
            'label' => __('Cover image (optional)', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::MEDIA,
            'default' => array('url' => ''),
        ));
        $this->end_controls_section();

        /* Style tab — Elementor-native styling, mapped onto the plugin's
           CSS variables and button so it stays inside the design system. */
        $this->start_controls_section('style_section', array(
            'label' => __('Showcase', 'amazon-product-showcase'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_control('aps_accent', array(
            'label' => __('Accent colour', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'description' => __('Overrides the style\'s accent (prices, kickers, highlights). Leave empty to keep the chosen style\'s accent.', 'amazon-product-showcase'),
            'selectors' => array(
                '{{WRAPPER}} .aps' => '--aps-accent: {{VALUE}};',
            ),
        ));
        $this->add_control('aps_button_bg', array(
            'label' => __('Button background', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .aps__button' => 'background-color: {{VALUE}};',
            ),
        ));
        $this->add_control('aps_button_color', array(
            'label' => __('Button text colour', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .aps__button' => 'color: {{VALUE}};',
            ),
        ));
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'aps_title_typography',
                'label' => __('Title typography', 'amazon-product-showcase'),
                'selector' => '{{WRAPPER}} .aps__title',
            )
        );
        $this->end_controls_section();

        /* Style tab — Product image: enlarge or shrink the cover image. */
        $this->start_controls_section('image_style_section', array(
            'label' => __('Product image', 'amazon-product-showcase'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_control('aps_img_size', array(
            'label' => __('Image size', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 80, 'max' => 640, 'step' => 10)),
            'description' => __('Slide to enlarge or shrink the product image. Empty keeps the built-in size for the layout.', 'amazon-product-showcase'),
            'selectors' => array(
                '{{WRAPPER}} .aps__cover' => 'width: {{SIZE}}{{UNIT}};min-width: {{SIZE}}{{UNIT}};',
            ),
        ));
        $this->add_control('aps_img_align', array(
            'label' => __('Image alignment', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => array(
                'left' => array('title' => __('Left', 'amazon-product-showcase'), 'icon' => 'eicon-text-align-left'),
                'center' => array('title' => __('Center', 'amazon-product-showcase'), 'icon' => 'eicon-text-align-center'),
                'right' => array('title' => __('Right', 'amazon-product-showcase'), 'icon' => 'eicon-text-align-right'),
            ),
            'toggle' => true,
            'description' => __('Align the product image left, center or right. Empty keeps the layout position.', 'amazon-product-showcase'),
            'selectors' => array(
                '{{WRAPPER}} .aps__cover' => '{{VALUE}}',
            ),
            'selectors_dictionary' => array(
                'left' => 'float:left;margin:0 20px 10px 0;',
                'center' => 'float:none;display:block;margin:0 auto 10px;',
                'right' => 'float:right;margin:0 0 10px 20px;',
            ),
        ));
        $this->add_control('aps_img_radius', array(
            'label' => __('Image corner radius', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 40, 'step' => 1)),
            'selectors' => array(
                '{{WRAPPER}} .aps__cover img' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ));
        $this->end_controls_section();

        /* Style tab — Sections: simple styling for the page's other sections. */
        $this->start_controls_section('sections_style_section', array(
            'label' => __('Sections', 'amazon-product-showcase'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_control('aps_text_color', array(
            'label' => __('Text colour', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .aps' => 'color: {{VALUE}};',
            ),
        ));
        $this->add_control('aps_heading_color', array(
            'label' => __('Section heading colour', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .aps__section h3' => 'color: {{VALUE}};',
            ),
        ));
        $this->add_control('aps_price_color', array(
            'label' => __('Price colour', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .aps__price' => 'color: {{VALUE}};',
                '{{WRAPPER}} .aps__buycard-price' => 'color: {{VALUE}};',
                '{{WRAPPER}} .aps__ld-price' => 'color: {{VALUE}};',
                '{{WRAPPER}} .aps__sb-price' => 'color: {{VALUE}};',
                '{{WRAPPER}} .aps__sp-price' => 'color: {{VALUE}};',
            ),
        ));
        $this->add_control('aps_section_spacing', array(
            'label' => __('Section spacing', 'amazon-product-showcase'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 80, 'step' => 2)),
            'description' => __('Space above each section (details, about, reviews). Empty keeps the built-in spacing for the layout.', 'amazon-product-showcase'),
            'selectors' => array(
                '{{WRAPPER}} .aps__section' => 'margin-top: {{SIZE}}{{UNIT}};',
            ),
        ));
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $atts = array(
            'style' => isset($s['aps_style']) ? $s['aps_style'] : '',
            'layout' => isset($s['aps_layout']) ? $s['aps_layout'] : 'full-page',
            'url' => isset($s['aps_url']['url']) ? $s['aps_url']['url'] : '',
            'button_text' => isset($s['aps_button_text']) ? $s['aps_button_text'] : '',
            'image' => isset($s['aps_image']['url']) ? $s['aps_image']['url'] : '',
            'tag' => isset($s['aps_tag']) ? $s['aps_tag'] : '',
            'title' => isset($s['aps_title']) ? $s['aps_title'] : '',
            'price' => isset($s['aps_price']) ? $s['aps_price'] : '',
            'defaults' => isset($s['aps_defaults']) ? $s['aps_defaults'] : '1',
            'simple' => (isset($s['aps_simple']) && $s['aps_simple'] === '1') ? '1' : '0',
            'zoom' => (isset($s['aps_zoom']) && $s['aps_zoom'] === '1') ? '1' : '0',
        );
        echo aps_render_product($atts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside aps_render_product()
    }
}
