const PluginManager = window.PluginManager;

PluginManager.register('TonurPackstationEnderecoPlugin', () => import("./packstation/packstation.endereco.plugin"), '[data-tonur-packstation-form]');