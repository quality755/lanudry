/* Spin City Laundry — front-end behaviour. No frameworks, no nonsense. */
(function () {
    'use strict';

    var money = function (value) {
        return Number(value || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    var toNumber = function (value) {
        var n = parseFloat(String(value).replace(/[^0-9.\-]/g, ''));
        return isNaN(n) ? 0 : n;
    };

    /* ---------------- Sidebar ---------------- */

    function initMenu() {
        var sidebar = document.getElementById('sidebar');
        var scrim = document.querySelector('.scrim');
        if (!sidebar) return;

        var toggles = document.querySelectorAll('[data-menu-toggle]');

        var close = function () {
            sidebar.classList.remove('is-open');
            if (scrim) scrim.hidden = true;
        };

        Array.prototype.forEach.call(toggles, function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                var open = sidebar.classList.toggle('is-open');
                if (scrim) scrim.hidden = !open;
            });
        });

        if (scrim) scrim.addEventListener('click', close);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') close();
        });
    }

    /* ---------------- Flashes ---------------- */

    function initFlashes() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-dismiss]'), function (btn) {
            btn.addEventListener('click', function () {
                var flash = btn.closest('[data-flash]');
                if (flash) flash.remove();
            });
        });
    }

    /* ---------------- Delete confirmations ---------------- */

    function initConfirms() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
    }

    /* ---------------- Print ---------------- */

    function initPrint() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-print]'), function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                window.print();
            });
        });
    }

    /* ---------------- Order line editor ---------------- */

    function initOrderLines() {
        var list = document.getElementById('line-rows');
        var template = document.getElementById('line-template');
        var addBtn = document.getElementById('add-line');
        if (!list || !template) return;

        var discount = document.getElementById('discount');
        var delivery = document.getElementById('delivery_fee');
        var subtotalOut = document.querySelectorAll('[data-subtotal]');
        var totalOut = document.querySelectorAll('[data-total]');

        var recalc = function () {
            var subtotal = 0;
            Array.prototype.forEach.call(list.querySelectorAll('.line-row'), function (row) {
                var qty = toNumber(row.querySelector('[name="item_qty[]"]').value);
                var price = toNumber(row.querySelector('[name="item_price[]"]').value);
                var line = Math.round(qty * price * 100) / 100;
                var out = row.querySelector('[data-line-total]');
                if (out) out.textContent = money(line);
                subtotal += line;
            });

            var disc = discount ? toNumber(discount.value) : 0;
            var fee = delivery ? toNumber(delivery.value) : 0;
            var total = Math.max(0, Math.round((subtotal - disc + fee) * 100) / 100);

            Array.prototype.forEach.call(subtotalOut, function (el) {
                el.textContent = money(subtotal);
            });
            Array.prototype.forEach.call(totalOut, function (el) {
                el.textContent = money(total);
            });
        };

        var renumber = function () {
            Array.prototype.forEach.call(list.querySelectorAll('.line-row'), function (row, index) {
                var label = row.querySelector('[data-line-index]');
                if (label) label.textContent = String(index + 1);
            });
        };

        var bindRow = function (row) {
            Array.prototype.forEach.call(row.querySelectorAll('input, select'), function (field) {
                field.addEventListener('input', recalc);
                field.addEventListener('change', recalc);
            });

            var service = row.querySelector('[name="item_service[]"]');
            if (service) {
                service.addEventListener('change', function () {
                    var option = service.options[service.selectedIndex];
                    var price = row.querySelector('[name="item_price[]"]');
                    var unitOut = row.querySelector('[data-unit]');
                    if (price && option && option.getAttribute('data-price')) {
                        price.value = option.getAttribute('data-price');
                    }
                    if (unitOut && option) {
                        unitOut.textContent = option.getAttribute('data-unit') || '';
                    }
                    recalc();
                });
            }

            var remove = row.querySelector('[data-remove-line]');
            if (remove) {
                remove.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (list.querySelectorAll('.line-row').length <= 1) {
                        row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                        var select = row.querySelector('select');
                        if (select) select.selectedIndex = 0;
                    } else {
                        row.remove();
                    }
                    renumber();
                    recalc();
                });
            }
        };

        if (addBtn) {
            addBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var fragment = template.content.cloneNode(true);
                var row = fragment.querySelector('.line-row');
                list.appendChild(fragment);
                bindRow(row);
                renumber();
                recalc();
                var first = row.querySelector('select');
                if (first) first.focus();
            });
        }

        Array.prototype.forEach.call(list.querySelectorAll('.line-row'), bindRow);
        if (discount) discount.addEventListener('input', recalc);
        if (delivery) delivery.addEventListener('input', recalc);
        recalc();
    }

    /* ---------------- Payment quick-fill ---------------- */

    function initPaymentFill() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-pay-full]'), function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                var target = document.querySelector(btn.getAttribute('data-pay-full'));
                if (!target) return;
                target.value = btn.getAttribute('data-amount') || '';
                target.focus();
                target.select();
            });
        });
    }

    /* ---------------- Auto-submit filters ---------------- */

    function initFilters() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-autosubmit]'), function (field) {
            field.addEventListener('change', function () {
                var form = field.closest('form');
                if (form) form.submit();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initMenu();
        initFlashes();
        initConfirms();
        initPrint();
        initOrderLines();
        initPaymentFill();
        initFilters();
    });
})();
