// Admin bundle entry: themed Bootstrap CSS, icons, datagrid (naja AJAX + plugins), form
// validation, plus the page-editor widgets — each in its own module under assets/admin/.
// Note: datagrid-full.js already bundles naja, nette-forms wiring and Bootstrap's Dropdown
// data-API — do not import 'bootstrap' here again or dropdowns will toggle twice.
import './scss/admin.scss';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fortawesome/fontawesome-free/css/fontawesome.min.css';
import '@fortawesome/fontawesome-free/css/solid.min.css';
import '@contributte/datagrid/dist/datagrid-full.css';
import '@contributte/datagrid/dist/datagrid-full.js';
import 'bootstrap/js/dist/alert';
import netteForms from 'nette-forms';

import { initRichText } from './admin/richtext.js';
import { initImageUploads } from './admin/image-upload.js';
import { initMasonry } from './admin/masonry.js';

netteForms.initOnLoad();
initRichText();
initImageUploads();
initMasonry();
