import {
    applyUploadedUrl, enhanceImageUpload, spinnerOverlay, uploadImage, wireDropzone,
} from './image-upload.js';

let rowSeq = 0;
const collator = new Intl.Collator('cs', {numeric: true, sensitivity: 'base'});

function cloneRow(container, template) {
    const index = `new${rowSeq++}`;
    const temp = document.createElement('div');
    temp.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
    const row = temp.firstElementChild;
    container.appendChild(row);
    row.querySelectorAll('[data-image-upload]').forEach(enhanceImageUpload);
    return row;
}

const isNonImage = (f) => f.type !== '' && !f.type.startsWith('image/');

async function uploadFiles(dropzone, endpoint, fileList, ordered, onUploaded) {
    const allFiles = Array.from(fileList || []);
    const files = allFiles.filter((f) => !isNonImage(f));
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
    return {total: allFiles.length, urls, failed};
}

function showSummary(errorEl, result) {
    if (!result.failed.length) return;
    errorEl.textContent = `Nahráno ${result.urls.length}/${result.total}. Nepodařilo se: ${result.failed.join(' · ')}`;
    errorEl.hidden = false;
}

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
        const row = e.target;
        if (!(row instanceof Element) || !row.matches('[data-gallery-item]') || row.parentElement !== container) return;
        if (!row.draggable) {
            e.preventDefault();
            return;
        }
        dragged = row;
        row.classList.add('masonry-item-dragging');
        e.dataTransfer.effectAllowed = 'move';
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

function initTile(tile) {
    const container = tile.querySelector('[data-gallery-items]');
    const template = tile.querySelector('[data-gallery-template]');
    if (!container || !template) return;

    const addRow = () => cloneRow(container, template);
    tile.querySelector('[data-gallery-add]')?.addEventListener('click', addRow);
    wireBulkDropzone(tile.querySelector('[data-gallery-bulk]'), (url) => applyUploadedUrl(addRow(), url));
    wireSorting(container);
}

function initBulkCreate(bulkEl) {
    wireBulkDropzone(bulkEl, null, (result) => {
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
        result.failed.forEach((message) => add('failed[]', message));
        document.body.appendChild(form);
        form.submit();
    });
}

export function initMasonry(scope = document) {
    scope.querySelectorAll('[data-masonry-item]').forEach(initTile);
    scope.querySelectorAll('[data-masonry-bulk-create]').forEach(initBulkCreate);

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-gallery-item-remove]')) {
            e.target.closest('[data-gallery-item]')?.remove();
        }
    });
}
