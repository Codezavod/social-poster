<?php

    class SP_TW_Network
    {
        var $options_name = 'SocialPoster';

        public function __construct()
        {
            $this->tmhOAuthFolderPath = dirname(__FILE__) . '/vendors/tmhOAuth';
            require_once(sprintf('%s/tmhOAuth.php', $this->tmhOAuthFolderPath));
            add_action('sp_publish_to_networks', array( &$this, 'publish' ));
            add_action('post_submitbox_misc_actions', array( &$this, 'submit_box_actions' ));
            $this->options = get_option($this->options_name);
        }

        public function submit_box_actions()
        {
            if (!isset($this->options['tw_api_key']) || !isset($this->options['tw_api_secret']) || !isset($this->options['tw_access_token']) || !isset($this->options['tw_access_token_secret'])) {
                echo '
						<div class="misc-pub-section misc-pub-section-last">
							<a href="' . get_site_url() . '/wp-admin/options-general.php?page=social_poster">
								Update your settings for posting to Twitter.com
							</a>
						</div>';

                return;
            }
            global $post;
            $value = get_post_meta($post->ID, 'post_in_tw', TRUE);
            echo '
					<div class="misc-pub-section misc-pub-section-last">
						<span>
							<label>
								<input type="checkbox"' . (!empty($value) ? ' checked="checked" disabled="disabled" ' : NULL) . ' value="1" name="post_in_tw" />
								' . (!empty($value) ? 'Already published to Twitter.com' : 'Publish to Twitter.com') . '
							</label>
						</span>
					</div>';
        }

        public function twitter_bearer_token()
        {
            @$tmhOAuth = new tmhOAuth(array(
                'consumer_key'    => $this->options['tw_api_key'],
                'consumer_secret' => $this->options['tw_api_secret'],
                'token'           => $this->options['tw_access_token'],
                'secret'          => $this->options['tw_access_token_secret'],
                'bearer'          => base64_encode($this->options['tw_api_key'] . ':' . $this->options['tw_api_secret']),
                'curl_cainfo'     => $this->tmhOAuthFolderPath . '/cacert.pem',
                'curl_capath'     => $this->tmhOAuthFolderPath . '/',
            ));

            $bearer = $tmhOAuth->bearer_token_credentials();
            $params = array(
                'grant_type' => 'client_credentials',
            );

            $code = $tmhOAuth->request(
                'POST',
                $tmhOAuth->url('/oauth2/token', NULL),
                $params,
                FALSE,
                FALSE,
                array(
                    'Authorization' => "Basic ${bearer}",
                )
            );

            if ($code == 200) {
                $data = json_decode($tmhOAuth->response['response']);
                if (isset($data->token_type) && strcasecmp($data->token_type, 'bearer') === 0) {
                    return $data->access_token;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }

        public function publish($postID)
        {
            if (!isset($_POST['post_in_tw']) || get_post_meta($postID, 'post_in_tw', TRUE)) {
                return FALSE;
            }

            $tw_bearer_token = $this->twitter_bearer_token();

            $notices = get_option('social_poster_deferred_admin_notices', array());

            @$tmhOAuth = new tmhOAuth(array(
                'consumer_key'    => $this->options['tw_api_key'],
                'consumer_secret' => $this->options['tw_api_secret'],
                'token'           => $this->options['tw_access_token'],
                'secret'          => $this->options['tw_access_token_secret'],
                'bearer'          => $tw_bearer_token,
                'curl_cainfo'     => $this->tmhOAuthFolderPath . '/cacert.pem',
                'curl_capath'     => $this->tmhOAuthFolderPath . '/',
            ));

            $title = get_the_title($postID);
            $permalink = get_permalink($postID);
            $attachments = array();

            if ($post_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($postID), 'full')) {
                $domain = get_site_url();
                $attachments[] = $_SERVER['DOCUMENT_ROOT'] . str_replace($domain, '', $post_thumbnail[0]);
            }

            $tags = '';
//			if (trim($_POST['tags']) != '') {
//				$tags = $_POST['tags']." / ";
//			}

            if ($attachments) {
                $imageName = end(explode('/', $attachments[0]));
                $imageSize = getimagesize($attachments[0]);
                $type = $imageSize['mime'];
                if (mb_strlen($title, 'UTF-8') + mb_strlen($tags, 'UTF-8') + mb_strlen($permalink, 'UTF-8') + 25 > 139) {
                    $title = mb_substr($title, 0, 137 - mb_strlen($tags, 'UTF-8') - mb_strlen($permalink, 'UTF-8') - 25, 'UTF-8') . '...';
                }
                $arr = array(
                    'status'  => $tags . $title . " / / " . $permalink,
                    'media[]' => "@{$attachments[0]};type={$type};filename={$imageName}",
                );

                $code = $tmhOAuth->user_request(array(
                    'method'    => 'POST',
                    'url'       => $tmhOAuth->url("1.1/statuses/update_with_media"),
                    'params'    => $arr,
                    'multipart' => TRUE,
                ));

            } else {
                if (mb_strlen($title, 'UTF-8') + mb_strlen($tags, 'UTF-8') + mb_strlen($permalink, 'UTF-8') > 139)
                    $title = mb_substr($title, 0, 137 - mb_strlen($tags, 'UTF-8') - mb_strlen($permalink, 'UTF-8'), 'UTF-8') . '...';

                $code = $tmhOAuth->user_request(array(
                    'method' => 'POST',
                    'url'    => $tmhOAuth->url("1.1/statuses/update"),
                    'params' => array( 'status' => $tags . $title . " / / " . $permalink ),
                ));
            }

            if ($code != 200) {
                $notices[] = array( 'class' => 'error', 'message' => 'Error: ' . print_r($tmhOAuth->response, TRUE) );
            } else {
                $respJSON = json_decode($tmhOAuth->response['response']);
                $post_link = '<a href="https://twitter.com/' . $respJSON->user->screen_name . '/status/' . $respJSON->id_str . '" target="_blank">Show</a>';
                $notices[] = array(
                    'class'   => 'updated',
                    'message' => 'Post successfully published to Twitter.com. ' . $post_link,
                );
                update_post_meta($postID, 'post_in_tw', isset($_POST['post_in_tw']) ? 1 : 0);
            }

            update_option('social_poster_deferred_admin_notices', $notices);
        }

    }

    if (class_exists('SP_TW_Network')) {
        $social_poster_tw = new SP_TW_Network();
    }
