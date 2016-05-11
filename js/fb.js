/* global facebookData */

jQuery(function ($) {

    var $post_in_fb = $('[name="post_in_fb"]');

    var submitHandler = function (e) {
        if ($post_in_fb.is(':checked') && !$post_in_fb.is('[disabled]')) {
            e.preventDefault();
            $(document).off('submit', '#post', submitHandler);

            FB.init({appId: $('#fb_app_id').data('appId'), status: true, cookie: true, xfbml: true, oauth: true});
            FB.login(fb_login, {scope: 'publish_actions,manage_pages,publish_pages,user_posts,user_photos,user_groups'});
        }
    };

    $(document).on('submit', '#post', submitHandler);

    function fb_login(response) {
        if (response.authResponse) {
            var accessToken = FB.getAuthResponse()['accessToken'];
            $.post(ajaxurl, {
                fb_access_token: accessToken,
                action: 'save_fb_access_token',
                nonce: facebookData.nonce
            }).done(function (data) {
                if (data.status != 200) {
                    alert('Произошла ошибка, пост не будет опубликован на Facebook.');
                }
            }).fail(function () {
                alert('Во время авторизации на Facebook произошла ошибка, пост не будет опубликован на Facebook.');
            }).always(function () {
                $('#post').submit();
            });
        } else {
            alert('Во время авторизации на Facebook произошла ошибка, пост не будет опубликован на Facebook.');
            $('#post').submit();
        }
    }
});
