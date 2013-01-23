YUI.add('supra.input-video', function (Y) {
	//Invoke strict mode
	"use strict";
	
	function Input (config) {
		Input.superclass.constructor.apply(this, arguments);
		this.init.apply(this, arguments);
	}
	
	Input.NAME = 'input-video';
	Input.CLASS_NAME = Y.ClassNameManager.getClassName(Input.NAME);
	Input.ATTRS = {};
	
	Y.extend(Input, Supra.Input.Proto, {
		
		INPUT_TEMPLATE: '<input type="hidden" value="" />',
		LABEL_TEMPLATE: '', // No label on this widget
		
		/**
		 * Sub widgets, 'source'
		 * @type {Object}
		 * @private
		 */
		widgets: null,
		
		renderUI: function () {
			Input.superclass.renderUI.apply(this, arguments);
			
			this.widgets = {};
			
			var source = this.widgets.source = new Supra.Input.Text({
				'label': this.get('label'),
				'value': this.get('value').source
			});
			
			source.render(this.get('contentBox'));
		},
		
		bindUI: function () {
			Input.superclass.bindUI.apply(this, arguments);
			
			//Handle value attribute change
			this.on('valueChange', this._afterValueChange, this);
			
			//On source change update this widget too
			this.widgets.source.on('change', this._onWidgetsChange, this);
		},
		
		/**
		 * Convert 'link' video data into 'source'
		 * 
		 * @param {Object} data Video data
		 * @returns {Object} Normalized video data
		 */
		normalizeData: function (data) {
			if (!data || !data.resource) {
				data = {'resource': 'source', 'source': ''};
			} else if (data.resource == 'link'){
				data = Supra.mix({}, data);
				data.resource = 'source';
				
				switch (data.service) {
					case 'youtube':
						data.source = 'http://' + data.service + '.com/?v=' + data.id;
						break;
					case 'vimeo':
						data.source = 'http://' + data.service + '.com/' + data.id;
						break;
				}
				
				delete(data.id);
				delete(data.service);
			}
			
			return data;
		},
		
		/**
		 * Value attribute setter
		 * 
		 * @param {Object} data New input value
		 * @returns {Object} New input value
		 * @private
		 */
		_setValue: function (data) {
			var value = '',
				input = this.widgets ? this.widgets.source : null; // May not be rendered yet
			
			data = this.normalizeData(data);
			value = data.source || '';
			
			if (input && input.get('value') !== value) {
				input.set('value', value);
			}
			
			return data;
		},
		
		/**
		 * Value attribute getter
		 * 
		 * @param {Object} data Old value
		 * @returns {Object} New value
		 * @private
		 */
		_getValue: function (data) {
			var input = this.widgets ? this.widgets.source : null; // May not be rendered yet
			
			return {
				'resource': data && data.resource ? data.resource : 'source',
				'source': input ? input.get('value') : data.source || ''
			};
		},
		
		/**
		 * Trigger change event when value changes
		 * 
		 * @param {Object} evt
		 */
		_afterValueChange: function (evt) {
			if (evt.prevVal != evt.newVal) {
				this.fire('change', {'value': evt.newVal});
			}
		},
		
		_onWidgetsChange: function (evt) {
			if (evt.prevVal != evt.newVal) {
				this.set('value', this.get('value'));
			}
		}
		
	});
	
	Supra.Input.Video = Input;
	
	//Since this widget has Supra namespace, it doesn't need to be bound to each YUI instance
	//Make sure this constructor function is called only once
	delete(this.fn); this.fn = function () {};
	
}, YUI.version, {requires:['supra.input-proto']});