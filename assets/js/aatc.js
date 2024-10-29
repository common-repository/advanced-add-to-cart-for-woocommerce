document.addEventListener("DOMContentLoaded", () => {
    let xmlhttp = [];
    const counterSel = 'custom-counter';
    const counterEls = document.querySelectorAll('.custom-counter');
    [...counterEls].forEach(counterEl => {
        initActions(counterEl);
    })

    jQuery('body').on('added_to_cart', function (e, fragments, cart_hash, $button) {
        const buttonEl = $button.get(0);
        const newEl = createElement(fragments.newdata);
        if (newEl) {
            buttonEl.replaceWith(newEl);
            initActions(newEl);
        }
    });

    jQuery('body').on('wc_fragments_refreshed', function (e, fragments, cart_hash, $button) {
        const counterSel = 'custom-counter';
        const counterEls = document.querySelectorAll('.custom-counter');
        [...counterEls].forEach(counterEl => {
            initActions(counterEl);
        })
    });

    jQuery('body').on('removed_from_cart', function (e, fragments, cart_hash, $button) {
        var key = $button[0].dataset.cart_item_key;
        const inputEl = document.querySelector(`input[data-key="${key}"]`);
        if (inputEl) {
            quantityChange(document.querySelector('input[data-key="' + key + '"'), 0);
        }
    });

    const fireChangeEvent = (element) => {
        const evt = document.createEvent("HTMLEvents");
        evt.initEvent("change", false, true);
        element.dispatchEvent(evt);
    }

    function initActions(counterEl) {
        const minusBtn = counterEl.querySelector('.qty-minus');
        const plusBtn = counterEl.querySelector('.qty-plus');
        const inputEl = counterEl.querySelector('input');
        let oldVal = inputEl.value * 1;

        minusBtn && minusBtn.addEventListener('click', () => {
            oldVal = oldVal - 1;
            inputEl.value = oldVal;
            fireChangeEvent(inputEl);
        })

        plusBtn && plusBtn.addEventListener('click', () => {
            oldVal = oldVal + 1;
            inputEl.value = oldVal;
            fireChangeEvent(inputEl);
        });

        inputEl.addEventListener('change', (e) => {
            quantityChange(inputEl, inputEl.value * 1);
        });
    }

    function validate(inputEl, val) {
        const min = inputEl.min ? inputEl.min : 0;
        const max = inputEl.max ? inputEl.max : 1000;
        if (val < min) {
            inputEl.value = min;
        } else if (val > max) {
            inputEl.value = max;
        } else {
            inputEl.value = val;
        }
    }

    function quantityChange(inputEl, val) {
        validate(inputEl, val);
        const quantity = inputEl.value * 1;
        const counterEl = inputEl.closest('.custom-counter');

        const key = inputEl.dataset.key;
        const id = inputEl.dataset.product_id;

        ajaxCall(counterEl, id, quantity, key);
    }

    function createElement(htmlString) {
        var div = document.createElement('div');
        div.innerHTML = htmlString.trim();
        return div.firstChild;
    }

    function ajaxCall(counterEl, id, quantity, key) {
        if (xmlhttp.length > 0 && xmlhttp[id] && xmlhttp[id]?.readyState != 4) {
            xmlhttp[id].abort();
        }
        xmlhttp[id] = new XMLHttpRequest();
        xmlhttp[id].open("POST", aatc.ajaxUrl);
        xmlhttp[id].setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xmlhttp[id].onreadystatechange = function () {
            if (xmlhttp[id].readyState === 4) {
                if (xmlhttp[id].status === 200) {
                    const response = JSON.parse(xmlhttp[id].responseText);
                    const newEl = createElement(response.fragments.newdata);
                    if (newEl) {
                        counterEl.replaceWith(newEl);
                        newEl.classList.contains('custom-counter') && initActions(newEl);
                    }
                    for (const [key, value] of Object.entries(response.fragments)) {
                        const element = document.querySelector(key);
                        if (element !== null) {
                            document.querySelector(key).replaceWith(createElementFromHTML(value));
                        }
                    }
                } else {
                    // console.log('failed');
                }
                xmlhttp[id] = null;
            }
        }
        xmlhttp[id].send(`action=aatc_add_to_cart_quantity&product_id=${id}&quantity=${quantity}&item_key=${key}`);
    }

    function createElementFromHTML(htmlString) {
        var div = document.createElement('div');
        div.innerHTML = htmlString.trim();
        return div.firstChild;
    }
});