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
        private $name, $table, $database_version, $taxonomies, $slug, $choose_option, $exclude_from_search, $queue_frontend_css;
        // variables that hold cached items
        private $terms;

        public function __construct($title = 'Ruige hond')
        {
            parent::__construct('ruigehond010');
            $this->name = __CLASS__;
            // set some options
            $this->database_version = $this->getOption('database_version', '0.0.0');
            $this->taxonomies = $this->getOption('taxonomies', 'category');
            $this->slug = $this->getOption('slug', 'faq');
            $this->choose_option = $this->getOption('choose_option', __('Choose option', 'faq-with-categories'));
            $this->exclude_from_search = $this->getOption('exclude_from_search', true);
            $this->queue_frontend_css = $this->getOption('queue_frontend_css', true);
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
                        'name' => __('FAQ entry', 'faq-with-categories'),
                        'singular_name' => __('FAQ entries', 'faq-with-categories'),
                    ),
                    'public' => true,
                    'has_archive' => true,
                    'taxonomies' => array($this->taxonomies),
                    'exclude_from_search' => $this->exclude_from_search,
                    'rewrite' => array('slug' => $this->slug), // remember to flush_rewrite_rules(); when this changes
                )
            );
            if (is_admin()) {
                add_action('admin_init', array($this, 'settings'));
                add_action('admin_menu', array($this, 'menuitem'));
            } else {
                wp_enqueue_script('ruigehond010_javascript', plugin_dir_url(__FILE__) . 'client.js', array('jquery'));
                if ($this->queue_frontend_css) { // only output css when necessary
                    wp_enqueue_style('ruigehond010_stylesheet_display', plugin_dir_url(__FILE__) . 'display.css', [], RUIGEHOND010_VERSION);
                }
                add_action('wp_head', array($this, 'outputSchema'), 2);
                add_shortcode('faq-with-categories', array($this, 'getHtmlForFrontend'));
                add_shortcode('faq-with-categories-filter', array($this, 'getHtmlForFrontend'));
                add_shortcode('faq-with-categories-search', array($this, 'getHtmlForFrontend'));
            }
        }

        public function outputSchema()
        {
            echo '<!-- ruigehond010 does not output schema yet -->';
        }

        public function getHtmlForFrontend($attributes = [], $content = null, $short_code = 'faq-with-categories')
        {
            $chosen_exclusive = isset($attributes['exclusive']) ? $attributes['exclusive'] : null;
            $chosen_term = isset($attributes['category']) ? $attributes['category'] : isset($_GET['category']) ? $_GET['category'] : null;
            // several types of html can be got with this
            // 1) the select boxes for the filter (based on term)
            if ($short_code === 'faq-with-categories-filter') {
                // ->getTerms() = fills by sql SELECT term_taxonomy_id, parent, count, term FROM etc.
                $rows = $this->getTerms();
                if (!is_null($chosen_term)) $chosen_term = strtolower($chosen_term);
                // write the html lists
                ob_start();
                foreach ($rows as $parent => $options) {
                    echo '<select class="ruigehond010 faq choose-category" data-ruigehond010_parent="';
                    echo $parent;
                    if ($parent === 0) {
                        echo '" style="display: block'; // to prevent repainting in default situation
                    }
                    echo '" onchange="ruigehond010_filter(this);"><option hidden="hidden">';
                    echo $this->choose_option;
                    echo '</option>';
                    foreach ($options as $index => $option) {
                        echo '<option data-ruigehond010_term_taxonomy_id="';
                        echo $option['term_taxonomy_id'];
                        echo '" value="';
                        echo htmlentities($term = $option['term']);
                        if (strtolower($term) === $chosen_term) echo '" selected="selected';
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
                $str .= $this->getOption('search_faqs', __('Search faqs', 'faq-with-categories'));
                $str .= '"/>';

                return $str;
            } else { // 2) all the posts, filtered by 'exclusive' or 'term'
                // [faq-with-categories exclusive="homepage"], or /url?category=blah (if category is the term)
                // load the posts, will return row data: ID = id of the post, exclusive = meta value for exclusive (null when none)
                // term = category: this will multiply rows if multiple categories are attached,
                // post_title = question, post_content = answer, post_date = the date
                $posts = $this->getPosts($chosen_exclusive);
                ob_start();
                echo '<ul id="ruigehond010_faq" class="ruigehond010 faq posts ';
                if ($chosen_exclusive) echo strtolower(htmlentities($chosen_exclusive));
                echo '">';
                foreach ($posts as $index => $post) {
                    echo '<li class="ruigehond010_post ';
                    echo strtolower(implode(' ', $post->terms));
                    if ($post->exclusive) {
                        echo '" data-exclusive="';
                        echo $post->exclusive;
                    }
                    echo '"><h4>';
                    echo $post->post_title;
                    echo '</h4>';
                    echo $post->post_content;
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
            $sql = 'select tt.term_taxonomy_id, tt.parent, tt.count, t.name as term from ' .
                $this->wpdb->prefix . 'term_taxonomy tt inner join ' .
                $this->wpdb->prefix . 'terms t on t.term_id = tt.term_id where tt.taxonomy = \'' .
                addslashes($taxonomies) . '\' order by t.name asc';
            $rows = $this->wpdb->get_results($sql, OBJECT);
            $terms = array();
            foreach ($rows as $key => $row) {
                if (!isset($terms[$parent = intval($row->parent)])) $terms[$parent] = array();
                $terms[$parent][] = array(
                    'term_taxonomy_id' => intval($row->term_taxonomy_id),
                    'count' => intval($row->count),
                    'term' => $row->term,
                );
            }
            $this->terms = $terms;

            return $terms;
        }

        /**
         * @param string|null $exclusive
         * @returns \stdClass the rows from the db as object in an indexed array
         */
        private function getPosts($exclusive = null)
        {
            $sql = 'select p.ID, p.post_title, p.post_content, p.post_date, t.name AS term, pm.meta_value AS exclusive from ' .
                $this->wpdb->prefix . 'posts p left outer join ' .
                $this->wpdb->prefix . 'term_relationships tr on tr.object_id = p.ID left outer join ' .
                $this->wpdb->prefix . 'term_taxonomy tt on tt.term_taxonomy_id = tr.term_taxonomy_id left outer join ' .
                $this->wpdb->prefix . 'terms t on t.term_id = tt.term_id left outer join ' .
                $this->wpdb->prefix . 'postmeta pm on pm.post_id = p.ID and pm.meta_key = \'_ruigehond010_exclusive\' ' .
                'where p.post_type = \'ruigehond010_faq\'';
            // setup the where condition regarding exclusive....
            if (!is_null($exclusive)) {
                $sql .= ' and pm.meta_value = \'' . addslashes(sanitize_text_field($exclusive)) . '\'';
            }
            $sql .= ' order by p.post_date desc';
            $rows = $this->wpdb->get_results($sql, OBJECT);
            $return_arr = array();
            $current_id = 0;
            foreach ($rows as $index => $row) {
                if ($row->ID === $current_id) { // add the category to the current return value
                    $return_arr[count($return_arr) - 1]->terms[] = $row->term;
                } else { // add the row, when not exclusive is requested posts without terms will be filtered out
                    $term = $row->term;
                    $row->terms = array($term);
                    unset($row->term);
                    $return_arr[] = $row;
                    $current_id = $row->ID;
                }
            }
            unset($rows);

            return $return_arr;
        }

        public function handle_input($post)
        {
            $r = $this->getReturnObject();

            return $r;
        }

        public function settingspage()
        {
            // check user capabilities
            if (false === current_user_can('manage_options')) return;
            echo '<div class="wrap"><h1>';
            echo esc_html(get_admin_page_title());
            echo '</h1><form action="options.php" method="post">';
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
                'choose_option' => __('The ‘choose’ option in select lists without a selected option.', 'faq-with-categories'),
                'exclude_from_search' => __('Will exclude the faq posts from site search queries.', 'faq-with-categories'),
            );
            foreach (
                array(
                    'queue_frontend_css',
                    'taxonomies',
                    'slug',
                    'choose_option',
                    'exclude_from_search',
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
                        $options[$key] = ($value === '1' or $value === true);
                        break;
                    case 'taxonomies': // TODO check if it's an existing taxonomy?
                    // by default just accept the value
                    default:
                        $options[$key] = $input[$key];
                }
            }

            return $options;
        }

        public function menuitem()
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

        public function install()
        {
        }

        public function uninstall()
        {
        }

        public function deactivate()
        {
        }
    }
}