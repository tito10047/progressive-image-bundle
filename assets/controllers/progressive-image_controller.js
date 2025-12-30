import { Controller } from '@hotwired/stimulus';
import { decode } from 'blurhash';
export default class extends Controller {
	static targets = ["highRes", "placeholder", "errorOverlay"];
    static values = { hash: String };

	connect() {
        this.renderBlurhash();

        // Ak obrázok ešte nie je načítaný, nastavíme mu opacity na 0 bez transition
        if (!this.highResTarget.complete) {
            this.highResTarget.style.opacity = '0';
        }

        // Senior tip: Ak obrázok zlyhal skôr, než sa stihol pripojiť JS
        if (this.highResTarget.complete && this.highResTarget.naturalWidth === 0) {
            this.handleError();
        } else if (this.highResTarget.complete && this.highResTarget.naturalWidth > 0) {
            this.reveal();
        }
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

    reveal() {
        this.highResTarget.style.opacity = '1';
        if (this.hasPlaceholderTarget) {
            setTimeout(() => this.placeholderTarget.style.display = 'none', 1000);
        }
    }

    handleError() {
        if (this.hasErrorOverlayTarget) {
			if (this.hasPlaceholderTarget) this.placeholderTarget.style.display = 'none';
			this.highResTarget.style.display = 'none';
            this.errorOverlayTarget.style.display = 'block';
            // Malý delay pre plynulý fade-in overlayu, ak máš transition
            setTimeout(() => {
                this.errorOverlayTarget.style.opacity = '1';
            }, 50);
        }
        
        console.error(`ProgressiveImage: Failed to load image at ${this.highResTarget.src}`);
    }
}