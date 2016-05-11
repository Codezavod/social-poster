<?php
    if (!class_exists('SocialPosterSettings')) {
        class SocialPosterSettings
        {
            var $options_name = 'SocialPoster';

            var $available_options = array(
                array(
                    'id'     => 'vk_section',
                    'title'  => 'VK.com settings',
                    'fields' => array(
                        array( 'control_type' => 'input_text', 'id' => 'vk_app_id', 'title' => 'VK app ID' ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'vk_access_token',
                            'title'        => 'VK access token',
                        ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'vk_target_id',
                            'title'        => 'VK target id (user/group)',
                        ),
                    ),
                ),
                array(
                    'id'     => 'tw_section',
                    'title'  => 'Twitter.com settings',
                    'fields' => array(
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'tw_api_key',
                            'title'        => 'Twitter Consumer Key (API Key)',
                        ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'tw_api_secret',
                            'title'        => 'Twitter Consumer Secret (API Secret)',
                        ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'tw_access_token',
                            'title'        => 'Twitter Access Token',
                        ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'tw_access_token_secret',
                            'title'        => 'Twitter Access Token Secret',
                        ),
                    ),
                ),
                array(
                    'id'     => 'fb_section',
                    'title'  => 'Facebook.com settings',
                    'fields' => array(
                        array( 'control_type' => 'input_text', 'id' => 'fb_app_id', 'title' => 'Facebook app ID' ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'fb_app_secret',
                            'title'        => 'Facebook app secret',
                        ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'fb_target_id',
                            'title'        => 'Facebook target id (user/group)',
                        ),
                        array(
                            'control_type' => 'input_text',
                            'id'           => 'fb_access_token',
                            'title'        => 'Facebook access token (if exists)',
                        ),
                    ),
                ),
            );

            public function __construct()
            {
                // register actions
                add_action('admin_init', array( &$this, 'admin_init' ));
                add_action('admin_menu', array( &$this, 'add_menu' ));
                $this->options = get_option($this->options_name);
            }

            /**
             * hook into WP's admin_init action hook
             */
            public function admin_init()
            {

                register_setting(
                    $this->options_name . '-group', // Option group
                    $this->options_name, // Option name
                    array( $this, 'sanitize' ) // Sanitize
                );

                foreach ($this->available_options as $section) {
                    add_settings_section(
                        $section['id'], // ID
                        $section['title'], // Title
                        array( $this, 'settings_section_social_poster' ), // Callback
                        $this->options_name . '-page' // Page
                    );

                    foreach ($section['fields'] as $field) {
                        add_settings_field(
                            $field['id'],
                            $field['title'],
                            array( &$this, $field['control_type'] . '_field' ),
                            $this->options_name . '-page',
                            $section['id'],
                            array(
                                'field' => $field['id'],
                            )
                        );
                    }
                }
            }

            /**
             * Get the settings option array and print one of its values
             */
            public function input_text_field($args)
            {
                $field = $args['field'];
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" />',
                    $field,
                    $this->options_name . '[' . $field . ']',
                    isset($this->options[ $field ]) ? esc_attr($this->options[ $field ]) : ''
                );
            }

            public function sanitize($input)
            {
                $new_input = array();
                foreach ($this->available_options as $section) {
                    foreach ($section['fields'] as $field) {
                        if (isset($input[ $field['id'] ])) {
                            $new_input[ $field['id'] ] = sanitize_text_field($input[ $field['id'] ]);
                        }
                    }
                }

                return $new_input;
            }

            public function settings_section_social_poster()
            {
                // Think of this as help text for the section.
                echo 'These settings do things for the Social Poster.';
            }

            /**
             * add a menu
             */
            public function add_menu()
            {
                // Add a page to manage this plugin's settings
                add_options_page(
                    'Social Poster Settings',
                    'Social Poster',
                    'manage_options',
                    'social_poster',
                    array( &$this, 'plugin_settings_page' )
                );
            }

            /**
             * Menu Callback
             */
            public function plugin_settings_page()
            {
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have sufficient permissions to access this page.'));
                }

                // Render the settings template
                include(sprintf("%s/templates/settings.php", dirname(__FILE__)));
            }
        }
    }
