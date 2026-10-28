<?php
add_action('admin_menu', 'idemailwiz_settings_page');
function idemailwiz_settings_page()
{
    add_menu_page('ID Email Wiz Settings', 'Wiz Settings', 'manage_options', 'idemailwiz_settings', 'idemailwiz_settings_page_content');
}

function idemailwiz_settings_page_content()
{
    ?>
    <div class="wrap">
        <h2>ID Email Wiz Settings</h2>
        <p>Editing the folder settings will not necessarily break anything, but some strange behavior may be encountered,
            especially related to trashed templates.
            <br />If you change these, make sure to go to Settings > Permalinks and hit the save button (sometimes twice) to
            refresh the permalinks cache.
        </p>
        <p>Campaign analytics, data feeds, courses and locations moved to the Email Analyzer app
            (<a href="https://id-email-analyzer.vercel.app" target="_blank" rel="noreferrer">id-email-analyzer.vercel.app</a>).</p>

        <form method="post" action="options.php">
            <?php
            settings_fields('idemailwiz_settings_group');
            do_settings_sections('idemailwiz_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}


add_action('admin_init', 'idemailwiz_register_settings');
function idemailwiz_register_settings()
{
    register_setting('idemailwiz_settings_group', 'idemailwiz_settings', 'idemailwiz_settings_sanitize');
    add_settings_section('idemailwiz_main_section', 'Main Settings', null, 'idemailwiz_settings');

    add_settings_field('folder_base', 'Folders Root', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'folder_base', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('folder_trash', 'Trash Folder', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'folder_trash', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('iterable_api_key', 'Iterable API Key', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_main_section', array(
        'option_name' => 'iterable_api_key',
        'description' => 'Used to push templates to Iterable and to check which templates are on sent campaigns'
    ));
}

function idemailwiz_render_dropdown_taxonomy_field($args)
{
    $options = get_option('idemailwiz_settings', array());
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    $terms = get_terms(array('taxonomy' => $args['taxonomy'], 'hide_empty' => false));
    ?>
    <select name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]">
        <?php foreach ($terms as $term): ?>
            <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected, $term->term_id); ?>>
                <?php echo esc_html($term->name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

function idemailwiz_render_text_field($args)
{
    $options = get_option('idemailwiz_settings', array());
    $value = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    
    // Set default value for specific fields if empty
    if (empty($value) && isset($args['default'])) {
        $value = $args['default'];
    }
    
    ?>
    <input type="text" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]"
        value="<?php echo esc_attr($value); ?>" />
    <?php if (isset($args['description'])): ?> <!-- Check if description is set -->
        <p class="description">
            <?php echo esc_html($args['description']); ?>
        </p>
    <?php endif; ?>
<?php
}

function idemailwiz_settings_sanitize($input)
{
    // Sanitization code here
    return $input;
}
