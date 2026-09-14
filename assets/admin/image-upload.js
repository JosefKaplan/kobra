// Drag & drop / click-to-browse image upload, layered on top of a plain image-URL text input
// (see the `imageUploadField` Latte define in app/Modules/Admin/Page/_componentEditor.latte).
// The text input stays the value that actually gets posted — pasting a URL by hand still works,
// this is purely additive. Also reused by masonry.js for its per-row and bulk uploaders.

// Wire click-to-browse + drag & drop highlight onto a dropzone. `onFiles(FileList, fromDrop)` is
// called with the picked files; `fromDrop` tells the caller whether their order is meaningful
// (a drop preserves the file-manager selection order, a dialog FileList does not).
export function wireDropzone(dropzone, fileInput, browseTrigger, onFiles) {
	dropzone.addEventListener('click', () => fileInput.click());
	browseTrigger?.addEventListener('click', (e) => {
		e.stopPropagation();
		fileInput.click();
	});
	fileInput.addEventListener('change', () => {
		// Snapshot then clear: a file input only fires `change` when its value changes, so without
		// the reset picking the same file again (after a failed upload, say) would do nothing.
		const files = Array.from(fileInput.files);
		fileInput.value = '';
		onFiles(files, false);
	});

	['dragover', 'dragleave', 'drop'].forEach((eventName) => {
		dropzone.addEventListener(eventName, (e) => {
			// Let non-file drags (e.g. the masonry row-reorder drag, which carries only text/plain)
			// bubble through untouched — otherwise stopPropagation() here would block the sortable
			// container from seeing the pointer move over a row's image field.
			if (!Array.from(e.dataTransfer?.types || []).includes('Files')) return;
			e.preventDefault();
			e.stopPropagation();
			// dragleave also fires when the pointer moves onto a child element (the preview image,
			// the hint text) inside the dropzone, not just when it actually leaves it — relatedTarget
			// is where the pointer is going, so only clear the highlight once that's truly outside.
			if (eventName === 'dragleave' && dropzone.contains(e.relatedTarget)) return;
			dropzone.classList.toggle('image-upload-dropzone-active', eventName === 'dragover');
		});
	});
	dropzone.addEventListener('drop', (e) => onFiles(e.dataTransfer.files, true));
}

// Append a loading overlay onto a dropzone. It's a sibling on top, not an innerHTML swap —
// swapping would detach the browse trigger / file input the listeners are bound to, silently
// breaking the widget after the first upload. Returns { update(text), remove() }.
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
		update: render,
		remove() {
			dropzone.classList.remove('image-upload-dropzone-loading');
			overlay.remove();
		},
	};
}

// Point a widget's text input + <img> preview at an uploaded URL, creating the preview element
// if the field started empty. `root` is any element containing the widget's `[data-image-upload-*]`.
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

// Mirrors App\Helpers\ImageUploader::MAX_SIZE — keep the two in sync. The server enforces this
// regardless; checking here just avoids uploading a large file over a slow connection only to
// have it rejected afterwards (worse in the bulk uploader, where several would queue up first).
const MAX_UPLOAD_SIZE = 8 * 1024 * 1024;

// POST one file to the widget's endpoint. Resolves with the uploaded URL, rejects with the
// server's error message (or a generic network-failure message).
export function uploadImage(endpoint, file) {
	if (file.size > MAX_UPLOAD_SIZE) {
		return Promise.reject(new Error('Soubor je příliš velký (max 8 MB).'));
	}
	const formData = new FormData();
	formData.append('image', file);
	return fetch(endpoint, { method: 'POST', body: formData })
		.then((response) => response.json())
		.then((data) => {
			if (data.error) throw new Error(data.error);
			return data.url;
		}, () => {
			throw new Error('Nahrávání se nezdařilo. Zkuste to prosím znovu.');
		});
}

// Enhance one server-rendered `[data-image-upload]` widget. Called at load for every widget and
// again by masonry.js for each row it clones at runtime (listeners don't survive cloning).
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

	wireDropzone(
		dropzone,
		root.querySelector('[data-image-upload-input]'),
		root.querySelector('[data-image-upload-browse]'),
		(files) => upload(files[0]),
	);
}

export function initImageUploads(scope = document) {
	scope.querySelectorAll('[data-image-upload]').forEach(enhanceImageUpload);
}