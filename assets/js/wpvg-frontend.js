/**
 * Nexura Product Video Media Gallery for WooCommerce - Frontend Controller
 * Premium Pan-Zoom (Hover & Click), Split Lightbox Modal & Mosaic Grid
 * Built natively with jQuery - Zero bloated dependencies, 100% WordPress.org compliant
 *
 * @package Product_Video_Showcase_For_WooCommerce
 */

(function($) {
	'use strict';

	class WPVGGallery {
		constructor($element) {
			this.$gallery = $element;
			this.layout = this.$gallery.data('layout') || 'vertical_left';
			this.isGrid = (this.layout === 'grid_mosaic' || this.layout === 'grid_2col' || this.layout === 'grid_1col');
			this.zoomEnabled = (this.$gallery.data('zoom-enabled') !== 'no');
			this.zoomType = this.$gallery.attr('data-zoom-type') || this.$gallery.data('zoom-type') || 'inner';
			this.zoomMode = this.$gallery.data('zoom-mode') || 'both';
			this.zoomLevel = parseFloat(this.$gallery.data('zoom-level')) || 2.2;
			this.lensShape = this.$gallery.attr('data-lens-shape') || this.$gallery.data('lens-shape') || 'circle';
			this.lensSize = parseInt(this.$gallery.attr('data-lens-size') || this.$gallery.data('lens-size'), 10) || 180;
			this.windowPos = this.$gallery.attr('data-window-pos') || this.$gallery.data('window-pos') || 'right';
			this.windowWidth = parseInt(this.$gallery.attr('data-window-width') || this.$gallery.data('window-width'), 10) || 450;
			this.windowHeight = parseInt(this.$gallery.attr('data-window-height') || this.$gallery.data('window-height'), 10) || 450;
			this.lightboxEnabled = (this.$gallery.data('lightbox-enabled') !== 'no');
			const videoAutoplayAttr = this.$gallery.attr('data-video-autoplay') || this.$gallery.data('video-autoplay');
			this.videoAutoplay = (videoAutoplayAttr === 'yes' || videoAutoplayAttr === true || videoAutoplayAttr === '1');
			const sliderAutoplayAttr = this.$gallery.attr('data-slider-autoplay') || this.$gallery.data('slider-autoplay');
			this.sliderAutoplay = (sliderAutoplayAttr === 'yes' || sliderAutoplayAttr === true || sliderAutoplayAttr === '1');
			this.sliderTimer = null;
			const speedAttr = parseInt(this.$gallery.attr('data-slider-speed') || this.$gallery.data('slider-speed'), 10);
			this.autoplayDelay = (speedAttr && speedAttr >= 500) ? speedAttr : 3500;
			this.videoLoop = (this.$gallery.attr('data-video-loop') || this.$gallery.data('video-loop')) === 'yes';
			this.videoMute = (this.$gallery.attr('data-video-mute') || this.$gallery.data('video-mute')) === 'muted';
			this.isHovered = false;
			this.thumbsToShow = parseInt(this.$gallery.data('thumbs-to-show'), 10) || 4;
			this.mobileBreakpoint = parseInt(this.$gallery.attr('data-mobile-breakpoint') || this.$gallery.data('mobile-breakpoint'), 10) || 768;
			const mobZoomAttr = this.$gallery.attr('data-mobile-disable-zoom') || this.$gallery.data('mobile-disable-zoom');
			this.mobileDisableZoom = (mobZoomAttr === 'yes' || mobZoomAttr === true || mobZoomAttr === '1');

			// Read structured slide data from JSON data attribute
			const rawSlides = this.$gallery.attr('data-slides');
			try {
				this.slides = rawSlides ? JSON.parse(rawSlides) : [];
			} catch (e) {
				this.slides = [];
			}

			// DOM Elements
			this.$slides = this.$gallery.find('.wpvg-slide-item');
			this.$thumbs = this.$gallery.find('.wpvg-thumb-item');
			this.$dots = this.$gallery.find('.wpvg-dot');
			this.$thumbList = this.$gallery.find('.wpvg-thumbnails-list');
			this.$gridItems = this.$gallery.find('.wpvg-grid-item');
			this.totalSlides = this.slides.length > 0 ? this.slides.length : (this.isGrid ? this.$gridItems.length : this.$slides.length);
			this.currentIndex = 0;

			// Fullscreen Lightbox Modal references (scoped to gallery or product)
			const prodId = this.$gallery.attr('data-product-id');
			if (prodId && $('.wpvg-lightbox-modal[data-product-id="' + prodId + '"]').length) {
				this.$modal = $('.wpvg-lightbox-modal[data-product-id="' + prodId + '"]').first();
			} else if (this.$gallery.nextAll('.wpvg-lightbox-modal').length) {
				this.$modal = this.$gallery.nextAll('.wpvg-lightbox-modal').first();
			} else if (this.$gallery.siblings('.wpvg-lightbox-modal').length) {
				this.$modal = this.$gallery.siblings('.wpvg-lightbox-modal').first();
			} else if (this.$gallery.parent().find('.wpvg-lightbox-modal').length) {
				this.$modal = this.$gallery.parent().find('.wpvg-lightbox-modal').first();
			} else {
				this.$modal = $('#wpvg-lightbox-modal');
			}
			this.$modalSidebar = this.$modal.find('.wpvg-modal-sidebar');
			this.$modalStage = this.$modal.find('.wpvg-modal-stage');
			this.$modalCounter = this.$modal.find('.wpvg-modal-counter');
			this.$modalThumbs = this.$modal.find('.wpvg-modal-thumb-item, .wpvg-modal-thumb');

			// Mobile touch tracking
			this.touchStartX = 0;
			this.touchStartY = 0;
			this.touchEndX = 0;

			this.zoomIconSvg = '<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>';
			this.playIconSvg = '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="rgba(0,0,0,0.6)" stroke="#fff" stroke-width="1.5"/><polygon points="10,8 16,12 10,16" fill="#fff"/></svg>';

			this.init();
		}

		init() {
			if (this.totalSlides === 0) return;

			this.bindEvents();

			if (!this.isGrid) {
				this.goToSlide(0, false);
				this.checkThumbnailScrollOverflow();
				if (this.sliderAutoplay) {
					this.startSliderAutoplay();
				}
			}

			// Init Pan-Zoom on on-page images
			if (this.zoomEnabled) {
				this.initPanZoom(this.$gallery.find('.wpvg-zoomable-container'), this.zoomLevel, this.zoomMode);
			}

			const self = this;
			$(window).on('resize', function() {
				if (!self.isGrid) {
					self.checkThumbnailScrollOverflow();
				}
			});
		}

		/**
		 * Interactive Magnifier & Pan-Zoom Engine
		 * Supports: Inner Zoom, Lens Magnifier (Circle/Square), and Amazon-Style Side Window Zoom
		 *
		 * @param {jQuery} $containers
		 * @param {number} scaleFactor
		 * @param {string} mode 'hover', 'click', or 'both'
		 */
		initPanZoom($containers, scaleFactor, mode) {
			const self = this;

			$containers.each(function() {
				const $container = $(this);
				const $img = $container.find('img');
				const isModalZoom = $container.hasClass('wpvg-modal-zoom-container');

				// Reset existing events to prevent multiple bindings
				$container.off('mouseenter mousemove mouseleave click');

				// Skip on-page hover/touch zoom on mobile if disabled in settings
				if (!isModalZoom && self.mobileDisableZoom && window.innerWidth <= self.mobileBreakpoint) {
					return;
				}

				// If inside the Fullscreen Lightbox Modal, always use split-view Pan-Zoom
				if (isModalZoom) {
					// 1. CLICK TO ZOOM inside modal
					if (mode === 'click' || mode === 'both') {
						$container.on('click', function(e) {
							if ($(e.target).closest('.wpvg-nav-btn, .wpvg-modal-close-btn').length) {
								return;
							}

							const isCurrentlyZoomed = $container.hasClass('wpvg-is-zoomed');

							if (isCurrentlyZoomed) {
								$container.removeClass('wpvg-is-zoomed wpvg-is-zooming');
								$img.css({
									'transform': 'scale(1)',
									'transform-origin': 'center center',
									'transition': 'transform 0.25s ease-out'
								});
							} else {
								$container.addClass('wpvg-is-zoomed');
								const rect = this.getBoundingClientRect();
								let xPercent = 50;
								let yPercent = 50;

								if (rect.width > 0 && rect.height > 0) {
									xPercent = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
									yPercent = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
								}

								$img.css({
									'transform-origin': xPercent + '% ' + yPercent + '%',
									'transform': 'scale(' + scaleFactor + ')',
									'transition': 'transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94)'
								});
							}
						});
					}

					// 2. MOUSE HOVER inside modal
					if (mode === 'hover' || mode === 'both') {
						$container.on('mouseenter', function() {
							if (!$container.hasClass('wpvg-is-zoomed')) {
								$container.addClass('wpvg-is-zooming');
								$img.css({
									'transform': 'scale(' + scaleFactor + ')',
									'transition': 'transform 0.15s ease-out'
								});
							}
						});

						$container.on('mouseleave', function() {
							if (!$container.hasClass('wpvg-is-zoomed')) {
								$container.removeClass('wpvg-is-zooming');
								$img.css({
									'transform': 'scale(1)',
									'transform-origin': 'center center',
									'transition': 'transform 0.25s ease-out'
								});
							}
						});
					}

					// 3. MOUSE MOVE inside modal
					$container.on('mousemove', function(e) {
						if ($container.hasClass('wpvg-is-zooming') || $container.hasClass('wpvg-is-zoomed')) {
							const rect = this.getBoundingClientRect();
							if (rect.width <= 0 || rect.height <= 0) return;

							const xPercent = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
							const yPercent = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));

							$img.css({
								'transform-origin': xPercent + '% ' + yPercent + '%',
								'transform': 'scale(' + scaleFactor + ')'
							});
						}
					});

					return;
				}

				// ==========================================
				// ON-PAGE MAGNIFIER (Inner, Lens, or Window)
				// ==========================================
				const zoomType = self.zoomType || 'inner';

				// ------------------------------------------
				// MODE 1: LENS ZOOM (Magnifying Glass)
				// ------------------------------------------
				if (zoomType === 'lens') {
					let $lens = $container.find('.wpvg-zoom-lens');
					if ($lens.length === 0) {
						$lens = $('<div class="wpvg-zoom-lens wpvg-shape-' + self.lensShape + '"></div>');
						$container.append($lens);
					}
					$lens.css({
						width: self.lensSize + 'px',
						height: self.lensSize + 'px'
					});

					$container.on('mouseenter', function() {
						const fullSrc = $img.attr('data-full-src') || $img.attr('src');
						$lens.css('background-image', 'url("' + fullSrc + '")');
						$lens.addClass('wpvg-active');
					});

					$container.on('mouseleave', function() {
						$lens.removeClass('wpvg-active');
					});

					$container.on('mousemove', function(e) {
						const rect = this.getBoundingClientRect();
						if (rect.width <= 0 || rect.height <= 0) return;

						const x = e.clientX - rect.left;
						const y = e.clientY - rect.top;
						const halfLens = self.lensSize / 2;

						let lensX = x - halfLens;
						let lensY = y - halfLens;

						if (lensX < 0) lensX = 0;
						if (lensY < 0) lensY = 0;
						if (lensX > rect.width - self.lensSize) lensX = rect.width - self.lensSize;
						if (lensY > rect.height - self.lensSize) lensY = rect.height - self.lensSize;

						const bgW = rect.width * scaleFactor;
						const bgH = rect.height * scaleFactor;
						const bgX = -(x * scaleFactor - halfLens);
						const bgY = -(y * scaleFactor - halfLens);

						$lens.css({
							left: lensX + 'px',
							top: lensY + 'px',
							'background-size': bgW + 'px ' + bgH + 'px',
							'background-position': bgX + 'px ' + bgY + 'px'
						});
					});

					// Clicking opens lightbox if enabled
					if (self.lightboxEnabled) {
						$container.on('click', function(e) {
							if ($(e.target).closest('.wpvg-zoom-trigger').length) return;
							self.openModal(self.currentIndex);
						});
					}
				}

				// ------------------------------------------
				// MODE 2: SIDE WINDOW ZOOM (Amazon-Style)
				// ------------------------------------------
				else if (zoomType === 'window') {
					let $tracker = $container.find('.wpvg-zoom-tracker');
					if ($tracker.length === 0) {
						$tracker = $('<div class="wpvg-zoom-tracker"></div>');
						$container.append($tracker);
					}

					let $window = self.$gallery.find('.wpvg-zoom-window');
					if ($window.length === 0) {
						$window = $('<div class="wpvg-zoom-window wpvg-pos-' + self.windowPos + '"><img src="" alt="Zoom preview"></div>');
						self.$gallery.append($window);
					}
					const $windowImg = $window.find('img');

					// Responsive check: if side window would overflow viewport, adapt position
					let activeWindowPos = self.windowPos;
					const galleryRect = self.$gallery[0].getBoundingClientRect();
					if (activeWindowPos === 'right' && (window.innerWidth - galleryRect.right) < (self.windowWidth + 30)) {
						if (galleryRect.left > (self.windowWidth + 30)) {
							activeWindowPos = 'left';
						}
					}
					$window.removeClass('wpvg-pos-right wpvg-pos-left').addClass('wpvg-pos-' + activeWindowPos);

					$window.css({
						width: self.windowWidth + 'px',
						height: self.windowHeight + 'px'
					});

					$container.on('mouseenter', function() {
						const fullSrc = $img.attr('data-full-src') || $img.attr('src');
						$windowImg.attr('src', fullSrc);
						$tracker.addClass('wpvg-active');
						$window.addClass('wpvg-active');
					});

					$container.on('mouseleave', function() {
						$tracker.removeClass('wpvg-active');
						$window.removeClass('wpvg-active');
					});

					$container.on('mousemove', function(e) {
						const rect = this.getBoundingClientRect();
						if (rect.width <= 0 || rect.height <= 0) return;

						const x = e.clientX - rect.left;
						const y = e.clientY - rect.top;

						const trackerW = Math.min(rect.width, self.windowWidth / scaleFactor);
						const trackerH = Math.min(rect.height, self.windowHeight / scaleFactor);

						let tX = x - trackerW / 2;
						let tY = y - trackerH / 2;

						if (tX < 0) tX = 0;
						if (tY < 0) tY = 0;
						if (tX > rect.width - trackerW) tX = rect.width - trackerW;
						if (tY > rect.height - trackerH) tY = rect.height - trackerH;

						$tracker.css({
							width: trackerW + 'px',
							height: trackerH + 'px',
							left: tX + 'px',
							top: tY + 'px'
						});

						const imgW = rect.width * scaleFactor;
						const imgH = rect.height * scaleFactor;
						const imgLeft = -(tX * scaleFactor);
						const imgTop = -(tY * scaleFactor);

						$windowImg.css({
							width: imgW + 'px',
							height: imgH + 'px',
							left: imgLeft + 'px',
							top: imgTop + 'px'
						});
					});

					// Clicking opens lightbox if enabled
					if (self.lightboxEnabled) {
						$container.on('click', function(e) {
							if ($(e.target).closest('.wpvg-zoom-trigger').length) return;
							self.openModal(self.currentIndex);
						});
					}
				}

				// ------------------------------------------
				// MODE 3: INNER ZOOM (Default Smooth Pan-Zoom)
				// ------------------------------------------
				else {
					// Click to Zoom (if Lightbox is disabled or click mode requested)
					if ((mode === 'click' || mode === 'both') && !self.lightboxEnabled) {
						$container.on('click', function(e) {
							if ($(e.target).closest('.wpvg-zoom-trigger').length) return;

							const isCurrentlyZoomed = $container.hasClass('wpvg-is-zoomed');

							if (isCurrentlyZoomed) {
								$container.removeClass('wpvg-is-zoomed wpvg-is-zooming');
								$img.css({
									'transform': 'scale(1)',
									'transform-origin': 'center center',
									'transition': 'transform 0.25s ease-out'
								});
							} else {
								$container.addClass('wpvg-is-zoomed');
								const rect = this.getBoundingClientRect();
								let xPercent = 50;
								let yPercent = 50;

								if (rect.width > 0 && rect.height > 0) {
									xPercent = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
									yPercent = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
								}

								$img.css({
									'transform-origin': xPercent + '% ' + yPercent + '%',
									'transform': 'scale(' + scaleFactor + ')',
									'transition': 'transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94)'
								});
							}
						});
					}

					// Mouse hover pan-zoom
					if (mode === 'hover' || mode === 'both') {
						$container.on('mouseenter', function() {
							if (!$container.hasClass('wpvg-is-zoomed')) {
								$container.addClass('wpvg-is-zooming');
								$img.css({
									'transform': 'scale(' + scaleFactor + ')',
									'transition': 'transform 0.15s ease-out'
								});
							}
						});

						$container.on('mouseleave', function() {
							if (!$container.hasClass('wpvg-is-zoomed')) {
								$container.removeClass('wpvg-is-zooming');
								$img.css({
									'transform': 'scale(1)',
									'transform-origin': 'center center',
									'transition': 'transform 0.25s ease-out'
								});
							}
						});
					}

					// Mouse move tracking
					$container.on('mousemove', function(e) {
						if ($container.hasClass('wpvg-is-zooming') || $container.hasClass('wpvg-is-zoomed')) {
							const rect = this.getBoundingClientRect();
							if (rect.width <= 0 || rect.height <= 0) return;

							const xPercent = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
							const yPercent = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));

							$img.css({
								'transform-origin': xPercent + '% ' + yPercent + '%',
								'transform': 'scale(' + scaleFactor + ')'
							});
						}
					});
				}
			});
		}

		checkThumbnailScrollOverflow() {
			if (this.$thumbList.length === 0) return;
			const isVertical = this.$gallery.hasClass('wpvg-layout-vertical-left') || this.$gallery.hasClass('wpvg-layout-vertical-right');
			if (isVertical) {
				const canScroll = this.$thumbList[0].scrollHeight > (this.$thumbList.innerHeight() + 5);
				this.$gallery.find('.wpvg-thumb-scroll-btn').toggle(canScroll);
			} else {
				const canScroll = this.$thumbList[0].scrollWidth > (this.$thumbList.innerWidth() + 5);
				this.$gallery.find('.wpvg-thumb-scroll-btn').toggle(canScroll);
			}
		}

		bindEvents() {
			const self = this;

			this.$gallery.on('click', '.wpvg-video-play-btn, .wpvg-video-facade', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const $slide = $(this).closest('.wpvg-slide-item, .wpvg-grid-item');
				const index = parseInt($slide.attr('data-index'), 10);
				if (!isNaN(index) && !self.isGrid) {
					self.goToSlide(index, true, true);
				}
				self.hydrateFacade($slide.length ? $slide : self.$gallery, true);
			});

			// ============================
			// 1. Slider Events
			// ============================
			if (!this.isGrid) {
				// Hover pause / resume for slider autoplay
				this.$gallery.on('mouseenter', function() {
					self.isHovered = true;
				}).on('mouseleave', function() {
					self.isHovered = false;
				});

				// Thumbnail click on product page
				this.$thumbs.on('click', function(e) {
					e.preventDefault();
					const rawIdx = $(this).attr('data-index');
					const index = parseInt(rawIdx !== undefined ? rawIdx : $(this).data('index'), 10);
					if (!isNaN(index)) {
						self.goToSlide(index, true, true);
						if (self.sliderAutoplay) {
							self.startSliderAutoplay();
						}
					}
				});

				// Slider Next / Prev navigation
				this.$gallery.find('.wpvg-nav-prev').on('click', function(e) {
					e.preventDefault();
					self.prevSlide(true);
				});

				this.$gallery.find('.wpvg-nav-next').on('click', function(e) {
					e.preventDefault();
					self.nextSlide(true);
				});

				// Mobile Dots click
				this.$dots.on('click', function() {
					const index = parseInt($(this).data('index'), 10);
					self.goToSlide(index, true, true);
				});

				// Thumbnail Scroll buttons (Vertical & Horizontal)
				this.$gallery.find('.wpvg-thumb-scroll-up').on('click', function(e) {
					e.preventDefault();
					const step = (self.$thumbs.first().outerHeight() || 72) + 8;
					self.$thumbList.animate({ scrollTop: self.$thumbList.scrollTop() - step }, 200, function() {
						self.checkThumbnailScrollOverflow();
					});
				});

				this.$gallery.find('.wpvg-thumb-scroll-down').on('click', function(e) {
					e.preventDefault();
					const step = (self.$thumbs.first().outerHeight() || 72) + 8;
					self.$thumbList.animate({ scrollTop: self.$thumbList.scrollTop() + step }, 200, function() {
						self.checkThumbnailScrollOverflow();
					});
				});

				this.$gallery.find('.wpvg-thumb-scroll-left').on('click', function(e) {
					e.preventDefault();
					const step = (self.$thumbs.first().outerWidth() || 72) + 8;
					self.$thumbList.animate({ scrollLeft: self.$thumbList.scrollLeft() - step }, 200, function() {
						self.checkThumbnailScrollOverflow();
					});
				});

				this.$gallery.find('.wpvg-thumb-scroll-right').on('click', function(e) {
					e.preventDefault();
					const step = (self.$thumbs.first().outerWidth() || 72) + 8;
					self.$thumbList.animate({ scrollLeft: self.$thumbList.scrollLeft() + step }, 200, function() {
						self.checkThumbnailScrollOverflow();
					});
				});

				// Manual scroll listener to update button states
				this.$thumbList.on('scroll', function() {
					self.checkThumbnailScrollOverflow();
				});

				// Touch swipe on mobile with passive event listeners for 60fps scrolling
				const stageElem = this.$gallery.find('.wpvg-slider-stage')[0];
				if (stageElem) {
					stageElem.addEventListener('touchstart', function(e) {
						if (e.touches && e.touches[0]) {
							self.touchStartX = e.touches[0].clientX;
							self.touchStartY = e.touches[0].clientY;
						}
					}, { passive: true });

					stageElem.addEventListener('touchend', function(e) {
						if (e.changedTouches && e.changedTouches[0]) {
							self.touchEndX = e.changedTouches[0].clientX;
							const touchEndY = e.changedTouches[0].clientY;
							const diffX = self.touchStartX - self.touchEndX;
							const diffY = self.touchStartY - touchEndY;

							if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY)) {
								if (diffX > 0) {
									self.nextSlide(true);
								} else {
									self.prevSlide(true);
								}
							}
						}
					}, { passive: true });
				}

				// Open modal on image click or zoom trigger
				if (this.lightboxEnabled) {
					this.$gallery.on('click', '.wpvg-zoom-trigger, .wpvg-slide-item.wpvg-slide-type-image, .wpvg-slide-item .wpvg-zoomable-container', function(e) {
						if ($(e.target).closest('.wpvg-nav-btn, .wpvg-thumb-scroll-btn, video, iframe, .wpvg-video-facade, .wpvg-video-play-btn').length) {
							return;
						}
						e.preventDefault();
						e.stopPropagation();
						self.openModal(self.currentIndex);
					});
				}
			}

			// ============================
			// 2. Grid Events
			// ============================
			if (this.isGrid && this.lightboxEnabled) {
				this.$gallery.on('click', '.wpvg-grid-item', function(e) {
					if ($(e.target).closest('video, iframe').length) {
						return;
					}
					const rawIdx = $(this).attr('data-index');
					const index = parseInt(rawIdx, 10);
					if (!isNaN(index)) {
						e.preventDefault();
						e.stopPropagation();
						self.openModal(index);
					}
				});
			}

			// ============================
			// 3. Fullscreen Lightbox Modal Events
			// ============================
			if (this.lightboxEnabled && this.$modal.length > 0) {
				// Close button
				this.$modal.find('.wpvg-modal-close-btn').on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					self.closeModal();
				});

				// Click backdrop to close
				this.$modal.on('click', function(e) {
					if ($(e.target).hasClass('wpvg-lightbox-modal')) {
						self.closeModal();
					} else if (!self.$modal.hasClass('wpvg-modal-card') && ($(e.target).hasClass('wpvg-modal-main') || $(e.target).hasClass('wpvg-modal-stage'))) {
						self.closeModal();
					}
				});

				// Prev / Next inside modal
				this.$modal.find('.wpvg-modal-prev').on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					self.modalPrevSlide();
				});

				this.$modal.find('.wpvg-modal-next').on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					self.modalNextSlide();
				});

				// Mobile touch swipe inside modal stage
				let modalTouchStartX = 0;
				let modalTouchStartY = 0;
				const modalStageElem = this.$modal.find('.wpvg-modal-stage')[0];
				if (modalStageElem) {
					modalStageElem.addEventListener('touchstart', function(e) {
						if (e.touches && e.touches[0]) {
							modalTouchStartX = e.touches[0].clientX;
							modalTouchStartY = e.touches[0].clientY;
						}
					}, { passive: true });

					modalStageElem.addEventListener('touchend', function(e) {
						if (e.changedTouches && e.changedTouches[0]) {
							const diffX = modalTouchStartX - e.changedTouches[0].clientX;
							const diffY = modalTouchStartY - e.changedTouches[0].clientY;
							if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY)) {
								if (diffX > 0) {
									self.modalNextSlide();
								} else {
									self.modalPrevSlide();
								}
							}
						}
					}, { passive: true });
				}

				// CLICK ON ANY THUMBNAIL IN THE SIDE PANEL (Delegated)
				this.$modal.on('click', '.wpvg-modal-thumb-item, .wpvg-modal-thumb', function(e) {
					e.preventDefault();
					e.stopPropagation();
					const $thumb = $(this);
					const rawIdx = $thumb.attr('data-index');
					const index = parseInt(rawIdx, 10);

					if (!isNaN(index)) {
						self.modalGoToSlide(index);
					}
				});

				// Keyboard navigation (Escape, Left, Right)
				$(document).on('keydown', function(e) {
					if (self.$modal.hasClass('wpvg-modal-open')) {
						if (e.key === 'Escape') {
							self.closeModal();
						} else if (e.key === 'ArrowLeft') {
							self.modalPrevSlide();
						} else if (e.key === 'ArrowRight') {
							self.modalNextSlide();
						}
					}
				});
			}

			// ============================
			// 4. WooCommerce Variation Support
			// ============================
			$('form.variations_form').on('found_variation', function(event, variation) {
				self.handleVariation(variation);
			});

			$('form.variations_form').on('reset_data', function() {
				self.resetVariation();
			});
		}

		goToSlide(index, pausePrev = true, isUserAction = false) {
			if (index < 0 || index >= this.totalSlides) return;

			if (pausePrev && this.currentIndex !== index) {
				this.pauseVideoInSlide(this.currentIndex);
			}

			this.currentIndex = index;

			// Reset any active lens/window magnifier
			this.$gallery.find('.wpvg-zoom-lens, .wpvg-zoom-tracker, .wpvg-zoom-window').removeClass('wpvg-active');

			// Prefer data-index so 3D insertion order never desyncs thumb ↔ slide
			this.$slides.removeClass('wpvg-active-slide');
			let $activeSlide = this.$slides.filter('[data-index="' + index + '"]');
			if (!$activeSlide.length) {
				$activeSlide = this.$slides.eq(index);
			}
			$activeSlide.addClass('wpvg-active-slide');

			// Keep 2D/3D CSS view state in sync (otherwise thumbs "click" but main stage stays on 3D)
			if (this.$gallery.attr('data-has-3d') === 'yes') {
				if ($activeSlide.hasClass('wpvg-slide-type-3d')) {
					this.$gallery.attr('data-active-view', '3d');
				} else {
					this.$gallery.attr('data-active-view', '2d');
				}
			}

			// If active slide is 3D Model, ensure it wakes up and auto-rotates
			if ($activeSlide.hasClass('wpvg-slide-type-3d')) {
				const mv = $activeSlide.find('model-viewer')[0];
				if (mv) {
					$activeSlide.find('.wpvg-3d-poster-wrap').addClass('wpvg-hidden');
					$activeSlide.find('.wpvg-3d-canvas-wrap').addClass('wpvg-ready');
					const isAutoRotate = ($activeSlide.data('auto-rotate') === 'yes' || $activeSlide.attr('data-auto-rotate') === 'yes' || (window.wpvg_3d_data && window.wpvg_3d_data.autoRotate));
					if (isAutoRotate) {
						mv.autoRotate = true;
						mv.autoRotateDelay = 0;
					}
					if (typeof mv.dismissPoster === 'function') {
						try { mv.dismissPoster(); } catch(e) {}
					}
				}
				$(document).trigger('wpvg_slide_changed_3d', { slide: $activeSlide, index: index });
			}

			// Update thumbnail states
			this.$thumbs.removeClass('wpvg-active-thumb');
			const $activeThumb = this.$thumbs.filter('[data-index="' + index + '"]');
			if ($activeThumb.length) {
				$activeThumb.addClass('wpvg-active-thumb');
			} else {
				this.$thumbs.eq(index).addClass('wpvg-active-thumb');
			}

			// Update mobile dots
			this.$dots.removeClass('wpvg-active-dot');
			const $activeDot = this.$dots.filter('[data-index="' + index + '"]');
			if ($activeDot.length) {
				$activeDot.addClass('wpvg-active-dot');
			} else {
				this.$dots.eq(index).addClass('wpvg-active-dot');
			}

			// Auto-scroll thumbnails if necessary
			this.scrollThumbnailIntoView(index);

			// If video autoplay setting is enabled, start playback
			const isVideoSlide = $activeSlide.hasClass('wpvg-slide-type-video');
			if (this.videoAutoplay && isVideoSlide) {
				this.playVideoInSlide(index);
			}
		}

		nextSlide(isUserAction = false) {
			let nextIndex = this.currentIndex + 1;
			if (nextIndex >= this.totalSlides) nextIndex = 0;
			this.goToSlide(nextIndex, true, isUserAction);
			if (isUserAction && this.sliderAutoplay) {
				this.startSliderAutoplay();
			}
		}

		prevSlide(isUserAction = false) {
			let prevIndex = this.currentIndex - 1;
			if (prevIndex < 0) prevIndex = this.totalSlides - 1;
			this.goToSlide(prevIndex, true, isUserAction);
			if (isUserAction && this.sliderAutoplay) {
				this.startSliderAutoplay();
			}
		}

		startSliderAutoplay() {
			if (!this.sliderAutoplay || this.isGrid || this.totalSlides <= 1) return;
			this.stopSliderAutoplay();
			this.sliderTimer = setInterval(() => {
				if (this.isHovered || $('body').hasClass('wpvg-modal-open')) {
					return;
				}

				// Do not advance if the current slide is a video slide
				const $currentSlide = this.$slides.eq(this.currentIndex);
				if ($currentSlide.hasClass('wpvg-slide-type-video')) {
					return;
				}
				const $nativeVideo = $currentSlide.find('video');
				if ($nativeVideo.length && !$nativeVideo[0].paused) {
					return;
				}

				// Do not advance if user is actively interacting or dragging 3D turntable / model
				if ($('.wpvg-is-dragging').length || $('.wpvg-360-turntable-container.wpvg-is-dragging').length) {
					return;
				}

				this.nextSlide(false);
			}, this.autoplayDelay);
		}

		stopSliderAutoplay() {
			if (this.sliderTimer) {
				clearInterval(this.sliderTimer);
				this.sliderTimer = null;
			}
		}

		scrollThumbnailIntoView(index) {
			const $thumb = this.$thumbs.eq(index);
			if ($thumb.length === 0) return;

			const isVertical = this.$gallery.hasClass('wpvg-layout-vertical-left') || this.$gallery.hasClass('wpvg-layout-vertical-right');

			if (isVertical) {
				const containerTop = this.$thumbList.scrollTop();
				const containerHeight = this.$thumbList.innerHeight();
				const thumbTop = $thumb.position().top + containerTop;
				const thumbHeight = $thumb.outerHeight();

				if (thumbTop < containerTop) {
					this.$thumbList.animate({ scrollTop: thumbTop }, 200, () => this.checkThumbnailScrollOverflow());
				} else if (thumbTop + thumbHeight > containerTop + containerHeight) {
					this.$thumbList.animate({ scrollTop: thumbTop + thumbHeight - containerHeight }, 200, () => this.checkThumbnailScrollOverflow());
				} else {
					this.checkThumbnailScrollOverflow();
				}
			} else {
				const containerLeft = this.$thumbList.scrollLeft();
				const containerWidth = this.$thumbList.innerWidth();
				const thumbLeft = $thumb.position().left + containerLeft;
				const thumbWidth = $thumb.outerWidth();

				if (thumbLeft < containerLeft) {
					this.$thumbList.animate({ scrollLeft: thumbLeft }, 200, () => this.checkThumbnailScrollOverflow());
				} else if (thumbLeft + thumbWidth > containerLeft + containerWidth) {
					this.$thumbList.animate({ scrollLeft: thumbLeft + thumbWidth - containerWidth }, 200, () => this.checkThumbnailScrollOverflow());
				} else {
					this.checkThumbnailScrollOverflow();
				}
			}
		}

		checkThumbnailScrollOverflow() {
			if (this.isGrid || this.totalSlides <= this.thumbsToShow) {
				this.$gallery.find('.wpvg-thumb-scroll-btn').hide();
				return;
			}

			this.$gallery.find('.wpvg-thumb-scroll-btn').show();

			const isVertical = this.$gallery.hasClass('wpvg-layout-vertical-left') || this.$gallery.hasClass('wpvg-layout-vertical-right');

			if (isVertical) {
				const scrollTop = this.$thumbList.scrollTop();
				const scrollHeight = this.$thumbList[0] ? this.$thumbList[0].scrollHeight : 0;
				const clientHeight = this.$thumbList.innerHeight();

				const $btnUp = this.$gallery.find('.wpvg-thumb-scroll-up');
				const $btnDown = this.$gallery.find('.wpvg-thumb-scroll-down');

				$btnUp.prop('disabled', scrollTop <= 2).toggleClass('wpvg-disabled', scrollTop <= 2);
				$btnDown.prop('disabled', scrollTop + clientHeight >= scrollHeight - 2).toggleClass('wpvg-disabled', scrollTop + clientHeight >= scrollHeight - 2);
			} else {
				const scrollLeft = this.$thumbList.scrollLeft();
				const scrollWidth = this.$thumbList[0] ? this.$thumbList[0].scrollWidth : 0;
				const clientWidth = this.$thumbList.innerWidth();

				const $btnLeft = this.$gallery.find('.wpvg-thumb-scroll-left');
				const $btnRight = this.$gallery.find('.wpvg-thumb-scroll-right');

				$btnLeft.prop('disabled', scrollLeft <= 2).toggleClass('wpvg-disabled', scrollLeft <= 2);
				$btnRight.prop('disabled', scrollLeft + clientWidth >= scrollWidth - 2).toggleClass('wpvg-disabled', scrollLeft + clientWidth >= scrollWidth - 2);
			}
		}

		hydrateFacade($scope, autoplay) {
			const $facade = $scope.find('.wpvg-video-facade').first();
			if (!$facade.length || $facade.hasClass('is-playing') || $facade.hasClass('is-loading')) {
				return $scope.find('iframe, video').first();
			}

			let src = $facade.attr('data-src') || '';
			if (!src) {
				return $();
			}

			const provider = $facade.attr('data-provider') || '';
			const orientation = $facade.attr('data-orientation') || '';

			// Browsers block unmuted autoplay → black iframe. Always mute when autoplaying.
			if (autoplay && provider !== 'tiktok' && provider !== 'facebook') {
				if (src.indexOf('autoplay=1') === -1) {
					src += (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
				}
				if ((provider === 'youtube' || src.indexOf('youtube.com') !== -1 || src.indexOf('youtube-nocookie.com') !== -1) && src.indexOf('mute=') === -1) {
					src += '&mute=1';
				}
				if (provider === 'vimeo' && src.indexOf('muted=') === -1) {
					src += '&muted=1';
				}
			}

			const iframe = document.createElement('iframe');
			iframe.src = src;
			iframe.setAttribute('title', 'Product video');
			iframe.setAttribute('allowfullscreen', 'true');
			iframe.setAttribute('loading', 'eager');
			iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
			if (orientation) {
				iframe.setAttribute('data-orientation', orientation);
			}

			if (provider === 'tiktok') {
				iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
			} else if (provider === 'facebook') {
				iframe.setAttribute('allow', 'autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share');
			} else if (provider === 'vimeo') {
				iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
			} else {
				iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
			}

			// Keep poster visible until the iframe has loaded (prevents black flash / empty stage).
			$facade.addClass('is-loading').append(iframe);
			const revealPlayer = function() {
				$facade.removeClass('is-loading').addClass('is-playing');
			};
			iframe.addEventListener('load', function() {
				setTimeout(revealPlayer, 120);
			});
			setTimeout(revealPlayer, 2500);

			return $(iframe);
		}

		pauseVideoInSlide(index) {
			const $slide = this.$slides.eq(index);
			if ($slide.length === 0) return;

			const $ytIframe = $slide.find('iframe[src*="youtube.com"], iframe[src*="youtube-nocookie.com"]');
			if ($ytIframe.length) {
				try {
					$ytIframe[0].contentWindow.postMessage(JSON.stringify({
						event: 'command',
						func: 'pauseVideo'
					}), '*');
				} catch (e) {}
			}

			const $vimeoIframe = $slide.find('iframe[src*="vimeo.com"]');
			if ($vimeoIframe.length) {
				try {
					$vimeoIframe[0].contentWindow.postMessage(JSON.stringify({
						method: 'pause'
					}), '*');
				} catch (e) {}
			}

			const $nativeVideo = $slide.find('video');
			if ($nativeVideo.length && !$nativeVideo[0].paused) {
				$nativeVideo[0].pause();
			}
		}

		playVideoInSlide(index, isUserAction = false) {
			const $slide = this.$slides.eq(index);
			if ($slide.length === 0) return;

			this.hydrateFacade($slide, true);

			// 1. YouTube Player
			const $ytIframe = $slide.find('iframe[src*="youtube.com"], iframe[src*="youtube-nocookie.com"], iframe[src*="youtu.be"]');
			if ($ytIframe.length) {
				let src = $ytIframe.attr('src') || '';
				if (src.indexOf('autoplay=1') === -1) {
					const delim = src.indexOf('?') !== -1 ? '&' : '?';
					const muteParam = this.videoMute ? '&mute=1' : (isUserAction ? '' : '&mute=1');
					src += delim + 'autoplay=1' + muteParam;
					$ytIframe.attr('src', src);
				} else {
					const sendYtPlay = () => {
						try {
							if (this.videoMute) {
								$ytIframe[0].contentWindow.postMessage(JSON.stringify({
									event: 'command',
									func: 'mute',
									args: ''
								}), '*');
							} else if (isUserAction) {
								$ytIframe[0].contentWindow.postMessage(JSON.stringify({
									event: 'command',
									func: 'unMute',
									args: ''
								}), '*');
							}
							$ytIframe[0].contentWindow.postMessage(JSON.stringify({
								event: 'command',
								func: 'playVideo',
								args: ''
							}), '*');
						} catch (e) {}
					};
					sendYtPlay();
					setTimeout(sendYtPlay, 250);
				}
			}

			// 2. Vimeo Player
			const $vimeoIframe = $slide.find('iframe[src*="vimeo.com"]');
			if ($vimeoIframe.length) {
				let src = $vimeoIframe.attr('src') || '';
				if (src.indexOf('autoplay=1') === -1) {
					const delim = src.indexOf('?') !== -1 ? '&' : '?';
					const vimeoMuteParam = this.videoMute ? '&muted=1' : (isUserAction ? '' : '&muted=1');
					src += delim + 'autoplay=1' + vimeoMuteParam;
					$vimeoIframe.attr('src', src);
				} else {
					const sendVimeoPlay = () => {
						try {
							if (this.videoMute) {
								$vimeoIframe[0].contentWindow.postMessage(JSON.stringify({
									method: 'setVolume',
									value: 0
								}), '*');
							} else if (isUserAction) {
								$vimeoIframe[0].contentWindow.postMessage(JSON.stringify({
									method: 'setVolume',
									value: 1
								}), '*');
							}
							$vimeoIframe[0].contentWindow.postMessage(JSON.stringify({
								method: 'play'
							}), '*');
						} catch (e) {}
					};
					sendVimeoPlay();
					setTimeout(sendVimeoPlay, 250);
				}
			}

			// 3. HTML5 Native Video
			const $nativeVideo = $slide.find('video');
			if ($nativeVideo.length) {
				const vid = $nativeVideo[0];
				vid.loop = this.videoLoop;
				if (this.videoMute) {
					vid.muted = true;
				} else if (isUserAction) {
					vid.muted = false;
				}
				const tryPlay = () => {
					try {
						const playPromise = vid.play();
						if (playPromise !== undefined) {
							playPromise.catch(function() {
								vid.muted = true;
								vid.play().catch(function() {});
							});
						}
					} catch (e) {
						try {
							vid.muted = true;
							vid.play().catch(function() {});
						} catch (err2) {}
					}
				};

				tryPlay();
				if (vid.readyState < 2) {
					$nativeVideo.one('loadeddata canplay', function() {
						tryPlay();
					});
				}
			}
		}

		/* ==========================================================================
		   Fullscreen Split Lightbox Modal Methods
		   ========================================================================== */
		openModal(index) {
			if (!this.$modal || this.$modal.length === 0) {
				const prodId = this.$gallery.attr('data-product-id');
				if (prodId && $('.wpvg-lightbox-modal[data-product-id="' + prodId + '"]').length) {
					this.$modal = $('.wpvg-lightbox-modal[data-product-id="' + prodId + '"]').first();
				} else if (this.$gallery.nextAll('.wpvg-lightbox-modal').length) {
					this.$modal = this.$gallery.nextAll('.wpvg-lightbox-modal').first();
				} else if (this.$gallery.siblings('.wpvg-lightbox-modal').length) {
					this.$modal = this.$gallery.siblings('.wpvg-lightbox-modal').first();
				} else if (this.$gallery.parent().find('.wpvg-lightbox-modal').length) {
					this.$modal = this.$gallery.parent().find('.wpvg-lightbox-modal').first();
				} else {
					this.$modal = $('#wpvg-lightbox-modal');
				}
				this.$modalSidebar = this.$modal.find('.wpvg-modal-sidebar');
				this.$modalStage = this.$modal.find('.wpvg-modal-stage');
				this.$modalCounter = this.$modal.find('.wpvg-modal-counter');
				this.$modalThumbs = this.$modal.find('.wpvg-modal-thumb-item, .wpvg-modal-thumb');
			}
			if (this.$modal.length === 0) return;

			if (this.sliderAutoplay) {
				this.stopSliderAutoplay();
			}

			this.modalGoToSlide(index);
			this.$modal.addClass('wpvg-modal-open').attr('aria-hidden', 'false');
			$('body').css('overflow', 'hidden');
		}

		closeModal() {
			this.$modal.removeClass('wpvg-modal-open').attr('aria-hidden', 'true');
			$('body').css('overflow', '');
			this.$modalStage.find('.wpvg-modal-zoom-container').empty();

			if (this.sliderAutoplay) {
				this.startSliderAutoplay();
			}
		}

		modalGoToSlide(index) {
			if (index < 0 || index >= this.totalSlides) return;

			this.currentIndex = index;

			// 1. Update counter
			this.$modalCounter.text((index + 1) + ' / ' + this.totalSlides);

			// 2. Find the thumbnail element in the side panel
			const $allModalThumbs = this.$modal.find('.wpvg-modal-thumb-item, .wpvg-modal-thumb');
			$allModalThumbs.removeClass('wpvg-active-modal-thumb');
			const $activeThumb = $allModalThumbs.filter('[data-index="' + index + '"]');
			$activeThumb.addClass('wpvg-active-modal-thumb');

			// 3. Scroll active thumbnail into view inside side panel
			if ($activeThumb.length) {
				const $scrollContainer = this.$modal.find('.wpvg-modal-thumbs-scroll');
				if ($scrollContainer.length) {
					const isMobile = window.innerWidth <= this.mobileBreakpoint;
					const isBottom = this.$modal.hasClass('wpvg-modal-pos-bottom') || this.$modal.hasClass('wpvg-modal-card');
					if (isMobile || isBottom) {
						const scrollWidth = $scrollContainer.innerWidth();
						const thumbOffsetLeft = $activeThumb.position().left + $scrollContainer.scrollLeft();
						$scrollContainer.animate({
							scrollLeft: thumbOffsetLeft - (scrollWidth / 2) + ($activeThumb.outerWidth() / 2)
						}, 200);
					} else {
						const scrollHeight = $scrollContainer.innerHeight();
						const thumbOffsetTop = $activeThumb.position().top + $scrollContainer.scrollTop();
						$scrollContainer.animate({
							scrollTop: thumbOffsetTop - (scrollHeight / 2) + ($activeThumb.outerHeight() / 2)
						}, 200);
					}
				}
			}

			// 4. Extract slide details directly from thumbnail attributes
			let fullSrc = $activeThumb.attr('data-full-src');
			let altText = $activeThumb.attr('data-alt') || '';
			let slideType = $activeThumb.attr('data-type') || 'image';
			let embedHtml = $activeThumb.attr('data-embed') || '';

			// Fallback to structured slide array or image element if needed
			if (!fullSrc && this.slides && this.slides[index]) {
				const s = this.slides[index];
				slideType = s.type;
				if (s.type === 'video') {
					embedHtml = s.data.embed_html;
				} else {
					fullSrc = s.data.full_src || s.data.large_src;
					altText = s.data.alt || '';
				}
			}

			if (!fullSrc && slideType !== 'video' && slideType !== '3d') {
				fullSrc = $activeThumb.find('img').attr('src');
			}

			// 5. Render Main Stage
			const $zoomContainer = this.$modal.find('.wpvg-modal-zoom-container');
			$zoomContainer.empty().removeClass('wpvg-is-zoomed wpvg-is-zooming');

			if (slideType === '3d') {
				const modelUrl = $activeThumb.attr('data-model-url') || (this.slides[index] && this.slides[index].data && this.slides[index].data.model_url);
				$(document).trigger('wpvg_modal_slide_changed', {
					slideType: '3d',
					$activeThumb: $activeThumb,
					modelUrl: modelUrl,
					posterUrl: fullSrc
				});
			} else if (slideType === 'video' && embedHtml) {
				let finalHtml = embedHtml;
				if (this.videoAutoplay) {
					if (finalHtml.indexOf('youtube.com') !== -1 || finalHtml.indexOf('youtu.be') !== -1 || finalHtml.indexOf('youtube-nocookie.com') !== -1) {
						finalHtml = finalHtml.replace(/src="([^"]+)"/, function(match, src) {
							if (src.indexOf('autoplay=1') !== -1) {
								return match;
							}
							const delim = src.indexOf('?') !== -1 ? '&' : '?';
							return 'src="' + src + delim + 'autoplay=1&mute=1"';
						}).replace(/data-src="([^"]+)"/, function(match, src) {
							if (src.indexOf('autoplay=1') !== -1) {
								return match;
							}
							const delim = src.indexOf('?') !== -1 ? '&' : '?';
							return 'data-src="' + src + delim + 'autoplay=1&mute=1"';
						});
					} else if (finalHtml.indexOf('vimeo.com') !== -1) {
						finalHtml = finalHtml.replace(/src="([^"]+)"/, function(match, src) {
							if (src.indexOf('autoplay=1') !== -1) {
								return match;
							}
							const delim = src.indexOf('?') !== -1 ? '&' : '?';
							return 'src="' + src + delim + 'autoplay=1&muted=1"';
						}).replace(/data-src="([^"]+)"/, function(match, src) {
							if (src.indexOf('autoplay=1') !== -1) {
								return match;
							}
							const delim = src.indexOf('?') !== -1 ? '&' : '?';
							return 'data-src="' + src + delim + 'autoplay=1&muted=1"';
						});
					} else if (finalHtml.indexOf('<video') !== -1) {
						finalHtml = finalHtml.replace('<video', '<video autoplay');
					}
				}
				const isVertical = finalHtml.indexOf('wpvg-video-orient-vertical') !== -1 || finalHtml.indexOf('data-orientation="vertical"') !== -1;
				const $videoWrap = $('<div class="wpvg-modal-video' + (isVertical ? ' wpvg-modal-video-vertical' : '') + '"></div>').html(finalHtml);
				$zoomContainer.append($videoWrap);
				this.hydrateFacade($videoWrap, true);
			} else if (fullSrc) {
				const $modalImg = $('<img />').attr({
					'src': fullSrc,
					'alt': altText
				});

				const $zoomBtn = $('<button type="button" class="wpvg-zoom-trigger" aria-label="Toggle Zoom">' + this.zoomIconSvg + '</button>');

				$zoomContainer.append($modalImg).append($zoomBtn);

				// Initialize Pan-Zoom inside Modal Canvas
				this.initPanZoom($zoomContainer, this.zoomLevel, this.zoomMode);
			}

			// Keep on-page slider synced if in slider mode
			if (!this.isGrid) {
				this.goToSlide(index, true);
			}
		}

		modalNextSlide() {
			let nextIndex = this.currentIndex + 1;
			if (nextIndex >= this.totalSlides) nextIndex = 0;
			this.modalGoToSlide(nextIndex);
		}

		modalPrevSlide() {
			let prevIndex = this.currentIndex - 1;
			if (prevIndex < 0) prevIndex = this.totalSlides - 1;
			this.modalGoToSlide(prevIndex);
		}

		handleVariation(variation) {
			if (!variation) return;

			// Save initial state if not already cached
			if (!this.savedInitialGallery) {
				this.savedInitialFirstSlideHtml = this.$slides.first().prop('outerHTML');
				this.savedInitialFirstThumbHtml = this.$thumbs.first().prop('outerHTML');
				this.savedInitialGallery = true;
			}

			// Case 1: Variation has a video attached
			if (variation.wpvg_video && variation.wpvg_video.embed_html) {
				const $firstSlide = this.$slides.first();
				const $firstThumb = this.$thumbs.first();

				$firstSlide.removeClass('wpvg-slide-type-image').addClass('wpvg-slide-type-video');
				$firstSlide.html('<div class="wpvg-slide-video">' + variation.wpvg_video.embed_html + '</div>');

				if (variation.wpvg_video.thumb_src) {
					$firstThumb.find('img').attr('src', variation.wpvg_video.thumb_src);
				}
				if (!$firstThumb.find('.wpvg-thumb-video-badge').length) {
					$firstThumb.append('<span class="wpvg-thumb-video-badge">' + this.playIconSvg + '</span>');
				}

				this.goToSlide(0, true, true);
				this.playVideoInSlide(0);
				return;
			}

			// Case 2: Standard variation image
			if (variation.image && variation.image.src) {
				const variationImageSrc = variation.image.src;
				const self = this;
				let matchFound = false;

				if (this.slides && this.slides.length) {
					$.each(this.slides, function(idx, s) {
						if (s.type === 'image' && s.data) {
							if ((s.data.full_src && s.data.full_src.indexOf(variationImageSrc) !== -1) ||
								(s.data.large_src && s.data.large_src.indexOf(variationImageSrc) !== -1) ||
								(s.data.thumb_src && s.data.thumb_src.indexOf(variationImageSrc) !== -1)) {
								self.goToSlide(idx);
								matchFound = true;
								return false;
							}
						}
					});
				}

				if (!matchFound) {
					// Restore original image structure if first slide was previously turned into a video
					if (this.savedInitialFirstSlideHtml) {
						this.$slides.first().replaceWith(this.savedInitialFirstSlideHtml);
						this.$thumbs.first().replaceWith(this.savedInitialFirstThumbHtml);
						this.$slides = this.$gallery.find('.wpvg-slide-item');
						this.$thumbs = this.$gallery.find('.wpvg-thumb-item');
					}

					const $firstImg = self.$slides.first().find('img');
					if ($firstImg.length) {
						$firstImg.attr('src', variationImageSrc).data('full-src', variation.image.full_src || variationImageSrc);
						self.$thumbs.first().find('img').attr('src', variation.image.thumb_src || variationImageSrc);
						if (self.zoomEnabled) {
							self.initPanZoom(self.$slides.first().find('.wpvg-zoomable-container'), self.zoomLevel, self.zoomMode);
						}
						self.goToSlide(0);
					}
				}
			}
		}

		resetVariation() {
			if (this.savedInitialGallery) {
				this.$slides.first().replaceWith(this.savedInitialFirstSlideHtml);
				this.$thumbs.first().replaceWith(this.savedInitialFirstThumbHtml);
				this.$slides = this.$gallery.find('.wpvg-slide-item');
				this.$thumbs = this.$gallery.find('.wpvg-thumb-item');

				if (this.zoomEnabled) {
					this.initPanZoom(this.$slides.first().find('.wpvg-zoomable-container'), this.zoomLevel, this.zoomMode);
				}
			}

			if (!this.isGrid) {
				this.goToSlide(0);
			}
		}
	}

	// =========================================================================
	// Shop Archive & Catalog Video Showcase Controller [PRO]
	// =========================================================================
	class WPVGArchiveVideoController {
		constructor() {
			this.init();
		}

		init() {
			this.ensureThumbWrap();
			this.bindHoverAutoplay();
			this.bindClickLightbox();
			this.bindInlinePlayers();
			this.bindNavDots();
			this.bindTouchSwipe();
			this.bindLinkInterception();
		}

		bindLinkInterception() {
			// Prevent clicks inside archive video player/badge/dots from following parent product link
			$(document).on('click', 'a', function(e) {
				if ($(e.target).closest('.wpvg-archive-card-video').length) {
					if ($(e.target).closest('.wpvg-video-play-btn, .wpvg-video-facade, .wpvg-archive-play-badge, .wpvg-archive-dot, video, iframe, button, .wpvg-archive-style-inline-player').length) {
						e.preventDefault();
					}
				}
			});
		}

		ensureThumbWrap() {
			$('.wpvg-archive-card-video').each(function() {
				const $video = $(this);
				const $parent = $video.parent();
				const $card = $video.closest('li.product, .wc-block-grid__product, .thunk-woo-product-list, .product');

				// Check if the product image is actually inside this wrapper (ignoring video's own poster/facade)
				const hasProductThumb = $parent.find('img, picture, .woocommerce-loop-product__thumbnail, .wp-post-image')
					.not($video.find('*'))
					.not($video)
					.length > 0;

				if (!hasProductThumb && $card.length) {
					// Custom theme (e.g. Shop Mania, Flatsome, WoodMart, Astra, OceanWP, Blocksy, Kadence custom loop)
					const $target = $card.find('.thunk-product-image, .box-image, .image-fade, .product-image, .woodmart-product-image, .product-element-top, .astra-shop-thumbnail-wrap, .woo-entry-image, .woocommerce-loop-product__thumbnail, .product-thumbnail').first();
					if ($target.length) {
						if ($target.is('img')) {
							$target.parent().css('position', 'relative').append($video);
						} else {
							$target.css('position', 'relative').append($video);
						}
						if ($parent.hasClass('wpvg-archive-thumb-wrap') && !$parent.children().not($video).length) {
							$parent.remove();
						}
					} else {
						const $anyImg = $card.find('img.attachment-woocommerce_thumbnail, img.wp-post-image, img')
							.not($video.find('*'))
							.not($video)
							.first();
						if ($anyImg.length) {
							$anyImg.parent().css('position', 'relative').append($video);
							if ($parent.hasClass('wpvg-archive-thumb-wrap') && !$parent.children().not($video).length) {
								$parent.remove();
							}
						}
					}
				} else if (!$parent.hasClass('wpvg-archive-thumb-wrap')) {
					const $img = $video.prev('img, picture, .woocommerce-loop-product__thumbnail, .wp-post-image').not($video.find('*'));
					if ($img.length) {
						$img.add($video).wrapAll('<div class="wpvg-archive-thumb-wrap"></div>');
					}
				}
			});
		}

		bindHoverAutoplay() {
			$(document).on('mouseenter', 'li.product, .wc-block-grid__product, .wpvg-archive-style-hover-autoplay', function() {
				const $card = $(this);
				const $wrap = $card.find('.wpvg-archive-style-hover-autoplay');
				if (!$wrap.length) return;

				$wrap.addClass('wpvg-hovered');
				const $activeItem = $wrap.find('.wpvg-archive-video-item.wpvg-active');
				if (!$activeItem.length) return;

				const provider = $activeItem.data('provider');
				const src = $activeItem.data('src');

				if (provider === 'self_hosted') {
					const video = $activeItem.find('video')[0];
					if (video) {
						video.play().catch(function() {});
					}
				} else if (provider === 'youtube' || provider === 'vimeo') {
					if (!$activeItem.find('iframe').length && src) {
						$activeItem.html('<iframe src="' + src + '" allow="autoplay; encrypted-media" allowfullscreen></iframe>');
					}
				}
			});

			$(document).on('mouseleave', 'li.product, .wc-block-grid__product, .wpvg-archive-style-hover-autoplay', function() {
				const $card = $(this);
				const $wrap = $card.find('.wpvg-archive-style-hover-autoplay');
				if (!$wrap.length) return;

				$wrap.removeClass('wpvg-hovered');
				$wrap.find('.wpvg-archive-video-item').each(function() {
					const $item = $(this);
					const provider = $item.data('provider');

					if (provider === 'self_hosted') {
						const video = $item.find('video')[0];
						if (video) {
							video.pause();
							video.currentTime = 0;
						}
					} else if (provider === 'youtube' || provider === 'vimeo') {
						$item.empty(); // Remove iframe on mouseleave to pause audio completely
					}
				});
			});
		}

		bindClickLightbox() {
			function getArchiveModal() {
				let $modal = $('#wpvg-archive-modal');
				if (!$modal.length) {
					$modal = $(
						'<div id="wpvg-archive-modal" class="wpvg-archive-modal" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true">' +
							'<div class="wpvg-archive-modal-backdrop"></div>' +
							'<div class="wpvg-archive-modal-dialog">' +
								'<button type="button" class="wpvg-archive-modal-close" aria-label="Close">&times;</button>' +
								'<div class="wpvg-archive-modal-player-wrap">' +
									'<div class="wpvg-archive-modal-player"></div>' +
									'<div class="wpvg-archive-modal-dots"></div>' +
								'</div>' +
								'<div class="wpvg-archive-modal-footer">' +
									'<h4 class="wpvg-archive-modal-title"></h4>' +
									'<a href="#" class="button wpvg-archive-modal-btn">View Product Details &rarr;</a>' +
								'</div>' +
							'</div>' +
						'</div>'
					).appendTo('body');
				}
				return $modal;
			}

			function loadModalVideo(video, $modal) {
				if (!video) return;
				const $player = $modal.find('.wpvg-archive-modal-player');
				$player.empty();

				const provider = video.provider || '';
				let embedUrl = video.embed_url || video.url || '';

				if (provider === 'self_hosted') {
					$player.html('<video controls autoplay playsinline style="width:100%;height:100%;border-radius:12px;" src="' + (video.url || embedUrl) + '"></video>');
				} else if (provider === 'youtube') {
					if (embedUrl.indexOf('autoplay=1') === -1) {
						embedUrl += (embedUrl.indexOf('?') !== -1 ? '&' : '?') + 'autoplay=1&mute=0';
					}
					$player.html('<iframe src="' + embedUrl + '" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen style="width:100%;height:100%;border:none;border-radius:12px;"></iframe>');
				} else if (provider === 'vimeo') {
					if (embedUrl.indexOf('autoplay=1') === -1) {
						embedUrl += (embedUrl.indexOf('?') !== -1 ? '&' : '?') + 'autoplay=1';
					}
					$player.html('<iframe src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="width:100%;height:100%;border:none;border-radius:12px;"></iframe>');
				} else if (provider === 'tiktok') {
					$player.html('<iframe src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="no-referrer-when-downgrade" style="width:100%;height:100%;border:none;border-radius:12px;"></iframe>');
				} else if (provider === 'facebook') {
					$player.html('<iframe src="' + embedUrl + '" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" style="width:100%;height:100%;border:none;border-radius:12px;"></iframe>');
				} else if (video.embed_html) {
					$player.html(video.embed_html);
				} else {
					$player.html('<iframe src="' + embedUrl + '" allow="autoplay; fullscreen; encrypted-media" allowfullscreen style="width:100%;height:100%;border:none;border-radius:12px;"></iframe>');
				}
			}

			function closeArchiveModal() {
				const $modal = $('#wpvg-archive-modal');
				if ($modal.length) {
					$modal.find('.wpvg-archive-modal-player').empty();
					$modal.removeClass('wpvg-active').fadeOut(150);
					$('body').css('overflow', '');
				}
			}

			$(document).on('click', '.wpvg-archive-play-badge', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const $btnEl = $(this);
				const title = $btnEl.data('product-title') || $btnEl.attr('data-product-title') || '';
				const url = $btnEl.data('product-url') || $btnEl.attr('data-product-url') || '#';
				let videos = [];

				const rawVideos = $btnEl.attr('data-videos');
				if (rawVideos) {
					try {
						videos = typeof rawVideos === 'string' ? JSON.parse(rawVideos) : rawVideos;
					} catch (err) {
						videos = $btnEl.data('videos') || [];
					}
				} else {
					videos = $btnEl.data('videos') || [];
				}

				if (!Array.isArray(videos) || !videos.length) return;

				const $modal = getArchiveModal();
				const $dots = $modal.find('.wpvg-archive-modal-dots');
				const $title = $modal.find('.wpvg-archive-modal-title');
				const $btn = $modal.find('.wpvg-archive-modal-btn');

				$title.text(title);
				$btn.attr('href', url);
				$dots.empty();

				if (videos.length > 1) {
					videos.forEach(function(v, i) {
						$dots.append('<button type="button" class="wpvg-archive-dot' + (i === 0 ? ' active' : '') + '" data-index="' + i + '"></button>');
					});
				}

				loadModalVideo(videos[0], $modal);
				$modal.addClass('wpvg-active').fadeIn(150);
				$('body').css('overflow', 'hidden');

				$modal.off('click', '.wpvg-archive-dot').on('click', '.wpvg-archive-dot', function(ev) {
					ev.preventDefault();
					const idx = parseInt($(this).data('index'), 10);
					$dots.find('.wpvg-archive-dot').removeClass('active');
					$(this).addClass('active');
					loadModalVideo(videos[idx], $modal);
				});
			});

			$(document).on('click', '.wpvg-archive-modal-close, .wpvg-archive-modal-backdrop', function(e) {
				e.preventDefault();
				closeArchiveModal();
			});

			$(document).on('keyup', function(e) {
				if (e.key === 'Escape' && $('#wpvg-archive-modal').is(':visible')) {
					closeArchiveModal();
				}
			});
		}

		bindInlinePlayers() {
			$(document).on('click', '.wpvg-archive-style-inline-player .wpvg-video-facade, .wpvg-archive-style-inline-player .wpvg-video-play-btn, .wpvg-archive-style-inline-player .wpvg-video-poster', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const $facade = $(this).closest('.wpvg-video-facade');
				if (!$facade.length || $facade.hasClass('is-playing')) return;

				const src = $facade.attr('data-src') || $facade.data('src') || '';
				const provider = $facade.attr('data-provider') || $facade.data('provider') || '';

				if (provider === 'self_hosted') {
					$facade.addClass('is-playing').replaceWith('<video controls autoplay playsinline style="width:100%;height:100%;object-fit:cover;" src="' + src + '"></video>');
				} else if (provider === 'youtube') {
					const autoplaySrc = src.indexOf('?') !== -1 ? src + '&autoplay=1' : src + '?autoplay=1';
					$facade.addClass('is-playing').replaceWith('<iframe src="' + autoplaySrc + '" allow="autoplay; encrypted-media; fullscreen" allowfullscreen style="width:100%;height:100%;border:none;"></iframe>');
				} else if (provider === 'vimeo') {
					const autoplaySrc = src.indexOf('?') !== -1 ? src + '&autoplay=1' : src + '?autoplay=1';
					$facade.addClass('is-playing').replaceWith('<iframe src="' + autoplaySrc + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="width:100%;height:100%;border:none;"></iframe>');
				} else if (provider === 'tiktok') {
					$facade.addClass('is-playing').replaceWith('<iframe src="' + src + '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="no-referrer-when-downgrade" style="width:100%;height:100%;border:none;"></iframe>');
				} else if (provider === 'facebook') {
					$facade.addClass('is-playing').replaceWith('<iframe src="' + src + '" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" style="width:100%;height:100%;border:none;"></iframe>');
				} else if (src) {
					$facade.addClass('is-playing').replaceWith('<iframe src="' + src + '" allow="autoplay; encrypted-media; fullscreen" allowfullscreen style="width:100%;height:100%;border:none;"></iframe>');
				}
			});
		}

		bindNavDots() {
			$(document).on('click', '.wpvg-archive-nav-dots .wpvg-archive-dot', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const $dot = $(this);
				const idx = parseInt($dot.data('index'), 10);
				const $wrap = $dot.closest('.wpvg-archive-card-video');

				$wrap.find('.wpvg-archive-dot').removeClass('active');
				$dot.addClass('active');

				const $items = $wrap.find('.wpvg-archive-video-item');
				$items.each(function() {
					const $it = $(this);
					if (parseInt($it.data('index'), 10) !== idx) {
						$it.removeClass('wpvg-active');
						const vid = $it.find('video')[0];
						if (vid) {
							vid.pause();
							vid.currentTime = 0;
						}
					}
				});

				const $targetItem = $items.filter('[data-index="' + idx + '"]').addClass('wpvg-active');

				// If hover mode is currently active, play the newly switched video
				if ($wrap.hasClass('wpvg-hovered')) {
					const provider = $targetItem.attr('data-provider') || $targetItem.data('provider');
					const src = $targetItem.attr('data-src') || $targetItem.data('src');

					if (provider === 'self_hosted') {
						const video = $targetItem.find('video')[0];
						if (video) {
							video.currentTime = 0;
							video.play().catch(function() {});
						}
					} else if (provider === 'youtube' || provider === 'vimeo') {
						if (src && !$targetItem.find('iframe').length) {
							$targetItem.html('<iframe src="' + src + '" allow="autoplay; encrypted-media" allowfullscreen style="width:100%;height:100%;border:none;"></iframe>');
						}
					} else if (provider === 'tiktok' || provider === 'facebook') {
						if (src && !$targetItem.find('iframe').length) {
							$targetItem.html('<iframe src="' + src + '" allow="autoplay; encrypted-media; fullscreen" allowfullscreen referrerpolicy="no-referrer-when-downgrade" style="width:100%;height:100%;border:none;"></iframe>');
						}
					}
				}
			});
		}

		bindTouchSwipe() {
			let touchStartX = 0;
			let touchStartY = 0;

			$(document).on('touchstart', '.wpvg-archive-card-video', function(e) {
				if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0]) {
					touchStartX = e.originalEvent.touches[0].clientX;
					touchStartY = e.originalEvent.touches[0].clientY;
				}
			}, { passive: true });

			$(document).on('touchend', '.wpvg-archive-card-video', function(e) {
				if (e.originalEvent && e.originalEvent.changedTouches && e.originalEvent.changedTouches[0]) {
					const diffX = touchStartX - e.originalEvent.changedTouches[0].clientX;
					const diffY = touchStartY - e.originalEvent.changedTouches[0].clientY;

					if (Math.abs(diffX) > 35 && Math.abs(diffX) > Math.abs(diffY)) {
						const $wrap = $(this).closest('.wpvg-archive-card-video');
						const $dots = $wrap.find('.wpvg-archive-dot');
						if ($dots.length > 1) {
							const currentIdx = $dots.filter('.active').index();
							let nextIdx = diffX > 0 ? currentIdx + 1 : currentIdx - 1;
							if (nextIdx >= $dots.length) nextIdx = 0;
							if (nextIdx < 0) nextIdx = $dots.length - 1;
							$dots.eq(nextIdx).trigger('click');
						}
					}
				}
			}, { passive: true });
		}	}

	/**
	 * Safe frontend initialization function that avoids duplicate bindings
	 *
	 * @param {jQuery} [$scope] Optional container element to scope initialization.
	 */
	function initWPVGFrontend($scope) {
		const $context = ($scope && $scope.length) ? $scope : $(document);
		$context.find('.wpvg-gallery-container').each(function() {
			const $el = $(this);
			if (!$el.data('wpvg-instance')) {
				const instance = new WPVGGallery($el);
				$el.data('wpvg-instance', instance);
			}
		});
	}

	window.initWPVGFrontend = initWPVGFrontend;

	// 1. Initialize on DOM ready
	$(document).ready(function() {
		initWPVGFrontend($(document));
		window.wpvgArchiveController = new WPVGArchiveVideoController();
	});

	// Re-run thumb wrapper check on window load (in case themes re-order DOM after ready)
	$(window).on('load', function() {
		if (window.wpvgArchiveController) {
			window.wpvgArchiveController.ensureThumbWrap();
		}
	});

	// 2. Elementor Page Builder compatibility (Frontend & Live Preview Editor)
	$(window).on('elementor/frontend/init', function() {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope) {
				initWPVGFrontend($scope);
				if (window.wpvgArchiveController) {
					window.wpvgArchiveController.ensureThumbWrap();
				}
			});
			window.elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-product-images.default', function($scope) {
				initWPVGFrontend($scope);
			});
			window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function($scope) {
				initWPVGFrontend($scope);
			});
		}
	});

	// 3. Divi Theme Builder & Visual Builder compatibility
	$(document).on('ajaxComplete et_pb_after_init_modules et_builder_ready', function() {
		initWPVGFrontend($(document));
		if (window.wpvgArchiveController) {
			window.wpvgArchiveController.ensureThumbWrap();
		}
	});

	// 4. WooCommerce Dynamic AJAX / Quick View compatibility
	$(document).on('wc_fragments_refreshed quick_view_pro:modal_loaded yith_infs_added_elem post-load', function() {
		initWPVGFrontend($(document));
		if (window.wpvgArchiveController) {
			window.wpvgArchiveController.ensureThumbWrap();
		}
	});

})(jQuery);


