<?php
/**
 * ILM Guide utilities for Terraso
 *
 * @package Terraso
 */

/**
 * Holds methods for ILM Guide content.
 * Class ILM_Guide
 */
class ILM_Guide {
	const POST_TYPE         = 'guide';
	const TAG_TAXONOMY      = 'ilm_tag';
	const TYPE_TAXONOMY     = 'ilm_type';
	const TOOL_URL_META_KEY = 'ilm_url';
	const POST_LIMIT        = 90;

	/**
	 * Subset of SVG tags/attributes we use.
	 *
	 * @var $svg_tags
	 */
	public static $svg_tags = [
		'svg'  => [ 'class', 'id', 'style', 'width', 'height', 'fill', 'xmlns', 'xmlns:xlink', 'xmlns:serif', 'xml:space', 'viewbox' ], // viewbox must be lowercase.
		'rect' => [ 'class', 'id', 'style', 'width', 'height', 'fill', 'rx', 'x', 'y' ],
		'path' => [ 'class', 'id', 'style', 'fill', 'd', 'stroke', 'stroke-linecap', 'stroke-linejoin', 'stroke-width' ],
		'g'    => [ 'class', 'id', 'style', 'transform' ],
	];

	/**
	 * Add actions and filters.
	 */
	public static function hooks() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'zakra_after_single_post_content', [ __CLASS__, 'after_single_post_content' ] );
		add_action( 'et_after_post', [ __CLASS__, 'after_single_post_content' ] );
		add_filter( 'et_builder_post_types', [ __CLASS__, 'et_builder_post_types' ] );
		add_action( 'init', [ __CLASS__, 'guide_rewrite' ] );
		add_action( 'init', [ __CLASS__, 'allow_svg_tags' ] );
		add_filter( 'safe_style_css', [ __CLASS__, 'allow_svg_css' ] );
		add_filter( 'manage_guide_posts_columns', [ __CLASS__, 'guide_admin_columns' ] );
		add_action( 'manage_guide_posts_custom_column', [ __CLASS__, 'guide_type_column_content' ], 10, 2 );
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomy' ] );
		add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );
	}

	/**
	 * Add the date ILM Type column.
	 *
	 * @param array $defaults default settings for the columns.
	 */
	public static function guide_admin_columns( $defaults ) {
		unset( $defaults['date'] );
		$defaults['ilm_type'] = __( 'Type' );
		$defaults['date']     = __( 'Date' );

		return $defaults;
	}

	/**
	 * Add column content for ILM Type column.
	 *
	 * @param string  $column_name  Column name.
	 * @param integer $post_id     Post ID.
	 */
	public static function guide_type_column_content( $column_name, $post_id ) {
		if ( 'ilm_type' === $column_name ) {
			$post_type = substr( self::get_post_type( $post_id ), 4 );
			echo esc_html( ucwords( $post_type ) );
		}
	}

	/**
	 * Add actions and filters.
	 */
	public static function late_hooks() {
		if ( self::POST_TYPE !== get_post_type() ) {
			return;
		}

		$ext = defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ? 'src' : 'min';

		wp_enqueue_style( 'ilm-guide', plugins_url( "assets/css/ilm-guide.{$ext}.css", __DIR__ ), [], ILM_GUIDE_VERSION );

		if ( 'ilm-output' === self::get_post_type() ) {
			wp_enqueue_script( 'plausible-analytics-terraso', plugins_url( "assets/js/plausible.{$ext}.js", __DIR__ ), [], ILM_GUIDE_VERSION, false );
		}

		// redirect tools pages to corresponding outputs.
		if ( 'ilm-tool' === self::get_post_type() ) {
			wp_safe_redirect( get_the_permalink( wp_get_post_parent_id() ) );
			exit();
		}

		add_filter( 'body_class', [ __CLASS__, 'filter_body_class' ] );
		add_filter( 'get_post_metadata', [ __CLASS__, 'disable_page_header' ], 10, 5 );
		add_filter( 'zakra_current_layout', [ __CLASS__, 'zakra_current_layout' ] );
		add_filter( 'the_content', [ __CLASS__, 'the_content' ] );
		remove_action( 'zakra_after_single_post_content', 'zakra_post_navigation', 10 );
	}

	/**
	 * Treat /guide/ as an index page.
	 */
	public static function guide_rewrite() {
		add_rewrite_rule( '^guide$', 'index.php?guide=intro', 'top' );
	}

	/**
	 * Allow SVG tags within WordPress
	 */
	public static function allow_svg_tags() {
		global $allowedposttags;

		foreach ( self::$svg_tags as $tag => $attributes ) {
			$allowedposttags[ $tag ] = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			foreach ( $attributes as $a ) {
				$allowedposttags[ $tag ][ $a ] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Allow inline styles needed for SVGs.
	 *
	 * @param array $styles   List of allowed style rules.
	 * @return array
	 */
	public static function allow_svg_css( $styles ) {
		$styles[] = 'fill';
		$styles[] = 'fill-rule';
		$styles[] = 'clip-rule';
		$styles[] = 'stroke-linejoin';
		$styles[] = 'stroke-miterlimit';

		return $styles;
	}

	/**
	 * Print breadcrumbs.
	 */
	public static function get_breadcrumbs() {
		return self::get_template_part(
			'template-parts/breadcrumbs-top.php'
		);
	}

	/**
	 * Gets ILM post type (output, element, tool, guide)
	 *
	 * @param integer $id      Post ID.
	 */
	public static function get_post_type( $id = null ) {
		if ( ! $id ) {
			$id = get_the_ID();
		}
		$post_terms = wp_get_post_terms( $id, self::TYPE_TAXONOMY, [ 'fields' => 'slugs' ] );
		if ( $post_terms && is_array( $post_terms ) ) {
			return $post_terms[0];
		}

		return 'ilm-' . self::POST_TYPE;
	}

	/**
	 * Gets HTML for ILM Guide section image (one of 5 elements).
	 */
	public static function get_section_image() {
		$post_type = self::get_post_type();

		if ( 'ilm-output' === $post_type ) {
			$slug = get_post_field( 'post_name', wp_get_post_parent_id() );
		} elseif ( 'ilm-element' === $post_type ) {
			$slug = get_post_field( 'post_name' );
		} else {
			return;
		}

		$svg = file_get_contents( plugin_dir_path( __DIR__ ) . 'assets/images/ilm/' . $slug . '.svg' );
		return '<span class="' . esc_attr( 'section-image ' . $slug ) . '">' . $svg . '</span>';
	}

	/**
	 * Adds the post name slug to the body class list.
	 *
	 * @param array $classes   List of CSS classes.
	 */
	public static function filter_body_class( $classes ) {
		$queried_obj = get_queried_object();

		if ( isset( $queried_obj->post_name ) && is_string( $queried_obj->post_name ) ) {
			$classes[] = 'guide-' . $queried_obj->post_name;
		}

		$post_type = self::get_post_type();
		$classes[] = 'guide-' . ( $post_type ?? 'intro' );
		if ( 'ilm-output' === $post_type ) {
			$parent_name = get_post_field( 'post_name', wp_get_post_parent_id() );
			$classes[]   = 'parent-' . $parent_name;
		}

		return $classes;
	}

	/**
	 * Disable the header (which is outside of .entry-content) so we can add our own header.
	 *
	 * @param mixed  $value     The value to return, either a single metadata value or an array
	 *                          of values depending on the value of `$single`. Default null.
	 * @param int    $object_id ID of the object metadata is for.
	 * @param string $meta_key  Metadata key.
	 * @param bool   $single    Whether to return only the first value of the specified `$meta_key`.
	 * @param string $meta_type Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user',
	 *                          or any other object type with an associated meta table.
	 */
	public static function disable_page_header( $value, $object_id, $meta_key, $single, $meta_type ) { // phpcs:ignore  Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Zakra.
		if ( 'zakra_page_header' === $meta_key ) {
			return '0';
		}

		// Divi.
		if ( '_et_pb_show_title' === $meta_key ) {
			return 'off';
		}

		if ( '_et_pb_use_builder' === $meta_key ) {
			return 'on';
		}

		if ( '_et_pb_page_layout' === $meta_key ) {
			return 'et_no_sidebar';
		}

		return $value;
	}

	/**
	 * Adds the meta box container to Guide CPT posts.
	 */
	public static function add_meta_boxes() {
		if ( class_exists( 'Zakra_Meta_Box_Page_Settings' ) ) {
			add_meta_box( 'zakra-page-setting', esc_html__( 'Page Settings', 'zakra' ), 'Zakra_Meta_Box_Page_Settings::render', [ self::POST_TYPE ] );
		}
	}

	/**
	 * Get the first paragraph of post content.
	 *
	 * @param string $content           Post content HTML.
	 */
	public static function get_first_paragraph( $content ) {
		if ( ! has_blocks( $content ) ) {
			return;
		}

		$blocks = parse_blocks( $content );
		return $blocks[0]['innerHTML'];
	}

	/**
	 * For ILM Guide, set layout to stretched to hide sidebar and allow for customization.
	 *
	 * @param string $layout           Zakra layout name.
	 */
	public static function zakra_current_layout( $layout ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return 'tg-site-layout--stretched';
	}

	/**
	 * Append ILM Guide output and tool content.
	 */
	public static function after_single_post_content() {
		$post_type = self::get_post_type();

		$result = '';
		if ( 'ilm-element' === $post_type ) {
			$result = self::get_template_part(
				'template-parts/output-list.php',
			);
		} elseif ( 'ilm-output' === $post_type ) {
			$result = self::get_template_part(
				'template-parts/tool-list.php',
			);
		}

		echo wp_kses_post( $result );
	}

	/**
	 * Prepend title to ILM content.
	 *
	 * @param string $content           Post content HTML.
	 */
	public static function the_content( $content ) {
		$result = '';
		if ( in_array( self::get_post_type(), [ 'ilm-element', 'ilm-output' ], true ) ) {
			$result = self::get_breadcrumbs() . '<h1>' . self::get_section_image() . esc_html( get_the_title() ) . '</h1>';
		}
		return $result . $content;
	}

	/**
	 * Register ILM Guide post type.
	 */
	public static function register_post_type() {
		/**
		 * Post Type: Guide Entries.
		 */

		$labels = [
			'name'          => esc_html__( 'Guide Entries', 'terraso' ),
			'singular_name' => esc_html__( 'Guide Entry', 'terraso' ),
			'menu_name'     => esc_html__( 'ILM Guide', 'terraso' ),
		];

		$args = [
			'label'                 => esc_html__( 'Guide Entries', 'terraso' ),
			'labels'                => $labels,
			'description'           => '',
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_rest'          => true,
			'rest_base'             => '',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'rest_namespace'        => 'wp/v2',
			'has_archive'           => false,
			'show_in_menu'          => true,
			'show_in_nav_menus'     => true,
			'delete_with_user'      => false,
			'exclude_from_search'   => false,
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
			'hierarchical'          => true,
			'can_export'            => true,
			'rewrite'               => [
				'slug'       => 'guide',
				'with_front' => true,
			],
			'query_var'             => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-book',
			'supports'              => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes', 'revisions' ],
			'taxonomies'            => [ 'ilm_tag' ],
			'show_in_graphql'       => false,
		];

		register_post_type( self::POST_TYPE, $args ); // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
	}

	/**
	 * Register ILM Types and ILM Tags taxonomies.
	 */
	public static function register_taxonomy() {
		/**
		 * Taxonomy: Entry Types.
		 */

		$ilm_type_labels = [
			'name'          => esc_html__( 'Entry Types', 'terraso' ),
			'singular_name' => esc_html__( 'Entry Type', 'terraso' ),
		];

		$ilm_tyoe_args = [
			'label'                 => esc_html__( 'Entry Types', 'terraso' ),
			'labels'                => $ilm_type_labels,
			'public'                => true,
			'publicly_queryable'    => false,
			'hierarchical'          => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'show_in_nav_menus'     => true,
			'query_var'             => true,
			'rewrite'               => [
				'slug'       => 'ilm_type',
				'with_front' => true,
			],
			'show_admin_column'     => false,
			'show_in_rest'          => true,
			'show_tagcloud'         => false,
			'rest_base'             => 'ilm_type',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rest_namespace'        => 'wp/v2',
			'show_in_quick_edit'    => true,
			'sort'                  => false,
			'show_in_graphql'       => false,
		];

		/**
		 * Taxonomy: Tags.
		 */

		$ilm_tag_labels = [
			'name'          => esc_html__( 'Tags', 'terraso' ),
			'singular_name' => esc_html__( 'Tag', 'terraso' ),
		];


		$ilm_type_args = [
			'label'                 => esc_html__( 'Tags', 'terraso' ),
			'labels'                => $ilm_tag_labels,
			'public'                => true,
			'publicly_queryable'    => false,
			'hierarchical'          => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'show_in_nav_menus'     => true,
			'query_var'             => true,
			'rewrite'               => [
				'slug'       => 'ilm_tag',
				'with_front' => true,
			],
			'show_admin_column'     => false,
			'show_in_rest'          => true,
			'show_tagcloud'         => false,
			'rest_base'             => 'ilm_tag',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rest_namespace'        => 'wp/v2',
			'show_in_quick_edit'    => true,
			'sort'                  => false,
			'show_in_graphql'       => false,
		];

		register_taxonomy( 'ilm_type', [ self::POST_TYPE ], $ilm_tyoe_args );
		register_taxonomy( 'ilm_tag', [ self::POST_TYPE ], $ilm_type_args );
	}

	/**
	 * Get the template part in an output buffer and return it
	 *
	 * @param string $template_name Template file name.
	 */
	public static function get_template_part( $template_name ) {
		$located = dirname( __DIR__ ) . '/' . $template_name;

		ob_start();
		require $located; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		return ob_get_clean();
	}

	/**
	 * Post types for which Divi builder is enabled.
	 *
	 * @param array $post_types Array of post types.
	 */
	public static function et_builder_post_types( $post_types ) {
		$post_types[] = self::POST_TYPE;

		return $post_types;
	}
}

add_action( 'after_setup_theme', [ 'ILM_Guide', 'hooks' ] );
add_action( 'wp', [ 'ILM_Guide', 'late_hooks' ] );
