<?php
/**
 * This file has ruigehond010 namespace.
 * ruigehond010 namespace holds the Plugin class ruigehond010
 * the widget for displaying the data frontend
 * and some other classes specific to this plugin.
 */

namespace ruigehond010 {

    use ruigehond_0_3_4;

    defined('ABSPATH') or die();

    class ruigehond010 extends ruigehond_0_3_4
    {
        private $name, $database_version, $taxonomies, $slug, $choose_option, $choose_all, $search_faqs, $order_table,
            $title_links_to_overview, $exclude_from_search, $exclude_from_count, $queue_frontend_css;
        // variables that hold cached items
        private $terms;

        public function __construct($title = 'Ruige hond')
        {
            parent::__construct('ruigehond010');
            $this->name = __CLASS__;
            $this->order_table = $this->wpdb->prefix . 'ruigehond010_taxonomy_o';
            // set some options
            $this->database_version = $this->getOption('database_version', '0.0.0');
            $this->taxonomies = $this->getOption('taxonomies', 'category');
            $this->slug = $this->getOption('slug', 'ruigehond010_faq'); // standard the post_type is used by WP
            $this->title_links_to_overview = $this->getOption('title_links_to_overview', false);
            $this->choose_option = $this->getOption('choose_option', __('Choose option', 'faq-with-categories'));
            $this->choose_all = $this->getOption('choose_all', __('All', 'faq-with-categories'));
            $this->search_faqs = $this->getOption('search_faqs', __('Search faqs', 'faq-with-categories'));
            $this->exclude_from_search = $this->getOption('exclude_from_search', true);
            $this->exclude_from_count = $this->getOption('exclude_from_count', true);
            $this->queue_frontend_css = $this->getOption('queue_frontend_css', true);
            // Add custom callback for taxonomy counter, if we do not want the faq posts to be counted towards the total
            if (true === $this->exclude_from_count) {
                add_filter('register_taxonomy_args', function ($args, $name) {
                    if ($name === $this->taxonomies) {
                        $args['update_count_callback'] = array($this, 'update_count_callback');
                    }

                    return $args;
                }, 20, 2);
            }
        }

        public function initialize()
        {
            /*if (current_user_can('administrator')) {
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
            }*/
            $this->load_translations('faq-with-categories');
            /**
             * register custom post type for faqs
             */
            register_post_type('ruigehond010_faq',
                array(
                    'labels' => array(
                        'name' => __('FAQ', 'faq-with-categories'),
                        'singular_name' => __('FAQ', 'faq-with-categories'),
                    ),
                    'public' => true,
                    'has_archive' => true,
                    'taxonomies' => array($this->taxonomies),
                    'exclude_from_search' => $this->exclude_from_search,
                    'rewrite' => array('slug' => $this->slug), // remember to flush_rewrite_rules(); when this changes
                    'show_in_menu' => false,
                    'show_in_admin_bar' => true,
                )
            );
            // regular stuff
            if (is_admin()) {
                add_action('admin_init', array($this, 'settings'));
                add_action('admin_menu', array($this, 'menuitem'));
                add_action('add_meta_boxes', array($this, 'meta_box_add')); // in the box the user set the exclusive value
                add_action('save_post', array($this, 'meta_box_save'));
            } else {
                wp_enqueue_script('ruigehond010_javascript', plugin_dir_url(__FILE__) . 'client.js', array('jquery'));
                if ($this->queue_frontend_css) { // only output css when necessary
                    wp_enqueue_style('ruigehond010_stylesheet_display', plugin_dir_url(__FILE__) . 'display.css', [], RUIGEHOND010_VERSION);
                }
                add_action('wp_head', array($this, 'outputSchema'));
                add_shortcode('faq-with-categories', array($this, 'getHtmlForFrontend'));
                add_shortcode('faq-with-categories-filter', array($this, 'getHtmlForFrontend'));
                add_shortcode('faq-with-categories-search', array($this, 'getHtmlForFrontend'));
            }
        }

        public function update_count_callback($terms, $taxonomy)
        {
            // got from: https://ivanpaulin.com/exclude-pages-taxonomy-counter-wordpress/
            // https://codex.wordpress.org/Function_Reference/register_taxonomy -> update_count_callback
            unregister_taxonomy_for_object_type($this->taxonomies, 'ruigehond010_faq');
            // you can't call wp_update_term_count, because that will call the current function, resulting in a loop
            // so we just duplicate the code here, classy right
            // https://developer.wordpress.org/reference/functions/wp_update_term_count_now/
            $object_types = (array)$taxonomy->object_type;
            foreach ($object_types as &$object_type) {
                if (0 === strpos($object_type, 'attachment:'))
                    list($object_type) = explode(':', $object_type);
            }
            if ($object_types == array_filter($object_types, 'post_type_exists')) {
                // Only post types are attached to this taxonomy
                _update_post_term_count($terms, $taxonomy);
            } else {
                // Default count updater
                _update_generic_term_count($terms, $taxonomy);
            }
            // re-register the taxonomy for this post type
            register_taxonomy_for_object_type($this->taxonomies, 'ruigehond010_faq');
        }

        public function outputSchema()
        {
            if (!$post_id = get_the_ID()) return;
            if (($on = $this->getOption('post_ids')) and isset($on[$post_id])) {
                echo $this->getSchemaFromPosts($this->getPosts($on[$post_id]));
            }
        }

        public function getSchemaFromPosts($posts)
        {
            ob_start();
            $last_index = count($posts) - 1;
            echo '<script type="application/ld+json" id="ruigehond010_faq_schema">{"@context": "https://schema.org","@type": "FAQPage","mainEntity": [';
            foreach ($posts as $index => $post) {
                echo '{"@type":"Question","name":';
                echo json_encode($post->post_title);
                echo ',"acceptedAnswer":{"@type":"Answer","text":';
                echo json_encode($post->post_content);
                echo '}}';
                if ($index < $last_index) echo ',';
            }
            echo ']}</script>';
            $str = ob_get_contents();
            ob_end_clean();

            return $str;
        }

        public function getHtmlForFrontend($attributes = [], $content = null, $short_code = 'faq-with-categories')
        {
            if (!$post_id = get_the_ID()) return '';
            $chosen_exclusive = isset($attributes['exclusive']) ? $attributes['exclusive'] : null;
            $chosen_term = isset($attributes['category']) ? strtolower($attributes['category']) : null;
            $filter_term = isset($_GET['category']) ? strtolower($_GET['category']) : null;
            $quantity = isset($attributes['quantity']) ? intval($attributes['quantity']) : null;
            $title_only = isset($attributes['title-only']); // no matter the value, when set we do title only
            // several types of html can be got with this
            // 1) the select boxes for the filter (based on term)
            if ($short_code === 'faq-with-categories-filter') {
                // ->getTerms() = fills by sql SELECT term_id, parent, count, term FROM etc.
                $rows = $this->getTerms();
                // write the html lists
                ob_start();
                foreach ($rows as $parent => $options) {
                    echo '<select class="ruigehond010 faq choose-category" data-ruigehond010_parent="';
                    echo $parent;
                    if ($parent === 0) {
                        echo '" style="display: block"><option>'; // display block to prevent repainting default situation
                        echo $this->choose_option;
                    } else {
                        echo '"><option>';
                        echo $this->choose_all;
                    }
                    echo '</option>';
                    foreach ($options as $index => $option) {
                        echo '<option data-ruigehond010_term_id="';
                        echo $option['term_id'];
                        echo '" value="term-';
                        echo $option['term_id'];
                        //echo htmlentities($term = $option['term']);
                        if (strtolower(($term = $option['term'])) === $filter_term) echo '" selected="selected';
                        echo '">';
                        echo $term;
                        echo '</option>';
                    }
                    echo '</select>';
                }
                $str = ob_get_contents();
                ob_end_clean();

                return $str;
            } elseif ($short_code === 'faq-with-categories-search') {
                $str = '<input type="text" name="search" class="search-field ruigehond010 faq" id="ruigehond010_search" placeholder="';
                $str .= $this->search_faqs;
                $str .= '"/>';

                return $str;
            } else { // 2) all the posts, filtered by 'exclusive' or 'term'
                // only register exclusive displays and the full faq page
                if (is_null($chosen_term)) {
                    // register the shortcode being used here, for outputSchema method :-)
                    $register = (is_string($chosen_exclusive)) ? $chosen_exclusive : true;
                    if (($on = $this->getOption('post_ids'))) {
                        if (false === isset($on[$post_id])) {
                            // remove the original id if any
                            foreach ($on as $key => $value) {
                                if ($value === $register) {
                                    unset($on[$key]);
                                    break;
                                }
                            }
                        }
                        // register this id (also updates if e.g. the exclusive value changes
                        $on[$post_id] = $register;
                    } else {
                        $on = [$post_id => true];
                    }
                    $this->setOption('post_ids', $on);
                    if ($register === true) {
                        $this->setOption('faq_page_slug', get_post_field('post_name', $post_id));
                    }
                }
                // [faq-with-categories exclusive="homepage"], or /url?category=blah
                // load the posts, will return row data: ID = id of the post, exclusive = meta value for exclusive (null when none)
                // term = category: this will multiply rows if multiple categories are attached,
                // post_title = question, post_content = answer, post_date = the date
                $posts = $this->getPosts($chosen_exclusive, $chosen_term);
                ob_start();
                // prepare the link TODO make a setting: link to single FAQ page or link to complete FAQ page (now default)
                if (true === $title_only) {
                    if (true === $this->title_links_to_overview) {
                        $slug = $this->getOption('faq_page_slug');
                        if (is_null($slug)) {
                            echo '<span class="notice">';
                            echo __('Please visit the FAQ page once so the plugin knows where to link to.', 'faq-with-categories');
                            echo '</span>';
                        } else {
                            if (strpos($slug, '?') === false) {
                                $slug = $slug . '?post_id=%s';
                            } else {
                                $slug = $slug . '&post_id=%s';
                            }
                        }
                    } else {
                        $slug = $this->slug;
                    }
                } else {
                    $slug = '%s';
                }
                echo '<ul id="ruigehond010_faq" class="ruigehond010 faq posts ';
                if ($chosen_exclusive) echo strtolower(htmlentities($chosen_exclusive));
                echo '">';
                foreach ($posts as $index => $post) {
                    if ($index === $quantity) break;
                    echo '<li class="ruigehond010_post term-';
                    echo strtolower(implode(' term-', $post->term_ids));
                    if ($post->exclusive) {
                        echo '" data-exclusive="';
                        echo $post->exclusive;
                    }
                    echo '" data-post_id="';
                    echo $post->ID;
                    echo '">';
                    if (false === $title_only) {
                        echo '<h4>';
                        echo $post->post_title;
                        echo '</h4>';
                        echo $post->post_content;
                    } else {
                        echo '<a href="';
                        if (true === $this->title_links_to_overview) {
                            echo sprintf($slug, $post->ID);
                        } else {
                            echo '/' . $slug . '/' . $post->post_name;
                        }
                        echo '">';
                        echo $post->post_title;
                        echo '</a>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
                $str = ob_get_contents();
                ob_end_clean();

                return $str;
            }
        }

        private function getTerms()
        {
            if (isset($this->terms)) return $this->terms; // return cached value if available
            // get the terms for this registered taxonomies from the db
            $taxonomies = sanitize_text_field($this->taxonomies); // just for the h#ck of it
            $sql = 'select t.term_id, tt.parent, t.name as term from ' .
                $this->wpdb->prefix . 'terms t inner join ' .
                $this->wpdb->prefix . 'term_taxonomy tt on t.term_id = tt.term_id left outer join ' .
                $this->order_table . ' o on o.term_id = t.term_id where tt.taxonomy = \'' .
                addslashes($taxonomies) . '\' order by o.o, t.name';
            $rows = $this->wpdb->get_results($sql, OBJECT);
            $terms = array();
            foreach ($rows as $key => $row) {
                if (!isset($terms[$parent = intval($row->parent)])) $terms[$parent] = array();
                $terms[$parent][] = array(
                    'term_id' => intval($row->term_id),
                    'term' => $row->term,
                );
            }
            $this->terms = $terms;

            return $terms;
        }

        /**
         * @param string|null $exclusive
         * @param null $term
         * @return array the rows from db as \stdClasses in an indexed array
         */
        private function getPosts($exclusive = null, $term = null)
        {
            $sql = 'select p.ID, p.post_title, p.post_content, p.post_date, p.post_name, t.term_id, pm.meta_value AS exclusive from ' .
                $this->wpdb->prefix . 'posts p left outer join ' .
                $this->wpdb->prefix . 'term_relationships tr on tr.object_id = p.ID left outer join ' .
                $this->wpdb->prefix . 'term_taxonomy tt on tt.term_taxonomy_id = tr.term_taxonomy_id left outer join ' .
                $this->wpdb->prefix . 'terms t on t.term_id = tt.term_id left outer join ' .
                $this->wpdb->prefix . 'postmeta pm on pm.post_id = p.ID and pm.meta_key = \'_ruigehond010_exclusive\' ' .
                'where p.post_type = \'ruigehond010_faq\'';
            // setup the where condition regarding exclusive and term....
            if (is_string($term)) {
                $sql .= ' and lower(t.name) = \'' . addslashes($term) . '\'';
            } elseif (is_string($exclusive)) {
                $sql .= ' and pm.meta_value = \'' . addslashes(sanitize_text_field($exclusive)) . '\'';
            }
            $sql .= ' order by p.post_date desc';
            $rows = $this->wpdb->get_results($sql, OBJECT);
            $return_arr = array();
            $current_id = 0;
            foreach ($rows as $index => $row) {
                if ($row->ID === $current_id) { // add the category to the current return value
                    $return_arr[count($return_arr) - 1]->term_ids[] = $row->term_id;
                } else { // add the row, when not exclusive is requested posts without terms must be filtered out
                    if (($term_id = $row->term_id) or $exclusive) {
                        $row->term_ids = array($term_id);
                        unset($row->term_id);
                        $return_arr[] = $row;
                        $current_id = $row->ID;
                    }
                }
            }
            unset($rows);

            return $return_arr;
        }

        public function handle_input($args)
        {
            $r = $this->getReturnObject();
            if (isset($args['handle']) and $args['handle'] === 'order_taxonomy') {
                if (isset($args['order']) and is_array($args['order'])) {
                    $this->wpdb->query('truncate table ' . $this->order_table);
                    $rows = $args['order'];
                    foreach ($rows as $term_id => $o) {
                        $this->wpdb->insert($this->order_table,
                            array('o' => $o, 'term_id' => $term_id)
                        );
                    }
                    $r->set_success(true);
                    $r->set_data($args);
                }
            }

            return $r;
        }

        /**
         * https://developer.wordpress.org/reference/functions/add_meta_box/
         * @param null $post_type
         */
        function meta_box_add($post_type = null)
        {
            if (!$post_id = get_the_ID()) {
                return;
            }
            if ($post_type === 'ruigehond010_faq') {
                add_meta_box( // WP function.
                    'ruigehond010', // Unique ID
                    'FAQ with categories', // Box title
                    array($this, 'meta_box'), // Content callback, must be of type callable
                    'ruigehond010_faq',
                    'normal',
                    'low',
                    array('exclusive' => get_post_meta($post_id, '_ruigehond010_exclusive', true))
                );
            }
        }

        function meta_box($post, $obj)
        {
            wp_nonce_field('ruigehond010_save', 'ruigehond010_nonce');
            echo '<input type="text" id="ruigehond010_exclusive" name="ruigehond010_exclusive" value="';
            echo $obj['args']['exclusive'];
            echo '"/> <label for="ruigehond010_exclusive">';
            echo __('The tag this FAQ entry is exclusive to, use it in a shortcut to summon the entry. Note that it will still be displayed for the taxonomies that are checked.', 'faq-with-categories');
            echo '</label>';
        }

        function meta_box_save($post_id)
        {
            if (!isset($_POST['ruigehond010_nonce']) || !wp_verify_nonce($_POST['ruigehond010_nonce'], 'ruigehond010_save'))
                return;
            if (!current_user_can('edit_post', $post_id))
                return;
            delete_post_meta($post_id, '_ruigehond010_exclusive');
            if (isset($_POST['ruigehond010_exclusive'])) {
                add_post_meta($post_id, '_ruigehond010_exclusive',
                    sanitize_title($_POST['ruigehond010_exclusive']), true);
            }
        }

        public function ordertaxonomypage()
        {
            wp_enqueue_script('ruigehond010_admin_javascript', plugin_dir_url(__FILE__) . 'admin.js', array(
                'jquery-ui-droppable',
                'jquery-ui-sortable',
                'jquery'
            ), RUIGEHOND010_VERSION);
            //wp_enqueue_script( 'jquery-ui-accordion' );
            wp_enqueue_style('ruigehond010_admin_stylesheet', plugin_dir_url(__FILE__) . 'admin.css', [], RUIGEHOND008_VERSION);
            wp_enqueue_style('wp-jquery-ui-dialog');
            echo '<div class="wrap ruigehond010"><h1>';
            echo esc_html(get_admin_page_title());
            echo '</h1><p>';
            echo __('This page only concerns itself with the order. The hierarchy is determined by the taxonomy itself.', 'faq-with-categories');
            echo '</p><section class="rows-sortable">';
            $terms = $this->getTerms(); // these are ordered to the best of the knowlede of the system already
            foreach ($terms as $index => $sub_terms) {
                foreach ($sub_terms as $o => $term) {
                    echo '<div class="ruigehond010-order-term" data-id="';
                    echo $term['term_id'];
                    echo '" data-inferred_order="';
                    echo $o;
                    echo '">';
                    echo '<div class="sortable-handle">';
                    echo $term['term'];
                    echo '</div></div>';
                }
            }
            echo '</section></div>';
        }

        public function settingspage()
        {
            // check user capabilities
            if (false === current_user_can('manage_options')) return;
            // if the slug for the faq posts just changed, flush rewrite rules as a service
            if (get_option('ruigehond010_flag_flush_rewrite_rules')) {
                delete_option('ruigehond010_flag_flush_rewrite_rules');
                flush_rewrite_rules();
            }
            // start the page
            echo '<div class="wrap"><h1>';
            echo esc_html(get_admin_page_title());
            echo '</h1><p>';
            echo __('FAQS are always sorted by post-date descending, so newest entries are first. By default they are output as an accordion list with the first one opened.', 'faq-with-categories');
            echo '<br/>';
            echo __('You may use the following shortcuts, of course certain combinations do not make sense and may produce erratic behaviour.', 'faq-with-categories');
            echo '<br/>';
            echo sprintf(__('%s produces the default list with all the faqs and outputs FAQ snippets schema in the head.', 'faq-with-categories'), '[faq-with-categories]');
            echo '<br/>';
            echo sprintf(__('%s produces a filter menu according to the chosen taxonomy using the specified order.', 'faq-with-categories'), '[faq-with-categories-filter]');
            echo '<br/>';
            echo sprintf(__('%s produces a search box that will perform client-side lookup through the faqs.', 'faq-with-categories'), '[faq-with-categories-search]');
            echo '<br/>';
            echo sprintf(__('%s limits the quantity of the faqs to 5, or use another number*.', 'faq-with-categories'), '[faq-with-categories quantity="5"]');
            echo '<br/>';
            echo sprintf(__('%s display only faqs for the specified category (case insensitive)*. This will NOT output FAQ snippets schema in the head.', 'faq-with-categories'), '[faq-with-categories category="category name"]');
            echo '<br/>';
            echo sprintf(__('%s any tag you specified under a faq entry in the box, will gather all faqs with that tag for display*.', 'faq-with-categories'), '[faq-with-categories exclusive="your tag"]');
            echo '<br/>';
            echo sprintf(__('%s outputs the list as links rather than as an accordion.', 'faq-with-categories'), '[faq-with-categories title-only="any value"]');
            echo '<br/><em>';
            echo __('* NOTE: only a limited number of faqs will be present on the page so search and filter will not work.', 'faq-with-categories');
            echo '</em></p><form action="options.php" method="post">';
            // output security fields for the registered setting
            settings_fields('ruigehond010');
            // output setting sections and their fields
            do_settings_sections('ruigehond010');
            // output save settings button
            submit_button(__('Save Settings', 'faq-with-categories'));
            echo '</form></div>';
        }

        public function settings()
        {
            if (false === $this->onSettingsPage('faq-with-categories')) return;
            if (false === current_user_can('manage_options')) return;
            register_setting('ruigehond010', 'ruigehond010', array($this, 'settings_validate'));
            // register a new section in the page
            add_settings_section(
                'global_settings', // section id
                __('Options', 'faq-with-categories'), // title
                function () {
                }, //callback
                'ruigehond010' // page id
            );
            $labels = array(
                'queue_frontend_css' => __('By default a small css-file is output to the frontend to format the entries. Uncheck to handle the css yourself.', 'faq-with-categories'),
                'taxonomies' => __('Type the taxonomy you want to use for the categories.', 'faq-with-categories'),
                'slug' => __('Slug for the individual faq entries (optional).', 'faq-with-categories'),
                'title_links_to_overview' => __('When using title-only in shortcuts, link to the overview rather than individual FAQ page.', 'faq-with-categories'),
                'choose_option' => __('The ‘choose / show all’ option in top most select list.', 'faq-with-categories'),
                'choose_all' => __('The ‘choose / show all’ option in subsequent select lists.', 'faq-with-categories'),
                'search_faqs' => __('The placeholder in the search bar for the faqs.', 'faq-with-categories'),
                'exclude_from_search' => __('Will exclude the FAQ posts from site search queries.', 'faq-with-categories'),
                'exclude_from_count' => __('FAQ posts will not count towards total posts in taxonomies.', 'faq-with-categories'),
            );
            foreach (
                array(
                    'taxonomies',
                    'slug',
                    'title_links_to_overview',
                    'choose_option',
                    'choose_all',
                    'search_faqs',
                    'exclude_from_search',
                    'exclude_from_count',
                    'queue_frontend_css',
                ) as $index => $setting_name
            ) {
                add_settings_field(
                    $setting_name . $index, // id, As of WP 4.6 this value is used only internally
                    $setting_name, // title
                    array($this, 'echo_settings_field'), // callback
                    'ruigehond010', // page id
                    'global_settings',
                    [
                        'setting_name' => $setting_name,
                        'label_for' => $labels[$setting_name],
                        'class_name' => 'ruigehond010',
                    ] // args
                );
            }
        }

        public function echo_settings_field($args)
        {
            $setting_name = $args['setting_name'];
            $str = '';
            switch ($setting_name) {
                case 'queue_frontend_css':
                case 'exclude_from_count':
                case 'title_links_to_overview':
                case 'exclude_from_search': // make checkbox that transmits 1 or 0, depending on status
                    $str .= '<label><input type="hidden" name="ruigehond010[' . $setting_name . ']" value="' .
                        (($this->$setting_name) ? '1' : '0') . '"><input type="checkbox"';
                    if ($this->$setting_name) {
                        $str .= ' checked="checked"';
                    }
                    $str .= ' onclick="this.previousSibling.value=1-this.previousSibling.value" class="' .
                        $args['class_name'] . '"/>' . $args['label_for'] . '</label>';
                    break;
                default: // make text input
                    $str .= '<input type="text" name="ruigehond010[' . $setting_name . ']" value="';
                    $str .= htmlentities($this->$setting_name);
                    $str .= '" style="width: 162px" class="' . $args['class_name'] . '"/> <label>' . $args['label_for'] . '</label>';
            }
            echo $str;
        }

        public function settings_validate($input)
        {
            $options = (array)get_option('ruigehond010');
            foreach ($input as $key => $value) {
                switch ($key) {
                    // on / off flags (1 vs 0 on form submit, true / false otherwise
                    case 'queue_frontend_css':
                    case 'exclude_from_search':
                    case 'title_links_to_overview':
                    case 'exclude_from_count':
                        $options[$key] = ($value === '1' or $value === true);
                        break;
                    case 'slug':
                        if (($value = sanitize_title($value)) !== $options['slug']) {
                            $options['slug'] = $value;
                            // flag for flush_rewrite_rules upon reload of the settings page
                            update_option('ruigehond010_flag_flush_rewrite_rules', 'yes', true);
                        }
                        break;
                    case 'taxonomies': // TODO check if it's an existing taxonomy?
                        if (false === taxonomy_exists($value)) $value = 'category';
                    // intentional fall through, just validated the value
                    // by default just accept the value
                    default:
                        $options[$key] = $value;
                }
            }

            return $options;
        }

        public function menuitem_()
        {
            // add management page under admin menu settings
            // https://premium.wpmudev.org/blog/creating-wordpress-admin-pages/
            add_options_page(
                'FAQ with categories',
                'FAQ with categories',
                'manage_options',
                'faq-with-categories',
                array($this, 'settingspage') // function
            );
        }

        public function menuitem()
        {
            // add top level page
            add_menu_page(
                'FAQ',
                'FAQ',
                'manage_options',
                'faq-with-categories',
                array($this, 'redirect_to_entries'), // callback
                'dashicons-lightbulb',
                27 // just under comments / reacties
            );
            add_submenu_page(
                'faq-with-categories',
                __('Settings', 'faq-with-categories'), // page_title
                __('FAQ', 'faq-with-categories'), // menu_title
                'manage_options',
                'faq-with-categories',
                array($this, 'redirect_to_entries') // callback
            );
            add_submenu_page(
                'faq-with-categories',
                __('Settings', 'faq-with-categories'), // page_title
                __('Settings', 'faq-with-categories'), // menu_title
                'manage_options',
                'faq-with-categories-settings',
                array($this, 'settingspage') // callback
            );
            add_submenu_page(
                'faq-with-categories',
                __('Order taxonomy', 'faq-with-categories'), // page_title
                __('Order taxonomy', 'faq-with-categories'), // menu_title
                'manage_options',
                'faq-with-categories-order-taxonomy',
                array($this, 'ordertaxonomypage') // callback
            );
            global $submenu; // make the first entry go to the edit page of the faq post_type
            $submenu['faq-with-categories'][0] = array(
                __('FAQ', 'faq-with-categories'),
                'manage_options',
                'edit.php?post_type=ruigehond010_faq',
                'blub' // WHOA
            );
            /*            echo '<pre>';
                        var_dump($submenu);
                        echo '</pre>';
                        die('opa');
                        $submenu['faq-with-categories'][] = array(
                            __('FAQ Entries', 'faq-with-categories'),
                            'manage_options',
                            'edit.php?post_type=ruigehond010_faq'
                        );*/
        }

        public function install()
        {
            $table_name = $this->order_table;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $sql = 'CREATE TABLE ' . $table_name . ' (
						term_id INT NOT NULL,
						o INT NOT NULL DEFAULT 1)
					';
                $this->wpdb->query($sql);
            }
            // register the current version
            $this->setOption('version', RUIGEHOND010_VERSION);
        }

        public function deactivate()
        {
        }

        public function uninstall()
        {
            // remove settings
            //delete_option('ruigehond010');
            // remove the post_meta entries
            //delete_post_meta_by_key('_ruigehond010_exclusive');
            // TODO remove the posts
        }
    }
}