<?php

declare( strict_types=1 );

namespace ruigehond010;

// TODO have querystring selection of category work without filter being present on the page
use ruigehond_0_5_1;

defined( 'ABSPATH' ) or die();

class ruigehond010 extends ruigehond_0_5_1\ruigehond {
	private $basename, $database_version, $taxonomies, $slug, $choose_option, $choose_all, $search_faqs, $table_prefix,
		$more_button_text, $no_results_warning, $max, $max_ignore_elsewhere,
		$order_table, $header_tag,
		$title_links_to_overview, $schema_on_single_page, $exclude_from_search, $exclude_from_count, $queue_frontend_css,
		$open_first_faq_on_page;
	// variables that hold cached items
	private $terms;

	public function __construct( $basename ) {
		parent::__construct( 'ruigehond010' );
		$this->basename    = $basename;
		$wp_prefix         = $this->wpdb->prefix;
		$this->order_table = "{$wp_prefix}ruigehond010_taxonomy_o";
		// set some options
		$this->database_version        = $this->getOption( 'database_version', '0.0.0' );
		$this->taxonomies              = $this->getOption( 'taxonomies', 'category' );
		$this->slug                    = $this->getOption( 'slug', 'ruigehond010_faq' ); // standard the post_type is used by WP
		$this->title_links_to_overview = $this->getOption( 'title_links_to_overview', false );
		$this->choose_option           = $this->getOption( 'choose_option', __( 'Choose option', 'faq-with-categories' ) );
		$this->choose_all              = $this->getOption( 'choose_all', __( 'All', 'faq-with-categories' ) );
		$this->header_tag              = $this->getOption( 'header_tag', 'h4' );
		$this->schema_on_single_page   = $this->getOption( 'schema_on_single_page', false );
		$this->search_faqs             = $this->getOption( 'search_faqs', __( 'Search faqs', 'faq-with-categories' ) );
		$this->exclude_from_search     = $this->getOption( 'exclude_from_search', true );
		$this->exclude_from_count      = $this->getOption( 'exclude_from_count', true );
		$this->queue_frontend_css      = $this->getOption( 'queue_frontend_css', true );
		$this->open_first_faq_on_page  = $this->getOption( 'open_first_faq_on_page', true );
		// more_button_text and max are only used in javascript, attach them to the ruigehond010_faq element as data
		$this->max_ignore_elsewhere = $this->getOption( 'max_ignore_elsewhere', false );
		$this->more_button_text     = $this->getOption( 'more_button_text', __( 'Show more', 'faq-with-categories' ) );
		$this->no_results_warning   = $this->getOption( 'no_results_warning', __( 'No results found', 'faq-with-categories' ) );
		$this->max                  = $this->getOption( 'max', 5 );
		// Add custom callback for taxonomy counter, if we do not want the faq posts to be counted towards the total
		if ( true === $this->exclude_from_count ) {
			add_filter( 'register_taxonomy_args', function ( $args, $name ) {
				if ( $name === $this->taxonomies ) {
					$args['update_count_callback'] = array( $this, 'update_count_callback' );
				}

				return $args;
			}, 20, 2 );
		}
		// table names
		$this->table_prefix = "{$wp_prefix}ruigehond010_";
	}

	public function initialize() {
//        if (current_user_can('administrator')) {
//            error_reporting(E_ALL);
//            ini_set('display_errors', '1');
//        }
		$this->loadTranslations( 'faq-with-categories' );
		/**
		 * register custom post type for faqs
		 */
		register_post_type( 'ruigehond010_faq',
			array(
				'labels'              => array(
					'name'          => esc_html__( 'FAQ', 'faq-with-categories' ),
					'singular_name' => esc_html__( 'FAQ', 'faq-with-categories' ),
				),
				'public'              => true,
				'has_archive'         => true,
				'taxonomies'          => array( $this->taxonomies ),
				'exclude_from_search' => $this->exclude_from_search,
				// remember to flush_rewrite_rules(); when this changes
				'rewrite'             => array( 'slug' => $this->slug ),
				'show_in_menu'        => false,
				'show_in_admin_bar'   => true,
			)
		);
		// regular stuff
		if ( is_admin() ) {
			// seems excessive but no better stable solution found yet
			// update check only on admin, so make sure to be admin after updating :-)
			$this->update_when_necessary();
			add_action( 'admin_init', array( $this, 'settings' ) );
			add_action( 'admin_menu', array( $this, 'menuitem' ) );
			add_action( 'add_meta_boxes', array(
				$this,
				'meta_box_add'
			) ); // in the box the user set the exclusive value
			add_action( 'save_post', array( $this, 'saveForPost' ) );
			add_action( 'admin_notices', array( $this, 'displayAdminNotices' ) );
			// settings link on plugins page
			add_filter( "plugin_action_links_$this->basename", array( $this, 'settingsLink' ) );
			// styles...
			wp_enqueue_style( 'ruigehond010_admin_stylesheet', plugin_dir_url( __FILE__ ) . 'admin.css', [], RUIGEHOND010_VERSION );
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
		} else {
			wp_enqueue_script( 'ruigehond010_javascript', plugin_dir_url( __FILE__ ) . 'client.js', array( 'jquery' ), RUIGEHOND010_VERSION );
			if ( $this->queue_frontend_css ) { // only output css when necessary
				wp_enqueue_style( 'ruigehond010_stylesheet_display', plugin_dir_url( __FILE__ ) . 'display.css', [], RUIGEHOND010_VERSION );
			}
			add_action( 'wp_head', array( $this, 'outputSchema' ) );
			add_shortcode( 'faq-with-categories', array( $this, 'getHtmlForFrontend' ) );
			add_shortcode( 'faq-with-categories-filter', array( $this, 'getHtmlForFrontend' ) );
			add_shortcode( 'faq-with-categories-search', array( $this, 'getHtmlForFrontend' ) );
		}
	}

	public function displayAdminNotices() {
		// the message generated by this plugin if overview or exclusive tags are used multiple times
		if ( ( $message = get_option( 'ruigehond010_admin_multi_message' ) ) ) {
			echo '<div class="notice notice-warning" style="background-color:#ffc;"><strong>Faq-with-categories:</strong><br/>';
			echo esc_html( $message );
			echo '</div>';
		}
		delete_option( 'ruigehond010_admin_multi_message' );
	}

	public function update_count_callback( $terms, $taxonomy ) {
		// got from: https://ivanpaulin.com/exclude-pages-taxonomy-counter-wordpress/
		// https://codex.wordpress.org/Function_Reference/register_taxonomy -> update_count_callback
		unregister_taxonomy_for_object_type( $this->taxonomies, 'ruigehond010_faq' );
		// you can't call wp_update_term_count, because that will call the current function, resulting in a loop
		// so we just duplicate the code here, classy right
		// https://developer.wordpress.org/reference/functions/wp_update_term_count_now/
		$object_types = (array) $taxonomy->object_type;
		foreach ( $object_types as &$object_type ) {
			if ( 0 === strpos( $object_type, 'attachment:' ) ) {
				list( $object_type ) = explode( ':', $object_type );
			}
		}
		if ( $object_types == array_filter( $object_types, 'post_type_exists' ) ) {
			// Only post types are attached to this taxonomy
			_update_post_term_count( $terms, $taxonomy );
		} else {
			// Default count updater
			_update_generic_term_count( $terms, $taxonomy );
		}
		// re-register the taxonomy for this post type
		register_taxonomy_for_object_type( $this->taxonomies, 'ruigehond010_faq' );
	}

	public function outputSchema() {
		if ( false === ( $post_id = get_the_ID() ) ) {
			return;
		}
		if ( $this->schema_on_single_page ) {
			if ( ( $temp_post = get_post( $post_id ) )->post_type === 'ruigehond010_faq' ) {
				echo $this->getSchemaFromPosts( array( $temp_post ) );
			}
		} elseif ( ( $on = $this->getOption( 'post_ids' ) ) && isset( $on[ $post_id ] ) ) {
			// output the exclusive ones and main faq only when not on single page
			if ( is_array( $on[ $post_id ] ) ) {
				$exclusive = $on[ $post_id ]['exclusive'] ?? null;
				$term      = $on[ $post_id ]['term'] ?? null;
				echo $this->getSchemaFromPosts( $this->getPosts( $exclusive, $term ) );
			} else {
				echo $this->getSchemaFromPosts( $this->getPosts() );
			}
		}
	}

	public function getSchemaFromPosts( $posts ) {
		ob_start();
		$last_index = count( $posts ) - 1;
		echo '<script type="application/ld+json" id="ruigehond010_faq_schema">{"@context": "https://schema.org","@type": "FAQPage","mainEntity": [';
		foreach ( $posts as $index => $post ) {
			echo '{"@type":"Question","name":';
			echo wp_json_encode( $post->post_title );
			echo ',"acceptedAnswer":{"@type":"Answer","text":';
			echo wp_json_encode( $post->post_content );
			echo '}}';
			if ( $index < $last_index ) {
				echo ',';
			}
		}
		echo ']}</script>';

		return ob_get_clean();
	}

	/**
	 * @param $post_id
	 *
	 * @return null|string The term or null when not found
	 * @since 1.1.0
	 */
	public function getDefaultTerm( $post_id ) {
		$rows    = $this->getTerms();
		$post_id = (string) $post_id;
		foreach ( $rows as $term_id => $arr ) {
			foreach ( $arr as $index => $term ) {
				if ( isset( $term['post_id'] ) && $post_id === (string) $term['post_id'] ) {
					return $term['term'];
				}
			}
		}

		return null;
	}

	public function getHtmlForFrontend( $attributes = [], $content = null, $short_code = 'faq-with-categories' ): string {
		if ( ( ! $post_id = get_the_ID() ) ) {
			return '';
		}
		$chosen_exclusive = isset( $attributes['exclusive'] ) ? $attributes['exclusive'] : null;
		$chosen_term      = isset( $attributes['category'] ) ? strtolower( $attributes['category'] ) : null;
		$filter_term      = isset( $_GET['category'] ) ? strtolower( $_GET['category'] ) : null;
		$quantity         = isset( $attributes['quantity'] ) ? (int) $attributes['quantity'] : null;
		$title_only       = isset( $attributes['title-only'] ); // no matter the value, when set we do title only
		// when you have assigned a page to a term, also use that term when you’re on that specific page
		if ( null === $chosen_term ) {
			$chosen_term = $this->getDefaultTerm( $post_id );
		}
		// several types of html can be got with this
		// 1) the select boxes for the filter (based on term)
		if ( 'faq-with-categories-filter' === $short_code ) {
			// ->getTerms() = fills by sql SELECT term_id, parent, count, term FROM etc.
			$rows = $this->getTerms();
			// write the html lists
			ob_start();
			foreach ( $rows as $parent => $options ) {
				echo '<select class="ruigehond010 choose-category" data-ruigehond010_parent="';
				echo (int) $parent;
				if ( 0 === $parent ) {
					echo '" style="display: block"><option>'; // display block to prevent repainting default situation
					echo esc_html( $this->choose_option );
				} else {
					echo '"><option>';
					echo esc_html( $this->choose_all );
				}
				echo '</option>';
				foreach ( $options as $index => $option ) {
					$term_id = (int) $option['term_id'];
					echo '<option data-ruigehond010_term_id="';
					echo $term_id;
					if ( true === $option['has_items'] ) {
						echo '" data-ruigehond010_has_items="1';
					}
					echo '" value="term-';
					echo $term_id;
					//echo htmlentities($term = $option['term']);
					if ( strtolower( ( $term = $option['term'] ) ) === $filter_term ) {
						echo '" selected="selected';
					}
					echo '">';
					echo esc_html( $term );
					echo '</option>';
				}
				echo '</select>';
			}

			return ob_get_clean();
		} elseif ( 'faq-with-categories-search' === $short_code ) {
			$__search_faqs = esc_html( $this->search_faqs );

			return "<input type='text' name='search' class='search-field ruigehond010' id='ruigehond010_search' placeholder='$__search_faqs'/>";
		} else { // 2) all the posts, filtered by 'exclusive' or 'term'
			// [faq-with-categories exclusive="homepage"], or /url?category=blah
			// load the posts, will return row data: ID = id of the post, exclusive = meta value for exclusive (null when none)
			// term = category: this will multiply rows if multiple categories are attached,
			// post_title = question, post_content = answer, post_date = the published date
			$posts = $this->getPosts( $chosen_exclusive, $chosen_term );
			ob_start();
			// prepare the link
			if ( true === $title_only ) {
				if ( true === $this->title_links_to_overview ) {
					$slug = $this->getOption( 'faq_page_slug' );
					if ( is_null( $slug ) ) {
						echo '<span class="notice">';
						echo esc_html__( 'Please indicate the main FAQ page in page settings.', 'faq-with-categories' );
						echo '</span>';
					} else {
						if ( strpos( $slug, '?' ) === false ) {
							$slug = "$slug?post_id=%s";
						} else {
							$slug = "$slug&post_id=%s";
						}
						if ( strpos( $slug, '/' ) !== 1 ) {
							$slug = "/$slug";
						}
					}
				} else {
					$slug = $this->slug;
				}
			} else {
				$slug = '%s';
			}
			echo '<ul class="ruigehond010 faq posts ';
			if ( $chosen_exclusive ) {
				echo sanitize_title( $chosen_exclusive );
			}
			if ( $title_only ) {
				echo ' title-only';
			}
			if ( true === $this->max_ignore_elsewhere &&
			     ( null !== $chosen_term || null !== $chosen_exclusive )
			) {
				echo '" data-max_ignore="1'; // set the max to be ignored on pages that display subsets of the faq
			}
			echo '" data-max="';
			echo (int) $this->max;
			echo '" data-more_button_text="';
			echo esc_html( $this->more_button_text );
			echo '" data-open_first_faq_on_page="';
			if ( true === $this->open_first_faq_on_page ) {
				echo '1';
			}
			echo '">';
			$h_tag = $this->header_tag;
			if ( ! in_array( $h_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) {
				$h_tag = 'h4';
			}
			$h_open  = "<$h_tag class=\"faq-header\">";
			$h_close = "</$h_tag>";
			foreach ( $posts as $index => $post ) {
				if ( $index === $quantity ) {
					break;
				}
				echo '<li class="ruigehond010_post term-';
				echo strtolower( implode( ' term-', $post->term_ids ) );
				if ( $post->exclusive ) {
					echo '" data-exclusive="';
					echo esc_html( $post->exclusive );
				}
				echo '" data-post_id="';
				echo (int) $post->ID;
				echo '">';
				if ( false === $title_only ) {
					echo $h_open;
					echo $post->post_title;
					echo $h_close;
					echo '<div>';
					echo apply_filters( 'the_content', $post->post_content );
					echo '</div>';
				} else {
					echo '<a class="title-only faq-header" href="';
					if ( true === $this->title_links_to_overview ) {
						echo sprintf( $slug, $post->ID );
					} else {
						echo "/$slug/$post->post_name";
					}
					echo '">';
					echo esc_html( $post->post_title );
					echo '</a>';
				}
				echo '</li>';
			}
			// no results warning
			echo '<li class="ruigehond010 no-results-warning" style="display: none;">';
			echo esc_html( $this->no_results_warning );
			echo '</li>';
			// end list
			echo '</ul>';

			return ob_get_clean();
		}
	}

	private function getTerms(): array {
		if ( true === isset( $this->terms ) ) {
			return $this->terms;
		} // return cached value if available
		// get the terms for this registered taxonomies from the db
		$taxonomies = addslashes( sanitize_text_field( $this->taxonomies ) ); // just for the h#ck of it
		$wp_prefix  = $this->wpdb->prefix;
		$sql        = "SELECT DISTINCT t.term_id, tt.parent, t.name AS term, o.t, o.post_id,
       			MAX(CASE WHEN
           		EXISTS(SELECT 1 FROM {$wp_prefix}posts p WHERE ID = tr.object_id AND p.post_type = 'ruigehond010_faq')
				THEN 1 ELSE 0 END) AS has_items                
				FROM {$wp_prefix}terms t
                INNER JOIN {$wp_prefix}term_taxonomy tt ON t.term_id = tt.term_id
                LEFT OUTER JOIN $this->order_table o ON o.term_id = t.term_id
                LEFT OUTER JOIN {$wp_prefix}term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = '$taxonomies'
				GROUP BY t.term_id, tt.parent, t.name, o.t, o.post_id, o.o
				ORDER BY tt.parent, COALESCE(o.o, 1), t.name;";
		$rows       = $this->wpdb->get_results( $sql, OBJECT );
		$terms      = array();
		foreach ( $rows as $key => $row ) {
			$parent = (int) $row->parent;
			if ( false === isset( $terms[ $parent ] ) ) {
				$terms[ $parent ] = array();
			}
			$terms[ $parent ][] = array(
				'term_id'   => (int) $row->term_id,
				'term'      => $row->term,
				't'         => $row->t,
				'post_id'   => $row->post_id,
				'has_items' => (bool) $row->has_items,
			);
		}
		$this->terms = $terms;

		return $terms;
	}

	/**
	 * @param string|bool|null $exclusive
	 * @param null $term
	 *
	 * @return array the rows from db as \stdClasses in an indexed array
	 */
	private function getPosts( $exclusive = null, $term = null ): array {
		$term_ids  = array(); // we are going to collect all the term_ids that fall under the requested $term
		$wp_prefix = $this->wpdb->prefix;
		if ( is_string( $term ) ) {
			$sql = $this->wpdb->prepare( "SELECT term_id FROM {$wp_prefix}terms t WHERE lower(t.name) = %s;", $term );
			// now for as long as rows with term_ids are returned, keep building the array
			while ( ( $rows = $this->wpdb->get_results( $sql ) ) ) {
				foreach ( $rows as $index => $row ) {
					$term_ids[] = (int) $row->term_id;
				}
				// new sql selects all the children from the term_ids that are in the array
				$str_term_ids = implode( ',', $term_ids );
				$sql          = "SELECT term_id FROM {$wp_prefix}term_taxonomy tt 
                        WHERE tt.parent IN ($str_term_ids) 
                        AND term_id NOT IN ($str_term_ids);"; // excluding the term_ids already in the array
				// so it returns no rows if there are no more children, ending the while loop
			}
		}
		ob_start();
		echo "SELECT p.ID, p.post_title, p.post_content, p.post_date, p.post_name, t.term_id, pm.meta_value AS exclusive FROM
                {$wp_prefix}posts p LEFT OUTER JOIN 
                {$wp_prefix}term_relationships tr ON tr.object_id = p.ID LEFT OUTER JOIN 
                {$wp_prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id LEFT OUTER JOIN 
                {$wp_prefix}terms t ON t.term_id = tt.term_id LEFT OUTER JOIN 
                {$wp_prefix}postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_ruigehond010_exclusive' 
                WHERE p.post_type = %s AND post_status = %s";
		// set up the where condition regarding exclusive and term....
		$values = array( 'ruigehond010_faq', 'publish' );
		if ( count( $term_ids ) > 0 ) {
			echo ' AND t.term_id IN (';
			foreach ( $term_ids as $index => $term_id ) {
				$values[] = $term_id;
				if ( 0 === $index ) {
					echo '%d';
				} else {
					echo ',%d';
				}
			}
			echo ')';
			//echo ' AND t.term_id IN (', implode( ',', $term_ids ), ')';
		} elseif ( is_string( $exclusive ) ) {
			$values[] = $exclusive;
			echo ' AND pm.meta_value = %s';
		}
		echo ' ORDER BY p.post_date DESC;';
		$sql        = $this->wpdb->prepare( ob_get_clean(), $values );
		$rows       = $this->wpdb->get_results( $sql, OBJECT );
		$return_arr = array();
		$current_id = 0;
		foreach ( $rows as $index => $row ) {
			if ( $row->ID === $current_id ) { // add the category to the current return value
				$return_arr[ count( $return_arr ) - 1 ]->term_ids[] = $row->term_id;
			} else { // add the row, when not exclusive is requested posts without terms must be filtered out
				if ( ( $term_id = $row->term_id ) || $exclusive ) {
					$row->term_ids = array( $term_id );
					unset( $row->term_id );
					$return_arr[] = $row;
					$current_id   = $row->ID;
				}
			}
		}
		unset( $rows );

		return $return_arr;
	}

	public function handle_input( $args ): ruigehond_0_5_1\returnObject {
		$returnObject = $this->getReturnObject();
		$wp_prefix    = $this->wpdb->prefix;
		if ( isset( $args['id'] ) ) {
			$id = (int) $args['id']; // this must be the same as $this->row->id
		} else {
			$id = 0;
		}
		if ( isset( $args['value'] ) ) {
			$value = trim( stripslashes( $args['value'] ) ); // don't know how it gets magically escaped, but not relying on it
		} else {
			$value = '';
		}
		$handle = trim( stripslashes( $args['handle'] ) );
		// cleanup the array, can this be done more elegantly?
		$args['id']    = $id;
		$args['value'] = $value;
		switch ( $handle ) {
			case 'order_taxonomy':
				if ( isset( $args['order'] ) and is_array( $args['order'] ) ) {
					$rows = $args['order'];
					foreach ( $rows as $term_id => $o ) {
						$this->upsertDb( $this->order_table,
							array( 'o' => $o ),
							array( 'term_id' => $term_id )
						);
					}
					$returnObject->set_success( true );
				}
				break;
			case 'update':
				if ( is_admin() ) {
					$table_name  = stripslashes( $args['table_name'] );
					$column_name = stripslashes( $args['column_name'] );
					$id_column   = ( isset( $args['id_column'] ) ) ? $args['id_column'] : "{$table_name}_id";
					switch ( $column_name ) {
						case 't': // you need to save the title and the id as well
							if ( strrpos( $value, ')' ) === strlen( $value ) - 1 ) {
								$post_id = (int) str_replace( ')', '', substr( $value, strrpos( $value, '(' ) + 1 ) );
								//$post_title = trim( substr( $value, 0, strrpos( $value, '(' ) ) );
								if ( $post_title = $this->wpdb->get_var( "SELECT post_title FROM {$wp_prefix}posts WHERE ID = {$post_id};" ) ) {
									$args['value'] = "$post_title ($post_id)";
									$update        = array( 't' => $args['value'], 'post_id' => $post_id );
								} else {
									$update = array();
									$returnObject->add_message( sprintf( esc_html__( 'post_id %d not found', 'faq-with-categories' ), $post_id ), 'warn' );
								}
							} else {
								$post_title = $value;
								if ( '' === $value ) {
									$update = array( 't' => '', 'post_id' => null );
								} else {
									$sql = $this->wpdb->prepare( 'SELECT ID FROM %i WHERE post_title = %s;', "{$wp_prefix}posts", $post_title );
									if ( $post_id = $this->wpdb->get_var( $sql ) ) {
										$args['value'] = "$post_title ($post_id)";
										$update        = array( 't' => $args['value'], 'post_id' => $post_id );
									} else {
										$update              = array( 't' => $args['value'], 'post_id' => 0 );
										$args['nonexistent'] = true;
										$returnObject->add_message( sprintf( esc_html__( 'Could not find post_id based on title: %s', 'faq-with-categories' ), $post_title ), 'warn' );
									}
								}
							}
							break;
						default:
							$update = array();
					}
					if ( count( $update ) > 0 ) {
						$rows_affected = $this->upsertDb(
							"$this->table_prefix$table_name", $update,
							array( $id_column => $id ) );
						if ( 0 === $rows_affected ) {
							$returnObject->add_message( esc_html__( 'Not updated', 'faq-with-categories' ), 'warn' );
						} else {
							$returnObject->set_success( true );
							$sql           = $this->wpdb->prepare( "SELECT %i FROM %i WHERE %i = %d;", $column_name, "{$this->table_prefix}$table_name", $id_column, $id );
							$args['value'] = $this->wpdb->get_var( $sql );
							if ( $column_name === 'rating_criteria' ) {
								$args['value'] = implode( PHP_EOL, json_decode( $args['value'] ) );
							}
						}
					}
				}
				break;
			case 'suggest_t':
				// return all valid post titles that can be used for this tag
				$rows = $this->wpdb->get_results(
					"SELECT CONCAT(post_title, ' (', ID, ')') AS t 
						FROM {$wp_prefix}posts 
						WHERE post_status = 'publish' AND NOT post_type = 'nav_menu_item'
						ORDER BY post_title ASC;" );
				if ( count( $rows ) > 0 ) {
					$returnObject->set_success( true );
				}
				$returnObject->set_data( array( 'suggestions' => $rows ) );
				break;
			default:
				return $this->getReturnObject( sprintf( esc_html__( 'Did not understand handle %s', 'faq-with-categories' ),
					var_export( $args['handle'], true ) ) );
		}
		$returnObject->set_data( $args );

		return $returnObject;
	}

	/**
	 * https://developer.wordpress.org/reference/functions/add_meta_box/
	 * @param null $post_type
	 */
	function meta_box_add( $post_type = null ) {
		if ( ! $post_id = get_the_ID() ) {
			return;
		}
		if ( $post_type === 'ruigehond010_faq' ) {
			add_meta_box( // WP function.
				'ruigehond010', // Unique ID
				'FAQ with categories', // Box title
				array( $this, 'meta_box' ), // Content callback, must be of type callable
				'ruigehond010_faq',
				'normal',
				'low',
				array( 'exclusive' => get_post_meta( $post_id, '_ruigehond010_exclusive', true ) )
			);
		}
	}

	function meta_box( $post, $obj ) {
		wp_nonce_field( 'ruigehond010_save', 'ruigehond010_nonce' );
		echo '<input type="text" id="ruigehond010_exclusive" name="ruigehond010_exclusive" value="';
		echo esc_html( $obj['args']['exclusive'] );
		echo '"/> <label for="ruigehond010_exclusive">';
		echo esc_html__( 'The tag this FAQ entry is exclusive to, use it in a shortcode to summon the entry. Note that it will still be displayed for the taxonomies that are checked.', 'faq-with-categories' );
		echo '</label>';
	}

	function saveForPost( $post_id ) {
		// update this particular post in the post_ids option regarding term / category shortcode
		$post_ids = $this->getOption( 'post_ids' );
		// only update (remove) them, do not assume the user wants schema always so don’t automatically add them
		if ( isset( $post_ids[ $post_id ] ) ) {
			$this->setOption( 'post_ids', $this->registerPostByShortcode( $this->getOption( 'post_ids' ), $post_id ) );
		}
		// save meta box:
		if ( ! isset( $_POST['ruigehond010_nonce'] ) || ! wp_verify_nonce( $_POST['ruigehond010_nonce'], 'ruigehond010_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, '_ruigehond010_exclusive' );
		if ( isset( $_POST['ruigehond010_exclusive'] ) ) {
			add_post_meta( $post_id, '_ruigehond010_exclusive',
				sanitize_title( $_POST['ruigehond010_exclusive'] ), true );
		}
	}

	private function decodePostContent( $content ) {
		if ( ! $content ) {
			return $content;
		}
		/* stolen from have-searchwp-index-wpbakery-rawhtml */
		while ( false !== strpos( $content, '[/vc_raw_html]' ) ) {
			$start = strpos( $content, '[vc_raw_html' );
			if ( false === $start ) {
				return $content;
			}
			$stop = strpos( $content, ']', $start );
			if ( false === $stop ) {
				return $content;
			}
			$stop += 1;
			$end  = strpos( $content, '[/vc_raw_html]', $stop );
			if ( false === $end ) {
				return $content;
			}
			$chunk   = substr( $content, $start, $end - $start ) . '[/vc_raw_html]';
			$encoded = substr( $content, $stop, $end - $stop );
			$decoded = rawurldecode( base64_decode( $encoded ) );
			$content = str_replace( $chunk, $decoded, $content );
		}

		return $content;
	}

	public function ordertaxonomypage() {
		wp_enqueue_script( 'ruigehond010_admin_javascript', plugin_dir_url( __FILE__ ) . 'admin.js', array(
			'jquery-ui-droppable',
			'jquery-ui-sortable',
			'jquery'
		), RUIGEHOND010_VERSION );
		$ajax_nonce = wp_create_nonce( 'ruigehond010_nonce' );
		wp_localize_script( 'ruigehond010_admin_javascript', 'Ruigehond010_global', array(
			'nonce' => $ajax_nonce,
		) );
		echo '<div class="wrap ruigehond010"><h1>';
		echo esc_html( get_admin_page_title() );
		echo '</h1><p>';
		echo esc_html__( 'This page only concerns itself with the order. The hierarchy is determined by the taxonomy itself.', 'faq-with-categories' );
		echo '<br/>';
		echo esc_html__( 'If you assign a page to a taxonomy, the FAQ shortcode on that page will display FAQ-posts from that taxonomy.', 'faq-with-categories' );
		echo '</p><hr/>';
		$terms = $this->getTerms(); // these are ordered to the best of the knowledge of the system already, but with parents
		foreach ( $terms as $index => $sub_terms ) {
			echo '<section class="rows-sortable">';
			foreach ( $sub_terms as $o => $term ) {
				$term_id = $term['term_id'];
				$t       = $term['t'] ?? '';
				echo '<div class="ruigehond010-order-term" data-id="';
				echo (int) $term_id;
				echo '" data-inferred_order="';
				echo (int) $o;
				echo '">';
				// ajax input to link a page to the taxonomy / explaining the taxonomy
				echo '<input type="text" data-id_column="term_id" data-id="';
				echo (int) $term_id;
				echo '" data-handle="update" data-table_name="taxonomy_o" data-column_name="t" data-value="';
				echo esc_html( $t );
				echo '" value="';
				echo esc_html( $t );
				echo '"	class="ruigehond010 input post_title ajaxupdate ajaxsuggest tabbed';
				if ( '0' === $term['post_id'] ) {
					echo ' nonexistent';
				}
				echo '"/>';
				// ordering handle
				echo '<div class="sortable-handle">';
				echo esc_html( $term['term'] );
				echo '</div></div>';
			}
			echo '</section><hr/>';
		}
		echo '</div>';
	}

	public function per_page_settings() {
		// check user capabilities
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>';
		echo esc_html( get_admin_page_title() );
		echo '</h1><p>';
		echo esc_html__( 'Settings for pages the shortcode is used on.', 'faq-with-categories' );
		echo '<br/>';
		echo esc_html__( 'If you do not output schema on single FAQ pages you can check here which pages should output schema.', 'faq-with-categories' );
		echo '<br/>';
		echo esc_html__( 'Note that outputting duplicate FAQs in schema may result in them not being considered at all.', 'faq-with-categories' );
		echo '<br/>';
		echo esc_html__( 'Some pages may not be able to output schema, check if you use a specific category or limited quantity on those pages.', 'faq-with-categories' );
		echo '</p>';

		global $wpdb;
		$rows     = $wpdb->get_results( "SELECT ID, post_title, post_name, post_content, post_status FROM $wpdb->posts WHERE post_type NOT IN ('revision');", OBJECT );
		$edit     = get_admin_url();
		$main     = $this->getOption( 'faq_page_slug' );
		$post_ids = $this->getOption( 'post_ids' );

		echo '<form action="options.php" method="post">';
		// output security fields for the registered setting
		settings_fields( 'ruigehond010' );
		// output setting sections and their fields
		echo '<table class="ruigehond010"><thead><tr><td>Post title / edit link</td><td>&nbsp;</td><td colspan="2" style="text-align:right">Main FAQ page</td></tr></thead><tbody>';
		foreach ( $rows as $index => $row ) {
			$post_id = (int) $row->ID;
			$content = $this->decodePostContent( $row->post_content );
			if ( false !== strpos( $content, '[faq-with-categories' ) ) {
				echo '<tr>';
				echo '<td><a href="', $edit, '/post.php?action=edit&post=', $post_id, '">', $row->post_title, '</a></td>';
				//echo '<td>', $main, ', ', $row->post_name, '</td>';
				echo '<td><a href="', $row->post_name, '">View</a></td>';
				if ($this->schema_on_single_page) {
					echo '<td>&nbsp;</td>';
				} else {
					echo '<td><label><input type="checkbox" name="ruigehond010[output_schema][]" value="', $post_id, '"';
					if ( isset( $post_ids[ $post_id ] ) ) {
						echo ' checked="checked"';
					}
					echo '/> ', esc_html__( 'Output schema', 'faq-with-categories' ), '</label></td>';
				}
				echo '<td style="text-align:center"><input type="radio" name="ruigehond010[main_faq_page]" value="', $post_id, '"';
				if ( $main === $row->post_name ) {
					echo ' checked="checked"';
				}
				echo '/></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		submit_button( esc_html__( 'Save Settings', 'faq-with-categories' ) );
		echo '</form>';

		$rows = null;
	}

	public function settingspage() {
		// check user capabilities
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}
		// if the slug for the faq posts just changed, flush rewrite rules as a service
		if ( get_option( 'ruigehond010_flag_flush_rewrite_rules' ) ) {
			delete_option( 'ruigehond010_flag_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
		// start the page
		echo '<div class="wrap"><h1>';
		echo esc_html( get_admin_page_title() );
		echo '</h1><p>';
		echo esc_html__( 'FAQS are always sorted by published date descending, so newest entries are first. By default they are output as an accordion list with the first one opened.', 'faq-with-categories' );
		echo '<br/>';
		// #TRANSLATORS: string inserted is an example of a querystring to pre-filter for a category
		echo sprintf( esc_html__( 'You can link to your general FAQ page with a category in the querystring (e.g. %s) to pre-filter the faqs.', 'faq-with-categories' ), '<strong>?category=test%20category</strong>' );
		echo '<br/>';
		echo esc_html__( 'You may use the following shortcodes, of course certain combinations do not make sense and may produce erratic behaviour.', 'faq-with-categories' );
		echo '<br/>';
		echo sprintf( esc_html__( '%s produces the default list for the central FAQ page and outputs FAQ snippets schema in the head.', 'faq-with-categories' ), '<strong>[faq-with-categories]</strong>' );
		echo '<br/>';
		echo sprintf( esc_html__( '%s produces a filter menu according to the chosen taxonomy using the specified order.', 'faq-with-categories' ), '<strong>[faq-with-categories-filter]</strong>' );
		echo '<br/>';
		echo sprintf( esc_html__( '%s produces a search box that will perform client-side lookup through the faqs.', 'faq-with-categories' ), '<strong>[faq-with-categories-search]</strong>' );
		echo '<br/>';
		// #TRANSLATORS: 1 is a tag, 2 indicates the NOTE at the bottom with an asterisk (*)
		echo sprintf( esc_html__( '%1$s %2$s limits the quantity of the faqs to 5, or use another number.', 'faq-with-categories' ), '[faq-with-categories <strong>quantity="5"</strong>]', '<em>(*)</em>' );
		echo ' ';
		echo esc_html__( 'This will NOT output FAQ snippets schema in the head.', 'faq-with-categories' );
		echo '<br/>';
		// #TRANSLATORS: 1 is a tag, 2 indicates the NOTE at the bottom with an asterisk (*)
		echo sprintf( esc_html__( '%1$s %2$s display only faqs for the specified category (case insensitive).', 'faq-with-categories' ), '[faq-with-categories <strong>category="category name"</strong>]', '<em>(*)</em>' );
		echo ' ';
		echo esc_html__( 'This will NOT output FAQ snippets schema in the head.', 'faq-with-categories' );
		echo '<br/>';
		// #TRANSLATORS: 1 is a tag, 2 indicates the NOTE at the bottom with an asterisk (*)
		echo sprintf( esc_html__( '%1$s %2$s any tag you specified under a faq entry in the box, will gather all faqs with that tag for display.', 'faq-with-categories' ), '[faq-with-categories <strong>exclusive="your tag"</strong>]', '<em>(*)</em>' );
		echo '<br/>';
		echo sprintf( esc_html__( '%s outputs the list as links rather than as an accordion.', 'faq-with-categories' ), '[faq-with-categories <strong>title-only="any value"</strong>]' );
		echo '<br/><em>(*) ';
		echo esc_html__( 'NOTE: only a limited number of faqs will be present on the page so search and filter will not work.', 'faq-with-categories' );
		echo '</em></p>';
		echo '<form action="options.php" method="post">';
		// output security fields for the registered setting
		settings_fields( 'ruigehond010' );
		// output setting sections and their fields
		do_settings_sections( 'ruigehond010' );
		// output save settings button
		submit_button( esc_html__( 'Save Settings', 'faq-with-categories' ) );
		echo '</form></div>';
	}

	public function settings() {
		if ( false === $this->onSettingsPage( 'faq-with-categories' ) ) {
			return;
		}
		if ( false === current_user_can( 'manage_options', 'faq-with-categories' ) ) {
			return;
		}
		register_setting( 'ruigehond010', 'ruigehond010', array( $this, 'settings_validate' ) );
		// register a new section in the page
		add_settings_section(
			'global_settings', // section id
			esc_html__( 'Options', 'faq-with-categories' ), // title
			static function () {
			}, //callback
			'ruigehond010' // page id
		);
		$labels = array(
			'taxonomies'              => esc_html__( 'Type the taxonomy you want to use for the categories.', 'faq-with-categories' ),
			'slug'                    => esc_html__( 'Slug for the individual faq entries (optional).', 'faq-with-categories' ),
			'title_links_to_overview' => esc_html__( 'When using title-only in shortcodes, link to the overview rather than individual FAQ page.', 'faq-with-categories' ),
			'open_first_faq_on_page'  => esc_html__( 'Open the first FAQ in a list automatically.', 'faq-with-categories' ),
			'schema_on_single_page'   => esc_html__( 'Output the faq schema on individual page rather than overview.', 'faq-with-categories' ),
			'choose_option'           => esc_html__( 'The ‘choose / show all’ option in top most select list.', 'faq-with-categories' ),
			'choose_all'              => esc_html__( 'The ‘choose / show all’ option in subsequent select lists.', 'faq-with-categories' ),
			'search_faqs'             => esc_html__( 'The placeholder in the search bar for the faqs.', 'faq-with-categories' ),
			'header_tag'              => esc_html__( 'Tag used for the header on faq page (e.g. h4), invalid input may cause errors on your page.', 'faq-with-categories' ),
			'max'                     => esc_html__( 'Number of faqs shown before ‘Show more’ button.', 'faq-with-categories' ),
			'max_ignore_elsewhere'    => esc_html__( 'Only use the more button on the central FAQ page, nowhere else.', 'faq-with-categories' ),
			'more_button_text'        => esc_html__( 'The text on the ‘Show more’ button.', 'faq-with-categories' ),
			'no_results_warning'      => esc_html__( 'Text shown when search or filter results in 0 faqs found.', 'faq-with-categories' ),
			'exclude_from_search'     => esc_html__( 'Will exclude the FAQ posts from site search queries.', 'faq-with-categories' ),
			'exclude_from_count'      => esc_html__( 'FAQ posts will not count towards total posts in taxonomies.', 'faq-with-categories' ),
			'queue_frontend_css'      => esc_html__( 'By default a small css-file is output to the frontend to format the entries. Uncheck to handle the css yourself.', 'faq-with-categories' ),
		);
		foreach ( $labels as $setting_name => $explanation ) {
			add_settings_field(
				$setting_name, // id, As of WP 4.6 this value is used only internally
				$setting_name, // title
				array( $this, 'echo_settings_field' ), // callback
				'ruigehond010', // page id
				'global_settings',
				[
					'setting_name' => $setting_name,
					'label_for'    => $explanation,
					'class_name'   => 'ruigehond010',
				] // args
			);
		}
	}

	public function echo_settings_field( $args ) {
		$setting_name = $args['setting_name'];
		switch ( $setting_name ) {
			case 'queue_frontend_css':
			case 'title_links_to_overview':
			case 'max_ignore_elsewhere':
			case 'schema_on_single_page':
			case 'open_first_faq_on_page':
			case 'exclude_from_count':
			case 'exclude_from_search': // make checkbox that transmits 1 or 0, depending on status
				echo '<label><input type="hidden" name="ruigehond010[', $setting_name, ']" value="';
				if ( $this->$setting_name ) {
					echo '1"><input checked="checked"';
				} else {
					echo '0"><input';
				}
				echo ' type="checkbox" onclick="this.previousSibling.value=1-this.previousSibling.value" class="',
				$args['class_name'], '"/>', $args['label_for'], '</label>';
				break;
			default: // make text input
				echo '<input type="text" name="ruigehond010[', $setting_name, ']" value="';
				echo htmlentities( (string) $this->$setting_name );
				echo '" style="width: ';
				if ( in_array( $setting_name, array(
					'choose_option',
					'choose_all',
					'search_faqs',
					'more_button_text',
					'no_results_warning',
				) ) ) {
					echo '312';
				} else {
					echo '162';
				}
				echo 'px" class="', $args['class_name'], '"/> <label>', $args['label_for'], '</label>';
		}
	}

	public function settings_validate( $input ) {
		$options = (array) get_option( 'ruigehond010' );
		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				// on / off flags (1 vs 0 on form submit, true / false otherwise
				case 'queue_frontend_css':
				case 'title_links_to_overview':
				case 'max_ignore_elsewhere':
				case 'schema_on_single_page':
				case 'open_first_faq_on_page':
				case 'exclude_from_count':
				case 'exclude_from_search':
					$options[ $key ] = ( $value === '1' || $value === true );
					break;
				case 'slug':
					if ( ( $value = sanitize_title( $value ) ) !== $options['slug'] ) {
						$options['slug'] = $value;
						// flag for flush_rewrite_rules upon reload of the settings page
						update_option( 'ruigehond010_flag_flush_rewrite_rules', 'yes', true );
					}
					break;
				case 'max':
					if ( abs( intval( $value ) ) > 0 ) {
						$options[ $key ] = abs( intval( $value ) );
					}
					break;
				case 'output_schema': // this is an array of post id’s (can be empty) where you want to output schema
					$values   = array_map( 'intval', $value );
					$post_ids = array();
					foreach ( $values as $post_id ) {
						$post_ids = $this->registerPostByShortcode( $post_ids, $post_id );
					}
					$options['post_ids'] = $post_ids;
					break;
				case 'main_faq_page': // int which is a post->ID
					if ( ( $slug = get_post_field( 'post_name', (int) $value ) ) ) {
						$options['faq_page_slug'] = $slug;
						$options['faq_page_id'] = (int) $value;
					}
					break;
				case 'taxonomies': // check if it's an existing taxonomy
					if ( false === taxonomy_exists( $value ) ) {
						$value = 'category';
					}
				// intentional fall through, just validated the value
				// by default just accept the value
				default:
					$options[ $key ] = $value;
			}
		}

		return $options;
	}

	private function registerPostByShortcode( array $post_ids, int $post_id ): array {
		// find out what kind...
		$content = $this->decodePostContent( get_post_field( 'post_content', $post_id ) );
		if ( $content ) {
			$chunks = explode( '[faq-with-categories', $content );
			unset( $chunks[0] ); // first chunk never contains the attributes
			if ( 0 === count( $chunks ) ) { // shortcode is not present
				unset( $post_ids[ $post_id ] );

				return $post_ids;
			}
			foreach ( $chunks as $index => $chunk ) {
				$attributes = substr( $chunk, 0, strpos( $chunk, ']' ) );
				// you can ignore title-only="any value" (assume it is never added more than once per shortcode)
				if ( false !== ( $pos = strpos( $attributes, ' title-only="' ) )
				     && false !== ( $end = strpos( $attributes, '"', $pos + 13 ) ) ) {
					$attributes = substr( $attributes, 0, $pos ) . substr( $attributes, $end );
				}
				if ( '' === $attributes ) {
					if ( ( $term = $this->getDefaultTerm( $post_id ) ) ) {
						$post_ids[ $post_id ] = array( 'term' => $term );
					} else {
						$post_ids[ $post_id ] = true;
					}
					break;
				} elseif ( false !== ( $pos = strpos( $attributes, ' exclusive="' ) )
				           && false !== ( $end = strpos( $attributes, '"', $pos + 12 ) ) ) {
					// only handle exclusive terms
					$pos                  += 12;
					$post_ids[ $post_id ] = array( 'exclusive' => substr( $attributes, $pos, $end - $pos ) );
				}
			}
		}

		return $post_ids;
	}

	public function menuitem() {
		$menu_slug = current_user_can( 'manage_options' ) ? 'faq-with-categories-with-submenu' : 'faq-with-categories';
		// add top level page
		add_menu_page(
			'FAQ',
			'FAQ',
			'edit_posts',
			$menu_slug,
			array( $this, 'redirect_to_entries' ), // callback unused
			"data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxOC42OSAxNy44MSI+CiAgPGRlZnM+CiAgICA8c3R5bGU+CiAgICAgIC5jbHMtMSB7CiAgICAgICAgZmlsbDogI2E3YWFhZDsKICAgICAgfQogICAgPC9zdHlsZT4KICA8L2RlZnM+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSIxNi42IiB5PSI3LjU1IiB3aWR0aD0iLjYiIGhlaWdodD0iMy41OCIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMjYuMjQgLTcuNTUpIHJvdGF0ZSg5MCkiLz4KICA8cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Im03LjU0LDE0LjExdjIuNGMwLC43LjgsMS4zLDEuOCwxLjNzMS44LS42LDEuOC0xLjN2LTIuNGMtLjUuMy0xLjIuNS0xLjguNS0uNywwLTEuMy0uMi0xLjgtLjVaIi8+CiAgPGNpcmNsZSBjbGFzcz0iY2xzLTEiIGN4PSI5LjM0IiBjeT0iOS4zNCIgcj0iNC43NiIvPgogIDxyZWN0IGNsYXNzPSJjbHMtMSIgeD0iMS40OSIgeT0iNy41NSIgd2lkdGg9Ii42IiBoZWlnaHQ9IjMuNTgiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDExLjEzIDcuNTUpIHJvdGF0ZSg5MCkiLz4KICA8cmVjdCBjbGFzcz0iY2xzLTEiIHg9IjkuMDQiIHk9IjAiIHdpZHRoPSIuNiIgaGVpZ2h0PSIzLjU4Ii8+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSIxNC4zOSIgeT0iMi4yMSIgd2lkdGg9Ii42IiBoZWlnaHQ9IjMuNTgiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDcuMTMgLTkuMjEpIHJvdGF0ZSg0NSkiLz4KICA8cmVjdCBjbGFzcz0iY2xzLTEiIHg9IjMuNyIgeT0iMTIuOSIgd2lkdGg9Ii42IiBoZWlnaHQ9IjMuNTgiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDExLjU2IDEuNDcpIHJvdGF0ZSg0NSkiLz4KICA8cmVjdCBjbGFzcz0iY2xzLTEiIHg9IjE0LjM5IiB5PSIxMi45IiB3aWR0aD0iLjYiIGhlaWdodD0iMy41OCIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMzUuNDYgMTQuNjkpIHJvdGF0ZSgxMzUpIi8+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSIzLjciIHk9IjIuMjEiIHdpZHRoPSIuNiIgaGVpZ2h0PSIzLjU4IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg5LjY2IDQpIHJvdGF0ZSgxMzUpIi8+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSIxNi4wMiIgeT0iMTAuNDkiIHdpZHRoPSIuNiIgaGVpZ2h0PSIzLjU4IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgzMy45MiAxLjkpIHJvdGF0ZSgxMTIuNSkiLz4KICA8cmVjdCBjbGFzcz0iY2xzLTEiIHg9IjIuMDYiIHk9IjQuNzEiIHdpZHRoPSIuNiIgaGVpZ2h0PSIzLjU4IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg5LjI3IDYuOCkgcm90YXRlKDExMi41KSIvPgogIDxyZWN0IGNsYXNzPSJjbHMtMSIgeD0iMTEuOTQiIHk9Ii42MiIgd2lkdGg9Ii42IiBoZWlnaHQ9IjMuNTgiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEuODUgLTQuNSkgcm90YXRlKDIyLjUpIi8+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSIxNi4wMiIgeT0iNC43MSIgd2lkdGg9Ii42IiBoZWlnaHQ9IjMuNTgiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE2LjA4IC0xMS4wNykgcm90YXRlKDY3LjUpIi8+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSIyLjA2IiB5PSIxMC40OSIgd2lkdGg9Ii42IiBoZWlnaHQ9IjMuNTgiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEyLjgxIDUuNCkgcm90YXRlKDY3LjUpIi8+CiAgPHJlY3QgY2xhc3M9ImNscy0xIiB4PSI2LjE1IiB5PSIuNjIiIHdpZHRoPSIuNiIgaGVpZ2h0PSIzLjU4IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMy4zNCAyLjE3KSByb3RhdGUoMTU3LjUpIi8+Cjwvc3ZnPg==",
			27 // just under comments / reacties
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Settings', 'faq-with-categories' ), // page_title
			esc_html__( 'FAQ', 'faq-with-categories' ), // menu_title
			'edit_posts',
			$menu_slug,
			array( $this, 'redirect_to_entries', 'faq-with-categories' ) // callback unused
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Settings', 'faq-with-categories' ), // page_title
			esc_html__( 'Settings', 'faq-with-categories' ), // menu_title
			'manage_options',
			"$menu_slug-settings",
			array( $this, 'settingspage' ) // callback
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Order taxonomy', 'faq-with-categories' ), // page_title
			esc_html__( 'Order taxonomy', 'faq-with-categories' ), // menu_title
			'manage_options',
			"$menu_slug-order-taxonomy",
			array( $this, 'ordertaxonomypage' ) // callback
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Page settings', 'faq-with-categories' ), // page_title
			esc_html__( 'Page settings', 'faq-with-categories' ), // menu_title
			'manage_options',
			"$menu_slug-page-settings",
			array( $this, 'per_page_settings' ) // callback
		);
		global $submenu; // make the first entry go to the edit page of the faq post_type
		$submenu[ $menu_slug ][0] = array(
			esc_html__( 'FAQ', 'faq-with-categories' ),
			'edit_posts',
			'edit.php?post_type=ruigehond010_faq',
			'blub' // WHOA
		);
	}

	public function update_when_necessary() {
		// register current version, but keep incremental updates (for when someone skips a version)
		// on busy sites this can be called several times, so suppress the errors
		$this->wpdb->suppress_errors = true;
		if ( version_compare( $this->database_version, '1.1.0' ) < 0 ) {
			$sql = "ALTER TABLE {$this->order_table} ADD COLUMN 
				        t VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_520_ci' NOT NULL DEFAULT '';";
			// TODO query returns false on error, maybe it never works, what do we do then?
			$this->wpdb->query( $sql );
			$sql = "ALTER TABLE {$this->order_table} ADD COLUMN post_id INT;";
			$this->wpdb->query( $sql );
			$old_version = $this->setOption( 'version', '1.1.0' );
		}
		if ( version_compare( $this->database_version, '1.1.8' ) < 0 ) {
			$sql = "ALTER TABLE {$this->order_table} ADD CONSTRAINT ruigehond010_unique_{$this->order_table} UNIQUE (term_id);";
			$this->wpdb->query( $sql );
			$old_version = $this->setOption( 'version', '1.1.8' );
		}
		$this->wpdb->suppress_errors = false;
	}

	public function settingsLink( $links ) {
		$admin_url  = get_admin_url();
		$__faq      = esc_html__( 'FAQ', 'faq-with-categories' );
		$__settings = esc_html__( 'Settings', 'faq-with-categories' );
		array_unshift(
			$links,
			"<a href=\"edit.php?post_type=ruigehond010_faq\">{$__faq}</a>",
			"<a href=\"{$admin_url}admin.php?page=faq-with-categories-with-submenu-settings\">{$__settings}</a>"
		);

		return $links;
	}

	public function activate() {
		$table_name = $this->order_table;
		if ( $this->wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$sql = "CREATE TABLE $table_name (
						term_id INT NOT NULL,
						post_id INT,
						t VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_520_ci' NOT NULL DEFAULT '',
						o INT NOT NULL DEFAULT 1);";
			$this->wpdb->query( $sql );
			$sql = "ALTER TABLE $table_name ADD CONSTRAINT ruigehond010_unique_$table_name UNIQUE (term_id);";
			$this->wpdb->query( $sql );
		}
		// register the current version
		$this->setOption( 'database_version', RUIGEHOND010_VERSION );
	}

	public function uninstall() {
		// remove the ordering table
		if ( $this->wpdb->get_var( "SHOW TABLES LIKE '$this->order_table'" ) == $this->order_table ) {
			$sql = "DROP TABLE $this->order_table";
			$this->wpdb->query( $sql );
		}
		// remove the post_meta entries
		delete_post_meta_by_key( '_ruigehond010_exclusive' );
		// remove settings
		delete_option( 'ruigehond010' );
		// TODO provide checkbox in settings to remove the posts on uninstall
	}
}
