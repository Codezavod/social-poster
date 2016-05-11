<div class="wrap">
    <h2>Social Poster</h2>
    <form method="post" action="options.php">
        <?php
            settings_fields('SocialPoster-group');
            do_settings_sections('SocialPoster-page');
            submit_button();
        ?>
    </form>
</div>
