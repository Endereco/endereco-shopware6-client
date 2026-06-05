const { Component, Mixin } = Shopware;
import template from './endereco-api-check-button.html.twig';

Component.register('endereco-api-check-button', {
    template,

    props: ['label'],
    inject: ['enderecoSW6ClientAPITest'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            const salesChannelId = $parent.currentSalesChannelId;

            // Filter out null values from sales channel config.
            // Inherited config values are null by default,
            // so these null values must be removed before merging
            // with the global config.
            const salesChannelConfig = Object.fromEntries(
                Object.entries($parent.actualConfigData[salesChannelId] || {})
                    .filter(([, value]) => value !== null)
            );

            // Merge global config with sales channel-specific overrides
            // so the test always uses the correct credentials, even for
            // subshops with channel-specific API keys.
            return {
                ...$parent.actualConfigData.null,
                ...salesChannelConfig
            };
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
            this.enderecoSW6ClientAPITest.check(this.pluginConfig).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('endereco-api-check-button.title'),
                        message: this.$tc('endereco-api-check-button.success')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('endereco-api-check-button.title'),
                        message: this.$tc('endereco-api-check-button.error')
                    });
                }
                this.isLoading = false;
            });
        }
    }
})
