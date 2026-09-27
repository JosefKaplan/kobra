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

// one editable image list (a masonry tile's gallery, a footer block's carousel) — see the
// imageListEditor define in Admin/Page/_componentEditor.latte
function initImageList(list) {
    const container = list.querySelector('[data-gallery-items]');
    const template = list.querySelector('[data-gallery-template]');
    if (!container || !template) return;

    const addRow = () => cloneRow(container, template);
    list.querySelector('[data-gallery-add]')?.addEventListener('click', addRow);
    wireBulkDropzone(list.querySelector('[data-gallery-bulk]'), (url) => applyUploadedUrl(addRow(), url));
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
    scope.querySelectorAll('[data-image-list]').forEach(initImageList);
    scope.querySelectorAll('[data-masonry-bulk-create]').forEach(initBulkCreate);

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-gallery-item-remove]')) {
            e.target.closest('[data-gallery-item]')?.remove();
            return;
        }
        const move = e.target.closest('[data-gallery-item-move]');
        if (move) {
            // purely client-side: the new DOM order becomes the POST order on save
            const row = move.closest('[data-gallery-item]');
            const sibling = move.dataset.galleryItemMove === 'up' ? row?.previousElementSibling : row?.nextElementSibling;
            if (!row || !sibling) return;
            if (move.dataset.galleryItemMove === 'up') sibling.before(row); else sibling.after(row);
            row.classList.add('masonry-item-moved');
            row.addEventListener('animationend', () => row.classList.remove('masonry-item-moved'), {once: true});
        }
    });
}
