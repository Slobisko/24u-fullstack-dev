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

                var contentType = response.headers.get('Content-Type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    return response.json().then(function (data) {
                        if (data && data.redirect) {
                            return loadContent(data.redirect, pushState);
                        }
                        throw new Error('Unexpected JSON response');
                    });
                }

                return response.text().then(function (html) {
                    content.innerHTML = html;
                    setActiveLink(response.url);

                    if (pushState) {
                        history.pushState({ url: response.url }, '', response.url);
                    }

                    content.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    function filterBooks(query) {
        var normalized = query.trim().toLowerCase();
        var cards = content.querySelectorAll('.book-card');
        var printRows = content.querySelectorAll('.print-table tbody tr');
        var visible = 0;

        cards.forEach(function (card, index) {
            var title = card.querySelector('.book-card__title').textContent.toLowerCase();
            var author = (card.dataset.authors || card.querySelector('.book-card__author').textContent).toLowerCase();
            var matches = title.includes(normalized) || author.includes(normalized);

            card.hidden = !matches;
            if (printRows[index]) {
                printRows[index].hidden = !matches;
            }
            if (matches) {
                visible++;
            }
        });

        var counter = content.querySelector('#bookCount');
        if (counter) {
            counter.textContent = visible;
        }

        var printCount = content.querySelector('#printCount');
        if (printCount) {
            printCount.textContent = visible;
        }

        var printQuery = content.querySelector('#printQuery');
        if (printQuery) {
            printQuery.hidden = normalized === '';
            printQuery.querySelector('span').textContent = query.trim();
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

    content.addEventListener('click', function (event) {
        if (event.target.closest('.js__print')) {
            window.print();
        }
    });

    function handleNavClick(event) {
        var link = event.target.closest('a[href]');

        if (!link || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        loadContent(link.getAttribute('href'), true);
    }

    sideMenu.addEventListener('click', handleNavClick);
    content.addEventListener('click', handleNavClick);

    // On phones the categories live in a drawer opened by a button on the left edge.
    var categoryDrawer = document.querySelector('.js__category-drawer');
    var categoryToggle = document.querySelector('.js__category-toggle');
    var categoryBackdrop = document.querySelector('.category-backdrop');

    function setCategoryDrawer(open) {
        categoryDrawer.classList.toggle('is-open', open);
        categoryBackdrop.hidden = !open;
        categoryToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('is-category-drawer-open', open);
    }

    if (categoryDrawer && categoryToggle && categoryBackdrop) {
        categoryToggle.addEventListener('click', function () {
            setCategoryDrawer(true);
            categoryDrawer.querySelector('.js__category-close').focus();
        });

        document.querySelectorAll('.js__category-close').forEach(function (element) {
            element.addEventListener('click', function () {
                setCategoryDrawer(false);
                categoryToggle.focus();
            });
        });

        sideMenu.addEventListener('click', function (event) {
            if (event.target.closest('a[href]')) {
                setCategoryDrawer(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && categoryDrawer.classList.contains('is-open')) {
                setCategoryDrawer(false);
                categoryToggle.focus();
            }
        });
    }

    window.addEventListener('popstate', function () {
        loadContent(window.location.href, false);
    });

    var flashContainer = document.getElementById('flashMessages');

    function showFlash(type, text) {
        if (!flashContainer) {
            return;
        }

        var flash = document.createElement('div');
        flash.className = 'flash-message flash-message--' + type;
        flash.textContent = text;
        flashContainer.appendChild(flash);

        setTimeout(function () {
            flash.remove();
        }, 5000);
    }

    function openReservationModal(modal) {
        modal.hidden = false;

        var firstField = modal.querySelector('input[name="first_name"]');
        if (firstField) {
            firstField.focus();
        }
    }

    function closeReservationModal(modal) {
        modal.hidden = true;
    }

    content.addEventListener('click', function (event) {
        var openTrigger = event.target.closest('.js__reservation-open');
        if (openTrigger) {
            var modal = content.querySelector('.js__reservation-modal');
            if (modal) {
                openReservationModal(modal);
            }
            return;
        }

        var closeTrigger = event.target.closest(
            '.js__reservation-close, .js__reservation-cancel, .js__reservation-backdrop'
        );
        if (closeTrigger) {
            var modalToClose = closeTrigger.closest('.js__reservation-modal');
            if (modalToClose) {
                closeReservationModal(modalToClose);
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var openModal = content.querySelector('.js__reservation-modal:not([hidden])');
        if (openModal) {
            closeReservationModal(openModal);
        }
    });

    content.addEventListener('submit', function (event) {
        var form = event.target.closest('.js__reservation-form');
        if (!form) {
            return;
        }

        event.preventDefault();

        var modal = form.closest('.js__reservation-modal');
        var submitButton = form.querySelector('.js__reservation-submit');

        submitButton.disabled = true;

        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                submitButton.disabled = false;

                if (result.data && result.data.ok) {
                    form.reset();

                    if (modal) {
                        closeReservationModal(modal);
                    }

                    showFlash('success', 'Rezervace byla úspěšně odeslána.');
                } else {
                    var errors = (result.data && result.data.errors) || {};
                    var text = Object.keys(errors).map(function (key) {
                        return errors[key];
                    }).join(' ');

                    showFlash('error', text || 'Rezervaci se nepodařilo odeslat.');
                }
            })
            .catch(function () {
                submitButton.disabled = false;
                showFlash('error', 'Rezervaci se nepodařilo odeslat. Zkuste to prosím znovu.');
            });
    });
})();
