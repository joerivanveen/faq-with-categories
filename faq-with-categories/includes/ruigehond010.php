<?php

declare( strict_types=1 );

namespace ruigehond010;

// TODO BUG if you put central faq short_code or any exclusive tag on multiple pages, the $on option keeps getting updated
use ruigehond_0_4_0;

defined( 'ABSPATH' ) or die();

class ruigehond010 extends ruigehond_0_4_0\ruigehond {
	private $name, $database_version, $taxonomies, $slug, $choose_option, $choose_all, $search_faqs, $table_prefix,
		$more_button_text, $no_results_warning, $max, $max_ignore_elsewhere,
		$order_table, $header_tag,
		$title_links_to_overview, $schema_on_single_page, $exclude_from_search, $exclude_from_count, $queue_frontend_css;
	// variables that hold cached items
	private $terms;

	public function __construct( $title = 'Ruige hond' ) {
		parent::__construct( 'ruigehond010' );
		$this->name        = __CLASS__;
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
					'name'          => __( 'FAQ', 'faq-with-categories' ),
					'singular_name' => __( 'FAQ', 'faq-with-categories' ),
				),
				'public'              => true,
				'has_archive'         => true,
				'taxonomies'          => array( $this->taxonomies ),
				'exclude_from_search' => $this->exclude_from_search,
				'rewrite'             => array( 'slug' => $this->slug ),
				// remember to flush_rewrite_rules(); when this changes
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
			add_action( 'save_post', array( $this, 'meta_box_save' ) );
			add_action( 'admin_notices', array( $this, 'displayAdminNotices' ) );
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
			echo $message;
			echo '</div>';
		}
		delete_option( 'ruigehond010_admin_multi_message' );
		//
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
		} elseif ( ( $on = $this->getOption( 'post_ids' ) ) and isset( $on[ $post_id ] ) ) {
			// output the exclusive ones and main faq only when not on single page
			echo $this->getSchemaFromPosts( $this->getPosts( $on[ $post_id ] ) );
		}
	}

	public function getSchemaFromPosts( $posts ) {
		ob_start();
		$last_index = count( $posts ) - 1;
		echo '<script type="application/ld+json" id="ruigehond010_faq_schema">{"@context": "https://schema.org","@type": "FAQPage","mainEntity": [';
		foreach ( $posts as $index => $post ) {
			echo '{"@type":"Question","name":';
			echo json_encode( $post->post_title );
			echo ',"acceptedAnswer":{"@type":"Answer","text":';
			echo json_encode( $post->post_content );
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
	 * @return string The term or null when not found
	 * @since 1.1.0
	 */
	public function getDefaultTerm( $post_id ) {
		$rows    = $this->getTerms();
		$post_id = strval( $post_id );
		foreach ( $rows as $term_id => $arr ) {
			foreach ( $arr as $index => $term ) {
				if ( isset( $term['post_id'] ) and $post_id === strval( $term['post_id'] ) ) {
					return $term['term'];
				}
			}
		}

		return null;
	}

	public function getHtmlForFrontend( $attributes = [], $content = null, $short_code = 'faq-with-categories' ) {
		if ( ( ! $post_id = get_the_ID() ) ) {
			return '';
		}
		$chosen_exclusive = isset( $attributes['exclusive'] ) ? $attributes['exclusive'] : null;
		$chosen_term      = isset( $attributes['category'] ) ? strtolower( $attributes['category'] ) : null;
		$filter_term      = isset( $_GET['category'] ) ? strtolower( $_GET['category'] ) : null;
		$quantity         = isset( $attributes['quantity'] ) ? intval( $attributes['quantity'] ) : null;
		$title_only       = isset( $attributes['title-only'] ); // no matter the value, when set we do title only
		// when you have assigned a page to a term, also use that term when you’re on that specific page
		if ( is_null( $chosen_term ) ) {
			$chosen_term = $this->getDefaultTerm( $post_id );
		}
		// several types of html can be got with this
		// 1) the select boxes for the filter (based on term)
		if ( $short_code === 'faq-with-categories-filter' ) {
			// ->getTerms() = fills by sql SELECT term_id, parent, count, term FROM etc.
			$rows = $this->getTerms();
			// write the html lists
			ob_start();
			foreach ( $rows as $parent => $options ) {
				echo '<select class="ruigehond010 faq choose-category" data-ruigehond010_parent="';
				echo $parent;
				if ( $parent === 0 ) {
					echo '" style="display: block"><option>'; // display block to prevent repainting default situation
					echo $this->choose_option;
				} else {
					echo '"><option>';
					echo $this->choose_all;
				}
				echo '</option>';
				foreach ( $options as $index => $option ) {
					echo '<option data-ruigehond010_term_id="';
					echo $option['term_id'];
					echo '" value="term-';
					echo $option['term_id'];
					//echo htmlentities($term = $option['term']);
					if ( strtolower( ( $term = $option['term'] ) ) === $filter_term ) {
						echo '" selected="selected';
					}
					echo '">';
					echo $term;
					echo '</option>';
				}
				echo '</select>';
			}

			return ob_get_clean();
		} elseif ( $short_code === 'faq-with-categories-search' ) {
			return "<input type=\"text\" name=\"search\" class=\"search-field ruigehond010 faq\" id=\"ruigehond010_search\" placeholder=\"$this->search_faqs\"/>";
		} else { // 2) all the posts, filtered by 'exclusive' or 'term'
			// only do the whole registering if you output schema on any of those pages
			if ( ! $this->schema_on_single_page ) {
				// only register exclusive displays and the full faq page for outputting schema
				if ( is_null( $chosen_term ) ) {
					// register the shortcode being used here, for outputSchema method :-)
					// don’t update if it’s all faqs but with a quantity
					$register = ( is_string( $chosen_exclusive ) ) ? $chosen_exclusive : ( ( is_null( $quantity ) ) ? true : false );
					if ( ( $on = $this->getOption( 'post_ids' ) ) ) {
						// remove any reference for this $register (exclusive or ‘true’ for overview), it will be set to the correct one later
						//if (false === isset($on[$post_id])) {
						// remove the original id if any
						foreach ( $on as $on_post_id => $value ) {
							if ( $value === $register ) {
								// check for duplicate tags, you may need to warn the admin about it
								if ( $post_id !== $on_post_id ) {
									$temps  = get_the_content( null, false, $on_post_id );
									$danger = false;
									if ( true === $register ) { // disallowed is [faq-with-categories without ‘exclusive’ or ‘category’
										if ( false !== ( $pos = strpos( $temps, '[faq-with-categories' ) ) ) {
											if ( false === strpos( $temps, ' exclusive="', $pos ) and
											     false === strpos( $temps, ' category="', $pos ) ) {
												$danger = true;
											}
										}
									} else {
										if ( false !== strpos( $temps, 'exclusive="' . $register . '"' ) ) {
											$danger = true;
										}
									}
									if ( true === $danger ) {
										ob_start();
										echo sprintf(
											__( 'Looks like the tag ‘%s’ is used multiple times.', 'faq-with-categories' ),
											( true === $register ) ? '[faq-with-categories]' : 'exclusive="' . $register . '"' );
										echo ' ';
										echo __( 'Found on', 'faq-with-categories' );
										echo ': <a href="';
										echo( ( $perm = get_permalink( $post_id ) ) );
										echo '">';
										echo $perm;
										echo '</a> &amp; <a href="';
										echo( ( $perm = get_permalink( $on_post_id ) ) );
										echo '">';
										echo $perm;
										echo '</a><br/>';
										echo __( 'For this plugin to function properly, you can use the overview or any exclusive tag only once in your entire site.', 'faq-with-categories' );
										update_option( 'ruigehond010_admin_multi_message', ob_get_clean() );
									}
								}
								unset( $on[ $on_post_id ] );
							}
						}
						//} else { // remove this one, it will be set later if applicable
						//    unset($on[$post_id]);
						//}
						// register this id (also updates if e.g. the exclusive value changes)
						if ( false !== $register ) {
							$on[ $post_id ] = $register;
						}
					} else { // set it for the first time
						$on = [ $post_id => true ];
					}
					$this->setOption( 'post_ids', $on );
					if ( $register === true and $title_only === false ) {
						$this->setOption( 'faq_page_slug', get_post_field( 'post_name', $post_id ) );
					}
				} else {
					if ( ( $on = $this->getOption( 'post_ids' ) ) ) {
						if ( isset( $on[ $post_id ] ) ) {
							unset( $on[ $post_id ] );
							$this->setOption( 'post_ids', $on );
						}
					}
				}
			}
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
						echo __( 'Please visit the FAQ page once so the plugin knows where to link to.', 'faq-with-categories' );
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
			echo '<ul id="ruigehond010_faq" class="ruigehond010 faq posts ';
			if ( $chosen_exclusive ) {
				echo strtolower( htmlentities( $chosen_exclusive ) );
			}
			if ( true === $this->max_ignore_elsewhere and
			     ( false === is_null( $chosen_term ) or false === is_null( $chosen_exclusive ) )
			) {
				echo '" data-max_ignore="1'; // set the max to be ignored on pages that display subsets of the faq
			}
			echo '" data-max="';
			echo $this->max;
			echo '" data-more_button_text="';
			echo htmlentities( $this->more_button_text );
			echo '">';
			// apparently under some circumstances the first time you call apply_filters it doesn’t do anything
			apply_filters( 'the_content', 'bug' );
			// so now apply_filters is ready to apply some filters on the post content in the following loop:
			$h_open  = "<$this->header_tag class=\"faq-header\">";
			$h_close = "</$this->header_tag>";
			foreach ( $posts as $index => $post ) {
				if ( $index === $quantity ) {
					break;
				}
				echo '<li class="ruigehond010_post term-';
				echo strtolower( implode( ' term-', $post->term_ids ) );
				if ( $post->exclusive ) {
					echo '" data-exclusive="';
					echo $post->exclusive;
				}
				echo '" data-post_id="';
				echo $post->ID;
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
					echo $post->post_title;
					echo '</a>';
				}
				echo '</li>';
			}
			// no results warning
			echo '<li id="ruigehond010_no_results_warning" style="display: none;">';
			echo $this->no_results_warning;
			echo '</li>';
			// end list
			echo '</ul>';
			$str = ob_get_contents();
			ob_end_clean();

			return $str;
		}
	}

	private function getTerms() {
		if ( isset( $this->terms ) ) {
			return $this->terms;
		} // return cached value if available
		// get the terms for this registered taxonomies from the db
		$taxonomies = addslashes( sanitize_text_field( $this->taxonomies ) ); // just for the h#ck of it
		$wp_prefix  = $this->wpdb->prefix;
		$sql        = "SELECT t.term_id, tt.parent, t.name AS term, o.t, o.post_id 
                FROM {$wp_prefix}terms t
                INNER JOIN {$wp_prefix}term_taxonomy tt ON t.term_id = tt.term_id
                LEFT OUTER JOIN {$this->order_table} o ON o.term_id = t.term_id
                WHERE tt.taxonomy = '$taxonomies'
                ORDER BY o.o, t.name;";
		$rows       = $this->wpdb->get_results( $sql, OBJECT );
		$terms      = array();
		foreach ( $rows as $key => $row ) {
			if ( ! isset( $terms[ $parent = intval( $row->parent ) ] ) ) {
				$terms[ $parent ] = array();
			}
			$terms[ $parent ][] = array(
				'term_id' => intval( $row->term_id ),
				'term'    => $row->term,
				't'       => $row->t,
				'post_id' => $row->post_id,
			);
		}
		$this->terms = $terms;

		return $terms;
	}

	/**
	 * @param string|null $exclusive
	 * @param null $term
	 *
	 * @return array the rows from db as \stdClasses in an indexed array
	 */
	private function getPosts( $exclusive = null, $term = null ) {
		$term_ids  = null; // we are going to collect all the term_ids that fall under the requested $term
		$wp_prefix = $this->wpdb->prefix;
		if ( is_string( $term ) ) {
			$sql_term = addslashes( $term );
			$sql      = "select term_id from {$wp_prefix}terms t where lower(t.name) = '$sql_term';";
			// now for as long as rows with term_ids are returned, keep building the array
			while ( $rows = $this->wpdb->get_results( $sql ) ) {
				foreach ( $rows as $index => $row ) {
					$term_ids[] = $row->term_id;
				}
				// new sql selects all the children from the term_ids that are in the array
				$str_term_ids = implode( ',', $term_ids );
				$sql          = "select term_id from {$wp_prefix}term_taxonomy tt 
                        where tt.parent in ($str_term_ids) 
                        and term_id not in ($str_term_ids);"; // excluding the term_ids already in the array
				// so it returns no rows if there are no more children, ending the while loop
			}
		}
		$sql = "select p.ID, p.post_title, p.post_content, p.post_date, p.post_name, t.term_id, pm.meta_value AS exclusive from
                {$wp_prefix}posts p left outer join 
                {$wp_prefix}term_relationships tr on tr.object_id = p.ID left outer join 
                {$wp_prefix}term_taxonomy tt on tt.term_taxonomy_id = tr.term_taxonomy_id left outer join 
                {$wp_prefix}terms t on t.term_id = tt.term_id left outer join 
                {$wp_prefix}postmeta pm on pm.post_id = p.ID and pm.meta_key = '_ruigehond010_exclusive' 
                where p.post_type = 'ruigehond010_faq' and post_status = 'publish'";
		// setup the where condition regarding exclusive and term....
		if ( is_array( $term_ids ) ) {
			$sql .= ' and t.term_id IN (' . implode( ',', $term_ids ) . ')';
		} elseif ( is_string( $exclusive ) ) {
			$sql .= ' and pm.meta_value = \'' . addslashes( sanitize_text_field( $exclusive ) ) . '\'';
		}
		$sql        = "$sql order by p.post_date desc;";
		$rows       = $this->wpdb->get_results( $sql, OBJECT );
		$return_arr = array();
		$current_id = 0;
		foreach ( $rows as $index => $row ) {
			if ( $row->ID === $current_id ) { // add the category to the current return value
				$return_arr[ count( $return_arr ) - 1 ]->term_ids[] = $row->term_id;
			} else { // add the row, when not exclusive is requested posts without terms must be filtered out
				if ( ( $term_id = $row->term_id ) or $exclusive ) {
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

	public function handle_input( $args ) {
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
						$this->upsert( $this->order_table,
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
									$returnObject->add_message( sprintf( __( 'post_id %d not found', 'faq-with-categories' ), $post_id ), 'warn' );
								}
							} else {
								$post_title = $value;
								if ( '' === $value ) {
									$update = array( 't' => '', 'post_id' => null );
								} elseif ( $post_id = $this->wpdb->get_var( "SELECT ID 
										FROM {$wp_prefix}posts WHERE post_title = '" . addslashes( $post_title ) . "';" ) ) {
									$args['value'] = "$post_title ($post_id)";
									$update        = array( 't' => $args['value'], 'post_id' => $post_id );
								} else {
									$update              = array( 't' => $args['value'], 'post_id' => 0 );
									$args['nonexistent'] = true;
									$returnObject->add_message( sprintf( __( 'Could not find post_id based on title: %s', 'faq-with-categories' ), $post_title ), 'warn' );
								}
							}
					}
					if ( count( $update ) > 0 ) {
						$rowsaffected = $this->upsert(
							$this->table_prefix . $table_name, $update,
							array( $id_column => $id ) );
						if ( $rowsaffected === 0 ) {
							$returnObject->add_message( __( 'Update with same value not necessary...', 'faq-with-categories' ), 'warn' );
						}
						if ( $rowsaffected === false ) {
							$returnObject->add_message( __( 'Operation failed', 'faq-with-categories' ), 'error' );
						} else {
							$returnObject->set_success( true );
							$args['value'] = $this->wpdb->get_var(
								"SELECT $column_name FROM {$this->table_prefix}$table_name WHERE $id_column = $id;" );
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
				return $this->getReturnObject( sprintf( __( 'Did not understand handle %s', 'faq-with-categories' ),
					var_export( $args['handle'], true ) ) );
		}
		$returnObject->set_data( $args );

		return $returnObject;
	}

	private function upsert( string $table_name, array $values, array $where ): int {
		$where_condition = 'WHERE 1 = 1';
		foreach ( $where as $key => $value ) {
			$key = addslashes( $key );
			if ( true === is_string( $value ) ) {
				$value = addslashes( $value );
			}
			$where_condition = "$where_condition AND $key = '$value'";
		}
		if ( $this->wpdb->get_var( "SELECT EXISTS (SELECT 1 FROM $table_name $where_condition);" ) ) {
			$rows_affected = $this->wpdb->update( $table_name, $values, $where );
		} else {
			$rows_affected = $this->wpdb->insert( $table_name, $values + $where );
		}

		return $rows_affected;
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
		echo $obj['args']['exclusive'];
		echo '"/> <label for="ruigehond010_exclusive">';
		echo __( 'The tag this FAQ entry is exclusive to, use it in a shortcode to summon the entry. Note that it will still be displayed for the taxonomies that are checked.', 'faq-with-categories' );
		echo '</label>';
	}

	function meta_box_save( $post_id ) {
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

	public function ordertaxonomypage() {
		wp_enqueue_script( 'ruigehond010_admin_javascript', plugin_dir_url( __FILE__ ) . 'admin.js', array(
			'jquery-ui-droppable',
			'jquery-ui-sortable',
			'jquery'
		), RUIGEHOND010_VERSION );
		echo '<div class="wrap ruigehond010"><h1>';
		echo esc_html( get_admin_page_title() );
		echo '</h1><p>';
		echo __( 'This page only concerns itself with the order. The hierarchy is determined by the taxonomy itself.', 'faq-with-categories' );
		echo '<br/>';
		echo __( 'If you assign a page to a taxonomy, the faq shortcut on that page will display faq-posts from that taxonomy.', 'faq-with-categories' );
		echo '</p><hr/>';
		$terms = $this->getTerms(); // these are ordered to the best of the knowledge of the system already, but with parents
		foreach ( $terms as $index => $sub_terms ) {
			echo '<section class="rows-sortable">';
			foreach ( $sub_terms as $o => $term ) {
				$term_id = $term['term_id'];
				$t       = $term['t'] ?? '';
				echo '<div class="ruigehond010-order-term" data-id="';
				echo $term_id;
				echo '" data-inferred_order="';
				echo $o;
				echo '">';
				// ajax input to link a page to the taxonomy / explaining the taxonomy
				echo '<input type="text" data-id_column="term_id" data-id="';
				echo $term_id;
				echo '" data-handle="update" data-table_name="taxonomy_o" data-column_name="t" data-value="';
				echo htmlentities( $t );
				echo '" value="';
				echo htmlentities( $t );
				echo '"	class="ruigehond010 input post_title ajaxupdate ajaxsuggest tabbed';
				if ( '0' === $term['post_id'] ) {
					echo ' nonexistent';
				}
				echo '"/>';
				// ordering handle
				echo '<div class="sortable-handle">';
				echo $term['term'];
				echo '</div></div>';
			}
			echo '</section><hr/>';
		}
		echo '</div>';
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
		echo __( 'FAQS are always sorted by published date descending, so newest entries are first. By default they are output as an accordion list with the first one opened.', 'faq-with-categories' );
		echo '<br/>';
		// #TRANSLATORS: string inserted is an example of a querystring to pre-filter for a category
		echo sprintf( __( 'You can link to your general faq page with a category in the querystring (e.g. %s) to pre-filter the faqs.', 'faq-with-categories' ), '<strong>?category=test%20category</strong>' );
		echo '<br/>';
		echo __( 'You may use the following shortcodes, of course certain combinations do not make sense and may produce erratic behaviour.', 'faq-with-categories' );
		echo '<br/>';
		echo sprintf( __( '%s produces the default list for the central FAQ page and outputs FAQ snippets schema in the head.', 'faq-with-categories' ), '<strong>[faq-with-categories]</strong>' );
		echo '<br/>';
		echo sprintf( __( '%s produces a filter menu according to the chosen taxonomy using the specified order.', 'faq-with-categories' ), '<strong>[faq-with-categories-filter]</strong>' );
		echo '<br/>';
		echo sprintf( __( '%s produces a search box that will perform client-side lookup through the faqs.', 'faq-with-categories' ), '<strong>[faq-with-categories-search]</strong>' );
		echo '<br/>';
		// #TRANSLATORS: 1 is a tag, 2 indicates the NOTE at the bottom with an asterisk (*)
		echo sprintf( __( '%1$s %2$s limits the quantity of the faqs to 5, or use another number.', 'faq-with-categories' ), '[faq-with-categories <strong>quantity="5"</strong>]', '<em>(*)</em>' );
		echo ' ';
		echo __( 'This will NOT output FAQ snippets schema in the head.', 'faq-with-categories' );
		echo '<br/>';
		// #TRANSLATORS: 1 is a tag, 2 indicates the NOTE at the bottom with an asterisk (*)
		echo sprintf( __( '%1$s %2$s display only faqs for the specified category (case insensitive).', 'faq-with-categories' ), '[faq-with-categories <strong>category="category name"</strong>]', '<em>(*)</em>' );
		echo ' ';
		echo __( 'This will NOT output FAQ snippets schema in the head.', 'faq-with-categories' );
		echo '<br/>';
		// #TRANSLATORS: 1 is a tag, 2 indicates the NOTE at the bottom with an asterisk (*)
		echo sprintf( __( '%1$s %2$s any tag you specified under a faq entry in the box, will gather all faqs with that tag for display.', 'faq-with-categories' ), '[faq-with-categories <strong>exclusive="your tag"</strong>]', '<em>(*)</em>' );
		echo '<br/>';
		echo sprintf( __( '%s outputs the list as links rather than as an accordion.', 'faq-with-categories' ), '[faq-with-categories <strong>title-only="any value"</strong>]' );
		echo '<br/><em>(*) ';
		echo __( 'NOTE: only a limited number of faqs will be present on the page so search and filter will not work.', 'faq-with-categories' );
		echo '</em></p>';
		echo '<form action="options.php" method="post">';
		// output security fields for the registered setting
		settings_fields( 'ruigehond010' );
		// output setting sections and their fields
		do_settings_sections( 'ruigehond010' );
		// output save settings button
		submit_button( __( 'Save Settings', 'faq-with-categories' ) );
		echo '</form></div>';
	}

	public function settings() {
		if ( false === $this->onSettingsPage( 'faq-with-categories' ) ) {
			return;
		}
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}
		register_setting( 'ruigehond010', 'ruigehond010', array( $this, 'settings_validate' ) );
		// register a new section in the page
		add_settings_section(
			'global_settings', // section id
			__( 'Options', 'faq-with-categories' ), // title
			function () {
			}, //callback
			'ruigehond010' // page id
		);
		$labels = array(
			'taxonomies'              => __( 'Type the taxonomy you want to use for the categories.', 'faq-with-categories' ),
			'slug'                    => __( 'Slug for the individual faq entries (optional).', 'faq-with-categories' ),
			'title_links_to_overview' => __( 'When using title-only in shortcodes, link to the overview rather than individual FAQ page.', 'faq-with-categories' ),
			'schema_on_single_page'   => __( 'Output the faq schema on individual page rather than overview.', 'faq-with-categories' ),
			'choose_option'           => __( 'The ‘choose / show all’ option in top most select list.', 'faq-with-categories' ),
			'choose_all'              => __( 'The ‘choose / show all’ option in subsequent select lists.', 'faq-with-categories' ),
			'search_faqs'             => __( 'The placeholder in the search bar for the faqs.', 'faq-with-categories' ),
			'header_tag'              => __( 'Tag used for the header on faq page (e.g. h4), invalid input may cause errors on your page.', 'faq-with-categories' ),
			'max'                     => __( 'Number of faqs shown before ‘Show more’ button.', 'faq-with-categories' ),
			'max_ignore_elsewhere'    => __( 'Only use the more button on the central FAQ page, nowhere else.', 'faq-with-categories' ),
			'more_button_text'        => __( 'The text on the ‘Show more’ button.', 'faq-with-categories' ),
			'no_results_warning'      => __( 'Text shown when search or filter results in 0 faqs found.', 'faq-with-categories' ),
			'exclude_from_search'     => __( 'Will exclude the FAQ posts from site search queries.', 'faq-with-categories' ),
			'exclude_from_count'      => __( 'FAQ posts will not count towards total posts in taxonomies.', 'faq-with-categories' ),
			'queue_frontend_css'      => __( 'By default a small css-file is output to the frontend to format the entries. Uncheck to handle the css yourself.', 'faq-with-categories' ),
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
			case 'exclude_from_count':
			case 'title_links_to_overview':
			case 'max_ignore_elsewhere':
			case 'schema_on_single_page':
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
				echo '" style="width: 162px" class="', $args['class_name'], '"/> <label>', $args['label_for'], '</label>';
		}
	}

	public function settings_validate( $input ) {
		$options = (array) get_option( 'ruigehond010' );
		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				// on / off flags (1 vs 0 on form submit, true / false otherwise
				case 'queue_frontend_css':
				case 'exclude_from_search':
				case 'title_links_to_overview':
				case 'max_ignore_elsewhere':
				case 'exclude_from_count':
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

	public function menuitem() {
		$menu_slug = current_user_can( 'manage_options' ) ? 'faq-with-categories-with-submenu' : 'faq-with-categories';
		// add top level page
		add_menu_page(
			'FAQ',
			'FAQ',
			'edit_posts',
			$menu_slug,
			array( $this, 'redirect_to_entries' ), // callback unused
			'dashicons-lightbulb',
			27 // just under comments / reacties
		);
		add_submenu_page(
			$menu_slug,
			__( 'Settings', 'faq-with-categories' ), // page_title
			__( 'FAQ', 'faq-with-categories' ), // menu_title
			'edit_posts',
			$menu_slug,
			array( $this, 'redirect_to_entries' ) // callback unused
		);
		add_submenu_page(
			$menu_slug,
			__( 'Settings', 'faq-with-categories' ), // page_title
			__( 'Settings', 'faq-with-categories' ), // menu_title
			'manage_options',
			"$menu_slug-settings",
			array( $this, 'settingspage' ) // callback
		);
		add_submenu_page(
			$menu_slug,
			__( 'Order taxonomy', 'faq-with-categories' ), // page_title
			__( 'Order taxonomy', 'faq-with-categories' ), // menu_title
			'manage_options',
			"$menu_slug-order-taxonomy",
			array( $this, 'ordertaxonomypage' ) // callback
		);
		global $submenu; // make the first entry go to the edit page of the faq post_type
		$submenu[ $menu_slug ][0] = array(
			__( 'FAQ', 'faq-with-categories' ),
			'edit_posts',
			'edit.php?post_type=ruigehond010_faq',
			'blub' // WHOA
		);
	}

	public function update_when_necessary() {
		// register current version, but keep incremental updates (for when someone skips a version)
		if ( version_compare( $this->database_version, '1.1.0' ) < 0 ) {
			// on busy sites this can be called several times, so suppress the errors
			$this->wpdb->suppress_errors = true;
			$sql                         = "ALTER TABLE {$this->order_table} ADD COLUMN 
				        t VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_520_ci' NOT NULL DEFAULT '';";
			// TODO query returns false on error, maybe it never works, what do we do then?
			$this->wpdb->query( $sql );
			$sql = "ALTER TABLE {$this->order_table} ADD COLUMN post_id INT;";
			$this->wpdb->query( $sql );
			$old_version                 = $this->setOption( 'version', '1.1.0' );
			$this->wpdb->suppress_errors = false;
		}
		if ( version_compare( $this->database_version, '1.1.8' ) < 0 ) {
			// on busy sites this can be called several times, so suppress the errors
			$this->wpdb->suppress_errors = true;
			$sql                         = "ALTER TABLE {$this->order_table} ADD CONSTRAINT ruigehond010_unique_{$this->order_table} UNIQUE (term_id);";
			$this->wpdb->query( $sql );
			$old_version                 = $this->setOption( 'version', '1.1.8' );
			$this->wpdb->suppress_errors = false;
		}
	}

	public function install() {
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

	public function deactivate() {
		// nothing to do here
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
