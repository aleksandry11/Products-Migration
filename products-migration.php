<?php
/**
 * Plugin Name: Products Migration
 */

if ( ! defined('ABSPATH') ) exit;

require dirname(__FILE__) . '/query-config.php';

spl_autoload_register(function($class_name) {
    $filePath = dirname(__FILE__) . '/classes/' . $class_name . '.php';
    $filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);

    if (file_exists($filePath)) {
        require_once $filePath;
    } 
});

/**
 * pm stands for Products Migration
 *
 * 
 * Add Plugin's page to admin menu
 */
function pm_setup_menu() {
    # add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);
    add_menu_page('Products Migration', 'Products Migration', 'manage_options', 'products-migration', 'pm_init', plugins_url('assets/icon/pm.svg', __FILE__), 10);
}
add_action('admin_menu', 'pm_setup_menu');

$config = [
    'handle'                        => 'Handle',
    'title'                         => 'Title',
    'body'                          => 'Body (HTML)',
    'vendor'                        => 'Vendor', //min 2 characters
    'type'                          => 'Type',
    'tags'                          => 'Tags', //can be left blank
    'published'                     => 'Published',
    'option1name'                   => 'Option 1 Name', //For products with only single option should be set to 'Title'
    'option1value'                  => 'Option 1 Value', //For products with only single option should be set to 'Default Title'
    'option2name'                   => 'Option 2 Name', //can be left blank
    'option2value'                  => 'Option 2 Value', //can be left blank
    'variant_sku'                   => 'Variant SKU',
    'variant_grams'                 => 'Variant Grams',
    'variant_inventory_tracker'     => 'Variant Inventory Tracker', //can be left blank    
    'variant_inventory_qty'         => 'Variant Inventory Qty',
    'variant_inventory_policy'      => 'Variant Inventory Policy',
    'variant_fulfillment_service'   => 'Variant Fulfillment Service',
    'variant_price'                 => 'Variant Price',
    'variant_compare_at_price'      => 'Variant Compare at Price',
    'variant_requires_shipping'     => 'Variant Requires Shipping', //blank = false
    'variant_taxable'               => 'Variant Taxable', //blank = false
    'variant_barcode'               => 'Variant Barcode', //blank = false
    'image_src'                     => 'Image Src',
    'image_position'                => 'Image Position',
    'image_alt_text'                => 'Image Alt Text', //can be left blank
    'gift_card'                     => 'Gift Card',
    'seo_title'                     => 'SEO Title',
    'seo_description'               => 'SEO Description',
    'google_shopping_metafields'    => 'Google Shopping Metafields',
    'variant_image'                 => 'Variant Image',
    'variant_weight_unit'           => 'Variant Weight Unit',
    'variant_tax_code'              => 'Variant Tax Code', //shopify plus
    'cost_per_item'                 => 'Cost per item'
];

function pre_dump($vars, $printed = false, $hidden = false) {
    echo '<pre data-type="debug_dump" '. ($hidden ? 'style="display:none"' : '') . '>';
    if ($printed) {
        var_dump($vars);
    } else {
        print_r($vars);
    }
    echo '</pre>';
}

//
function migration($config) {
    if (!$config) return false;

    global $wpdb;
    
    $data = [];

    $tags_query= get_tags_query('_tag');
    $category_query= get_category_query('_cat');
    $image_query = get_image();
    $options = get_options('_options');
    $variant_sku = get_variant_sku('_sku');

    $price = get_variants_query('_price');
    $tax = get_variants_query('_tax_status');

    $results = $wpdb->get_results(
        "SELECT
            wpp.ID as product_id,
            wpp.post_name as product_name, 
            wpp.post_title as product_title, 
            wpp.post_status as published,
            wpp.post_content as product_content,
            GROUP_CONCAT(DISTINCT wpt_tag.name) as tags,
            wpt_cat.name as category,
            GROUP_CONCAT(DISTINCT s.guid SEPARATOR ',') as product_image,
            wpp3.guid as variation_image,
            GROUP_CONCAT(DISTINCT wptt_options.taxonomy, '=', wpt_options.slug SEPARATOR ',') as options,
            wppm_sku.meta_value as variant_sku,
            wppm{$price['slug']}.meta_value as variant_price,
            wppm{$tax['slug']}.meta_value as variant_taxable
        FROM
            wp_posts as wpp

        $tags_query

        $category_query

        $image_query

        $options

        $variant_sku

        {$price['query']}

        {$tax['query']}
            
        WHERE 
            wpp.post_type ='product' OR wpp.post_type = 'product_variation' 
        GROUP BY wpp.ID"
    );

    foreach( $results as $result) {
        $data[] = array(
            'id' => $result->product_id,
            'handle' => $result->product_name,
            'title' => $result->product_title,
            'body' => $result->product_content,
            'type' => $result->category,
            'tags' => $result->tags,
            'published' => $result->published === 'publish' ? 'true' : 'false',
            'image_src' => $result->product_image,
            'variation_img' => $result->variation_image,
            'options' => $result->options,
            'variant_sku' => $result->variant_sku,
            'price' => $result->variant_price,
            'taxable' => $result->variant_taxable === 'taxable' ? 'true' : 'false'
        );
    }
    return $data;
}

/**
 * Query Parts
 */
function get_tags_query($db_slug) {
    $query = "
        JOIN
            wp_term_relationships as wptr{$db_slug}
        ON
            wpp.ID = wptr{$db_slug}.object_id
        LEFT JOIN
            wp_termmeta as wptm{$db_slug}
        ON
            wptm{$db_slug}.term_id = wptr{$db_slug}.term_taxonomy_id AND wptm{$db_slug}.meta_key = 'product_count_product_tag'
        LEFT JOIN
            wp_terms as wpt{$db_slug}
        ON
            wpt{$db_slug}.term_id = wptm{$db_slug}.term_id
        JOIN
            wp_term_taxonomy as wptt{$db_slug}
        ON
            wptr{$db_slug}.term_taxonomy_id = wptt{$db_slug}.term_taxonomy_id
    ";

    return $query;
}

function get_category_query($db_slug) {
    $query = "
        JOIN
            wp_term_relationships as wptr{$db_slug}
        ON
            wpp.ID = wptr{$db_slug}.object_id
        JOIN
            wp_termmeta as wptm{$db_slug}
        ON
            wptm{$db_slug}.term_id = wptr{$db_slug}.term_taxonomy_id AND wptm{$db_slug}.meta_key = 'product_count_product_cat'
        JOIN
            wp_terms as wpt{$db_slug}
        ON
            wpt{$db_slug}.term_id = wptm{$db_slug}.term_id
        JOIN
            wp_term_taxonomy as wptt{$db_slug}
        ON
            wptr{$db_slug}.term_taxonomy_id = wptt{$db_slug}.term_taxonomy_id
    ";
    return $query;
}

function get_image() {
    $query = "
        JOIN
        (
            SELECT
                wpp1.guid,
                wpp1.post_parent
            FROM
                wp_posts as wpp1
            WHERE
                wpp1.post_type = 'attachment'
        ) as s
        ON s.post_parent = wpp.ID

        /* product's variations images */
        LEFT OUTER JOIN
            wp_posts as wpp2
        ON
            wpp2.post_parent = wpp.ID AND wpp2.post_type = 'product_variation'

        LEFT OUTER JOIN
            wp_posts as wpp3
        ON
            wpp3.post_parent = wpp2.ID AND wpp3.post_type = 'attachment'

    ";

    return $query;
}

function get_options($slug) {
    $query = "
    JOIN
        wp_term_relationships as wptr$slug
    ON
        wptr$slug.object_id = wpp.ID
        
    LEFT JOIN
        wp_term_taxonomy as wptt$slug
    ON
        wptt$slug.term_id = wptr$slug.term_taxonomy_id AND wptt$slug.taxonomy LIKE 'pa_%'
        
    LEFT JOIN
        wp_term_relationships as wptr2$slug
    ON
        wptr2$slug.term_taxonomy_id = wptt$slug.term_id
        
    LEFT JOIN
        wp_terms as wpt$slug
    ON
        wpt$slug.term_id = wptr2$slug.term_taxonomy_id
    ";

    return $query;
}

function get_variant_sku($slug) {
    $query = "
    LEFT JOIN
        wp_postmeta as wppm$slug
    ON
        wppm$slug.post_id = wpp.ID AND wppm$slug.meta_key LIKE '%sku'
    ";

    return $query;
}
/**
 * Admin Page
 */
function pm_init() {
    global $config;

    
    pre_dump(migration($config), false);
    echo '__________________________________________PRODUCT VARIATIONS______________________________________________';
    pre_dump(migration_variations(), false);
    

    ?>
    <div class="wrap">
        <h1>Products Migration Info</h1>
    </div>
    <?php
}



function migration_variations() {
    global $wpdb;

    $data = [];

    $sku_query = get_variants_query('_sku');
    $weight_query = get_variants_query('_weight');
    $price_query = get_variants_query('_price');
    $regular_price_query = get_variants_query('_regular_price');
    $sale_price_query = get_variants_query('_sale_price');
    $tax_query = get_variants_query('_tax_status');

    $results = $wpdb->get_results("
    SELECT
        wpp.ID as variation_id,
        wpp.post_parent as parent_post,
        wppm{$sku_query['slug']}.meta_value AS variant_sku,
        wppm{$weight_query['slug']}.meta_value as variant_grams,
        wppm{$price_query['slug']}.meta_value as variant_price,
        wppm{$regular_price_query['slug']}.meta_value as variant_regular_price,
        wppm{$sale_price_query['slug']}.meta_value as variant_sale_price,
        wppm{$tax_query['slug']}.meta_value as variant_taxable
    FROM
        wp_posts AS wpp
        
    {$sku_query['query']}

    {$weight_query['query']}

    {$price_query['query']}

    {$tax_query['query']}

    {$regular_price_query['query']}

    {$sale_price_query['query']}
        
    WHERE
        wpp.post_type = 'product_variation'
    
    ");

    foreach($results as $result) {
        $data[] = [
            'variation_id' => $result->variation_id,
            'parent_post' => $result->parent_post,
            'variant_sku' => $result->variant_sku,
            'variant_grams' => (float)$result->variant_grams * 1000,
            'variant_price' => $result->variant_price,
            'variant_regular_price' => $result->variant_regular_price,
            'variant_sale_price' => $result->variant_sale_price,
            'variant_taxable' => $result->variant_taxable === 'taxable' ? 'true' : 'false'
        ];
    }

    return $data;

}

function get_variants_query($slug) {
    $query = "
        LEFT JOIN
            wp_postmeta as wppm$slug
        ON
            wppm$slug.post_id = wpp.ID AND wppm$slug.meta_key = '$slug'
    ";

    return [
        'slug' => $slug,
        'query' => $query
    ];
}