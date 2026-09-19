import Modal from 'bootstrap/js/dist/modal';

/**
 * Masonry "book" view: prev/next arrows (and ← / → keys) inside a tile's modal
 * close it and open the sibling tile's modal (data-masonry-nav="#modal-id");
 * clicking a gallery photo shows it in place of the main one.
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

	// mini gallery: a clicked gallery photo swaps places with the main one (nothing gets lost,
	// the previous main photo becomes a thumbnail); each photo remembers its original src/alt
	const replay = (img) => {
		img.classList.remove('book-swap');
		void img.offsetWidth; // restart the fade-in animation
		img.classList.add('book-swap');
	};

	document.addEventListener('click', (e) => {
		const thumb = e.target.closest('.book-thumb');
		if (!thumb) return;
		const book = thumb.closest('.masonry-book-modal');
		const main = book.querySelector('.book-main img');
		const img = thumb.querySelector('img');
		// keep the main block as wide as it was with the original photo; a narrower one leaves a gap
		const box = main.parentElement;
		if (!box.style.getPropertyValue('--book-main-w')) {
			box.style.setProperty('--book-main-w', `${box.getBoundingClientRect().width}px`);
		}
		for (const el of [main, img]) {
			el.dataset.origSrc ??= el.getAttribute('src');
			el.dataset.origAlt ??= el.getAttribute('alt') ?? '';
		}
		const [src, alt] = [main.getAttribute('src'), main.getAttribute('alt')];
		main.setAttribute('src', img.getAttribute('src'));
		main.setAttribute('alt', img.getAttribute('alt'));
		img.setAttribute('src', src);
		img.setAttribute('alt', alt);
		replay(main);
		replay(img);
		// on phones the main photo sits above the gallery, possibly scrolled away — scroll only
		// the modal itself (scrollIntoView would also scroll the page underneath)
		const top = main.getBoundingClientRect().top;
		if (top < 0) book.scrollBy({ top: top - 16, behavior: 'smooth' });
	});

	// a book always reopens in its original order (closing it, or paging away with the arrows)
	document.addEventListener('hidden.bs.modal', (e) => {
		if (!e.target.classList.contains('masonry-book-modal')) return;
		e.target.querySelector('.book-main')?.style.removeProperty('--book-main-w');
		e.target.querySelectorAll('img[data-orig-src]').forEach((img) => {
			img.setAttribute('src', img.dataset.origSrc);
			img.setAttribute('alt', img.dataset.origAlt);
			delete img.dataset.origSrc;
			delete img.dataset.origAlt;
			img.classList.remove('book-swap');
		});
	});
}
