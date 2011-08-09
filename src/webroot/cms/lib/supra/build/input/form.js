//Invoke strict mode
"use strict";

YUI.add("supra.form", function (Y) {
	
	//Input configuration defaults
	var INPUT_DEFINITION = {
		"id": null,
		"name": null,
		"label": "",
		"type": "String",
		"srcNode": null,
		"labelNode": null,
		"value": "",
		"disabled": false
	};
	
	/**
	 * Class for handling inputs, saving, loading and deleting data 
	 * 
	 * @alias Supra.Form
	 * @param {Object} config Configuration
	 */
	function Form (config) {
		//Fix 'value' references for inputs
		this.fixInputConfigValueReferences(config);
		
		Form.superclass.constructor.apply(this, [config]);
		
		this.inputs = {};
		this.inputs_definition = {};
		this.init.apply(this, arguments);
		this.processAttributes();
	}
	
	Form.NAME = "form";
	Form.ATTRS = {
		"inputs": {
			value: null
		},
		"autoDiscoverInputs": {
			value: true
		},
		"urlLoad": {
			value: null
		},
		"urlSave": {
			value: null
		},
		"urlDelete": {
			value: null
		},
		"style": {
			value: ""
		}
	};
	Form.HTML_PARSER = {
		"urlLoad": function (srcNode) {
			var value = this.get('urlLoad');
			if (value === null && srcNode.test('form')) {
				value = srcNode.getAttribute("action");
			}
			return value ? value : null;
		}
	};
	
	Y.extend(Form, Y.Widget, {
		
		CONTENT_TEMPLATE: '<form></form>',
		
		/*
		 * List of input definitions
		 */
		inputs_definition: null,
		
		/*
		 * List of input fields
		 */
		inputs: null,
		
		/**
		 * Search for inputs in DOM
		 * 
		 * @private
		 * @return Object with input definitions
		 * @type {Object}
		 */
		discoverInputs: function () {
			var inputs = this.get('srcNode').all('input,textarea,select');
			var config = {};
			
			for(var i=0,ii=inputs.size(); i<ii; i++) {
				var input = inputs.item(i);
				var id = input.getAttribute('id') || input.getAttribute('name');
				var name = input.getAttribute('name') || input.getAttribute('id');
				var value = input.get("value");
				var disabled = input.getAttribute("disabled") ? true : false;
				var label = '';
				
				//If there is no name or id, then input can't be identified
				if (!id || !name) continue;
				
				var tagName = input.get('tagName').toLowerCase();
				var tagType = input.getAttribute('type').toLowerCase();
				var type = "String";
				
				//Get label
				var labelNode = this.get('srcNode').one('label[for="' + id + '"]');
				if (labelNode) {
					label = labelNode.get('innerHTML');
				}
				
				//Detect type
				var suType = input.getAttribute('suType');
				if (suType) {
					type = suType;
				} else {
					switch(tagName) {
						case 'textarea':
							type = "Text"; break;
						case 'select':
							type = "Select"; break;
						case 'input':
							switch(tagType) {
								case 'hidden':
									type = "Hidden"; break;
								case 'checkbox':
									type = "Checkbox"; break;
								case 'radio':
									type = "Radio"; break;
								case 'file':
									type = "FileUpload"; break;
							}
							break;
					}
				}
				
				var srcNode = input.ancestor('div.field') || input;
				
				config[id] = {
					"id": id,
					"label": label,
					"name": name,
					"type": type,
					"srcNode": srcNode,
					"labelNode": labelNode,
					"value": value,
					"disabled": disabled
				};
			}
			
			return config;
		},
		
		/**
		 * Fix input config value references to prevent two inputs with
		 * same type (like Hidden) having same raw value
		 * 
		 * @param {Object} config
		 */
		fixInputConfigValueReferences: function (config) {
			if (config.inputs) {
				var empty = {},
					value = null;
				
				for(var i=0,ii=config.inputs.length; i<ii; i++) {
					value = config.inputs[i].value;
					if (Y.Lang.isObject(value)) {
						empty = (Y.Lang.isArray(value) ? [] : {});
						config.inputs[i].value = Supra.mix(empty, config.inputs[i].value, true);
					}
				}
			}
		},
		
		/**
		 * Normalize input config
		 * 
		 * @private
		 * @param {Object} config
		 * @return Normalized input config
		 * @type {Object}
		 */
		normalizeInputConfig: function () {
			//Convert arguments into
			//[{}, INPUT_DEFINITION, argument1, argument2, ...]
			var args = [].slice.call(arguments,0);
				args = [{}, INPUT_DEFINITION].concat(args);
			
			//Mix them together
			return Supra.mix.apply(Supra, args);
		},
		
		/**
		 * Create Input instance from configuration
		 * 
		 * @param {Object} config
		 * @return Input instance
		 * @type {Object}
		 */
		factoryField: function (config) {
			var type = config.type;
				type = type.substr(0,1).toUpperCase() + type.substr(1);
			
			if (type in Supra.Input) {
				return new Supra.Input[type](config);
			} else {
				return null;
			}
		},
		
		/**
		 * Add input
		 * 
		 * config - input configuration object:
		 *     id         - unique field ID
		 *     name       - input name, will be sent as name on save request. Optional
		 *     label      - label text. Optional
		 *     type       - field type. Possible values: "String", "Path", "Template", "Checkbox", "Select", "Text", "Html". Optional, default is "String"
		 *     srcNode    - input node or field (input,label,etc.) node. Optional
		 *     labelNode  - label node, label property will be set to text content of labelNode. Optional
		 *     value      - input value. Optional, default is empty string,
		 *     disabled   - input disabled or not. Optional, if input has disabled attribute, then it will be used
		 * 
		 * @param {Object} config
		 */
		addInput: function (config) {
			if (this.get('rendered')) {
				//@TODO Add possibility to change input attributes after input has been rendered
			} else {
				var id = ('id' in config && config.id ? config.id : ('name' in config ? config.name : ''));
				if (!id) {
					Y.log('Input configuration must specify ID or NAME', 'error');
					return this;
				}
				
				var conf = (id in this.inputs_definition ? this.inputs_definition[id] : {});
				this.inputs_definition[id] = Supra.mix(conf, config);
			}
			
			return this;
		},
		
		/**
		 * Alias of addInput
		 */
		setInput: function (config) {
			return this.addInput(config);
		},
		
		
		/**
		 * Bind even listeners
		 * @private
		 */
		bindUI: function () {
			Form.superclass.bindUI.apply(this, arguments);
			
			//On visibility change show/hide form
			this.on('visibleChange', function (event) {
				if (event.newVal) {
					this.get('boundingBox').removeClass('hidden');
				} else {
					this.get('boundingBox').addClass('hidden');
				}
			}, this);
		},
		
		/**
		 * Process Input attribute
		 */
		processAttributes: function () {
			var inputs = this.get('inputs');
			
			if (Y.Lang.isArray(inputs)) {
				var obj = {},
					id = null,
					i = 0,
					ii = inputs.length;
				
				for(; i<ii; i++) {
					id = (('id' in inputs[i]) ? inputs[i].id : ('name' in inputs[i] ? inputs[i].name : null));
					if (id) {
						obj[id] = inputs[i];
					}
				}
				inputs = obj;
			}
			
			this.inputs_definition = inputs || {};
		},
		
		/**
		 * Render form and inputs
		 * @private
		 */
		renderUI: function () {
			Form.superclass.renderUI.apply(this, arguments);
			
			var srcNode = this.get("srcNode");
			var contentBox = this.get("contentBox");
			
			var inputs = {};
			var definitions = this.inputs_definition || {};
			
			//Find all inputs
			if (this.get('autoDiscoverInputs')) {
				definitions = Supra.mix(this.discoverInputs(), definitions, true);
			}
			
			//Normalize definitions
			//by adding missing parameters
			var definition = null,
				id = null,
				node = null,
				input;
			
			//Create Inputs
			for(var i in definitions) {
				definition = definitions[i] = this.normalizeInputConfig(definitions[i]);
				id = definition.id;
				
				//Try finding input
				if (!definition.srcNode) {
							    node = srcNode.one('#' + id);
					if (!node)  node = srcNode.one('*[name="' + definition.name + '"]');
					if (!node)  node = srcNode.one('*[data-input-id="' + id + '"]');
					
					if (node) {
						definition.srcNode = node;
					}
				}
				
				input = this.factoryField(definition);
				if (input) {
					inputs[id] = input;
					
					if (definition.srcNode) {
						input.render();
					} else {
						//If input doesn't exist, then create it
						input.render(contentBox);
					}
				}
			}
			
			this.inputs = inputs;
			this.inputs_definition = definitions;
			
			//Style
			this.get('srcNode').addClass(Y.ClassNameManager.getClassName(Form.NAME, 'default'));
			var style = this.get('style');
			if (style) {
				this.get('srcNode').addClass(Y.ClassNameManager.getClassName(Form.NAME, style));
			}
		},
		
		/**
		 * Serialize multi-dimensional object into one dimensional object
		 * 
		 * @param {Object} obj
		 * @param {String} prefix
		 * @return One dimensional object
		 * @type {Object}
		 * @private
		 */
		serializeObject: function (obj, prefix, skip_encode) {
			var prefix = prefix || '';
			var out = {};
			
			for(var id in obj) {
				var name = skip_encode ? id : encodeURIComponent(id);
					name = prefix ? prefix + '[' + name + ']' : name;
				
				if (Y.Lang.isObject(obj[id])) {
					out = Y.mix(this.serializeObject(obj[id], name, skip_encode) ,out);
				} else {
					out[name] = skip_encode ? obj[id] : encodeURIComponent(obj[id]);
				}
			}
			
			return out;
		},
		
		/**
		 * Convert one-dimensional value object into multi-dimensional changing
		 * "key1[key2][key3]" = 3 into {key1: {key2: {key3: 3}}}
		 * 
		 * @param {Object} obj
		 * @return Multi dimensional object
		 * @type {Object}
		 * @private
		 */
		unserializeObject: function (obj, skip_decode) {
			var out = {},
				m,
				name;
			
			for(var id in obj) {
				if (String(id).indexOf('[') != -1) {
					if (m = id.match(/([^\[]+)\[([^\]]+)\](.*)/)) {
						name = skip_decode ? m[1] : decodeURIComponent(String(m[1]));
						if (!(name in out)) out[name] = {};
						this.unserializeItem(m[2] + m[3], obj[id], out[name], skip_decode);
					}
				} else {
					out[id] = skip_decode ? obj[id] : decodeURIComponent(obj[id]);
				}
			}
			
			return out;
		},
		
		/**
		 * Parse ID string and set data on out object
		 * 
		 * @param {String} id
		 * @param {Object} value
		 * @param {Object} out
		 */
		unserializeItem: function (id, value, out, skip_decode) {
			var m, name;
			
			if (String(id).indexOf('[') != -1) {
				if (m = id.match(/([^\[]+)\[([^\]]+)\](.*)/)) {
					name = skip_decode ? m[1] : decodeURIComponent(String(m[1]));
					if (!(name in out)) out[name] = {};
					this.unserializeItem(m[2] + m[3], value, out[name], skip_decode);
				}
			} else {
				out[id] = skip_decode ? value : decodeURIComponent(value);
			}
		},
		
		/**
		 * Returns serialize values ready for use in query string
		 * 
		 * @param {String} key Name of the property, which will be used for key, default is 'name'
		 * @return Form input values
		 * @type {Object}
		 */
		getSerializedValues: function (key) {
			var values = this.getValues(key);
				values = this.serializeObject(values);
			
			return values;
		},
		
		/**
		 * Returns values parsing input names and changing into
		 * multi-dimension object
		 * 
		 * @param {String} key Name of the property, which will be used for key, default is 'name'
		 * @return Form input values
		 * @type {Object}
		 */
		getValuesObject: function (key) {
			var values = this.getValues(key);
			return this.unserializeObject(values);
		},
		
		/**
		 * Returns input name => value pairs
		 * Optionally other attribute can be used instead of "name"
		 * 
		 * @param {String} key
		 * @param {Boolean} save Return save value
		 * @return Form input values
		 * @type {Object}
		 */
		getValues: function (key, save) {
			var key = key || 'name';
			var values = {};
			var definitions = this.inputs_definition;
			var prop = save ? 'saveValue' : 'value';
			
			for(var id in this.inputs) {
				var input = this.inputs[id];
				var val = input.get(prop);
				values[key == 'id' || key == 'name' ? definitions[id][key] : input.getAttribute(key)] = val;
			}
			
			return values;
		},
		
		/**
		 * Set input values
		 * 
		 * @param {Object} data
		 * @param {Object} key
		 */
		setValues: function (data, key, skip_encode) {
			var key = key || 'name',
				definitions = this.inputs_definition,
				input = null,
				key_value = null;
				data = skip_encode ? data : this.serializeObject(data, null, true);
			
			data = data || {};
			
			for(var id in this.inputs) {
				input = this.inputs[id];
				key_value = (key == 'id' || key == 'name' ? definitions[id][key] : input.getAttribute(key));
				
				if (key_value in data) {
					input.set('value', data[key_value]);
				}
			}
			
			return this;
		},
		
		/**
		 * Set input values without converting names {'a': {'b': 'c'}} into {'a[b]': 'c'}
		 * 
		 * @param {Object} data
		 * @param {Object} key
		 */
		setValuesObject: function (data, key) {
			return this.setValues(data, key, true);
		},
		
		/**
		 * Reset input values to defaults
		 * 
		 * @param {Array} inputs Optional. Array of input ids which should be reseted, if not set then all inpts
		 */
		resetValues: function (list) {
			var inputs = this.inputs;
			
			if (Y.Lang.isArray(list)) {
				for(var i=0,ii=list.length; i<ii; i++) {
					if (list[i] in inputs) inputs[list[i]].resetValue();
				}
			} else {
				for(var id in inputs) {
					inputs[id].resetValue();
				}
			}
			
			return this;
		},
		
		/**
		 * Returns inputs
		 *  
		 * @return All inputs
		 * @type {Object}
		 */
		getInputs: function () {
			return this.inputs;
		},
		
		/**
		 * Returns input by ID or if not found, then by name
		 * 
		 * @param {String} id
		 * @return Input instance
		 * @type {Object}
		 */
		getInput: function (id) {
			if (id in this.inputs) {
				return this.inputs[id];
			} else {
				//Search by "name"
				var definitions = this.inputs_definition;
				
				for(var i in definitions) {
					if (definitions[i].name == id) return this.inputs[i];
				}
			}
			return null;
		},
		
		/**
		 * Returns input configuration/definition by id or by name if
		 * there were no matches by id or null if name also didn't matches
		 * any input
		 * 
		 * @param {String} id
		 * @return Input configuration/definition
		 * @type {Object}
		 */
		getConfig: function (id) {
			var definitions = this.inputs_definition;
			if (id in definitions) {
				return definitions[id];
			} else {
				for(var i in definitions) {
					if (definitions[i].name == id) return definitions[i];
				}
			}
			return null;
		},
		
		/**
		 * Set if form should search for inputs
		 * 
		 * @param {Boolean} value
		 */
		setAutoDiscoverInputs: function (value) {
			this.set('autoDiscoverInputs', !!value);
			return this;
		},
		
		/**
		 * Set load request url
		 * 
		 * @param {String} url
		 */
		setURLLoad: function (url) {
			this.set('urlLoad', url);
			return this;
		},
		
		/**
		 * Set delete request url
		 * 
		 * @param {String} url
		 */
		setURLDelete: function (url) {
			this.set('urlDelete', url);
			return this;
		},
		
		/**
		 * Set save request url
		 * 
		 * @param {String} url
		 */
		setURLSave: function (url) {
			this.set('urlSave', url);
			return this;
		},
		
		/**
		 * Validate and execute save request if url is set and user is authorized to save data
		 */
		save: function () {
			//@TODO
		},
		
		/**
		 * Load data and populate form if url is set and user is authorized to load data
		 */
		load: function () {
			//@TODO
		},
		
		/**
		 * Execute delete request if url is set, form has ID field and user is authorized to delete record
		 */
		'delete': function () {
			//@TODO
		}
		
	});
	
	Supra.Form = Form;
	Supra.Form.normalizeInputConfig = Form.prototype.normalizeInputConfig;
	Supra.Form.factoryField = Form.prototype.factoryField;
	
	//Since this widget has Supra namespace, it doesn't need to be bound to each YUI instance
	//Make sure this constructor function is called only once
	delete(this.fn); this.fn = function () {};
	
}, YUI.version, {requires:[
	"widget",
	"supra.input-proto",
	"supra.input-hidden",
	"supra.input-string",
	"supra.input-path",
	"supra.input-checkbox",
	"supra.input-file-upload",
	"supra.input-select",
	"supra.input-select-list"
]});