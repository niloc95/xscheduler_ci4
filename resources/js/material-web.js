// Material Web Components Entry Point
import '@material/web/all.js';
import { styles as typescaleStyles } from '@material/web/typography/md-typescale-styles.js';

// Apply Material Design typography styles
document.adoptedStyleSheets.push(typescaleStyles.styleSheet);

// Material Web Components are auto-initialized on import
