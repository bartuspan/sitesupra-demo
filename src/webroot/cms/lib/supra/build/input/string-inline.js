YUI.add("supra.input-inline-string", function (Y) {
	//Invoke strict mode
	"use strict";
	
	/**
	 * Helper functions for escaping/unescaping strings
	 */
	var HTML_CHARS = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#x27;',
        '/': '&#x2F;',
        '`': '&#x60;'
        //' ': '&nbsp;'
    }
	
	var HTML_CHARS_INVERSE = {};
	var HTML_CHARS_REGEXP = '';
	
	for (var i in HTML_CHARS) {
		HTML_CHARS_INVERSE[HTML_CHARS[i].toLowerCase()] = i;
		HTML_CHARS_REGEXP += '\\' + i;
	}
	
	HTML_CHARS_REGEXP = new RegExp('[' + HTML_CHARS_REGEXP + ']', 'g');
	
	function escapeHtml (chr) {
		return HTML_CHARS[chr] || chr;
	}
	
	function unescapeHtml (ent) {
		return HTML_CHARS_INVERSE[ent.toLowerCase()] || ent;
	}
	
	
	
	/**
	 * Inline string input widget
	 */
	function Input (config) {
		Input.superclass.constructor.apply(this, arguments);
		this.init.apply(this, arguments);
	}
	
	Input.NAME = "input-string-inline";
	Input.ATTRS = {
		'doc': null,
		'win': null
	};
	
	Input.HTML_PARSER = {};
	
	Y.extend(Input, Supra.Input.InlineHTML, {
		/*CONTENT_TEMPLATE: null,*/
		
		renderUI: function () {
			//We overwrite InlineHTML.renderUI and it shouldn't be called, that's why
			//we call InlineHTML parent not Input parent
			Supra.Input.InlineHTML.superclass.renderUI.apply(this, arguments);
			
			this.set('boundingBox', this.get('srcNode'));
			
			var doc = this.get('doc'),
				win = this.get('win'),
				src = this.get('srcNode');
			
			if (doc && win && src) {
				this.htmleditor = new Supra.HTMLEditor({
					'doc': doc,
					'win': win,
					'srcNode': src,
					'toolbar': this.get('toolbar'),
					'mode': Supra.HTMLEditor.MODE_STRING
				});
				this.htmleditor.render();
				this.htmleditor.set('disabled', true);
			}
		},
		
		_getValue: function (value) {
			if (this.htmleditor) {
				value = this.htmleditor.getHTML();
				value = value.replace(/<[^>]+>/g, '');
				value = value.replace(/&.*?;/g, unescapeHtml);
			}
		
			return value;
		},
		
		_getSaveValue: function (value) {
			if (this.htmleditor) {
				value = this.htmleditor.getProcessedHTML();
				value = value.replace(/<[^>]+>/g, '');
				value = value.replace(/&.*?;/g, unescapeHtml);
				value = value.replace(/(^&nbsp;|&nbsp;$)/gi, ' ');
			}
			
			return value;
		},
		
		_setValue: function (value) {
			value = value || '';
			value = value.replace(HTML_CHARS_REGEXP, escapeHtml);
			if (this.htmleditor) {
				this.htmleditor.setHTML(value);
			}
			
			return value;
		},
		
		/**
		 * On focus move carret to the end of the text
		 */
		focus: function () {
			if (this.get('disabled')) return;
			Input.superclass.focus.apply(this, arguments);
			
			var node = this.get('srcNode'),
				element = node.getDOMNode(),
				length = element.childNodes.length;
			
			this.htmleditor.setSelection({
				start: element,
				start_offset: length,
				end: element,
				end_offset: length
			});
		},
		
		/**
		 * On blur move carret to the body
		 */
		blur: function () {
			Input.superclass.blur.apply(this, arguments);
			if (this.get('disabled')) return;
			
			if (this.htmleditor) {
				//Set carret position to body
				this.htmleditor.setSelection({
					start: document.body,
					start_offset: 0,
					end: document.body,
					end_offset: 0
				});
			}
		},
		
		/**
		 * Select all text
		 */
		selectAll: function () {
			if (this.get('disabled')) return;
			
			var node = this.get('srcNode'),
				element = node.getDOMNode(),
				length = element.childNodes.length;
			
			this.htmleditor.setSelection({
				start: element,
				start_offset: 0,
				end: element,
				end_offset: length
			});
		},
		
		/**
		 * Clean up
		 */
		destructor: function () {
			if (this.htmleditor) {
				this.htmleditor.detach('change');
				this.htmleditor.destroy();
				this.htmleditor = null;
			}
		}
		
	});
	
	Supra.Input.InlineString = Input;
	
	//Since this widget has Supra namespace, it doesn't need to be bound to each YUI instance
	//Make sure this constructor function is called only once
	delete(this.fn); this.fn = function () {};
	
}, YUI.version, {requires:["supra.input-inline-html"]});