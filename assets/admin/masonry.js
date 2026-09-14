// Page-editor widgets of the `masonry` container and its `masonry_item` tiles (see
// app/Modules/Admin/Page/masonry.latte and the masonry_item form in _componentEditor.latte):
//   - `[data-masonry-bulk-create]` (masonry screen): drop N images → N new tiles. The files go
//     through the shared single-file `uploadImage!` endpoint one at a time (so PHP's request-wide
//     post_max_size / max_file_uploads never apply), then the collected URLs are POSTed to
//     `createMasonryItems!`, which creates one tile per URL and redirects back.
//   - `[data-masonry-item]` (one tile's form): a `[data-gallery-items]` list of photo rows
//     (`[data-gallery-item]`), cloned from `[data-gallery-template]` with the '__INDEX__'
//     placeholder, plus a bulk dropzone that appends one row per uploaded file, and grip-handle
//     drag reordering. Gallery order is purely the DOM/POST order of the rows — the server
//     re-indexes with array_values() on save (PagePresenter::handleSaveComponent()).
import {
	applyUploadedUrl,
	enhanceImageUpload,
	spinnerOverlay,
	uploadImage,
	wireDropzone,
} from './image-upload.js';

let rowSeq = 0;
const collator = new Intl.Collator('cs', { numeric: true, sensitivity: 'base' });

// Clone one gallery row out of `template` with a fresh unique index, append it to `container`,
// wire up its image-upload widget (listeners don't survive cloning), and return it.
function cloneRow(container, template) {
	const index = `new${rowSeq++}`;
	const temp = document.createElement('div');
	temp.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
	const row = temp.firstElementChild;
	container.appendChild(row);
	row.querySelectorAll('[data-image-upload]').forEach(enhanceImageUpload);
	return row;
}

// Upload the picked files one at a time through `endpoint`, reporting progress on `dropzone`.
// Files whose type says they're clearly not images are rejected up front and never sent, but
// still reported — otherwise a mixed selection where every actual image succeeds would look like
// a fully successful upload. File.type comes from the extension and is '' for unmapped or
// extensionless files, so those are sent and left to the server's real (getimagesize) check.
// `onUploaded(url)` runs after each success. Returns { total, urls, failed }.
const isNonImage = (f) => f.type !== '' && !f.type.startsWith('image/');
async function uploadFiles(dropzone, endpoint, fileList, ordered, onUploaded) {
	const allFiles = Array.from(fileList || []);
	const files = allFiles.filter((f) => !isNonImage(f));
	// A drop preserves the file-manager selection order ("podle označení"); a dialog FileList
	// exposes no such order, so sort it alphabetically as a stable fallback.
	if (!ordered) files.sort((a, b) => collator.compare(a.name, b.name));

	const spinner = spinnerOverlay(dropzone, `Nahrávám 1/${files.length}…`);
	const failed = allFiles.filter(isNonImage).map((f) => `${f.name}: není obrázek`);
	const urls = [];
	for (let i = 0; i < files.length; i++) {
		spinner.update(`Nahrávám ${i + 1}/${files.length}…`);
		try {
			const url = await uploadImage(endpoint, files[i]);
			urls.push(url);
			onUploaded?.(url);
		} catch (e) {
			failed.push(`${files[i].name}: ${e.message}`);
		}
	}
	spinner.remove();
	return { total: allFiles.length, urls, failed };
}

function showSummary(errorEl, result) {
	if (!result.failed.length) return;
	errorEl.textContent = `Nahráno ${result.urls.length}/${result.total}. Nepodařilo se: ${result.failed.join(' · ')}`;
	errorEl.hidden = false;
}

// Bulk dropzone wiring shared by both dropzones — `[data-dropzone]`/`[data-input]`/`[data-browse]`/
// `[data-error]` inside `bulkEl`. `onDone(result)` runs once all files are processed.
function wireBulkDropzone(bulkEl, onUploaded, onDone) {
	if (!bulkEl) return;
	const dropzone = bulkEl.querySelector('[data-dropzone]');
	const errorEl = bulkEl.querySelector('[data-error]');
	const endpoint = bulkEl.dataset.uploadEndpoint;
	let loading = false;

	wireDropzone(dropzone, bulkEl.querySelector('[data-input]'), bulkEl.querySelector('[data-browse]'), async (fileList, ordered) => {
		if (loading || !fileList?.length) return;
		errorEl.hidden = true;
		loading = true;
		const result = await uploadFiles(dropzone, endpoint, fileList, ordered, onUploaded);
		loading = false;
		showSummary(errorEl, result);
		onDone?.(result);
	});
}

// Native HTML5 drag & drop reordering of one tile's gallery rows. A dedicated grip handle per
// row: the row is only `draggable` while the pointer went down on its own handle, otherwise the
// text inputs inside it couldn't be selected with the mouse.
function wireSorting(container) {
	if (!container || container.dataset.sortingWired) return;
	container.dataset.sortingWired = '1';

	let dragged = null;
	const clearDraggable = () => {
		container.querySelectorAll('[data-gallery-item][draggable]').forEach((r) => r.removeAttribute('draggable'));
	};

	container.addEventListener('pointerdown', (e) => {
		const row = e.target.closest('[data-gallery-item-drag-handle]')?.closest('[data-gallery-item]');
		if (row) row.draggable = true;
	});
	container.addEventListener('pointerup', clearDraggable);

	container.addEventListener('dragstart', (e) => {
		// dragstart's e.target is the element that has draggable=true — match it directly rather
		// than via closest(), so a drag that isn't one of this list's own rows is left alone.
		const row = e.target;
		if (!(row instanceof Element) || !row.matches('[data-gallery-item]') || row.parentElement !== container) return;
		if (!row.draggable) {
			e.preventDefault();
			return;
		}
		dragged = row;
		row.classList.add('masonry-item-dragging');
		e.dataTransfer.effectAllowed = 'move';
		// Firefox needs some payload set for the drag to start at all.
		e.dataTransfer.setData('text/plain', '');
	});

	container.addEventListener('dragover', (e) => {
		if (!dragged) return;
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		const over = e.target.closest('[data-gallery-item]');
		if (!over || over === dragged || over.parentElement !== container) return;
		const rect = over.getBoundingClientRect();
		const after = e.clientY > rect.top + rect.height / 2;
		container.insertBefore(dragged, after ? over.nextElementSibling : over);
	});

	container.addEventListener('dragend', () => {
		dragged?.classList.remove('masonry-item-dragging');
		dragged = null;
		clearDraggable();
	});
}

// One tile's form: add button, bulk gallery dropzone, sorting.
function initTile(tile) {
	const container = tile.querySelector('[data-gallery-items]');
	const template = tile.querySelector('[data-gallery-template]');
	if (!container || !template) return;

	const addRow = () => cloneRow(container, template);
	tile.querySelector('[data-gallery-add]')?.addEventListener('click', addRow);
	wireBulkDropzone(tile.querySelector('[data-gallery-bulk]'), (url) => applyUploadedUrl(addRow(), url));
	wireSorting(container);
}

// The masonry screen's create-tiles dropzone: after the uploads, POST the URLs as a plain form
// (createMasonryItems! redirects back to the screen, which then shows the new tiles).
function initBulkCreate(bulkEl) {
	wireBulkDropzone(bulkEl, null, (result) => {
		// Nothing uploaded: the failure summary stays on screen (no redirect to wipe it).
		if (!result.urls.length) return;
		const form = document.createElement('form');
		form.method = 'post';
		form.action = bulkEl.dataset.createEndpoint;
		form.hidden = true;
		const add = (name, value) => {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild(input);
		};
		result.urls.forEach((url) => add('urls[]', url));
		// The redirect would otherwise hide which files failed — the presenter re-shows them as a flash.
		result.failed.forEach((message) => add('failed[]', message));
		document.body.appendChild(form);
		form.submit();
	});
}

export function initMasonry(scope = document) {
	scope.querySelectorAll('[data-masonry-item]').forEach(initTile);
	scope.querySelectorAll('[data-masonry-bulk-create]').forEach(initBulkCreate);

	// Gallery row removal — delegated so it also covers rows added at runtime.
	document.addEventListener('click', (e) => {
		if (e.target.closest('[data-gallery-item-remove]')) {
			e.target.closest('[data-gallery-item]')?.remove();
		}
	});
}
