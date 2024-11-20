// Prevent indexing of the page with the incident report form
function hlir_noindex_shortcode_page() {
    if (is_page() && has_shortcode(get_post()->post_content, 'incident_report_form')) {
        echo '<meta name="robots" content="noindex, nofollow">';
    }
}
add_action('wp_head', 'hlir_noindex_shortcode_page');
