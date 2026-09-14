// Dynamic rows for the `masonry` component type — two independent nesting levels (see
// `bookRow`/`galleryImageRow` in app/Modules/Admin/Page/_componentEditor.latte):
//   - `[data-books]` holds one row per book (`[data-book]`), templated by `[data-book-template]`
//     with the placeholder '__BOOK_INDEX__'.
//   - each book row owns its own `[data-book-gallery]` list of gallery photos
//     (`[data-gallery-item]`), templated by that book's own `[data-gallery-template]` with the
//     placeholder '__GALLERY_INDEX__'.
// Cloning + renumbering a template (rather than keeping a live counter in sync with rows removed
// out of order) works at both levels since indices never need to be contiguous — the server
// re-indexes both with array_values() on save (see PagePresenter::handleSaveComponent()).
import {
	applyUploadedUrl,
	enhanceImageUpload,
	spinnerOverlay,
	uploadImage,
	wireDropzone,
} from './image-upload.js';

let rowSeq = 0;
const collator = new Intl.Collator('cs', { numeric: true, sensitivity: 'base' });

// Clone one row out of `template`, replacing every occurrence of `placeholder` with a fresh
// unique index, append it to `container`, wire up any image-upload widgets inside it (listeners
// don't survive cloning), and return the new row element.
function cloneRow(container, template, placeholder) {
	const index = `new${rowSeq++}`;
	const temp = document.createElement('div');
	temp.innerHTML = template.innerHTML.replaceAll(placeholder, index).trim();
	const row = temp.firstElementChild;
	container.appendChild(row);
	row.querySelectorAll('[data-image-upload]').forEach(enhanceImageUpload);
	return row;
}

// Generic bulk-uploader wiring, shared by the top-level (new book) and per-book (new gallery
// photo) dropzones — each is just `[data-dropzone]`/`[data-input]`/`[data-browse]`/`[data-error]`
// inside its own wrapper. Uploads the picked files one at a time through the single-file
// `uploadImage!` endpoint (one file per request sidesteps PHP's request-wide post_max_size /
// max_file_uploads limits — only upload_max_filesize matters, same as the single-row widget),
// calling `addRow()` for each and applying the resulting URL to its upload field.
function wireBulkUploader(bulkEl, addRow) {
	if (!bulkEl) return;

	const dropzone = bulkEl.querySelector('[data-dropzone]');
	const errorEl = bulkEl.querySelector('[data-error]');
	const endpoint = bulkEl.dataset.uploadEndpoint;
	let loading = false;

	const uploadMany = async (fileList, ordered) => {
		errorEl.hidden = true;
		const allFiles = Array.from(fileList || []);
		if (allFiles.length === 0 || loading) return;

		const files = allFiles.filter((f) => f.type.startsWith('image/'));
		// A drop preserves the file-manager selection order ("podle označení"); a dialog FileList
		// exposes no such order, so sort it alphabetically as a stable fallback.
		if (!ordered) files.sort((a, b) => collator.compare(a.name, b.name));

		loading = true;
		const spinner = spinnerOverlay(dropzone, `Nahrávám 1/${files.length}…`);
		// Non-image files are rejected up front and never sent, but still need to show up in the
		// summary below — otherwise a mixed selection where every actual image upload succeeds
		// looks like a fully successful upload, silently hiding that something was skipped.
		const failed = allFiles.filter((f) => !f.type.startsWith('image/')).map((f) => `${f.name}: není obrázek`);
		let succeeded = 0;
		for (let i = 0; i < files.length; i++) {
			spinner.update(`Nahrávám ${i + 1}/${files.length}…`);
			try {
				// Await the upload before creating the row — addRow() as a direct argument would
				// evaluate (and insert into the DOM) before the awaited call settles, leaving a
				// permanent blank row behind whenever the upload itself fails.
				const url = await uploadImage(endpoint, files[i]);
				applyUploadedUrl(addRow(), url);
				succeeded++;
			} catch (e) {
				failed.push(`${files[i].name}: ${e.message}`);
			}
		}
		spinner.remove();
		loading = false;

		if (failed.length) {
			errorEl.textContent = `Nahráno ${succeeded}/${allFiles.length}. Nepodařilo se: ${failed.join(' · ')}`;
			errorEl.hidden = false;
		}
	};

	wireDropzone(dropzone, bulkEl.querySelector('[data-input]'), bulkEl.querySelector('[data-browse]'), uploadMany);
}

// Native HTML5 drag & drop reordering, generic over which container/row/handle it wires — used
// for both the top-level books list and each book's own gallery list independently. The server
// keeps arrays in POST-body order and re-indexes with array_values() on save, so the DOM order of
// the rows *is* the saved order — nothing to renumber, just move the row and let the next
// "Uložit" persist it.
//
// A dedicated grip handle per row: the row is only `draggable` while the pointer went down on its
// own handle, otherwise the text inputs inside it couldn't be selected with the mouse.
function wireSorting(container, rowSelector, handleSelector) {
	if (!container || container.dataset.sortingWired) return;
	container.dataset.sortingWired = '1';

	let dragged = null;

	const clearDraggable = () => {
		container.querySelectorAll(`${rowSelector}[draggable]`).forEach((r) => r.removeAttribute('draggable'));
	};

	container.addEventListener('pointerdown', (e) => {
		const row = e.target.closest(handleSelector)?.closest(rowSelector);
		if (row) row.draggable = true;
	});
	container.addEventListener('pointerup', clearDraggable);

	container.addEventListener('dragstart', (e) => {
		// dragstart's e.target is always the element that actually has draggable=true — never a
		// bubbled-up descendant — but the event still bubbles through every ancestor's listener,
		// including an outer container at a different nesting level (e.g. a gallery item's
		// dragstart reaching the *books* container too). e.target.closest(rowSelector) would wrongly
		// match there: the gallery item's ancestor book row also matches '[data-book]', and that
		// book row's own parentElement genuinely is the books container. Matching e.target itself
		// (not an ancestor found via closest()) is what actually tells the two apart.
		const row = e.target;
		if (!(row instanceof Element) || !row.matches(rowSelector) || row.parentElement !== container) return;
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
		const over = e.target.closest(rowSelector);
		// Ignore rows outside this container — e.g. a gallery item's drag hovering over a book row.
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

// Wire one book row's own gallery (add button, bulk dropzone, sorting) — called both for book
// rows already in the DOM at page load and for one created at runtime by addBookRow() below.
function wireBookRow(bookRow) {
	const galleryContainer = bookRow.querySelector('[data-book-gallery]');
	const galleryTemplate = bookRow.querySelector('[data-gallery-template]');
	if (!galleryContainer || !galleryTemplate) return;

	const addGalleryRow = () => cloneRow(galleryContainer, galleryTemplate, '__GALLERY_INDEX__');

	bookRow.querySelector('[data-gallery-add]')?.addEventListener('click', addGalleryRow);
	wireBulkUploader(bookRow.querySelector('[data-book-gallery-bulk]'), addGalleryRow);
	wireSorting(galleryContainer, '[data-gallery-item]', '[data-gallery-item-drag-handle]');
}

export function initMasonry(scope = document) {
	scope.querySelectorAll('[data-masonry-block]').forEach((block) => {
		const booksContainer = block.querySelector('[data-books]');
		const bookTemplate = block.querySelector('[data-book-template]');
		const addButton = block.querySelector('[data-book-add]');
		if (!booksContainer || !bookTemplate || !addButton) return;

		const addBookRow = () => {
			const row = cloneRow(booksContainer, bookTemplate, '__BOOK_INDEX__');
			wireBookRow(row);
			return row;
		};

		addButton.addEventListener('click', addBookRow);
		wireBulkUploader(block.querySelector('[data-book-bulk]'), addBookRow);
		wireSorting(booksContainer, '[data-book]', '[data-book-drag-handle]');
		booksContainer.querySelectorAll('[data-book]').forEach(wireBookRow);
	});

	// Row removal — delegated so it also covers rows added at runtime. Gallery items are checked
	// first: a gallery item's remove button also sits inside a book row, so it must never be
	// mistaken for the book's own remove button (which would delete the whole block).
	document.addEventListener('click', (e) => {
		if (e.target.closest('[data-gallery-item-remove]')) {
			e.target.closest('[data-gallery-item]')?.remove();
		} else if (e.target.closest('[data-book-remove]')) {
			e.target.closest('[data-book]')?.remove();
		}
	});
}