<?php

    class SP_VK_Network
    {
        var $options_name = 'SocialPoster';

        public function __construct()
        {
            add_action('sp_publish_to_networks', array( &$this, 'publish' ));
            add_action('post_submitbox_misc_actions', array( &$this, 'submit_box_actions' ));
            $this->options = get_option($this->options_name);
        }

        public function submit_box_actions()
        {
            if (!$this->options['vk_app_id'] || !$this->options['vk_access_token'] || !$this->options['vk_target_id']) {
                echo '
						<div class="misc-pub-section misc-pub-section-last">
							<a href="' . get_site_url() . '/wp-admin/options-general.php?page=social_poster">
								Update your settings for posting to VK.com
							</a>
						</div>
						<div class="misc-pub-section misc-pub-section-last">
						    <label class="selectit">
							    <input type="checkbox"> Подтверждаю, что речь идёт о прошедшем событии
							</label>
						</div>';

                return;
            }
            global $post;
            $value = get_post_meta($post->ID, 'post_in_vk', TRUE);
            echo '
					<div class="misc-pub-section misc-pub-section-last">
						<span>
							<label>
								<input type="checkbox"' . (!empty($value) ? ' checked="checked" disabled="disabled" ' : NULL) . ' value="1" name="post_in_vk" />
								' . (!empty($value) ? 'Already published to VK.com' : 'Publish to VK.com') . '
							</label>
						</span>
					</div>
                    <div class="misc-pub-section misc-pub-section-last">
                        <label class="selectit">
                            <input type="checkbox"> Подтверждаю, что речь идёт о прошедшем событии
                        </label>
                    </div>';
        }

        public function publish($postID)
        {
            if (!isset($_POST['post_in_vk']) || get_post_meta($postID, 'post_in_vk', TRUE)) {
                return FALSE;
            }

            $notices = get_option('social_poster_deferred_admin_notices', array());

            $attachments = [ ];
            if ($post_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($postID), 'full')) {
                $upload_response = $this->upload_vk_image($post_thumbnail[0]);
//				$notices[] = array('class' => 'error', 'message' => 'Error: '. print_r($upload_response, true));
                if (is_object($upload_response)) {
                    $attachments[] = $upload_response->id;
                } else {
                    $notices[] = array( 'class' => 'error', 'message' => 'Error: ' . print_r($upload_response, TRUE) );
                }
            }


//			$vk_app_id = $this->options['vk_app_id'];
            $vk_access_token = $this->options['vk_access_token'];
            $vk_target_id = $this->options['vk_target_id'];
            $title = get_the_title($postID);
            $postUrl = 'https://api.vk.com/method/wall.post';
            $attachments[] = get_permalink($postID);
            if (is_array($attachments)) {
                $attachments = implode(',', $attachments);
            }
            $postArr = array(
                'owner_id'     => $vk_target_id,
                'access_token' => $vk_access_token,
                'from_group'   => '1',
                'message'      => $title,
                'attachment'   => $attachments,
            );

            $response = wp_remote_post($postUrl, array( 'body' => $postArr ));
            if (is_wp_error($response) || (is_object($response) && (isset($response->errors))) || (is_array($response) && stripos($response['body'], '"error":') !== FALSE)) {
                $notices[] = array( 'class' => 'error', 'message' => 'Error: ' . print_r($response, TRUE) );
            } else {
                $respJSON = json_decode($response['body'], TRUE);
                $link = '<a href="http://vk.com/wall' . $vk_target_id . '_' . $respJSON['response']['post_id'] . '" target="_blank">Show</a>';
                $notices[] = array(
                    'class'   => 'updated',
                    'message' => 'Post successfully published to VK.com. ' . $link,
                );
                update_post_meta($postID, 'post_in_vk', isset($_POST['post_in_vk']) ? 1 : 0);
            }

            update_option('social_poster_deferred_admin_notices', $notices);
        }

        /**
         * @param $imgURL - absolute url to image
         *
         * @return mixed
         */
        function upload_vk_image($imgURL)
        {
            $vk_app_id = $this->options['vk_app_id'];
            $vk_access_token = $this->options['vk_access_token'];
//			$vk_target_id = $this->options['vk_target_id'];

            $postUrl = 'https://api.vk.com/method/photos.getWallUploadServer?gid=' . $vk_app_id . '&access_token=' . $vk_access_token;
            $response = wp_remote_get($postUrl);
            $thumbUploadUrl = $response['body'];
            if (!empty($thumbUploadUrl)) {
                $thumbUploadUrlObj = json_decode($thumbUploadUrl);
                $VKUploadUrl = $thumbUploadUrlObj->response->upload_url;
            }
            if (!empty($VKUploadUrl)) {
                $remImgURL = urldecode($imgURL);
//				$urlParced = pathinfo( $remImgURL );
//				$remImgURLFilename = $urlParced['basename'];
                $imgData = wp_remote_get($remImgURL);
                $imgData = $imgData['body'];
                $tmp = array_search('uri', @array_flip(stream_get_meta_data($GLOBALS[ mt_rand() ] = tmpfile())));
                if (!is_writable($tmp)) {
                    return "Your temporary folder or file (file - " . $tmp . ") is not writable. Can't upload image to VK";
                }
                rename($tmp, $tmp .= '.png');
                register_shutdown_function(create_function('', "unlink('{$tmp}');"));
                file_put_contents($tmp, $imgData);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $VKUploadUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                if (function_exists('curl_file_create')) {
                    $file = curl_file_create($tmp);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, array( 'photo' => $file ));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, array( 'photo' => '@' . $tmp ));
                }

                $response = curl_exec($ch);
//				$errmsg = curl_error( $ch );
                curl_close($ch);

                $uploadResultObj = json_decode($response);

                if (!empty($uploadResultObj->server) && !empty($uploadResultObj->photo) && !empty($uploadResultObj->hash)) {
                    $postUrl = 'https://api.vk.com/method/photos.saveWallPhoto?server=' . $uploadResultObj->server . '&photo=' . $uploadResultObj->photo . '&hash=' . $uploadResultObj->hash . '&gid=' . $vk_app_id . '&access_token=' . $vk_access_token;
                    $response = wp_remote_get($postUrl);
                    $resultObject = json_decode($response['body']);
                    if (isset($resultObject) && isset($resultObject->response[0]->id)) {
                        return $resultObject->response[0];
                    } else {
                        return FALSE;
                    }
                }
            }

        }
    }

    if (class_exists('SP_VK_Network')) {
        $social_poster_vk = new SP_VK_Network();
    }
