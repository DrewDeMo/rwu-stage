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
  - Shortcode integration
  - Format validation
- **Section Management**
  - Import/Export functionality
  - Section templates
  - Version control
  - Autosave capabilities

## Technical Architecture

### JavaScript Modules
- **Core**: Central initialization and configuration
- **Sections**: Section management and rendering
- **DragDrop**: Drag and drop functionality
- **Modal**: Modal window management
- **Editors**: CodeMirror integration
- **WordPress**: WordPress integration and autosave
- **Events**: Pub/sub event system
- **Utils**: Utility functions
- **DID**: Campaign DID handling

### CSS Architecture
- **Variables**: Global CSS variables
- **Base**: Core styles
- **Components**: Modular component styles
- **Admin**: WordPress admin integration
- **Frontend**: Public-facing styles

### WordPress Integration
- Custom post meta handling
- AJAX operations
- Revision system
- Autosave functionality
- Nonce verification
- Capability checking

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

## Usage

### Adding Sections

1. Click "Add Section"
2. Choose section type:
   - HTML/CSS Section
   - Shortcode Section
3. Configure section:
   - Set title
   - Add content
   - Choose designation
4. Save changes

### Managing Sections

- Drag sections to reorder
- Use designation selector to categorize
- Export/Import sections
- Access revision history

### Campaign DID

1. Enter DID in XXX-XXX-XXXX format
2. Save DID
3. Use [campaign_did] shortcode in content

## Development

### File Structure
```
TCL_Builder_Base/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── base.css
│   │   ├── buttons.css
│   │   ├── ...
│   ├── js/
│   │   ├── builder.js
│   │   ├── builder/
│   │   │   ├── core.js
│   │   │   ├── sections.js
│   │   │   ├── ...
│   ├── fonts/
│   │   ├── Gotham/
│   │   ├── Futura/
├── inc/
│   ├── builder/
│   │   ├── class-tcl-builder.php
│   │   ├── class-tcl-builder-ajax.php
│   │   ├── ...
├── template-parts/
```

### Key Components

#### Section Data Structure
```javascript
{
  id: number,
  type: 'html' | 'shortcode',
  title: string,
  designation: 'store' | 'library' | 'code',
  content: {
    html?: string,
    css?: string
  } | string
}
```

#### Events System
```javascript
TCLBuilder.Events.subscribe('event:name', callback);
TCLBuilder.Events.publish('event:name', data);
```

### Building

1. Install dependencies
2. Compile assets
3. Run tests if available

## Security

- Nonce verification for AJAX
- Capability checking
- Data sanitization
- Error logging
- XSS prevention

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
