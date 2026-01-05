import { Controller } from '@hotwired/stimulus';
import { decode } from 'blurhash';
export default class extends Controller {
	static targets = ["highRes", "placeholder", "errorOverlay"];
	static values = {hash: String, framework: String};

	initialize() {
		if (this.frameworkValue === 'tailwind') {
			import("../styles/progressive-image-tailwind.css")
		}
		if (this.frameworkValue === 'bootstrap') {
			import("../styles/progressive-image-bootstrap.css")
		}
	}

	connect() {
		if (this.highResTarget.complete && this.highResTarget.naturalWidth === 0) {
			this.handleError(true);
			return;
		} else if (this.highResTarget.complete && this.highResTarget.naturalWidth > 0) {
			this.reveal();
			return;
		}

        this.renderBlurhash();

        if (!this.highResTarget.complete) {
            this.highResTarget.style.opacity = '0';
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
		if (this.highResTarget.complete && this.highResTarget.naturalWidth > 0) {
			this.highResTarget.style.transition = 'none';
		}
		// Force reflow pre istotu, ak by sa opacity menilo príliš rýchlo po pripojení do DOM
		this.highResTarget.offsetHeight;
        this.highResTarget.style.opacity = '1';
        if (this.hasPlaceholderTarget) {
			const delay = (this.highResTarget.style.transition === 'none') ? 0 : 1000;
			setTimeout(() => this.placeholderTarget.style.display = 'none', delay);
        }
    }

	handleError(immediate = false) {
        if (this.hasErrorOverlayTarget) {
			if (this.hasPlaceholderTarget) this.placeholderTarget.style.display = 'none';
			this.highResTarget.style.display = 'none';
            this.errorOverlayTarget.style.display = 'block';

			if (immediate) {
				this.errorOverlayTarget.style.transition = 'none';
				this.errorOverlayTarget.style.opacity = '1';
			} else {
				// Malý delay pre plynulý fade-in overlayu, ak máš transition
				setTimeout(() => {
					this.errorOverlayTarget.style.opacity = '1';
				}, 50);
			}
        }
        
        console.error(`ProgressiveImage: Failed to load image at ${this.highResTarget.src}`);
    }
}