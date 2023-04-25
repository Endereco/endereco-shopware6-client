import Promise from 'promise-polyfill';
import merge from 'lodash.merge';
import EnderecoIntegrator from './node_modules/@endereco/js-sdk/modules/integrator';
import './endereco.scss'

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
    if (!!jQuery) {
        jQuery(subscriber.object).val(value).trigger('change');
    } else {
        subscriber.object.value = value;
    }
}

EnderecoIntegrator.resolvers.subdivisionCodeSetValue = function (subscriber, value) {
    if (!!jQuery) {
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
    var mapping = {
        'F': 'ms',
        'M': 'mr'
    };
    return new Promise(function (resolve, reject) {
        resolve(mapping[value]);
    });
}
EnderecoIntegrator.resolvers.salutationRead = function (value) {
    var mapping = {
        'ms': 'F',
        'mr': 'M'
    };
    return new Promise(function (resolve, reject) {
        resolve(mapping[value]);
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

EnderecoIntegrator.afterAMSActivation.push(function (EAO) {
    EAO.onEditAddress.push((d) => {
        const targetFormLinkSelector = d.forms[0].getAttribute('data-end-target-link-selector');
        if (targetFormLinkSelector) {
            const targetLink = document.querySelector(targetFormLinkSelector);
            if (targetLink) {
                targetLink.click();
            }
        }

    })

    EAO.onAfterAddressCheckSelected.push((d) => {
        const form = d.forms[0];
        if (form) {
            const targetForm = form.getAttribute('data-end-target-link-selector');
            const ajaxPlugin = window.PluginManager.getPluginInstanceFromElement(form, 'FormAjaxSubmit')
            if (targetForm && ajaxPlugin) {
                ajaxPlugin.$emitter.subscribe('onAfterAjaxSubmit', ({detail: {response}}) => {
                    try {
                        const {addressSaved} = JSON.parse(response);
                        if (addressSaved) {
                            window.location.reload();
                        }
                    } catch (e) {
                        console.warn('[ENDERECO] Failed to save new address', e);
                    }
                });

                ajaxPlugin._fireRequest();
            }
        }
    })
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

var $waitForConfig = setInterval( function() {
    if(typeof enderecoLoadAMSConfig === 'function'){
        enderecoLoadAMSConfig();
        clearInterval($waitForConfig);
    }
}, 1);
