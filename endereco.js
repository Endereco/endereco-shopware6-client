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

EnderecoIntegrator.onAjaxFormHandler.push( function(EAO) {
    EAO.forms.forEach( function(form) {
        var submitButtons = form.querySelectorAll('[type="submit"]');
        submitButtons.forEach( function(buttonElement) {
            buttonElement.addEventListener('click', function(e) {
                if (EAO.util.shouldBeChecked()) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (window.EnderecoIntegrator && !window.EnderecoIntegrator.submitResume) {
                        window.EnderecoIntegrator.submitResume = function() {
                            if(form.dispatchEvent(
                                new EAO.util.CustomEvent(
                                    'submit',
                                    {
                                        'bubbles': true,
                                        'cancelable': true
                                    }
                                )
                            )) {
                                form.submit();
                            }
                            window.EnderecoIntegrator.submitResume = undefined;
                        }
                    }

                    EAO.util.checkAddress()
                        .catch(function() {
                            EAO.waitForAllPopupsToClose().then(function() {
                                if (window.EnderecoIntegrator && window.EnderecoIntegrator.submitResume) {
                                    window.EnderecoIntegrator.submitResume();
                                }
                            }).catch()
                        });

                    return false;
                }
            })
        })
    })

});

EnderecoIntegrator.afterAMSActivation.push( function(EAO) {

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
