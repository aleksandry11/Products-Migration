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
    'option3name'                   => 'Option 3 Name', //can be left blank
    'option3value'                  => 'Option 3 Value', //can be left blank
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

//get all products from a database
function get_products() {

    global $wpdb;
    
    $data = [];

    //get query parts

    $tags_query= get_tags_query('_tag');
    $category_query= get_category_query('_cat');
    $image_query = get_image();
    $variant_sku = get_variant_sku('_sku');

    $price = get_variants_query('_price');
    $tax = get_variants_query('_tax_status');
    $product_type = get_product_type('_type');

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
            wppm_sku.meta_value as variant_sku,
            wppm{$price['slug']}.meta_value as variant_price,
            wppm{$tax['slug']}.meta_value as variant_taxable,
            wpt{$product_type['slug']}.name as product_type
        FROM
            wp_posts as wpp

        $tags_query

        $category_query

        $image_query

        $variant_sku

        {$price['query']}

        {$tax['query']}

        {$product_type['query']}
            
        WHERE 
            wpp.post_type ='product'
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
            'variant_sku' => $result->variant_sku,
            'price' => $result->variant_price,
            'taxable' => $result->variant_taxable === 'taxable' ? 'true' : 'false',
            'variable' => $result->product_type
        );
    }
    return $data;
}

/**
 * Query Parts for products request
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

function get_product_type($slug) {
    $query = "
    JOIN
        wp_term_relationships as wptr{$slug}
    ON
        wptr{$slug}.object_id = wpp.ID

    JOIN
        wp_terms as wpt{$slug}
    ON
        wptr{$slug}.term_taxonomy_id = wpt{$slug}.term_id AND (wpt{$slug}.name = 'simple' OR wpt{$slug}.name = 'variable')
    ";

    return [
        'slug' => $slug,
        'query' => $query
    ];
}

/**
 * Admin Page
 */
function pm_init() {
    global $config;

    $products = get_products();
    $product_variations = get_product_variations();

    //csv array records

    $csv_arr = [];

    foreach ($config as $key => $value) {
        $csv_arr[0][] = $value;
    }

    foreach ($products as $product) {

        //check if product has variations
        if ($product['variable'] === 'variable') {

            # combine variations of one product to the temporary array
            $tmp = [];

            foreach ($product_variations as $i => $product_variation) {
                $tmp[] = $product_variations[$i];

                if ($i < (count($product_variations) - 1) && $tmp[0]['variation_id'] === $product_variations[$i + 1]['variation_id']) {
                    $tmp[] = $product_variations[$i + 1];
                }

                if ($product_variations[$i]['parent_post'] === $product['id']) {
                    
                    /**
                     * all variations records
                     */
                    $csv_arr[] = array(
                        $product['handle'],
                        $key === 0 ? $product['title'] : '',
                        $key === 0 ? $product['body'] : '',
                        $key === 0 ? 'vendor' : '',
                        $key === 0 ? $product['type'] : '',
                        $key === 0 ? $product['tags'] : '',
                        $key === 0 ? $product['published'] : '',
                        get_option_name($tmp, 0),
                        get_option_value($tmp, 0),
                        get_option_name($tmp, 1),
                        get_option_value($tmp, 1),
                        get_option_name($tmp, 2),
                        get_option_value($tmp, 2),
                    );
    
                }
                unset($tmp);
            }
        } else {
            //simple product record
            $csv_arr[] = [
                $product['handle'],
                $product['title'],
                $product['body'],
                'vendor',
                $product['type'],
                $product['tags'],
                $product['published'],
                'Title',
                'Default Title',
                '',
                '',
                '',
                '',
            ];
        }
        

    }
    ?>
    <div class="wrap">
        <h1>Products Migration Info</h1>
    </div>
    <?php
}

/**
 * these functions should be dependent on each other. Returning values must be like this:
 * 
 *    [attribute_name] = $value | [second_attriubte_name] = $value | [third_attribute_name] = $value
 * 
 * 
 * All those attributes are set on each product variation e.g.:
 * product = Shoes.
 * 
 * Attributes to a variations would look like this:
 * [attriubte_name] = Color
 * [attribute_value] = Red
 * 
 * [second_attriubte_name] = Size
 * [second_attriubte_value] = Small;
 * 
 * etc.
 * By default shopify expect for you to have only 3 options(attriubtes);
 * 
 * ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓ should be reworked ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
 */

//get option name of a product's variation
function get_option_name($arr, $index) {

    return str_replace('attribute_pa_', '', $arr[$index]['options_name']);
}
//get option value of a product's variation
function get_option_value($arr, $index) {
    return $arr[$index]['options_value'];
}


// get all products variations from a database

function get_product_variations() {
    global $wpdb;

    $data = [];

    // get query parts

    $sku_query = get_variants_query('_sku');
    $weight_query = get_variants_query('_weight');
    $price_query = get_variants_query('_price');
    $regular_price_query = get_variants_query('_regular_price');
    $sale_price_query = get_variants_query('_sale_price');
    $tax_query = get_variants_query('_tax_status');
    $options_query = get_variant_attributes('options');

    $results = $wpdb->get_results("
    SELECT
        wpp.ID as variation_id,
        wpp.post_parent as parent_post,
        wppm{$sku_query['slug']}.meta_value AS variant_sku,
        wppm{$weight_query['slug']}.meta_value as variant_grams,
        wppm{$price_query['slug']}.meta_value as variant_price,
        wppm{$regular_price_query['slug']}.meta_value as variant_regular_price,
        wppm{$sale_price_query['slug']}.meta_value as variant_sale_price,
        wppm{$tax_query['slug']}.meta_value as variant_taxable,
        wppm{$options_query['slug']}.meta_key as options,
        wppm{$options_query['slug']}.meta_value as options_value
    FROM
        wp_posts AS wpp
        
    {$sku_query['query']}

    {$weight_query['query']}

    {$price_query['query']}

    {$tax_query['query']}

    {$regular_price_query['query']}

    {$sale_price_query['query']}

    {$options_query['query']}
        
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
            'variant_taxable' => $result->variant_taxable === 'taxable' ? 'true' : 'false',
            'options_name' => $result->options,
            'options_value' => $result->options_value
        ];
    }

    /**
     * csv file creation. should be replaced!
     */
    create_csv_file($data, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'csv_file.csv');

    return $data;

}

/**
 * Query parts for product variations request
 */
function get_variants_query($slug) {
    $query = "
        LEFT JOIN
            wp_postmeta as wppm$slug
        ON
            wppm$slug.post_id = wpp.ID AND wppm$slug.meta_key = '$slug'
    ";

    return array(
        'slug' => $slug,
        'query' => $query
    );
}

function get_variant_attributes($slug) {
    $query = "
    LEFT JOIN wp_postmeta AS wppm$slug
    ON
        wppm$slug.post_id = wpp.ID AND wppm$slug.meta_key LIKE 'attribute_pa%'
    ";

    return array(
        'slug' => $slug,
        'query' => $query
    );
}


function create_csv_file($create_data, $file = null, $col_delimiter = ';', $row_delimiter = "\r\n") {
    
    $CSV_str = '';

    foreach($create_data as $row) {
        $cols = array();

        foreach($row as $col_val) {
            //Strings should be in quotes
            // " in stings shoud be prefaced with "
            if ($col_val && preg_match('/[",;\r\n]/', $col_val)) {
                if ($row_delimiter === "\r\n") {
                    $col_val = str_replace("\r\n", '\n', $col_val);
                    $col_val = str_replace("\r", '', $col_val);
                }
                elseif ($row_delimiter === "\n") {
                    $col_val = str_replace("\n", '\r', $col_val);
                    $col_val = str_replace("\r\n", '\r', $col_val);
                }

                $col_val = str_replace('"', '""', $col_val);
                $col_val = '"' . $col_val . '"';
            }

            $cols[] = $col_val;
        }

        $CSV_str .= implode($col_delimiter, $cols) . $row_delimiter;
    }
    
    $CSV_str = rtrim($CSV_str, $row_delimiter);

    $CSV_str = iconv('UTF-8', 'cp1251', $CSV_str);

    file_put_contents($file, $CSV_str);

    return $CSV_str;
}


function create_csv_schema($config) {
    $data = [];
    foreach ($config as $key => $row) {
        $tmp[] = $row;
    }
    array_push($data, $tmp);
    return $data;
}

function separate_options($string) {
    $arr = explode(',', $string);

    $options = [];
    foreach($arr as $item) {
        $str = explode('=', $item);
        $key = trim($str[0], 'pa_');
        @$options[$key][] = $str[1];
    }

    return $options;
}