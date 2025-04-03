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
    if (subscriber?.object?.options?.length === 1) {
        value = subscriber.object.dataset?.initialCountryStateId ?? value;
    }

    return new Promise(function (resolve, reject) {
        const key = window.EnderecoIntegrator.subdivisionMappingReverse?.[value];
        if (key !== undefined) {
            resolve(key);
        } else {
            resolve('');
        }
    });
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

const editAddressHandler = async (EAO) => {
    const form = EAO.forms[0];
    if (!form) {
        return Promise.resolve();
    }

    const targetFormLinkSelector = form.getAttribute('data-end-target-link-selector');
    if (!targetFormLinkSelector) {
        return Promise.resolve();
    }

    const targetLink = document.querySelector(targetFormLinkSelector);
    if (!targetLink) {
        return Promise.resolve();
    }

    targetLink.click();

    const addressEditorPlugin = window.PluginManager.getPluginInstanceFromElement(targetLink, 'AddressEditor');
    if (!addressEditorPlugin) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const timeoutId = setTimeout(() => {
            console.warn('Timed out waiting for modal to open');
            resolve();
        }, 10000);

        addressEditorPlugin.$emitter.subscribe('onOpen', ({detail: {pseudoModal}}) => {
            const modalElement = pseudoModal._modal;

            let addressEditButton;
            if (modalElement.querySelector('[data-bs-target="#billing-address-create-edit"]')) {
                addressEditButton = modalElement.querySelector('[data-bs-target="#billing-address-create-edit"]');
            } else if (modalElement.querySelector('[data-bs-target="#shipping-address-create-edit"]')) {
                addressEditButton = modalElement.querySelector('[data-bs-target="#shipping-address-create-edit"]');
            } else {
                addressEditButton = modalElement.querySelector('[data-target="#address-create-edit"]');
            }

            if (!addressEditButton) {
                console.warn("Modal opened but edit button not found");
                clearTimeout(timeoutId);
                return resolve();
            }

            // Add a small timeout to ensure the button is clickable
            setTimeout(() => {
                try {
                    addressEditButton.click();
                    clearTimeout(timeoutId);
                    return resolve();
                } catch (err) {
                    // Handle potential navigation or other errors
                    console.warn("Error clicking edit button:", {
                        error: err
                    });
                    clearTimeout(timeoutId);
                    return resolve();
                }
            }, 50);
        });
    });
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
            console.log("Response", window.EnderecoIntegrator.processQueue.size)
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
    const shopwareModal = document.querySelector('.address-editor-modal');
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

if (window.EnderecoIntegrator) {
    window.EnderecoIntegrator = merge(window.EnderecoIntegrator, EnderecoIntegrator);
} else {
    window.EnderecoIntegrator = EnderecoIntegrator;
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
