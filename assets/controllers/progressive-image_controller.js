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
		const width = this.placeholderTarget.width;
		const height = this.placeholderTarget.height;
		const pixels = decode(this.hashValue, width, height);
		const ctx = this.placeholderTarget.getContext('2d');
		const imageData = ctx.createImageData(width, height);
		imageData.data.set(pixels);
		ctx.putImageData(imageData, 0, 0);
    }


	preloadImage() {
		const img = new Image();
		img.src = this.srcValue;

		img.onload = () => {
			this.revealTarget(this.highResTarget);
			this.highResTarget.src = this.srcValue;
			this.highResTarget.style.opacity = 1;
		};

		img.onerror = () => {
			console.warn("Obraz nenačítaný, zobrazujem lokalizované 404 HTML.");
			this.revealTarget(this.errorOverlayTarget);
		};
	}

	revealTarget(target) {
		// Zobrazíme element v DOM
		target.style.display = 'block';

		// Malý timeout, aby prehliadač stihol zaregistrovať odstránenie 'hidden'
		// a spustil CSS transition pre opacity
		setTimeout(() => {
			target.style.opacity = 1;
		}, 10);
	}
}