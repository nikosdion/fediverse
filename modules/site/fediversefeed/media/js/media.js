/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

[].slice.call(document.querySelectorAll('.fediverse-toot-media-item'))
	.forEach((media) => media.addEventListener('click', (event) => {
		event.preventDefault();

		const elModal   = document.getElementById('mod_fediversemodal_dialog');
		const elContent = document.getElementById('mod_fediversemodal_dialog_content');

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