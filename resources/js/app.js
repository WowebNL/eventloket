import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('[data-accordion="faq"]').forEach((accordion) => {
		const items = accordion.querySelectorAll('[data-accordion-item]');

		const closeItem = (item) => {
			const trigger = item.querySelector('[data-accordion-trigger]');
			const panel = item.querySelector('[data-accordion-panel]');
			const plusIcon = item.querySelector('[data-accordion-icon="plus"]');
			const minusIcon = item.querySelector('[data-accordion-icon="minus"]');

			if (panel) panel.classList.add('hidden');
			if (trigger) trigger.setAttribute('aria-expanded', 'false');
			if (plusIcon) plusIcon.classList.remove('hidden');
			if (minusIcon) minusIcon.classList.add('hidden');
		};

		const openItem = (item) => {
			const trigger = item.querySelector('[data-accordion-trigger]');
			const panel = item.querySelector('[data-accordion-panel]');
			const plusIcon = item.querySelector('[data-accordion-icon="plus"]');
			const minusIcon = item.querySelector('[data-accordion-icon="minus"]');

			if (panel) panel.classList.remove('hidden');
			if (trigger) trigger.setAttribute('aria-expanded', 'true');
			if (plusIcon) plusIcon.classList.add('hidden');
			if (minusIcon) minusIcon.classList.remove('hidden');
		};

		items.forEach((item) => {
			const trigger = item.querySelector('[data-accordion-trigger]');
			if (!trigger) return;

			trigger.addEventListener('click', () => {
				const panel = item.querySelector('[data-accordion-panel]');
				const isOpen = panel && !panel.classList.contains('hidden');

				items.forEach(closeItem);

				if (!isOpen) {
					openItem(item);
				}
			});
		});
	});
});
