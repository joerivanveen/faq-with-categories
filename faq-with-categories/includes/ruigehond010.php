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
        private $name, $table, $database_version, $term, $slug, $find, $queue_frontend_css;

        public function __construct($title = 'Ruige hond')
        {
            parent::__construct('ruigehond010');
            $this->name = __CLASS__;
            $this->table = $this->wpdb->prefix . 'ruigehond010_faq';
            // set some options
            $this->database_version = $this->getOption('database_version', '0.0.0');
            $this->term = $this->getOption('term', 'category');
            $this->slug = $this->getOption('slug', 'faq');
            $this->find = $this->getOption('find', true);
            $this->queue_frontend_css = $this->getOption('queue_frontend_css', true);
        }

        public function initialize()
        {
            $this->load_translations( 'user-reviews' );
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
                    'taxonomies'  => array( $this->term ),
                    'exclude_from_search' => !$this->find,
                    'rewrite'     => array( 'slug' => $this->slug ), // remember to flush_rewrite_rules(); when this changes
                )
            );
            if ( is_admin() ) {
                add_action( 'admin_init', array( $this, 'settings' ) );
                add_action( 'admin_menu', array( $this, 'menuitem' ) );
            } else {
                if ( $this->queue_frontend_css ) { // only output css when necessary
                    wp_enqueue_style( 'ruigehond010_stylesheet_display', plugin_dir_url( __FILE__ ) . 'display.css', [], RUIGEHOND010_VERSION );
                }
                add_action( 'wp_head', array( $this, 'outputSchema' ), 2 );
                add_shortcode( 'faq-with-categories', array( $this, 'getHtmlForFrontend' ) );
            }
        }

        public function outputSchema() {
            echo '<!-- ruigehond010 does not output schema just yet -->';
        }
        public function getHtmlForFrontend($attributes = [], $content = null, $short_code = 'faq-with-categories') {
            return 'ruigehond010 does not output html for frontend yet';
        }

        public function handle_input($post)
        {
            $r = $this->getReturnObject();

            return $r;
        }

        public function settings()
        {
            if (\false === $this->onSettingsPage('faq-with-categories')) return;
            if (\false === current_user_can('manage_options')) return;

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