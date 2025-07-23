import Promise from 'promise-polyfill';
import merge from 'lodash.merge';
import EnderecoIntegrator from './node_modules/@endereco/js-sdk/modules/integrator';
import css from  './endereco.scss'

import 'polyfill-array-includes';

if ('NodeList' in window && !NodeList.prototype.forEach) {
    NodeList.prototype.forEach = function (callback, thisArg) {
        thisArg = thisArg || window;
        for (var i = 0; i < this.length; i++) {
            callback.call(thisArg, this[i], i, this);
        }
    };
}

if (!window.Promise) {
    window.Promise = Promise;
}

if (css) {
    EnderecoIntegrator.css = css[0][1];
}

EnderecoIntegrator.postfix = {
    personServices: {
        salutation: '[salutation]',
        firstName: '[firstname]'
    },
    emailServices: {
        email: '[email]'
    }
};

EnderecoIntegrator.resolvers.countryCodeSetValue = (subscriber, value) => {
    const functionsExist = (typeof jQuery !== 'undefined') && jQuery.fn.val && jQuery.fn.trigger;
    if (subscriber.dispatchEvent('endereco-change')) {
        subscriber._allowFieldInspection = false;
        if (functionsExist) {
            jQuery(subscriber.object).val(value).trigger('change');
        } else {
            subscriber.object.value = value;
        }
        subscriber.lastValue = value
        subscriber._allowFieldInspection = true;
        subscriber.dispatchEvent('endereco-blur');
    }
}

EnderecoIntegrator.resolvers.subdivisionCodeSetValue = (subscriber, value) => {
    const functionsExist = (typeof jQuery !== 'undefined') && jQuery.fn.val && jQuery.fn.trigger;
    if (subscriber.dispatchEvent('endereco-change')) {
        subscriber._allowFieldInspection = false;
        if (functionsExist) {
            jQuery(subscriber.object).val(value).trigger('change');
        } else {
            subscriber.object.value = value;
        }
        subscriber.lastValue = value
        subscriber._allowFieldInspection = true;
        subscriber.dispatchEvent('endereco-blur');
    }
}

EnderecoIntegrator.resolvers.countryCodeWrite = function (value, subscriber) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.countryMapping[value.toUpperCase()];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.countryMapping[value.toUpperCase()]);
        } else {
            resolve('');
        }
    });
}

EnderecoIntegrator.resolvers.countryCodeRead = function (value, subscriber) {
    if (subscriber?.object?.options?.length === 1) {
        value = subscriber.object.dataset?.initialCountryId ?? value;
    }
    return new Promise(function (resolve, reject) {
        const key = window.EnderecoIntegrator.countryMappingReverse?.[value];
        if (key !== undefined) {
            resolve(key);
        } else {
            resolve('');
        }
    });
}

EnderecoIntegrator.resolvers.subdivisionCodeWrite = function (value, subscriber) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.subdivisionMapping[value.toUpperCase()];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.subdivisionMapping[value.toUpperCase()]);
        } else {
            resolve('');
        }
    });
}

EnderecoIntegrator.resolvers.subdivisionCodeRead = function (value, subscriber) {
    return new Promise(function (resolve, reject) {
        const key = window.EnderecoIntegrator.subdivisionMappingReverse?.[value];
        if (key !== undefined) {
            resolve(key);
        } else {
            resolve('');
        }
    });
}

EnderecoIntegrator.resolvers.subdivisionCodeGetValue = function(subscriber) {
    let value;
    if (subscriber?.object?.type === 'select-one' && subscriber?.object?.options?.length === 1) {
        value = subscriber.object.dataset?.initialCountryStateId ?? '';
        return value
    }
    value = subscriber.getValue();
    return value
}

EnderecoIntegrator.resolvers.salutationWrite = function (value) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.salutationMapping[value];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.salutationMapping[value]);
        } else {
            resolve('');
        }
    });
}

EnderecoIntegrator.resolvers.salutationRead = function (value) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.salutationMappingReverse[value];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.salutationMappingReverse[value]);
        } else {
            resolve('x');
        }
    });
}

const originalHasActiveSubscriber = EnderecoIntegrator.hasActiveSubscriber;
EnderecoIntegrator.hasActiveSubscriber = (fieldName, domElement, dataObject) => {
    if (fieldName === 'subdivisionCode' &&
        domElement &&
        domElement.tagName === 'SELECT' &&
        domElement.hasAttribute('data-initial-country-state-id') &&
        domElement.getAttribute('data-initial-country-state-id').trim() !== ''
    ) {
        // If 'data-initial-country-state-id' is set, then MOST LIKELY the element will have proper options (later)
        return true;
    } else {
        return originalHasActiveSubscriber(fieldName, domElement, dataObject);
    }
};

/**
 * Wait until an element matching `selector` appears inside `root`
 * (defaults to document) or until `timeout` ms elapse.
 * Resolves with the element or rejects on timeout.
 */
function waitForElement(
    selector,
    { root = document, timeout = 5000 } = {},
) {
    return new Promise((resolve, reject) => {
        const existing = root.querySelector(selector);
        if (existing) return resolve(existing);

        const observer = new MutationObserver(() => {
            const found = root.querySelector(selector);
            if (found) {
                observer.disconnect();
                clearTimeout(timer);
                resolve(found);
            }
        });

        observer.observe(root, { childList: true, subtree: true });

        const timer = setTimeout(() => {
            observer.disconnect();
            reject(new Error(`Timed out waiting for: ${selector}`));
        }, timeout);
    });
}

/**
 * Opens the address-manager modal and switches it into
 * "edit address" mode. Silent no-ops when something is missing.
 */
const editAddressHandler = async (EAO) => {
    const form = EAO?.forms?.[0];
    if (!form) return;

    const linkSelector = form.getAttribute('data-end-target-link-selector');
    if (!linkSelector) return;

    const link = document.querySelector(linkSelector);
    if (!link) return;

    link.click();

    try {
        const modal = await waitForElement('.address-manager-modal', {
            timeout: 10_000,
        });

        // Give the plugin a couple of ticks to render.
        await new Promise(r => setTimeout(r, 200));

        // Determine address type from the link selector to target the correct edit button
        const isShippingAddress = linkSelector.includes('confirm-shipping-address');
        const isBillingAddress = linkSelector.includes('confirm-billing-address');
        
        let editBtn;
        if (isShippingAddress) {
            editBtn = modal.querySelector('.address-manager-modal-address-form[data-address-type="shipping"]');
        } else if (isBillingAddress) {
            editBtn = modal.querySelector('.address-manager-modal-address-form[data-address-type="billing"]');
        }
        
        if (!editBtn) {
            console.warn('Edit-address button not found inside modal');
            return;
        }
        editBtn.click();

        await waitForElement(
            '.address-manager-modal form, .address-manager-modal .address-form',
            { root: modal, timeout: 5_000 },
        );
    } catch (err) {
        console.warn(err.message);
    }
};

const addressSelectedOrConfirmHandler = async (EAO) => {
    const form = EAO.forms[0];
    if (!form) {
        return Promise.resolve();
    }

    const targetForm = form.getAttribute('data-end-target-link-selector');
    const ajaxPlugin = window.PluginManager.getPluginInstanceFromElement(form, 'FormAjaxSubmit');

    if (!targetForm || !ajaxPlugin) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        // Set a timeout to prevent hanging indefinitely
        const timeoutId = setTimeout(() => {
            console.warn('[ENDERECO] Form submission timed out');
            resolve();
        }, 15000); // 15 second timeout

        ajaxPlugin.$emitter.subscribe('onAfterAjaxSubmit', () => {
            clearTimeout(timeoutId);
            resolve();

            // If there is only one process, it means it's the last one we are in, so reload is ok.
            if (window.EnderecoIntegrator.processQueue.size === 1) {
                window.location.reload();
            }
        });

        // Also handle potential errors
        ajaxPlugin.$emitter.subscribe('onError', () => {
            console.warn('[ENDERECO] Error during form submission');
            clearTimeout(timeoutId);
            resolve();
        });

        ajaxPlugin._fireRequest();
    });
};

EnderecoIntegrator.afterAMSActivation.push((EAO) => {
    EAO.onEditAddress.push((e) => {
        return editAddressHandler(e);
    })

    EAO.onAfterAddressPersisted.push((e, result) => {
        if (result.processStatus === 'finished') {
            return addressSelectedOrConfirmHandler(e)
        }

        return Promise.resolve();
    })
});

/**
 * Determines if the popup area is free for rendering Endereco modals
 *
 * This function extends the logic of modal rendering in JS_SDK providing additional context
 * about shopware address book modals. For address forms outside of the address book modal,
 * the modal area is not free (therefore they have to wait for it to disappear). But for forms
 * inside the address book modal, the area is free - they can render the endereco modal on top of it.
 *
 * @param {Object} EAO - Endereco Address Object containing form references
 * @returns {boolean} - Returns true if popup area is free, false otherwise
 */
EnderecoIntegrator.isPopupAreaFree = (EAO) => {
    const shopwareModal = document.querySelector('.address-manager-modal');
    if (!shopwareModal) {
        return true;
    }

    const form = EAO.forms[0];
    if (!form) {
        // TODO: revisit in the future. Currently this case is impossible.
        return false;
    }

    // Check if the form is inside the shopwareModal
    return shopwareModal.contains(form);
}

/**
 * Increases the process level if the shopware address modal is open.
 * @returns {number}
 */
EnderecoIntegrator.getProcessLevel = () => {
    return (document.querySelector('.address-manager-modal')) ? 1 : 0;
}

if (window.EnderecoIntegrator) {
    window.EnderecoIntegrator = merge(window.EnderecoIntegrator, EnderecoIntegrator);
} else {
    window.EnderecoIntegrator = EnderecoIntegrator;
}

window.EnderecoIntegrator.prepareDOMElement = (DOMElement) => {
    // Check if the element has already been prepared
    if (DOMElement._enderecoBlurListenerAttached) {
        return; // Skip if already prepared
    }

    const enderecoBlurListener = (e) => {
        // Dispatch 'focus', 'input', 'change' and 'blur' events on the target element
        // The 'input' event is required for Shopware 6.7 to reset error states
        let prevActiveElement = document.activeElement;
        e.target.dispatchEvent(new CustomEvent('focus', { bubbles: true, cancelable: true }));
        e.target.dispatchEvent(new CustomEvent('input', { bubbles: true, cancelable: true }));
        e.target.dispatchEvent(new CustomEvent('change', { bubbles: true, cancelable: true }));
        e.target.dispatchEvent(new CustomEvent('blur', { bubbles: true, cancelable: true }));
        prevActiveElement.dispatchEvent(new CustomEvent('focus', { bubbles: true, cancelable: true }));
    }

    DOMElement.addEventListener('endereco-blur', enderecoBlurListener);

    // Mark the element as prepared
    DOMElement._enderecoBlurListenerAttached = true;
}

window.EnderecoIntegrator.asyncCallbacks.forEach(function (cb) {
    cb();
});
window.EnderecoIntegrator.asyncCallbacks = [];

const waitForConfig = setInterval(() => {
    if (typeof enderecoLoadAMSConfig === 'function') {
        enderecoLoadAMSConfig();
        clearInterval(waitForConfig);
    }
}, 10);
