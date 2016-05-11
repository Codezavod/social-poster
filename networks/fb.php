<?php

    class SP_FB_Network
    {
        var $options_name = 'SocialPoster';

        public function __construct()
        {
            add_action('sp_publish_to_networks', array( &$this, 'publish' ));
            add_action('post_submitbox_misc_actions', array( &$this, 'submit_box_actions' ));
            $this->options = get_option($this->options_name);

            add_action('wp_ajax_save_fb_access_token', array( &$this, 'save_fb_access_token' ));


            function facebook_social_poster($hook)
            {
                if ('post.php' != $hook) {
                    return;
                }
                wp_enqueue_script('facebook_api', 'http://connect.facebook.net/en_US/all.js', array(), FALSE, TRUE);
                wp_enqueue_script('facebook_social_poster', plugin_dir_url(dirname(__FILE__) . 'social-poster.php') . 'js/fb.js', array(
                    'jquery',
                    'facebook_api',
                ), FALSE, TRUE);
                wp_localize_script('facebook_social_poster', 'facebookData',
                    array(
                        'url'   => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('facebookData-nonce'),
                    )
                );

            }

            add_action('admin_enqueue_scripts', 'facebook_social_poster');
            if (isset($this->options['fb_app_id'])) {
                add_action('admin_footer', array( &$this, 'admin_fb_app_id' ));
            }
        }

        public function admin_fb_app_id()
        {
            ?>
            <div id="fb_app_id" data-app-id="<?php echo $this->options['fb_app_id']; ?>"></div>
            <?php
        }

        public function save_fb_access_token()
        {
            $nonce = $_POST['nonce'];
            if (!wp_verify_nonce($nonce, 'facebookData-nonce') || !current_user_can('publish_posts')) {
                die ('Stop!');
            }

            $this->options['fb_access_token'] = $_POST['fb_access_token'];
            update_option($this->options_name, $this->options);
            header('Content-Type: application/json; charset=UTF-8');
            print json_encode(array( 'status' => 200 ));
            wp_die();
        }

        public function submit_box_actions()
        {
            if (!isset($this->options['fb_app_id']) || !isset($this->options['fb_app_secret']) || !isset($this->options['fb_target_id'])) {
                echo '
						<div class="misc-pub-section misc-pub-section-last">
							<a href="' . get_site_url() . '/wp-admin/options-general.php?page=social_poster">
								Update your settings for posting to Facebook
							</a>
						</div>';

                return;
            }
            global $post;
            $value = get_post_meta($post->ID, 'post_in_fb', TRUE);
            echo '
					<div class="misc-pub-section misc-pub-section-last">
						<span>
							<label>
								<input type="checkbox"' . (!empty($value) ? ' checked="checked" disabled="disabled" ' : NULL) . ' value="1" name="post_in_fb" />
								' . (!empty($value) ? 'Already published to Facebook' : 'Publish to Facebook') . '
							</label>
						</span>
					</div>';
        }

        public function publish($postID)
        {
            if (!isset($_POST['post_in_fb']) || get_post_meta($postID, 'post_in_fb', TRUE)) {
                return FALSE;
            }

            $notices = get_option('social_poster_deferred_admin_notices', array());

            $page_access_token = $this->options['fb_access_token'];
            // TODO: more right page id extractor
            $target_url_arr = explode('/', $this->options['fb_target_id']);
            $page_id = end($target_url_arr);

            if ($post_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($postID), 'full')) {
//				$data['picture'] = 'http://kazanfirst.ru/storage/feeds/2015/05/85a647d4ecf7e52be715c510ff232923.jpg';
                $data['picture'] = $post_thumbnail[0];
            }
            $data['link'] = get_permalink($postID);
            $data['message'] = get_the_title($postID);
//			$data['caption'] = "Caption";
//			$data['description'] = "Description";

            $data['access_token'] = $page_access_token;

            $post_url = 'https://graph.facebook.com/' . $page_id . '/feed';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $post_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $return = curl_exec($ch);
            $ret_JSON = json_decode($return);
            if (isset($ret_JSON->id)) {
                $id_arr = explode('_', $ret_JSON->id);
                // https://www.facebook.com/permalink.php?story_fbid=659142410853779&id=402343286533694
                $link = '<a href="https://www.facebook.com/permalink.php?story_fbid=' . $id_arr[1] . '&id=' . $id_arr[0] . '" target="_blank">Show</a>';
                $notices[] = array(
                    'class'   => 'updated',
                    'message' => 'Post successfully published to Facebook. ' . $link,
                );
                update_post_meta($postID, 'post_in_fb', isset($_POST['post_in_fb']) ? 1 : 0);
            } else {
                $notices[] = array( 'class' => 'error', 'message' => 'Error: ' . print_r($return, TRUE) );
            }
            curl_close($ch);

            update_option('social_poster_deferred_admin_notices', $notices);
        }
    }

    if (class_exists('SP_FB_Network')) {
        $social_poster_fb = new SP_FB_Network();
    }
