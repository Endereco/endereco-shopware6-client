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
    ams: {
        countryCode: '[country]',
        subdivisionCode: '[countryStateId]',
        postalCode: '[zipcode]',
        locality: '[city]',
        streetFull: '[street]',
        streetName: '',
        buildingNumber: '',
        addressStatus: '',
        addressTimestamp: '',
        addressPredictions: '',
        additionalInfo: '',
    },
    personServices: {
        salutation: '[salutation]',
        firstName: '[firstname]'
    },
    emailServices: {
        email: '[email]'
    }
};


EnderecoIntegrator.resolvers.countryCodeSetValue = function (subscriber, value) {
    var functionsExist = (typeof jQuery !== 'undefined') && jQuery.fn.val && jQuery.fn.trigger;

    if (functionsExist) {
        jQuery(subscriber.object).val(value).trigger('change');
    } else {
        subscriber.object.value = value;
    }
}

EnderecoIntegrator.resolvers.subdivisionCodeSetValue = function (subscriber, value) {
    var functionsExist = (typeof jQuery !== 'undefined') && jQuery.fn.val && jQuery.fn.trigger;

    if (functionsExist) {
        jQuery(subscriber.object).val(value).trigger('change');
    } else {
        subscriber.object.value = value;
    }
}

EnderecoIntegrator.resolvers.countryCodeWrite = function (value) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.countryMapping[value.toUpperCase()];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.countryMapping[value.toUpperCase()]);
        } else {
            resolve('');
        }
    });
}
EnderecoIntegrator.resolvers.countryCodeRead = function (value) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.countryMappingReverse[value];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.countryMappingReverse[value]);
        } else {
            resolve('');
        }
    });
}

EnderecoIntegrator.resolvers.subdivisionCodeWrite = function (value) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.subdivisionMapping[value.toUpperCase()];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.subdivisionMapping[value.toUpperCase()]);
        } else {
            resolve('');
        }
    });
}
EnderecoIntegrator.resolvers.subdivisionCodeRead = function (value) {
    return new Promise(function (resolve, reject) {
        var key = window.EnderecoIntegrator.subdivisionMappingReverse[value];
        if (key !== undefined) {
            resolve(window.EnderecoIntegrator.subdivisionMappingReverse[value]);
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

// Main function attached to EnderecoIntegrator for handling Ajax form events
EnderecoIntegrator.onAjaxFormHandler.push(function (EAO) {
    let addressCheckTimeout; // Timeout for delaying the address check

    // Attaching event listeners to each form
    EAO.forms.forEach(form => {
        attachEventListenersToForm(form);
    });

    // Function to attach event listeners to the form's elements
    function attachEventListenersToForm(form) {
        // Attach to submit buttons
        form.querySelectorAll('[type="submit"]').forEach(button => {
            button.addEventListener('click', handleEvent);
        });

        // Attach to input elements
        form.querySelectorAll('input').forEach(input => {
            input.addEventListener('keydown', handleEvent);
        });
    }

    // Handles click and keydown events
    function handleEvent(e) {
        const isClickedOnButton = e.type === 'click';
        const isEnterKeyPressed = e.type === 'keydown' && e.key === 'Enter';

        // Return early if the event isn't a relevant one
        if (!isClickedOnButton && !isEnterKeyPressed) {
            return true;
        }

        // Check if onsubmit trigger is disabled
        if (!EAO.config.trigger.onsubmit) {
            return true;
        }

        // Prevent default action and initiate address check if required
        if (EAO.util.shouldBeChecked() || EAO._addressIsBeingChecked) {
            e.preventDefault();
            if (!EAO._addressIsBeingChecked && !(isEnterKeyPressed && EAO.hasOpenDropdowns())) {
                e.stopPropagation();
                initiateAddressCheck(e);
            }
        } else {
            return true;
        }
    }

    // Initiates the address checking process
    function initiateAddressCheck(e) {
        // Clear existing timeout
        if (addressCheckTimeout) {
            clearTimeout(addressCheckTimeout);
        }

        // Delay execution to debounce rapid requests
        addressCheckTimeout = setTimeout(() => {
            if (EAO.util.shouldBeChecked() && !EAO._addressIsBeingChecked) {
                // Setup for resuming submission if not already set
                if (window.EnderecoIntegrator && !window.EnderecoIntegrator.submitResume) {
                    setupSubmitResume(e);
                }

                // Mark that a submission is in progress and perform the address check
                window.EnderecoIntegrator.hasSubmit = true;
                EAO.util.checkAddress()
                    .catch(error => console.error("Address check failed:", error))
                    .finally(() => window.EnderecoIntegrator.hasSubmit = false);
            }
        }, 300);
    }

    // Sets up the mechanism to resume form submission
    function setupSubmitResume(e) {
        let buttonElement;

        if (e.target.tagName.toLowerCase() === 'input' && e.key === 'Enter') {
            let form = e.target.form;
            buttonElement = form.querySelector('[type="submit"]');
        } else {
            buttonElement = e.target;
        }

        window.EnderecoIntegrator.submitResume = () => {
            window.EnderecoIntegrator.submitResume = undefined;
            if (EAO.config.ux.resumeSubmit) {
                simulateButtonClick(buttonElement);
            }
        };
    }

    // Simulates a button click
    function simulateButtonClick(buttonElement) {
        setTimeout( function() {
            // Creating and dispatching a custom 'click' event
            if (buttonElement.dispatchEvent(new EAO.util.CustomEvent('click', { bubbles: true, cancelable: true }))) {
                buttonElement.click();
            }
        }, 1000);
    }
});


const editAddressHandler = (e) => {
    const targetFormLinkSelector = e.forms[0].getAttribute('data-end-target-link-selector');
    if (!targetFormLinkSelector) {
        return;
    }

    const targetLink = document.querySelector(targetFormLinkSelector);
    if (!targetLink) {
        return;
    }

    targetLink.click();
    const addressEditorPlugin = window.PluginManager.getPluginInstanceFromElement(targetLink, 'AddressEditor');
    if (!addressEditorPlugin) {
        return;
    }

    window.EnderecoIntegrator.editingIntent = true;
    window.EnderecoIntegrator.thirdPartyModals = 1;

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
            return;
        }
        addressEditButton.click();

        var itnrvl = setInterval( function() {
            if (!document.querySelector('.address-editor-modal')) {
                window.EnderecoIntegrator.editingIntent = false;
                window.EnderecoIntegrator.thirdPartyModals = 0;
                clearInterval(itnrvl);
            }
        }, 50);
    })
}

const addressCheckSelectedHandler = (EAO, e) => {
    const form = e.forms[0];
    if (!form) {
        return;
    }

    const targetForm = form.getAttribute('data-end-target-link-selector');
    const ajaxPlugin = window.PluginManager.getPluginInstanceFromElement(form, 'FormAjaxSubmit')
    if (!targetForm || !ajaxPlugin) {
        return;
    }

    ajaxPlugin.$emitter.subscribe('onAfterAjaxSubmit', ({detail: {response}}) => {
        try {
            window.ajaxSubmitRequestPending = false;
            const {addressSaved} = JSON.parse(response);
            if (addressSaved) {
                const reloadHandler = () => {
                    EAO.waitForAllPopupsToClose().then(() => {
                        window.setTimeout(() => {
                            if (!window.ajaxSubmitRequestPending) {
                                if(window.EnderecoIntegrator.popupQueue > 0) {
                                    //we are still waiting for all popups to close
                                   reloadHandler();
                                   return;
                                }
                                window.location.reload();
                            }
                        }, 100);
                    });
                }
                reloadHandler();
            }
        } catch (e) {
            console.warn('[ENDERECO] Failed to save new address', e);
        }
    });

    window.ajaxSubmitRequestPending = true;
    ajaxPlugin._fireRequest();
}

EnderecoIntegrator.afterAMSActivation.push(function (EAO) {

    EAO.onEditAddress.push((e) => {
        editAddressHandler(e);
    })

    EAO.onAfterAddressCheckSelected.push((e) => {
        addressCheckSelectedHandler(EAO, e)
    })

    EAO.onConfirmAddress.push((e) => {
        addressCheckSelectedHandler(EAO, e);
    })

    /**
     * If its one of the existing customer check modals, competing with shopware modals,
     * then rewrite their waitForPopupAreaToBeFree logic
     */
    if (!document.querySelector('.address-editor-modal')) {
        EAO.waitForPopupAreaToBeFree = function() {
            return new EAO.util.Promise(function(resolve, reject) {
                var waitForFreePlace = setInterval(function() {
                    var isAreaFree = !document.querySelector('[endereco-popup]');

                    // No modals from shopware should be open.
                    isAreaFree = isAreaFree && (window.EnderecoIntegrator.thirdPartyModals < 1);

                    // Some global filters, in case in the future we have third party plugins, who want to override this logic.
                    if (!!window.EnderecoIntegrator.$globalFilters && !!window.EnderecoIntegrator.$globalFilters.isModalAreaFree) {
                        window.EnderecoIntegrator.$globalFilters.isModalAreaFree.forEach( function(callback) {
                            isAreaFree = callback(isAreaFree, EAO);
                        });
                    }

                    if(isAreaFree) {
                        clearInterval(waitForFreePlace);
                        resolve();
                    }
                }, 100);
            })
        }
    }
});

if (window.EnderecoIntegrator) {
    window.EnderecoIntegrator = merge(window.EnderecoIntegrator, EnderecoIntegrator);
} else {
    window.EnderecoIntegrator = EnderecoIntegrator;
}

window.EnderecoIntegrator.asyncCallbacks.forEach(function (cb) {
    cb();
});
window.EnderecoIntegrator.asyncCallbacks = [];

window.EnderecoIntegrator.waitUntilReady().then(function () {
    //
});

var $waitForConfig = setInterval(function () {
    if (typeof enderecoLoadAMSConfig === 'function') {
        enderecoLoadAMSConfig();
        clearInterval($waitForConfig);
    }
}, 1);


window.swModalOpened = false;

const swModalListener = () => {
    const shopwareModal = document.querySelector('.modal-backdrop');
    if (shopwareModal && !window.swModalOpened) {
        window.swModalOpened = true;
        window.EnderecoIntegrator.popupQueue++;
    }
    if(!shopwareModal && window.swModalOpened) {
        window.swModalOpened = false;
        window.EnderecoIntegrator.popupQueue--;
    }
};

(new MutationObserver(swModalListener)).observe(document.querySelector('body'), {childList: true, subtree: true});
