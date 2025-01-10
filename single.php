<?php
/**
 * The template for displaying all single posts
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
    while (have_posts()) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>

                <?php if ('post' === get_post_type()) : ?>
                    <div class="entry-meta">
                        <?php
                        printf(
                            /* translators: %s: Post date. */
                            esc_html__('Posted on %s', 'tcl-builder'),
                            '<time class="entry-date published" datetime="' . esc_attr(get_the_date(DATE_W3C)) . '">' . esc_html(get_the_date()) . '</time>'
                        );

                        $categories_list = get_the_category_list(esc_html__(', ', 'tcl-builder'));
                        if ($categories_list) {
                            printf(
                                /* translators: %s: Categories list. */
                                '<span class="cat-links">' . esc_html__(' in %s', 'tcl-builder') . '</span>',
                                $categories_list
                            );
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="post-thumbnail">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php
                the_content();

                wp_link_pages(array(
                    'before' => '<div class="page-links">' . esc_html__('Pages:', 'tcl-builder'),
                    'after'  => '</div>',
                ));
                ?>
            </div>

            <footer class="entry-footer">
                <?php
                $tags_list = get_the_tag_list('', esc_html_x(', ', 'list item separator', 'tcl-builder'));
                if ($tags_list) {
                    printf(
                        /* translators: %s: Tags list. */
                        '<span class="tags-links">' . esc_html__('Tagged: %s', 'tcl-builder') . '</span>',
                        $tags_list
                    );
                }

                // If comments are open or we have at least one comment
                if (comments_open() || get_comments_number()) :
                    comments_template();
                endif;

                // Post navigation
                the_post_navigation(array(
                    'prev_text' => '<span class="nav-subtitle">' . esc_html__('Previous:', 'tcl-builder') . '</span> <span class="nav-title">%title</span>',
                    'next_text' => '<span class="nav-subtitle">' . esc_html__('Next:', 'tcl-builder') . '</span> <span class="nav-title">%title</span>',
                ));
                ?>
            </footer>
        </article>
    <?php 
    endwhile;
    wp_reset_postdata();
    ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
