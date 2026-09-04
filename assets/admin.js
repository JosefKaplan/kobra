// Admin bundle: themed Bootstrap CSS, icons, datagrid (naja AJAX + plugins), form validation.
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
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

netteForms.initOnLoad();

// Enhances the plain component-editor textareas (app/Modules/Admin/Page/edit.latte) with a
// Quill WYSIWYG widget, plus a toggle to switch to editing the raw HTML source directly in the
// same textarea. The textarea itself stays in the DOM (hidden while Quill is active) as the
// no-JS fallback and as the field that actually gets posted — its value is synced from Quill
// right before submit, unless the user is currently editing the source.
document.querySelectorAll('textarea[data-richtext]').forEach((textarea) => {
	const toggle = document.createElement('button');
	toggle.type = 'button';
	toggle.className = 'btn btn-sm btn-link text-decoration-none px-0 mb-1 d-block';
	toggle.innerHTML = '<i class="bi bi-code-slash me-1"></i>Zdrojový kód';

	const editorContainer = document.createElement('div');

	textarea.insertAdjacentElement('afterend', editorContainer);
	textarea.insertAdjacentElement('afterend', toggle);
	textarea.hidden = true;

	const quill = new Quill(editorContainer, {
		theme: 'snow',
		modules: {
			toolbar: [
				['bold', 'italic', 'underline', 'strike'],
				['link'],
				[{ list: 'ordered' }, { list: 'bullet' }],
				['blockquote'],
				['clean'],
			],
		},
		formats: ['bold', 'italic', 'underline', 'strike', 'link', 'list', 'blockquote'],
	});
	quill.clipboard.dangerouslyPasteHTML(textarea.value);

	let sourceMode = false;
	toggle.addEventListener('click', () => {
		sourceMode = !sourceMode;

		if (sourceMode) {
			textarea.value = quill.root.innerHTML;
			textarea.hidden = false;
			editorContainer.hidden = true;
			toggle.innerHTML = '<i class="bi bi-eye me-1"></i>Náhled';
		} else {
			quill.setText('');
			quill.clipboard.dangerouslyPasteHTML(textarea.value);
			textarea.hidden = true;
			editorContainer.hidden = false;
			toggle.innerHTML = '<i class="bi bi-code-slash me-1"></i>Zdrojový kód';
		}
	});

	textarea.form?.addEventListener('submit', () => {
		if (!sourceMode) {
			textarea.value = quill.root.innerHTML;
		}
	});
});
