//Invoke strict mode
"use strict";

YUI.add('supra.button', function (Y) {
	
	function Button (config) {
		Button.superclass.constructor.apply(this, arguments);
		this.init.apply(this, arguments);
	}
	
	Button.NAME = 'button';
	
	Button.ATTRS = {
		nodeWrapper: {
			value: null,
		},
		nodeButton: {
			value: null,
		},
		nodeLabel: {
			value: null,
		},
		label: {
			value: '',
			setter: '_setLabel'
		},
		type: {
			value: 'push'		// Valid types are 'push', 'toggle'
		},
		style: {
			value: 'mid',
			setter: '_setStyle'
		},
		disabled: {
			value: false,
			setter: '_setDisabled'
		},
		down: {
			value: false,
			setter: '_setDown'
		},
		visible: {
			value: true,
			setter: '_setVisible'
		},
		icon: {
			value: null,
			setter: '_setIcon'
		}
	},
	
	Button.CLASS_NAME = Y.ClassNameManager.getClassName(Button.NAME);
	
	/* 
     * The HTML_PARSER static constant is used by the Widget base class to populate 
     * the configuration for the button instance from markup already on the page.
     *
     * The Button class attempts to set the label, style, disabled, wrapper element of the Button widget if it
     * finds the appropriate elements on the page
     */
    Button.HTML_PARSER = {
        nodeButton: function (srcNode) {
			var tag = srcNode.get('tagName');
			
			if (tag == 'INPUT' || tag == 'BUTTON') {
				this.set('nodeButton', srcNode);
				return srcNode;
			} else {
				var node = srcNode.one('BUTTON,INPUT');
				if (!node) {
					node = Y.Node.create('<button type="button">' + (this.get('label') || '') + '</button>');
					this.get('contentBox').append(node);
				}
				
				this.set('nodeButton', node);
				return node;
			}
		},
		nodeWrapper: function (srcNode) {
			var tag = srcNode.get('tagName');
			if (tag != 'INPUT' && tag != 'BUTTON') {
				this.set('nodeWrapper', srcNode);
				return srcNode;
			};
			return null;
		},
		nodeLabel: function (srcNode) {
			// find SPAN inside button
			var btn = this.get('nodeButton');
			if (btn) {
				var label = btn.one('p');
				if (label) {
					this.set('nodeLabel', label);
					return;
				}
			}
			
			this.set('nodeLabel', btn);
		},
		disabled: function (srcNode) {
			var btn = this.get('nodeButton');
			if (btn) {
				var disabled = btn.get('disabled') ? true : false;
				this.set('disabled', disabled);
				return disabled;
			}
			return false;
		},
		label: function (srcNode) {
			var label = this.get('nodeLabel'),
				text = null;
			
			if (label) {
				text = label.get('innerHTML');
			}
			return text || this.get('label') || '&nbsp;';
        },
		icon: function (srcNode) {
			var node = srcNode.one('img');
			if (node) {
				return node.get('src');
			}
			return null;
		}
    };
	
	Y.extend(Button, Y.Widget, {
		
		initializer: function () {
			
		},
		
		destructor: function () {
			
		},
		
		renderUI : function() {
			//Add DIV around button
			if (!this.get('nodeWrapper')) {
				var btn = this.get('nodeButton');
				var nodeWrapper = Y.Node.create('<div></div>');
				btn.ancestor().appendChild(nodeWrapper);
				nodeWrapper.appendChild(btn);
			}
			
			//Add label inside button
			var btn = this.get('nodeButton');
			if (btn) {
				if (!this.get('nodeLabel') || this.get('nodeLabel').get('tagName') != 'P') {
					var p = Y.Node.create('<p>' + this.get('label') + '</p>');
					
					btn.set('innerHTML', '');
					btn.appendChild(p);
					
					this.set('nodeLabel', p);
				}
				
				if (!btn.getAttribute('type')) {
					btn.setAttribute('type', 'button');
				}
			}
			
			//ClassName
			if (btn) {
				var className = btn.getAttribute('className').replace(/\s?yui3-button-content\s?/, '');
				
				if (className) {
					btn.removeClass(className);
					this.get('boundingBox').addClass(className);
				}
			}
			
			if (this.get('icon')) {
				this.set('icon', this.get('icon'));
			}
		},
		
		syncUI: function () {
			this._syncUIStyle();
			
			//Change label if needed
			var label = this.get('nodeLabel');
			if (label && label.get('value') != this.get('label')) {
				label.set('value', this.get('label'));
			}
		},
		
		bindUI: function () {
			this.on('mousedown', this._onMouseDown, this);
			this.on('mouseup', this._onMouseUp, this);
			this.on('mouseover', this._onMouseOver, this);
			this.on('mouseout', this._onMouseOut, this)
			
			this.on('click', this._onClick, this);
			
			//On focus, focus input
			this.on('focusedChange', function (event) {
				if (event.newVal) {
					var btn = this.get('nodeButton');
					if (btn) btn.focus();
				}
			}, this);
		},
		
		_syncUIStyle: function (name, add) {
			var box = this.get('boundingBox');
			if (box) {
				var style = this.get('style');
				if (style) box.addClass(this.getClassName(style));
			}
		},
		
		_setDisabled: function (disabled) {
			var btn = this.get('nodeButton');
			if (btn) {
				btn.set('disabled', disabled);
			}
		},
		
		_setStyle: function (new_style) {
			var old_style = this.get('style');
			if (new_style == old_style) return;
			
			var box = this.get('boundingBox');
			if (box) {
				if (old_style) box.removeClass(this.getClassName(old_style));
				if (new_style) box.addClass(this.getClassName(new_style));
			}
		},
		
		_setDown: function (down) {
			if (down == this.get('down')) return !!down;
			
			if (down) {
				var box = this.get('boundingBox');
				if (box) box.addClass(this.getClassName('down'));
			} else {
				var box = this.get('boundingBox');
				if (box) box.removeClass(this.getClassName('down'));
			}
			
			if (box) box.removeClass(this.getClassName('mouse-hover'));
			
			return !!down;
		},
		
		_setLabel: function (label) {
			var labelNode = this.get('nodeLabel');
			if (labelNode) {
				label = label || '&nbsp;';
				labelNode.set('innerHTML', label);
			}
		},
		
		_setVisible: function (visible) {
			var box = this.get('boundingBox');
			if (box) {
				if (visible) {
					box.removeClass('hidden');
				} else {
					box.addClass('hidden');
				} 
			}
		},
		
		_setIcon: function (value) {
			var img = this.get('contentBox').one('img');
			if (!img) {
				if (!value) return value;
				img = Y.Node.create('<img src="' + value + '" alt="" />');
				var button = this.get('contentBox').one('button');
				button.prepend(img);
			} else {
				if (!value) {
					img.remove();
					return value;
				}
				img.setAttribute('src', value);
			}
			
			return value;
		},
		
		_onMouseDown: function () {
			if (this.get('disabled') || (this.get('type') == 'toggle' && this.get('down'))) return;
			var box = this.get('boundingBox');
			if (box) {
				box.addClass(this.getClassName('down'));
				box.removeClass(this.getClassName('mouse-hover'));
			}
		},
		
		_onMouseUp: function () {
			if (this.get('disabled') || this.get('down')) return;
			var box = this.get('boundingBox');
			if (box) {
				box.removeClass(this.getClassName('down'));
				box.removeClass(this.getClassName('mouse-hover'));
			}
		},
		
		_onMouseOver: function () {
			if (this.get('disabled') || this.get('down')) return;
			var box = this.get('boundingBox');
			if (box) box.addClass(this.getClassName('mouse-hover'));
		},
		
		_onMouseOut: function () {
			if (this.get('disabled') || this.get('down')) return;
			var box = this.get('boundingBox');
			if (box) {
				box.removeClass(this.getClassName('down'));
				box.removeClass(this.getClassName('mouse-hover'));
			}
		},
		
		_onClick: function () {
			if (this.get('type') == 'toggle') {
				var down = !this.get('down');
				this.set('down', down);
			}
		},
		
		addClass: function () {
			var box = this.get('boundingBox');
			if (box) box.addClass.apply(box, arguments);
			return this;
		},
		
		removeClass: function () {
			var box = this.get('boundingBox');
			if (box) box.removeClass.apply(box, arguments);
			return this;
		},
		
		hasClass: function () {
			var box = this.get('boundingBox');
			if (box) return box.hasClass.apply(box, arguments);
			return false;
		}
	});
	
	Supra.Button = Button;
	
	//Since this Widget has Supra namespace, it doesn't need to be bound to each YUI instance
	//Make sure this constructor function is called only once
	delete(this.fn); this.fn = function () {};
	
	
}, YUI.version, {'requires': ['node-focusmanager', 'widget', 'widget-child']});