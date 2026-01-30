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
            repertusAddressTypeLogin: '[name="shippingAddress[repertusPackstationAddressPackstationType]"]',
            repertusAddressType: '[name="address[repertusPackstationAddressPackstationType]"]'
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
        me.$streetInput = me.$form.querySelector(me.options.selectors.streetAddress) ?? me.$form.querySelector(me.options.selectors.streetAddressLogin);
        me.$repertusAddressTypeInput = me.$form.querySelector(me.options.selectors.repertusAddressType) ?? me.$form.querySelector(me.options.selectors.repertusAddressTypeLogin);

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

        // trigger address check when packstation number has been changed
        if (me.$repertusPackstationNumberInput) {
            me.$repertusPackstationNumberInput.addEventListener('blur', (event) => {
                me._triggerAddressCheck();
            });
        }

        // trigger address check when post number has been changed
        if (me.$repertusPackstationPostNumberInput) {
            me.$repertusPackstationPostNumberInput.addEventListener('blur', (event) => {
                me._triggerAddressCheck();
            });
        }

        // trigger address check when address type has been changed
        if (me.$repertusAddressTypeInput) {
            me.$repertusAddressTypeInput.addEventListener('change', (event) => {
                me._triggerAddressCheck();
            });
        }
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
            me._updateBorders(me.$streetInput, me.$repertusPackstationNumberInput);

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

        if (sourceFormGroup.classList.contains('endereco-s--field_not_correct')) {
            destinationFormGroup.classList.add('endereco-s--field_not_correct');
        } else {
            destinationFormGroup.classList.remove('endereco-s--field_not_correct');
        }

        if (sourceFormGroup.classList.contains('endereco-s--field_correct')) {
            destinationFormGroup.classList.add('endereco-s--field_correct');
        } else {
            destinationFormGroup.classList.remove('endereco-s--field_correct');
        }
    }

    /**
     * Should trigger a andereco address check which should show a popup for corrections
     * @private
     */
    async _triggerAddressCheck() {
        const me = this;

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
    }

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