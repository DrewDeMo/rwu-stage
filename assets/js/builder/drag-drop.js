/**
 * TCL Builder Drag & Drop Module
 * Handles drag and drop functionality using jQuery UI Sortable
 */

(function() {
    'use strict';

    // Extend existing TCLBuilder.DragDrop
    Object.assign(TCLBuilder.DragDrop, {
        init() {
            // Initialize immediately and also subscribe to section renders
            this.initializeSortable();
            TCLBuilder.Events.subscribe('sections:rendered', () => {
                this.initializeSortable();
            });
        },

        initializeSortable() {
            const $container = jQuery('.content-sections');
            
            // Destroy existing sortable instance if it exists
            if ($container.hasClass('ui-sortable')) {
                $container.sortable('destroy');
            }

            // Initialize jQuery UI Sortable with enhanced options
            $container.sortable({
                handle: '.drag-handle',
                items: '.section-container',
                placeholder: 'section-container ui-sortable-placeholder',
                helper: 'clone',
                forcePlaceholderSize: true,
                tolerance: 'intersect',
                cursor: 'grabbing',
                delay: 100,
                distance: 3,
                scroll: true,
                scrollSensitivity: 60,
                scrollSpeed: 15,
                zIndex: 100,
                revert: 150,
                
                create: (e, ui) => {
                    TCLBuilder.Logger.info('Sortable initialized', {
                        containerSelector: '.content-sections',
                        itemCount: $container.children('.section-container').length
                    });
                },
                
                start: (e, ui) => {
                    TCLBuilder.Core.isDragging = true;
                    TCLBuilder.Core.draggedSection = ui.item;
                    
                    // Match placeholder dimensions exactly
                    const $item = ui.item;
                    const height = $item.outerHeight();
                    const width = $item.outerWidth();
                    const margins = {
                        top: parseInt($item.css('marginTop')),
                        bottom: parseInt($item.css('marginBottom'))
                    };
                    
                    ui.placeholder.height(height).width(width);
                    ui.placeholder.css({
                        margin: `${margins.top}px 0 ${margins.bottom}px`
                    });
                    
                    // Add visual feedback
                    ui.helper.addClass('ui-sortable-helper');
                    $container.addClass('ui-sortable-active');
                    
                    TCLBuilder.Logger.info('Started dragging section', {
                        sectionId: $item.data('id'),
                        dimensions: { height, width, margins }
                    });
                },

                change: (e, ui) => {
                    TCLBuilder.Logger.debug('Sort order changing', {
                        placeholder: ui.placeholder.index(),
                        item: ui.item.index()
                    });
                },
                
                stop: (e, ui) => {
                    TCLBuilder.Core.isDragging = false;
                    TCLBuilder.Core.draggedSection = null;
                    
                    // Remove visual feedback
                    ui.item.removeClass('ui-sortable-helper');
                    $container.removeClass('ui-sortable-active');
                    
                    // Store the previous order for undo
                    const previousOrder = TCLBuilder.Core.sections.map(s => s.id);
                    
                    // Update order with error handling and logging
                    TCLBuilder.Sections.updateOrder()
                        .then(() => {
                            TCLBuilder.Logger.info('Section order updated successfully', {
                                newOrder: jQuery('.section-container').map(function() {
                                    return jQuery(this).data('id');
                                }).get()
                            });
                        })
                        .catch(error => {
                            TCLBuilder.Logger.error('Failed to update section order', {
                                error: error.message,
                                previousOrder
                            });
                            
                            // Revert to previous order
                            TCLBuilder.Core.sections = previousOrder.map(id => 
                                TCLBuilder.Core.sections.find(s => s.id === id)
                            );
                            TCLBuilder.Sections.renderSections();
                            
                            // Show error message
                            alert('Failed to update section order. The order has been reverted.');
                        });
                },

                over: (e, ui) => {
                    ui.placeholder.show();
                },

                out: (e, ui) => {
                    ui.placeholder.hide();
                }
            });

            // Fix template URI in preview content
            this.fixTemplateURIs();
        },

        fixTemplateURIs() {
            const templateUri = tclBuilderData.templateUri || '';
            jQuery('.preview-content img').each(function() {
                const $img = jQuery(this);
                const src = $img.attr('src');
                if (src && src.includes('<?php echo get_template_directory_uri(); ?>')) {
                    $img.attr('src', src.replace('<?php echo get_template_directory_uri(); ?>', templateUri));
                }
            });
        }
    });
})();
