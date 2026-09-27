import Quill from 'quill';
import 'quill/dist/quill.snow.css';

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
            theme: 'snow', placeholder: textarea.placeholder, modules: {
                toolbar: [['bold', 'italic', 'underline', 'strike'], ['link'], [{list: 'ordered'}, {list: 'bullet'}], ['blockquote'], ['clean'],],
            }, formats: ['bold', 'italic', 'underline', 'strike', 'link', 'list', 'blockquote'],
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
