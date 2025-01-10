<?php
/**
 * The main template file
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<main id="primary" class="site-main">
    <?php
    if (have_posts()) :
        if (is_home() && !is_front_page()) :
            ?>
            <header>
                <h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
            </header>
            <?php
        endif;

        /* Start the Loop */
        while (have_posts()) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php
                    if (is_singular()) :
                        the_title('<h1 class="entry-title">', '</h1>');
                    else :
                        the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>');
                    endif;

                    if ('post' === get_post_type()) :
                        ?>
                        <div class="entry-meta">
                            <?php
                            printf(
                                /* translators: %s: Post date. */
                                esc_html__('Posted on %s', 'tcl-builder'),
                                '<time class="entry-date published" datetime="' . esc_attr(get_the_date(DATE_W3C)) . '">' . esc_html(get_the_date()) . '</time>'
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="entry-content">
                    <?php
                    if (is_singular()) :
                        the_content();
                    else :
                        the_excerpt();
                        ?>
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="read-more">
                            <?php
                            /* translators: %s: Post title. */
                            printf(
                                wp_kses(
                                    __('Continue reading<span class="screen-reader-text"> "%s"</span>', 'tcl-builder'),
                                    array(
                                        'span' => array(
                                            'class' => array(),
                                        ),
                                    )
                                ),
                                wp_kses_post(get_the_title())
                            );
                            ?>
                        </a>
                    <?php endif; ?>
                </div>

                <footer class="entry-footer">
                    <?php
                    $categories_list = get_the_category_list(esc_html__(', ', 'tcl-builder'));
                    if ($categories_list) {
                        printf(
                            /* translators: %s: Categories list. */
                            '<span class="cat-links">' . esc_html__('Posted in %s', 'tcl-builder') . '</span>',
                            $categories_list
                        );
                    }

                    $tags_list = get_the_tag_list('', esc_html_x(', ', 'list item separator', 'tcl-builder'));
                    if ($tags_list) {
                        printf(
                            /* translators: %s: Tags list. */
                            '<span class="tags-links">' . esc_html__('Tagged %s', 'tcl-builder') . '</span>',
                            $tags_list
                        );
                    }
                    ?>
                </footer>
            </article>
            <?php
        endwhile;

        the_posts_navigation();

    else :
        ?>
        <p><?php esc_html_e('No posts found.', 'tcl-builder'); ?></p>
        <?php
    endif;
    ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
