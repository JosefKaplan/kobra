import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// Enhance the plain component-editor textareas (app/Modules/Admin/Page/_componentEditor.latte) with
// a Quill WYSIWYG widget, plus a toggle to edit the raw HTML source directly in the same textarea.
// The textarea stays in the DOM (hidden while Quill is active) as the no-JS fallback and as the
// field that actually gets posted — its value is synced from Quill right before submit, unless the
// user is currently editing the source.
export function initRichText(scope = document) {
	scope.querySelectorAll('textarea[data-richtext]').forEach((textarea) => {
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
}
