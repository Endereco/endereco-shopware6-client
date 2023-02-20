import EnderecoCheckoutAddressPlugin from './plugins/endereco-checkout-address';

const PluginManager = window.PluginManager;
PluginManager.register('EnderecoCheckoutAddress', EnderecoCheckoutAddressPlugin,  '[data-endereco-checkout-address]');
