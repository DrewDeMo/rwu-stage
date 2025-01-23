/**
 * TCL Builder Core Module
 * Handles initialization and core functionality
 */

(function($) {
    'use strict';

    if (typeof window.TCLBuilder === 'undefined') {
        console.error('[ShadowDOM] TCLBuilder not found. Core module initialization failed.');
        return;
    }

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
        
        createShadowRoot(section) {
            if (!section.hasAttribute('data-shadow-dom')) return null;
            
            try {
                const shadowRoot = section.attachShadow({ mode: 'open' });
                const rootId = `shadow-${Date.now()}`;
                section.dataset.shadowId = rootId;
                TCLBuilder.Events.registerShadowContext(rootId, shadowRoot);
                TCLBuilder.Events.publish('shadow:section:created', { section, shadowRoot });
                return shadowRoot;
            } catch (error) {
                console.error('[ShadowDOM] Failed to create shadow root:', error);
                return null;
            }
        },

        destroyShadowRoot(section) {
            const rootId = section.dataset.shadowId;
            if (rootId) {
                TCLBuilder.Events.unregisterShadowContext(rootId);
                TCLBuilder.Events.publish('shadow:section:destroyed', { section });
                delete section.dataset.shadowId;
            }
        },

        init() {
            TCLBuilder.Events.unsubscribeAll();
            
            try {
                // Initialize shadow DOM support
                TCLBuilder.Events.subscribe('section:created', (section) => {
                    if (section.hasAttribute('data-shadow-dom')) {
                        this.createShadowRoot(section);
                    }
                });

                TCLBuilder.Events.subscribe('section:destroyed', (section) => {
                    this.destroyShadowRoot(section);
                });

                // Initialize core modules
                TCLBuilder.Sections.init();
                TCLBuilder.Editors.init();
                TCLBuilder.Modal.init();
                TCLBuilder.DragDrop.init();
                TCLBuilder.WordPress.init();
                TCLBuilder.Tabs.init();

                TCLBuilder.Events.publish('core:initialized');
            } catch (error) {
                console.error('[ShadowDOM] Error initializing TCL Builder:', error);
            }
        }
    });

})(jQuery);
