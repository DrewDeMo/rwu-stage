document.addEventListener('DOMContentLoaded', () => {
    // Function to handle header scroll behavior
    function handleHeaderScroll() {
        // First try to find header in regular DOM
        let header = document.querySelector('.main-header');
        
        // If not found in regular DOM, try shadow DOM
        if (!header) {
            const shadowRoots = Array.from(document.querySelectorAll('*'))
                .filter(el => el.shadowRoot)
                .map(el => el.shadowRoot);
            
            for (const root of shadowRoots) {
                header = root.querySelector('.main-header');
                if (header) break;
            }
        }

        if (header) {
            const scrollHandler = () => {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            };

            // Add scroll listener
            window.addEventListener('scroll', scrollHandler);
            
            // Initial check
            scrollHandler();
        }
    }

    // Initialize header scroll behavior
    handleHeaderScroll();
});
