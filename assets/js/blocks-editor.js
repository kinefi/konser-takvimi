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

	// 1. Blok: Konser Takvimi
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

	// 2. Blok: İlçe Konser Kontrolü, Takvime Ekle & Yönlendirme
	registerBlockType('konser-takvimi/ilce-yonlendir', {
		title: 'Konser İlçe Yönlendirici & Takvime Ekle',
		description: 'İlçede aktif konser varsa Takvime Ekle düğmesini ve analitiği çalıştırır; yoksa ziyaretçiyi hedef sayfaya yönlendirir.',
		icon: 'randomize',
		category: 'widgets',
		keywords: ['ilçe', 'yönlendir', 'takvime ekle', 'konser', 'analitik'],
		attributes: {
			ilce: {
				type: 'string',
				default: ''
			},
			hedef: {
				type: 'string',
				default: ''
			},
			takvime_ekle: {
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
							value: attributes.ilce,
							placeholder: 'orn: kadikoy',
							onChange: function (val) {
								setAttributes({ ilce: val });
							}
						}),
						el(TextControl, {
							label: 'Yönlendirme Hedefi (Boş bırakılırsa genel ayar kullanılır)',
							value: attributes.hedef,
							placeholder: '/konserler',
							onChange: function (val) {
								setAttributes({ hedef: val });
							}
						}),
						el(ToggleControl, {
							label: 'Takvime Ekle düğmesini göster (Google & .ics)',
							checked: attributes.takvime_ekle !== false,
							onChange: function (checked) {
								setAttributes({ takvime_ekle: checked });
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

	// 3. Blok: Konser Bilet Butonu
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
			resim: {
				type: 'string',
				default: ''
			},
			ilce: {
				type: 'string',
				default: ''
			},
			alt: {
				type: 'string',
				default: 'Bilet Al'
			},
			hedef: {
				type: 'string',
				default: ''
			}
		},
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var openInNewTab = attributes.hedef === '_blank';

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
							value: attributes.resim,
							placeholder: 'https://...',
							onChange: function (val) {
								setAttributes({ resim: val });
							}
						}),
						el(TextControl, {
							label: 'İlçe Analitik Anahtarı (Boş bırakılırsa sayfa slug)',
							value: attributes.ilce,
							placeholder: 'orn: bornova',
							onChange: function (val) {
								setAttributes({ ilce: val });
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
								setAttributes({ hedef: checked ? '_blank' : '' });
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
