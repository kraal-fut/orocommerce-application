import BaseView from 'oroui/js/app/views/base/view';

/**
 * Shows the per line item shipping address choices when "Ship to multiple addresses" is checked
 * and resets them to the main shipping address when it is unchecked.
 */
const SplitDeliveryView = BaseView.extend({
    options: {
        selectors: {
            toggle: '[data-role="split-delivery-toggle"]',
            items: '[data-role="split-delivery-items"]'
        }
    },

    events() {
        return {
            [`change ${this.options.selectors.toggle}`]: 'onToggle'
        };
    },

    /**
     * @inheritdoc
     */
    constructor: function SplitDeliveryView(options) {
        SplitDeliveryView.__super__.constructor.call(this, options);
    },

    /**
     * @inheritdoc
     */
    initialize(options) {
        this.options = {...this.options, ...options};
        SplitDeliveryView.__super__.initialize.call(this, options);
        this.onToggle();
    },

    onToggle() {
        const enabled = this.$(this.options.selectors.toggle).is(':checked');
        const $items = this.$(this.options.selectors.items);

        $items.toggleClass('hidden', !enabled);
        if (!enabled) {
            $items.find('select').val('').trigger('change');
        }
    }
});

export default SplitDeliveryView;
