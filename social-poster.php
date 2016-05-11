<?php
    /*
    Plugin Name: Social Poster
    Plugin URI: https://github.com/fyaconiello/wp_plugin_template
    Description: Social poster
    Version: 1.0
    Author: Vadim Petrov
    Author URI: http://www.twitter.com/imposibrus
    License: GPL2
    */

    if (!class_exists('SocialPoster')) {
        class SocialPoster
        {
            public function __construct()
            {
                // Initialize Settings
                require_once(sprintf("%s/settings.php", dirname(__FILE__)));
                $WP_Plugin_Template_Settings = new SocialPosterSettings();

                $plugin = plugin_basename(__FILE__);
                add_filter("plugin_action_links_$plugin", array( $this, 'plugin_settings_link' ));

                add_action('publish_post', array( $this, 'publish_to_networks' ), 10, 3);

                function my_admin_notice()
                {
                    if ($notices = get_option('social_poster_deferred_admin_notices')) {
                        foreach ($notices as $notice) {
                            echo '<div class="' . $notice['class'] . '"><p>' . $notice['message'] . '</p></div>';
                        }
                        delete_option('social_poster_deferred_admin_notices');
                    }
                }

                add_action('admin_notices', 'my_admin_notice');

                foreach (glob(dirname(__FILE__) . '/networks/*.php') as $filename) {
                    require_once $filename;
                }
            }

            public static function activate()
            {
                // Do nothing
            }

            public static function deactivate()
            {
                // Do nothing
            }

            function plugin_settings_link($links)
            {
                $settings_link = '<a href="options-general.php?page=social_poster">Settings</a>';
                array_unshift($links, $settings_link);

                return $links;
            }

            public function publish_to_networks($postID/*, $post, $is_update*/)
            {
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return FALSE;
                }
                if (empty($postID)/* || $is_update*/) {
                    return FALSE;
                }
                if (!current_user_can('edit_page', $postID)) {
                    return FALSE;
                }
                if (wp_is_post_revision($postID)) {
                    return FALSE;
                }

                do_action('sp_publish_to_networks', $postID);

            }

        }
    }

    if (class_exists('SocialPoster')) {
        // Installation and uninstallation hooks
        register_activation_hook(__FILE__, array( 'SocialPoster', 'activate' ));
        register_deactivation_hook(__FILE__, array( 'SocialPoster', 'deactivate' ));

        // instantiate the plugin class
        $wp_plugin_template = new SocialPoster();
    }
