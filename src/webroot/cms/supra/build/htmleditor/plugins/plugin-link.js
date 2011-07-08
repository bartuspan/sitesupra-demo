YUI().add('supra.htmleditor-plugin-link', function (Y) {
	
	var defaultConfiguration = {
	};
	
	SU.HTMLEditor.addPlugin('link', defaultConfiguration, {
		
		/**
		 * Link editor is visible
		 * @type {Boolean}
		 */
		visible: false,
		
		
		/**
		 * Insert link around current selection
		 */
		insertLink: function () {
			if (!this.htmleditor.editingAllowed) return;
			
			var htmleditor = this.htmleditor,
				selection = htmleditor.getSelection();
			
			//If in current selection is a link then edit it instead of creating new
			var nodes = htmleditor.findNodesInSelection(selection, 'a');
			
			if (nodes && nodes.size())
			{
				//Edit selected link
				this.editLink({
					'currentTarget': nodes.item(0)
				});
				
				//Prevent default 
				return false;
			}
			else if (selection.collapsed)
			{
				//Cancel if no text is selected
				return false;
			}
			else if (htmleditor.isSelectionEditable(selection))
			{
				//Show link manager
				this.showLinkManager(null, Y.bind(function (data) {
					this.insertLinkConfirmed(data, selection);
				}, this));
				
				//Prevent default
				return false;
			}
			
			//Nothing was done
			return true;
		},
		
		/**
		 * After user entered value in prompt insert link
		 * 
		 * @param {Object} event
		 */
		insertLinkConfirmed: function (data, selection) {
			if (data && data.href) {
				var htmleditor = this.htmleditor;
				
				//Restore selection
				htmleditor.setSelection(selection);
				
				//Insert link
				var uid = htmleditor.generateDataUID(),
					text = this.htmleditor.getSelectionText(),
					href = this.normalizeHref(data.href),
					html = '<a id="' + uid + '"' + (data.target ? ' target="' + data.target + '"' : '') + ' title="' + Y.Lang.escapeHTML(data.title || '') + '" href="' + href + '">' + text + '</a>';
				
				data.type = this.NAME;
				htmleditor.setData(uid, data)
				htmleditor.replaceSelection(html, null);
			}
			
			//Trigger selection change event
			this.visible = false;
			this.htmleditor.refresh(true);
			
			var button = this.htmleditor.get('toolbar').getButton('insertlink');
			if (button) button.set('down', false).set('disabled', true);
		},
		
		/**
		 * Double clicking link must open prompt to enter new link url
		 * 
		 * @param {Object} event Event
		 */
		editLink: function (event) {
			var target = event.currentTarget;
			if (!this.htmleditor.editingAllowed || !this.htmleditor.isEditable(target)) return;
			
			//Get current value
			var data = this.htmleditor.getData(target);
			
			if (!data) {
				data = {
					'type': this.NAME,
					'title': target.getAttribute('title'),
					'target': target.getAttribute('target'),
					'href': this.normalizeHref(target.getAttribute('href'))
				}
			}
			
			this.showLinkManager(data, Y.bind(function (data) {
				this.editLinkConfirmed(data, target);
			}, this));
		},
		
		/**
		 * After user changed link save data into htmleditor and update href
		 * 
		 * @param {Object} event
		 */
		editLinkConfirmed: function (data, target) {
			if (data && data.href) {
				data.type = this.NAME;
				this.htmleditor.setData(target, data);
				
				//HREF attribute
				var href = this.normalizeHref(data.href);
				target.setAttribute('href', href);
				
				//Title attribute
				target.setAttribute('title', data.title || '');
				
				//Target attribute
				if (data.target) {
					target.setAttribute('target', data.target);
				} else {
					target.removeAttribute('target');
				}
			} else {
				//Insert all link children nodes before link and remove <A>
				target.insert(target.get('childNodes'), 'before').remove();
			}
			
			//Trigger selection change event
			this.visible = false;
			this.htmleditor.refresh(true);
			
			var button = this.htmleditor.get('toolbar').getButton('insertlink');
			if (button) button.set('down', false).set('disabled', true);
		},
		
		/**
		 * Normalize link by removing domain
		 * 
		 * @param {String} href
		 * @return Normalized domain
		 * @type {String}
		 */
		normalizeHref: function (href) {
			var domain = document.location.protocol + '//' + document.location.host;
			return href.replace(domain, '') || '/';
		},
		
		/**
		 * Show link manager
		 * 
		 * @param {String} href
		 * @param {Object} target
		 * @param {Function} callback
		 */
		showLinkManager: function (data, callback) {
			if (!callback) return;
			
			SU.Manager.getAction('LinkManager').once('execute', function () {
				this.visible = true;
			}, this);
			SU.Manager.getAction('LinkManager').execute(data, callback);
		},
		
		/**
		 * Hide link manager
		 */
		hideLinkManager: function () {
			if (this.visible) {
				SU.Manager.getAction('LinkManager').hide();
				this.visible = false;
				this.htmleditor.refresh();
			}
		},
		
		/**
		 * Show or hide link manager based on toolbar button state
		 */
		toggleLinkManager: function () {
			var button = this.htmleditor.get('toolbar').getButton('insertlink');
			if (button.get('down')) {
				this.insertLink();
			} else {
				this.hideLinkManager();
			}
		},
		
		/**
		 * Initialize plugin for editor,
		 * Called when editor instance is initialized
		 * 
		 * @param {Object} htmleditor HTMLEditor instance
		 * @constructor
		 */
		init: function (htmleditor) {
			// Add command
			htmleditor.addCommand('insertlink', Y.bind(this.toggleLinkManager, this));
			
			// When double clicking on link show popup
			var container = htmleditor.get('srcNode');
			container.delegate('dblclick', Y.bind(this.editLink, this), 'a');
			
			var self = this;
			var toolbar = htmleditor.get('toolbar');
			var button = toolbar ? toolbar.getButton('insertlink') : null;
			if (button) {
				
				//When un-editable node is selected disable toolbar button
				htmleditor.on('editingAllowedChange', function (event) {
					button.set('disabled', !event.allowed);
				});
				
				//If there is no text selection disable toolbar button
				htmleditor.on('selectionChange', function (event) {
					var allowEditing = false,
						down = false;
					
					//Check if cursor is inside link
					var node = this.getSelectedElement();
					if (node && node.tagName == 'A') {
						if (this.editingAllowed) {
							allowEditing = true;
							down = self.visible;
						}
					} else if (this.editingAllowed) {
						//Check if there is text selection
						if (!this.selection.collapsed) {
							allowEditing = true;
							down = self.visible;
						}
					}
					
					button.set('disabled', !allowEditing);
					button.set('down', down);
				});
			}
			
			this.visible = false;
			
			//When selection changes hide link manager
			htmleditor.on('selectionChange', this.hideLinkManager, this);
			
			//Hide link manager when editor is closed
			htmleditor.on('disable', this.hideLinkManager, this);
		},
		
		/**
		 * Clean up after plugin
		 * Called when editor instance is destroyed
		 */
		destroy: function () {},
		
		
		/**
		 * Process HTML and replace all nodes with macros {supra.image id="..."}
		 * Called before HTML is saved
		 * 
		 * @param {String} html
		 * @return Processed HTML
		 * @type {HTML}
		 */
		processHTML: function (html) {
			var htmleditor = this.htmleditor,
				NAME = this.NAME;
			
			//Opening tag
			html = html.replace(/<a [^>]*id="([^"]+)"[^>]*>/gi, function (html, id) {
				if (!id) return html;
				var data = htmleditor.getData(id);
				
				if (data && data.type == NAME) {
					return '{supra.' + NAME + ' id="' + id + '"}';
				} else {
					return html;
				}
			});
			
			//Closing tag
			html = html.replace(/<\/a[^>]*>/g, '{/supra.' + NAME + '}');
			
			return html;
		},
		
		/**
		 * Process data and remove all unneeded before it's sent to server
		 * Called before save
		 * 
		 * @param {String} id Data ID
		 * @param {Object} data Data
		 * @return Processed data
		 * @type {Object}
		 */
		processData: function (id, data) {
			data.image = data.image.id;
			return data;
		}
		
	});
	
	
	//Since this widget has Supra namespace, it doesn't need to be bound to each YUI instance
	//Make sure this constructor function is called only once
	delete(this.fn); this.fn = function () {};
	
}, YUI.version, {'requires': ['supra.htmleditor-base']});