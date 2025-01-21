/**
 * TCL Builder Tabs Module
 * Handles tab switching functionality
 */

(function($) {
	'use strict';

	// Ensure TCLBuilder exists
	if (typeof window.TCLBuilder === 'undefined') {
		window.TCLBuilder = {};
	}

	// Create Tabs module
	TCLBuilder.Tabs = {
		init() {
			this.bindEvents();
		},

		bindEvents() {
			$(document).on('click', '.tab-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				const $this = $(this);
				const tab = $this.data('tab');
				const $container = $this.closest('.editor-wrapper');
				
				// Update tab buttons
				$container.find('.tab-btn').removeClass('active');
				$this.addClass('active');
				
				// Update panels
				$container.find('.editor-panel').removeClass('active');
				$container.find(`.editor-panel[data-panel="${tab}"]`).addClass('active');
				
				// Refresh CodeMirror if present
				if (TCLBuilder.Core.editors[tab]?.codemirror) {
					setTimeout(() => {
						TCLBuilder.Core.editors[tab].codemirror.refresh();
					}, 10);
				}

				return false;
			});

			// Prevent form submission when clicking tabs
			$(document).on('submit', '.editor-modal form', function(e) {
				e.preventDefault();
				return false;
			});
		}
	};

	// Initialize on document ready
	$(document).ready(() => {
		TCLBuilder.Tabs.init();
	});

})(jQuery);