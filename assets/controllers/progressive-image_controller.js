import { Controller } from '@hotwired/stimulus';
import { decode } from 'blurhash';

export default class extends Controller {
	static targets = ["highRes", "placeholder", "errorOverlay"];
    static values = { hash: String, src: String };

	connect() {
        this.renderBlurhash();
		this.preloadImage();
	}

    renderBlurhash() {
		if (this.hashValue.length<6){
			return;
		}
        const pixels = decode(this.hashValue, 32, 32);
        const ctx = this.placeholderTarget.getContext('2d');
        const imageData = ctx.createImageData(32, 32);
        imageData.data.set(pixels);
        ctx.putImageData(imageData, 0, 0);
    }


	preloadImage() {
		const img = new Image();
		img.src = this.srcValue;

		img.onload = () => {
			this.revealTarget(this.highResTarget);
			// Nastavíme src až po načítaní, aby sme sa vyhli bliknutiu
			this.highResTarget.src = this.srcValue;
		};

		img.onerror = () => {
			console.warn("Obraz nenačítaný, zobrazujem lokalizované 404 HTML.");
			this.revealTarget(this.errorOverlayTarget);
		};
	}

	revealTarget(target) {
		// Zobrazíme element v DOM
		target.classList.remove('hidden');

		// Malý timeout, aby prehliadač stihol zaregistrovať odstránenie 'hidden'
		// a spustil CSS transition pre opacity
		setTimeout(() => {
			target.classList.remove('opacity-0');
			target.classList.add('opacity-100');
		}, 10);
	}
}