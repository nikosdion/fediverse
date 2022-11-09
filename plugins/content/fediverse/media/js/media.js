/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

[].slice.call(document.querySelectorAll('.toot-embed-content-media-item'))
	.forEach((media) => media.addEventListener('click', (event) => {
		event.preventDefault();

		const elModal   = document.getElementById('plg_content_fediverse_dialog');
		const elContent = document.getElementById('plg_content_fediverse_dialog_content');

		if (!elModal || !elContent)
		{
			return;
		}

		elContent.innerHTML = media.outerHTML;

		const elVideo = elContent.querySelector('video');

		if (elVideo) {
			elVideo.setAttribute('controls' , 'controls');
		}

		const myModal = new bootstrap.Modal(
			elModal, {
				keyboard: true,
				backdrop: true
			}
		);

		myModal.show();
	}));