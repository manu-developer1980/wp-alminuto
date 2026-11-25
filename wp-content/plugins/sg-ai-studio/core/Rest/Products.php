<?php
/**
 * Products API class for managing WooCommerce products via REST API
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use SG_AI_Studio\Activity_Log\Activity_Log_Helper;
use SG_AI_Studio\Helper\Helper;

/**
 * Handles REST API endpoints for WooCommerce product operations.
 */
class Products extends Rest_Controller_Base {
	use Object_Terms;

	/**
	 * REST API base
	 *
	 * @var string
	 */
	private $base = 'products';

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Register endpoint for creating and getting products.
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_product' ),
					'permission_callback' => array( $this, 'create_permissions_check' ),
					'args'                => $this->get_create_product_args(),
					'description'         => 'Creates a new WooCommerce product with the provided data.',
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_products' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'args'                => $this->get_products_args(),
					'description'         => 'Retrieves a list of WooCommerce products based on the provided filters. Pass embed_terms=true to also receive the named terms of every taxonomy on each product.',
				),
				'schema' => array( $this, 'get_product_schema' ),
			)
		);

		// Register endpoint for retrieving, updating, and deleting a single product.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
					'args'                => array(
						'id'          => array(
							'description' => 'Unique identifier for the product.',
							'type'        => 'integer',
							'required'    => true,
						),
						'embed_terms' => $this->get_embed_terms_arg(),
					),
					'description'         => 'Retrieves a specific WooCommerce product by ID. Pass embed_terms=true to also receive the named terms of every taxonomy on the product, including custom ones, instead of only product categories and tags.',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_product' ),
					'permission_callback' => array( $this, 'update_permissions_check' ),
					'args'                => $this->get_update_product_args(),
					'description'         => 'Updates a specific WooCommerce product with the provided data.',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_product' ),
					'permission_callback' => array( $this, 'delete_permissions_check' ),
					'args'                => array(
						'id'    => array(
							'description' => 'Unique identifier for the product.',
							'type'        => 'integer',
							'required'    => true,
						),
					),
					'description'         => 'Deletes a specific WooCommerce product. By default, moves to trash unless force=true.',
				),
				'schema' => array( $this, 'get_product_schema' ),
			)
		);

		// Register endpoint for retrieving the variations of a variable product.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/variations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_product_variations' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => 'Unique identifier for the variable product.',
							'type'        => 'integer',
							'required'    => true,
						),
					),
					'description'         => 'Retrieves every variation of a variable product with its own price, stock and attributes.',
				),
			)
		);

		// Register endpoint for bulk operations on products.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/batch',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'batch_operations' ),
					'permission_callback' => array( $this, 'create_permissions_check' ),
					'args'                => $this->get_batch_args(),
					'description'         => 'Batch create, update, and delete operations in a single request.',
				),
				'schema' => array( $this, 'get_batch_schema' ),
			)
		);

		// Register endpoints for reading and assigning the terms of a product.
		$this->register_object_terms_routes( $this->base, 'product' );
	}

	/**
	 * Get arguments for creating a product
	 *
	 * @return array
	 */
	protected function get_create_product_args() {
		$args = array(
			'name'               => array(
				'description' => 'Product name.',
				'type'        => 'string',
				'required'    => true,
			),
			'description'        => array(
				'description' => 'Product description.',
				'type'        => 'string',
				'required'    => false,
			),
			'short_description'  => array(
				'description' => 'Product short description.',
				'type'        => 'string',
				'required'    => false,
			),
			'sku'                => array(
				'description' => 'Unique identifier.',
				'type'        => 'string',
				'required'    => false,
			),
			'regular_price'      => array(
				'description' => 'Product regular price.',
				'type'        => 'string',
				'required'    => false,
			),
			'sale_price'         => array(
				'description' => 'Product sale price.',
				'type'        => 'string',
				'required'    => false,
			),
			'status'             => array(
				'description' => 'Product status.',
				'type'        => 'string',
				'enum'        => array( 'draft', 'pending', 'private', 'publish' ),
				'default'     => 'publish',
				'required'    => false,
			),
			'catalog_visibility' => array(
				'description' => 'Catalog visibility.',
				'type'        => 'string',
				'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
				'default'     => 'visible',
				'required'    => false,
			),
			'featured'           => array(
				'description' => 'Featured product.',
				'type'        => 'boolean',
				'default'     => false,
				'required'    => false,
			),
			'virtual'            => array(
				'description' => 'If the product is virtual.',
				'type'        => 'boolean',
				'default'     => false,
				'required'    => false,
			),
			'downloadable'       => array(
				'description' => 'If the product is downloadable.',
				'type'        => 'boolean',
				'default'     => false,
				'required'    => false,
			),
			'manage_stock'       => array(
				'description' => 'Stock management at product level.',
				'type'        => 'boolean',
				'default'     => false,
				'required'    => false,
			),
			'stock_quantity'     => array(
				'description' => 'Stock quantity.',
				'type'        => 'integer',
				'required'    => false,
			),
			'stock_status'       => array(
				'description' => 'Controls the stock status of the product.',
				'type'        => 'string',
				'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
				'default'     => 'instock',
				'required'    => false,
			),
			'categories'         => array(
				'description' => 'List of categories.',
				'type'        => 'array',
				'items'       => array(
					'type' => 'integer',
				),
				'required'    => false,
			),
			'tags'               => array(
				'description' => 'List of tags.',
				'type'        => 'array',
				'items'       => array(
					'type' => 'integer',
				),
				'required'    => false,
			),
			'weight'             => array(
				'description' => 'Product weight.',
				'type'        => 'string',
				'required'    => false,
			),
			'dimensions'         => array(
				'description' => 'Product dimensions.',
				'type'        => 'object',
				'properties'  => array(
					'length' => array(
						'description' => 'Product length.',
						'type'        => 'string',
					),
					'width'  => array(
						'description' => 'Product width.',
						'type'        => 'string',
					),
					'height' => array(
						'description' => 'Product height.',
						'type'        => 'string',
					),
				),
				'required'    => false,
			),
			'images'             => array(
				'description' => 'List of images.',
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array(
							'description' => 'Image ID.',
							'type'        => 'integer',
						),
						'src'      => array(
							'description' => 'Image URL.',
							'type'        => 'string',
						),
						'name'     => array(
							'description' => 'Image name.',
							'type'        => 'string',
						),
						'alt'      => array(
							'description' => 'Image alternative text.',
							'type'        => 'string',
						),
						'position' => array(
							'description' => 'Image position. 0 means that the image is featured.',
							'type'        => 'integer',
						),
					),
				),
				'required'    => false,
			),
		);

		return array_merge( $args, $this->get_sale_date_args() );
	}

	/**
	 * Get arguments for updating a product
	 *
	 * @return array
	 */
	protected function get_update_product_args() {
		$args = [];

		// Add product ID.
		$args['id'] = array(
			'description' => 'Unique identifier for the product.',
			'type'        => 'integer',
			'required'    => true,
		);

		return array_merge( $args, $this->get_sale_date_args() );
	}

	/**
	 * Get the scheduled sale arguments shared by create, update and batch.
	 *
	 * @return array
	 */
	protected function get_sale_date_args() {
		$rules = 'Requires a sale_price, sent in the same request or already stored. Empty string or null clears the date. Not supported on variable products, set the sale on each variation instead.';

		return array(
			'date_on_sale_from'     => array(
				'description' => 'Start of a scheduled sale, ISO 8601 in the site timezone (e.g. 2026-11-27 or 2026-11-27T09:00:00). A date without a time starts at 00:00:00 of that day. Until then the regular price is shown. ' . $rules,
				'type'        => array( 'string', 'null' ),
				'required'    => false,
			),
			'date_on_sale_to'       => array(
				'description' => 'End of a scheduled sale, ISO 8601 in the site timezone (e.g. 2026-11-30). A date without a time is inclusive and ends at 23:59:59 of that day. WooCommerce then ends the sale on its own. Must not be before date_on_sale_from. Clearing it keeps the sale active with no end date. ' . $rules,
				'type'        => array( 'string', 'null' ),
				'required'    => false,
			),
			'date_on_sale_from_gmt' => array(
				'description' => 'Same as date_on_sale_from, but in UTC. Takes precedence over date_on_sale_from when both are sent.',
				'type'        => array( 'string', 'null' ),
				'required'    => false,
			),
			'date_on_sale_to_gmt'   => array(
				'description' => 'Same as date_on_sale_to, but in UTC. Takes precedence over date_on_sale_to when both are sent.',
				'type'        => array( 'string', 'null' ),
				'required'    => false,
			),
		);
	}

	/**
	 * Get arguments for retrieving products
	 *
	 * @return array
	 */
	protected function get_products_args() {
		return array(
			'embed_terms'  => $this->get_embed_terms_arg(),
			'page'         => array(
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'minimum'           => 1,
				'required'          => false,
			),
			'per_page'     => array(
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'required'          => false,
			),
			'search'       => array(
				'description' => 'Limit results to those matching a string.',
				'type'        => 'string',
				'required'    => false,
			),
			'status'       => array(
				'description' => 'Limit result set to products with specific statuses.',
				'type'        => 'string',
				'enum'        => array( 'any', 'draft', 'pending', 'private', 'publish' ),
				'default'     => 'any',
				'required'    => false,
			),
			'sku'          => array(
				'description' => 'Limit result set to products with specific SKU.',
				'type'        => 'string',
				'required'    => false,
			),
			'featured'     => array(
				'description' => 'Limit result set to featured products.',
				'type'        => 'boolean',
				'required'    => false,
			),
			'category'     => array(
				'description' => 'Limit result set to products assigned specific categories. Accepts a category ID, a slug, or a comma-separated list of either.',
				'type'        => 'string',
				'required'    => false,
			),
			'tag'          => array(
				'description' => 'Limit result set to products assigned specific tags. Accepts a tag ID, a slug, or a comma-separated list of either.',
				'type'        => 'string',
				'required'    => false,
			),
			'include'      => array(
				'description' => 'Limit result set to specific product IDs.',
				'type'        => 'array',
				'items'       => array(
					'type' => 'integer',
				),
				'default'     => array(),
				'required'    => false,
			),
			'min_price'    => array(
				'description' => 'Limit result set to products based on a minimum price.',
				'type'        => 'string',
				'required'    => false,
			),
			'max_price'    => array(
				'description' => 'Limit result set to products based on a maximum price.',
				'type'        => 'string',
				'required'    => false,
			),
			'stock_status' => array(
				'description' => 'Limit result set to products with specified stock status.',
				'type'        => 'string',
				'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
				'required'    => false,
			),
			'type'         => array(
				'description'       => 'Limit result set to products of a specific type (e.g. simple, variable, grouped, external, or a plugin-registered type).',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'required'          => false,
			),
			'orderby'      => array(
				'description' => 'Sort collection by object attribute.',
				'type'        => 'string',
				'default'     => 'date',
				'enum'        => array( 'date', 'id', 'include', 'title', 'slug', 'price', 'popularity', 'rating' ),
				'required'    => false,
			),
			'order'        => array(
				'description' => 'Order sort attribute ascending or descending.',
				'type'        => 'string',
				'default'     => 'desc',
				'enum'        => array( 'asc', 'desc' ),
				'required'    => false,
			),
		);
	}

	/**
	 * Get product schema
	 *
	 * @return array
	 */
	public function get_product_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'product',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => 'Unique identifier for the product.',
					'type'        => 'integer',
					'readonly'    => true,
				),
				'name'               => array(
					'description' => 'Product name.',
					'type'        => 'string',
				),
				'slug'               => array(
					'description' => 'Product slug.',
					'type'        => 'string',
				),
				'permalink'          => array(
					'description' => 'Product URL.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'date_created'       => array(
					'description' => 'The date the product was created.',
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'date_modified'      => array(
					'description' => 'The date the product was last modified.',
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'type'               => array(
					'description' => 'Product type.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'status'             => array(
					'description' => 'Product status.',
					'type'        => 'string',
					'enum'        => array( 'draft', 'pending', 'private', 'publish' ),
				),
				'featured'           => array(
					'description' => 'Featured product.',
					'type'        => 'boolean',
				),
				'catalog_visibility' => array(
					'description' => 'Catalog visibility.',
					'type'        => 'string',
					'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
				),
				'description'        => array(
					'description' => 'Product description.',
					'type'        => 'string',
				),
				'short_description'  => array(
					'description' => 'Product short description.',
					'type'        => 'string',
				),
				'sku'                => array(
					'description' => 'Unique identifier.',
					'type'        => 'string',
				),
				'price'              => array(
					'description' => 'Current product price.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'regular_price'      => array(
					'description' => 'Product regular price.',
					'type'        => 'string',
				),
				'sale_price'         => array(
					'description' => 'Product sale price.',
					'type'        => 'string',
				),
				'price_html'         => array(
					'description' => 'Price formatted in HTML.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'currency'           => array(
					'description' => 'Store currency code.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'price_min'          => array(
					'description' => 'Lowest price among the product\'s variations or children. Only for variable products and single reads of grouped products.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'price_max'          => array(
					'description' => 'Highest price among the product\'s variations or children. Only for variable products and single reads of grouped products.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'regular_price_min'  => array(
					'description' => 'Lowest regular price among the product\'s variations. Variable products only.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'regular_price_max'  => array(
					'description' => 'Highest regular price among the product\'s variations. Variable products only.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'price_is_range'     => array(
					'description' => 'Whether the product spans a price range, meaning price_min and price_max differ.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'on_sale'            => array(
					'description' => 'Shows if the product is on sale right now. False before date_on_sale_from and after date_on_sale_to.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'date_on_sale_from'     => array(
					'description' => 'Start of the scheduled sale in the site timezone, null when not scheduled.',
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
				),
				'date_on_sale_from_gmt' => array(
					'description' => 'Start of the scheduled sale in UTC, null when not scheduled.',
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
				),
				'date_on_sale_to'       => array(
					'description' => 'End of the scheduled sale in the site timezone, null when the sale has no end date. The sale runs until this moment, usually 23:59:59 of the end day.',
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
				),
				'date_on_sale_to_gmt'   => array(
					'description' => 'End of the scheduled sale in UTC, null when the sale has no end date.',
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
				),
				'purchasable'        => array(
					'description' => 'Shows if the product can be bought.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'total_sales'        => array(
					'description' => 'Amount of sales.',
					'type'        => 'integer',
					'readonly'    => true,
				),
				'virtual'            => array(
					'description' => 'If the product is virtual.',
					'type'        => 'boolean',
				),
				'downloadable'       => array(
					'description' => 'If the product is downloadable.',
					'type'        => 'boolean',
				),
				'external_url'       => array(
					'description' => 'Product external URL.',
					'type'        => 'string',
				),
				'button_text'        => array(
					'description' => 'Product external button text.',
					'type'        => 'string',
				),
				'tax_status'         => array(
					'description' => 'Tax status.',
					'type'        => 'string',
					'enum'        => array( 'taxable', 'shipping', 'none' ),
				),
				'tax_class'          => array(
					'description' => 'Tax class.',
					'type'        => 'string',
				),
				'manage_stock'       => array(
					'description' => 'Stock management at product level.',
					'type'        => 'boolean',
				),
				'stock_quantity'     => array(
					'description' => 'Stock quantity.',
					'type'        => 'integer',
				),
				'stock_status'       => array(
					'description' => 'Controls the stock status of the product.',
					'type'        => 'string',
					'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
				),
				'backorders'         => array(
					'description' => 'If managing stock, this controls if backorders are allowed.',
					'type'        => 'string',
					'enum'        => array( 'no', 'notify', 'yes' ),
				),
				'backorders_allowed' => array(
					'description' => 'Shows if backorders are allowed.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'backordered'        => array(
					'description' => 'Shows if the product is on backordered.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'sold_individually'  => array(
					'description' => 'Allow one item to be bought in a single order.',
					'type'        => 'boolean',
				),
				'weight'             => array(
					'description' => 'Product weight.',
					'type'        => 'string',
				),
				'dimensions'         => array(
					'description' => 'Product dimensions.',
					'type'        => 'object',
					'properties'  => array(
						'length' => array(
							'description' => 'Product length.',
							'type'        => 'string',
						),
						'width'  => array(
							'description' => 'Product width.',
							'type'        => 'string',
						),
						'height' => array(
							'description' => 'Product height.',
							'type'        => 'string',
						),
					),
				),
				'shipping_required'  => array(
					'description' => 'Shows if the product need to be shipped.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'shipping_taxable'   => array(
					'description' => 'Shows whether or not the product shipping is taxable.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'shipping_class'     => array(
					'description' => 'Shipping class slug.',
					'type'        => 'string',
				),
				'shipping_class_id'  => array(
					'description' => 'Shipping class ID.',
					'type'        => 'integer',
					'readonly'    => true,
				),
				'reviews_allowed'    => array(
					'description' => 'Allow reviews.',
					'type'        => 'boolean',
				),
				'average_rating'     => array(
					'description' => 'Reviews average rating.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'rating_count'       => array(
					'description' => 'Amount of reviews that the product have.',
					'type'        => 'integer',
					'readonly'    => true,
				),
				'related_ids'        => array(
					'description' => 'List of related products IDs.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'readonly'    => true,
				),
				'upsell_ids'         => array(
					'description' => 'List of up-sell products IDs.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
				),
				'cross_sell_ids'     => array(
					'description' => 'List of cross-sell products IDs.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
				),
				'parent_id'          => array(
					'description' => 'Product parent ID.',
					'type'        => 'integer',
				),
				'purchase_note'      => array(
					'description' => 'Optional note to send the customer after purchase.',
					'type'        => 'string',
				),
				'categories'         => array(
					'description' => 'List of categories.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array(
								'description' => 'Category ID.',
								'type'        => 'integer',
							),
							'name' => array(
								'description' => 'Category name.',
								'type'        => 'string',
								'readonly'    => true,
							),
							'slug' => array(
								'description' => 'Category slug.',
								'type'        => 'string',
								'readonly'    => true,
							),
						),
					),
				),
				'tags'               => array(
					'description' => 'List of tags.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array(
								'description' => 'Tag ID.',
								'type'        => 'integer',
							),
							'name' => array(
								'description' => 'Tag name.',
								'type'        => 'string',
								'readonly'    => true,
							),
							'slug' => array(
								'description' => 'Tag slug.',
								'type'        => 'string',
								'readonly'    => true,
							),
						),
					),
				),
				'terms'              => array(
					'description' => 'Named terms assigned to the product, keyed by taxonomy slug. Present only when embed_terms=true was requested. A taxonomy with nothing assigned is an empty array.',
					'type'        => 'object',
					'readonly'    => true,
				),
				'images'             => array(
					'description' => 'List of images.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'            => array(
								'description' => 'Image ID.',
								'type'        => 'integer',
							),
							'date_created'  => array(
								'description' => 'The date the image was created.',
								'type'        => 'string',
								'format'      => 'date-time',
								'readonly'    => true,
							),
							'date_modified' => array(
								'description' => 'The date the image was last modified.',
								'type'        => 'string',
								'format'      => 'date-time',
								'readonly'    => true,
							),
							'src'           => array(
								'description' => 'Image URL.',
								'type'        => 'string',
							),
							'name'          => array(
								'description' => 'Image name.',
								'type'        => 'string',
							),
							'alt'           => array(
								'description' => 'Image alternative text.',
								'type'        => 'string',
							),
							'position'      => array(
								'description' => 'Image position. 0 means that the image is featured.',
								'type'        => 'integer',
							),
						),
					),
				),
				'attributes'         => array(
					'description' => 'List of attributes.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'        => array(
								'description' => 'Attribute ID.',
								'type'        => 'integer',
							),
							'name'      => array(
								'description' => 'Attribute name.',
								'type'        => 'string',
							),
							'position'  => array(
								'description' => 'Attribute position.',
								'type'        => 'integer',
							),
							'visible'   => array(
								'description' => 'Define if the attribute is visible on the "Additional information" tab in the product\'s page.',
								'type'        => 'boolean',
							),
							'variation' => array(
								'description' => 'Define if the attribute can be used as variation.',
								'type'        => 'boolean',
							),
							'options'   => array(
								'description' => 'List of available term names of the attribute.',
								'type'        => 'array',
								'items'       => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				'default_attributes' => array(
					'description' => 'Defaults variation attributes.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array(
								'description' => 'Attribute ID.',
								'type'        => 'integer',
							),
							'name'   => array(
								'description' => 'Attribute name.',
								'type'        => 'string',
							),
							'option' => array(
								'description' => 'Selected attribute term name.',
								'type'        => 'string',
							),
						),
					),
				),
				'variations'         => array(
					'description' => 'List of variations IDs.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'readonly'    => true,
				),
				'variation_ids'      => array(
					'description' => 'IDs of the product\'s variations. Variable products only.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'readonly'    => true,
				),
				'variation_attributes' => array(
					'description'          => 'Available option values per variation attribute, keyed by attribute name. Variable products only.',
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
					'readonly'             => true,
				),
				'grouped_products'   => array(
					'description' => 'List of grouped products ID.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
				),
				'menu_order'         => array(
					'description' => 'Menu order, used to custom sort products.',
					'type'        => 'integer',
				),
			),
		);
	}

	/**
	 * Create a new product
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_product( $request ) {
		// Check if powermode is enabled.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$product = new \WC_Product_Simple();
		$result  = $this->update_product_data( $product, $request );

		if ( is_wp_error( $result ) ) {
			return $this->get_invalid_product_data_response( $result );
		}

		$product_id = $product->save();

		if ( is_wp_error( $product_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $product_id->get_error_message(),
				),
				400
			);
		}

		// Log the activity.
		/* translators: %1$s is the product name, %2$d is the product ID. */
		Activity_Log_Helper::add_log_entry( 'Products', sprintf( __( 'Product Created: %1$s (Product ID: %2$d)', 'sg-ai-studio' ), $product->get_name(), $product->get_id() ) );

		// Clear all caches.
		if ( \function_exists( '\sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		// Return lean response for write operation, with the sale state so a schedule can be confirmed.
		return new WP_REST_Response(
			array_merge(
				array(
					'success'       => true,
					'id'            => $product->get_id(),
					'name'          => $product->get_name(),
					'status'        => $product->get_status(),
					'permalink'     => $product->get_permalink(),
					'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->format( 'c' ) : null,
					'sale_price'    => $product->get_sale_price(),
					'on_sale'       => $product->is_on_sale(),
				),
				$this->get_sale_date_fields( $product )
			),
			201
		);
	}

	/**
	 * Update an existing product
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_product( $request ) {
		// Check if powermode is enabled.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}
		$product_id = $request['id'];
		$product    = \wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid product ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		$result = $this->update_product_data( $product, $request );

		if ( is_wp_error( $result ) ) {
			return $this->get_invalid_product_data_response( $result );
		}

		$product->save();

		// Log the activity.
		/* translators: %1$s is the product name, %2$d is the product ID. */
		Activity_Log_Helper::add_log_entry( 'Products', sprintf( __( 'Product Updated: %1$s (Product ID: %2$d)', 'sg-ai-studio' ), $product->get_name(), $product->get_id() ) );

		// Clear all caches.
		if ( \function_exists( '\sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		// Return lean response for write operation, with the sale state so a schedule can be confirmed.
		return new WP_REST_Response(
			array_merge(
				array(
					'success'       => true,
					'id'            => $product->get_id(),
					'name'          => $product->get_name(),
					'status'        => $product->get_status(),
					'permalink'     => $product->get_permalink(),
					'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->format( 'c' ) : null,
					'sale_price'    => $product->get_sale_price(),
					'on_sale'       => $product->is_on_sale(),
				),
				$this->get_sale_date_fields( $product )
			),
			200
		);
	}

	/**
	 * Delete a product
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_product( $request ) {
		// Check if powermode is enabled.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}
		$product_id = $request['id'];
		$force      = isset( $request['force'] ) ? \SG_AI_Studio\Helper\Helper::validate_force_param( $request['force'] ) : false;
		$product    = \wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid product ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		$product_name = $product->get_name();

		if ( $force ) {
			$result = $product->delete( true );
		} else {
			wp_trash_post( $product_id );
			$result = 'trash' === get_post_status( $product_id );
		}

		if ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'The product could not be deleted.', 'sg-ai-studio' ),
				),
				500
			);
		}

		// Log the activity.
		if ( $force ) {
			/* translators: %1$s is the product name, %2$d is the product ID. */
			Activity_Log_Helper::add_log_entry( 'Products', sprintf( __( 'Product Permanently Deleted: %1$s (Product ID: %2$d)', 'sg-ai-studio' ), $product_name, $product_id ) );
		} else {
			/* translators: %1$s is the product name, %2$d is the product ID. */
			Activity_Log_Helper::add_log_entry( 'Products', sprintf( __( 'Product Moved to Trash: %1$s (Product ID: %2$d)', 'sg-ai-studio' ), $product_name, $product_id ) );
		}

		// Clear all caches.
		if ( \function_exists( '\sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		// Return lean response for delete operation.
		return new WP_REST_Response(
			array(
				'success' => true,
				'id'      => $product_id,
				'status'  => $force ? 'deleted' : 'trashed',
			),
			200
		);
	}

	/**
	 * Get a list of products
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_products( $request ) {
		$args = array(
			'status'  => $request['status'],
			'limit'   => $request['per_page'],
			'page'    => $request['page'],
			'orderby' => $request['orderby'],
			'order'   => $request['order'],
		);

		// WP_Query searches via `s`; `search` is not a recognised query var and would be dropped.
		if ( isset( $request['search'] ) && '' !== trim( (string) $request['search'] ) ) {
			$args['s'] = $request['search'];
		}

		if ( ! empty( $request['include'] ) ) {
			$args['include'] = array_values( array_filter( array_map( 'absint', (array) $request['include'] ) ) );
		}

		if ( isset( $request['sku'] ) && ! empty( $request['sku'] ) ) {
			$args['sku'] = $request['sku'];
		}

		if ( isset( $request['featured'] ) ) {
			$args['featured'] = $request['featured'];
		}

		if ( isset( $request['category'] ) && ! empty( $request['category'] ) ) {
			$args = $this->add_term_query_arg( $args, $request['category'], 'category', 'product_category_id' );
		}

		if ( isset( $request['tag'] ) && ! empty( $request['tag'] ) ) {
			$args = $this->add_term_query_arg( $args, $request['tag'], 'tag', 'product_tag_id' );
		}

		if ( isset( $request['stock_status'] ) && ! empty( $request['stock_status'] ) ) {
			$args['stock_status'] = $request['stock_status'];
		}

		if ( isset( $request['type'] ) && ! empty( $request['type'] ) ) {
			$args['type'] = $request['type'];
		} else {
			$args['type'] = \SG_AI_Studio\Helper\Helper::get_all_product_types();
		}

		// Price filtering and non-native sort keys cannot be expressed as wc_get_products() query vars
		// (it discards any meta_query passed in), so they travel under a custom query var and are
		// applied to the generated WP_Query args by filter_products_query().
		$args['sg_ai_studio_list_query'] = array(
			'orderby'   => $request['orderby'],
			'order'     => $request['order'],
			'min_price' => isset( $request['min_price'] ) && is_numeric( $request['min_price'] ) ? (float) $request['min_price'] : null,
			'max_price' => isset( $request['max_price'] ) && is_numeric( $request['max_price'] ) ? (float) $request['max_price'] : null,
		);

		$products       = \wc_get_products( $args );
		$total_products = \wc_get_products(
			array_merge(
				$args,
				array(
					'limit'  => -1,
					'return' => 'ids',
				)
			)
		);

		$total_products = count( $total_products );

		$embed_terms = (bool) $request['embed_terms'];

		// Prime the object term cache once so embedding terms is a single query, not one per product.
		if ( $embed_terms && ! empty( $products ) ) {
			$product_ids = array();

			foreach ( $products as $product ) {
				$product_ids[] = $product->get_id();
			}

			update_object_term_cache( $product_ids, 'product' );
		}

		$data = array();
		foreach ( $products as $product ) {
			$data[] = $this->prepare_product_for_response( $product, 'list', $embed_terms );
		}

		$max_pages = ceil( $total_products / $request['per_page'] );

		$response = new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'products'    => $data,
					'total'       => $total_products,
					'page'        => $request['page'],
					'per_page'    => $request['per_page'],
					'total_pages' => $max_pages,
				),
			),
			200
		);

		return $response;
	}

	/**
	 * Add a category/tag filter to wc_get_products() args.
	 *
	 * Wc_get_products() matches `category`/`tag` by slug only, so numeric IDs are routed
	 * to the `product_category_id`/`product_tag_id` query vars instead.
	 *
	 * @param array  $args     Query args for wc_get_products().
	 * @param string $value    Requested value: an ID, a slug, or a comma-separated list of either.
	 * @param string $slug_key Query var that matches by slug.
	 * @param string $id_key   Query var that matches by term ID.
	 * @return array
	 */
	private function add_term_query_arg( $args, $value, $slug_key, $id_key ) {
		$terms = array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' );
		$ids   = array();
		$slugs = array();

		foreach ( $terms as $term ) {
			if ( ctype_digit( $term ) ) {
				$ids[] = (int) $term;
				continue;
			}

			$slugs[] = sanitize_title( $term );
		}

		// WooCommerce honours only one of the two keys, so resolve IDs to slugs when both are given.
		if ( ! empty( $ids ) && ! empty( $slugs ) ) {
			$taxonomy = 'category' === $slug_key ? 'product_cat' : 'product_tag';

			foreach ( $ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			}

			$ids = array();
		}

		if ( ! empty( $slugs ) ) {
			$args[ $slug_key ] = $slugs;
		} elseif ( ! empty( $ids ) ) {
			$args[ $id_key ] = $ids;
		}

		return $args;
	}

	/**
	 * Apply price range and sort order to the WP_Query args generated by wc_get_products().
	 *
	 * Hooked to `woocommerce_product_data_store_cpt_get_products_query`. Only acts on queries
	 * issued by get_products(), which carry the `sg_ai_studio_list_query` query var.
	 *
	 * @param array $wp_query_args WP_Query args built by the WooCommerce product data store.
	 * @param array $query_vars    Query vars passed to wc_get_products().
	 * @return array
	 */
	public function filter_products_query( $wp_query_args, $query_vars ) {
		if ( empty( $query_vars['sg_ai_studio_list_query'] ) || ! is_array( $query_vars['sg_ai_studio_list_query'] ) ) {
			return $wp_query_args;
		}

		// WooCommerce copies unknown query vars into the WP_Query args; this one is ours only.
		unset( $wp_query_args['sg_ai_studio_list_query'] );

		$list_query = $query_vars['sg_ai_studio_list_query'];

		$order = 'asc' === strtolower( (string) $list_query['order'] ) ? 'ASC' : 'DESC';

		$meta_sort_keys = array(
			'price'      => '_price',
			'popularity' => 'total_sales',
			'rating'     => '_wc_average_rating',
		);

		switch ( $list_query['orderby'] ) {
			case 'id':
				$wp_query_args['orderby'] = 'ID';
				break;
			case 'slug':
				$wp_query_args['orderby'] = 'name';
				break;
			case 'include':
				$wp_query_args['orderby'] = 'post__in';
				break;
			case 'price':
			case 'popularity':
			case 'rating':
				$wp_query_args['meta_key'] = $meta_sort_keys[ $list_query['orderby'] ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$wp_query_args['orderby']  = array(
					'meta_value_num' => $order,
					'ID'             => $order,
				);
				break;
		}

		$wp_query_args['order'] = $order;

		$min_price = $list_query['min_price'];
		$max_price = $list_query['max_price'];

		if ( null !== $min_price || null !== $max_price ) {
			$price_clause = array(
				'key'  => '_price',
				'type' => 'DECIMAL(19,4)',
			);

			$price_clause['value']   = $max_price;
			$price_clause['compare'] = '<=';

			if ( null !== $min_price && null !== $max_price ) {
				$price_clause['value']   = array( $min_price, $max_price );
				$price_clause['compare'] = 'BETWEEN';
			} elseif ( null !== $min_price ) {
				$price_clause['value']   = $min_price;
				$price_clause['compare'] = '>=';
			}

			if ( ! isset( $wp_query_args['meta_query'] ) || ! is_array( $wp_query_args['meta_query'] ) ) {
				$wp_query_args['meta_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}

			$wp_query_args['meta_query'][] = $price_clause;
		}

		return $wp_query_args;
	}

	/**
	 * Get a single product
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_product( $request ) {
		$product_id = $request['id'];
		$product    = \wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid product ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		$response = $this->prepare_product_for_response( $product, 'view', (bool) $request['embed_terms'] );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $response,
			),
			200
		);
	}

	/**
	 * Get the variations of a variable product
	 *
	 * Returns every variation, including ones hidden from the parent's price range, so the
	 * response can answer per variation stock and attribute questions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_product_variations( $request ) {
		$product = \wc_get_product( $request['id'] );

		if ( ! $product ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'product_not_found',
					'message' => __( 'Invalid product ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'not_variable_product',
					'message' => __( 'This product is not a variable product and has no variations.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$data = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = \wc_get_product( $variation_id );

			if ( ! $variation ) {
				continue;
			}

			$data[] = array(
				'id'             => $variation->get_id(),
				'sku'            => $variation->get_sku(),
				'price'          => $this->format_price( $variation->get_price() ),
				'regular_price'  => $this->format_price( $variation->get_regular_price() ),
				'sale_price'     => $this->format_price( $variation->get_sale_price() ),
				'on_sale'        => $variation->is_on_sale(),
				'stock_status'   => $variation->get_stock_status(),
				'stock_quantity' => $variation->get_stock_quantity(),
				'manage_stock'   => $variation->get_manage_stock(),
				// Cast so a variation with no attributes serialises as `{}`, not `[]`.
				'attributes'     => (object) $variation->get_attributes(),
				'image_id'       => $variation->get_image_id(),
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Update product data from request
	 *
	 * Validation runs before any setter, so a rejected request leaves the product untouched.
	 *
	 * @param \WC_Product     $product The product object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True on success, WP_Error when the request is invalid.
	 */
	protected function update_product_data( $product, $request ) {
		$sale_dates = $this->prepare_sale_dates( $product, $request );

		if ( is_wp_error( $sale_dates ) ) {
			return $sale_dates;
		}

		$fields = array(
			'name',
			'description',
			'short_description',
			'sku',
			'regular_price',
			'sale_price',
			'status',
			'catalog_visibility',
			'featured',
			'virtual',
			'downloadable',
			'manage_stock',
			'stock_quantity',
			'stock_status',
			'weight',
			'price',
		);

		foreach ( $fields as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$method = 'set_' . $field;
				if ( method_exists( $product, $method ) ) {
					$product->$method( $request[ $field ] );
				}
			}
		}

		if ( isset( $request['dimensions'] ) && is_array( $request['dimensions'] ) ) {
			$dimensions = $request['dimensions'];
			if ( isset( $dimensions['length'] ) ) {
				$product->set_length( $dimensions['length'] );
			}
			if ( isset( $dimensions['width'] ) ) {
				$product->set_width( $dimensions['width'] );
			}
			if ( isset( $dimensions['height'] ) ) {
				$product->set_height( $dimensions['height'] );
			}
		}

		if ( isset( $request['categories'] ) && is_array( $request['categories'] ) ) {
			$product->set_category_ids( $request['categories'] );
		}

		if ( isset( $request['tags'] ) && is_array( $request['tags'] ) ) {
			$product->set_tag_ids( $request['tags'] );
		}

		if ( isset( $request['images'] ) && is_array( $request['images'] ) ) {
			$this->set_product_images( $product, $request['images'] );
		}

		// WooCommerce recalculates the active price on save and its scheduled sales cron
		// starts and ends the sale, so only the dates are set here.
		if ( array_key_exists( 'from', $sale_dates ) ) {
			$product->set_date_on_sale_from( $sale_dates['from'] );
		}
		if ( array_key_exists( 'to', $sale_dates ) ) {
			$product->set_date_on_sale_to( $sale_dates['to'] );
		}

		return true;
	}

	/**
	 * Parse and validate the scheduled sale dates of a request.
	 *
	 * @param \WC_Product     $product The product object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Map with a 'from' and/or 'to' key (Unix timestamp, or null to clear) for
	 *                        each date that was sent, or WP_Error when the request is invalid.
	 */
	protected function prepare_sale_dates( $product, $request ) {
		$dates = array();

		foreach ( array( 'from', 'to' ) as $edge ) {
			$field = 'date_on_sale_' . $edge;

			// The UTC variant wins when both are sent.
			if ( $request->has_param( $field . '_gmt' ) ) {
				$field   .= '_gmt';
				$timezone = new \DateTimeZone( 'UTC' );
			} elseif ( $request->has_param( $field ) ) {
				$timezone = wp_timezone();
			} else {
				continue;
			}

			$timestamp = $this->parse_sale_date( $request->get_param( $field ), $timezone, 'to' === $edge );

			if ( false === $timestamp ) {
				return new WP_Error(
					'invalid_sale_date',
					/* translators: %s is the name of the request field. */
					sprintf( __( 'Invalid date in %s. Use ISO 8601, e.g. 2026-11-30 or 2026-11-30T23:59:59.', 'sg-ai-studio' ), $field ),
					array( 'field' => $field )
				);
			}

			$dates[ $edge ] = $timestamp;
		}

		$sends_sale_price = $request->has_param( 'sale_price' );

		if ( empty( $dates ) && ! $sends_sale_price ) {
			return $dates;
		}

		// A variable parent has no price of its own, WooCommerce syncs it from the variations.
		if ( $product->is_type( 'variable' ) ) {
			return new WP_Error(
				'variable_product_sale',
				__( 'Sale prices and sale dates cannot be set on a variable product. Set them on each of its variations instead.', 'sg-ai-studio' )
			);
		}

		$from = array_key_exists( 'from', $dates ) ? $dates['from'] : $this->get_sale_date_timestamp( $product->get_date_on_sale_from( 'edit' ) );
		$to   = array_key_exists( 'to', $dates ) ? $dates['to'] : $this->get_sale_date_timestamp( $product->get_date_on_sale_to( 'edit' ) );

		if ( null !== $from && null !== $to && $to < $from ) {
			return new WP_Error(
				'invalid_sale_dates',
				__( 'date_on_sale_to must not be before date_on_sale_from.', 'sg-ai-studio' ),
				array( 'field' => 'date_on_sale_to' )
			);
		}

		$sale_price = isset( $request['sale_price'] ) ? (string) $request['sale_price'] : (string) $product->get_sale_price( 'edit' );
		$sets_date  = ( isset( $dates['from'] ) || isset( $dates['to'] ) );

		if ( $sets_date && '' === $sale_price ) {
			return new WP_Error(
				'sale_price_required',
				__( 'A sale date needs a sale_price. Send sale_price in the same request or set it first.', 'sg-ai-studio' ),
				array( 'field' => 'sale_price' )
			);
		}

		return $dates;
	}

	/**
	 * Parse one scheduled sale date.
	 *
	 * A date without a time covers the whole day, the same as in the WooCommerce product editor:
	 * a start date begins at 00:00:00 and an end date runs until 23:59:59.
	 *
	 * @param mixed         $value    Raw request value.
	 * @param \DateTimeZone $timezone Timezone of a value that carries no offset.
	 * @param bool          $is_end   Whether the value is the end of the sale.
	 * @return int|null|false Unix timestamp, null to clear the date, or false when the value is invalid.
	 */
	protected function parse_sale_date( $value, $timezone, $is_end ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[Tt ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(Z|z|[+-]\d{2}:?\d{2})?)?$/', $value, $matches ) ) {
			return false;
		}

		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return false;
		}

		if ( ! isset( $matches[4] ) ) {
			$value .= $is_end ? ' 23:59:59' : ' 00:00:00';
		} elseif ( (int) $matches[4] > 23 || (int) $matches[5] > 59 || ( isset( $matches[6] ) && (int) $matches[6] > 59 ) ) {
			return false;
		}

		try {
			// An offset in the value takes precedence over $timezone.
			$date = new \DateTimeImmutable( $value, $timezone );
		} catch ( \Exception $e ) {
			return false;
		}

		return $date->getTimestamp();
	}

	/**
	 * Get the Unix timestamp of a stored sale date.
	 *
	 * @param \WC_DateTime|null $date Stored date.
	 * @return int|null Timestamp, or null when no date is stored.
	 */
	private function get_sale_date_timestamp( $date ) {
		return $date ? $date->getTimestamp() : null;
	}

	/**
	 * Format the scheduled sale dates of a product for a response.
	 *
	 * @param \WC_Product $product The product object.
	 * @return array Sale date fields in the site timezone and in UTC, null when not scheduled.
	 */
	private function get_sale_date_fields( $product ) {
		$fields = array();

		foreach ( array( 'from', 'to' ) as $edge ) {
			$getter = 'get_date_on_sale_' . $edge;
			$date   = $product->$getter( 'edit' );

			$fields[ 'date_on_sale_' . $edge ]          = $date ? $date->format( 'c' ) : null;
			$fields[ 'date_on_sale_' . $edge . '_gmt' ] = $date ? gmdate( 'c', $date->getTimestamp() ) : null;
		}

		return $fields;
	}

	/**
	 * Build the error response for a rejected product write.
	 *
	 * @param WP_Error $error The validation error.
	 * @return WP_REST_Response
	 */
	private function get_invalid_product_data_response( $error ) {
		$data = array(
			'success' => false,
			'error'   => $error->get_error_code(),
			'message' => $error->get_error_message(),
		);

		$error_data = $error->get_error_data();
		if ( isset( $error_data['field'] ) ) {
			$data['field'] = $error_data['field'];
		}

		return new WP_REST_Response( $data, 400 );
	}

	/**
	 * Set product images
	 *
	 * @param \WC_Product $product The product object.
	 * @param array       $images Array of image data.
	 * @return void
	 */
	protected function set_product_images( $product, $images ) {
		$gallery_ids = array();

		foreach ( $images as $index => $image ) {
			$attachment_id = null;

			// If image has an ID, use it.
			if ( isset( $image['id'] ) && ! empty( $image['id'] ) ) {
				$attachment_id = absint( $image['id'] );
			} elseif ( isset( $image['src'] ) && ! empty( $image['src'] ) ) {
				// Upload the image from URL.
				$attachment_id = $this->upload_image_from_url( $image['src'], $product->get_id() );
			}

			if ( $attachment_id ) {
				// Set image alt text if provided.
				if ( isset( $image['alt'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $image['alt'] ) );
				}

				// Set image name if provided.
				if ( isset( $image['name'] ) ) {
					wp_update_post(
						array(
							'ID'         => $attachment_id,
							'post_title' => sanitize_text_field( $image['name'] ),
						)
					);
				}

				// First image or position 0 is the featured image.
				if ( 0 === $index || ( isset( $image['position'] ) && 0 === absint( $image['position'] ) ) ) {
					$product->set_image_id( $attachment_id );
				} else {
					$gallery_ids[] = $attachment_id;
				}
			}
		}

		// Set gallery images.
		if ( ! empty( $gallery_ids ) ) {
			$product->set_gallery_image_ids( $gallery_ids );
		}
	}

	/**
	 * Upload image from URL
	 *
	 * @param string $image_url Image URL.
	 * @param int    $product_id Product ID.
	 * @return int|false Attachment ID or false on failure.
	 */
	protected function upload_image_from_url( $image_url, $product_id ) {
		// Validate the URL to prevent SSRF before making any outbound request.
		if ( ! Helper::is_safe_remote_url( $image_url ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the image.
		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		// Get the filename and extension.
		$file_array = array(
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
		);

		// Upload the image.
		$attachment_id = media_handle_sideload( $file_array, $product_id );

		// Clean up temp file.
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $file_array['tmp_name'] );
			return false;
		}

		return $attachment_id;
	}

	/**
	 * Get arguments for batch operations
	 *
	 * @return array
	 */
	protected function get_batch_args() {
		return array(
			'create' => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'object' ),
				'description' => 'Array of products to create. Each item accepts the same fields as creating a single product, including sale_price, date_on_sale_from and date_on_sale_to for a scheduled sale.',
				'required'    => false,
			),
			'update' => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'object' ),
				'description' => 'Array of products to update. Each item needs an id and accepts the same fields as updating a single product, including sale_price, date_on_sale_from and date_on_sale_to for a scheduled sale.',
				'required'    => false,
			),
			'delete' => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'integer' ),
				'description' => 'Array of product IDs to delete.',
				'required'    => false,
			),
			'force' => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Whether to permanently delete products (true) or move to trash (false).',
				'required'    => false,
			),
		);
	}

	/**
	 * Get batch schema
	 *
	 * @return array
	 */
	public function get_batch_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'batch',
			'type'       => 'object',
			'properties' => array(
				'success' => array(
					'description' => 'Whether the batch operation was successful.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'data'    => array(
					'description' => 'Data returned by the batch operation.',
					'type'        => 'object',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Process batch create operations
	 *
	 * @param array $items Items to create.
	 * @return array Array with 'results' and 'errors' keys.
	 */
	protected function process_batch_creates( $items ) {
		if ( empty( $items ) ) {
			return array(
				'results' => array(),
				'errors'  => array(),
			);
		}

		$results = array();
		$errors  = array();

		foreach ( $items as $key => $product_data ) {
			// Create a new request for each product.
			$sub_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/' . $this->base );

			// Add product data to the request.
			foreach ( $product_data as $param_key => $param_value ) {
				$sub_request->set_param( $param_key, $param_value );
			}

			// Create the product.
			$response = $this->create_product( $sub_request );

			if ( $response->is_error() || ! $response->get_data()['success'] ) {
				$errors[ $key ] = $response->get_data();
			} else {
				$results[ $key ] = $this->get_batch_item_result( $response );
			}
		}

		return array(
			'results' => $results,
			'errors'  => $errors,
		);
	}

	/**
	 * Process batch update operations
	 *
	 * @param array $items Items to update.
	 * @return array Array with 'results' and 'errors' keys.
	 */
	protected function process_batch_updates( $items ) {
		if ( empty( $items ) ) {
			return array(
				'results' => array(),
				'errors'  => array(),
			);
		}

		$results = array();
		$errors  = array();

		foreach ( $items as $key => $product_data ) {
			if ( ! isset( $product_data['id'] ) ) {
				$errors[ $key ] = array(
					'success' => false,
					'message' => __( 'Product ID is required for updating products.', 'sg-ai-studio' ),
				);
				continue;
			}

			// Create a new request for each product.
			$sub_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/' . $this->base . '/' . $product_data['id'] );

			// Add product data to the request.
			foreach ( $product_data as $param_key => $param_value ) {
				$sub_request->set_param( $param_key, $param_value );
			}

			// Update the product.
			$response = $this->update_product( $sub_request );

			if ( $response->is_error() || ! $response->get_data()['success'] ) {
				$errors[ $key ] = $response->get_data();
			} else {
				$results[ $key ] = $this->get_batch_item_result( $response );
			}
		}

		return array(
			'results' => $results,
			'errors'  => $errors,
		);
	}

	/**
	 * Strip the envelope from a create or update response so it can be reported as a batch item.
	 *
	 * @param WP_REST_Response $response Response of create_product() or update_product().
	 * @return array Item data.
	 */
	private function get_batch_item_result( $response ) {
		$data = $response->get_data();
		unset( $data['success'] );

		return $data;
	}

	/**
	 * Process batch delete operations
	 *
	 * @param array $ids Product IDs to delete.
	 * @param bool  $force Whether to permanently delete (true) or move to trash (false).
	 * @return array Array with 'results' and 'errors' keys.
	 */
	protected function process_batch_deletes( $ids, $force = false ) {
		if ( empty( $ids ) ) {
			return array(
				'results' => array(),
				'errors'  => array(),
			);
		}

		$results = array();
		$errors  = array();

		foreach ( $ids as $product_id ) {
			// Create a new request for each product.
			$sub_request = new WP_REST_Request( 'DELETE', '/' . $this->namespace . '/' . $this->base . '/' . $product_id );
			$sub_request->set_param( 'id', $product_id );
			$sub_request->set_param( 'force', $force );

			// Delete the product.
			$response = $this->delete_product( $sub_request );

			if ( $response->is_error() || ! $response->get_data()['success'] ) {
				$errors[ $product_id ] = $response->get_data();
			} else {
				$results[ $product_id ] = array(
					'id'     => $product_id,
					'status' => $response->get_data()['status'],
				);
			}
		}

		return array(
			'results' => $results,
			'errors'  => $errors,
		);
	}

	/**
	 * Batch operations (create, update, delete)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function batch_operations( $request ) {
		// Check if powermode is enabled.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$body = $request->get_json_params();

		$create_items = isset( $body['create'] ) ? $body['create'] : array();
		$update_items = isset( $body['update'] ) ? $body['update'] : array();
		$delete_ids   = isset( $body['delete'] ) ? $body['delete'] : array();
		$force        = isset( $body['force'] ) ? $body['force'] : false;

		// Process operations.
		$create_result = $this->process_batch_creates( $create_items );
		$update_result = $this->process_batch_updates( $update_items );
		$delete_result = $this->process_batch_deletes( $delete_ids, $force );

		// Check if all operations succeeded.
		$all_errors = array_merge(
			$create_result['errors'],
			$update_result['errors'],
			$delete_result['errors']
		);
		$success = empty( $all_errors );

		// Clear all caches.
		if ( \function_exists( '\sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		// Determine HTTP status.
		$has_creates = ! empty( $create_result['results'] );
		$http_status = $success ? ( $has_creates ? 201 : 200 ) : 207;

		return new WP_REST_Response(
			array(
				'success' => $success,
				'data'    => array(
					'create' => array_values( $create_result['results'] ),
					'update' => array_values( $update_result['results'] ),
					'delete' => array_values( $delete_result['results'] ),
					'errors' => array(
						'create' => $create_result['errors'],
						'update' => $update_result['errors'],
						'delete' => $delete_result['errors'],
					),
				),
			),
			$http_status
		);
	}

	/**
	 * Prepare a product for the response
	 *
	 * @param \WC_Product $product     Product object.
	 * @param string      $context     Request context: 'view' for single reads (full fidelity)
	 *                                 or 'list' for collection responses (heavy fields omitted).
	 * @param bool        $embed_terms Whether to add a named terms map for every taxonomy on the product.
	 * @return array Prepared product data.
	 */
	protected function prepare_product_for_response( $product, $context = 'view', $embed_terms = false ) {
		$categories = array();
		foreach ( $product->get_category_ids() as $category_id ) {
			$category = get_term( $category_id, 'product_cat' );
			if ( $category && ! is_wp_error( $category ) ) {
				$categories[] = array(
					'id'   => $category->term_id,
					'name' => $category->name,
					'slug' => $category->slug,
				);
			}
		}

		$tags = array();
		foreach ( $product->get_tag_ids() as $tag_id ) {
			$tag = get_term( $tag_id, 'product_tag' );
			if ( $tag && ! is_wp_error( $tag ) ) {
				$tags[] = array(
					'id'   => $tag->term_id,
					'name' => $tag->name,
					'slug' => $tag->slug,
				);
			}
		}

		$images = array();
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$attachment = get_post( $image_id );
			$images[] = array(
				'id'            => $image_id,
				'date_created'  => $attachment ? gmdate( 'c', strtotime( $attachment->post_date_gmt ) ) : null,
				'date_modified' => $attachment ? gmdate( 'c', strtotime( $attachment->post_modified_gmt ) ) : null,
				'src'           => wp_get_attachment_url( $image_id ),
				'name'          => $attachment ? $attachment->post_title : '',
				'alt'           => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
				'position'      => 0,
			);
		}

		$gallery_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_ids as $position => $gallery_id ) {
			$attachment = get_post( $gallery_id );
			$images[] = array(
				'id'            => $gallery_id,
				'date_created'  => $attachment ? gmdate( 'c', strtotime( $attachment->post_date_gmt ) ) : null,
				'date_modified' => $attachment ? gmdate( 'c', strtotime( $attachment->post_modified_gmt ) ) : null,
				'src'           => wp_get_attachment_url( $gallery_id ),
				'name'          => $attachment ? $attachment->post_title : '',
				'alt'           => get_post_meta( $gallery_id, '_wp_attachment_image_alt', true ),
				'position'      => $position + 1,
			);
		}

		$data = array(
			'id'                 => $product->get_id(),
			'name'               => $product->get_name(),
			'slug'               => $product->get_slug(),
			'permalink'          => $product->get_permalink(),
			'date_created'       => $product->get_date_created() ? $product->get_date_created()->format( 'c' ) : null,
			'date_modified'      => $product->get_date_modified() ? $product->get_date_modified()->format( 'c' ) : null,
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'featured'           => $product->get_featured(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'description'        => $product->get_description(),
			'short_description'  => $product->get_short_description(),
			'sku'                => $product->get_sku(),
			'price'              => $product->get_price(),
			'regular_price'      => $product->get_regular_price(),
			'sale_price'         => $product->get_sale_price(),
			'price_html'         => $product->get_price_html(),
			'on_sale'            => $product->is_on_sale(),
			'purchasable'        => $product->is_purchasable(),
			'total_sales'        => $product->get_total_sales(),
			'virtual'            => $product->get_virtual(),
			'downloadable'       => $product->get_downloadable(),
			'manage_stock'       => $product->get_manage_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'stock_status'       => $product->get_stock_status(),
			'backorders'         => $product->get_backorders(),
			'backorders_allowed' => $product->backorders_allowed(),
			'backordered'        => $product->is_on_backorder(),
			'sold_individually'  => $product->get_sold_individually(),
			'weight'             => $product->get_weight(),
			'dimensions'         => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			),
			'shipping_required'  => $product->needs_shipping(),
			'shipping_taxable'   => $product->is_shipping_taxable(),
			'shipping_class'     => $product->get_shipping_class(),
			'shipping_class_id'  => $product->get_shipping_class_id(),
			'reviews_allowed'    => $product->get_reviews_allowed(),
			'average_rating'     => $product->get_average_rating(),
			'rating_count'       => $product->get_rating_count(),
			'related_ids'        => \wc_get_related_products( $product->get_id() ),
			'upsell_ids'         => $product->get_upsell_ids(),
			'cross_sell_ids'     => $product->get_cross_sell_ids(),
			'parent_id'          => $product->get_parent_id(),
			'purchase_note'      => $product->get_purchase_note(),
			'categories'         => $categories,
			'tags'               => $tags,
			'images'             => $images,
			'menu_order'         => $product->get_menu_order(),
		);

		$data             = array_merge( $data, $this->get_sale_date_fields( $product ) );
		$data['currency'] = get_woocommerce_currency();

		if ( $product->is_type( 'variable' ) ) {
			$min = $product->get_variation_price( 'min' );
			$max = $product->get_variation_price( 'max' );

			// WooCommerce caches variation prices with the store's decimal precision while the
			// parent's own _price meta is stored unpadded, so re-format both to one format.
			$data['price']         = $this->format_price( $data['price'] );
			$data['regular_price'] = $this->format_price( $data['regular_price'] );
			$data['sale_price']    = $this->format_price( $data['sale_price'] );

			$data['price_min']         = $this->format_price( $min );
			$data['price_max']         = $this->format_price( $max );
			$data['regular_price_min'] = $this->format_price( $product->get_variation_regular_price( 'min' ) );
			$data['regular_price_max'] = $this->format_price( $product->get_variation_regular_price( 'max' ) );
			$data['price_is_range']    = ( $min !== $max );
			$data['variation_ids']     = $product->get_children();

			// array_values() keeps every option list a JSON array: WooCommerce builds these with
			// array_unique(), which preserves keys and would otherwise serialise as an object.
			// The map itself is cast to an object so it stays `{}` rather than `[]` when empty.
			$attributes = array();
			foreach ( $product->get_variation_attributes() as $name => $values ) {
				$attributes[ $name ] = array_values( $values );
			}
			$data['variation_attributes'] = (object) $attributes;
		}

		if ( $product->is_type( 'grouped' ) ) {
			$data['grouped_products'] = $product->get_children();

			// A grouped parent carries no price of its own, so the range costs one load per
			// child. Single reads only, to keep collection responses cheap.
			if ( 'view' === $context ) {
				$data = array_merge( $data, $this->get_grouped_price_range( $product ) );
			}
		}

		if ( $embed_terms ) {
			$data['terms'] = $this->get_object_terms_map( $product->get_id(), 'product' );
		}

		// Omit heavy fields in list context to keep collection responses small.
		// Single reads (context 'view') retain full content.
		if ( 'list' === $context ) {
			unset( $data['description'], $data['short_description'] );
		}

		return $data;
	}

	/**
	 * Normalize a price value to the store's decimal precision.
	 *
	 * WooCommerce stores a variable parent's `_price` unpadded ("45") but caches its variation
	 * prices through `wc_format_decimal( $price, wc_get_price_decimals() )` ("45.00"), so the
	 * same amount can reach the response in two formats. Empty values are passed through as an
	 * empty string: `get_variation_price()` returns false when no variation carries a price, and
	 * `wc_format_decimal( false, 2 )` would report that as "0.00".
	 *
	 * @param string|float|bool|null $value Raw price value.
	 * @return string The formatted price, or an empty string when there is no price.
	 */
	private function format_price( $value ) {
		if ( '' === $value || null === $value || false === $value ) {
			return '';
		}

		return wc_format_decimal( $value, wc_get_price_decimals() );
	}

	/**
	 * Build the price range of a grouped product from its children.
	 *
	 * @param \WC_Product $product Grouped product object.
	 * @return array Price range fields: price_min, price_max and price_is_range.
	 */
	private function get_grouped_price_range( $product ) {
		$children = $product->get_children();
		$prices   = array();

		if ( ! empty( $children ) ) {
			$child_products = \wc_get_products(
				array(
					'include' => $children,
					'limit'   => -1,
					'type'    => \SG_AI_Studio\Helper\Helper::get_all_product_types(),
				)
			);

			foreach ( $child_products as $child ) {
				$price = $child->get_price();

				if ( '' === $price || null === $price ) {
					continue;
				}

				$prices[] = (float) $price;
			}
		}

		if ( empty( $prices ) ) {
			return array(
				'price_min'      => '',
				'price_max'      => '',
				'price_is_range' => false,
			);
		}

		$min = $this->format_price( min( $prices ) );
		$max = $this->format_price( max( $prices ) );

		return array(
			'price_min'      => $min,
			'price_max'      => $max,
			'price_is_range' => ( $min !== $max ),
		);
	}
}
