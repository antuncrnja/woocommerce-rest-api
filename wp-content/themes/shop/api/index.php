<?php
// Include WordPress core
require_once('../../../../wp-load.php');

// Ensure WooCommerce is active
if (!class_exists('WooCommerce')) {
    wp_send_json_error('WooCommerce is not active');
    exit;
}

// Initialize response array
$response = array();

// Gather filter parameters from GET method
$name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
$categories = isset($_GET['categories']) ? explode(',', sanitize_text_field($_GET['categories'])) : array();
$tags = isset($_GET['tags']) ? explode(',', sanitize_text_field($_GET['tags'])) : array();
$min_price = isset($_GET['minprice']) ? floatval($_GET['minprice']) : 0;
$max_price = isset($_GET['maxprice']) ? floatval($_GET['maxprice']) : PHP_INT_MAX;

// Pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Default to page 1
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10; // Default to 10 products per page
$offset = ($page - 1) * $per_page; // Calculate offset

// Count total matching products for pagination
$total_args = array(
    'status' => 'publish',
    'limit' => -1, // Fetch all to count
    'return' => 'ids', // Fetch only IDs to speed up the query
    's' => $name,
    'category' => $categories,
    'tag' => $tags,
);

$total_query = new WC_Product_Query($total_args);
$total_products = $total_query->get_products();
$total_count = count($total_products); // Total number of products matching the criteria

// Calculate total pages
$total_pages = ceil($total_count / $per_page);

// Fetch products with pagination
$args = array(
    'status' => 'publish',
    'limit' => $per_page,
    'offset' => $offset,
    's' => $name,
    'category' => $categories,
    'tag' => $tags,
);

$query = new WC_Product_Query($args);
$products = $query->get_products();

// Filter products by price
$filtered_products = array_filter($products, function($product) use ($min_price, $max_price) {
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    return ($sale_price ? $sale_price : $regular_price) >= $min_price && ($sale_price ? $sale_price : $regular_price) <= $max_price;
});

// Build response for filtered products
$response = array_map(function($product) {
    $image_id = $product->get_image_id();
    $thumbnail_url = wp_get_attachment_image_url($image_id, 'thumbnail'); // Get thumbnail image
    $original_image_url = wp_get_attachment_url($image_id); // Get original image

    return array(
        'name' => $product->get_name(),
        'description' => $product->get_description(),
        'price' => $product->get_regular_price(),
        'price_with_discount' => $product->get_sale_price() ? $product->get_sale_price() : $product->get_regular_price(),
        'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
        'tags' => wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names')),
        'featured_image_original' => $original_image_url,
        'featured_image_thumbnail' => $thumbnail_url,
        'product_url' => get_permalink($product->get_id()),
        'gallery_images_urls' => array_map('wp_get_attachment_url', $product->get_gallery_image_ids())
    );
}, $filtered_products);

// Determine if current page is the last page
$is_last_page = $page >= $total_pages;

// Include pagination info and 'is_last_page' in your response
$response_data = array(
    'products' => $response,
    'is_last_page' => $is_last_page,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'total_products' => $total_count,
);


// Return the response as JSON
if (empty($response)) {
    wp_send_json(['error' => 'No products found within the specified criteria']);
} else {
    wp_send_json($response_data);
}