<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Wiz Settings → Export for Analyzer: downloads the builder's templates, folders, snippets and interactives, plus
 * the HTML this site renders for each template, as one JSON file for the Email Analyzer app's new builder
 * (its scripts/builder/unpack-export.ts reads it). Admins only. Each template is rendered in its own request
 * (the renderer's CSS file is require_once'd, so it only prints once per request).
 */
// Priority 20: after wiz-options.php adds the Wiz Settings menu (it's loaded after this file). A submenu added
// before its parent gets a broken link and a "not allowed" page.
add_action('admin_menu', function () {
    add_submenu_page('idemailwiz_settings', 'Export for Analyzer', 'Export for Analyzer', 'manage_options', 'idemailwiz_analyzer_export', 'idemailwiz_analyzer_export_page');
}, 20);

function idemailwiz_analyzer_export_check()
{
    if (!current_user_can('manage_options') || !check_ajax_referer('idemailwiz_analyzer_export', 'security', false)) {
        wp_send_json_error('Not allowed', 403);
    }
}

// Everything except the rendered HTML.
add_action('wp_ajax_idemailwiz_analyzer_export_data', function () {
    idemailwiz_analyzer_export_check();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_modified, p.post_author, w.template_data
         FROM {$wpdb->prefix}wiz_templates w JOIN {$wpdb->posts} p ON p.ID = w.post_id
         WHERE p.post_type = 'idemailwiz_template' ORDER BY p.ID",
        ARRAY_A
    );
    $templates = [];
    foreach ($rows as $row) {
        $templates[] = [
            'post_id' => (int) $row['ID'],
            'title' => $row['post_title'],
            'status' => $row['post_status'],
            'created' => $row['post_date'],
            'modified' => $row['post_modified'],
            'author' => (int) $row['post_author'],
            'folders' => wp_get_object_terms((int) $row['ID'], 'idemailwiz_folder', ['fields' => 'ids']),
            'type' => get_wiztemplate_type((int) $row['ID']),
            'data' => json_decode($row['template_data'], true),
        ];
    }

    $terms = get_terms(['taxonomy' => 'idemailwiz_folder', 'hide_empty' => false]);
    $posts = fn($type) => get_posts(['post_type' => $type, 'post_status' => 'any', 'numberposts' => -1]);

    wp_send_json_success([
        'exported_at' => current_time('c'),
        'site' => home_url(),
        'templates' => $templates,
        'folders' => array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => $t->parent], is_array($terms) ? $terms : []),
        'snippets' => array_map(fn($p) => [
            'id' => $p->ID,
            'title' => $p->post_title,
            'content' => get_post_meta($p->ID, 'snippet_content', true),
            'css' => get_post_meta($p->ID, 'snippet_css', true),
        ], $posts('wysiwyg_snippet')),
        'interactives' => array_map(fn($p) => [
            'id' => $p->ID,
            'title' => $p->post_title,
            'data' => get_post_meta($p->ID, '_recommendation_engine_data', true),
            'html' => get_post_meta($p->ID, '_recommendation_engine_html', true),
            'css' => get_post_meta($p->ID, '_recommendation_engine_css', true),
        ], $posts('wysiwyg_interactive')),
    ]);
});

// One template's rendered HTML (as the builder renders it for Iterable) + the image ratios it used.
add_action('wp_ajax_idemailwiz_analyzer_export_render', function () {
    idemailwiz_analyzer_export_check();
    $postId = (int) ($_POST['post_id'] ?? 0);
    $data = get_wiztemplate($postId);
    if (!$data || empty($data['rows'])) {
        wp_send_json_success(['html' => null, 'ratios' => []]);
    }

    $urls = [$data['template_options']['template_styles']['header-and-footer']['template_header_logo'] ?? ''];
    $rows = $data['rows'];
    array_walk_recursive($rows, function ($value, $key) use (&$urls) {
        if ($key === 'image_url') $urls[] = $value;
    });
    $ratios = [];
    foreach (array_unique($urls) as $url) {
        $ratios[$url] = get_image_aspect_ratio($url);
    }

    // Warnings would end up inside the HTML.
    $display = ini_get('display_errors');
    ini_set('display_errors', '0');
    ob_start();
    $html = render_template_from_structure(generate_template_structure($data, 'false'));
    ob_end_clean();
    ini_set('display_errors', $display);

    wp_send_json_success(['html' => $html, 'ratios' => $ratios]);
});

function idemailwiz_analyzer_export_page()
{
    $nonce = wp_create_nonce('idemailwiz_analyzer_export');
    ?>
    <div class="wrap">
        <h1>Export for the Email Analyzer</h1>
        <p>Downloads every builder template (with its folders), the snippets and interactives, and the HTML this site
            renders for each template, as one JSON file. Nothing on this site changes.</p>
        <p><button type="button" class="button button-primary" id="analyzer-export">Download export</button>
            <span id="analyzer-export-status" style="margin-left: 10px;"></span></p>
    </div>
    <script>
        (function () {
            const button = document.getElementById('analyzer-export');
            const status = document.getElementById('analyzer-export-status');
            const post = async (action, extra = {}) => {
                const body = new URLSearchParams({ action, security: <?php echo wp_json_encode($nonce); ?>, ...extra });
                const res = await fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' });
                const json = await res.json();
                if (!json.success) throw new Error(json.data || 'Request failed');
                return json.data;
            };
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    status.textContent = 'Reading templates…';
                    const exportData = await post('idemailwiz_analyzer_export_data');
                    exportData.golden = {};
                    exportData.image_ratios = {};
                    const list = exportData.templates;
                    for (let i = 0; i < list.length; i++) {
                        status.textContent = `Rendering ${i + 1} of ${list.length}…`;
                        try {
                            const r = await post('idemailwiz_analyzer_export_render', { post_id: list[i].post_id });
                            if (r.html !== null) exportData.golden[list[i].post_id] = r.html;
                            Object.assign(exportData.image_ratios, r.ratios || {});
                        } catch (err) {
                            exportData.golden[list[i].post_id] = null;
                            console.error('Render failed for', list[i].post_id, err);
                        }
                    }
                    const blob = new Blob([JSON.stringify(exportData)], { type: 'application/json' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'wizard-export-' + new Date().toISOString().slice(0, 10) + '.json';
                    a.click();
                    status.textContent = `Done: ${list.length} templates.`;
                } catch (err) {
                    status.textContent = 'Failed: ' + err.message;
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
    <?php
}
