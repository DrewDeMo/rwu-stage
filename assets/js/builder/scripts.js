(function($) {
	'use strict';

	if (typeof window.TCLBuilder === 'undefined') {
		console.error('TCLBuilder not found');
		return;
	}

	TCLBuilder.Scripts = {
		initialized: false,
		
		init() {
			if (this.initialized) return;
			
			this.bindEvents();
			
			const $container = $('.tcl-builder-scripts-field');
			const $textarea = $container.find('.script-url');
			
			if (tclBuilderScripts && tclBuilderScripts.current_scripts) {
				$textarea.val(tclBuilderScripts.current_scripts);
			}
			
			this.initialized = true;
		},

		bindEvents() {
			$('.save-scripts-btn').off('click').on('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				this.saveScripts();
			});
		},

		wrapScriptInShadowContext(script) {
            // Remove the DOMContentLoaded wrapper if it exists
            script = script.replace(/document\.addEventListener\(['"]DOMContentLoaded['"],\s*(?:function\s*\(\)\s*)?[{=]?([\s\S]*?)}\);?/m, '$1');
            
            // Replace document.querySelectorAll with root.querySelectorAll
            script = script.replace(/document\.querySelectorAll\(/g, 'querySelectorAll(');
            
            // Replace document.querySelector with root.querySelector
            script = script.replace(/document\.querySelector\(/g, 'querySelector(');
            
            // Replace document.getElementById with root.querySelector
            script = script.replace(/document\.getElementById\(['"]([^'"]+)['"]\)/g, 'querySelector("#$1")');
            
            // Replace document.addEventListener with root.addEventListener
            script = script.replace(/document\.addEventListener\(/g, 'addEventListener(');
            
            // Add support for closest() method in shadow DOM
            script = script.replace(/\.closest\(/g, '.closest(');

            return `
// Initialize function that will run in shadow DOM context
function initializeInShadowDOM() {
    try {
        const root = this.shadowRoot;
        if (!root) throw new Error('Shadow root not found');

        const querySelector = (selector) => root.querySelector(selector);
        const querySelectorAll = (selector) => root.querySelectorAll(selector);
        const addEventListener = (event, handler) => root.addEventListener(event, handler);
        
        // Store event listeners for cleanup
        const eventListeners = new Set();
        const addEventListenerWithCleanup = (element, event, handler, options) => {
            element.addEventListener(event, handler, options);
            eventListeners.add(() => element.removeEventListener(event, handler, options));
        };

        // Create a scoped closest function that respects shadow DOM boundaries
        Element.prototype._shadowClosest = function(selector) {
            let element = this;
            while (element && element !== root) {
                if (element.matches(selector)) return element;
                element = element.parentElement || element.parentNode;
            }
            return null;
        };

        // Override closest for shadow DOM context
        const originalClosest = Element.prototype.closest;
        Element.prototype.closest = function(selector) {
            return this._shadowClosest(selector) || originalClosest.call(this, selector);
        };

        // Check if GSAP is available
        if (typeof gsap === 'undefined') {
            console.warn('GSAP not found, animations will be disabled');
        }

        // Execute the script immediately since we're already in the component
        ${script}

        // Cleanup function
        return () => {
            try {
                eventListeners.forEach(cleanup => cleanup());
                eventListeners.clear();
                Element.prototype.closest = originalClosest;
                delete Element.prototype._shadowClosest;
            } catch (error) {
                console.error('Error during cleanup:', error);
            }
        };
    } catch (error) {
        console.error('Error initializing shadow DOM script:', error);
        return () => {}; // Return empty cleanup function in case of error
    }
}

// Call the initialization function and store cleanup
const cleanup = initializeInShadowDOM();

// Add cleanup to disconnectedCallback if available
if (this.disconnectedCallback) {
    const originalDisconnected = this.disconnectedCallback;
    this.disconnectedCallback = function() {
        cleanup();
        originalDisconnected.call(this);
    };
} else {
    this.disconnectedCallback = cleanup;
}`.trim();
        },




		async saveScripts() {
			const $container = $('.tcl-builder-scripts-field');
			const $textarea = $container.find('.script-url');
			
			if (!$container.length || !tclBuilderScripts?.post_id) {
				console.error('Scripts container not found or invalid post ID');
				return;
			}

			// Get the original script without wrapping
			const scripts = $textarea.val().trim();

			try {
				const response = await $.ajax({
					url: tclBuilderScripts.ajaxurl,
					type: 'POST',
					data: {
						action: 'tcl_builder_save_scripts',
						nonce: tclBuilderScripts.nonce,
						post_id: tclBuilderScripts.post_id,
						scripts: scripts,
						shadow_context: true // Flag to indicate this should use shadow DOM on frontend
					}
				});

				if (response.success) {
					if (response.data.saved_value) {
						$textarea.val(response.data.saved_value);
					}
					this.showNotice('success', response.data.message);
				} else {
					throw new Error(response.data?.message || 'Unknown error occurred');
				}
			} catch (error) {
				console.error('Save error:', error);
				this.showNotice('error', error.message || 'Failed to save scripts.');
			}
		},


		showNotice(type, message) {
			$('.tcl-builder-scripts-field .notice').remove();
			
			const $notice = $('<div>')
				.addClass(`notice notice-${type} is-dismissible`)
				.append($('<p>').text(message));

			$('.tcl-builder-scripts-field').prepend($notice);

			setTimeout(() => {
				$notice.fadeOut(() => $notice.remove());
			}, 3000);
		}
	};

	// Initialize when document is ready
	$(document).ready(() => {
		if (typeof tclBuilderScripts !== 'undefined') {
			TCLBuilder.Scripts.init();
		}
	});

})(jQuery);



