if (typeof window.wc !== 'undefined' && window.wc.wcBlocksRegistry && typeof wp.i18n !== 'undefined' && typeof React !== 'undefined') {

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

    const HipayMultibanco = {
        name: 'hipaymultibanco', 
        label: wp.i18n.__('Multibanco', 'hipaymultibanco'), 
        content: React.createElement('div', null, wp.i18n.__('Efetue o pagamento num terminal ou através do seu Homebanking.', 'hipaymultibanco')), 
        edit: React.createElement('div', null, wp.i18n.__('Efetue o pagamento num terminal ou através do seu Homebanking.', 'hipaymultibanco')), 
        canMakePayment: () => true, 
        ariaLabel: wp.i18n.__('Multibanco', 'hipaymultibanco'), 
        supports: {
            features: ['products'], 
        },
    };

    registerPaymentMethod(HipayMultibanco);
}
