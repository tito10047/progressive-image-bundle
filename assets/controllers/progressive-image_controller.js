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

		if (img.complete) {
			this.showImmediately(this.highResTarget);
			this.highResTarget.src = this.srcValue;
			return;
		}

		img.onload = () => {
			this.revealTarget(this.highResTarget);
			this.highResTarget.src = this.srcValue;
		};

		img.onerror = () => {
			console.warn("Obraz nenačítaný, zobrazujem lokalizované 404 HTML.");
			this.revealTarget(this.errorOverlayTarget);
		};
	}

	showImmediately(target) {
		target.style.transition = 'none';
		target.style.display = 'block';
		target.style.opacity = 1;
		// Vynútenie prekreslenia (reflow), aby sa transition: none aplikovalo okamžite
		target.offsetHeight; 
		target.style.transition = '';
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