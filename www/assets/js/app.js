(function () {
    'use strict';

    var content = document.querySelector('.page-content');
    var sideMenu = document.querySelector('.side-menu');

    if (!content || !sideMenu) {
        return;
    }

    function setActiveLink(url) {
        var path = new URL(url, window.location.href).pathname;

        sideMenu.querySelectorAll('a.active').forEach(function (el) {
            el.classList.remove('active');
        });

        var link = sideMenu.querySelector('a[href="' + path + '"]');
        if (link) {
            link.classList.add('active');
        }
    }

    function loadContent(url, pushState) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed: ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                content.innerHTML = html;
                setActiveLink(url);

                if (pushState) {
                    history.pushState({ url: url }, '', url);
                }

                content.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    function filterBooks(query) {
        var normalized = query.trim().toLowerCase();
        var cards = content.querySelectorAll('.book-card');
        var visible = 0;

        cards.forEach(function (card) {
            var title = card.querySelector('.book-card__title').textContent.toLowerCase();
            var author = card.querySelector('.book-card__author').textContent.toLowerCase();
            var matches = title.includes(normalized) || author.includes(normalized);

            card.hidden = !matches;
            if (matches) {
                visible++;
            }
        });

        var counter = content.querySelector('#bookCount');
        if (counter) {
            counter.textContent = visible;
        }

        var emptyState = content.querySelector('#bookEmptyState');
        if (emptyState) {
            emptyState.hidden = visible !== 0;
        }
    }

    content.addEventListener('input', function (event) {
        if (event.target.id === 'bookSearch') {
            filterBooks(event.target.value);
        }
    });

    sideMenu.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');

        if (!link || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        loadContent(link.getAttribute('href'), true);
    });

    window.addEventListener('popstate', function () {
        loadContent(window.location.href, false);
    });
})();
