import Plugin from 'src/plugin-system/plugin.class';

export default class EnderecoCheckoutAddress extends Plugin {

    static options = {
        billingNeedsCorrection: false,
        shippingNeedsCorrection: false,
    };

    init() {
        const billingNeedsCorrection = this.options.billingNeedsCorrection;
        const shippingNeedsCorrection = this.options.shippingNeedsCorrection;
        this.billingEditLink = this.el.querySelector('.confirm-billing-address a[data-address-editor]');
        this.shippingEditLink = this.el.querySelector('.confirm-shipping-address a[data-address-editor]');
        if (this.billingEditLink && billingNeedsCorrection) {
            this.billingEditLink.click();
        }
        if (!billingNeedsCorrection && this.shippingEditLink && shippingNeedsCorrection) {
            this.shippingEditLink.click();
        }

        //If both addresses are required - force to open shipping modal right after billing modal is closed
        if (billingNeedsCorrection && shippingNeedsCorrection) {
            this.registerBillingModalEvents();
        }
    }

    registerBillingModalEvents() {
        const billingAddressEditPlugin = window.PluginManager.getPluginInstanceFromElement(this.billingEditLink, 'AddressEditor');

        if (billingAddressEditPlugin) {
            billingAddressEditPlugin.$emitter.subscribe('onOpen', ({detail: {pseudoModal = null}}) => {
                if (pseudoModal) {
                    pseudoModal._$modal.on('hidden.bs.modal', () => {
                        this.shippingEditLink.click();
                    });
                }
            })
        }
    }
}
