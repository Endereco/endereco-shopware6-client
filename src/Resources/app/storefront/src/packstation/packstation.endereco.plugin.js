const {PluginBaseClass} = window;

export default class TonurPackstationEnderecoPlugin extends PluginBaseClass {
    static options = {
        selectors: {
            enderecoHouseNumberLogin: '[name="shippingAddress[enderecoHousenumber]"]',
            enderecoHouseNumber: '[name="address[enderecoHousenumber]"]',
            enderecoAddressStreetLogin: '[name="shippingAddress[enderecoStreet]"]',
            enderecoAddressStreet: '[name="address[enderecoStreet]"]',
            repertusPackstationNumberLogin: '[name="shippingAddress[repertusPackstationAddressPackstationNumber]"]',
            repertusPackstationNumber: '[name="address[repertusPackstationAddressPackstationNumber]"]',
            repertusPackstationPostNumberLogin: '[name="shippingAddress[repertusPackstationPostNumber]"]',
            repertusPackstationPostNumber: '[name="address[repertusPackstationPostNumber]"]',
            additionalAddressLine1Login: '[name="shippingAddress[additionalAddressLine1]"]',
            additionalAddressLine1: '[name="address[additionalAddressLine1]"]',
            streetAddressLogin: '[name="shippingAddress[street]"]',
            streetAddress: '[name="address[street]"]',
            repertusAddressTypeLogin: '[name="shippingAddress[repertusPackstationAddressType]"]',
            repertusAddressType: '[name="address[repertusPackstationAddressPackstationType]"]',
            zipcodeAddressLogin: '[name="shippingAddress[zipcode]"]',
            zipcodeAddress: '[name="address[zipcode]"]',
            cityAddressLogin: '[name="shippingAddress[city]"]',
            cityAddress: '[name="address[city]"]',
            repertusAddressTypeSelector: '[name="shippingAddress[repertusPackstationAddressPackstationType]"]',
            repertusAddressTypeSelectorLogin: '[name="address[repertusPackstationAddressPackstationType]"]'
        }
    }

    init() {
        const me = this;
        me.$el = me.el;

        me.$form = me.$el.closest('form');

        if (!me.$form) {
            return;
        }

        me.$enderecoHouseNumberInput = me.$form.querySelector(me.options.selectors.enderecoHouseNumber) ?? me.$form.querySelector(me.options.selectors.enderecoHouseNumberLogin);
        me.$enderecoStreetInput = me.$form.querySelector(me.options.selectors.enderecoAddressStreet) ?? me.$form.querySelector(me.options.selectors.enderecoAddressStreetLogin);
        me.$repertusPackstationNumberInput = me.$form.querySelector(me.options.selectors.repertusPackstationNumber) ?? me.$form.querySelector(me.options.selectors.repertusPackstationNumberLogin);
        me.$repertusPackstationPostNumberInput = me.$form.querySelector(me.options.selectors.repertusPackstationPostNumber) ?? me.$form.querySelector(me.options.selectors.repertusPackstationPostNumberLogin);
        me.$additionalAddressLine1Input = me.$form.querySelector(me.options.selectors.additionalAddressLine1) ?? me.$form.querySelector(me.options.selectors.additionalAddressLine1Login);
        me.$shopwareStreetInput = me.$form.querySelector(me.options.selectors.streetAddress) ?? me.$form.querySelector(me.options.selectors.streetAddressLogin);

        me.$repertusAddressTypeRadiosRegister = me.$form.querySelectorAll(me.options.selectors.repertusAddressType);
        me.$repertusAddressTypeRadiosLogin = me.$form.querySelectorAll(me.options.selectors.repertusAddressTypeLogin);

        me.$repertusAddressTypeRadios = [...me.$repertusAddressTypeRadiosRegister, ...me.$repertusAddressTypeRadiosLogin];

        me.$zipcodeInput = me.$form.querySelector(me.options.selectors.zipcodeAddress) ?? me.$form.querySelector(me.options.selectors.zipcodeAddressLogin);
        me.$cityInput = me.$form.querySelector(me.options.selectors.cityAddress) ?? me.$form.querySelector(me.options.selectors.cityAddressLogin);
        me.$repertusAddressTypeSelector = me.$form.querySelector(me.options.selectors.repertusAddressTypeSelector) ?? me.$form.querySelector(me.options.selectors.repertusAddressTypeSelectorLogin);

        me.$streetInput = (me.$enderecoStreetInput ?? me.$shopwareStreetInput);

        me._registerEvents();
    }

    _registerEvents() {
        const me = this;

        const pluginRegistry = window.PluginManager;

        pluginRegistry.initializePlugins().then(() => {
            const pluginInstance = pluginRegistry.getPluginInstanceFromElement(document.querySelector('[data-tonur-packstation-form]'), 'TonurPackstationForm');
            pluginInstance.$emitter.subscribe('TonurPackstationForm/updateView', me._updateView.bind(me));
            pluginInstance._handleRelevantDataChanged(false);

            // triggered when popup has been closed, no matter if a prediction has been selected or address has been confirmed
            // used to update the repertus fields and visual status css classes
            document.addEventListener('EAO.onAfterAddressPersisted', (event) => {
                me._updateRepertusFields(event);
            });
        });

        // trigger address check when packstation or post number has been changed
        // -> triggers too many checks when tabbing out of fields (DEV-307)
        /*if (me.$repertusPackstationNumberInput && me.$repertusPackstationPostNumberInput) {
            me.$repertusPackstationNumberInput.addEventListener('blur', (event) => {
                me._triggerAddressCheck();
            });

            me.$repertusPackstationPostNumberInput.addEventListener('blur', (event) => {
                me._triggerAddressCheck();
            });
        }*/

        if (me.$repertusPackstationNumberInput) {
            me.$repertusPackstationNumberInput.addEventListener('blur', (event) => {
                me._resetBorders(me.$repertusPackstationNumberInput);
            });
        }

        if (me.$repertusPackstationPostNumberInput) {
            me.$repertusPackstationPostNumberInput.addEventListener('blur', (event) => {
                me._resetBorders(me.$repertusPackstationPostNumberInput);
            });
        }

        // clear street and housenumber when address is changed between normal post address and packstation
        if (me.$repertusAddressTypeRadios) {
            me.$repertusAddressTypeRadios.forEach(radio => {
                radio.addEventListener('change', (event) => {
                    if (event.target.value === '0') {
                        if (me.$enderecoHouseNumberInput) {
                            me.$enderecoHouseNumberInput.value = '';
                        }

                        if (me.$streetInput) {
                            me.$streetInput.value = '';
                        }
                    }

                    if (event.target.value === '1') {
                        if (me.$repertusPackstationNumberInput) {
                            me.$repertusPackstationNumberInput.value = '';
                        }
                    }

                    if (me.$repertusPackstationNumberInput) {
                        me._resetBorders(me.$repertusPackstationNumberInput);
                    }

                    if (me.$repertusPackstationPostNumberInput) {
                        me._resetBorders(me.$repertusPackstationPostNumberInput);
                    }
                });
            });
        }

        // trigger check when we switch between packstation and post office address type
        // triggers check too early when switching between post office and normal address type (DEV-307)
        /*if(me.$repertusAddressTypeSelector) {
            me.$repertusAddressTypeSelector.addEventListener('change', (event) => {
                me._triggerAddressCheck();
            });
        }*/
    }

    _resetBorders(element) {
        if (!element) {
            return;
        }

        const elementFormGroup = element.closest('.form-group');

        if (!elementFormGroup) {
            return;
        }

        elementFormGroup.classList.remove('endereco-s--field_not_correct');
        elementFormGroup.classList.remove('endereco-s--field_correct');
    }

    /**
     * Triggered when endereco has done the address check and marks the fields from repertus with the correct status color
     * @param event
     * @private
     */
    _updateRepertusFields(event) {
        const me = this;

        const EAO = event.detail.EAOEventData;

        EAO.waitForAllExtension().then(function () {

            // update repertus postnumber border with postnumber status from endereco
            me._updateBorders(me.$additionalAddressLine1Input, me.$repertusPackstationPostNumberInput);

            // update repertus housenumber border with housenumber status from endereco when split street is active
            me._updateBorders(me.$enderecoHouseNumberInput, me.$repertusPackstationNumberInput);

            // update repertus housenumber border from street status from endereco if split street is inactive
            me._updateBorders(me.$shopwareStreetInput, me.$repertusPackstationNumberInput);

            // in case user has selected a prediction from the popup, update repertus packstation number field
            if (me.$repertusPackstationNumberInput && EAO.getBuildingNumber()) {
                me.$repertusPackstationNumberInput.value = EAO.getBuildingNumber();
            }
        });
    }

    /**
     * Update borders of source and destination form groups based on validation status
     * @param source
     * @param destination
     * @private
     */
    _updateBorders(source, destination) {
        if (!source || !destination) {
            return;
        }

        const sourceFormGroup = source.closest('.form-group');
        const destinationFormGroup = destination.closest('.form-group');

        if (!sourceFormGroup || !destinationFormGroup) {
            return;
        }

        destinationFormGroup.classList.remove('endereco-s--field_not_correct');
        destinationFormGroup.classList.remove('endereco-s--field_correct');

        if (sourceFormGroup.classList.contains('endereco-s--field_not_correct')) {
            destinationFormGroup.classList.add('endereco-s--field_not_correct');
        }

        if (sourceFormGroup.classList.contains('endereco-s--field_correct')) {
            destinationFormGroup.classList.add('endereco-s--field_correct');
        }
    }

    /**
     * Should trigger a andereco address check which should show a popup for corrections
     * @private
     */

    /*
    Triggers too many address checks and causes other problems (DEV-307)
    async _triggerAddressCheck() {
        const me = this;

        // Only trigger address check when all fields are filled
        if (
            me.$repertusPackstationNumberInput.value === ''
            || me.$repertusPackstationPostNumberInput.value === ''
            || me.$streetInput.value === ''
            || me.$cityInput.value === ''
            || me.$zipcodeInput.value === ''
        ) {
            return;
        }

        if (window.EAO && window.EAO.util) {

            // we need to wait until all fields have been updated in the background
            await me._delay(250);

            // invalidate all data about the address
            window.EAO.util.invalidateAddressMeta();

            // wait for invalidation finishes...
            await me._delay(50);

            // we need to trigger a address check because we check hidden fields
            window.EAO.util.checkAddress();
        }
    }*/

    /**
     * Simple delay function to wait for some time
     * @param ms
     * @returns {Promise}
     * @private
     */
    _delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Triggered when repertus has updated the shopware default input fields with
     * the data from the packstation field inputs, copies the data to endereco fields
     * if neccessary
     * @param event
     * @private
     */
    _updateView(event) {
        const me = this;

        if (me.$repertusPackstationNumberInput) {
            me._resetBorders(me.$repertusPackstationNumberInput);
        }

        if (me.$repertusPackstationPostNumberInput) {
            me._resetBorders(me.$repertusPackstationPostNumberInput);
        }

        // if split street is active, copy housenumber (packstation or post office number) from repertus to the endereco housenumber field
        if (me.$enderecoStreetInput && me.$enderecoHouseNumberInput && me.$repertusPackstationNumberInput && me.$repertusPackstationNumberInput.value !== '') {
            me.$enderecoHouseNumberInput.value = me.$repertusPackstationNumberInput.value;
            me.$enderecoStreetInput.value = event.detail.streetValue;
        }

        // hide endereco fields for street and housenumber
        if (event.detail.isPackstationOrPostOffice) {
            if (me.$enderecoStreetInput) {
                const $enderecoStreetFormGroup = me.$enderecoStreetInput.closest('.form-group');
                me._hideElement($enderecoStreetFormGroup);
            }

            if (me.$enderecoHouseNumberInput) {
                const $enderecoHouseNumberFormGroup = me.$enderecoHouseNumberInput.closest('.form-group');
                me._hideElement($enderecoHouseNumberFormGroup);
            }

            return;
        }

        // show endereco fields for street and housenumber if we have a normal address for shipping
        if (me.$enderecoStreetInput) {
            const $enderecoStreetFormGroup = me.$enderecoStreetInput.closest('.form-group');
            me._showElement($enderecoStreetFormGroup);
        }

        if (me.$enderecoHouseNumberInput) {
            const $enderecoHouseNumberFormGroup = me.$enderecoHouseNumberInput.closest('.form-group');
            me._showElement($enderecoHouseNumberFormGroup);
        }
    }

    _hideElement($el) {
        $el.style.display = 'none';
    }

    _showElement($el) {
        $el.attributeStyleMap.clear();
    }
}