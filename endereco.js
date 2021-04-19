import Promise from 'promise-polyfill';
import merge from 'lodash.merge';
import axios from 'axios';
import EnderecoIntegrator from '../js-sdk/modules/integrator';
import css from '../js-sdk/themes/default-theme.scss'
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
        postalCode: '[zipcode]',
        locality: '[city]',
        streetFull: '[street]',
        streetName: '[streetname]',
        buildingNumber: '[streetnumber]',
        addressStatus: '[attribute][enderecoamsstatus]',
        addressTimestamp: '[attribute][enderecoamsts]',
        addressPredictions: '[attribute][enderecoamsapredictions]',
        additionalInfo: '[additionalAddressLine2]',
    },
    personServices: {
        salutation: '[salutation]',
        firstName: '[firstname]'
    },
    emailServices: {
        email: '[email]'
    }
};

EnderecoIntegrator.css = css[0][1];
EnderecoIntegrator.resolvers.countryCodeWrite = function (value) {
    return new Promise(function (resolve, reject) {

        var countyCodeEndpoint = EnderecoIntegrator.countryMappingUrl + '?countryCode=' + value;
        new axios.get(countyCodeEndpoint, {
            timeout: 3000
        })
            .then(function (response) {
                resolve(response.data);
            })
            .catch(function (e) {
                resolve(value);
            }).finally(function () {
        });
    });
}
EnderecoIntegrator.resolvers.countryCodeRead = function (value) {
    return new Promise(function (resolve, reject) {
        var countyEndpoint = EnderecoIntegrator.countryMappingUrl + '?countryId=' + value;
        new axios.get(countyEndpoint, {
            timeout: 3000
        })
            .then(function (response) {
                resolve(response.data);
            })
            .catch(function (e) {
                resolve(value);
            }).finally(function () {
        });
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

