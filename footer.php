<?php
/**
 * The template for displaying the footer
 */
?>
    </div><!-- #content -->

    <footer id="colophon" class="site-footer">
        <div class="site-info">
            <?php
            printf(
                /* translators: %s: Theme name. */
                esc_html__('Theme: %s', 'tcl-builder'),
                'TCL Builder'
            );
            ?>
            <span class="sep"> | </span>
            <?php
            printf(
                /* translators: %1$s: Theme author. */
                esc_html__('Created by %1$s', 'tcl-builder'),
                '<a href="' . esc_url('http://example.com') . '">TCL</a>'
            );
            ?>
        </div>
    </footer>
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
