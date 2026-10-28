<?php
function wrap_with_placeholder($name, $content, $dataParams = [])
{
    if ($dataParams) {
        $dataParamsString = ' ' . implode(' ', array_map(function ($key, $value) {
            return 'data-' . $key . '="' . $value . '"';
        }, array_keys($dataParams), array_values($dataParams)));
    } else {
        $dataParamsString = '';
    }
    $wrappedHtml = '<wizPlaceholder ' . $dataParamsString . ' data-preview-part="' . $name . '_start"></wizPlaceholder>' . $content . '<wizPlaceholder data-preview-part="' . $name . '_end"></wizPlaceholder>';

    return $wrappedHtml;
}
function generate_template_structure($templateData, $isEditor = false)
{
    // Turn $isEditor from 'true' to true and 'false' to false
    $isEditor = $isEditor === 'true';
    
    $rows = $templateData['rows'] ?? [];
    $templateOptions = $templateData['template_options'] ?? [];
    $templateSettings = $templateOptions['message_settings'] ?? [];
    $templateStyles = $templateOptions['template_styles'] ?? [];

    $structure = ['top' => [], 'rows' => [], 'bottom' => []];

    $structure['top']['doc_start'] = '<!DOCTYPE html>
    <html lang="en" dir="ltr" xmlns="https://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" title="iD Tech Camps">';
    
    // $structure['top']['head'] = $isEditor ? wrap_with_placeholder('email_head', idwiz_get_email_head($templateSettings, $templateStyles, $rows)) : idwiz_get_email_head($templateSettings, $templateStyles, $rows);
    // $structure['top']['body_start'] = $isEditor ? wrap_with_placeholder('body_start', idwiz_get_email_body_top($templateSettings, $templateStyles)) : idwiz_get_email_body_top($templateSettings, $templateStyles);
    
    $structure['top']['head'] = idwiz_get_email_head($templateSettings, $templateStyles, $rows);
    $structure['top']['body_start'] = idwiz_get_email_body_top($templateSettings, $templateStyles);

    $structure['top']['standard_header'] = $isEditor ? wrap_with_placeholder('standard_header', idwiz_get_standard_header($templateOptions, $isEditor)) : idwiz_get_standard_header($templateOptions, $isEditor);

    $structure['rows'] = [];

    foreach ($templateData['rows'] as $rowIndex => $row) {
        $rowStart = generate_row_start($rowIndex, $templateData, $isEditor);
        $rowEnd = generate_row_end($rowIndex, $isEditor);

       

        $structure['rows'][$rowIndex] = [
            'start' => $rowStart,
            'columnSets' => [],
            'end' => $rowEnd
        ];

        foreach ($row['columnSets'] as $colSetIndex => $columnSet) {
            $colSetStart = generate_columnset_start($rowIndex, $colSetIndex, $templateData, $isEditor);
            $colSetEnd = generate_columnset_end($rowIndex, $colSetIndex, $isEditor);

            

            $structure['rows'][$rowIndex]['columnSets'][$colSetIndex] = [
                'start' => $colSetStart,
                'columns' => [],
                'end' => $colSetEnd
            ];

            $columns = $columnSet['columns'] ?? [];

            foreach ($columns as $columnIndex => $column) {
                if ($column['activation'] !== 'active') {
                    unset($columns[$columnIndex]);
                    continue;
                }

                $colStart = generate_column_start($rowIndex, $colSetIndex, $columnIndex, $templateData, $isEditor);
                $colEnd = generate_column_end($rowIndex, $colSetIndex, $columnIndex, $isEditor);

               

                $structure['rows'][$rowIndex]['columnSets'][$colSetIndex]['columns'][$columnIndex] = [
                    'start' => $colStart,
                    'chunks' => [],
                    'end' => $colEnd
                ];

                foreach ($column['chunks'] as $chunkIndex => $chunk) {
                    $chunkContent = '';
                    if ($isEditor) {
                        $chunkContent .= '<wizPlaceholder data-preview-part="chunk_start" data-row-index="' . $rowIndex . '" data-columnset-index="' . $colSetIndex . '" data-column-index="' . $columnIndex . '" data-chunk-index="' . $chunkIndex . '"></wizPlaceholder>';
                    }
                    $chunkContent .= idwiz_get_chunk_template(false, $rowIndex, $colSetIndex, $columnIndex, $chunkIndex, $templateData, $isEditor);
                    if ($isEditor) {
                        $chunkContent .= '<wizPlaceholder data-preview-part="chunk_end" data-row-index="' . $rowIndex . '" data-columnset-index="' . $colSetIndex . '" data-column-index="' . $columnIndex . '" data-chunk-index="' . $chunkIndex . '"></wizPlaceholder>';
                    }

                   

                    $structure['rows'][$rowIndex]['columnSets'][$colSetIndex]['columns'][$columnIndex]['chunks'][$chunkIndex] = [
                        'chunk' => $chunkContent
                    ];
                }
            }

            // If Magic Wrap is on, reverse the order of columns but keep the keys intact
            $magicWrap = $columnSet['magic_wrap'] ?? 'off';
            if ($magicWrap == 'on') {
                $structure['rows'][$rowIndex]['columnSets'][$colSetIndex]['columns'] = array_reverse($structure['rows'][$rowIndex]['columnSets'][$colSetIndex]['columns'], true);
            }
        }
    }

    $structure['bottom']['standard_footer'] = $isEditor ? wrap_with_placeholder('standard_footer', idwiz_get_standard_footer($templateStyles, $isEditor)) : idwiz_get_standard_footer($templateStyles, $isEditor);

    $structure['bottom']['fine_print'] = $isEditor ? wrap_with_placeholder('fine_print', idwiz_get_fine_print_disclaimer($templateOptions)) : idwiz_get_fine_print_disclaimer($templateOptions);

    $structure['bottom']['body_end'] = idwiz_get_email_body_bottom();
    $structure['bottom']['closetags'] = idwiz_get_email_bottom();

    return $structure;
}

function render_template_from_structure($structure)
{
    $html = '';

    // Render top section
    foreach ($structure['top'] as $topElement) {
        $html .= $topElement;
    }

    // Render rows
    foreach ($structure['rows'] as $row) {
        $html .= $row['start'];
        foreach ($row['columnSets'] as $columnSet) {
            $html .= $columnSet['start'];
            foreach ($columnSet['columns'] as $column) {
                $html .= $column['start'];
                foreach ($column['chunks'] as $chunk) {
                    $html .= $chunk['chunk'];
                }
                $html .= $column['end'];
            }
            $html .= $columnSet['end'];
        }
        $html .= $row['end'];
    }

    // Render bottom section
    foreach ($structure['bottom'] as $bottomElement) {
        $html .= $bottomElement;
    }

    return $html;
}



function generate_row_start($rowIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        return;
    }

    $row = $templateData['rows'][$rowIndex] ?? null;
    if (!$row) {
        return;
    }

    $return = '';
    if ($isEditor) {
        $return .= '<wizPlaceholder data-preview-part="row_start" data-row-index="' . $rowIndex . '"></wizPlaceholder>';
    }
    

    $rowBackgroundCss = generate_background_css($row['background_settings']);
    $rowBackgroundCssMso = generate_background_css($row['background_settings'], '', true);

    $rowClasses = $row['row_classes'] ?? '';

    // If this is showing in the editor, add a data attribute to the row
    $rowDataAttr = $isEditor ? 'data-row-index=' . $rowIndex : '';

    $return .= "<div class='row $rowClasses' $rowDataAttr style='font-size:0; width: 100%; margin: 0; padding: 0; " . $rowBackgroundCss . "'>";
    $return .= "<!--[if mso]><table class='row $rowClasses' role='presentation' width='100%' style='$rowBackgroundCssMso white-space:nowrap;width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align:center; '><tr><td><![endif]-->";

   
    return $return;
}
function generate_row_end($rowIndex, $isEditor = false)
{
    $return = "<!--[if mso]></td></tr></table><![endif]-->";
    $return .= "</div>"; // Close the row layout div 
    
    if ($isEditor) {
        $return .= '<wizPlaceholder data-preview-part="row_end" data-row-index="' . $rowIndex . '"></wizPlaceholder>';
    }
    return $return;
}



function generate_columnset_start($rowIndex, $columnSetIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        error_log('No template data provided for columnSet ' . $columnSetIndex);
        return;
    }
    $columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
    if (!$columnSet) {
        error_log('No columnSet found');
        return '';
    }

    $magicRtl = '';
    $magicWrap = $columnSet['magic_wrap'] ?? 'off';
    if ($magicWrap == 'on') {
        $magicRtl = 'dir="rtl"';
    }

    $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
    $mobileWrapClass = '';
    if ($mobileWrap !== 'on') {
        $mobileWrapClass = 'noWrap';
    }

    $columnSetClasses = $columnSet['columnset_classes'] ?? '';

    $columns = $columnSet['columns'] ?? []; // includes inactive columns

    foreach ($columns as $columnIndex => $column) {
        if ($column['activation'] !== 'active') {
            unset($columns[$columnIndex]);
        }
    }

    
    $colSetBGSettings = $columnSet['background_settings'] ?? [];
    $colSetBackgroundCss = generate_background_css($colSetBGSettings, '', false);
    $colSetBackgroundCssMso = generate_background_css($colSetBGSettings, '', true);

    $layoutClass = $columnSet['layout'] ?? '';

    $colSetDataAttr = $isEditor ? 'data-columnset-index=' . $columnSetIndex : '';

    // $numActiveColumns = count($columns);
    // $displayTable = $numActiveColumns > 1 ? 'display: table;' : '';

    $return = '';

    if ($isEditor) {
        $return = '<wizPlaceholder data-preview-part="columnset_start" data-row-index="' . $rowIndex . '" data-columnset-index="' . $columnSetIndex . '"></wizPlaceholder>';
        
    }
    $return .= "<div class='columnSet $columnSetClasses $layoutClass $mobileWrapClass' $colSetDataAttr $magicRtl style='$colSetBackgroundCss text-align: center; font-size: 0; width: 100%;'>";
    $return .= "<!--[if mso]><table role='presentation' class='columnset $columnSetClasses' width='100%' style='$colSetBackgroundCssMso margin:0; padding:0;'><tr><![endif]-->";

    return $return;
}

function generate_columnset_end($rowIndex, $colSetIndex, $isEditor = false)
{
    $return = '';

    $return .= "<!--[if mso]></tr></table><![endif]-->";
    $return .= "</div>"; // Close the colset layout div

    if ($isEditor) {
        $return .= '<wizPlaceholder data-preview-part="columnset_end" data-row-index="' . $rowIndex . '" data-columnset-index="' . $colSetIndex . '"></wizPlaceholder>';
    }

    return $return;
}


function generate_column_start($rowIndex, $columnSetIndex, $columnIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        error_log('No template data provided for column ' . $columnIndex);
        return;
    }

    $columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
    if (!$columnSet) return '';
    $layoutClass = $columnSet['layout'] ?? '';
    $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
    $mobileWrapClass = '';

    if ($mobileWrap == 'on') {
        $mobileWrapClass = 'wrap';
    }
    $columns = $columnSet['columns'];
    $column = $columns[$columnIndex] ?? null;

    if (!$column || $column['activation'] !== 'active') {
        error_log('Not active or found: Row ' . $rowIndex . ' ColSet ' . $columnSetIndex . ' Column ' . $columnIndex);
        return '';
    }

    $columnClasses = $column['settings']['column_classes'] ?? '';

    $templateStyles = $templateData['template_options']['template_styles'];
    $colValign = $column['settings']['valign'] ? strtolower($column['settings']['valign']) : 'top';

    $colBackgroundCSS = generate_background_css($column['settings'], '');
    $msoColBackgroundCSS = generate_background_css($column['settings'], '', true);

    $templateWidth = $templateStyles['body-and-background']['template_width'];
    $templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;

    // Determine column width based on layout and style index
    if ($layoutClass === 'two-col') {
        $columnWidthPx = round($templateWidth / 2, 0);
        $columnWidthPct = 50;
    } elseif ($layoutClass === 'three-col') {
        $columnWidthPx = round($templateWidth / 3, 0);
        $columnWidthPct = 33.33;
    } elseif ($layoutClass === 'sidebar-left') {
        if ($columnIndex === 0) {
            $columnWidthPx = round($templateWidth * 0.33, 0);
            $columnWidthPct = 33.33;
        } else {
            $columnWidthPx = round($templateWidth * 0.67, 0);
            $columnWidthPct = 66.67;
        }
    } elseif ($layoutClass === 'sidebar-right') {
        if ($columnIndex === 0) {
            $columnWidthPx = round($templateWidth * 0.67, 0);
            $columnWidthPct = 66.67;
        } else {
            $columnWidthPx = round($templateWidth * 0.33, 0);
            $columnWidthPct = 33.33;
        }
    } else {
        // Default layout (single column)
        $columnWidthPx = $templateWidth;
        $columnWidthPct = 100;
    }

    $columnStyle = "display: inline-block; width: {$columnWidthPct}%; max-width: {$columnWidthPx}px; font-size: {$templateStyles['font-styles']['template_font_size']}; vertical-align: {$colValign}; text-align: left;";

    $columnDataAttr = $isEditor ? 'data-column-index=' . $columnIndex : '';
    $return = '';
    if ($isEditor) {
        $return .= '<wizPlaceholder data-preview-part="column_start" data-row-index="' . $rowIndex . '" data-columnset-index="' . $columnSetIndex . '" data-column-index="' . $columnIndex . '"></wizPlaceholder>';
    }
    $return .= "<!--[if mso]><td class='$columnClasses' style='$msoColBackgroundCSS vertical-align:$colValign;' width='$columnWidthPx' valign='$colValign'><![endif]-->";
    $return .= "<div class='column $columnClasses $mobileWrapClass' $columnDataAttr style='$columnStyle $colBackgroundCSS' valign='$colValign' dir='ltr'>";

    return $return;
}

function generate_column_end($rowIndex, $colSetIndex, $columnIndex, $isEditor = false)
{
    $return = '';

    $return .= '</div>'; // Close .column div
    $return .= '<!--[if mso]></td><![endif]-->';

    if ($isEditor) {
        $return .= '<wizPlaceholder data-preview-part="column_end" data-row-index="' . $rowIndex . '" data-columnset-index="' . $colSetIndex . '" data-column-index="' . $columnIndex . '"></wizPlaceholder>';
    }

    return $return;
}

function idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        $templateData = get_wiztemplate($templateId);
    }

    $chunk = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]['chunks'][$chunkIndex] ?? null;
    if (!$chunk) return '';

    $chunkType = $chunk['field_type'];
    $templateOptions = $templateData['template_options'];
    $chunkHtml = '';
    switch ($chunkType) {
        case 'text':
            $chunkHtml = idwiz_get_plain_text_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'image':
            $chunkHtml = idwiz_get_image_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'button':
            $chunkHtml = idwiz_get_button_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'icon-list':
            $chunkHtml = idwiz_get_icon_list_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'spacer':
            $chunkHtml = idwiz_get_spacer_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'snippet':
            $chunkHtml = idwiz_get_snippet_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'interactive':
            $chunkHtml = idwiz_get_interactive_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'html':
            $chunkHtml = idwiz_get_raw_html_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        default:
            return 'Unknown chunk type passed for generation';
            break;
    }

    

    return $chunkHtml;
}



add_action('wp_ajax_get_wiztemplate_part_html', 'get_wiztemplate_part_html');

function get_wiztemplate_part_html()
{
    // Verify the nonce for security.
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    // Get the template data
    $templateData = json_decode(stripslashes($_POST['templateData']), true);
    $isEditor = $_POST['isEditor'] ?? false;
    $partType = isset($_POST['partType']) ? sanitize_text_field($_POST['partType']) : null;

    $rowIndex = isset($_POST['rowIndex']) ? intval($_POST['rowIndex']) : null;
    $columnSetIndex = isset($_POST['columnSetIndex']) ? intval($_POST['columnSetIndex']) : null;
    $columnIndex = isset($_POST['columnIndex']) ? intval($_POST['columnIndex']) : null;
    $chunkIndex = isset($_POST['chunkIndex']) ? intval($_POST['chunkIndex']) : null;

    // Generate the full structure
    $structure = generate_template_structure($templateData, $isEditor);

    // Extract the requested part
    $html = '';
    switch ($partType) {
        case 'fullTemplate':
        case 'email_head':
        case 'body_start':
            $html = render_template_from_structure($structure);
            break;
        case 'standard_header':
            $html = $structure['top'][$partType];
            break;
        case 'standard_footer':
        case 'fine_print':
            $html = $structure['bottom'][$partType];
            break;
        case 'allRows':
            $html = render_all_rows($structure['rows']);
            break;
        case 'row':
            $html = render_row($structure['rows'][$rowIndex]);
            break;
        case 'row_start':
            $html = $structure['rows'][$rowIndex]['start'];
            break;
        case 'row_end':
            $html = $structure['rows'][$rowIndex]['end'];
            break;
        case 'columnset':
            $html = render_columnset($structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]);
            break;
        case 'columnset_start':
            $html = $structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]['start'];
            break;
        case 'columnset_end':
            $html = $structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]['end'];
            break;
        case 'column':
            $html = render_column($structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]);
            break;
        case 'column_start':
            $html = $structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]['start'];
            break;
        case 'column_end':
            $html = $structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]['end'];
            break;
        case 'chunk':
            $html = $structure['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]['chunks'][$chunkIndex]['chunk'];
            break;
        default:
            wp_send_json_error(['message' => 'Invalid part type']);
            return;
    }

    wp_send_json_success(['html' => $html]);
}

// Helper functions to render specific parts
function render_all_rows($rows)
{
    return implode('', array_map('render_row', $rows));
}

function render_row($row)
{
    return $row['start'] . implode('', array_map('render_columnset', $row['columnSets'])) . $row['end'];
}

function render_columnset($columnSet)
{
    return $columnSet['start'] . implode('', array_map('render_column', $columnSet['columns'])) . $columnSet['end'];
}

function render_column($column)
{
    return $column['start'] . implode('', array_column($column['chunks'], 'chunk')) . $column['end'];
}
