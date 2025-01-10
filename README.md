# TCL Builder Base Theme

A sophisticated WordPress theme featuring a modular page builder with real-time editing capabilities and section isolation.

## Core Features

### Page Builder
- **Section Types**
  - HTML/CSS sections with Shadow DOM isolation
  - Shortcode sections for dynamic content
  - Section designations (Store/Library/Code)
- **Drag & Drop Interface**
  - Intuitive section reordering
  - Visual feedback during drag operations
  - Section designation indicators
- **Real-Time Editing**
  - CodeMirror integration for HTML/CSS
  - Syntax highlighting and validation
  - Auto-formatting and error detection

### Advanced Features
- **Shadow DOM Integration**
  - Complete style isolation between sections
  - Prevents CSS conflicts
  - Maintains section independence
- **Campaign DID Tracking**
  - Phone number management
  - Shortcode integration `[campaign_did]`
  - Format validation (XXX-XXX-XXXX)
- **Section Management**
  - Import/Export functionality
  - Section templates
  - Version control
  - Autosave capabilities

## Technical Architecture

### JavaScript Modules
- **Core**: Central initialization and configuration
  - Location: `/assets/js/builder/core.js`
  - Purpose: Initializes builder and manages global state
  - Usage: `TCLBuilder.Core.init()`

- **Sections**: Section management and rendering
  - Location: `/assets/js/builder/sections.js`
  - Purpose: Handles section CRUD operations
  - Usage: `TCLBuilder.Sections.add()`, `TCLBuilder.Sections.update()`

- **DragDrop**: Drag and drop functionality
  - Location: `/assets/js/builder/drag-drop.js`
  - Purpose: Manages section reordering
  - Usage: `TCLBuilder.DragDrop.init()`

- **Modal**: Modal window management
  - Location: `/assets/js/builder/modal.js`
  - Purpose: Handles modal dialogs
  - Usage: `TCLBuilder.Modal.open()`, `TCLBuilder.Modal.close()`

- **Editors**: CodeMirror integration
  - Location: `/assets/js/builder/editors.js`
  - Purpose: Manages code editors
  - Usage: `TCLBuilder.Editors.initHTML()`, `TCLBuilder.Editors.initCSS()`

- **WordPress**: WordPress integration and autosave
  - Location: `/assets/js/builder/wordpress.js`
  - Purpose: Handles WP-specific functionality
  - Usage: `TCLBuilder.WordPress.save()`

- **Events**: Pub/sub event system
  - Location: `/assets/js/builder/events.js`
  - Purpose: Manages event communication
  - Usage: `TCLBuilder.Events.subscribe()`, `TCLBuilder.Events.publish()`

- **Utils**: Utility functions
  - Location: `/assets/js/builder/utils.js`
  - Purpose: Common helper functions
  - Usage: `TCLBuilder.Utils.sanitize()`

- **DID**: Campaign DID handling
  - Location: `/assets/js/builder/did.js`
  - Purpose: Manages phone number functionality
  - Usage: `TCLBuilder.DID.validate()`

### CSS Architecture
- **Variables**: Global CSS variables
  - Location: `/assets/css/variables.css`
  - Purpose: Theme-wide design tokens

- **Base**: Core styles
  - Location: `/assets/css/base.css`
  - Purpose: Foundation styles

- **Components**: Modular component styles
  - Location: `/assets/css/components/*.css`
  - Purpose: Reusable UI components

- **Admin**: WordPress admin integration
  - Location: `/assets/css/admin.css`
  - Purpose: Admin interface styling

- **Frontend**: Public-facing styles
  - Location: `/assets/css/frontend.css`
  - Purpose: Theme frontend styling

### PHP Classes
- **TCL_Builder**: Main builder class
  - Location: `/inc/builder/class-tcl-builder.php`
  - Purpose: Core builder functionality
  - Usage: `TCL_Builder::get_instance()`

- **TCL_Builder_AJAX**: AJAX handler
  - Location: `/inc/builder/class-tcl-builder-ajax.php`
  - Purpose: Handles AJAX requests
  - Key Actions: `tcl_builder_save_sections`, `tcl_builder_load_sections`

- **TCL_Builder_DID**: DID handler
  - Location: `/inc/builder/class-tcl-builder-did.php`
  - Purpose: Phone number management
  - Usage: `[campaign_did]` shortcode

- **TCL_Builder_Meta**: Meta handler
  - Location: `/inc/builder/class-tcl-builder-meta.php`
  - Purpose: Manages section metadata
  - Usage: `TCL_Builder_Meta::get_sections()`, `TCL_Builder_Meta::update_sections()`

- **TCL_Builder_Revisions**: Revision system
  - Location: `/inc/builder/class-tcl-builder-revisions.php`
  - Purpose: Version control for sections
  - Hooks: `tcl_builder_sections_updated`

- **TCL_Builder_Sections_Manager**: Section management
  - Location: `/inc/builder/class-tcl-builder-sections-manager.php`
  - Purpose: Import/Export functionality
  - Usage: Tools > Builder Sections

- **TCL_Builder_Shared_Sections**: Shared sections
  - Location: `/inc/builder/class-tcl-builder-shared-sections.php`
  - Purpose: Cross-site section sharing
  - Usage: `[shared_section id="123"]`

- **TCL_Builder_Logger**: Logging system
  - Location: `/inc/class-tcl-builder-logger.php`
  - Purpose: Debug and error logging
  - Usage: `TCL_Builder_Logger::get_instance()->log()`

### WordPress Integration
- **Meta Handling**
  - Key: `_tcl_builder_sections`
  - Storage: JSON encoded section data
  - Revision Support: Yes

- **AJAX Operations**
  - Nonce: `tcl_builder_nonce`
  - Actions: Save, Load, Delete, Reorder
  - Security: Capability checking

- **Revision System**
  - Auto-save Support: Yes
  - Restore Capability: Yes
  - Comparison UI: Yes

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Modern browser with Shadow DOM support
- jQuery 1.13+
- jQuery UI (Sortable)

## Installation

1. Upload theme to `/wp-content/themes/`
2. Activate through WordPress admin
3. Configure theme settings if needed

## Development

### Local Setup
1. Clone repository
2. Install dependencies
3. Configure wp-config.php
4. Set up local environment variables

### Debugging
1. Enable WP_DEBUG in wp-config.php
2. Check logs in wp-content/uploads/tcl-builder-logs/
3. Use browser console for JavaScript debugging
4. Monitor AJAX requests in Network tab

### Common Issues
1. Section not saving
   - Check AJAX response in Network tab
   - Verify nonce
   - Check user capabilities

2. Styles not applying
   - Verify Shadow DOM support
   - Check CSS syntax
   - Inspect section isolation

3. DID validation failing
   - Verify format (XXX-XXX-XXXX)
   - Check AJAX handler
   - Monitor PHP error log

### Adding New Features
1. Identify appropriate module
2. Follow existing patterns
3. Update relevant classes
4. Add necessary hooks
5. Test thoroughly
6. Update documentation

## API Reference

### Actions
- `tcl_builder_sections_updated`: Fired after sections are saved
- `tcl_builder_section_rendered`: Fired after section render
- `tcl_builder_init`: Fired on builder initialization

### Filters
- `tcl_builder_section_html`: Modify section HTML
- `tcl_builder_section_css`: Modify section CSS
- `tcl_builder_common_shortcodes`: Add custom shortcodes

### JavaScript API
```javascript
// Subscribe to events
TCLBuilder.Events.subscribe('section:added', function(section) {
    console.log('New section:', section);
});

// Add new section
TCLBuilder.Sections.add({
    type: 'html',
    title: 'New Section',
    content: {
        html: '<div>Content</div>',
        css: '.section { color: blue; }'
    }
});

// Save changes
TCLBuilder.WordPress.save();
```

### PHP API
```php
// Get sections
$sections = TCL_Builder_Meta::get_sections($post_id);

// Update sections
TCL_Builder_Meta::update_sections($post_id, $sections);

// Log debug info
TCL_Builder_Logger::get_instance()->log('Debug message', 'info', ['context' => 'value']);
```

## Security

- Nonce verification for AJAX
- Capability checking
- Data sanitization
- Error logging
- XSS prevention
- Shadow DOM isolation

## Browser Support

- Chrome 60+
- Firefox 63+
- Safari 11+
- Edge 79+

## License

GNU General Public License v2 or later

## Credits

- jQuery UI for drag-drop
- CodeMirror for editing
- Lucide for icons
- WordPress core team
