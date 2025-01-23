(function() {
    'use strict';

    const TCLCustomHeader = {
        init() {
            this.handleScroll();
            window.addEventListener('scroll', () => this.handleScroll());
        },

        handleScroll() {
            // Find header in main document first
            let header = document.querySelector('[data-component="main-header"]');

            if (!header) {
                // Search in shadow roots using data attributes
                const sections = document.querySelectorAll('.tcl-builder-section');
                for (const section of sections) {
                    const host = section.querySelector('.tcl-builder-section-host');
                    if (host?.shadowRoot) {
                        header = host.shadowRoot.querySelector('[data-component="main-header"]');
                        if (header) break;
                    }
                }
            }

            if (!header) return;

            const scrollPosition = window.scrollY;
            if (scrollPosition > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => TCLCustomHeader.init());
    } else {
        TCLCustomHeader.init();
    }
})();
