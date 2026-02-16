/**
 * Sidebar Navigation Module
 * Handles dropdown toggles and search functionality
 */

(function () {
    'use strict';

    // ── Search Modal ─────────────────────────────────────────
    var overlay = document.getElementById('searchOverlay');
    var modalInput = document.getElementById('searchModalInput');
    var resultsContainer = document.getElementById('searchModalResults');
    var headerSearchInput = document.querySelector('header input[type="text"], .bg-white input[type="text"]');
    var selectedIndex = -1;

    /**
     * Build a flat list of all searchable sidebar items with breadcrumb paths.
     * Each entry: { text, breadcrumb[], href, icon }
     */
    function buildSearchIndex() {
        var items = [];
        var nav = document.querySelector('.sidebar-scroll nav');
        if (!nav) return items;

        var sections = nav.querySelectorAll('.nav-section');
        sections.forEach(function (section) {
            var sectionLabel = section.querySelector('.section-label');
            var sectionName = sectionLabel ? sectionLabel.textContent.trim() : '';

            // Find all links and buttons in this section
            var links = section.querySelectorAll('a[href]');
            var buttons = section.querySelectorAll('button[class*="-toggle"]');

            // Process links
            links.forEach(function (link) {
                var span = link.querySelector('span');
                var text = span ? span.textContent.trim() : link.textContent.trim();
                var icon = link.querySelector('i');
                var href = link.getAttribute('href');

                var breadcrumb = [];
                if (sectionName) breadcrumb.push(sectionName);
                breadcrumb.push(text);

                items.push({
                    text: text,
                    breadcrumb: breadcrumb,
                    href: href,
                    icon: icon ? icon.className : 'fas fa-file'
                });
            });

            // Process buttons (collapsible headers like "Trend Analytics")
            buttons.forEach(function (btn) {
                var btnSpan = btn.querySelector('span > span');
                var btnText = btnSpan ? btnSpan.textContent.trim() : '';
                if (!btnText) return;

                var breadcrumb = [];
                if (sectionName) breadcrumb.push(sectionName);
                breadcrumb.push(btnText);

                var icon = btn.querySelector('i');
                items.push({
                    text: btnText,
                    breadcrumb: breadcrumb,
                    href: '',
                    icon: icon ? icon.className : 'fas fa-file'
                });

                // Get nested items under this button
                var content = btn.parentElement.querySelector('.dropdown-menu');
                if (content) {
                    var nestedItems = content.querySelectorAll('a[href], button');
                    nestedItems.forEach(function (nested) {
                        var nestedSpan = nested.querySelector('span');
                        var nestedText = nestedSpan ? nestedSpan.textContent.trim() : nested.textContent.trim();
                        var nestedIcon = nested.querySelector('i');
                        var nestedHref = nested.tagName === 'A' ? nested.getAttribute('href') : '';

                        var nestedBreadcrumb = [];
                        if (sectionName) nestedBreadcrumb.push(sectionName);
                        nestedBreadcrumb.push(btnText);
                        nestedBreadcrumb.push(nestedText);

                        items.push({
                            text: nestedText,
                            breadcrumb: nestedBreadcrumb,
                            href: nestedHref,
                            icon: nestedIcon ? nestedIcon.className : 'fas fa-file'
                        });
                    });
                }
            });
        });

        return items;
    }

    var searchIndex = [];
    // Build index once DOM is ready
    function initSearchIndex() {
        searchIndex = buildSearchIndex();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchIndex);
    } else {
        initSearchIndex();
    }

    function highlightMatch(text, query) {
        if (!query) return escapeHtml(text);
        var lowerText = text.toLowerCase();
        var lowerQuery = query.toLowerCase();
        var idx = lowerText.indexOf(lowerQuery);
        if (idx === -1) return escapeHtml(text);
        var before = text.substring(0, idx);
        var match = text.substring(idx, idx + query.length);
        var after = text.substring(idx + query.length);
        return escapeHtml(before) + '<span class="search-highlight">' + escapeHtml(match) + '</span>' + escapeHtml(after);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function renderResults(query) {
        selectedIndex = -1;
        if (!query || !query.trim()) {
            resultsContainer.innerHTML = '<div class="search-no-results">Type to search sidebar items...</div>';
            return;
        }

        var q = query.toLowerCase().trim();
        var matches = searchIndex.filter(function (item) {
            // Match against the item text or any breadcrumb segment
            return item.breadcrumb.some(function (seg) {
                return seg.toLowerCase().indexOf(q) !== -1;
            });
        });

        if (matches.length === 0) {
            resultsContainer.innerHTML = '<div class="search-no-results">No results found for "' + escapeHtml(query) + '"</div>';
            return;
        }

        var html = '<div class="search-modal-label">Go to</div>';
        matches.forEach(function (item, idx) {
            var breadcrumbHtml = item.breadcrumb.map(function (seg) {
                return highlightMatch(seg, query);
            }).join('<span class="breadcrumb-sep">&rsaquo;</span>');

            var tag = item.href ? 'a' : 'div';
            var hrefAttr = item.href ? ' href="' + escapeHtml(item.href) + '"' : '';

            html += '<' + tag + ' class="search-result-item" data-index="' + idx + '"' + hrefAttr + '>';
            html += '<div class="result-icon"><i class="' + escapeHtml(item.icon) + '"></i></div>';
            html += '<div class="result-text"><div class="result-breadcrumb">' + breadcrumbHtml + '</div></div>';
            if (item.href) {
                html += '<i class="fas fa-arrow-right result-arrow"></i>';
            }
            html += '</' + tag + '>';
        });

        resultsContainer.innerHTML = html;
    }

    function openSearchModal() {
        if (searchIndex.length === 0) initSearchIndex();
        overlay.classList.add('is-active');
        modalInput.value = '';
        renderResults('');
        setTimeout(function () { modalInput.focus(); }, 50);
    }

    function closeSearchModal() {
        overlay.classList.remove('is-active');
        modalInput.value = '';
        // Return focus to sidebar input
        if (headerSearchInput) headerSearchInput.value = '';
    }

    function navigateResults(direction) {
        var items = resultsContainer.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        // Remove current selection
        if (selectedIndex >= 0 && selectedIndex < items.length) {
            items[selectedIndex].classList.remove('is-selected');
        }

        selectedIndex += direction;
        if (selectedIndex < 0) selectedIndex = items.length - 1;
        if (selectedIndex >= items.length) selectedIndex = 0;

        items[selectedIndex].classList.add('is-selected');
        items[selectedIndex].scrollIntoView({ block: 'nearest' });
    }

    function selectResult() {
        var items = resultsContainer.querySelectorAll('.search-result-item');
        if (selectedIndex >= 0 && selectedIndex < items.length) {
            var item = items[selectedIndex];
            if (item.tagName === 'A' && item.href) {
                window.location.href = item.href;
            }
            closeSearchModal();
        }
    }

    // Event: sidebar input opens modal
    if (headerSearchInput) {
        headerSearchInput.addEventListener('focus', function () {
            openSearchModal();
            this.blur();
        });
    }

    // Event: Ctrl+K opens modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'k' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            openSearchModal();
        }
    });

    // Event: Escape closes modal
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeSearchModal();
        });
    }
    if (document.getElementById('searchModalEsc')) {
        document.getElementById('searchModalEsc').addEventListener('click', closeSearchModal);
    }

    // Event: typing in modal input
    if (modalInput) {
        modalInput.addEventListener('input', function () {
            renderResults(this.value);
        });

        modalInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeSearchModal();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateResults(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateResults(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectResult();
            }
        });
    }

    // ── Dropdown Toggle Setup ────────────────────────────────

    /**
     * Generic dropdown toggle
     * Toggles .is-open on content and .is-open-trigger on button
     */
    function setupToggle(buttonSelector, contentSelector) {
        document.querySelectorAll(buttonSelector).forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                const content = this.parentElement.querySelector(contentSelector);
                if (!content) return;

                content.classList.toggle('is-open');
                button.classList.toggle('is-open-trigger');
            });
        });
    }

    // Setup only the toggles that exist in the current sidebar
    setupToggle('.crime-trend-toggle', '.crime-trend-content');
    setupToggle('.crime-predictive-toggle', '.crime-predictive-content');
    setupToggle('.crime-reports-toggle', '.crime-reports-content');
    setupToggle('.crime-alerts-toggle', '.crime-alerts-content');

})();
