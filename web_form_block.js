(function (blocks, element) {
	var el = element.createElement;

	var blockStyle = {
		'margin-left': '20px',
		padding: '10px 20px',
	};

	if (VineFormsData.error != null)
		alert(VineFormsData.error);
	else
		blocks.registerBlockType('vine-ma-plugin/vine-web-form', {
			title: 'Vine: Web Form',
			icon: 'universal-access-alt',
			category: 'layout',
			attributes: {
				formid: {
					type: 'string',
					default: '-1',
					source: 'attribute',
					selector: '.VineForm',
					attribute: 'data-form-id'
				},
				botformid: {
					type: 'string',
					default: '-1',
					source: 'attribute',
					selector: '.VineForm',
					attribute: 'data-bot-id'
				}
			},
			edit: function (props) {
				var vineforms = VineFormsData.forms;
				var els = [];
				els.push(
					el(
						'option',
						{ value: '-1' },
						'<not set>'
					)
				);
				for (var i = 0; i < vineforms.length; i++) {
					els.push(
						el(
							'option',
							{ value: vineforms[i].id },
							vineforms[i].name
						)
					);
				}
				function onChangeContent(newContent) {
					props.setAttributes({ formid: newContent.target.value });
				}
				return el(
					'div',
					{},
					[
						el(
							'span',
							{},
							'Vine Form:'
						),
						el(
							'select',
							{ style: blockStyle, onChange: onChangeContent, value: props.attributes.formid === '-1' ? props.attributes.botformid : props.attributes.formid },
							els
						)
					]
				);
			},
			save: function (props) {
				var form = props.attributes.formid === '-1' ? props.attributes.botformid : props.attributes.formid;
				var vineforms = VineFormsData.forms;
				var targetForm = [];
				if (vineforms)
					targetForm = vineforms.filter(function (f) { return f.id == form; });
				var isDataForm = true;
				if (targetForm.length > 0 && targetForm[0].type == 5)
					isDataForm = false;
				var args = {
					className: 'VineForm' + (isDataForm ? '' : ' B' + form),
					'data-form-id': isDataForm ? form : undefined,
					'data-bot-id': !isDataForm ? form : undefined
				}
				return el(
					'div',
					args,
					''
				);
			},
		});
}(
	window.wp.blocks,
	window.wp.element
));