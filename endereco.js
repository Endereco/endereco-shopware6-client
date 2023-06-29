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

EnderecoIntegrator.onAjaxFormHandler.push(function (EAO) {
    EAO.forms.forEach(function (form) {
        var submitButtons = form.querySelectorAll('[type="submit"]');
        submitButtons.forEach(function (buttonElement) {

            buttonElement.addEventListener('click', function (e) {

                /**
                 * Essentially this event listener tries to recreate submit listener,
                 * so if in the setting there is no submit listener, then this logic
                 * should not be used, too.
                 */
                if (!EAO.config.trigger.onsubmit) {
                    return true;
                }

                if (EAO.util.shouldBeChecked() || EAO._awaits > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    return true;
                }

                /**
                 * This block defines a code that is executed, if submit has to be continued
                 * after the address correction has been selected.
                 */
                if (window.EnderecoIntegrator && !window.EnderecoIntegrator.submitResume) {
                    window.EnderecoIntegrator.submitResume = function () {

                        window.EnderecoIntegrator.submitResume = undefined;

                        if (EAO.config.ux.resumeSubmit) {
                            if (buttonElement.dispatchEvent(
                                new EAO.util.CustomEvent(
                                    'click',
                                    {
                                        'bubbles': true,
                                        'cancelable': true
                                    }
                                )
                            )) {
                                buttonElement.click();
                            }
                        }
                    }
                }

                if (EAO.util.shouldBeChecked()) {
                    window.EnderecoIntegrator.hasSubmit = true;

                    setTimeout(function () {
                        EAO.util.checkAddress()
                            .catch(function () {
                                EAO.waitForAllPopupsToClose().then(function () {
                                    if (window.EnderecoIntegrator && window.EnderecoIntegrator.submitResume) {
                                        window.EnderecoIntegrator.submitResume();
                                    }
                                }).catch()
                            }).finally(function () {
                            window.EnderecoIntegrator.hasSubmit = false;
                        });
                    }, 300);

                    return false;
                }
            })
        })
    })

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
        const addressEditButton = modalElement.querySelector('[data-target="#address-create-edit"]');
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
