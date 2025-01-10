<?php
/**
 * Template part for displaying the custom header
 */
?>
<div class="main-header">
    <div class="header-container">
        <div class="logo">
            <?php
            $logo_url = get_template_directory_uri() . '/assets/images/WW_Logo.svg';
            ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="WW Logo" class="ww-logo">
        </div>
        <div class="header-right">
            <div class="top-row">
                <div class="areas-served">
                    <i class="fas fa-map-marker-alt"></i>
                    Areas Served
                </div>
                <div class="contact-info">
                    <div class="phone">555-555-5555</div>
                    <div class="quote-text">CALL FOR YOUR FREE QUOTE</div>
                </div>
            </div>
            <button class="mobile-phone">
                <i class="fas fa-phone"></i>
                <span>555-555-5555</span>
            </button>
        </div>
    </div>
</div>
