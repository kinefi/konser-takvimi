(function (wp) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

	// Block 1: Concert Calendar
	registerBlockType('konser-takvimi/takvim', {
		title: 'Konser Takvimi',
		description: 'Aktif ve yaklaşan tüm konserleri Bugün/Yarın rozetleriyle renkli kart ızgarası halinde listeler.',
		icon: 'calendar-alt',
		category: 'widgets',
		keywords: ['konser', 'takvim', 'etkinlik', 'şubadap'],
		edit: function () {
			var blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el(ServerSideRender, {
					block: 'konser-takvimi/takvim'
				})
			);
		},
		save: function () {
			return null;
		}
	});

	// Block 2: District concert check, Add to Calendar & redirect
	registerBlockType('konser-takvimi/ilce-yonlendir', {
		title: 'Konser İlçe Yönlendirici & Takvime Ekle',
		description: 'İlçede aktif konser varsa Takvime Ekle düğmesini ve analitiği çalıştırır; yoksa ziyaretçiyi hedef sayfaya yönlendirir.',
		icon: 'randomize',
		category: 'widgets',
		keywords: ['ilçe', 'yönlendir', 'takvime ekle', 'konser', 'analitik'],
		attributes: {
			district: {
				type: 'string',
				default: ''
			},
			redirect_target: {
				type: 'string',
				default: ''
			},
			show_calendar_button: {
				type: 'boolean',
				default: true
			}
		},
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'İlçe Sayfası & Takvime Ekle Ayarları', initialOpen: true },
						el(TextControl, {
							label: 'İlçe Slug (Boş bırakılırsa sayfa slug kullanılır)',
							value: attributes.district,
							placeholder: 'orn: kadikoy',
							onChange: function (val) {
								setAttributes({ district: val });
							}
						}),
						el(TextControl, {
							label: 'Yönlendirme Hedefi (Boş bırakılırsa genel ayar kullanılır)',
							value: attributes.redirect_target,
							placeholder: '/konserler',
							onChange: function (val) {
								setAttributes({ redirect_target: val });
							}
						}),
						el(ToggleControl, {
							label: 'Takvime Ekle düğmesini göster (Google & .ics)',
							checked: attributes.show_calendar_button !== false,
							onChange: function (checked) {
								setAttributes({ show_calendar_button: checked });
							}
						})
					)
				),
				el(
					'div',
					blockProps,
					el(ServerSideRender, {
						block: 'konser-takvimi/ilce-yonlendir',
						attributes: attributes
					})
				)
			);
		},
		save: function () {
			return null;
		}
	});

	// Block 3: Concert Ticket Button
	registerBlockType('konser-takvimi/bilet-butonu', {
		title: 'Konser Bilet Butonu',
		description: 'CTR tıklama analitiği ile entegre bilet satın alma görsel butonu.',
		icon: 'tickets-alt',
		category: 'widgets',
		keywords: ['bilet', 'buton', 'konser', 'satın al'],
		attributes: {
			url: {
				type: 'string',
				default: ''
			},
			image: {
				type: 'string',
				default: ''
			},
			district: {
				type: 'string',
				default: ''
			},
			alt: {
				type: 'string',
				default: 'Bilet Al'
			},
			link_target: {
				type: 'string',
				default: ''
			}
		},
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var openInNewTab = attributes.link_target === '_blank';

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Bilet Butonu Ayarları', initialOpen: true },
						el(TextControl, {
							label: 'Bilet Satış URL (Boş bırakılırsa genel ayar kullanılır)',
							value: attributes.url,
							placeholder: 'https://...',
							onChange: function (val) {
								setAttributes({ url: val });
							}
						}),
						el(TextControl, {
							label: 'Buton Görsel URL (Boş bırakılırsa varsayılan görsel)',
							value: attributes.image,
							placeholder: 'https://...',
							onChange: function (val) {
								setAttributes({ image: val });
							}
						}),
						el(TextControl, {
							label: 'İlçe Analitik Anahtarı (Boş bırakılırsa sayfa slug)',
							value: attributes.district,
							placeholder: 'orn: bornova',
							onChange: function (val) {
								setAttributes({ district: val });
							}
						}),
						el(TextControl, {
							label: 'Görsel Alt Metni',
							value: attributes.alt,
							onChange: function (val) {
								setAttributes({ alt: val });
							}
						}),
						el(ToggleControl, {
							label: 'Bağlantıyı yeni sekmede aç (_blank)',
							checked: openInNewTab,
							onChange: function (checked) {
								setAttributes({ link_target: checked ? '_blank' : '' });
							}
						})
					)
				),
				el(
					'div',
					blockProps,
					el(ServerSideRender, {
						block: 'konser-takvimi/bilet-butonu',
						attributes: attributes
					})
				)
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp);
