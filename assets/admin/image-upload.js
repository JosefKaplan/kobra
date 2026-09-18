export function wireDropzone(dropzone, fileInput, browseTrigger, onFiles) {
    dropzone.addEventListener('click', () => fileInput.click());
    browseTrigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.click();
    });
    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files);
        fileInput.value = '';
        onFiles(files, false);
    });

    ['dragover', 'dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (e) => {
            if (!Array.from(e.dataTransfer?.types || []).includes('Files')) return;
            e.preventDefault();
            e.stopPropagation();
            if (eventName === 'dragleave' && dropzone.contains(e.relatedTarget)) return;
            dropzone.classList.toggle('image-upload-dropzone-active', eventName === 'dragover');
        });
    });
    dropzone.addEventListener('drop', (e) => onFiles(e.dataTransfer.files, true));
}

export function spinnerOverlay(dropzone, label = 'Nahrávám…') {
    dropzone.classList.add('image-upload-dropzone-loading');
    const overlay = document.createElement('div');
    overlay.className = 'image-upload-spinner-overlay';
    const render = (text) => {
        overlay.innerHTML = `<span class="image-upload-spinner" aria-hidden="true"></span>${text}`;
    };
    render(label);
    dropzone.appendChild(overlay);

    return {
        update: render, remove() {
            dropzone.classList.remove('image-upload-dropzone-loading');
            overlay.remove();
        },
    };
}

export function applyUploadedUrl(root, url) {
    const valueInput = root.querySelector('[data-image-upload-value]');
    const dropzone = root.querySelector('[data-image-upload-dropzone]');
    if (valueInput) valueInput.value = url;
    if (!dropzone) return;

    let preview = dropzone.querySelector('[data-image-upload-preview]');
    if (!preview) {
        preview = document.createElement('img');
        preview.className = 'image-upload-preview';
        preview.setAttribute('data-image-upload-preview', '');
        preview.alt = '';
        dropzone.insertBefore(preview, dropzone.firstChild);
    }
    preview.src = url;
}

const MAX_UPLOAD_SIZE = 8 * 1024 * 1024;

export function uploadImage(endpoint, file) {
    if (file.size > MAX_UPLOAD_SIZE) {
        return Promise.reject(new Error('Soubor je příliš velký (max 8 MB).'));
    }
    const formData = new FormData();
    formData.append('image', file);
    return fetch(endpoint, {method: 'POST', body: formData})
        .then((response) => response.json())
        .then((data) => {
            if (data.error) throw new Error(data.error);
            return data.url;
        }, () => {
            throw new Error('Nahrávání se nezdařilo. Zkuste to prosím znovu.');
        });
}

export function enhanceImageUpload(root) {
    const dropzone = root.querySelector('[data-image-upload-dropzone]');
    const errorEl = root.querySelector('[data-image-upload-error]');
    const endpoint = root.dataset.uploadEndpoint;

    const showError = (message) => {
        errorEl.textContent = message;
        errorEl.hidden = false;
    };

    const upload = (file) => {
        errorEl.hidden = true;
        if (!file || dropzone.classList.contains('image-upload-dropzone-loading')) return;

        const spinner = spinnerOverlay(dropzone);
        uploadImage(endpoint, file)
            .then((url) => applyUploadedUrl(root, url))
            .catch((e) => showError(e.message))
            .finally(() => spinner.remove());
    };

    wireDropzone(dropzone, root.querySelector('[data-image-upload-input]'), root.querySelector('[data-image-upload-browse]'), (files) => upload(files[0]),);
}

export function initImageUploads(scope = document) {
    scope.querySelectorAll('[data-image-upload]').forEach(enhanceImageUpload);
}