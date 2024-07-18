import Plugin from 'src/plugin-system/plugin.class';
export default class HeidipayOpenMiniBasket extends Plugin {
    init() {
        const elementOffcanvas = document.querySelector('[data-off-canvas-cart]');
        if (elementOffcanvas instanceof HTMLElement) {
            const pluginOffCanvas = window.PluginManager.getPluginInstanceFromElement(elementOffcanvas, 'OffCanvasCart');
            pluginOffCanvas.$emitter.subscribe('offCanvasOpened', this.onOffCanvasOpened);
        }
    }
    onOffCanvasOpened() {
        let cusid_ele = document.getElementsByClassName('heidipay-container-2');
        if (cusid_ele.length) {
            if (window.HeidiPayLoaded) {
                initCoreHeidiCode(jQuery);
            } else {
                loadHeidiPay();
            }
        }
    }
}