<?php
/**
 * Template Name: Oversee Dashboard (full-bleed)
 *
 * Full-bleed WordPress page template for the Oversee Customer Dashboard SPA.
 *
 * Renders ONLY wp_head(), the SPA mount, and wp_footer(). No theme header,
 * footer, sidebar, or navigation chrome — the SPA owns the entire viewport
 * and supplies its own Oversee chrome. The Hub child theme's wrappers do
 * not apply here, which is required: the dashboard is an application, not
 * a marketing page.
 *
 * Selection happens automatically: OCD_Page_Template auto-assigns this
 * template to the page with slug "dashboard" on plugin activation and on
 * init (idempotent). Editors can also pick "Oversee Dashboard (full-bleed)"
 * from Page Attributes → Template manually.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Resolve the page content first so any shortcodes inside (e.g.
// [oversee_customer_dashboard]) enqueue the SPA bundle BEFORE wp_head runs.
// Without this the script tag and inline OCD_CONFIG would land after the
// initial paint, causing the SPA to flash the role picker.
$ocd_content = '';
if (have_posts()) {
    while (have_posts()) {
        the_post();
        $ocd_content = apply_filters('the_content', get_the_content());
    }
    rewind_posts();
}

// If the page body has no shortcode, fall back to rendering the customer
// dashboard shortcode directly so /dashboard/ is never blank.
if (trim(wp_strip_all_tags($ocd_content)) === '') {
    $ocd_content = do_shortcode('[oversee_customer_dashboard]');
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="robots" content="noindex,nofollow" />
<title><?php
    $title = function_exists('wp_get_document_title') ? wp_get_document_title() : get_bloginfo('name');
    echo esc_html($title);
?></title>
<style id="oversee-dashboard-fullbleed-reset">
    html, body { margin: 0; padding: 0; background: #fff; min-height: 100vh; }
    body { -webkit-font-smoothing: antialiased; }
    /* Defeat any leftover theme rules that constrain the SPA viewport. */
    body.oversee-dashboard-fullbleed,
    body.oversee-dashboard-fullbleed > * {
        max-width: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .oversee-dashboard-mount,
    #oversee-dashboard-root {
        display: block;
        width: 100%;
        min-height: 100vh;
    }
</style>
<?php wp_head(); ?>
</head>
<body <?php body_class('oversee-dashboard-fullbleed'); ?>>
<?php
echo $ocd_content; // already filtered by the_content / do_shortcode
wp_footer();
?>
</body>
</html>
