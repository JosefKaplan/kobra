import Modal from 'bootstrap/js/dist/modal';

/**
 * Masonry "book" view: prev/next arrows (and ← / → keys) inside a tile's modal
 * close it and open the sibling tile's modal (data-masonry-nav="#modal-id").
 */
export function initMasonryBooks() {
	// direction ('prev' | 'next') only drives the slide animation in _layout.scss
	const switchTo = (current, selector, direction) => {
		const target = selector && document.querySelector(selector);
		if (!target || target === current) return;
		// the page would flash through between the outgoing book's `hidden` and the
		// incoming one's first frame — keep a page-coloured stage under both meanwhile
		const stage = document.createElement('div');
		stage.className = 'book-stage';
		document.body.append(stage);

		current.dataset.bookLeave = direction;
		target.dataset.bookEnter = direction;
		current.addEventListener('hidden.bs.modal', () => {
			delete current.dataset.bookLeave;
			Modal.getOrCreateInstance(target).show();
		}, { once: true });
		target.addEventListener('shown.bs.modal', () => {
			delete target.dataset.bookEnter;
			stage.remove();
		}, { once: true });
		Modal.getOrCreateInstance(current).hide();
	};

	document.addEventListener('click', (e) => {
		const btn = e.target.closest('[data-masonry-nav]');
		if (!btn) return;
		const direction = btn.classList.contains('book-nav-prev') ? 'prev' : 'next';
		switchTo(btn.closest('.masonry-book-modal'), btn.dataset.masonryNav, direction);
	});

	document.addEventListener('keydown', (e) => {
		if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
		const current = document.querySelector('.masonry-book-modal.show');
		if (!current) return;
		const direction = e.key === 'ArrowLeft' ? 'prev' : 'next';
		const btn = current.querySelector(`.book-nav-${direction}`);
		if (btn) switchTo(current, btn.dataset.masonryNav, direction);
	});
}
