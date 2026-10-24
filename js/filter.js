/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Module: filter.js (Procurement Tenders Search & Multi-Criteria Filtering Engine)
 *
 * Implements real-time client-side search indexing, category filtering,
 * sort re-ordering, and responsive DOM card visibility updates.
 */

class ContractFilter {
    constructor() {
        this.searchInput = document.getElementById('contract-search');
        this.categorySelect = document.getElementById('contract-category');
        this.sortSelect = document.getElementById('contract-sort');
        this.cardsContainer = document.getElementById('contracts-grid');
        this.countBadge = document.getElementById('contract-count-badge');
        this.noResultsEl = document.getElementById('contracts-empty-state');

        if (this.cardsContainer) {
            this.cards = Array.from(this.cardsContainer.querySelectorAll('.contract-card'));
            this.init();
        }
    }

    init() {
        // Event Listeners for Real-Time Querying
        if (this.searchInput) {
            this.searchInput.addEventListener('input', () => this.applyFilters());
        }
        if (this.categorySelect) {
            this.categorySelect.addEventListener('change', () => this.applyFilters());
        }
        if (this.sortSelect) {
            this.sortSelect.addEventListener('change', () => this.applyFilters());
        }

        // Reset Filter Button if available
        const resetBtn = document.getElementById('filter-reset-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.searchInput) this.searchInput.value = '';
                if (this.categorySelect) this.categorySelect.value = 'all';
                if (this.sortSelect) this.sortSelect.value = 'default';
                this.applyFilters();
            });
        }

        this.applyFilters();
    }

    applyFilters() {
        const query = this.searchInput ? this.searchInput.value.toLowerCase().trim() : '';
        const selectedCategory = this.categorySelect ? this.categorySelect.value.toLowerCase() : 'all';
        const sortBy = this.sortSelect ? this.sortSelect.value : 'default';

        let visibleCount = 0;
        const matchedCards = [];

        this.cards.forEach(card => {
            const cropName = (card.dataset.crop || card.querySelector('.crop-title')?.textContent || '').toLowerCase();
            const location = (card.dataset.location || card.querySelector('.meta-location')?.textContent || '').toLowerCase();
            const buyer = (card.dataset.buyer || card.querySelector('.meta-buyer')?.textContent || '').toLowerCase();
            const category = (card.dataset.category || '').toLowerCase();

            // Text search match across crop, variety, location, and buyer company
            const matchesQuery = query === '' ||
                cropName.includes(query) ||
                location.includes(query) ||
                buyer.includes(query);

            // Category dropdown match
            const matchesCategory = selectedCategory === 'all' || category === selectedCategory || cropName.includes(selectedCategory);

            if (matchesQuery && matchesCategory) {
                card.style.display = 'flex';
                matchedCards.push(card);
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Apply Sorting
        if (sortBy !== 'default' && matchedCards.length > 1) {
            matchedCards.sort((a, b) => {
                const priceA = parseFloat(a.dataset.price || 0);
                const priceB = parseFloat(b.dataset.price || 0);
                const qtyA = parseInt(a.dataset.quantity || 0, 10);
                const qtyB = parseInt(b.dataset.quantity || 0, 10);

                switch (sortBy) {
                    case 'price-desc':
                        return priceB - priceA;
                    case 'price-asc':
                        return priceA - priceB;
                    case 'qty-desc':
                        return qtyB - qtyA;
                    case 'qty-asc':
                        return qtyA - qtyB;
                    default:
                        return 0;
                }
            });

            // Re-append sorted elements in DOM order
            matchedCards.forEach(card => this.cardsContainer.appendChild(card));
        }

        // Update Counter Display
        if (this.countBadge) {
            this.countBadge.textContent = `Showing ${visibleCount} of ${this.cards.length} Tenders`;
        }

        // Toggle Empty State Message
        if (this.noResultsEl) {
            this.noResultsEl.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }
}

// Auto-initialize when DOM finishes loading
document.addEventListener('DOMContentLoaded', () => {
    new ContractFilter();
});
