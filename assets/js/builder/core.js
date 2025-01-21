/**
 * TCL Builder Core Module
 * Handles initialization and core functionality
 */

(function($) {
    'use strict';

    // Ensure TCLBuilder exists
    if (typeof window.TCLBuilder === 'undefined') {
        console.error('TCLBuilder not found. Core module initialization failed.');
        return;
    }

    // Extend existing TCLBuilder.Core
    $.extend(TCLBuilder.Core, {
        sections: [],
        activeModal: null,
        isDragging: false,
        draggedSection: null,
        editors: {
            html: null,
            css: null,
            js: null
        },
        
        init() {
            // Initialize all modules
            TCLBuilder.Events.unsubscribeAll(); // Clear any existing subscriptions
            
            // Initialize modules in order
            try {
                TCLBuilder.Sections.init();
                TCLBuilder.Editors.init();
                TCLBuilder.Modal.init();
                TCLBuilder.DragDrop.init();
                TCLBuilder.WordPress.init();
                TCLBuilder.Tabs.init();

                // Publish initialization event
                TCLBuilder.Events.publish('core:initialized');
            } catch (error) {
                console.error('Error initializing TCL Builder:', error);
            }
        }
    });

})(jQuery);
