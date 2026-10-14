/**
 * Nexura Product Video Media Gallery for WooCommerce - Admin Controller
 *
 * @package Product_Video_Showcase_For_WooCommerce
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Settings Page Tabs
		$('.wpvg-nav-tab').on('click', function(e) {
			e.preventDefault();
			const targetTab = $(this).attr('href');

			$('.wpvg-nav-tab').removeClass('active');
			$(this).addClass('active');

			$('.wpvg-tab-content').hide();
			$(targetTab).show();

			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, null, targetTab);
			}
		});

		// Restore tab from hash if present in URL
		if (window.location.hash && $(window.location.hash).length) {
			$('.wpvg-nav-tab[href="' + window.location.hash + '"]').trigger('click');
		}

		// Metabox Sub-Tabs (Product Videos vs Custom Gallery Style)
		$(document).on('click', '.wpvg-metabox-tab', function(e) {
			e.preventDefault();
			const targetId = $(this).attr('data-tab') || $(this).data('tab') || $(this).attr('href');
			if (!targetId) {
				return;
			}

			const selector = targetId.startsWith('#') ? targetId : '#' + targetId;

			$('.wpvg-metabox-tab').removeClass('active');
			$(this).addClass('active');

			$('.wpvg-metabox-tab-content').hide();
			$(selector).show();
		});

		// Product Gallery Override Toggle
		function toggleProductOverrideFields() {
			const isCustom = $('#wpvg_override_gallery').val() === 'custom';
			if (isCustom) {
				$('.wpvg-product-override-fields').slideDown(150);
			} else {
				$('.wpvg-product-override-fields').slideUp(150);
			}
		}

		$('#wpvg_override_gallery').on('change', toggleProductOverrideFields);
		toggleProductOverrideFields();

		// Free/.org package: strip Pro-only UI that is not present in this build.
		const isPremiumPackage = typeof wpvg_admin_data !== 'undefined' && !!wpvg_admin_data.is_premium_package;

		if (!isPremiumPackage) {
			$('option[data-pro="true"]').remove();
			$('optgroup').each(function() {
				if (!$(this).children('option').length) {
					$(this).remove();
				}
			});
			$('.wpvg-nav-tabs a[href="#tab-archive"], .wpvg-nav-tabs a[href="#tab-3d"]').remove();
			$('#tab-archive, #tab-3d').remove();
			$('.wpvg-metabox-tab[data-tab="wpvg-mb-tab-style"], .wpvg-metabox-tab[data-tab="wpvg-mb-tab-archive"], .wpvg-metabox-tab[data-tab="wpvg-mb-tab-3d"]').remove();
			$('#wpvg-mb-tab-style, #wpvg-mb-tab-archive, #wpvg-mb-tab-3d').remove();
			$('tr.wpvg-pro-locked-row, .wpvg-pro-locked-row').each(function() {
				const $row = $(this).is('tr') ? $(this) : $(this).closest('tr');
				if ($row.length) {
					$row.remove();
				}
			});
			$('.wpvg-input-with-pro[data-pro="true"]').closest('tr').remove();
			$('.wpvg-upgrade-banner .wpvg-btn-upgrade').closest('.wpvg-upgrade-banner').find('h3').text('Optional Pro edition');
		}

		/* ============================================================
		   Video Preview Helpers
		   ============================================================ */

		/**
		 * Detect video provider and extract embed info from a URL.
		 * Returns: { provider, embedUrl, thumbUrl, label, isTall }
		 */
		function wpvgDetectVideo(url) {
			url = (url || '').trim();
			if (!url) return null;

			// YouTube
			var ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/);
			if (ytMatch) {
				var vid = ytMatch[1];
				var isShort = url.indexOf('/shorts/') !== -1;
				return {
					provider: 'youtube',
					label: 'YouTube',
					embedUrl: 'https://www.youtube-nocookie.com/embed/' + vid + '?autoplay=1&rel=0&modestbranding=1&playsinline=1',
					thumbUrl: 'https://i.ytimg.com/vi/' + vid + '/' + (isShort ? 'hq720.jpg' : 'hqdefault.jpg'),
					isTall: isShort
				};
			}

			// Vimeo
			var vimeoMatch = url.match(/vimeo\.com\/(?:video\/|)(\d+)/);
			if (vimeoMatch) {
				var vimId = vimeoMatch[1];
				return {
					provider: 'vimeo',
					label: 'Vimeo',
					embedUrl: 'https://player.vimeo.com/video/' + vimId + '?autoplay=1',
					thumbUrl: '', // loaded async via oEmbed
					isTall: false,
					vimId: vimId
				};
			}

			// TikTok
			var tiktokMatch = url.match(/tiktok\.com\/@[^/]+\/video\/(\d+)/);
			if (tiktokMatch) {
				var tikId = tiktokMatch[1];
				return {
					provider: 'tiktok',
					label: 'TikTok',
					embedUrl: 'https://www.tiktok.com/embed/v2/' + tikId,
					thumbUrl: '',
					isTall: true
				};
			}

			// Facebook
			if (url.indexOf('facebook.com') !== -1) {
				return {
					provider: 'facebook',
					label: 'Facebook',
					embedUrl: 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=false&autoplay=true',
					thumbUrl: '',
					isTall: false
				};
			}

			// Self-hosted video file
			if (url.match(/\.(mp4|webm|ogg)(\?.*)?$/i)) {
				return {
					provider: 'self',
					label: 'Self-Hosted',
					embedUrl: url,
					thumbUrl: '',
					isTall: false
				};
			}

			// Generic iframe (custom embed)
			return {
				provider: 'unknown',
				label: 'Custom URL',
				embedUrl: url,
				thumbUrl: '',
				isTall: false
			};
		}

		/**
		 * Build the preview panel HTML string (facade state — click to play).
		 */
		function wpvgBuildPreviewHtml(info) {
			var tallClass = info.isTall ? ' tiktok-facade' : '';
			var thumbHtml = info.thumbUrl
				? '<img src="' + info.thumbUrl + '" class="wpvg-preview-facade-thumb" alt="Video preview">' 
				: '<div class="wpvg-preview-facade-thumb" style="background: linear-gradient(135deg,#1e293b,#0f172a);"></div>';

			return [
				'<div class="wpvg-preview-wrap" data-provider="' + info.provider + '" data-embed="' + encodeURIComponent(info.embedUrl) + '" data-is-tall="' + (info.isTall ? '1' : '0') + '" data-is-self="' + (info.provider === 'self' ? '1' : '0') + '">',
					'<div class="wpvg-preview-header">',
						'<div class="wpvg-preview-header-left">',
							'<span class="wpvg-preview-platform-badge ' + info.provider + '">' + info.label + '</span>',
							'<span class="wpvg-preview-label">Preview — click ▶ to play</span>',
						'</div>',
						'<button type="button" class="wpvg-preview-close" title="Close preview">✕</button>',
					'</div>',
					'<div class="wpvg-preview-facade' + tallClass + '">',
						thumbHtml,
						'<div class="wpvg-preview-facade-overlay">',
							'<div class="wpvg-preview-play-btn"><span class="dashicons dashicons-controls-play"></span></div>',
						'</div>',
						'<span class="wpvg-preview-facade-caption">Click to play</span>',
					'</div>',
				'</div>'
			].join('');
		}

		/**
		 * Load Vimeo thumbnail via oEmbed and update the facade image.
		 */
		function wpvgLoadVimeoThumb($wrap, vimId) {
			$.getJSON('https://vimeo.com/api/v2/video/' + vimId + '.json', function(data) {
				if (data && data[0] && data[0].thumbnail_large) {
					var thumb = data[0].thumbnail_large;
					$wrap.find('.wpvg-preview-facade-thumb').replaceWith('<img src="' + thumb + '" class="wpvg-preview-facade-thumb" alt="Vimeo thumbnail">');
				}
			});
		}

		/**
		 * Render or refresh a preview panel inside a video card.
		 */
		function wpvgRenderPreview($card, url) {
			var info = wpvgDetectVideo(url);
			if (!info) {
				$card.find('.wpvg-preview-wrap').remove();
				return;
			}

			// Replace existing preview if any
			$card.find('.wpvg-preview-wrap').remove();

			var html = wpvgBuildPreviewHtml(info);
			$card.find('.wpvg-video-url-input').closest('.wpvg-form-field').append(html);

			// Load Vimeo thumbnail asynchronously
			if (info.provider === 'vimeo' && info.vimId) {
				wpvgLoadVimeoThumb($card.find('.wpvg-preview-wrap'), info.vimId);
			}
		}


		/* ── Source type info: placeholder, description, example ───────────── */
		var wpvgSourceInfo = {
			youtube: {
				placeholder: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				example:     'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				desc:        '📺 Paste a YouTube video link. Supports standard, Shorts & live URLs.',
				tip:         'Example: <code>https://www.youtube.com/watch?v=dQw4w9WgXcQ</code>'
			},
			tiktok: {
				placeholder: 'https://www.tiktok.com/@username/video/7123456789012345678',
				example:     'https://www.tiktok.com/@username/video/7123456789012345678',
				desc:        '🎵 Paste a TikTok video page URL. Vertical 9:16 format — great for fashion & lifestyle.',
				tip:         'Example: <code>https://www.tiktok.com/@username/video/7123456789012345678</code>'
			},
			facebook: {
				placeholder: 'https://www.facebook.com/watch/?v=123456789',
				example:     'https://www.facebook.com/watch/?v=123456789',
				desc:        '📘 Paste a Facebook video watch URL. The video must be set to Public.',
				tip:         'Example: <code>https://www.facebook.com/watch/?v=123456789</code>'
			},
			vimeo: {
				placeholder: 'https://vimeo.com/123456789',
				example:     'https://vimeo.com/123456789',
				desc:        '🎬 Paste a Vimeo video URL. Supports standard and private video links.',
				tip:         'Example: <code>https://vimeo.com/123456789</code>'
			},
			self_hosted: {
				placeholder: '/wp-content/uploads/videos/product-demo.mp4',
				example:     '/wp-content/uploads/videos/product-demo.mp4',
				desc:        '📁 Upload or paste a direct link to an MP4 / WebM / OGG video file. Use "Select from Media Library" to pick from WordPress uploads.',
				tip:         'Example: <code>/wp-content/uploads/videos/demo.mp4</code>'
			},
			iframe: {
				placeholder: 'https://example.com/embed/video-id',
				example:     'https://example.com/embed/video-id',
				desc:        '🔗 Paste any embeddable video URL (Dailymotion, Wistia, Loom, Streamable, etc.).',
				tip:         'Example: <code>https://example.com/embed/video-id</code>'
			}
		};

		/**
		 * Update placeholder, description and tip when source type changes.
		 */
		function wpvgGetCardSourceType($card) {
			var $checkedRadio = $card.find('.wpvg-video-type-radio:checked');
			if ($checkedRadio.length) {
				return $checkedRadio.val() || 'youtube';
			}
			return $card.find('select.wpvg-video-type-select').val() || 'youtube';
		}

		function wpvgUpdateSourceUI($card, sourceType) {
			var info = wpvgSourceInfo[sourceType] || wpvgSourceInfo['iframe'];
			var $urlInput = $card.find('.wpvg-video-url-input');
			var $desc     = $card.find('.wpvg-field-desc');
			var $tip      = $card.find('.wpvg-source-tip');
			var $mediaBtn = $card.find('.wpvg-btn-select-media');

			// Update placeholder
			$urlInput.attr('placeholder', info.placeholder);
			if ($mediaBtn.length) {
				$mediaBtn.toggle(sourceType === 'self_hosted');
			}

			// Update description
			$desc.text(info.desc);

			// Insert or update tip box
			if (!$tip.length) {
				$tip = $('<div class="wpvg-source-tip"></div>');
				$desc.after($tip);
			}
			$tip.html(
				'<span class="wpvg-tip-icon dashicons dashicons-info"></span>' +
				'<span>' + info.tip + '</span>'
			);
		}

		// ── Preview button click: render/refresh preview ──────────────────────
		$(document).on('click', '.wpvg-btn-preview-video', function(e) {
			e.preventDefault();
			var $card = $(this).closest('.wpvg-video-item-card');
			var url = $card.find('.wpvg-video-url-input').val().trim();
			if (!url) {
				$(this).after('<span class="wpvg-preview-error" style="color:#f87171;font-size:11px;margin-left:8px;">Please enter a video URL first.</span>');
				setTimeout(function() { $('.wpvg-preview-error').fadeOut(300, function(){ $(this).remove(); }); }, 2500);
				return;
			}
			$('.wpvg-preview-error').remove();
			wpvgRenderPreview($card, url);
		});

		// ── Facade click: switch from thumbnail to live player ────────────────
		$(document).on('click', '.wpvg-preview-facade', function() {
			var $facade = $(this);
			var $wrap   = $facade.closest('.wpvg-preview-wrap');
			var embedUrl   = decodeURIComponent($wrap.data('embed'));
			var isTall     = $wrap.data('is-tall') === 1 || $wrap.data('is-tall') === '1';
			var isSelf     = $wrap.data('is-self') === 1 || $wrap.data('is-self') === '1';
			var provider   = $wrap.data('provider');
			var ratioClass = isTall ? ' ratio-9-16' : '';

			var playerHtml;
			if (isSelf) {
				playerHtml = '<div class="wpvg-preview-player' + ratioClass + '"><video src="' + embedUrl + '" autoplay controls style="position:absolute;inset:0;width:100%;height:100%;"></video></div>';
			} else {
				var iframeSandbox = provider === 'tiktok' ? '' : ' sandbox="allow-scripts allow-same-origin allow-presentation"';
				playerHtml = '<div class="wpvg-preview-player' + ratioClass + '"><div class="wpvg-preview-loading"><div class="wpvg-preview-spinner"></div> Loading…</div><iframe src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen' + iframeSandbox + ' onload="this.previousElementSibling.style.display=\'none\';"></iframe></div>';
			}

			$facade.replaceWith(playerHtml);
		});

		// ── Close preview panel ────────────────────────────────────────────────
		$(document).on('click', '.wpvg-preview-close', function(e) {
			e.stopPropagation();
			$(this).closest('.wpvg-preview-wrap').slideUp(200, function() { $(this).remove(); });
		});

		// ── Auto-preview for existing saved cards (on page load) ──────────────
		$('.wpvg-video-item-card').each(function() {
			var $card = $(this);
			var sourceType = wpvgGetCardSourceType($card);
			wpvgUpdateSourceUI($card, sourceType);
			var url = $card.find('.wpvg-video-url-input').val().trim();
			if (url) {
				wpvgRenderPreview($card, url);
			}
		});

		// Zoom Magnifier conditional settings visibility
		function toggleZoomSettingsRows() {
			const zoomEnabled = $('#wpvg_show_zoom').is(':checked');
			const zoomType = $('#wpvg_zoom_type').val();

			if (!zoomEnabled) {
				$('.wpvg-zoom-sub-setting').hide();
				return;
			}

			$('.wpvg-zoom-sub-setting').show();
			if (zoomType === 'lens') {
				$('.wpvg-lens-row').show();
				$('.wpvg-window-row').hide();
			} else if (zoomType === 'window') {
				$('.wpvg-lens-row').hide();
				$('.wpvg-window-row').show();
			} else {
				// Inner zoom
				$('.wpvg-lens-row').hide();
				$('.wpvg-window-row').hide();
			}
		}

		$('#wpvg_show_zoom, #wpvg_zoom_type').on('change', toggleZoomSettingsRows);
		toggleZoomSettingsRows();

		// 2. Color Picker Initialization
		if ($.fn.wpColorPicker) {
			$('.wpvg-color-picker').wpColorPicker();
		}

		// 2b. Professional Custom CSS editor (WordPress CodeMirror)
		var wpvgCssEditorInstance = null;
		function wpvgInitCustomCssEditor() {
			var $field = $('#wpvg_custom_css');
			if (!$field.length || typeof wp === 'undefined' || !wp.codeEditor) {
				return;
			}
			if ($field.data('wpvg-cm-ready')) {
				return;
			}
			var settings = (typeof wpvg_admin_data !== 'undefined' && wpvg_admin_data.css_editor)
				? wpvg_admin_data.css_editor
				: null;
			if (!settings) {
				return;
			}
			wpvgCssEditorInstance = wp.codeEditor.initialize($field[0], settings);
			$field.data('wpvg-cm-ready', true);
			if (wpvgCssEditorInstance && wpvgCssEditorInstance.codemirror) {
				wpvgCssEditorInstance.codemirror.setSize(null, 280);
				setTimeout(function() {
					wpvgCssEditorInstance.codemirror.refresh();
				}, 50);
			}
		}
		wpvgInitCustomCssEditor();

		// Refresh editor when Gallery Layout tab becomes visible (CodeMirror needs layout).
		$(document).on('click', '.wpvg-nav-tab[href="#tab-general"]', function() {
			setTimeout(function() {
				if (wpvgCssEditorInstance && wpvgCssEditorInstance.codemirror) {
					wpvgCssEditorInstance.codemirror.refresh();
				}
			}, 80);
		});

		// Multi-video Add/Remove lives in wpvg-admin-pro.js (@fs_premium_only).

		// 5. Video Source Change (select or free radio)
		$(document).on('change', '.wpvg-video-type-select, .wpvg-video-type-radio', function() {
			const type = $(this).val();
			const $card = $(this).closest('.wpvg-video-item-card');
			if ($(this).is('.wpvg-video-type-radio')) {
				$card.find('.wpvg-video-source-radio').removeClass('is-selected');
				$(this).closest('.wpvg-video-source-radio').addClass('is-selected');
			}
			wpvgUpdateSourceUI($card, type);

			if (type === 'self_hosted') {
				$card.find('.wpvg-btn-select-media').show();
			} else {
				$card.find('.wpvg-btn-select-media').hide();
			}
		});

		// 6. Media Library: Select Self-Hosted Video
		$(document).on('click', '.wpvg-btn-select-media', function(e) {
			e.preventDefault();
			const $card = $(this).closest('.wpvg-video-item-card');

			const mediaFrame = wp.media({
				title: 'Select Video',
				library: { type: 'video' },
				multiple: false,
				button: { text: 'Use Video' }
			});

			mediaFrame.on('select', function() {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				$card.find('.wpvg-video-url-input').val(attachment.url);
				$card.find('.wpvg-video-id-input').val(attachment.id);
			});

			mediaFrame.open();
		});

		// 7. Media Library: Select Custom Thumbnail
		$(document).on('click', '.wpvg-btn-select-thumb', function(e) {
			e.preventDefault();
			const $card = $(this).closest('.wpvg-video-item-card');

			const imageFrame = wp.media({
				title: 'Select Video Thumbnail Image',
				library: { type: 'image' },
				multiple: false,
				button: { text: 'Use Thumbnail' }
			});

			imageFrame.on('select', function() {
				const attachment = imageFrame.state().get('selection').first().toJSON();
				$card.find('.wpvg-thumb-id-input').val(attachment.id);
				$card.find('.wpvg-thumb-img-preview').attr('src', attachment.url).show();
				$card.find('.wpvg-btn-remove-thumb').show();
			});

			imageFrame.open();
		});

		// 8. Remove Custom Thumbnail
		$(document).on('click', '.wpvg-btn-remove-thumb', function(e) {
			e.preventDefault();
			const $card = $(this).closest('.wpvg-video-item-card');
			$card.find('.wpvg-thumb-id-input').val('');
			$card.find('.wpvg-thumb-img-preview').attr('src', '').hide();
			$(this).hide();
		});

		function wpvgSetCardSourceType($card, type) {
			var $radio = $card.find('.wpvg-video-type-radio[value="' + type + '"]');
			if ($radio.length) {
				$radio.prop('checked', true).trigger('change');
				return;
			}
			var $select = $card.find('select.wpvg-video-type-select');
			if ($select.length) {
				$select.val(type).trigger('change');
			}
		}

		// 9. Auto-detect video source from URL input & show preview
		$(document).on('blur', '.wpvg-video-url-input', function() {
			const url = $(this).val().trim();
			const $card = $(this).closest('.wpvg-video-item-card');

			if (url.indexOf('youtu') !== -1) {
				wpvgSetCardSourceType($card, 'youtube');
			} else if (url.indexOf('vimeo') !== -1) {
				wpvgSetCardSourceType($card, 'vimeo');
			} else if (url.indexOf('tiktok') !== -1) {
				wpvgSetCardSourceType($card, 'tiktok');
			} else if (url.indexOf('facebook.com') !== -1 || url.indexOf('fb.watch') !== -1) {
				wpvgSetCardSourceType($card, 'facebook');
			} else if (url.match(/\.(mp4|webm|ogg|mov)(\?.*)?$/i)) {
				wpvgSetCardSourceType($card, 'self_hosted');
			}

			// Auto-render preview when user finishes typing a URL
			if (url) {
				wpvgRenderPreview($card, url);
			}
		});

		// 10. Variation Video: Media Library select & auto-detect
		$(document).on('change', '.wpvg-variation-video-type-select', function() {
			const type = $(this).val();
			const $section = $(this).closest('.wpvg-variation-video-section');
			if (type === 'self_hosted') {
				$section.find('.wpvg-btn-select-variation-media').show();
			} else {
				$section.find('.wpvg-btn-select-variation-media').hide();
			}
		});

		$(document).on('click', '.wpvg-btn-select-variation-media', function(e) {
			e.preventDefault();
			const $section = $(this).closest('.wpvg-variation-video-section');

			const mediaFrame = wp.media({
				title: 'Select Variation Video',
				library: { type: 'video' },
				multiple: false,
				button: { text: 'Use Video' }
			});

			mediaFrame.on('select', function() {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				$section.find('.wpvg-variation-video-url-input').val(attachment.url);
			});

			mediaFrame.open();
		});

		$(document).on('blur', '.wpvg-variation-video-url-input', function() {
			const url = $(this).val();
			const $section = $(this).closest('.wpvg-variation-video-section');
			const $select = $section.find('.wpvg-variation-video-type-select');

			if (url.indexOf('youtu') !== -1) {
				$select.val('youtube').trigger('change');
			} else if (url.indexOf('vimeo') !== -1) {
				$select.val('vimeo').trigger('change');
			} else if (url.match(/\.(mp4|webm|ogg)$/i)) {
				$select.val('self_hosted').trigger('change');
			}
		});

		// 11. Metabox Sub-Tabs Switcher
		$(document).on('click', '.wpvg-metabox-tab', function(e) {
			e.preventDefault();
			const targetId = $(this).attr('data-tab');

			$('.wpvg-metabox-tab').removeClass('active');
			$(this).addClass('active');

			$('.wpvg-metabox-tab-content').removeClass('active');
			$('#' + targetId).addClass('active');
		});

		// 12. Toggle Custom Product Gallery Override Fields
		$(document).on('change', '#wpvg_override_gallery', function() {
			if ($(this).val() === 'custom') {
				$('.wpvg-product-override-fields').slideDown(200);
			} else {
				$('.wpvg-product-override-fields').slideUp(200);
			}
		});

		/* ============================================================
		   13. 3D Product Model Media Selectors & Live Preview [PRO]
		   ============================================================ */

		function wpvgRender3DAdminPreview(url) {
			const $previewBox = $('.wpvg-3d-admin-preview-box');
			const $stage = $('.wpvg-3d-admin-model-stage');

			if (!url || !url.trim()) {
				$stage.empty();
				$previewBox.slideUp(150);
				return;
			}

			url = url.trim();
			$stage.html('<model-viewer src="' + url + '" auto-rotate camera-controls shadow-intensity="1" style="width: 100%; height: 100%;" class="wpvg-admin-model-viewer"></model-viewer>');
			$previewBox.slideDown(200);
		}

		// Select/Upload 3D Model File (.glb, .gltf)
		$(document).on('click', '.wpvg-btn-select-3d-file', function(e) {
			e.preventDefault();

			const fileFrame = wp.media({
				title: 'Select or Upload 3D Model File (.glb, .gltf)',
				multiple: false,
				button: { text: 'Use 3D Model' }
			});

			fileFrame.on('select', function() {
				const attachment = fileFrame.state().get('selection').first().toJSON();
				$('#_wpvg_3d_model_url, #wpvg_3d_model_url, .wpvg-3d-model-input, input[name="_wpvg_3d_model_url"]').val(attachment.url).trigger('input').trigger('change');
				$('#_wpvg_3d_model_id, #wpvg_3d_model_id, input[name="_wpvg_3d_model_id"]').val(attachment.id);
				$('.wpvg-btn-clear-3d-model').show();
				wpvgRender3DAdminPreview(attachment.url);
			});

			fileFrame.open();
		});

		// Use Sample 3D Model in Product Metabox
		$(document).on('click', '.wpvg-btn-sample-3d-product', function(e) {
			e.preventDefault();
			const sampleUrl = $(this).attr('data-sample-url');
			if (sampleUrl) {
				$('#_wpvg_3d_model_url, #wpvg_3d_model_url, .wpvg-3d-model-input, input[name="_wpvg_3d_model_url"]').val(sampleUrl).trigger('input').trigger('change');
				$('#_wpvg_3d_model_id, #wpvg_3d_model_id, input[name="_wpvg_3d_model_id"]').val('');
				$('.wpvg-btn-clear-3d-model').show();
				wpvgRender3DAdminPreview(sampleUrl);
			}
		});

		// Use Sample 3D Model in Global Settings
		$(document).on('click', '.wpvg-btn-use-sample-model', function(e) {
			e.preventDefault();
			const sampleUrl = $(this).attr('data-sample-url');
			if (sampleUrl) {
				$('#wpvg_3d_default_model_url, #_wpvg_3d_default_model_url, input[name="wpvg_3d_default_model_url"]').val(sampleUrl).trigger('input').trigger('change');
			}
		});

		// Manual typing or paste into 3D Model input
		$(document).on('input blur change', '#_wpvg_3d_model_url, #wpvg_3d_model_url, .wpvg-3d-model-input, input[name="_wpvg_3d_model_url"]', function() {
			const url = $(this).val();
			if (url) {
				$('.wpvg-btn-clear-3d-model').show();
				wpvgRender3DAdminPreview(url);
			} else {
				$('.wpvg-btn-clear-3d-model').hide();
				wpvgRender3DAdminPreview('');
			}
		});

		// Clear 3D Model
		$(document).on('click', '.wpvg-btn-clear-3d-model', function(e) {
			e.preventDefault();
			$('#_wpvg_3d_model_url, #wpvg_3d_model_url, .wpvg-3d-model-input, input[name="_wpvg_3d_model_url"]').val('').trigger('input').trigger('change');
			$('#_wpvg_3d_model_id, #wpvg_3d_model_id, input[name="_wpvg_3d_model_id"]').val('');
			wpvgRender3DAdminPreview('');
			$(this).hide();
		});

		// Select Custom 3D Poster Image
		$(document).on('click', '.wpvg-btn-select-3d-poster', function(e) {
			e.preventDefault();

			const posterFrame = wp.media({
				title: 'Select 3D Poster Image',
				library: { type: 'image' },
				multiple: false,
				button: { text: 'Use Poster Image' }
			});

			posterFrame.on('select', function() {
				const attachment = posterFrame.state().get('selection').first().toJSON();
				$('#_wpvg_3d_poster_id, #wpvg_3d_poster_id').val(attachment.id);
				$('#wpvg_3d_poster_preview, #_wpvg_3d_poster_preview').attr('src', attachment.url).show();
				$('.wpvg-btn-remove-3d-poster').show();
			});

			posterFrame.open();
		});

		// Remove Custom 3D Poster Image
		$(document).on('click', '.wpvg-btn-remove-3d-poster', function(e) {
			e.preventDefault();
			$('#_wpvg_3d_poster_id, #wpvg_3d_poster_id, input[name="_wpvg_3d_poster_id"]').val('');
			$('#wpvg_3d_poster_preview, #_wpvg_3d_poster_preview').attr('src', '').hide();
			$(this).hide();
		});

		// Capture 3D Snapshot from Live Preview as Poster Image
		$(document).on('click', '.wpvg-btn-capture-3d-snapshot', async function(e) {
			e.preventDefault();

			const $btn = $(this);
			const $modelViewer = $('.wpvg-3d-admin-model-stage model-viewer');

			if (!$modelViewer.length || !$modelViewer[0]) {
				alert('Please load a 3D model first before capturing a snapshot.');
				return;
			}

			const mv = $modelViewer[0];
			const $status = $('.wpvg-snapshot-status');

			$btn.prop('disabled', true).css('opacity', '0.7');
			$status.css({ 'color': '#2563eb', 'display': 'inline-block' }).text('📸 Capturing snapshot...');

			try {
				// Pause rotation momentarily to get a sharp capture
				const originalAutoRotate = mv.autoRotate;
				mv.autoRotate = false;

				// Google model-viewer toDataURL
				let dataUrl = '';
				if (typeof mv.toDataURL === 'function') {
					dataUrl = await mv.toDataURL('image/png');
				}

				if (!dataUrl) {
					// Fallback to shadowRoot canvas capture if available
					const canvas = mv.shadowRoot ? mv.shadowRoot.querySelector('canvas') : null;
					if (canvas && typeof canvas.toDataURL === 'function') {
						dataUrl = canvas.toDataURL('image/png');
					}
				}

				mv.autoRotate = originalAutoRotate;

				if (!dataUrl) {
					throw new Error('Unable to extract snapshot from 3D model viewer.');
				}

				// Instantly preview captured image in the poster box
				$('#wpvg_3d_poster_preview, #_wpvg_3d_poster_preview').attr('src', dataUrl).show();
				$('.wpvg-btn-remove-3d-poster').show();

				$status.text('💾 Saving snapshot to Media Library...');

				// Send AJAX request to save into WordPress Media Library
				const ajaxUrl = (typeof wpvg_admin_data !== 'undefined' && wpvg_admin_data.ajax_url) ? wpvg_admin_data.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
				const nonce = (typeof wpvg_admin_data !== 'undefined' && wpvg_admin_data.nonce) ? wpvg_admin_data.nonce : '';
				const productId = (typeof wpvg_admin_data !== 'undefined' && wpvg_admin_data.post_id) ? wpvg_admin_data.post_id : $('#post_ID').val();

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpvg_capture_3d_snapshot',
						nonce: nonce,
						product_id: productId,
						image_data: dataUrl
					},
					success: function(res) {
						$btn.prop('disabled', false).css('opacity', '1');
						if (res && res.success && res.data) {
							$('#_wpvg_3d_poster_id, #wpvg_3d_poster_id, input[name="_wpvg_3d_poster_id"]').val(res.data.attachment_id);
							if (res.data.image_url) {
								$('#wpvg_3d_poster_preview, #_wpvg_3d_poster_preview').attr('src', res.data.image_url).show();
							}
							$status.css('color', '#16a34a').text('✅ Snapshot set as 3D Poster!');
							setTimeout(function() {
								$status.fadeOut(400);
							}, 4000);
						} else {
							$status.css('color', '#dc2626').text(res && res.data && res.data.message ? res.data.message : 'Error saving snapshot.');
						}
					},
					error: function() {
						$btn.prop('disabled', false).css('opacity', '1');
						$status.css('color', '#dc2626').text('Server error saving snapshot.');
					}
				});

			} catch (err) {
				console.error('[WPVG 3D Snapshot Error]:', err);
				$btn.prop('disabled', false).css('opacity', '1');
				$status.css('color', '#dc2626').text('Capture error: ' + (err.message || 'Could not capture snapshot.'));
			}
		});

		/* ============================================================
		   14. 3D conditional show/hide UI (Metabox & Global Settings)
		   ============================================================ */
		function wpvgToggle3DExclusiveUI() {
			const $card = $('.wpvg-3d-metabox-card');
			const isMetabox = $('#_wpvg_3d_source_type').length > 0;
			const isGlobalSettings = $('#wpvg_3d_enable_global').length > 0;

			// --- A. Single Product Metabox (Tab 4) ---
			if (isMetabox) {
				const enableVal = ($('#_wpvg_3d_enable').val() || 'default').toString();
				const sourceVal = ($('#_wpvg_3d_source_type').val() || 'default').toString();
				const viewVal = ($('#_wpvg_3d_view_mode').val() || 'default').toString();

				const globalSource = ($card.data('global-source') || $('#wpvg_3d_source_type').val() || 'auto').toString();
				const globalView = ($card.data('global-view') || $('#wpvg_3d_view_mode').val() || 'switcher').toString();

				const effSource = (sourceVal === 'default') ? globalSource : sourceVal;
				const effView = (viewVal === 'default') ? globalView : viewVal;

				const is3DDisabled = (enableVal === 'no');
				const is2DOnly = (effView === '2d_only');
				const isCommonActive = (!is3DDisabled && !is2DOnly);
				const isSwitcherActive = (isCommonActive && effView === 'switcher');

				let is360Active = false;
				let isModelActive = false;

				if (isCommonActive) {
					if (effSource === 'model_file') {
						isModelActive = true;
					} else if (effSource === 'gallery_360') {
						is360Active = true;
					} else {
						// auto
						if (effView === 'switcher') {
							is360Active = true;
						} else {
							isModelActive = true;
						}
					}
				}

				// Row 2 & 3: Source Engine & View Mode
				$('.wpvg-3d-row-source, .wpvg-3d-row-view-mode').toggle(!is3DDisabled);

				// Row 4: Default Active View (only when switcher is active)
				$('.wpvg-3d-row-default-view').toggle(isSwitcherActive);

				// Row 5: Live 360° Gallery Image Turntable Preview
				$('.wpvg-3d-360-preview-row').toggle(is360Active);

				// Row 6 & 7: 360° Spin Speed Limit & Drag Sensitivity
				$('.wpvg-3d-360-spin-settings').toggle(is360Active);

				// Row 8: 3D Model File (.glb / .gltf)
				$('.wpvg-3d-model-file-row').toggle(isModelActive);

				// Row 9: Custom 3D Poster Image
				$('.wpvg-3d-poster-row').toggle(isModelActive);

				// Row 10: Gallery Placement
				$('.wpvg-3d-placement-row').toggle(isCommonActive);

				// Row 11: Auto-Rotate
				$('.wpvg-3d-autorotate-row').toggle(isCommonActive);

				// Row 12: Button Text Override
				$('.wpvg-3d-btn-text-row').toggle(isCommonActive);

				// Row 13: Thumbnails Scroll & Autoplay
				$('.wpvg-3d-autoplay-row').toggle(isCommonActive);

				// Row 14: Modal Thumbnails Size (px)
				$('.wpvg-3d-modal-tsize-row').toggle(isCommonActive);

				// Notice on conflicting metabox tabs when 3D is enabled for this product
				const d3On = (enableVal === 'yes');
				$('.wpvg-3d-conflict-note').remove();
				if (d3On) {
					const note = '<div class="wpvg-3d-conflict-note" style="background:#fef3c7;border-left:4px solid #d97706;border-radius:6px;padding:10px 12px;margin:0 0 14px;font-size:12.5px;color:#92400e;">' +
						'<strong>3D Product Model is Enabled:</strong> Product Videos, Custom Gallery Style, and Shop Archive Video are automatically OFF on the frontend for this product.' +
						'</div>';
					$('#wpvg-mb-tab-videos, #wpvg-mb-tab-style, #wpvg-mb-tab-archive').prepend(note);
				}
			}

			// --- B. Global Settings (Tab 6) ---
			if (isGlobalSettings) {
				const gEnable = ($('#wpvg_3d_enable_global').val() || 'per_product').toString();
				const gSource = ($('#wpvg_3d_source_type').val() || 'auto').toString();
				const gView = ($('#wpvg_3d_view_mode').val() || 'switcher').toString();

				const isGlobalDisabled = (gEnable === 'disabled');
				const isGlobal2DOnly = (gView === '2d_only');
				const isGlobalCommonActive = (!isGlobalDisabled && !isGlobal2DOnly);
				const isGlobalSwitcherActive = (isGlobalCommonActive && gView === 'switcher');

				let isGlobal360Active = false;
				let isGlobalModelActive = false;

				if (isGlobalCommonActive) {
					if (gSource === 'model_file') {
						isGlobalModelActive = true;
					} else if (gSource === 'gallery_360') {
						isGlobal360Active = true;
					} else {
						// auto
						if (gView === 'switcher') {
							isGlobal360Active = true;
						} else {
							isGlobalModelActive = true;
						}
					}
				}

				// Scope !== disabled
				$('tr.wpvg-3d-global-source-row, tr.wpvg-3d-global-view-row').toggle(!isGlobalDisabled);

				// Switcher default view
				$('tr.wpvg-3d-global-default-view-row').toggle(isGlobalSwitcherActive);

				// 360 Spin Speed, Sensitivity, Direction
				$('tr.wpvg-3d-global-360-row').toggle(isGlobal360Active);

				// 3D Model File URL
				$('tr.wpvg-3d-global-model-row').toggle(isGlobalModelActive);

				// Common global settings (Loading, Overlay text, Auto-rotate, Camera, Badge, Lightbox, Bg, Autoplay, Thumb size)
				$('tr.wpvg-3d-global-common-row').toggle(isGlobalCommonActive);
			}
		}

		$(document).on('change', '#_wpvg_3d_source_type, #_wpvg_3d_view_mode, #_wpvg_3d_enable, #wpvg_3d_enable_global, #wpvg_3d_source_type, #wpvg_3d_view_mode', wpvgToggle3DExclusiveUI);
		wpvgToggle3DExclusiveUI();

		// 360 Admin Turntable Preview Box
		function wpvgInitAdmin360Preview() {
			var box = document.querySelector('.wpvg-admin-360-preview-box');
			if (!box) return;

			var rawFrames = box.getAttribute('data-frames');
			if (!rawFrames) return;

			var frames = [];
			try {
				frames = JSON.parse(rawFrames);
			} catch (e) {
				return;
			}
			if (!frames || !frames.length) return;

			var img = document.getElementById('wpvg-admin-360-preview-img');
			var slider = document.getElementById('wpvg-admin-360-slider');
			var ind = document.getElementById('wpvg-admin-360-frame-indicator');
			var stage = document.querySelector('.wpvg-admin-360-stage');
			var playBtn = document.getElementById('wpvg-admin-360-autoplay-btn');
			if (!img || !slider || !stage) return;

			var spinMs = Math.max(120, parseInt(box.getAttribute('data-spin-ms'), 10) || 220);
			var sens = Math.max(16, parseInt(box.getAttribute('data-spin-sens'), 10) || 30);
			var dir = (box.getAttribute('data-spin-dir') === 'reverse') ? 1 : -1;
			var maxVel = 0.55;
			var floatFrame = 0, targetFrame = 0, curIdx = -1;
			var isDragging = false, startX = 0, dragFrame = 0;
			var velocity = 0, lastX = 0, lastT = 0, isAuto = false, accumMs = 0;

			frames.forEach(function(src){ var i = new Image(); i.src = src; });

			function render(idx) {
				var n = ((idx % frames.length) + frames.length) % frames.length;
				if (n === curIdx) return;
				curIdx = n;
				img.src = frames[curIdx];
				slider.value = curIdx;
				if (ind) ind.textContent = (curIdx + 1) + ' / ' + frames.length;
			}

			function loop(now) {
				var elapsed = Math.min(40, now - (loop._ts || now));
				var dt = elapsed / 16.67;
				loop._ts = now;
				if (isDragging) {
					floatFrame += (targetFrame - floatFrame) * Math.min(1, 0.28 * dt);
					render(Math.round(floatFrame));
				} else if (Math.abs(velocity) > 0.004) {
					var v = Math.max(-maxVel, Math.min(maxVel, velocity));
					floatFrame += v * dt;
					velocity = v * Math.pow(0.88, dt);
					render(Math.round(floatFrame));
					if (Math.abs(velocity) <= 0.004) velocity = 0;
				} else if (isAuto) {
					accumMs += elapsed;
					while (accumMs >= spinMs) {
						accumMs -= spinMs;
						floatFrame += (-dir);
					}
					targetFrame = floatFrame;
					render(Math.round(floatFrame));
				}
				requestAnimationFrame(loop);
			}
			requestAnimationFrame(loop);

			slider.addEventListener('input', function() {
				isAuto = false; velocity = 0;
				floatFrame = parseInt(this.value, 10); targetFrame = floatFrame; render(floatFrame);
				if (playBtn) playBtn.innerHTML = '<span class="dashicons dashicons-controls-play" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> Auto Spin';
			});

			stage.addEventListener('pointerdown', function(e) {
				isDragging = true; isAuto = false; velocity = 0;
				startX = e.clientX; lastX = startX; lastT = performance.now();
				dragFrame = floatFrame; targetFrame = floatFrame;
				stage.style.cursor = 'grabbing';
				try { stage.setPointerCapture(e.pointerId); } catch (err) {}
				if (playBtn) playBtn.innerHTML = '<span class="dashicons dashicons-controls-play" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> Auto Spin';
			});
			stage.addEventListener('pointermove', function(e) {
				if (!isDragging) return;
				targetFrame = dragFrame + ((e.clientX - startX) / sens) * dir;
				var now = performance.now(), dt = Math.max(1, now - lastT);
				var inst = ((e.clientX - lastX) / dt) * (16.67 / sens) * dir;
				velocity = Math.max(-maxVel, Math.min(maxVel, velocity * 0.55 + inst * 0.45));
				lastX = e.clientX; lastT = now;
			});
			stage.addEventListener('pointerup', function() {
				isDragging = false; stage.style.cursor = 'grab';
				if (Math.abs(velocity) < 0.035) velocity = 0;
			});

			if (playBtn) {
				playBtn.addEventListener('click', function() {
					if (isAuto) {
						isAuto = false;
						playBtn.innerHTML = '<span class="dashicons dashicons-controls-play" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> Auto Spin';
					} else {
						isAuto = true; velocity = 0; accumMs = 0;
						playBtn.innerHTML = '<span class="dashicons dashicons-controls-pause" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> Pause';
					}
				});
			}
			render(0);
		}

		wpvgInitAdmin360Preview();
	});

})(jQuery);
